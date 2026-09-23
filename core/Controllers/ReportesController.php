<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Exportacion\Exportador;
use Core\Models\AnaliticaModel;
use Core\Models\EvaluacionesModel;
use Core\Router;
use Core\Services\Auditoria;
use Core\Services\ReportePdfService;
use Core\Services\ReportesService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Validador;
use Throwable;

/**
 * Reportes (RF05) en XLSX, CSV y PDF (RNF03).
 *
 *   GET /reportes                                     catálogo
 *   GET /reportes/descargar?tipo=&formato=&ficha_id=&desde=&hasta=
 *
 * La descarga es GET porque no cambia nada (se audita igualmente). Antes
 * era un POST que generaba un "Excel" que en realidad era HTML con
 * extensión .xls, y Excel avisaba de que el archivo estaba dañado.
 */
class ReportesController extends BaseController {
    public function index(): void {
        $actor = Actor::actual();
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $errors = [];
        $resumen = null;
        $fichas = [];
        try {
            $resumen = (new AnaliticaModel())->resumen($actor);
            $fichas = (new EvaluacionesModel())->fichasDelActor($actor);
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los reportes');
        }
        $this->render(BASE_PATH . 'modules/reportes/views/index.view.php', [
            'errors'  => $errors,
            'resumen' => $resumen,
            'fichas'  => $fichas,
            'tipos'   => ReportesService::TIPOS,
            'actor'   => $actor,
        ], 'Reportes · SENA');
    }

    public function descargar(): never {
        $actor = Actor::actual();
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = new Validador($_GET);
        $tipo = $v->enum('tipo', 'El reporte', array_keys(ReportesService::TIPOS));
        $formato = $v->enum('formato', 'El formato', ReportesService::FORMATOS, 'xlsx');
        $params = [
            'ficha_id' => $v->id('ficha_id', 'La ficha', false),
            'desde'    => $v->fecha('desde', 'La fecha inicial', false),
            'hasta'    => $v->fecha('hasta', 'La fecha final', false),
        ];
        $this->siHayErrores($v, '/reportes');
        try {
            @set_time_limit(180);
            $r = (new ReportesService())->generar($tipo, $actor, $params);
            (new Auditoria())->operacion($actor, 'Exportar', 'Reportes', 'evaluaciones', null,
                "{$r['titulo']} ({$r['subtitulo']}) en $formato: " . count($r['filas']) . ' filas');
            $nombre = $r['archivo'] . '.' . $formato;
            if ($formato === 'pdf') {
                $pdf = (new ReportePdfService())->generar($r['titulo'], $r['encabezados'], $r['filas'],
                    $r['estilos'] + ['subtitulo' => $r['subtitulo'], 'generado_por' => (string)(getCurrentUser()['nombre'] ?? ''), 'orientacion' => 'landscape']);
                Exportador::descargar($pdf, $nombre, 'pdf');
            }
            Exportador::descargar(
                $formato === 'csv'
                    ? Exportador::csv($nombre, $r['encabezados'], $r['filas'])
                    : Exportador::xlsx($r['titulo'], $r['encabezados'], $r['filas'],
                        ['titulo' => $r['titulo'] . ' · ' . $r['subtitulo'] . ' · ' . date('d/m/Y'), 'anchos' => $r['anchos'], 'estilos' => $r['estilos']]),
                $nombre, $formato);
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo generar el reporte'), '/reportes');
        }
    }
}
