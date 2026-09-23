<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Exportacion\Exportador;
use Core\Formularios\JuicioFormulario;
use Core\Models\EvaluacionesModel;
use Core\Services\Auditoria;
use Core\Services\JuiciosService;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Juicios evaluativos por RAP (RF03).
 *
 *   GET  /evaluaciones?search=&ficha_id=&concepto=   todos (cada rol ve lo suyo)
 *   GET  /evaluaciones/exportar?formato=xlsx|csv       todos, con los mismos filtros
 *   POST /evaluaciones  action=evaluar                 instructor y coordinación
 *
 * La importación del reporte de Sofia Plus es /evaluaciones/importar
 * (ImportacionController + ImportadorJuicios).
 */
class EvaluacionesController extends BaseController {
    public const CONCEPTOS = [
        'A'         => ['Aprobado (A)', 'success', 'bi-check-circle-fill'],
        'D'         => ['No aprobado (D)', 'danger', 'bi-x-circle-fill'],
        'pendiente' => ['Pendiente', 'warning', 'bi-clock-fill'],
    ];

    private EvaluacionesModel $modelo;

    public function __construct(?EvaluacionesModel $modelo = null) {
        $this->modelo = $modelo ?? new EvaluacionesModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $filtros = $this->filtros();
        $errors = [];
        $evaluaciones = $fichas = $historial = [];
        $cifras = ['total' => 0, 'a' => 0, 'd' => 0, 'pendientes' => 0];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->modelo->contar($actor, $filtros));
            $evaluaciones = $this->modelo->listar($actor, $filtros, $paginacion->perPage(), $paginacion->offset());
            $historial = $this->modelo->historial(array_column($evaluaciones, 'id'));
            $cifras = $this->modelo->cifras($actor, $filtros);
            $fichas = $this->modelo->fichasDelActor($actor);
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los juicios');
        }
        $this->render(BASE_PATH . 'modules/evaluaciones/views/index.view.php', [
            'errors'       => $errors,
            'evaluaciones' => $evaluaciones,
            'historial'    => $historial,
            'cifras'       => $cifras,
            'fichas'       => $fichas,
            'filtros'      => $filtros,
            'paginacion'   => $paginacion,
            'conceptos'    => self::CONCEPTOS,
            'actor'        => $actor,
        ], 'Juicios de evaluación · SENA');
    }

    public function evaluar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = JuicioFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta('/evaluaciones');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new JuiciosService())->calificar($d, Actor::actual()), $vuelta,
            static fn(string $accion) => $accion === 'sin_cambios' ? 'Comentario guardado; el juicio no cambió.' : "Juicio registrado: {$d['concepto']}.",
            'No se pudo guardar el juicio');
    }

    public function exportar(): never {
        $actor = Actor::actual();
        $formato = ($_GET['formato'] ?? '') === 'csv' ? 'csv' : 'xlsx';
        $filas = array_map(static fn($e) => [
            $e['numero_ficha'], $e['aprendiz_nombre'], $e['numero_documento'], $e['competencia_codigo'], $e['competencia_nombre'],
            $e['ra_codigo'], $e['ra_denominacion'], self::CONCEPTOS[$e['concepto']][0] ?? $e['concepto'],
            $e['fecha_evaluacion'] ?? '', $e['instructor_nombre'] ?? '', (string)($e['comentario'] ?? ''),
        ], $this->modelo->paraExportar($actor, $this->filtros(), Exportador::MAX_FILAS));
        $enc = ['Ficha', 'Aprendiz', 'Documento', 'Código competencia', 'Competencia', 'Código RAP', 'Resultado de aprendizaje',
                'Juicio', 'Fecha', 'Instructor', 'Comentario'];

        (new Auditoria())->operacion($actor, 'Exportar', 'Evaluaciones', 'evaluaciones', null, count($filas) . " juicios exportados en $formato");
        $nombre = 'juicios_' . date('Ymd_His') . '.' . $formato;
        Exportador::descargar(
            $formato === 'csv'
                ? Exportador::csv($nombre, $enc, $filas)
                : Exportador::xlsx('Juicios', $enc, $filas, [
                    'titulo' => 'Juicios evaluativos · ' . date('d/m/Y'),
                    'anchos' => [10, 32, 14, 14, 36, 14, 48, 16, 11, 26, 40]]),
            $nombre, $formato);
    }

    private function filtros(): array {
        $concepto = (string)($_GET['concepto'] ?? '');
        return [
            'search'   => $this->consulta()->busquedaCruda('search'),
            'ficha_id' => $this->idDeConsulta('ficha_id'),
            'concepto' => array_key_exists($concepto, self::CONCEPTOS) ? $concepto : '',
        ];
    }
}
