<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Database;
use Core\Formularios\UsuarioFormulario;
use Core\Models\AprendizModel;
use Core\Services\Auditoria;
use Core\Services\EvaluacionesSyncService;
use Core\Services\EvaluacionService;
use Core\Services\InstructorAccessService;
use Core\Services\Notificador;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\PoliticaContrasena;
use Core\Support\Transaccion;
use Core\Support\Validador;
use PDO;

/**
 * Juicios evaluativos desde el reporte de Sofia Plus (XLS, XLSX o CSV).
 *
 * El reporte trae arriba la ficha y el programa, y después una fila por
 * aprendiz y RAP: tipo y número de documento, nombres, apellidos, estado,
 * competencia, resultado de aprendizaje, juicio, fecha y funcionario.
 *
 * Qué cambió respecto del importador anterior (JuiciosImportService):
 *  - "NO APROBADO" contiene "APROBADO", y se comprobaba primero: todo juicio
 *    no aprobado del reporte entraba como A.
 *  - Un "POR EVALUAR" del reporte devolvía a pendiente un juicio ya
 *    emitido en el sistema. Ahora un juicio emitido nunca se borra desde
 *    aquí: solo se emite o se cambia (con historial).
 *  - Creaba fichas (con el primer instructor que encontrara como líder y el
 *    coordinador 1), programas, competencias y RAP desde el archivo de
 *    cualquier instructor. Ahora la ficha tiene que existir; la estructura
 *    que falte solo la crea la coordinación, y avisa antes de hacerlo.
 *  - Movía de ficha a aprendices matriculados en otra, sin las reglas del
 *    traslado. Ahora se señala y el traslado se hace en Matrículas.
 *  - El instructor podía cargar juicios de competencias asignadas a otro
 *    instructor. Ahora solo los que él califica (misma regla que el resto
 *    del sistema: InstructorAccessService).
 *  - Se subía en base64 por AJAX a `uploads/` (servida por la web) y las
 *    copias quedaban allí si algo fallaba. Ahora pasa por la importación
 *    en dos pasos, con vista previa y sin copias.
 */
final class ImportadorJuicios extends Importador {
    public const MOTIVO = 'Importado desde el reporte de juicios de Sofia Plus';

    private PDO $db;

    /** Datos de la ficha para validar filas (se cargan una vez). */
    private ?array $aprendices = null;   // documento => [id, estado]
    private ?array $raps = null;         // "comp|rap" => [id, competencia_id]
    private ?array $competencias = null; // codigo => id
    private ?array $conceptos = null;    // "aprendiz|rap" => concepto
    private ?array $permitidos = null;   // "aprendiz|rap" => true (solo instructor)
    private array $enOtraFicha = [];

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function clave(): string { return 'juicios'; }
    public function titulo(): string { return 'juicios evaluativos (Sofia Plus)'; }
    public function maxFilas(): int { return 8000; }

    public function permitido(Actor $actor): bool {
        return $actor->esCoordinador() || $actor->esInstructor();
    }

    /** En el orden del reporte de Sofia Plus. */
    public function columnas(): array {
        return [
            'tipo_documento'   => ['etiqueta' => 'Tipo de documento', 'alias' => ['tipo_doc']],
            'numero_documento' => ['etiqueta' => 'Número de documento', 'alias' => ['documento', 'numero_doc'], 'obligatorio' => true],
            'nombre'           => ['etiqueta' => 'Nombre', 'alias' => ['nombres']],
            'apellidos'        => ['etiqueta' => 'Apellidos', 'alias' => ['apellido']],
            'estado'           => ['etiqueta' => 'Estado', 'alias' => ['estado_aprendiz']],
            'competencia'      => ['etiqueta' => 'Competencia', 'obligatorio' => true, 'ayuda' => '"220501046 - Nombre de la competencia"'],
            'resultado'        => ['etiqueta' => 'Resultado de aprendizaje', 'alias' => ['rap', 'resultado_aprendizaje'], 'obligatorio' => true,
                                   'ayuda' => '"220501046 - 01 Denominación del RAP"'],
            'juicio'           => ['etiqueta' => 'Juicio de evaluación', 'alias' => ['juicio_evaluacion', 'concepto'], 'obligatorio' => true,
                                   'ayuda' => 'APROBADO, NO APROBADO / DEFICIENTE o POR EVALUAR'],
            'fecha'            => ['etiqueta' => 'Fecha del juicio', 'alias' => ['fecha_juicio'], 'ayuda' => 'DD/MM/AAAA'],
            'funcionario'      => ['etiqueta' => 'Funcionario que registró', 'alias' => ['instructor']],
        ];
    }

