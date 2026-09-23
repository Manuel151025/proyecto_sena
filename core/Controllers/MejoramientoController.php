<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Exportacion\Exportador;
use Core\Formularios\PlanFormulario;
use Core\Models\MejoramientoModel;
use Core\Services\Auditoria;
use Core\Services\Paginator;
use Core\Services\PlanesService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Planes de mejoramiento de los RAP en D.
 *
 *   GET  /mejoramiento?estado=&ficha_id=&search=   todos (cada rol ve lo suyo)
 *   GET  /mejoramiento/exportar                    todos, con los mismos filtros
 *   POST /mejoramiento  action=crear|editar|cerrar instructor y coordinación
 */
class MejoramientoController extends BaseController {
    public const ESTADOS = [
        'abierto'     => ['Abierto', 'info'],
        'en_curso'    => ['En curso', 'primary'],
        'cumplido'    => ['Cumplido', 'success'],
        'no_cumplido' => ['No cumplido', 'danger'],
    ];

    private MejoramientoModel $modelo;

    public function __construct(?MejoramientoModel $modelo = null) {
        $this->modelo = $modelo ?? new MejoramientoModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $filtros = $this->filtros();
        $errors = [];
        $planes = $sinPlan = $fichas = [];
        $cifras = ['vigentes' => 0, 'vencidos' => 0, 'cumplidos' => 0, 'no_cumplidos' => 0, 'sin_plan' => 0];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->modelo->contar($actor, $filtros), 20);
            $planes = $this->modelo->listar($actor, $filtros, $paginacion->perPage(), $paginacion->offset());
            $cifras = $this->modelo->cifras($actor);
            if ($actor->gestiona()) {
                $sinPlan = $this->modelo->sinPlan($actor, $filtros['ficha_id'], 30, $filtros['search']);
                $cifras['sin_plan_filtrados'] = $this->modelo->contarSinPlan($actor, $filtros['ficha_id'], $filtros['search']);
                $fichas = $this->modelo->fichasDelActor($actor);
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los planes de mejoramiento');
        }
        $this->render(BASE_PATH . 'modules/mejoramiento/views/index.view.php', [
            'errors'     => $errors,
            'planes'     => $planes,
            'sinPlan'    => $sinPlan,
            'fichas'     => $fichas,
            'cifras'     => $cifras,
            'filtros'    => $filtros,
            'paginacion' => $paginacion,
            'estados'    => self::ESTADOS,
            'actor'      => $actor,
        ], 'Planes de mejoramiento · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = PlanFormulario::validarCreacion($v);
        $vuelta = $this->rutaDeVuelta('/mejoramiento');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new PlanesService())->crear($d, Actor::actual()), $vuelta,
            'Plan de mejoramiento creado; el aprendiz recibió un aviso.', 'No se pudo crear el plan');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = PlanFormulario::validarEdicion($v);
        $vuelta = $this->rutaDeVuelta('/mejoramiento');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new PlanesService())->editar($d, Actor::actual()), $vuelta, 'Plan actualizado.', 'No se pudo actualizar el plan');
    }

    public function cerrar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = PlanFormulario::validarCierre($v);
        $vuelta = $this->rutaDeVuelta('/mejoramiento');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new PlanesService())->cerrar($d, Actor::actual()), $vuelta,
            $d['resultado'] === 'cumplido' ? 'Plan cumplido: el RAP quedó en A.' : 'Plan cerrado como no cumplido: el RAP sigue en D.',
            'No se pudo cerrar el plan');
    }

    public function exportar(): never {
        $actor = Actor::actual();
        $formato = ($_GET['formato'] ?? '') === 'csv' ? 'csv' : 'xlsx';
        $filas = array_map(static fn($p) => [
            $p['numero_ficha'], $p['aprendiz_nombre'], $p['numero_documento'], $p['ra_codigo'], $p['ra_denominacion'],
            (self::ESTADOS[$p['estado']][0] ?? $p['estado']) . ((int)$p['vencido'] ? ' (vencido)' : ''),
            $p['fecha_inicio'], $p['fecha_limite'], $p['instructor_nombre'], $p['actividades'], (string)($p['observaciones_cierre'] ?? ''),
        ], $this->modelo->paraExportar($actor, $this->filtros(), Exportador::MAX_FILAS));
        $enc = ['Ficha', 'Aprendiz', 'Documento', 'RAP', 'Resultado de aprendizaje', 'Estado', 'Inicio', 'Límite', 'Responsable', 'Actividades', 'Cierre'];

        (new Auditoria())->operacion($actor, 'Exportar', 'Mejoramiento', 'planes_mejoramiento', null, count($filas) . " planes exportados en $formato");
        $nombre = 'planes_mejoramiento_' . date('Ymd_His') . '.' . $formato;
        Exportador::descargar(
            $formato === 'csv'
                ? Exportador::csv($nombre, $enc, $filas)
                : Exportador::xlsx('Planes', $enc, $filas, ['titulo' => 'Planes de mejoramiento · ' . date('d/m/Y'),
                    'anchos' => [10, 30, 14, 14, 40, 16, 11, 11, 26, 50, 40]]),
            $nombre, $formato);
    }

    private function filtros(): array {
        $estado = (string)($_GET['estado'] ?? '');
        return [
            'search'   => $this->consulta()->busquedaCruda('search'),
            'estado'   => in_array($estado, MejoramientoModel::FILTROS, true) ? $estado : '',
            'ficha_id' => $this->idDeConsulta('ficha_id'),
        ];
    }
}
