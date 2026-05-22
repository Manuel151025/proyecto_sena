<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

// Inclusión de los archivos de la arquitectura MVC
require_once __DIR__ . '/../../core/models/UsuarioModel.php';
require_once __DIR__ . '/../../core/controllers/UsuarioController.php';

use Core\Controllers\UsuarioController;

// Restringir el acceso según el rol
requireRole(ROL_COORDINADOR);

// Inicializamos y delegamos el control al UsuarioController
$controller = new UsuarioController();
$controller->import();
