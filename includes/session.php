<?php
/**
 * SESSION.PHP — Sesiones con soporte de múltiples pestañas simultáneas.
 *
 * Cada pestaña del navegador genera su propio tabId (via sessionStorage en JS).
 * Los datos de sesión se almacenan bajo $_SESSION['tabs'][$tabId], de modo que
 * cada pestaña puede tener un usuario y rol diferente sin interferir con las demás.
 */

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// GESTIÓN DEL ID DE PESTAÑA
// ---------------------------------------------------------------------------

/**
 * Obtiene el tabId de la pestaña actual.
 *
 * Orden de prioridad:
 *   1. Parámetro GET/POST '_tab'  → para llamadas AJAX que lo envían explícitamente
 *   2. Cookie 'sena_tab'          → para navegaciones normales (se establece JIT en JS)
 *   3. 'default'                  → fallback cuando no hay contexto de pestaña
 */
function getTabId(): string {
    $t = $_GET['_tab'] ?? ($_POST['_tab'] ?? ($_COOKIE['sena_tab'] ?? ''));
    return preg_match('/^[a-z0-9]{8,24}$/', $t) ? $t : 'default';
}

/**
 * Devuelve los datos de sesión de la pestaña actual.
 */
function tabData(): array {
    return $_SESSION['tabs'][getTabId()] ?? [];
}

// ---------------------------------------------------------------------------
// AUTENTICACIÓN
// ---------------------------------------------------------------------------

/**
 * Devuelve true si hay un usuario autenticado en esta pestaña.
 */
function isAuthenticated(): bool {
    $d = tabData();
    return isset($d['user_id'], $d['user_rol'], $d['user_nombre']);
}

/**
 * Obtiene los datos del usuario actual de esta pestaña.
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) return null;
    $d = tabData();
    return [
        'id'           => $d['user_id'],
        'nombre'       => $d['user_nombre'],
        'email'        => $d['user_email'],
        'rol'          => $d['user_rol'],
        'avatar_color' => $d['user_avatar_color'] ?? '#39A900',
    ];
}

/**
 * Obtiene el rol del usuario de esta pestaña.
 */
function getCurrentRole(): string {
    return tabData()['user_rol'] ?? '';
}

// ---------------------------------------------------------------------------
// VALIDACIÓN CONTRA BASE DE DATOS
// ---------------------------------------------------------------------------

/**
 * Valida la sesión de la pestaña actual contra la BD (máximo una vez por petición).
 *
 * - Si el usuario fue desactivado → cierra solo esta pestaña.
 * - Si el coordinador cambió su rol → lo sincroniza en sesión.
 */
function validateSession(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $d   = tabData();
    $uid = (int)($d['user_id'] ?? 0);
    if ($uid === 0) return;

    require_once __DIR__ . '/../core/Database.php';
    try {
        $db   = Core\Database::getConnection();
        $stmt = $db->prepare("SELECT rol, estado FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['estado'] !== 'activo') {
            // Usuario inactivo o eliminado → cerrar solo esta pestaña
            unset($_SESSION['tabs'][getTabId()]);
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }

        // Sincronizar rol si fue modificado por el coordinador
        if ($_SESSION['tabs'][getTabId()]['user_rol'] !== $row['rol']) {
            $_SESSION['tabs'][getTabId()]['user_rol'] = $row['rol'];
        }

    } catch (Throwable $e) {
        // En fallo de BD se continúa con los datos de sesión existentes
    }
}

// ---------------------------------------------------------------------------
// CONTROL DE ACCESO
// ---------------------------------------------------------------------------

/**
 * Redirige a login si esta pestaña no tiene sesión activa.
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    validateSession();
}

/**
 * Requiere que el usuario tenga uno de los roles indicados.
 * Si el rol no coincide, redirige al dashboard propio del usuario.
 */
function requireRole(string ...$roles): void {
    requireAuth();
    if (!in_array(getCurrentRole(), $roles, true)) {
        header('Location: ' . APP_URL . '/modules/dashboard/' . getCurrentRole() . '.php');
        exit;
    }
}

/**
 * Comprueba sin redirigir si el usuario tiene uno de los roles.
 */
function hasRole(string ...$roles): bool {
    return in_array(getCurrentRole(), $roles, true);
}

// ---------------------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------------------

/**
 * Obtiene las iniciales (máx. 2) del nombre completo.
 */
function getInitials(string $name): string {
    $words    = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        if ($word !== '') {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
    }
    return $initials;
}
