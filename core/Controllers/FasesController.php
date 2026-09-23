<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\FaseFormulario;
use Core\Models\FasesModel;
use Core\Models\ProyectosModel;
use Core\Services\FasesService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Fases del proyecto formativo.
 *
 *   GET  /fases?proyecto_id=&ficha_id=   fases con el avance de sus actividades
 *   POST /fases  action=crear|editar     coordinación e instructor (en su alcance)
 *   POST /fases  action=eliminar         coordinación
 */
class FasesController extends BaseController {
    private FasesModel $fases;
    private ProyectosModel $proyectos;
    private FasesService $servicio;

    public function __construct(?FasesModel $fases = null, ?ProyectosModel $proyectos = null, ?FasesService $servicio = null) {
        $this->fases = $fases ?? new FasesModel();
        $this->proyectos = $proyectos ?? new ProyectosModel();
        $this->servicio = $servicio ?? new FasesService();
    }

    public function index(): void {
        $actor = Actor::actual();
        $errors = [];
        $proyectos = $fases = $fichas = [];
        $proyectoActual = null;
        $proyectoId = $this->idDeConsulta('proyecto_id');
        $fichaId = $this->idDeConsulta('ficha_id');

        try {
            $proyectos = $this->proyectos->opciones($actor);
            $ids = array_map(static fn($p) => (int)$p['id'], $proyectos);
            // Un proyecto fuera del alcance se trata como no elegido, igual
            // que uno que no existe: no se confirma su existencia.
            if (!in_array($proyectoId, $ids, true)) {
                $proyectoId = $ids[0] ?? 0;
            }
            if ($proyectoId > 0) {
                $proyectoActual = $this->proyectos->findById($proyectoId);
                $fichas = $this->proyectos->fichasDelProyecto($proyectoId, $actor);
                $idsFichas = array_map(static fn($f) => (int)$f['id'], $fichas);
                if (!in_array($fichaId, $idsFichas, true)) {
                    $fichaId = 0;
                }
                // El avance se calcula con las fichas que el actor ve, o con
                // la elegida. El coordinador sin filtro ve todo el proyecto.
                $alcance = $fichaId > 0 ? [$fichaId] : ($actor->esCoordinador() ? null : $idsFichas);
                $fases = $this->fases->listarDeProyecto($proyectoId, $alcance);
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las fases');
        }

        $this->render(BASE_PATH . 'modules/fases/views/index.view.php', [
            'errors'         => $errors,
            'user_rol'       => $actor->rol,
            'proyectos'      => $proyectos,
            'proyectoId'     => $proyectoId,
            'proyectoActual' => $proyectoActual,
            'fichas'         => $fichas,
            'fichaId'        => $fichaId,
            'fases'          => $fases,
            'puedeGestionar' => $actor->gestiona(),
            'puedeEliminar'  => $actor->esCoordinador(),
            'estados_label'  => [
                'planeada'     => ['Planeada', 'secondary'],
                'en_ejecucion' => ['En ejecución', 'warning'],
                'completada'   => ['Completada', 'success'],
            ],
            'limites' => ['nombre' => FaseFormulario::MAX_NOMBRE, 'texto' => FaseFormulario::MAX_DESCRIPCION, 'numero' => FaseFormulario::MAX_NUMERO],
        ], 'Fases del Proyecto · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $proyectoId = $v->id('proyecto_id', 'El proyecto');
        $datos = FaseFormulario::validar($v);
        $vuelta = '/fases?proyecto_id=' . $proyectoId;
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->crear($proyectoId, $datos, Actor::actual()),
            $vuelta, 'Fase registrada.', 'No se pudo registrar la fase');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La fase');
        $datos = FaseFormulario::validar($v);
        $vuelta = $this->vuelta();
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editar($id, $datos, Actor::actual()),
            $vuelta, 'Fase actualizada.', 'No se pudo actualizar la fase');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La fase');
        $vuelta = $this->vuelta();
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->eliminar($id, Actor::actual()),
            $vuelta, 'Fase eliminada.', 'No se pudo eliminar la fase');
    }

    /** Vuelve al mismo proyecto desde el que se envió el formulario. */
    private function vuelta(): string {
        $p = filter_var($_POST['proyecto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return '/fases' . ($p ? '?proyecto_id=' . $p : '');
    }
}