    public function ejemplo(): array {
        return ['tipo_documento' => 'CC', 'numero_documento' => '1117500123', 'nombre' => 'LAURA CAMILA', 'apellidos' => 'VARGAS RUIZ',
                'estado' => 'EN FORMACION', 'competencia' => '220501046 - Utilizar herramientas informáticas',
                'resultado' => '220501046 - 01 Configurar el equipo según el manual', 'juicio' => 'APROBADO',
                'fecha' => date('d/m/Y'), 'funcionario' => ''];
    }

    public function instrucciones(): array {
        return [
            'Sube el reporte de juicios evaluativos de Sofia Plus tal como se descarga (.xls); también sirve en .xlsx o .csv.',
            'La ficha se toma del reporte y tiene que existir en el sistema. Si tu archivo no la trae, elígela arriba.',
            'Un juicio ya emitido nunca vuelve a «pendiente» desde aquí; si el reporte lo cambia de A a D (o al revés), queda en el historial.',
            'Instructores: solo se cargan los RAP que calificas en esa ficha. Coordinación: la estructura y los aprendices que falten se crean, con aviso previo.',
        ];
    }

    public function columnasVistaPrevia(): array {
        return ['numero_documento', 'nombre', 'apellidos', 'resultado', 'juicio', 'fecha'];
    }

    // -----------------------------------------------------------------
    // FORMULARIO Y CONTEXTO
    // -----------------------------------------------------------------

