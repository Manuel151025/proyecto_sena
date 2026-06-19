<?php
/**
 * APP.PHP — Configuración de la aplicación
 */

// Configuración de la aplicación
define('APP_NAME', 'SENA Seguimiento');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') !== false ? getenv('APP_URL') : '/proyecto_sena');

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
define('DEV_MODE', filter_var(getenv('DEV_MODE') !== false ? getenv('DEV_MODE') : 'true', FILTER_VALIDATE_BOOLEAN));

