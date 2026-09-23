<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\UsuarioFormulario;
use Core\Interfaces\UsuarioRepositoryInterface;
use Core\Models\UsuarioModel;
use Core\Services\Paginator;
use Core\Services\UsuariosService;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Administración de cuentas (solo coordinación).
 *
 *   GET  /usuarios?search=&rol=&estado=&pagina=
 *   POST /usuarios  action=crear | editar | estado | restablecer
 *
 * Antes crear y editar eran a la vez pantallas propias (/usuarios/crear,
 * /usuarios/editar) y extremos AJAX de dos modales que el layout incluía en
 * TODAS las páginas del coordinador; el GET por AJAX de /usuarios/editar
 * devolvía la ficha del usuario en JSON. Ahora son acciones de esta
 * pantalla, con Post/Redirect/Get.
 */
class UsuarioController extends BaseController {
    private const RUTA = '/usuarios';
    private const CLAVE_CREDENCIAL = 'credencial_temporal';

    private UsuarioRepositoryInterface $repo;
    private UsuariosService $servicio;

    public function __construct(?UsuarioRepositoryInterface $repo = null, ?UsuariosService $servicio = null) {
        $this->repo = $repo ?? new UsuarioModel();
        $this->servicio = $servicio ?? new UsuariosService(null, $this->repo);
    }

    public function index(): void {
        $q = $this->consulta();
        $filtros = [
            'search' => $q->busquedaCruda('search'),
            'rol'    => is_string($_GET['rol'] ?? null) ? $_GET['rol'] : '',
            'estado' => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '',
        ];
        $errors = [];
        $usuarios = [];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->repo->contar($filtros));
            $usuarios = $this->repo->listar($filtros, $paginacion->perPage(), $paginacion->offset());
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los usuarios');
        }

        // Contraseña temporal recién generada: se muestra una sola vez.
        $credencial = $_SESSION[self::CLAVE_CREDENCIAL] ?? null;
        unset($_SESSION[self::CLAVE_CREDENCIAL]);

        $this->render(BASE_PATH . 'modules/usuarios/views/index.view.php', [
            'errors'        => $errors,
            'usuarios'      => $usuarios,
            'paginacion'    => $paginacion,
            'filtros'       => $filtros,
            'credencial'    => $credencial,
            'actor_id'      => $this->usuarioId(),
            'abrir_nuevo'   => isset($_GET['nuevo']),
            'colores'       => UsuarioFormulario::COLORES,
            'roles_label'   => ['coordinador' => 'Coordinador', 'instructor' => 'Instructor', 'aprendiz' => 'Aprendiz'],
            'estados_label' => ['activo' => ['Activo', 'success'], 'inactivo' => ['Inactivo', 'warning'], 'bloqueado' => ['Bloqueado', 'danger']],
            'limites'       => ['nombre' => UsuarioFormulario::MAX_NOMBRE, 'email' => UsuarioFormulario::MAX_EMAIL],
        ], 'Usuarios · SENA');
    }

    /** GET /usuarios/exportar?formato=xlsx|csv (con los mismos filtros del listado). */
    public function exportar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $filtros = [
            'search' => $this->consulta()->busquedaCruda('search'),
            'rol'    => is_string($_GET['rol'] ?? null) ? $_GET['rol'] : '',
            'estado' => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '',
        ];
        $formato = ($_GET['formato'] ?? '') === 'csv' ? 'csv' : 'xlsx';
        $filas = array_map(static fn($u) => [
            $u['nombre'], $u['email'], ucfirst($u['rol']), ucfirst($u['estado']), date('Y-m-d', strtotime((string)$u['fecha_creacion'])),
        ], $this->repo->paraExportar($filtros, \Core\Exportacion\Exportador::MAX_FILAS));
        $enc = ['Nombre', 'Correo', 'Rol', 'Estado', 'Creación'];

        (new \Core\Services\Auditoria())->operacion(Actor::actual(), 'Exportar', 'Usuarios', 'usuarios', null,
            count($filas) . " cuentas exportadas en $formato");
        $nombre = 'usuarios_' . date('Ymd_His') . '.' . $formato;
        \Core\Exportacion\Exportador::descargar(
            $formato === 'csv'
                ? \Core\Exportacion\Exportador::csv($nombre, $enc, $filas)
                : \Core\Exportacion\Exportador::xlsx('Usuarios', $enc, $filas, ['titulo' => 'Usuarios del sistema · ' . date('d/m/Y'), 'anchos' => [36, 34, 14, 12, 12]]),
            $nombre, $formato);
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $d = UsuarioFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        try {
            $r = $this->servicio->crear($d, Actor::actual());
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo crear la cuenta'), $vuelta);
        }
        $_SESSION[self::CLAVE_CREDENCIAL] = ['nombre' => $d['nombre'], 'email' => $d['email'], 'password' => $r['temporal'], 'motivo' => 'creada'];
        $this->exito('Cuenta creada.', $vuelta);
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El usuario');
        $d = UsuarioFormulario::validar($v, true);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editar($id, $d, Actor::actual()), $vuelta, 'Cuenta actualizada.', 'No se pudo actualizar la cuenta');
    }

    public function estado(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El usuario');
        $estado = $v->enum('estado', 'El estado', Enums::USUARIO_ESTADO);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->cambiarEstado($id, $estado, Actor::actual()), $vuelta,
            $estado === 'activo' ? 'Cuenta activada.' : 'Cuenta desactivada: ya no podrá iniciar sesión, y sus registros se conservan.',
            'No se pudo cambiar el estado');
    }

    public function restablecer(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El usuario');
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        try {
            $temporal = $this->servicio->restablecerContrasena($id, Actor::actual());
            $u = $this->repo->findById($id);
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo restablecer la contraseña'), $vuelta);
        }
        $_SESSION[self::CLAVE_CREDENCIAL] = ['nombre' => $u['nombre'] ?? '', 'email' => $u['email'] ?? '', 'password' => $temporal, 'motivo' => 'restablecida'];
        $this->exito('Contraseña restablecida.', $vuelta);
    }
}
