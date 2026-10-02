<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use PDO;
use Exception;

/**
 * Única fuente de verdad para "qué filas de `evaluaciones` deberían existir".
 *
 * Regla de negocio: todo aprendiz en formación debe tener una fila
 * 'pendiente' por cada RAP del programa de su ficha, de modo que el RAP
 * aparezca en /seguimiento y /evaluaciones y se pueda calificar.
 *
 * Antes esa regla solo se aplicaba en el momento de matricular
 * (`inicializarEvaluacionesAprendiz()`), lo que dejaba fuera el otro lado
 * del problema: los RAP creados o importados DESPUÉS de la matrícula no
 * llegaban a los aprendices ya matriculados. Este servicio cubre ambos
 * sentidos con la misma consulta, y por eso se invoca tanto al matricular
 * como al crear/importar RAP o al cambiar de programa una ficha.
 *
 * Es idempotente: solo crea lo que falta y nunca modifica una evaluación
 * existente (ni su concepto, ni su comentario, ni su fecha).
 */
class EvaluacionesSyncService {
    /**
     * Estados de aprendiz que cuentan como "en formación". 'desertado' y
     * 'egresado' quedan fuera a propósito: no se reabren expedientes
     * cerrados al importar estructura curricular nueva.
     */
    private const ESTADOS_EN_FORMACION = ['matriculado', 'suspendido', 'etapa_practica'];

