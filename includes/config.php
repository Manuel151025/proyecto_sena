<?php
/**
 * CONFIG.PHP — Bootstrap de configuración global del sistema
 * Sistema de Seguimiento de Proyectos Formativos SENA
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/navigation.php';
require_once __DIR__ . '/../vendor/autoload.php';

// El manejador de errores se registra lo antes posible, en cuanto existen
// DEV_MODE y BASE_PATH: cualquier fallo anterior a esta línea seguiría
// saliendo como traza de PHP, así que aquí no debe ir nada que pueda fallar.
Core\Support\ManejadorErrores::registrar();

// Cabeceras de seguridad antes de emitir contenido. Van después del
// manejador para que, si algo falla al calcularlas, quede registrado.
Core\Support\Seguridad::cabeceras();

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/functions.php';
