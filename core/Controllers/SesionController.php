<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;

/**
 * Cierre de sesión.
 *
 * Antes lo atendía `includes/auth.php` al pedirse directamente desde el
 * navegador. Moverlo al enrutador permite cerrar `includes/` al acceso
 * web. Solo admite POST, y el token CSRF lo exige `session.php` para
 * todo POST antes de llegar aquí: un tercero no puede expulsar al usuario
 * incrustando una imagen que apunte a esta dirección.
 */
class SesionController extends BaseController {
    public function cerrar(): never {
        require_once BASE_PATH . 'includes/auth.php';
        logout();
    }
}
