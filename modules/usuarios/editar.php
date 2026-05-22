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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . APP_URL . '/modules/usuarios/');
    exit;
}

// Inicializamos y delegamos el control al UsuarioController
$controller = new UsuarioController();
$controller->edit($id);
