<?php
/**
 * SESSION.PHP — Manejo de sesiones y autenticación
 */
session_start();

require_once __DIR__ . '/config.php';

/**
 * Verificar si el usuario está autenticado
 */
function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_rol']);
}

/**
 * Obtener datos del usuario actual
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_nombre'],
        'email' => $_SESSION['user_email'],
        'rol' => $_SESSION['user_rol'],
        'avatar_color' => $_SESSION['user_avatar_color'] ?? '#39A900',
    ];
}

/**
 * Obtener rol actual
 */
function getCurrentRole(): string {
    return $_SESSION['user_rol'] ?? '';
}

/**
 * Requiere autenticación, redirige a login si no está autenticado
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Requiere un rol específico
 */
function requireRole(string ...$roles): void {
    requireAuth();
    if (!in_array(getCurrentRole(), $roles)) {
        header('Location: ' . APP_URL . '/modules/dashboard/' . getCurrentRole() . '.php');
        exit;
    }
}

/**
 * Obtener iniciales del nombre
 */
function getInitials(string $name): string {
    $words = explode(' ', $name);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return $initials;
}
