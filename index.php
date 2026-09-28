<?php
declare(strict_types=1);

/**
 * INDEX.PHP — Front controller.
 *
 * Único punto de entrada de las pantallas autenticadas: arranca la
 * configuración y la sesión, exige usuario y delega en el enrutador. La
 * tabla de rutas —que es también la matriz de control de acceso— está en
 * `config/rutas.php`.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

use Core\Router;

$path = Router::normalizarRuta($_SERVER['REQUEST_URI'] ?? '');

// Sin sesión no se entra a ninguna ruta. Una API responde 401 en JSON: una
// redirección al login la seguiría el `fetch` y recibiría HTML.
if (!isAuthenticated()) {
    if (Router::esRutaApi($path)) {
        Router::responder(401, 'Sesión requerida', 'La sesión ha expirado. Vuelve a iniciar sesión.', [], true);
        exit;
    }
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

if ($path === '/' || $path === '') {
    header('Location: ' . APP_URL . '/index.php/dashboard');
    exit;
}

$router = new Router();
(require __DIR__ . '/config/rutas.php')($router);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
