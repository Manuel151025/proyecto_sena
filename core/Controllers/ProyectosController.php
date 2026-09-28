<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\ProyectoFormulario;
use Core\Models\ProyectosModel;
use Core\Services\ProyectosService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Proyectos formativos.
 *
 *   GET  /proyectos                   listado (los tres roles, cada uno en su alcance)
 *   POST /proyectos  action=crear     coordinación
 *   POST /proyectos  action=editar    coordinación
 *   POST /proyectos  action=eliminar  coordinación
 */
class ProyectosController extends BaseController {
    private const RUTA = '/proyectos';

    private ProyectosModel $modelo;
    private ProyectosService $servicio;

    public function __construct(?ProyectosModel $modelo = null, ?ProyectosService $servicio = null) {
        $this->modelo = $modelo ?? new ProyectosModel();
        $this->servicio = $servicio ?? new ProyectosService();
    }

    public function index(): void {
        $actor = Actor::actual();
        $errors = [];
        $proyectos = [];
        try {
            $proyectos = $this->modelo->listar($actor);
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los proyectos');
        }

        $this->render(BASE_PATH . 'modules/proyectos/views/index.view.php', [
            'errors'    => $errors,
            'user_rol'  => $actor->rol,
            'proyectos' => $proyectos,
            'limites'   => [
                'nombre' => ProyectoFormulario::MAX_NOMBRE,
                'codigo' => ProyectoFormulario::MAX_CODIGO,
                'texto'  => ProyectoFormulario::MAX_TEXTO,
            ],
        ], 'Proyectos Formativos · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $datos = ProyectoFormulario::validar($v);
        $this->siHayErrores($v, self::RUTA);

        $this->ejecutar(
            fn() => $this->servicio->crear($datos, Actor::actual()),
            self::RUTA, 'Proyecto formativo creado.', 'No se pudo crear el proyecto'
        );
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El proyecto');
        $datos = ProyectoFormulario::validar($v, true);
        $this->siHayErrores($v, self::RUTA);

        $this->ejecutar(
            fn() => $this->servicio->editar($id, $datos, Actor::actual()),
            self::RUTA, 'Proyecto actualizado.', 'No se pudo actualizar el proyecto'
        );
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El proyecto');
        $this->siHayErrores($v, self::RUTA);

        $this->ejecutar(
            fn() => $this->servicio->eliminar($id, Actor::actual()),
            self::RUTA, 'Proyecto eliminado.', 'No se pudo eliminar el proyecto'
        );
    }
}
