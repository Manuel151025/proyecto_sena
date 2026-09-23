<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\ProgramaFormulario;
use Core\Models\ProgramasModel;
use Core\Services\ProgramasService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Programas de formación.
 *
 *   GET  /programas                    coordinación e instructores (lectura)
 *   POST /programas  action=crear      coordinación
 *   POST /programas  action=editar     coordinación
 *   POST /programas  action=eliminar   coordinación
 *
 * Antes crear y editar eran dos pantallas aparte (/programas/crear y
 * /programas/editar) con la validación copiada en cada una, y el error de
 * base de datos se mostraba con `$e->getMessage()`. Ahora es un modal,
 * como en el resto de catálogos.
 */
class ProgramasController extends BaseController {
    private const RUTA = '/programas';

    private ProgramasModel $modelo;
    private ProgramasService $servicio;

    public function __construct(?ProgramasModel $modelo = null, ?ProgramasService $servicio = null) {
        $this->modelo = $modelo ?? new ProgramasModel();
        $this->servicio = $servicio ?? new ProgramasService();
    }

    public function index(): void {
        $errors = [];
        $programas = [];
        try {
            $programas = $this->modelo->getAll();
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los programas');
        }

        $this->render(BASE_PATH . 'modules/programas/views/index.view.php', [
            'errors'        => $errors,
            'programas'     => $programas,
            'puedeEditar'   => $this->esRol(ROL_COORDINADOR),
            'estados_label' => [
                'activo'    => ['Activo', 'success'],
                'inactivo'  => ['Inactivo', 'warning'],
                'archivado' => ['Archivado', 'info'],
            ],
            'limites' => [
                'nombre' => ProgramaFormulario::MAX_NOMBRE,
                'codigo' => ProgramaFormulario::MAX_CODIGO,
                'texto'  => ProgramaFormulario::MAX_DESCRIPCION,
                'horas'  => ProgramaFormulario::MAX_HORAS,
            ],
        ], 'Programas de Formación · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $datos = ProgramaFormulario::validar($v);
        $this->siHayErrores($v, self::RUTA);
        $this->ejecutar(fn() => $this->servicio->crear($datos, Actor::actual()),
            self::RUTA, 'Programa creado.', 'No se pudo crear el programa');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El programa');
        $datos = ProgramaFormulario::validar($v);
        $this->siHayErrores($v, self::RUTA);
        $this->ejecutar(fn() => $this->servicio->editar($id, $datos, Actor::actual()),
            self::RUTA, 'Programa actualizado.', 'No se pudo actualizar el programa');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El programa');
        $this->siHayErrores($v, self::RUTA);
        $this->ejecutar(fn() => $this->servicio->eliminar($id, Actor::actual()),
            self::RUTA, 'Programa eliminado.', 'No se pudo eliminar el programa');
    }
}
