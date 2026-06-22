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

// Si la ruta está vacía o es la raíz, redirigir al dashboard
if ($path === '/' || empty($path)) {
    header("Location: " . APP_URL . "/index.php/dashboard");
    exit;
}

// Inicializar el Router
$router = new Router();

// Registrar rutas
$router->add('GET', '/matriculas', 'Core\Controllers\MatriculaController', 'index');
$router->add('POST', '/matriculas', 'Core\Controllers\MatriculaController', 'index');

// Rutas de Usuarios
$router->add('GET', '/usuarios', 'Core\Controllers\UsuarioController', 'index');
$router->add('POST', '/usuarios', 'Core\Controllers\UsuarioController', 'index');
$router->add('GET', '/usuarios/crear', 'Core\Controllers\UsuarioController', 'create');
$router->add('POST', '/usuarios/crear', 'Core\Controllers\UsuarioController', 'create');
$router->add('GET', '/usuarios/editar', 'Core\Controllers\UsuarioController', 'edit');
$router->add('POST', '/usuarios/editar', 'Core\Controllers\UsuarioController', 'edit');
$router->add('GET', '/usuarios/importar', 'Core\Controllers\UsuarioController', 'import');
$router->add('POST', '/usuarios/importar', 'Core\Controllers\UsuarioController', 'import');

// Rutas de Estructura Curricular
$router->add('GET', '/estructura', 'Core\Controllers\EstructuraController', 'index');
$router->add('POST', '/estructura', 'Core\Controllers\EstructuraController', 'index');
$router->add('GET', '/estructura/editar_programa', 'Core\Controllers\EstructuraController', 'editPrograma');
$router->add('POST', '/estructura/editar_programa', 'Core\Controllers\EstructuraController', 'editPrograma');
$router->add('GET', '/estructura/editar_proyecto', 'Core\Controllers\EstructuraController', 'editProyecto');
$router->add('POST', '/estructura/editar_proyecto', 'Core\Controllers\EstructuraController', 'editProyecto');
$router->add('GET', '/estructura/importar', 'Core\Controllers\EstructuraController', 'import');
$router->add('POST', '/estructura/importar', 'Core\Controllers\EstructuraController', 'import');

// Rutas de Programas
$router->add('GET', '/programas', 'Core\Controllers\ProgramasController', 'index');
$router->add('POST', '/programas', 'Core\Controllers\ProgramasController', 'index');
$router->add('GET', '/programas/crear', 'Core\Controllers\ProgramasController', 'create');
$router->add('POST', '/programas/crear', 'Core\Controllers\ProgramasController', 'create');
$router->add('GET', '/programas/editar', 'Core\Controllers\ProgramasController', 'edit');
$router->add('POST', '/programas/editar', 'Core\Controllers\ProgramasController', 'edit');

// Rutas de Competencias
$router->add('GET', '/competencias', 'Core\Controllers\CompetenciasController', 'index');
$router->add('POST', '/competencias', 'Core\Controllers\CompetenciasController', 'index');
$router->add('GET', '/competencias/importar', 'Core\Controllers\CompetenciasController', 'import');
$router->add('POST', '/competencias/importar', 'Core\Controllers\CompetenciasController', 'import');

// Rutas de Resultados de Aprendizaje (RAPs)
$router->add('GET', '/resultados-aprendizaje', 'Core\Controllers\ResultadosAprendizajeController', 'index');
$router->add('POST', '/resultados-aprendizaje', 'Core\Controllers\ResultadosAprendizajeController', 'index');
$router->add('GET', '/resultados-aprendizaje/importar', 'Core\Controllers\ResultadosAprendizajeController', 'import');
$router->add('POST', '/resultados-aprendizaje/importar', 'Core\Controllers\ResultadosAprendizajeController', 'import');

// Rutas de Fichas
$router->add('GET', '/fichas', 'Core\Controllers\FichaController', 'index');
$router->add('POST', '/fichas', 'Core\Controllers\FichaController', 'index');

// Rutas de Evaluaciones (Juicios)
$router->add('GET', '/evaluaciones/importar', 'Core\Controllers\EvaluacionController', 'import');
$router->add('POST', '/evaluaciones/importar', 'Core\Controllers\EvaluacionController', 'import');

// Rutas de Dashboard
$router->add('GET', '/dashboard', 'Core\Controllers\DashboardController', 'index');

// Despachar la petición
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);