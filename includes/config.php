<?php
/**
 * CONFIG.PHP — Bootstrap de configuración global del sistema
 * Sistema de Seguimiento de Proyectos Formativos SENA
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/navigation.php';
require_once __DIR__ . '/../vendor/autoload.php';
