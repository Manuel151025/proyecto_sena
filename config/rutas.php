<?php
declare(strict_types=1);

/**
 * TABLA DE RUTAS — y, con ella, la matriz de control de acceso.
 *
 * Cada línea dice qué pantalla u operación existe y qué roles la alcanzan:
 *
 *   $router->add('GET', '/ruta', Controlador::class, 'metodo', $ROLES);
 *       Una pantalla (GET) o un envío sin `action` (POST).
 *
 *   $router->accion('/ruta', 'nombre', Controlador::class, 'metodo', $ROLES);
 *       Una operación concreta de esa pantalla: el formulario envía
 *       `action=nombre` por POST. Su permiso se declara por separado del de
 *       la pantalla, porque no suelen coincidir (el aprendiz VE los
 *       proyectos, pero solo coordinación los CREA).
 *
 * Vive fuera de index.php para que las pruebas la carguen tal cual, sin
 * ejecutar el front controller ni evaluar trozos de su código.
 *
 * Los controladores repiten la comprobación de rol en cada acción: son dos
 * capas, y la suya protege también las llamadas que no pasan por aquí.
 */

use Core\Router;

return static function (Router $router): void {
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
    $E = 'Core\Controllers\EstructuraController';
    $router->add('GET',  '/estructura',          $E, 'index',    $COORDINADOR);
    $router->add('GET',  '/estructura/importar', $E, 'importar', $COORDINADOR);
    $router->add('POST', '/estructura/importar', $E, 'analizar', $COORDINADOR);   // paso 1: subir y analizar
    $router->accion('/estructura/importar', 'confirmar', $E, 'confirmar', $COORDINADOR);
    $router->accion('/estructura/importar', 'cancelar',  $E, 'cancelar',  $COORDINADOR);

    $P = 'Core\Controllers\ProgramasController';
    $router->add('GET', '/programas', $P, 'index', $GESTION);
    $router->accion('/programas', 'crear',    $P, 'crear',    $COORDINADOR);
    $router->accion('/programas', 'editar',   $P, 'editar',   $COORDINADOR);
    $router->accion('/programas', 'eliminar', $P, 'eliminar', $COORDINADOR);

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
    $router->add('GET', '/proyectos', 'Core\Controllers\ProyectosController', 'index', $TODOS);
    $router->accion('/proyectos', 'crear',    'Core\Controllers\ProyectosController', 'crear',    $COORDINADOR);
    $router->accion('/proyectos', 'editar',   'Core\Controllers\ProyectosController', 'editar',   $COORDINADOR);
    $router->accion('/proyectos', 'eliminar', 'Core\Controllers\ProyectosController', 'eliminar', $COORDINADOR);

    $F = 'Core\Controllers\FasesController';
    $router->add('GET', '/fases', $F, 'index', $TODOS);
    $router->accion('/fases', 'crear',    $F, 'crear',    $GESTION);
    $router->accion('/fases', 'editar',   $F, 'editar',   $GESTION);
    $router->accion('/fases', 'eliminar', $F, 'eliminar', $COORDINADOR);

    $A = 'Core\Controllers\ActividadesController';
    $router->add('GET', '/actividades', $A, 'index', $TODOS);
    $router->accion('/actividades', 'crear',    $A, 'crear',    $GESTION);
    $router->accion('/actividades', 'editar',   $A, 'editar',   $GESTION);
    $router->accion('/actividades', 'avance',   $A, 'avance',   $GESTION);
    $router->accion('/actividades', 'eliminar', $A, 'eliminar', $GESTION);

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
};
