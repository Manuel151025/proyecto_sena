<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Support\ErrorDeNegocio;
use Core\Database;
use PDO;
use Exception;

/**
 * Única puerta de escritura del juicio evaluativo (`evaluaciones.concepto`).
 *
 * El sistema existe para que una nota no se pueda cambiar sin dejar
 * constancia (RNF02). Esa garantía estaba repartida entre cuatro caminos
 * que escribían la misma columna con reglas distintas:
 *
 *   - SeguimientoModel::registrarEvaluacion   historial sí, transacción NO
 *   - EvaluacionesModel::actualizarEvaluacion historial sí, transacción NO
 *   - EvidenciasModel::calificarEvidencia     historial NO, transacción sí
 *   - JuiciosImportService                    historial solo al ACTUALIZAR
 *
 * El resultado medible: de 987 juicios emitidos (A o D), solo 15 tenían
 * historial. Calificar una evidencia cambiaba la nota sin rastro, y el
 * importador creaba cientos de juicios sin registrar de dónde salían.
 *
 * Aquí la regla queda en un solo sitio y es la misma para todos:
 *
 *   1. Todo pasa por una transacción. Si el llamador ya abrió una, se
 *      participa en ella (ver `enTransaccion`); nunca se anida.
 *   2. La fila se bloquea con SELECT ... FOR UPDATE antes de leer el
 *      concepto anterior. Sin esto, dos instructores calificando a la vez
 *      escribían un `concepto_anterior` falso en el historial.
 *   3. Se escribe historial si y solo si el concepto cambia de verdad.
 *      Una evaluación nueva parte de 'pendiente', así que crear un juicio
 *      'A' o 'D' también deja constancia; crear una fila 'pendiente' no
 *      genera ruido.
 *
 * Lo que NO hace: crear la rejilla de filas 'pendiente' de un aprendiz.
 * Eso es otra regla y vive en EvaluacionesSyncService.
 */
final class EvaluacionService {
    /** Motivo que se registra cuando no hay uno escrito por una persona. */
    public const MOTIVO_INICIAL = 'Calificación inicial';

    /** Conceptos admitidos por el enum de `evaluaciones.concepto`. */
    public const CONCEPTOS = ['A', 'D', 'pendiente'];

