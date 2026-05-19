<?php
/**
 * AUTH.PHP — Lógica de login/logout con base de datos
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/**
 * Intentar login con credenciales de base de datos
 */
function attemptLogin(string $email, string $password): bool {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, nombre, email, rol, avatar_color, estado FROM usuarios WHERE email = ? AND estado = 'activo' LIMIT 1");
        $stmt->execute([$email]);
        
        $user = $stmt->fetch();
        if (!$user) {
            return false;
        }

        if (!password_verify($password, getUserPassword($db, $user['id']))) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_rol'] = $user['rol'];
        $_SESSION['user_avatar_color'] = $user['avatar_color'];
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener contraseña hasheada de usuario
 */
function getUserPassword($db, int $userId): ?string {
    try {
        $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user['password'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Cerrar sesión
 */
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Procesar logout si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
