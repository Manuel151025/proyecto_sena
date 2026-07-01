<?php
require_once __DIR__ . '/../../includes/config.php';
$proyecto_id = isset($_GET['proyecto_id']) ? '?proyecto_id=' . (int)$_GET['proyecto_id'] : '';
header("Location: " . APP_URL . "/index.php/fases" . $proyecto_id, true, 301);
exit;
