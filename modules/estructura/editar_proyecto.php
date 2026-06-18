<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
$id = (int)($_GET['id'] ?? 0);
header('Location: ' . APP_URL . '/index.php/estructura/editar_proyecto' . ($id > 0 ? '?id=' . $id : ''));
exit;
