<?php
declare(strict_types=1);

/**
 * INDEX.PHP — Front controller.
 *
 * La tabla de rutas de abajo es, además, la matriz de control de acceso del
 * sistema: cada ruta declara qué roles pueden llegar a ella. Antes el
 * permiso vivía dentro del constructor de cada controlador, de modo que
 * para saber quién entraba a una pantalla había que abrir su clase; nueve
 * controladores se limitaban a comprobar que hubiera sesión, sin mirar el
 * rol, y eso no se veía desde ningún sitio.
 *
 * Los controladores conservan su propia comprobación: son dos capas, y la
 * suya cubre además las llamadas que no pasan por aquí.
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

// Atajos de legibilidad para la tabla de rutas.
$TODOS       = [ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ];
$GESTION     = [ROL_COORDINADOR, ROL_INSTRUCTOR];
$COORDINADOR = [ROL_COORDINADOR];

/**
 * Registra una ruta para GET y POST a la vez, que es el patrón de casi
 * todas las pantallas de este sistema (listan y procesan en el mismo
 * `index()`).
 */
$ambos = static function (string $ruta, string $ctrl, string $accion, array $roles) use ($router): void {
    $router->add('GET',  $ruta, $ctrl, $accion, $roles);
    $router->add('POST', $ruta, $ctrl, $accion, $roles);
};

// ---------------------------------------------------------------------
// PANEL
// ---------------------------------------------------------------------
$router->add('GET', '/dashboard', 'Core\Controllers\DashboardController', 'index', $TODOS);

// ---------------------------------------------------------------------
// GESTIÓN DE USUARIOS — solo coordinación
// ---------------------------------------------------------------------
$ambos('/usuarios',          'Core\Controllers\UsuarioController', 'index',  $COORDINADOR);
$ambos('/usuarios/crear',    'Core\Controllers\UsuarioController', 'create', $COORDINADOR);
$ambos('/usuarios/editar',   'Core\Controllers\UsuarioController', 'edit',   $COORDINADOR);
$ambos('/usuarios/importar', 'Core\Controllers\UsuarioController', 'import', $COORDINADOR);

// ---------------------------------------------------------------------
// ESTRUCTURA CURRICULAR — la define coordinación
// ---------------------------------------------------------------------
$ambos('/estructura',                  'Core\Controllers\EstructuraController', 'index',         $COORDINADOR);
$ambos('/estructura/editar_programa',  'Core\Controllers\EstructuraController', 'editPrograma',  $COORDINADOR);
$ambos('/estructura/editar_proyecto',  'Core\Controllers\EstructuraController', 'editProyecto',  $COORDINADOR);
$ambos('/estructura/importar',         'Core\Controllers\EstructuraController', 'import',        $COORDINADOR);

$ambos('/programas',        'Core\Controllers\ProgramasController', 'index',  $GESTION);
$ambos('/programas/crear',  'Core\Controllers\ProgramasController', 'create', $COORDINADOR);
$ambos('/programas/editar', 'Core\Controllers\ProgramasController', 'edit',   $COORDINADOR);

$ambos('/competencias',          'Core\Controllers\CompetenciasController', 'index',  $GESTION);
$ambos('/competencias/importar', 'Core\Controllers\CompetenciasController', 'import', $COORDINADOR);

$ambos('/resultados-aprendizaje',          'Core\Controllers\ResultadosAprendizajeController', 'index',  $GESTION);
$ambos('/resultados-aprendizaje/importar', 'Core\Controllers\ResultadosAprendizajeController', 'import', $COORDINADOR);

// ---------------------------------------------------------------------
// FICHAS Y MATRÍCULAS
// ---------------------------------------------------------------------
// El aprendiz entra a /fichas: el controlador lo redirige a la suya.
$ambos('/fichas',        'Core\Controllers\FichaController', 'index', $TODOS);
$router->add('GET', '/fichas/ver', 'Core\Controllers\FichaController', 'view', $TODOS);
$ambos('/fichas/crear',  'Core\Controllers\FichaController', 'edit',  $COORDINADOR);
$ambos('/fichas/editar', 'Core\Controllers\FichaController', 'edit',  $COORDINADOR);

$ambos('/matriculas',   'Core\Controllers\MatriculaController',   'index', $GESTION);
$ambos('/asignaciones', 'Core\Controllers\AsignacionesController', 'index', $GESTION);

// ---------------------------------------------------------------------
// PROYECTO FORMATIVO
// ---------------------------------------------------------------------
// Proyectos, fases y actividades los consulta también el aprendiz; los
// controladores filtran la escritura por rol.
$ambos('/proyectos',    'Core\Controllers\ProyectosController',    'index', $TODOS);
$ambos('/fases',        'Core\Controllers\FasesController',        'index', $TODOS);
$ambos('/actividades',  'Core\Controllers\ActividadesController',  'index', $TODOS);

// ---------------------------------------------------------------------
// EVALUACIÓN Y SEGUIMIENTO
// ---------------------------------------------------------------------
$ambos('/evaluaciones',           'Core\Controllers\EvaluacionesController', 'index',  $TODOS);
$ambos('/evaluaciones/importar',  'Core\Controllers\EvaluacionesController', 'import', $GESTION);
$ambos('/seguimiento',            'Core\Controllers\SeguimientoController',  'index',  $TODOS);
$ambos('/evidencias',             'Core\Controllers\EvidenciasController',   'index',  $TODOS);
$ambos('/retroalimentacion',      'Core\Controllers\RetroalimentacionController', 'index', $TODOS);
$router->add('GET', '/mejoramiento', 'Core\Controllers\MejoramientoController', 'index', $TODOS);

// ---------------------------------------------------------------------
// REPORTES Y AUDITORÍA
// ---------------------------------------------------------------------
$ambos('/reportes', 'Core\Controllers\ReportesController', 'index', $GESTION);
$router->add('GET', '/logs', 'Core\Controllers\LogsController', 'index', $COORDINADOR);

// ---------------------------------------------------------------------
// TRANSVERSALES
// ---------------------------------------------------------------------
$ambos('/perfil',       'Core\Controllers\PerfilController',       'index', $TODOS);
$ambos('/calendario',   'Core\Controllers\CalendarioController',   'index', $TODOS);
$router->add('GET', '/calendario/api', 'Core\Controllers\CalendarioController', 'apiEvents', $TODOS);

// Sesión y avisos. Pasan por el enrutador para que ningún archivo de
// `includes/` tenga que ser accesible desde el navegador.
$router->add('POST', '/logout',             'Core\Controllers\SesionController',         'cerrar', $TODOS);
$router->add('GET',  '/api/notificaciones', 'Core\Controllers\NotificacionesController', 'listar', $TODOS);
$router->add('POST', '/api/notificaciones', 'Core\Controllers\NotificacionesController', 'marcar', $TODOS);
$ambos('/configuracion', 'Core\Controllers\ConfiguracionController', 'index', $COORDINADOR);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
