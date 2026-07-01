<?php
require_once __DIR__ . '/../../includes/config.php';
$id = isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : '';
header("Location: " . APP_URL . "/index.php/fichas/editar" . $id, true, 301);
exit;
