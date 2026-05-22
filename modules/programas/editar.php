<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
header('Location: ' . MODULES_PATH . '/programas/crear.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
exit;
