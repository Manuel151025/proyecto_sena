<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Exportacion\Exportador;
use Core\Models\LogsModel;
use Core\Services\Auditoria;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Validador;
use Throwable;

/**
 * Bitácora de auditoría (RNF02), solo coordinación.
 *
 *   GET /logs?search=&accion=&modulo=&usuario_id=&desde=&hasta=
 *   GET /logs/exportar?...&formato=xlsx|csv
 */
class LogsController extends BaseController {
    private const COLOR = [
        'Crear' => 'success', 'Matricular' => 'success', 'Asignar' => 'success', 'Importar' => 'success',
        'Editar' => 'warning', 'Modificar' => 'warning', 'Reasignar' => 'warning', 'Revisar' => 'primary', 'Calificar' => 'primary', 'Cerrar' => 'primary',
        'Eliminar' => 'danger', 'Retirar' => 'danger', 'Acceso denegado' => 'danger', 'Login fallido' => 'danger',
        'Login' => 'info', 'Logout' => 'secondary', 'Exportar' => 'secondary',
    ];

    private LogsModel $modelo;

    public function __construct(?LogsModel $modelo = null) {
        $this->modelo = $modelo ?? new LogsModel();
    }

    public function index(): void {
        $this->exigirRol(ROL_COORDINADOR);
        [$filtros, $errors] = $this->filtros();
        $logs = $acciones = $modulos = $usuarios = [];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->modelo->contar($filtros), 50);
            $logs = $this->modelo->listar($filtros, $paginacion->perPage(), $paginacion->offset());
            $acciones = $this->modelo->valores('accion');
            $modulos = $this->modelo->valores('modulo');
            $usuarios = $this->modelo->usuarios();
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar la bitácora');
        }
        $this->render(BASE_PATH . 'modules/logs/views/index.view.php', [
            'errors' => $errors, 'logs' => $logs, 'filtros' => $filtros, 'paginacion' => $paginacion,
            'acciones' => $acciones, 'modulos' => $modulos, 'usuarios' => $usuarios, 'colores' => self::COLOR,
        ], 'Bitácora de auditoría · SENA');
    }

    public function exportar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        [$filtros, $errors] = $this->filtros();
        if ($errors !== []) {
            $this->fallo($errors, '/logs');
        }
        $formato = ($_GET['formato'] ?? '') === 'csv' ? 'csv' : 'xlsx';
        $filas = $this->modelo->paraExportar($filtros, Exportador::MAX_FILAS);
        (new Auditoria())->operacion(Actor::actual(), 'Exportar', 'Auditoría', 'logs_sistema', null, count($filas) . " registros de la bitácora en $formato");
        $enc = ['Fecha', 'Usuario', 'Rol', 'Acción', 'Módulo', 'Tabla', 'Registro', 'Descripción', 'IP'];
        $nombre = 'bitacora_' . date('Ymd_His') . '.' . $formato;
        Exportador::descargar($formato === 'csv' ? Exportador::csv($nombre, $enc, $filas)
            : Exportador::xlsx('Bitácora', $enc, $filas, ['titulo' => 'Bitácora de auditoría · ' . date('d/m/Y'), 'anchos' => [19, 26, 12, 16, 16, 18, 9, 60, 15]]),
            $nombre, $formato);
    }

    /** @return array{0:array, 1:string[]} */
    private function filtros(): array {
        $v = new Validador($_GET);
        $f = [
            'search'     => $this->consulta()->busquedaCruda('search'),
            'accion'     => $v->texto('accion', 'La acción', 0, 100, false),
            'modulo'     => $v->texto('modulo', 'El módulo', 0, 100, false),
            'usuario_id' => $v->id('usuario_id', 'El usuario', false),
            'desde'      => $v->fecha('desde', 'La fecha inicial', false),
            'hasta'      => $v->fecha('hasta', 'La fecha final', false),
        ];
        $v->rangoFechas($f['desde'], $f['hasta'], 'La fecha final');
        return [$f, $v->errores()];
    }
}
