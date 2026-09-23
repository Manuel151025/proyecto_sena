<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\RetroalimentacionFormulario;
use Core\Models\RetroalimentacionModel;
use Core\Services\Paginator;
use Core\Services\RetroalimentacionService;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Retroalimentación a los aprendices.
 *
 *   GET  /retroalimentacion?tipo=&ficha_id=&search=   todos (cada rol ve lo suyo)
 *   POST /retroalimentacion  action=registrar          instructor y coordinación
 */
class RetroalimentacionController extends BaseController {
    private RetroalimentacionModel $modelo;

    public function __construct(?RetroalimentacionModel $modelo = null) {
        $this->modelo = $modelo ?? new RetroalimentacionModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $tipo = (string)($_GET['tipo'] ?? '');
        $filtros = [
            'search'   => $this->consulta()->busquedaCruda('search'),
            'tipo'     => in_array($tipo, Enums::RETROALIMENTACION_TIPO, true) ? $tipo : '',
            'ficha_id' => $this->idDeConsulta('ficha_id'),
        ];
        $errors = [];
        $retros = $aprendices = $fichas = [];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->modelo->contar($actor, $filtros), 20);
            $retros = $this->modelo->listar($actor, $filtros, $paginacion->perPage(), $paginacion->offset());
            if ($actor->gestiona()) {
                $aprendices = $this->modelo->aprendicesDisponibles($actor);
                $fichas = $this->modelo->fichasDelActor($actor);
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar la retroalimentación');
        }
        $this->render(BASE_PATH . 'modules/retroalimentacion/views/index.view.php', [
            'errors'     => $errors,
            'retros'     => $retros,
            'aprendices' => $aprendices,
            'fichas'     => $fichas,
            'filtros'    => $filtros,
            'paginacion' => $paginacion,
            'tipos'      => RetroalimentacionFormulario::TIPOS,
            'actor'      => $actor,
        ], 'Retroalimentación · SENA');
    }

    public function registrar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = RetroalimentacionFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta('/retroalimentacion');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new RetroalimentacionService())->registrar($d, Actor::actual()), $vuelta,
            $d['privada'] ? 'Observación privada registrada.' : 'Retroalimentación registrada; el aprendiz recibió un aviso.',
            'No se pudo registrar la retroalimentación');
    }
}
