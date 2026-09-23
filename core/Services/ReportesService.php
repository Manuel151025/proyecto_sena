<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\AnaliticaModel;
use Core\Models\FichaModel;
use Core\Models\ReportesModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Semaforo;
use PDO;

/**
 * Catálogo de reportes (RF05) y armado de cada uno.
 *
 * Un reporte es: título, subtítulo, encabezados, filas por posición y qué
 * columnas llevan semáforo. Los tres formatos (XLSX, CSV, PDF) se generan
 * a partir de esa misma definición, así que no pueden contar cosas
 * distintas ni pintar colores distintos.
 */
final class ReportesService {
    public const FORMATOS = ['xlsx', 'csv', 'pdf'];
    /** Máximo rango del historial, para acotar el tamaño del archivo. */
    public const MAX_DIAS_HISTORIAL = 366;

    public const TIPOS = [
        'ficha'       => ['Juicios de una ficha', 'Cada RAP de cada aprendiz de la ficha, con su juicio, fecha y quién califica.', 'bi-journal-text'],
        'fichas'      => ['Resumen por ficha', 'Aprendices, desempeño, avance de RAP, avance del proyecto y planes de cada ficha.', 'bi-folder2-open'],
        'instructor'  => ['Cumplimiento por instructor', 'Por instructor responsable, ficha y competencia: A, D, pendientes, desempeño y avance.', 'bi-person-badge'],
        'competencia' => ['Cumplimiento por competencia', 'Por competencia: fichas, aprendices, juicios, desempeño y avance.', 'bi-diagram-3'],
        'riesgo'      => ['Aprendices en riesgo', 'Aprendices en semáforo crítico o de riesgo, con sus D y planes vigentes.', 'bi-exclamation-triangle'],
        'historial'   => ['Historial de cambios de juicio', 'Quién cambió qué juicio, cuándo y por qué (trazabilidad).', 'bi-clock-history'],
    ];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * @param array{ficha_id?:int, desde?:?string, hasta?:?string} $params
     * @return array{titulo:string, subtitulo:string, encabezados:list<string>, filas:list<array>, estilos:array, anchos:list<int>, archivo:string}
     */
    public function generar(string $tipo, Actor $actor, array $params = []): array {
        if (!$actor->gestiona()) {
            throw new ErrorDeNegocio('Los reportes son para instructores y coordinación.');
        }
        if (!isset(self::TIPOS[$tipo])) {
            throw new ErrorDeNegocio('Ese reporte no existe.');
        }
        $modelo = new ReportesModel($this->db);
        $titulo = self::TIPOS[$tipo][0];
        $alcance = $actor->esCoordinador() ? 'Todo el centro' : 'Lo que califica ' . (getCurrentUser()['nombre'] ?? 'el instructor');

        return match ($tipo) {
            'ficha' => $this->ficha($modelo, $actor, (int)($params['ficha_id'] ?? 0), $titulo),
            'fichas' => [
                'titulo' => $titulo, 'subtitulo' => $actor->esCoordinador() ? 'Todas las fichas' : 'Fichas a cargo',
                'encabezados' => ['Ficha', 'Programa', 'Líder', 'Estado', 'Aprendices', 'RAP en A', 'RAP en D', '% Desempeño', 'Semáforo', '% Avance RAP', '% Proyecto', 'Planes abiertos'],
                'filas' => array_map(static fn($f) => [
                    $f['numero_ficha'], $f['codigo_programa'], $f['instructor'], $f['estado'], (int)$f['aprendices_activos'],
                    (int)$f['aprobados'], (int)$f['en_d'], $f['pct_a'] ?? '', Semaforo::etiqueta($f['semaforo']),
                    $f['cumplimiento'] ?? '', $f['avance_proyecto'] ?? '', (int)($f['planes_abiertos'] ?? 0),
                ], (new FichaModel($this->db))->paraExportar($actor, [], \Core\Exportacion\Exportador::MAX_FILAS)),
                'estilos' => ['columna_porcentaje' => 7], 'anchos' => [11, 10, 26, 11, 10, 9, 9, 12, 11, 12, 11, 10],
            ],
            'instructor' => [
                'titulo' => $titulo, 'subtitulo' => $alcance,
                'encabezados' => ['Instructor', 'Ficha', 'Programa', 'Código', 'Competencia', 'Total RAP', 'A', 'D', 'Pendientes', '% Desempeño', '% Avance'],
                'filas' => $modelo->porInstructor($actor),
                'estilos' => ['columna_porcentaje' => 9], 'anchos' => [26, 10, 9, 12, 40, 9, 7, 7, 10, 12, 10],
            ],
            'competencia' => [
                'titulo' => $titulo, 'subtitulo' => $alcance,
                'encabezados' => ['Programa', 'Código', 'Competencia', 'Fichas', 'Aprendices', 'Total RAP', 'A', 'D', 'Pendientes', '% Desempeño', '% Avance'],
                'filas' => $modelo->porCompetencia($actor),
                'estilos' => ['columna_porcentaje' => 9], 'anchos' => [9, 12, 44, 8, 10, 9, 7, 7, 10, 12, 10],
            ],
            'riesgo' => [
                'titulo' => $titulo, 'subtitulo' => $actor->esCoordinador() ? 'Todo el centro' : 'Fichas a cargo',
                'encabezados' => ['Ficha', 'Aprendiz', 'Documento', 'RAP en A', 'RAP en D', 'Pendientes', '% Desempeño', 'Semáforo', 'Planes vigentes'],
                'filas' => array_map(static fn($a) => [$a['numero_ficha'], $a['nombre'], $a['numero_documento'], (int)$a['aprobados'], (int)$a['en_d'],
                    (int)$a['pendientes'], $a['pct_a'] ?? '', Semaforo::etiqueta($a['semaforo']), (int)$a['planes']],
                    (new AnaliticaModel($this->db))->aprendicesEnRiesgo($actor, 5000)),
                'estilos' => ['columna_porcentaje' => 6], 'anchos' => [10, 32, 14, 9, 9, 10, 12, 10, 10],
            ],
            'historial' => $this->historial($modelo, $actor, $params, $titulo),
        } + ['archivo' => 'reporte_' . $tipo . '_' . date('Ymd_His')];
    }

