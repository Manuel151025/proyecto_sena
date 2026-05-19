<?php
/**
 * APP.PHP — Configuración de la aplicación
 */

// Configuración de la aplicación
define('APP_NAME', 'SENA Seguimiento');
define('APP_VERSION', '1.0.0');
define('APP_URL', '/proyecto_sena');

// Rutas base
define('BASE_PATH', dirname(__DIR__) . '/');
define('ASSETS_PATH', APP_URL . '/assets');
define('MODULES_PATH', APP_URL . '/modules');
define('UPLOADS_PATH', BASE_PATH . 'uploads/');

// Roles del sistema
define('ROL_COORDINADOR', 'coordinador');
define('ROL_INSTRUCTOR', 'instructor');
define('ROL_APRENDIZ', 'aprendiz');