    /** Conceptos que ya son un juicio emitido y no se cambian sin explicar por qué. */
    private const CONCEPTOS_EMITIDOS = ['A', 'D'];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Registra el juicio de un RAP para un aprendiz, creando la evaluación
     * si todavía no existe. Es el camino de /seguimiento y de la matrícula.
     *
     * @param array $datos Claves obligatorias:
     *   - resultado_aprendizaje_id int
     *   - aprendiz_id              int
     *   - ficha_id                 int  (solo se usa al crear la fila)
     *   - concepto                 string  'A' | 'D' | 'pendiente'
     *   - usuario_id               int     quién ejecuta la acción (va al historial)
     *   Opcionales: ver `opciones()`.
     * @return array{evaluacion_id:int, accion:string} accion es
     *         'creada' | 'actualizada' | 'sin_cambios'.
     * @throws Exception Si faltan datos, el concepto no es válido, o se
     *         cambia un juicio ya emitido sin motivo.
     */
    public function registrar(array $datos): array {
        $raId       = (int)($datos['resultado_aprendizaje_id'] ?? 0);
        $aprendizId = (int)($datos['aprendiz_id'] ?? 0);
        $fichaId    = (int)($datos['ficha_id'] ?? 0);

        if ($raId <= 0 || $aprendizId <= 0 || $fichaId <= 0) {
            throw new ErrorDeNegocio('Datos de evaluación incompletos.');
        }

        return $this->enTransaccion(function () use ($raId, $aprendizId, $fichaId, $datos): array {
            // La búsqueda va por (RAP, aprendiz) y no por (RAP, aprendiz,
            // ficha) a propósito: ese es el índice UNIQUE real de la tabla
            // (`unique_eval`). Buscar incluyendo la ficha hacía que, tras un
            // traslado de ficha, no se encontrara la fila existente y el
            // INSERT reventara contra el índice.
            $stmt = $this->db->prepare("
                SELECT id, concepto FROM evaluaciones
                 WHERE resultado_aprendizaje_id = ? AND aprendiz_id = ?
                 FOR UPDATE
            ");
            $stmt->execute([$raId, $aprendizId]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                return $this->escribir((int)$fila['id'], (string)$fila['concepto'], $aprendizId, $datos);
            }
            return $this->crear($raId, $aprendizId, $fichaId, $datos);
        });
    }

    /**
     * Cambia el juicio de una evaluación que ya existe, localizada por id.
     * Es el camino de /evaluaciones y de la calificación de evidencias.
     *
     * @param array $datos Claves obligatorias: concepto, usuario_id.
     *        Opcionales: ver `opciones()`.
     * @return array{evaluacion_id:int, accion:string}
     * @throws Exception Si la evaluación no existe o falta el motivo.
     */
    public function actualizarPorId(int $evaluacionId, array $datos): array {
        if ($evaluacionId <= 0) {
            throw new ErrorDeNegocio('ID de evaluación inválido.');
        }

        return $this->enTransaccion(function () use ($evaluacionId, $datos): array {
            $stmt = $this->db->prepare(
                "SELECT id, concepto, aprendiz_id FROM evaluaciones WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$evaluacionId]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                throw new ErrorDeNegocio('Evaluación no encontrada.');
            }

            return $this->escribir(
                $evaluacionId,
                (string)$fila['concepto'],
                (int)$fila['aprendiz_id'],
                $datos
            );
        });
    }

    // -----------------------------------------------------------------
    // INTERNO
    // -----------------------------------------------------------------

    /**
     * Normaliza las claves opcionales, para que los cuatro llamadores no
     * tengan que repetir los mismos valores por defecto.
     *
     * - instructor_id:      a quién se le atribuye la nota. El importador
     *                       la atribuye al instructor de la ficha, no a
     *                       quien subió el Excel.
     * - fecha_evaluacion:   el importador usa la fecha del reporte, no hoy.
     * - comentario:         null significa "no tocar el que ya hay". El
     *                       importador no trae comentarios y no debe
     *                       borrar los que escribió un instructor.
     * - motivo:             texto de la persona. Si viene vacío se usa
     *                       motivo_por_defecto.
     * - exigir_motivo:      solo /seguimiento y /evaluaciones lo piden por
     *                       formulario. Evidencias e importación generan
     *                       uno descriptivo en vez de bloquear el flujo.
     * - retroalimentacion:  si se refleja el comentario en la bandeja del
     *                       aprendiz. Evidencias escribe la suya aparte
     *                       (puede no haber evaluación asociada) y el
     *                       importador no genera ninguna.
     * - notificar:          avisar al aprendiz cuando recibe un juicio A o
     *                       D. El importador lo desactiva y manda un aviso
     *                       por aprendiz en lugar de uno por RAP.
     */
    private function opciones(array $datos): array {
        $usuarioId = (int)($datos['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new ErrorDeNegocio('No se puede registrar una evaluación sin el usuario que la ejecuta.');
        }

        $concepto = (string)($datos['concepto'] ?? '');
        if (!in_array($concepto, self::CONCEPTOS, true)) {
            throw new ErrorDeNegocio('Concepto no válido.');
        }

        // Un juicio 'pendiente' no tiene fecha de evaluación: todavía no se
        // ha evaluado. Poner hoy ahí hacía que los listados mostraran como
        // "evaluado hoy" lo que nadie había mirado.
        $fecha = $datos['fecha_evaluacion'] ?? null;
        if ($fecha === null) {
            $fecha = $concepto === 'pendiente' ? null : date('Y-m-d');
        }

        return [
            'usuario_id'        => $usuarioId,
            'concepto'          => $concepto,
            'instructor_id'     => (int)($datos['instructor_id'] ?? $usuarioId),
            'fecha_evaluacion'  => $fecha !== null ? (string)$fecha : null,
            'comentario'        => array_key_exists('comentario', $datos) && $datos['comentario'] !== null
                                        ? (string)$datos['comentario']
                                        : null,
            'motivo'            => trim((string)($datos['motivo'] ?? '')),
            'motivo_por_defecto'=> (string)($datos['motivo_por_defecto'] ?? self::MOTIVO_INICIAL),
            'exigir_motivo'     => (bool)($datos['exigir_motivo'] ?? true),
            'retroalimentacion' => (bool)($datos['retroalimentacion'] ?? true),
            'notificar'         => (bool)($datos['notificar'] ?? true),
        ];
    }

    /**
     * Crea la evaluación. El concepto anterior es 'pendiente' porque hasta
     * ahora no había juicio; así una nota importada o puesta de primeras
     * también queda registrada en el historial.
     */
    private function crear(int $raId, int $aprendizId, int $fichaId, array $datos): array {
        $o = $this->opciones($datos);

        $stmt = $this->db->prepare("
            INSERT INTO evaluaciones
                (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id,
                 concepto, comentario, fecha_evaluacion)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $raId, $aprendizId, $o['instructor_id'], $fichaId,
            $o['concepto'], $o['comentario'], $o['fecha_evaluacion'],
        ]);

        $evaluacionId = (int)$this->db->lastInsertId();

        $this->registrarCambio($evaluacionId, 'pendiente', $o);
        $this->registrarRetroalimentacion($evaluacionId, $aprendizId, $o, $datos);
        if ($o['concepto'] !== 'pendiente') {
            $this->notificarAprendiz($evaluacionId, 'pendiente', $o);
        }

        return ['evaluacion_id' => $evaluacionId, 'accion' => 'creada'];
    }

    /**
     * Actualiza una evaluación existente y deja constancia si el concepto
     * cambia. Si no cambia, se refresca el comentario y la autoría sin
     * generar una fila de historial que no diría nada.
     */
    private function escribir(int $evaluacionId, string $conceptoAnterior, int $aprendizId, array $datos): array {
        $o = $this->opciones($datos);
        $cambia = $conceptoAnterior !== $o['concepto'];

        if ($cambia && $o['exigir_motivo']
            && in_array($conceptoAnterior, self::CONCEPTOS_EMITIDOS, true)
            && $o['motivo'] === ''
        ) {
            throw new ErrorDeNegocio('El motivo del cambio de calificación es requerido.');
        }

        // `comentario = null` significa "no tocar": el importador no trae
        // comentarios y borrarlos perdería el trabajo del instructor.
        $sets   = ['instructor_id = ?', 'fecha_actualizacion = NOW()'];
        $params = [$o['instructor_id']];

        if ($cambia) {
            array_unshift($sets, 'concepto = ?');
            array_unshift($params, $o['concepto']);
            $sets[]   = 'fecha_evaluacion = ?';
            $params[] = $o['fecha_evaluacion'];
        }
        if ($o['comentario'] !== null) {
            $sets[]   = 'comentario = ?';
            $params[] = $o['comentario'];
        }
        $params[] = $evaluacionId;

        $stmt = $this->db->prepare('UPDATE evaluaciones SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);

        if ($cambia) {
            $this->registrarCambio($evaluacionId, $conceptoAnterior, $o);
            $this->notificarAprendiz($evaluacionId, $conceptoAnterior, $o);
        }
        $this->registrarRetroalimentacion($evaluacionId, $aprendizId, $o, $datos);

        return [
            'evaluacion_id' => $evaluacionId,
            'accion'        => $cambia ? 'actualizada' : 'sin_cambios',
        ];
    }

    /**
     * Escribe la fila de historial. Solo se llama cuando el concepto
     * cambió de verdad, así que aquí no se vuelve a comprobar.
     */
    private function registrarCambio(int $evaluacionId, string $conceptoAnterior, array $o): void {
        if ($conceptoAnterior === $o['concepto']) {
            return;
        }
        $stmt = $this->db->prepare("
            INSERT INTO historial_evaluaciones
                (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $evaluacionId,
            $o['usuario_id'],
            $conceptoAnterior,
            $o['concepto'],
            $o['motivo'] !== '' ? $o['motivo'] : $o['motivo_por_defecto'],
        ]);
    }

    /**
     * Aviso al aprendiz de que tiene un juicio nuevo o cambiado (A o D).
     * Volver a pendiente no se anuncia: no es un resultado.
     */
    private function notificarAprendiz(int $evaluacionId, string $anterior, array $o): void {
        if (!$o['notificar'] || $o['concepto'] === 'pendiente') {
            return;
        }
        $st = $this->db->prepare("
            SELECT ap.usuario_id, ra.codigo FROM evaluaciones e
              JOIN aprendices ap ON ap.id = e.aprendiz_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
             WHERE e.id = ?");
        $st->execute([$evaluacionId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || !$r['usuario_id']) {
            return;
        }
        $texto = $o['concepto'] === 'A' ? 'Aprobado (A)' : 'No aprobado (D)';
        $titulo = $anterior === 'pendiente' ? 'Nuevo juicio evaluativo' : 'Juicio evaluativo modificado';
        (new Notificador($this->db))->notificar((int)$r['usuario_id'], $titulo,
            "RAP {$r['codigo']}: $texto." . ($o['concepto'] === 'D' ? ' Revisa la retroalimentación y tu plan de mejoramiento.' : ''),
            $o['concepto'] === 'A' ? 'success' : 'warning', '/index.php/evaluaciones');
    }

    /**
     * Refleja el comentario en la bandeja de retroalimentación del aprendiz.
     * Sin esto el comentario quedaba "atrapado" en `evaluaciones.comentario`
     * y el aprendiz nunca lo veía.
     */
    private function registrarRetroalimentacion(int $evaluacionId, int $aprendizId, array $o, array $datos): void {
        if (!$o['retroalimentacion'] || $o['comentario'] === null || trim($o['comentario']) === '') {
            return;
        }
        $tipo = (string)($datos['tipo_retroalimentacion']
            ?? ($o['concepto'] === 'A' ? 'fortaleza' : 'aspecto_mejorar'));

        $this->db->prepare("
            INSERT INTO retroalimentacion (evaluacion_id, aprendiz_id, instructor_id, tipo, contenido)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$evaluacionId, $aprendizId, $o['instructor_id'], $tipo, $o['comentario']]);
    }

    /**
     * Ejecuta el trabajo dentro de una transacción.
     *
     * Si el llamador ya abrió una (EvidenciasModel::calificarEvidencia y
     * JuiciosImportService lo hacen), se participa en ella: su commit o su
     * rollBack manda. PDO no anida transacciones, así que abrir otra aquí
     * cerraría la del llamador a mitad de su propio trabajo.
     */
    private function enTransaccion(callable $fn): array {
        if ($this->db->inTransaction()) {
            return $fn();
        }

        $this->db->beginTransaction();
        try {
            $resultado = $fn();
            $this->db->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
