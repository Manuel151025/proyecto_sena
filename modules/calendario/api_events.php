<?php
require_once __DIR__ . '/../../includes/config.php';
$params = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: " . APP_URL . "/index.php/calendario/api" . $params, true, 301);
exit;