    public function opcionesFormulario(Actor $actor): array {
        $sql = "SELECT f.id, f.numero_ficha, p.codigo FROM fichas f JOIN programas p ON p.id = f.programa_id";
        $params = [];
        if ($actor->esInstructor()) {
            $sql .= " WHERE f.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            $params = [$actor->id, $actor->id, $actor->id];
        }
        $st = $this->db->prepare($sql . " ORDER BY f.numero_ficha");
        $st->execute($params);
        $opciones = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $opciones[(int)$f['id']] = 'Ficha ' . $f['numero_ficha'] . ' (' . $f['codigo'] . ')';
        }
        return ['campos' => [[
            'nombre' => 'ficha_id', 'etiqueta' => 'Ficha', 'opciones' => $opciones, 'opcional' => true,
            'vacio' => 'La que indique el reporte',
            'ayuda' => 'El reporte de Sofia Plus trae el número de ficha; elígela solo si tu archivo no lo incluye.',
        ]]];
    }

    public function contexto(array $post, Actor $actor): array {
        $v = new Validador($post);
        $ficha = $v->id('ficha_id', 'La ficha', false);
        return [['ficha_id' => $ficha], $v->errores()];
    }

    /**
     * Lee la cabecera del reporte (ficha y programa), localiza la fila de
     * títulos y resuelve la ficha. Devuelve las filas desde los títulos.
     */
    public function prepararFilas(array $filas, array $contexto, Actor $actor): array {
        $inicio = null;
        $meta = ['ficha' => '', 'programa' => ''];
        foreach (array_slice($filas, 0, 40, true) as $i => $fila) {
            $celdas = array_map(static fn($c) => trim((string)$c), $fila);
            $n0 = LectorTabular::normalizarEncabezado($celdas[0] ?? '');
            $n1 = LectorTabular::normalizarEncabezado($celdas[1] ?? '');
            if (str_contains($n0, 'tipo') && str_contains($n1, 'documento')) {
                $inicio = $i;
                break;
            }
            $this->leerMetadato($celdas, $i, $meta);
        }
        if ($inicio === null) {
            throw new ErrorDeNegocio('No se encontró la fila de títulos del reporte («Tipo de documento», «Número de documento», …). '
                . 'Descarga el reporte de juicios de Sofia Plus sin modificarlo o usa la plantilla.');
        }
        $filas = array_slice($filas, $inicio);
        $filas[0] = $this->encabezadosCanonicos($filas[0]);

        $ficha = $this->resolverFicha((int)($contexto['ficha_id'] ?? 0), $meta['ficha'], $actor);
        $avisos = [];
        if ($meta['programa'] !== '' && strcasecmp($meta['programa'], $ficha['programa_codigo']) !== 0) {
            throw new ErrorDeNegocio("El reporte es del programa {$meta['programa']} y la ficha {$ficha['numero_ficha']} es del programa {$ficha['programa_codigo']}.");
        }
        if ($ficha['estado'] === 'cierre') {
            $avisos[] = "La ficha {$ficha['numero_ficha']} está en cierre: los juicios se registrarán igualmente.";
        }
        return ['filas' => $filas, 'contexto' => ['ficha' => $ficha], 'avisos' => $avisos];
    }

    /**
     * Metadatos de la cabecera. Por etiqueta («Ficha…», «Código…») y, si el
     * reporte no las trae, por la posición conocida (filas 3 y 4, columna C).
     */
    private function leerMetadato(array $celdas, int $indice, array &$meta): void {
        foreach ($celdas as $k => $c) {
            $n = LectorTabular::normalizarEncabezado($c);
            if ($n === '') {
                continue;
            }
            $valor = '';
            foreach (array_slice($celdas, $k + 1) as $siguiente) {
                if ($siguiente !== '') {
                    $valor = $siguiente;
                    break;
                }
            }
            if ($meta['ficha'] === '' && str_contains($n, 'ficha') && preg_match('/\d{4,}/', $valor, $m)) {
                $meta['ficha'] = $m[0];
            } elseif ($meta['programa'] === '' && str_starts_with($n, 'codigo') && preg_match('/^[A-Za-z0-9\-]{2,20}$/', $valor)) {
                $meta['programa'] = $valor;
            }
            break;   // la etiqueta es la primera celda con texto de la fila
        }
        if ($meta['ficha'] === '' && $indice === 2 && preg_match('/^\d{4,}$/', $celdas[2] ?? '')) {
            $meta['ficha'] = $celdas[2];
        }
        if ($meta['programa'] === '' && $indice === 3 && preg_match('/^[A-Za-z0-9\-]{2,20}$/', $celdas[2] ?? '')) {
            $meta['programa'] = $celdas[2];
        }
    }

    /**
     * Los títulos de Sofia Plus son largos («Fecha y hora del juicio
     * evaluativo», «Funcionario que registró el juicio»): se traducen a las
     * claves de la plantilla por la palabra que los identifica.
     */
    private function encabezadosCanonicos(array $fila): array {
        $reglas = [
            'juicio' => 'juicio', 'fecha' => 'fecha', 'funcionario' => 'funcionario', 'instructor' => 'funcionario',
            'resultado' => 'resultado', 'competencia' => 'competencia', 'apellido' => 'apellidos',
            'estado' => 'estado', 'tipo' => 'tipo_documento', 'documento' => 'numero_documento', 'nombre' => 'nombre',
        ];
        $usadas = [];
        foreach ($fila as $i => $titulo) {
            $n = LectorTabular::normalizarEncabezado((string)$titulo);
            foreach ($reglas as $palabra => $clave) {
                if (str_contains($n, $palabra) && !isset($usadas[$clave])) {
                    $fila[$i] = $clave;
                    $usadas[$clave] = true;
                    break;
                }
            }
        }
        return $fila;
    }

    private function resolverFicha(int $elegida, string $delReporte, Actor $actor): array {
        $sql = "SELECT f.id, f.numero_ficha, f.programa_id, f.instructor_id, f.estado, p.codigo AS programa_codigo
                  FROM fichas f JOIN programas p ON p.id = f.programa_id WHERE ";
        if ($elegida > 0) {
            $st = $this->db->prepare($sql . "f.id = ?");
            $st->execute([$elegida]);
            $ficha = $st->fetch(PDO::FETCH_ASSOC) ?: throw new ErrorDeNegocio('La ficha elegida no existe.');
            if ($delReporte !== '' && $delReporte !== (string)$ficha['numero_ficha']) {
                throw new ErrorDeNegocio("El reporte es de la ficha $delReporte y elegiste la ficha {$ficha['numero_ficha']}.");
            }
        } elseif ($delReporte !== '') {
            $st = $this->db->prepare($sql . "f.numero_ficha = ?");
            $st->execute([$delReporte]);
            $ficha = $st->fetch(PDO::FETCH_ASSOC)
                ?: throw new ErrorDeNegocio("La ficha $delReporte del reporte no existe en el sistema. La coordinación debe crearla primero en Fichas.");
        } else {
            throw new ErrorDeNegocio('El archivo no indica la ficha: elígela en el formulario.');
        }
        if ($actor->esInstructor() && !(new InstructorAccessService($this->db))->tieneAccesoFicha((int)$ficha['id'], $actor->id)) {
            throw new ErrorDeNegocio("No tienes a cargo la ficha {$ficha['numero_ficha']}.");
        }
        foreach (['id', 'programa_id', 'instructor_id'] as $k) {
            $ficha[$k] = (int)$ficha[$k];
        }
        return $ficha;
    }

    // -----------------------------------------------------------------
    // VALIDACIÓN POR FILA
    // -----------------------------------------------------------------

    public function validarFila(array $fila, Actor $actor, array $contexto): array {
        $ficha = $contexto['ficha'];
        $this->cargar($ficha, $actor);
        $errores = [];
        $avisos = [];

        $doc = preg_replace('/[\s.\-]/', '', $fila['numero_documento']) ?? '';
        if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $doc)) {
            $errores[] = 'El número de documento no es válido.';
        }
        $comp = self::codigoCompetencia($fila['competencia']);
        $rap = self::codigoRap($fila['resultado']);
        if ($comp === null) {
            $errores[] = 'No se reconoce el código de la competencia (se espera «220501046 - Nombre»).';
        }
        if ($rap === null) {
            $errores[] = 'No se reconoce el código del resultado de aprendizaje (se espera «220501046 - 01 Denominación»).';
        }
        $concepto = self::concepto($fila['juicio']);
        if ($concepto === null) {
            $errores[] = "Juicio no reconocido: «" . mb_substr($fila['juicio'], 0, 40) . "». Se espera APROBADO, NO APROBADO o POR EVALUAR.";
        }
        if ($errores !== []) {
            return ['datos' => [], 'errores' => $errores];
        }

        $datos = [
            'documento' => $doc, 'concepto' => $concepto, 'accion' => 'emitir',
            'competencia_codigo' => $comp[0], 'competencia_nombre' => $comp[1],
            'rap_codigo' => $rap[0], 'rap_denominacion' => $rap[1],
            'fecha' => $concepto === 'pendiente' ? null : $this->fecha($fila['fecha'], $avisos),
            'aprendiz_id' => null, 'ra_id' => null, 'nuevo_aprendiz' => null,
        ];

        // Aprendiz
        $ap = $this->aprendices[$doc] ?? null;
        if ($ap !== null) {
            $datos['aprendiz_id'] = $ap['id'];
            if (in_array($ap['estado'], ['desertado', 'egresado'], true)) {
                $avisos[] = "El aprendiz está {$ap['estado']} en el sistema.";
            }
        } elseif (($otra = $this->fichaDe($doc)) !== null) {
            $errores[] = "El documento $doc está matriculado en la ficha $otra: trasládelo desde Matrículas antes de importar sus juicios.";
        } elseif ($actor->esCoordinador()) {
            $nombre = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $fila['nombre'] . ' ' . $fila['apellidos']) ?? ''), 'UTF-8');
            if (!preg_match(Validador::PATRON_PERSONA, $nombre) || mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
                $errores[] = 'No está matriculado y el nombre no es válido para matricularlo.';
            } else {
                $tipo = mb_strtoupper(trim($fila['tipo_documento']), 'UTF-8');
                $datos['nuevo_aprendiz'] = ['nombre' => $nombre, 'tipo_documento' => in_array($tipo, ['CC', 'TI', 'CE', 'PEP', 'PA'], true) ? $tipo : 'CC',
                                            'desertado' => self::esRetiro($fila['estado'])];
                $avisos[] = 'Se matriculará en la ficha (sin correo: complételo en Matrículas).';
            }
        } else {
            $errores[] = "El documento $doc no está matriculado en la ficha. Pida a la coordinación que lo matricule.";
        }
        if ($ap !== null && self::esRetiro($fila['estado']) && !in_array($ap['estado'], ['desertado', 'egresado'], true)) {
            $avisos[] = 'El reporte lo marca como retirado; actualice su matrícula si corresponde.';
        }

        // RAP
        $clave = $comp[0] . '|' . $rap[0];
        $ra = $this->raps[$clave] ?? null;
        if ($ra !== null) {
            $datos['ra_id'] = $ra['id'];
        } elseif ($actor->esCoordinador()) {
            $avisos[] = isset($this->competencias[$comp[0]])
                ? "Se creará el RAP {$rap[0]} en la competencia {$comp[0]}."
                : "Se crearán la competencia {$comp[0]} y el RAP {$rap[0]}.";
        } else {
            $errores[] = "El RAP {$rap[0]} de la competencia {$comp[0]} no existe en el programa. La coordinación debe cargar la estructura curricular.";
        }

        // Juicio actual y permiso
        if ($errores === [] && $datos['aprendiz_id'] !== null && $datos['ra_id'] !== null) {
            $par = $datos['aprendiz_id'] . '|' . $datos['ra_id'];
            if ($actor->esInstructor() && !isset($this->permitidos[$par])) {
                $errores[] = isset($this->conceptos[$par])
                    ? 'Este RAP lo califica otro instructor en la ficha (asignación o seguimiento de etapa práctica).'
                    : 'El aprendiz no tiene este RAP en su rejilla de evaluación (¿está desertado o egresado?).';
            }
            $actual = $this->conceptos[$par] ?? 'pendiente';
            if ($actual === $concepto) {
                $datos['accion'] = 'sin_cambios';
            } elseif ($concepto === 'pendiente') {
                $datos['accion'] = 'sin_cambios';
                $avisos[] = "En el sistema ya tiene juicio $actual: no se borra un juicio emitido.";
            } elseif ($actual !== 'pendiente') {
                $datos['accion'] = 'cambiar';
                $avisos[] = "Cambia de $actual a $concepto (queda en el historial).";
            }
        } elseif ($concepto === 'pendiente') {
            $datos['accion'] = 'sin_cambios';
        }

        return ['datos' => $datos, 'errores' => $errores, 'avisos' => $avisos, 'clave' => $doc . '|' . $clave];
    }

    public function resumen(array $filas, array $contexto): array {
        $n = ['emitir' => 0, 'cambiar' => 0, 'sin_cambios' => 0];
        $nuevos = [];
        foreach ($filas as $f) {
            if ($f['errores'] === [] && $f['datos'] !== null) {
                $n[$f['datos']['accion']]++;
                if ($f['datos']['nuevo_aprendiz'] !== null) {
                    $nuevos[$f['datos']['documento']] = true;
                }
            }
        }
        $r = [['Ficha', $contexto['ficha']['numero_ficha'] . ' · ' . $contexto['ficha']['programa_codigo']],
              ['Juicios nuevos', $n['emitir']], ['Juicios que cambian', $n['cambiar']], ['Sin cambios', $n['sin_cambios']]];
        if ($nuevos !== []) {
            $r[] = ['Aprendices por matricular', count($nuevos)];
        }
        return $r;
    }

    // -----------------------------------------------------------------
    // GUARDAR
    // -----------------------------------------------------------------

    public function guardar(array $datos, Actor $actor, array $contexto): array {
        $ficha = $contexto['ficha'];
        @set_time_limit(300);

        return Transaccion::ejecutar($this->db, function () use ($datos, $actor, $ficha) {
            // Se vuelve a leer todo: entre la vista previa y la confirmación
            // alguien pudo calificar, matricular o cambiar una asignación.
            $this->aprendices = $this->raps = $this->competencias = $this->conceptos = $this->permitidos = null;
            $this->cargar($ficha, $actor);
            $creados = ['aprendices' => 0, 'competencias' => 0, 'raps' => 0];
            $credenciales = [];

            if ($actor->esCoordinador()) {
                $credenciales = $this->crearFaltantes($datos, $ficha, $creados);
                if (array_sum($creados) > 0) {
                    (new EvaluacionesSyncService($this->db))->sincronizar(['ficha_id' => $ficha['id']]);
                    $this->aprendices = $this->raps = $this->competencias = $this->conceptos = null;
                    $this->cargar($ficha, $actor);
                }
            }

            $servicio = new EvaluacionService($this->db);
            $responsable = $this->responsables($ficha['id']);
            $n = ['emitidos' => 0, 'cambiados' => 0, 'sin_cambios' => 0, 'omitidos' => 0];
            $afectados = [];
            foreach ($datos as $d) {
                $apId = $this->aprendices[$d['documento']]['id'] ?? null;
                $raId = $this->raps[$d['competencia_codigo'] . '|' . $d['rap_codigo']]['id'] ?? null;
                if ($apId === null || $raId === null) {
                    $n['omitidos']++;
                    continue;
                }
                $par = $apId . '|' . $raId;
                if ($actor->esInstructor() && !isset($this->permitidos[$par])) {
                    $n['omitidos']++;
                    continue;
                }
                $actual = $this->conceptos[$par] ?? 'pendiente';
                if ($d['concepto'] === 'pendiente' || $actual === $d['concepto']) {
                    $n['sin_cambios']++;
                    continue;
                }
                $servicio->registrar([
                    'resultado_aprendizaje_id' => $raId,
                    'aprendiz_id'   => $apId,
                    'ficha_id'      => $ficha['id'],
                    'concepto'      => $d['concepto'],
                    'usuario_id'    => $actor->id,
                    'instructor_id' => $responsable[$par] ?? $ficha['instructor_id'],
                    'fecha_evaluacion' => $d['fecha'],
                    'motivo'        => self::MOTIVO,
                    'exigir_motivo' => false,
                    'retroalimentacion' => false,
                    'notificar'     => false,
                ]);
                $n[$actual === 'pendiente' ? 'emitidos' : 'cambiados']++;
                $afectados[$apId] = ($afectados[$apId] ?? 0) + 1;
            }

            $this->notificarAprendices($afectados, $ficha);
            $detalle = ["{$n['emitidos']} juicios nuevos y {$n['cambiados']} cambiados en la ficha {$ficha['numero_ficha']}."];
            if ($n['sin_cambios'] > 0) {
                $detalle[] = "{$n['sin_cambios']} filas ya coincidían con el sistema.";
            }
            if (array_sum($creados) > 0) {
                $detalle[] = "Se matricularon {$creados['aprendices']} aprendices y se crearon {$creados['competencias']} competencias y {$creados['raps']} RAP.";
            }
            if ($n['omitidos'] > 0) {
                $detalle[] = "{$n['omitidos']} filas se omitieron porque los datos cambiaron desde la vista previa.";
            }
            (new Auditoria($this->db))->operacion($actor, 'Importar', 'Evaluaciones', 'evaluaciones', null,
                "Juicios de Sofia Plus en la ficha {$ficha['numero_ficha']}: {$n['emitidos']} nuevos, {$n['cambiados']} cambiados");

            return ['creados' => $n['emitidos'] + $n['cambiados'], 'omitidos' => $n['sin_cambios'] + $n['omitidos'],
                    'detalle' => $detalle, 'credenciales' => $credenciales];
        });
    }

    /** Competencias, RAP y aprendices que faltan (solo coordinación). */
    private function crearFaltantes(array $datos, array $ficha, array &$creados): array {
        $credenciales = [];
        $insComp = $this->db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, estado) VALUES (?, ?, ?, 'activo')");
        $insRap = $this->db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
        $aprendices = new AprendizModel($this->db);
        $colores = UsuarioFormulario::COLORES;
        foreach ($datos as $d) {
            $c = $d['competencia_codigo'];
            if (!isset($this->competencias[$c])) {
                $insComp->execute([$ficha['programa_id'], $c, mb_substr($d['competencia_nombre'] ?: "Competencia $c", 0, 255)]);
                $this->competencias[$c] = (int)$this->db->lastInsertId();
                $creados['competencias']++;
            }
            $clave = $c . '|' . $d['rap_codigo'];
            if (!isset($this->raps[$clave])) {
                $insRap->execute([$this->competencias[$c], $d['rap_codigo'], $d['rap_denominacion'] ?: 'Resultado ' . $d['rap_codigo']]);
                $this->raps[$clave] = ['id' => (int)$this->db->lastInsertId(), 'competencia_id' => $this->competencias[$c]];
                $creados['raps']++;
            }
            $nuevo = $d['nuevo_aprendiz'];
            if ($nuevo !== null && !isset($this->aprendices[$d['documento']]) && $this->fichaDe($d['documento']) === null) {
                $email = strtolower($d['documento']) . '@sin-correo.invalid';
                if ($aprendices->existeEmail($email)) {
                    continue;
                }
                $temporal = PoliticaContrasena::temporal();
                $id = $aprendices->crear([
                    'nombre' => $nuevo['nombre'], 'email' => $email, 'ficha_id' => $ficha['id'], 'instructor_seguimiento_id' => null,
                    'numero_documento' => $d['documento'], 'tipo_documento' => $nuevo['tipo_documento'], 'genero' => 'O',
                    'fecha_nacimiento' => null, 'telefono' => '', 'ciudad' => '',
                ], password_hash($temporal, PASSWORD_DEFAULT), $colores[$creados['aprendices'] % count($colores)]);
                if ($nuevo['desertado']) {
                    $aprendices->marcarDesertado($id);
                    $this->db->prepare("UPDATE usuarios u JOIN aprendices a ON a.usuario_id = u.id SET u.estado = 'inactivo' WHERE a.id = ?")->execute([$id]);
                } else {
                    $credenciales[] = ['nombre' => $nuevo['nombre'], 'email' => $email, 'password' => $temporal];
                }
                $this->aprendices[$d['documento']] = ['id' => $id, 'estado' => $nuevo['desertado'] ? 'desertado' : 'matriculado'];
                $creados['aprendices']++;
            }
        }
        return $credenciales;
    }

    /** Un aviso por aprendiz, no uno por juicio: un reporte trae cientos. */
    private function notificarAprendices(array $afectados, array $ficha): void {
        if ($afectados === []) {
            return;
        }
        $marcas = implode(',', array_fill(0, count($afectados), '?'));
        $st = $this->db->prepare("SELECT id, usuario_id FROM aprendices WHERE id IN ($marcas)");
        $st->execute(array_keys($afectados));
        $notificador = new Notificador($this->db);
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $apId => $usuarioId) {
            $k = $afectados[(int)$apId];
            $notificador->notificar((int)$usuarioId, 'Juicios actualizados',
                "Se registraron $k juicio(s) de tu ficha {$ficha['numero_ficha']} desde Sofia Plus.", 'info', '/index.php/evaluaciones');
        }
    }

    // -----------------------------------------------------------------
    // CARGA DE DATOS DE LA FICHA
    // -----------------------------------------------------------------

    private function cargar(array $ficha, Actor $actor): void {
        if ($this->aprendices !== null) {
            return;
        }
        $st = $this->db->prepare("SELECT numero_documento, id, estado FROM aprendices WHERE ficha_id = ?");
        $st->execute([$ficha['id']]);
        $this->aprendices = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $this->aprendices[$a['numero_documento']] = ['id' => (int)$a['id'], 'estado' => $a['estado']];
        }

        $st = $this->db->prepare("
            SELECT c.id AS competencia_id, c.codigo AS comp, ra.id, ra.codigo AS rap
              FROM competencias c LEFT JOIN resultados_aprendizaje ra ON ra.competencia_id = c.id
             WHERE c.programa_id = ?");
        $st->execute([$ficha['programa_id']]);
        $this->competencias = $this->raps = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $this->competencias[$r['comp']] = (int)$r['competencia_id'];
            if ($r['id'] !== null) {
                $this->raps[$r['comp'] . '|' . $r['rap']] = ['id' => (int)$r['id'], 'competencia_id' => (int)$r['competencia_id']];
            }
        }

        $st = $this->db->prepare("SELECT CONCAT(aprendiz_id, '|', resultado_aprendizaje_id), concepto FROM evaluaciones WHERE ficha_id = ?");
        $st->execute([$ficha['id']]);
        $this->conceptos = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        if ($actor->esInstructor()) {
            // Los pares que el instructor puede calificar, con la misma
            // condición que usa todo el sistema (una consulta, no una por fila).
            $st = $this->db->prepare("
                SELECT CONCAT(ap.id, '|', ra.id)
                  FROM evaluaciones e
                  JOIN aprendices ap ON ap.id = e.aprendiz_id
                  JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
                  JOIN competencias c ON c.id = ra.competencia_id
                  JOIN fichas f ON f.id = e.ficha_id
                 WHERE e.ficha_id = ? AND (" . InstructorAccessService::sqlCondicionAcceso() . ")");
            $st->execute([$ficha['id'], $actor->id, $actor->id, $actor->id]);
            $this->permitidos = array_fill_keys($st->fetchAll(PDO::FETCH_COLUMN), true);
        }
    }

    /** Instructor responsable de cada evaluación pendiente de la ficha. */
    private function responsables(int $fichaId): array {
        $st = $this->db->prepare("SELECT CONCAT(aprendiz_id, '|', resultado_aprendizaje_id), instructor_id FROM evaluaciones WHERE ficha_id = ?");
        $st->execute([$fichaId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Número de la ficha donde está matriculado el documento, si no es esta. */
    private function fichaDe(string $doc): ?string {
        if (!array_key_exists($doc, $this->enOtraFicha)) {
            $st = $this->db->prepare("SELECT f.numero_ficha FROM aprendices a JOIN fichas f ON f.id = a.ficha_id WHERE a.numero_documento = ?");
            $st->execute([$doc]);
            $this->enOtraFicha[$doc] = ($n = $st->fetchColumn()) !== false ? (string)$n : null;
        }
        return $this->enOtraFicha[$doc];
    }

    // -----------------------------------------------------------------
    // INTERPRETACIÓN DE CELDAS (públicas para probarlas por separado)
    // -----------------------------------------------------------------

    /** "APROBADO" → A; "NO APROBADO" / "DEFICIENTE" → D; "POR EVALUAR" o vacío → pendiente. */
    public static function concepto(string $juicio): ?string {
        $j = LectorTabular::normalizarEncabezado($juicio);
        return match (true) {
            $j === '' || $j === 'pendiente' || str_contains($j, 'por_evaluar') || str_contains($j, 'sin_evaluar') => 'pendiente',
            // Antes de "aprobado": "no aprobado" también lo contiene.
            str_contains($j, 'no_aprobado') || str_contains($j, 'deficiente') || $j === 'd' || $j === 'no_aprobo' => 'D',
            str_contains($j, 'aprobado') || $j === 'a' || $j === 'aprobo' => 'A',
            default => null,
        };
    }

    /** "220501046 - Utilizar herramientas" → ['220501046', 'Utilizar herramientas']. */
    public static function codigoCompetencia(string $texto): ?array {
        $t = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if (preg_match('/^(\d{3,15})\s*-\s*(.*)$/u', $t, $m)) {
            return [$m[1], mb_substr(trim($m[2]), 0, 255)];
        }
        return preg_match('/^\d{3,15}$/', $t) ? [$t, ''] : null;
    }

    /** "220501046 - 01 Configurar…" → ['220501046-01', 'Configurar…']. */
    public static function codigoRap(string $texto): ?array {
        $t = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if (preg_match('/^(\d{3,15})\s*-\s*(\d{1,2})\b\s*[-.]?\s*(.*)$/u', $t, $m)) {
            return [$m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT), mb_substr(trim($m[3]), 0, 500)];
        }
        if (preg_match('/^(\d{3,15}-\d{2})\s*(.*)$/u', $t, $m)) {
            return [$m[1], mb_substr(trim($m[2]), 0, 500)];
        }
        return null;
    }

    private static function esRetiro(string $estado): bool {
        $e = LectorTabular::normalizarEncabezado($estado);
        return str_contains($e, 'retir') || str_contains($e, 'cancel') || str_contains($e, 'deser') || str_contains($e, 'trasl');
    }

    /** DD/MM/AAAA (con hora opcional) o AAAA-MM-DD; si no, hoy con aviso. */
    private function fecha(string $texto, array &$avisos): string {
        $t = trim($texto);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $t, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
            $f = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $t, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            $f = "{$m[1]}-{$m[2]}-{$m[3]}";
        } else {
            if ($t !== '') {
                $avisos[] = 'Fecha no reconocida: se usará la de hoy.';
            }
            return date('Y-m-d');
        }
        if ($f > date('Y-m-d')) {
            $avisos[] = 'La fecha del juicio es futura: se usará la de hoy.';
            return date('Y-m-d');
        }
        return $f;
    }
}
