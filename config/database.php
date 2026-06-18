<?php
/**
 * DATABASE.PHP — Credenciales de la base de datos cargadas desde .env
 */

// Cargar variables de entorno
require_once __DIR__ . '/../includes/env_loader.php';

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sena_seguimiento');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

