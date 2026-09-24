<?php
/**
 * APP.PHP — Configuración de la aplicación
 */

// Configuración de la aplicación
define('APP_NAME', 'SENA Seguimiento');
define('APP_VERSION', '3.2.0');
define('APP_URL', getenv('APP_URL') !== false ? getenv('APP_URL') : '/proyecto_sena');

// Dominio de confianza para construir URLs absolutas (ej. enlaces de correo).
// Si no se configura explícitamente, se usa el Host de la petición como
// último recurso (solo aceptable en desarrollo local) — en producción debe
// definirse APP_HOST en .env para no confiar en un header controlado por el cliente.
define('APP_HOST', getenv('APP_HOST') !== false ? getenv('APP_HOST') : ($_SERVER['HTTP_HOST'] ?? 'localhost'));

// Zona horaria. Sin fijarla, PHP usaba la del php.ini (Europe/Berlin en
// XAMPP, UTC en la imagen Docker): desde las 7 de la noche en Colombia, la
// fecha de un juicio o el vencimiento de un plan salían con el día
// siguiente, mientras la base (CURDATE) seguía en el de hoy.
$zonaHoraria = getenv('APP_TIMEZONE') !== false && getenv('APP_TIMEZONE') !== '' ? (string)getenv('APP_TIMEZONE') : 'America/Bogota';
if (!in_array($zonaHoraria, timezone_identifiers_list(), true)) {
    $zonaHoraria = 'America/Bogota';
}
date_default_timezone_set($zonaHoraria);
define('APP_TIMEZONE', $zonaHoraria);
unset($zonaHoraria);

// Rutas base
define('BASE_PATH', dirname(__DIR__) . '/');
define('ASSETS_PATH', APP_URL . '/assets');
define('MODULES_PATH', APP_URL . '/modules');
define('UPLOADS_PATH', BASE_PATH . 'uploads/');

// Roles del sistema
define('ROL_COORDINADOR', 'coordinador');
define('ROL_INSTRUCTOR', 'instructor');
define('ROL_APRENDIZ', 'aprendiz');

// Modo desarrollo: muestra el enlace de recuperación en pantalla.
// Cambiar a false antes de desplegar en producción.
define('DEV_MODE', filter_var(getenv('DEV_MODE') !== false ? getenv('DEV_MODE') : 'false', FILTER_VALIDATE_BOOLEAN));

