<?php
/**
 * INDEX.PHP — Enrutador principal
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

// Redirigir a login si no hay sesión
if (!isAuthenticated()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Redirigir al dashboard según el rol
$rol = getCurrentRole();
header("Location: " . APP_URL . "/modules/dashboard/{$rol}.php");
exit;