    private function ficha(ReportesModel $modelo, Actor $actor, int $fichaId, string $titulo): array {
        $ficha = (new FichaModel($this->db))->detalle($fichaId) ?? throw new ErrorDeNegocio('Elige una ficha.');
        if ($actor->esInstructor() && !(new InstructorAccessService($this->db))->tieneAccesoFicha($fichaId, $actor->id)) {
            throw new ErrorDeNegocio('No tienes a cargo esa ficha.');
        }
        return [
            'titulo' => $titulo, 'subtitulo' => 'Ficha ' . $ficha['numero_ficha'] . ' · ' . $ficha['programa'],
            'encabezados' => ['Aprendiz', 'Documento', 'Estado', 'Competencia', 'RAP', 'Resultado de aprendizaje', 'Juicio', 'Fecha', 'Califica'],
            'filas' => $modelo->juiciosDeFicha($fichaId, $actor),
            'estilos' => ['columnas_concepto' => [6]], 'anchos' => [30, 14, 12, 12, 12, 50, 10, 11, 26],
        ];
    }

    private function historial(ReportesModel $modelo, Actor $actor, array $params, string $titulo): array {
        $hasta = $params['hasta'] ?? date('Y-m-d');
        $desde = $params['desde'] ?? date('Y-m-d', strtotime("$hasta -90 days"));
        if ($desde > $hasta) {
            throw new ErrorDeNegocio('La fecha inicial no puede ser posterior a la final.');
        }
        if ((strtotime($hasta) - strtotime($desde)) / 86400 > self::MAX_DIAS_HISTORIAL) {
            throw new ErrorDeNegocio('El historial se exporta por periodos de hasta un año.');
        }
        return [
            'titulo' => $titulo, 'subtitulo' => 'Del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)),
            'encabezados' => ['Fecha', 'Ficha', 'Aprendiz', 'Documento', 'RAP', 'Anterior', 'Nuevo', 'Motivo', 'Registró'],
            'filas' => $modelo->historial($actor, $desde, $hasta),
            'estilos' => ['columnas_concepto' => [5, 6]], 'anchos' => [16, 10, 30, 14, 12, 10, 10, 50, 26],
        ];
    }
}
