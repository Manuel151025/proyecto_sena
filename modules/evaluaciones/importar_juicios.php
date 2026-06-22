<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';

// Redirigir al enrutador central
header('Location: ' . APP_URL . '/index.php/evaluaciones/importar');
exit;
