<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\UsuarioFormulario;
use Core\Models\PerfilModel;
use Core\Services\Auditoria;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\PoliticaContrasena;
use Core\Support\Validador;
use Throwable;

/**
 * Perfil propio (todos los roles).
 *
 *   GET  /perfil
 *   POST /perfil  action=datos        nombre y color del avatar
 *   POST /perfil  action=contrasena   cambio de contraseña (la temporal obliga a pasar por aquí)
 *
 * El nombre y los colores usan las mismas reglas que la gestión de usuarios
 * (antes el perfil rechazaba apóstrofos que la coordinación sí admitía, y
 * tenía otra paleta), y la contraseña, PoliticaContrasena.
 */
class PerfilController extends BaseController {
    private PerfilModel $modelo;

    public function __construct(?PerfilModel $modelo = null) {
        $this->modelo = $modelo ?? new PerfilModel();
    }

    public function index(): void {
        $errors = [];
        $user = ['nombre' => '', 'email' => '', 'rol' => '', 'avatar_color' => '#39A900', 'fecha_creacion' => ''];
        try {
            $user = $this->modelo->getPerfil(Actor::actual()->id) ?? $user;
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar tu perfil');
        }
        $this->render(BASE_PATH . 'modules/perfil/views/index.view.php', [
            'errors'              => $errors,
            'user'                => $user,
            'colores'             => UsuarioFormulario::COLORES,
            'debeCambiarPassword' => (bool)(tabData()['debe_cambiar_password'] ?? false),
            'minimo'              => PoliticaContrasena::MIN,
        ], 'Mi perfil · SENA');
    }

    public function datos(): never {
        $actor = Actor::actual();
        $v = $this->entrada();
        $nombre = $v->patron('nombre', 'El nombre', Validador::PATRON_PERSONA, 'letras, espacios, apóstrofo y guion', 3, UsuarioFormulario::MAX_NOMBRE);
        $color = $v->enum('avatar_color', 'El color', UsuarioFormulario::COLORES);
        $this->siHayErrores($v, '/perfil');
        $this->ejecutar(function () use ($actor, $nombre, $color) {
            $this->modelo->updateProfile($actor->id, $nombre, $color);
            // La cabecera lee el nombre y el color de la sesión de la pestaña.
            $_SESSION['tabs'][getTabId()]['user_nombre'] = $nombre;
            $_SESSION['tabs'][getTabId()]['user_avatar_color'] = $color;
            (new Auditoria())->operacion($actor, 'Editar', 'Perfil', 'usuarios', $actor->id, 'Actualizó sus datos personales');
        }, '/perfil', 'Datos actualizados.', 'No se pudieron guardar los cambios');
    }

    public function contrasena(): never {
        $actor = Actor::actual();
        $actual = (string)($_POST['password_actual'] ?? '');
        $nueva = (string)($_POST['password_nueva'] ?? '');
        $confirmar = (string)($_POST['password_confirmar'] ?? '');
        $perfil = $this->modelo->getPerfil($actor->id) ?? [];

        $errores = PoliticaContrasena::errores($nueva, (string)($perfil['email'] ?? ''), (string)($perfil['nombre'] ?? ''));
        if ($actual === '') {
            $errores[] = 'Escribe tu contraseña actual.';
        }
        if ($nueva !== $confirmar) {
            $errores[] = 'La confirmación no coincide con la nueva contraseña.';
        }
        if ($errores === [] && hash_equals($actual, $nueva)) {
            $errores[] = 'La nueva contraseña tiene que ser distinta de la actual.';
        }
        if ($errores === [] && !$this->modelo->verifyPassword($actor->id, $actual)) {
            (new Auditoria())->operacion($actor, 'Contraseña incorrecta', 'Perfil', 'usuarios', $actor->id, 'Intento de cambio de contraseña con la actual errada');
            $errores[] = 'La contraseña actual no es correcta.';
        }
        if ($errores !== []) {
            $this->fallo($errores, '/perfil');
        }
        $this->ejecutar(function () use ($actor, $nueva) {
            $this->modelo->changePassword($actor->id, $nueva);
            $_SESSION['tabs'][getTabId()]['debe_cambiar_password'] = false;
            // Identificador de sesión nuevo tras cambiar la credencial.
            session_regenerate_id(true);
            (new Auditoria())->operacion($actor, 'Cambiar contraseña', 'Perfil', 'usuarios', $actor->id, 'Cambió su contraseña');
        }, '/perfil', 'Contraseña actualizada.', 'No se pudo cambiar la contraseña');
    }
}
