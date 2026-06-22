<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';

// Redirigir al nuevo dashboard enrutado por el Front Controller
header('Location: ' . APP_URL . '/index.php/dashboard', true, 302);
exit;