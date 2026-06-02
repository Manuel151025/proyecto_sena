<?php
declare(strict_types=1);

/**
 * INDEX.PHP — Enrutador principal y Front Controller
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

use Core\Router;

// Redirigir a login si no hay sesión
if (!isAuthenticated()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Analizar la ruta solicitada
$uri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$path = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');

if (strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
} else {
    $baseDir = str_replace('\\', '/', dirname($scriptName));
    if ($baseDir !== '/' && !empty($baseDir) && strpos($path, $baseDir) === 0) {
        $path = substr($path, strlen($baseDir));
    }
}
$path = '/' . trim($path, '/');

// Si la ruta está vacía o es la raíz, redirigir al dashboard según el rol
if ($path === '/' || empty($path)) {
    $rol = getCurrentRole();
    header("Location: " . APP_URL . "/modules/dashboard/{$rol}.php");
    exit;
}

// Inicializar el Router
$router = new Router();

// Registrar rutas
$router->add('GET', '/matriculas', 'Core\Controllers\MatriculaController', 'index');
$router->add('POST', '/matriculas', 'Core\Controllers\MatriculaController', 'index');

// Despachar la petición
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);