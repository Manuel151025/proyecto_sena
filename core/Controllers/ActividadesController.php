<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\ActividadFormulario;
use Core\Models\ActividadesModel;
use Core\Models\FasesModel;
use Core\Services\ActividadesService;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Actividades de aprendizaje del proyecto formativo.
 *
 *   GET  /actividades?ficha_id=&fase_id=&proyecto_id=&estado=&search=
 *   POST /actividades  action=crear|editar|avance|eliminar   (gestión, en su alcance)
 */
class ActividadesController extends BaseController {
    private ActividadesModel $modelo;
    private FasesModel $fases;
    private ActividadesService $servicio;

    public function __construct(?ActividadesModel $modelo = null, ?FasesModel $fases = null, ?ActividadesService $servicio = null) {
        $this->modelo = $modelo ?? new ActividadesModel();
        $this->fases = $fases ?? new FasesModel();
        $this->servicio = $servicio ?? new ActividadesService();
    }

    public function index(): void {
        $actor = Actor::actual();
        $q = $this->consulta();
        $filtros = [
            'search'      => $q->busquedaCruda('search'),
            'ficha_id'    => $this->idDeConsulta('ficha_id'),
            'fase_id'     => $this->idDeConsulta('fase_id'),
            'proyecto_id' => $this->idDeConsulta('proyecto_id'),
            'estado'      => in_array($_GET['estado'] ?? '', Enums::ACTIVIDAD_ESTADO, true) ? $_GET['estado'] : '',
        ];

        $errors = [];
        $actividades = $fichas = $fases = $competencias = $instructores = [];
        $paginacion = null;
        try {
            $total = $this->modelo->contar($actor, $filtros);
            $paginacion = Paginator::desdePeticion($total, 24);
            $actividades = $this->modelo->listar($actor, $filtros, $paginacion->perPage(), $paginacion->offset());

            $fichas = $this->modelo->fichasDelActor($actor);
            if ($actor->gestiona()) {
                $proyectos = array_values(array_unique(array_filter(array_map(static fn($f) => (int)$f['proyecto_id'], $fichas))));
                $programas = array_values(array_unique(array_map(static fn($f) => (int)$f['programa_id'], $fichas)));
                $fases = $this->fases->opcionesDeProyectos($proyectos);
                $competencias = $this->modelo->competenciasDeProgramas($programas);
                $instructores = $this->modelo->instructoresActivos();
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las actividades');
        }

        $this->render(BASE_PATH . 'modules/actividades/views/index.view.php', [
            'errors'        => $errors,
            'user_rol'      => $actor->rol,
            'user_id'       => $actor->id,
            'puedeGestionar'=> $actor->gestiona(),
            'actividades'   => $actividades,
            'paginacion'    => $paginacion,
            'fichas'        => $fichas,
            'fases'         => $fases,
            'competencias'  => $competencias,
            'instructores'  => $instructores,
            'filtros'       => $filtros,
            'estados_label' => [
                'pendiente'   => ['Pendiente', 'secondary'],
                'en_progreso' => ['En progreso', 'warning'],
                'completada'  => ['Completada', 'success'],
                'cancelada'   => ['Cancelada', 'danger'],
            ],
            'limites' => ['nombre' => ActividadFormulario::MAX_NOMBRE, 'texto' => ActividadFormulario::MAX_DESCRIPCION],
        ], 'Actividades · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = ActividadFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta('/actividades');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->crear($d, Actor::actual()), $vuelta, 'Actividad registrada.', 'No se pudo registrar la actividad');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La actividad');
        $d = ActividadFormulario::validar($v, true);
        $vuelta = $this->rutaDeVuelta('/actividades');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editar($id, $d, Actor::actual()), $vuelta, 'Actividad actualizada.', 'No se pudo actualizar la actividad');
    }

    public function avance(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La actividad');
        $d = ActividadFormulario::validarAvance($v);
        $vuelta = $this->rutaDeVuelta('/actividades');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->actualizarAvance($id, $d['estado'], $d['cumplimiento_porcentaje'], Actor::actual()),
            $vuelta, 'Avance registrado.', 'No se pudo registrar el avance');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La actividad');
        $vuelta = $this->rutaDeVuelta('/actividades');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->eliminar($id, Actor::actual()), $vuelta, 'Actividad eliminada.', 'No se pudo eliminar la actividad');
    }
}