    /**
     * Quién responde por una evaluación pendiente, con los alias `a`, `f` y
     * `c` en el ámbito. Es la misma prelación que InstructorAccessService
     * usa para el permiso de calificar: el instructor asignado a la
     * competencia en esa ficha; en etapa práctica, el de seguimiento del
     * aprendiz; y si no, el líder de la ficha.
     */
    private const RESPONSABLE = "COALESCE(
            (SELECT asg.instructor_id FROM asignaciones asg WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id LIMIT 1),
            CASE WHEN c.es_etapa_practica = 1 THEN a.instructor_seguimiento_id END,
            f.instructor_id)";

    /**
     * Quién CALIFICA hoy el RAP, con la misma regla que
     * InstructorAccessService: la asignación manda; si no hay, en etapa
     * práctica el instructor de seguimiento y fuera de ella el líder. A
     * diferencia de RESPONSABLE no cae en el líder para la etapa práctica
     * (el líder no la califica), así que puede dar NULL: nadie la califica.
     *
     * Es la regla de los planes de mejoramiento: su responsable puede
     * cerrarlo como cumplido y con eso aprobar el RAP.
     */
    private const QUIEN_CALIFICA = "COALESCE(
            (SELECT asg.instructor_id FROM asignaciones asg WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id LIMIT 1),
            CASE WHEN c.es_etapa_practica = 1 THEN a.instructor_seguimiento_id ELSE f.instructor_id END)";

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Crea las filas 'pendiente' que falten.
     *
     * @param array $scope Acota el trabajo. Sin claves, revisa todo el
     *   sistema. Claves admitidas: 'aprendiz_id', 'ficha_id',
     *   'competencia_id', 'programa_id'.
     * @return array{creadas:int, omitidas_sin_instructor:int}
     */
    public function sincronizar(array $scope = []): array {
        [$where, $params] = $this->construirFiltro($scope);

        // `evaluaciones.instructor_id` es NOT NULL y tiene clave foránea a
        // `usuarios.id`, así que una ficha sin instructor líder no puede
        // generar filas: se excluye en el JOIN y se cuenta aparte, en vez de
        // dejar que el INSERT falle en bloque. (Hoy todas las fichas tienen
        // instructor, pero el esquema permite que no.)
        $desde = "
              FROM aprendices a
              JOIN fichas f                   ON f.id = a.ficha_id
              JOIN competencias c             ON c.programa_id = f.programa_id
              JOIN resultados_aprendizaje ra  ON ra.competencia_id = c.id
             WHERE a.estado IN ({$this->placeholdersEstados()})
               $where
               AND NOT EXISTS (
                     SELECT 1 FROM evaluaciones e
                      WHERE e.aprendiz_id = a.id
                        AND e.resultado_aprendizaje_id = ra.id
                   )
        ";
        $paramsBase = array_merge(self::ESTADOS_EN_FORMACION, $params);

        // Cuántas quedarían fuera por no tener instructor líder (informativo).
        $stmt = $this->db->prepare("SELECT COUNT(*) $desde AND (f.instructor_id IS NULL OR f.instructor_id = 0)");
        $stmt->execute($paramsBase);
        $sinInstructor = (int)$stmt->fetchColumn();

        // El INSERT ... SELECT se hace en una sola sentencia: recorrer los
        // RAP en PHP suponía miles de idas y venidas a la base en cada
        // importación.
        $stmt = $this->db->prepare("
            INSERT INTO evaluaciones
                (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
            SELECT ra.id, a.id, " . self::RESPONSABLE . ", a.ficha_id, 'pendiente', NULL, NULL
            $desde
              AND f.instructor_id IS NOT NULL
              AND f.instructor_id <> 0
        ");
        $stmt->execute($paramsBase);

        return [
            'creadas' => $stmt->rowCount(),
            'omitidas_sin_instructor' => $sinInstructor,
        ];
    }

    /**
     * Pone a cada evaluación PENDIENTE el instructor que hoy responde por
     * ella (ver RESPONSABLE). Se llama tras cualquier cambio que mueva esa
     * responsabilidad: asignar o quitar una competencia, cambiar el líder de
     * una ficha, trasladar a un aprendiz o cambiar su instructor de
     * seguimiento. Los juicios ya emitidos conservan a su autor.
     *
     * Los planes de mejoramiento vigentes pasan a quien hoy califica su RAP
     * (QUIEN_CALIFICA); si nadie lo califica, conservan su responsable, que
     * de todos modos ya no puede cerrarlo (PlanesService exige el acceso).
     *
     * @param array $scope Mismas claves que sincronizar().
     * @return int Evaluaciones que cambiaron de responsable.
     */
    public function actualizarResponsables(array $scope = []): int {
        [$where, $params] = $this->construirFiltro($scope);
        $stmt = $this->db->prepare("
            UPDATE evaluaciones e
              JOIN aprendices a              ON a.id = e.aprendiz_id
              JOIN fichas f                  ON f.id = a.ficha_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c            ON c.id = ra.competencia_id
               SET e.instructor_id = " . self::RESPONSABLE . "
             WHERE e.concepto = 'pendiente'
               AND f.instructor_id IS NOT NULL AND f.instructor_id <> 0
               $where
        ");
        $stmt->execute($params);
        $cambiadas = $stmt->rowCount();

        $this->db->prepare("
            UPDATE planes_mejoramiento pm
              JOIN evaluaciones e            ON e.id = pm.evaluacion_id
              JOIN aprendices a              ON a.id = pm.aprendiz_id
              JOIN fichas f                  ON f.id = a.ficha_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c            ON c.id = ra.competencia_id
               SET pm.instructor_id = COALESCE(" . self::QUIEN_CALIFICA . ", pm.instructor_id)
             WHERE pm.estado IN ('abierto', 'en_curso')
               AND f.instructor_id IS NOT NULL AND f.instructor_id <> 0
               $where
        ")->execute($params);
        return $cambiadas;
    }

    /** Instructor que hoy califica el RAP de esa evaluación (ver QUIEN_CALIFICA), o null si nadie. */
    public function quienCalifica(int $evaluacionId): ?int {
        $st = $this->db->prepare("
            SELECT " . self::QUIEN_CALIFICA . "
              FROM evaluaciones e
              JOIN aprendices a              ON a.id = e.aprendiz_id
              JOIN fichas f                  ON f.id = a.ficha_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c            ON c.id = ra.competencia_id
             WHERE e.id = ?
        ");
        $st->execute([$evaluacionId]);
        $id = $st->fetchColumn();
        return $id === false || $id === null ? null : (int)$id;
    }

    /**
     * Borra las evaluaciones pendientes de la ficha cuyos RAP no son de su
     * programa. Se llama al cambiar el programa de una ficha sin juicios
     * emitidos: antes solo se creaban las del programa nuevo y las del
     * anterior se quedaban, de modo que el avance de esos aprendices se
     * calculaba sobre los RAP de dos programas.
     */
    public function retirarDeOtroPrograma(int $fichaId): int {
        $st = $this->db->prepare("
            DELETE e FROM evaluaciones e
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c            ON c.id = ra.competencia_id
              JOIN fichas f                  ON f.id = e.ficha_id
             WHERE e.ficha_id = ? AND e.concepto = 'pendiente' AND c.programa_id <> f.programa_id
        ");
        $st->execute([$fichaId]);
        return $st->rowCount();
    }

    /**
     * Cuenta lo que falta sin escribir nada. Útil para diagnóstico.
     *
     * @return array{faltantes:int, aprendices:int}
     */
    public function contarFaltantes(array $scope = []): array {
        [$where, $params] = $this->construirFiltro($scope);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS faltantes, COUNT(DISTINCT a.id) AS aprendices
              FROM aprendices a
              JOIN fichas f                   ON f.id = a.ficha_id
              JOIN competencias c             ON c.programa_id = f.programa_id
              JOIN resultados_aprendizaje ra  ON ra.competencia_id = c.id
             WHERE a.estado IN ({$this->placeholdersEstados()})
               $where
               AND NOT EXISTS (
                     SELECT 1 FROM evaluaciones e
                      WHERE e.aprendiz_id = a.id
                        AND e.resultado_aprendizaje_id = ra.id
                   )
        ");
        $stmt->execute(array_merge(self::ESTADOS_EN_FORMACION, $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'faltantes' => (int)($row['faltantes'] ?? 0),
            'aprendices' => (int)($row['aprendices'] ?? 0),
        ];
    }

    private function placeholdersEstados(): string {
        return implode(',', array_fill(0, count(self::ESTADOS_EN_FORMACION), '?'));
    }

    /**
     * @return array{0:string, 1:array} Fragmento SQL y sus parámetros.
     */
    private function construirFiltro(array $scope): array {
        $where = '';
        $params = [];
        $columnas = [
            'aprendiz_id'    => 'a.id',
            'ficha_id'       => 'a.ficha_id',
            'competencia_id' => 'c.id',
            'programa_id'    => 'f.programa_id',
        ];
        foreach ($columnas as $clave => $columna) {
            if (isset($scope[$clave]) && (int)$scope[$clave] > 0) {
                $where .= " AND $columna = ?";
                $params[] = (int)$scope[$clave];
            }
        }
        $desconocidas = array_diff(array_keys($scope), array_keys($columnas));
        if ($desconocidas) {
            throw new Exception('Ámbito no reconocido para sincronizar evaluaciones: ' . implode(', ', $desconocidas));
        }
        return [$where, $params];
    }
}
