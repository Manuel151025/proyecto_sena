<?php
/**
 * AUTH.PHP — Lógica de login/logout con soporte de sesiones por pestaña.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/**
 * Intentar login con credenciales.
 *
 * Flujo:
 *   1. Busca el usuario activo por email.
 *   2. Verifica la contraseña hasheada.
 *   3. Regenera el ID de sesión (previene session fixation).
 *   4. Escribe los datos solo en el slot de la pestaña actual, sin tocar las demás.
 */
function attemptLogin(string $email, string $password): bool {
    try {
        $db   = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT id, nombre, email, rol, avatar_color, estado
             FROM usuarios
             WHERE email = ? AND estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;
        if (!password_verify($password, getUserPassword($db, (int)$user['id']))) return false;

        // Resolver tabId de la pestaña que hace login
        $tabId = $_POST['_tab'] ?? ($_COOKIE['sena_tab'] ?? '');
        if (!preg_match('/^[a-z0-9]{8,24}$/', $tabId)) {
            $tabId = 'default';
        }

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        // Escribir datos solo en el slot de esta pestaña (no afecta otras pestañas)
        $_SESSION['tabs'][$tabId] = [
            'user_id'           => (int)$user['id'],
            'user_nombre'       => $user['nombre'],
            'user_email'        => $user['email'],
            'user_rol'          => $user['rol'],
            'user_avatar_color' => $user['avatar_color'],
        ];

        return true;

    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener contraseña hasheada de un usuario por ID.
 */
function getUserPassword(PDO $db, int $userId): ?string {
    try {
        $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['password'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Cerrar sesión de la pestaña actual.
 * Si era la última pestaña activa, destruye la sesión completa.
 */
function logout(): void {
    // Cerrar solo el slot de esta pestaña
    $tabId = getTabId();
    unset($_SESSION['tabs'][$tabId]);

    // Si no quedan otras pestañas con sesión, destruir completamente
    if (empty($_SESSION['tabs'] ?? [])) {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Procesar logout si se solicita por GET
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
