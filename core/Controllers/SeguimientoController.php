<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\JuicioFormulario;
use Core\Formularios\RetroalimentacionFormulario;
use Core\Models\EvaluacionesModel;
use Core\Models\FichaModel;
use Core\Models\RetroalimentacionModel;
use Core\Models\SeguimientoModel;
use Core\Services\InstructorAccessService;
use Core\Services\JuiciosService;
use Core\Services\RetroalimentacionService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Semaforo;
use Throwable;

/**
 * Seguimiento académico: el expediente de cada aprendiz.
 *
 *   GET  /seguimiento?ficha_id=&aprendiz_id=&semaforo=   gestión: aprendices de una ficha y el
 *                                                        expediente del elegido; aprendiz: el suyo
 *   POST /seguimiento  action=evaluar     emitir o cambiar un juicio (JuiciosService)
 *   POST /seguimiento  action=observar    observación o retroalimentación (RetroalimentacionService)
 */
class SeguimientoController extends BaseController {
    private SeguimientoModel $modelo;

    public function __construct(?SeguimientoModel $modelo = null) {
        $this->modelo = $modelo ?? new SeguimientoModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $errors = [];
        $datos = ['fichas' => [], 'fichaId' => 0, 'ficha' => null, 'aprendices' => [], 'semaforo' => '',
                  'aprendizId' => 0, 'resumen' => null, 'competencias' => [], 'retros' => []];
        try {
            if ($actor->esAprendiz()) {
                $datos['aprendizId'] = $this->modelo->aprendizDeUsuario($actor->id)
                    ?? throw new ErrorDeNegocio('Tu cuenta no tiene una matrícula asociada.');
            } else {
                $datos = array_merge($datos, $this->ficha($actor));
            }
            if ($datos['aprendizId'] > 0) {
                $datos['resumen'] = $this->modelo->resumen($datos['aprendizId']);
                $datos['competencias'] = $this->modelo->competencias($datos['aprendizId'], $actor);
                $datos['retros'] = (new RetroalimentacionModel())->listar($actor, ['aprendiz_id' => $datos['aprendizId']], 30, 0);
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar el seguimiento');
        }
        $this->render(BASE_PATH . 'modules/seguimiento/views/index.view.php', $datos + [
            'errors' => $errors,
            'actor'  => $actor,
            'conceptos' => EvaluacionesController::CONCEPTOS,
            'tipos'  => RetroalimentacionFormulario::TIPOS,
        ], 'Seguimiento · SENA');
    }

    /** Ficha elegida (entre las del actor), sus aprendices y el aprendiz elegido. */
    private function ficha(Actor $actor): array {
        $fichas = (new EvaluacionesModel())->fichasDelActor($actor);
        $fichaId = $this->idDeConsulta('ficha_id');
        $aprendizId = $this->idDeConsulta('aprendiz_id');
        if ($fichaId === 0 && $aprendizId > 0) {
            $fichaId = (int)$this->modelo->fichaDeAprendiz($aprendizId);
        }
        $ids = array_map('intval', array_column($fichas, 'id'));
        if (!in_array($fichaId, $ids, true)) {
            $fichaId = $ids[0] ?? 0;
        }
        $semaforo = (string)($_GET['semaforo'] ?? '');
        $semaforo = in_array($semaforo, [Semaforo::CRITICO, Semaforo::RIESGO, Semaforo::AL_DIA, Semaforo::SIN_DATOS], true) ? $semaforo : '';
        if ($fichaId === 0) {
            return ['fichas' => $fichas, 'fichaId' => 0, 'semaforo' => $semaforo];
        }
        $modeloFichas = new FichaModel();
        $aprendices = $modeloFichas->aprendicesConIndicadores($fichaId);
        // El aprendiz elegido tiene que ser de esta ficha: el id viene de la URL.
        if (!in_array($aprendizId, array_map('intval', array_column($aprendices, 'id')), true)) {
            $aprendizId = 0;
        }
        if ($semaforo !== '') {
            $aprendices = array_values(array_filter($aprendices, static fn($a) => $a['semaforo'] === $semaforo));
        }
        return ['fichas' => $fichas, 'fichaId' => $fichaId, 'ficha' => $modeloFichas->detalle($fichaId),
                'aprendices' => $aprendices, 'semaforo' => $semaforo, 'aprendizId' => $aprendizId];
    }

    public function evaluar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = JuicioFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta('/seguimiento');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new JuiciosService())->calificar($d, Actor::actual()), $vuelta,
            static fn(string $accion) => $accion === 'sin_cambios' ? 'Comentario guardado; el juicio no cambió.' : "Juicio registrado: {$d['concepto']}.",
            'No se pudo guardar el juicio');
    }

    public function observar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = RetroalimentacionFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta('/seguimiento');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new RetroalimentacionService())->registrar($d, Actor::actual()), $vuelta,
            $d['privada'] ? 'Observación privada registrada.' : 'Retroalimentación registrada; el aprendiz recibió un aviso.',
            'No se pudo registrar la observación');
    }
}
