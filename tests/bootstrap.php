<?php
declare(strict_types=1);

/**
 * Arranque del entorno de pruebas.
 *
 * El proyecto no está pensado para ejecutarse fuera de una petición HTTP:
 * `session.php` arranca la sesión y valida CSRF al ser incluido, y
 * `config.php` define constantes leyendo `$_SERVER`. Aquí se simula una
 * petición GET mínima para que todo eso funcione bajo la línea de comandos
 * sin tocar el código de producción.
 */

// Petición simulada. Debe estar completa ANTES de incluir nada del
// proyecto: session.php lee SERVER_PORT y REQUEST_METHOD al cargarse.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/proyecto_sena/index.php/dashboard';
$_SERVER['SCRIPT_NAME']    = '/proyecto_sena/index.php';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['SERVER_PORT']    = '80';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

$raiz = dirname(__DIR__);

require_once $raiz . '/includes/config.php';
require_once $raiz . '/includes/session.php';
require_once $raiz . '/includes/functions.php';

// auth.php trae attemptLogin() y logout(), que las pruebas de seguridad
// ejercitan. Al final del archivo hay un bloque que cierra la sesión si
// llega `action=logout`; con la petición simulada de arriba no se dispara,
// pero conviene saber que está ahí antes de tocar $_GET en el bootstrap.
require_once $raiz . '/includes/auth.php';
require_once $raiz . '/includes/notificaciones.php';

// La sesión de CLI no persiste entre procesos; se asegura que exista el
// contenedor que esperan las funciones de sesión.
if (!isset($_SESSION['tabs'])) {
    $_SESSION['tabs'] = [];
}

require_once __DIR__ . '/CasoDePrueba.php';
require_once __DIR__ . '/CasoConBaseDeDatos.php';
