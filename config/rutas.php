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
    $U = 'Core\Controllers\UsuarioController';
    $router->add('GET', '/usuarios',          $U, 'index',    $COORDINADOR);
    $router->add('GET', '/usuarios/exportar', $U, 'exportar', $COORDINADOR);
    $router->accion('/usuarios', 'crear',       $U, 'crear',       $COORDINADOR);
    $router->accion('/usuarios', 'editar',      $U, 'editar',      $COORDINADOR);
    $router->accion('/usuarios', 'estado',      $U, 'estado',      $COORDINADOR);
    $router->accion('/usuarios', 'restablecer', $U, 'restablecer', $COORDINADOR);

    // ---------------------------------------------------------------------
    // IMPORTACIONES TABULARES (CSV / XLSX / XLS) — dos pasos: analizar y confirmar
    // ---------------------------------------------------------------------
    $IMP = 'Core\Controllers\ImportacionController';
    $importacion = static function (string $base, array $roles) use ($router, $IMP): void {
        $router->add('GET',  $base,                $IMP, 'formulario', $roles);
        $router->add('GET',  $base . '/plantilla', $IMP, 'plantilla',  $roles);
        $router->add('POST', $base,                $IMP, 'analizar',   $roles);
        $router->accion($base, 'confirmar', $IMP, 'confirmar', $roles);
        $router->accion($base, 'cancelar',  $IMP, 'cancelar',  $roles);
    };
    $importacion('/usuarios/importar',               $COORDINADOR);
    $importacion('/competencias/importar',           $COORDINADOR);
    $importacion('/resultados-aprendizaje/importar', $COORDINADOR);
    $importacion('/matriculas/importar',             $COORDINADOR);

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

    $C = 'Core\Controllers\CompetenciasController';
    $router->add('GET', '/competencias', $C, 'index', $GESTION);
    $router->accion('/competencias', 'crear',    $C, 'crear',    $COORDINADOR);
    $router->accion('/competencias', 'editar',   $C, 'editar',   $COORDINADOR);
    $router->accion('/competencias', 'eliminar', $C, 'eliminar', $COORDINADOR);

    // Los RAP son diseño curricular: el instructor los consulta, la
    // coordinación los define.
    $R = 'Core\Controllers\ResultadosAprendizajeController';
    $router->add('GET', '/resultados-aprendizaje', $R, 'index', $GESTION);
    $router->accion('/resultados-aprendizaje', 'crear',    $R, 'crear',    $COORDINADOR);
    $router->accion('/resultados-aprendizaje', 'editar',   $R, 'editar',   $COORDINADOR);
    $router->accion('/resultados-aprendizaje', 'eliminar', $R, 'eliminar', $COORDINADOR);

    // ---------------------------------------------------------------------
    // FICHAS Y MATRÍCULAS
    // ---------------------------------------------------------------------
    // El aprendiz entra a /fichas: el controlador lo redirige a la suya.
    $FI = 'Core\Controllers\FichaController';
    $router->add('GET', '/fichas',          $FI, 'index',    $TODOS);
    $router->add('GET', '/fichas/ver',      $FI, 'ver',      $TODOS);
    $router->add('GET', '/fichas/exportar', $FI, 'exportar', $GESTION);
    $router->accion('/fichas', 'crear',    $FI, 'crear',    $COORDINADOR);
    $router->accion('/fichas', 'editar',   $FI, 'editar',   $COORDINADOR);
    $router->accion('/fichas', 'eliminar', $FI, 'eliminar', $COORDINADOR);

    $M = 'Core\Controllers\MatriculaController';
    $router->add('GET', '/matriculas',          $M, 'index',    $GESTION);
    $router->add('GET', '/matriculas/exportar', $M, 'exportar', $GESTION);
    $router->accion('/matriculas', 'matricular', $M, 'matricular', $COORDINADOR);
    $router->accion('/matriculas', 'editar',     $M, 'editar',     $COORDINADOR);
    $router->accion('/matriculas', 'retirar',    $M, 'retirar',    $COORDINADOR);

    $AS = 'Core\Controllers\AsignacionesController';
    $router->add('GET', '/asignaciones', $AS, 'index', $GESTION);
    $router->accion('/asignaciones', 'asignar',   $AS, 'asignar',   $COORDINADOR);
    $router->accion('/asignaciones', 'reasignar', $AS, 'reasignar', $COORDINADOR);
    $router->accion('/asignaciones', 'eliminar',  $AS, 'eliminar',  $COORDINADOR);

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
    // Cada rol ve sus juicios; los emiten instructores y coordinación. El
    // reporte de Sofia Plus entra por la importación en dos pasos.
    $EV = 'Core\Controllers\EvaluacionesController';
    $router->add('GET', '/evaluaciones',          $EV, 'index',    $TODOS);
    $router->add('GET', '/evaluaciones/exportar', $EV, 'exportar', $TODOS);
    $router->accion('/evaluaciones', 'evaluar', $EV, 'evaluar', $GESTION);
    $importacion('/evaluaciones/importar', $GESTION);

    $ambos('/seguimiento',            'Core\Controllers\SeguimientoController',  'index',  $TODOS);
    $EVI = 'Core\Controllers\EvidenciasController';
    $router->add('GET', '/evidencias',         $EVI, 'index',   $TODOS);
    $router->add('GET', '/evidencias/archivo', $EVI, 'archivo', $TODOS);   // el servicio comprueba de quién es
    $router->accion('/evidencias', 'enviar',   $EVI, 'enviar',   [ROL_APRENDIZ]);
    $router->accion('/evidencias', 'revisar',  $EVI, 'revisar',  $GESTION);
    $router->accion('/evidencias', 'eliminar', $EVI, 'eliminar', [ROL_COORDINADOR, ROL_APRENDIZ]);
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
