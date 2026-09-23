<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\AsignacionesModel;
use Core\Services\AsignacionesService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Asignación de instructores por competencia.
 *
 *   GET  /asignaciones?search=&ficha_id=&instructor_id=   coordinación (todas) e instructor (las de sus fichas)
 *   POST /asignaciones  action=asignar|reasignar|eliminar  coordinación
 */
class AsignacionesController extends BaseController {
    private AsignacionesModel $modelo;
    private AsignacionesService $servicio;

    public function __construct(?AsignacionesModel $modelo = null, ?AsignacionesService $servicio = null) {
        $this->modelo = $modelo ?? new AsignacionesModel();
        $this->servicio = $servicio ?? new AsignacionesService();
    }

    public function index(): void {
        $actor = Actor::actual();
        $busqueda = $this->consulta()->busquedaCruda('search');
        $fichaId = $this->idDeConsulta('ficha_id');
        $instructorId = $this->idDeConsulta('instructor_id');
        $errors = [];
        $asignaciones = $fichas = $competencias = $instructores = [];
        try {
            $asignaciones = $this->modelo->listar($actor, $busqueda, $fichaId, $instructorId);
            $instructores = $this->modelo->getInstructores();
            if ($actor->esCoordinador()) {
                $fichas = $this->modelo->getFichas();
                $competencias = array_values(array_filter($this->modelo->getCompetencias(), static fn($c) => (int)$c['es_etapa_practica'] === 0));
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las asignaciones');
        }
        $this->render(BASE_PATH . 'modules/asignaciones/views/index.view.php', [
            'errors'        => $errors,
            'asignaciones'  => $asignaciones,
            'fichas'        => $fichas,
            'competencias'  => $competencias,
            'instructores'  => $instructores,
            'busqueda'      => $busqueda,
            'fichaId'       => $fichaId,
            'instructorId'  => $instructorId,
            'esCoordinador' => $actor->esCoordinador(),
        ], 'Asignaciones · SENA');
    }

    public function asignar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $ficha = $v->id('ficha_id', 'La ficha');
        $comp = $v->id('competencia_id', 'La competencia');
        $inst = $v->id('instructor_id', 'El instructor');
        $vuelta = $this->rutaDeVuelta('/asignaciones');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->asignar($ficha, $comp, $inst, Actor::actual()), $vuelta,
            'Instructor asignado. Sus evaluaciones pendientes de esa competencia pasan a él.', 'No se pudo asignar');
    }

    public function reasignar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La asignación');
        $inst = $v->id('instructor_id', 'El instructor');
        $vuelta = $this->rutaDeVuelta('/asignaciones');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->cambiarInstructor($id, $inst, Actor::actual()), $vuelta,
            'Instructor cambiado.', 'No se pudo cambiar el instructor');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La asignación');
        $vuelta = $this->rutaDeVuelta('/asignaciones');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->eliminar($id, Actor::actual()), $vuelta,
            'Asignación eliminada: la competencia vuelve al instructor líder de la ficha.', 'No se pudo eliminar la asignación');
    }
}
