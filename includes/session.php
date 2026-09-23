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

// Bajo HTTPS la cookie va como Secure; bajo HTTP no, o el navegador no la
// enviaría. `SERVER_PORT` no existe en CLI ni en algunas configuraciones de
// FastCGI, y leerlo a pelo provocaba un "Undefined array key" que, al estar
// antes de session_start(), imprimía salida y dejaba la sesión sin arrancar.
$isSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
ini_set('session.cookie_secure', $isSecure ? '1' : '0');

session_start();

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// CADUCIDAD DE LA SESIÓN
// ---------------------------------------------------------------------------

/** Inactividad máxima antes de cerrar sesión (2 horas). */
const SESION_INACTIVIDAD_MAX = 7200;

/** Duración máxima de una sesión aunque haya actividad continua (12 horas). */
const SESION_DURACION_MAX = 43200;

/** Pestañas simultáneas que se conservan por sesión. */
const SESION_MAX_PESTANAS = 12;

/**
 * Cierra la sesión si lleva demasiado tiempo inactiva o abierta.
 *
 * Antes no caducaba nunca: una sesión abierta en un equipo compartido del
 * centro seguía siendo válida indefinidamente mientras la cookie existiera.
 */
function aplicarCaducidadSesion(): void {
    $ahora = time();

    $inicio = $_SESSION['_creada'] ?? null;
    $ultimo = $_SESSION['_ultimo_acceso'] ?? null;

    if ($inicio === null) {
        $_SESSION['_creada'] = $ahora;
        $_SESSION['_ultimo_acceso'] = $ahora;
        return;
    }

    $expirada = ($ultimo !== null && $ahora - $ultimo > SESION_INACTIVIDAD_MAX)
             || ($ahora - $inicio > SESION_DURACION_MAX);

    if ($expirada) {
        $_SESSION = [];
        if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        session_start();
        $_SESSION['_creada'] = $ahora;
        $_SESSION['_ultimo_acceso'] = $ahora;
        return;
    }

    $_SESSION['_ultimo_acceso'] = $ahora;
}

/**
 * Descarta los slots de pestaña más antiguos.
 *
 * Cada pestaña nueva crea su propia entrada en `$_SESSION['tabs']` y nada
 * las retiraba: navegar con muchas pestañas a lo largo del día hacía crecer
 * el archivo de sesión indefinidamente. Se conservan las más recientes.
 */
function recolectarPestanas(): void {
    if (!isset($_SESSION['tabs']) || !is_array($_SESSION['tabs'])) {
        return;
    }
    if (count($_SESSION['tabs']) <= SESION_MAX_PESTANAS) {
        return;
    }

    $actual = getTabId();

    // Se ordena por último uso; las que nunca se marcaron van primero.
    uasort($_SESSION['tabs'], static fn($a, $b) => ($a['_visto'] ?? 0) <=> ($b['_visto'] ?? 0));

    while (count($_SESSION['tabs']) > SESION_MAX_PESTANAS) {
        $masAntigua = array_key_first($_SESSION['tabs']);
        if ($masAntigua === $actual) {
            // Nunca se descarta la pestaña que está pidiendo ahora mismo.
            break;
        }
        unset($_SESSION['tabs'][$masAntigua]);
    }
}

aplicarCaducidadSesion();

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
    $tabId = getTabId();
    if (isset($_SESSION['tabs'][$tabId])) {
        // Marca de uso, para que recolectarPestanas() sepa cuáles descartar.
        $_SESSION['tabs'][$tabId]['_visto'] = time();
    }
    return $_SESSION['tabs'][$tabId] ?? [];
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
        $stmt = $db->prepare("SELECT rol, estado, debe_cambiar_password FROM usuarios WHERE id = ? LIMIT 1");
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

        $_SESSION['tabs'][getTabId()]['debe_cambiar_password'] = (bool)$row['debe_cambiar_password'];

    } catch (Throwable $e) {
        // En fallo de BD se continúa con los datos de sesión existentes
    }
}

/**
 * Si la cuenta tiene una contraseña temporal pendiente de cambio, obliga a
 * pasar por "Mi Perfil" antes de usar el resto del sistema.
 */
function requirePasswordChangeIfPending(): void {
    if (empty(tabData()['debe_cambiar_password'])) {
        return;
    }

    $isLogout = isset($_GET['action']) && $_GET['action'] === 'logout';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($isLogout || strpos($uri, '/perfil') !== false) {
        return;
    }

    // Detectar si es una petición JSON/AJAX o hacia la API
    $isJson = false;
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    
    if (strpos($acceptHeader, 'application/json') !== false || 
        strtolower($requestedWith) === 'xmlhttprequest' || 
        strpos($uri, '/api/') !== false) {
        $isJson = true;
    }

    if ($isJson) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Debe cambiar su contraseña para continuar.']);
        exit;
    }

    setFlashMessage('Debes cambiar tu contraseña temporal antes de continuar.', 'warning');
    header('Location: ' . APP_URL . '/index.php/perfil');
    exit;
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
    requirePasswordChangeIfPending();
}

/**
 * Requiere que el usuario tenga uno de los roles indicados.
 * Si el rol no coincide, redirige al dashboard propio del usuario.
 */
function requireRole(string ...$roles): void {
    requireAuth();
    if (!in_array(getCurrentRole(), $roles, true)) {
        // Un usuario pidiendo una pantalla que no le corresponde es la
        // señal que delata a quien prueba rutas a mano. Antes se redirigía
        // en silencio y no quedaba constancia en ningún sitio.
        $u = getCurrentUser();
        (new Core\Services\Auditoria())->permisoDenegado(
            $u !== null ? (int)$u['id'] : null,
            parse_url($_SERVER['REQUEST_URI'] ?? '-', PHP_URL_PATH) ?: '-',
            'Rol actual: ' . getCurrentRole() . '; requeridos: ' . implode(', ', $roles)
        );
        header('Location: ' . APP_URL . '/index.php/dashboard');
        exit;
    }
}

/**
 * Comprueba sin redirigir si el usuario tiene uno de los roles.
 */
function hasRole(string ...$roles): bool {
    return in_array(getCurrentRole(), $roles, true);
}

/**
 * Deniega el acceso al recurso solicitado (ej. el usuario no es dueño
 * del registro) y redirige a su propio dashboard con un mensaje flash.
 */
function denyAccess(string $mensaje = 'No tienes permiso para acceder a este recurso.'): void {
    $u = getCurrentUser();
    (new Core\Services\Auditoria())->permisoDenegado(
        $u !== null ? (int)$u['id'] : null,
        parse_url($_SERVER['REQUEST_URI'] ?? '-', PHP_URL_PATH) ?: '-'
    );
    setFlashMessage($mensaje, 'danger');
    header('Location: ' . APP_URL . '/index.php/dashboard');
    exit;
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

// ---------------------------------------------------------------------------
// PROTECCIÓN CSRF (CROSS-SITE REQUEST FORGERY) CON SOPORTE DE PESTAÑAS
// ---------------------------------------------------------------------------

/**
 * Obtiene o genera el token CSRF de la sesión.
 *
 * El token es de sesión, no de pestaña. Antes había uno por pestaña, y eso
 * obligó a un parche que, al validar, recorría TODAS las pestañas y daba
 * por bueno cualquier token que coincidiera con alguna: el aislamiento que
 * justificaba tener un token por pestaña quedaba anulado de todos modos.
 *
 * El aislamiento por pestaña resuelve otro problema (dos roles abiertos a
 * la vez) y no aporta nada frente a CSRF: para eso basta con que el token
 * sea secreto y esté ligado a la sesión, que es lo que un atacante externo
 * no puede leer. Con un solo token desaparecen el parche y el caso de
 * borde que lo motivaba.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna el campo input oculto HTML con el token CSRF para formularios.
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida el token CSRF recibido contra el de la sesión, en tiempo constante.
 */
function validateCsrfToken(?string $token): bool {
    if ($token === null || $token === '') {
        return false;
    }
    $esperado = $_SESSION['csrf_token'] ?? '';
    if ($esperado === '') {
        return false;
    }
    return hash_equals($esperado, $token);
}

/**
 * Middleware para validar el token CSRF en peticiones de tipo POST.
 * Termina la ejecución con 403 Forbidden si el token no es válido.
 */
function requireCsrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_array($token)) {
        $token = '';
    }

    if (validateCsrfToken((string)$token)) {
        return;
    }

    // Esta respuesta llegó a imprimir el ID de sesión y los tokens CSRF
    // esperados de todas las pestañas, como "Diagnostic Info". Era código
    // de depuración en el camino de producción: cualquiera que provocara
    // un 403 se llevaba de vuelta los secretos con los que falsificar la
    // siguiente petición. El detalle va al log; al usuario, lo justo.
    error_log(sprintf(
        'CSRF rechazado | %s %s | sesión=%s | token_presente=%s',
        $_SERVER['REQUEST_METHOD'] ?? '-',
        parse_url($_SERVER['REQUEST_URI'] ?? '-', PHP_URL_PATH) ?: '-',
        session_id() !== '' ? 'sí' : 'no',
        $token !== '' ? 'sí' : 'no'
    ));

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
       . '<title>Sesión expirada</title></head><body style="font-family:system-ui,sans-serif;'
       . 'max-width:520px;margin:12vh auto;text-align:center;color:#1f2933">'
       . '<h1 style="font-size:20px;color:#00324D">No se pudo procesar el formulario</h1>'
       . '<p style="color:#52606d;line-height:1.6">Es probable que la sesión haya expirado o que '
       . 'el formulario llevara demasiado tiempo abierto. Vuelve a iniciar sesión e inténtalo de nuevo.</p>'
       . '<a href="' . htmlspecialchars(APP_URL . '/login.php', ENT_QUOTES, 'UTF-8') . '" '
       . 'style="display:inline-block;margin-top:16px;background:#39A900;color:#fff;'
       . 'text-decoration:none;padding:10px 24px;border-radius:8px">Ir al inicio de sesión</a>'
       . '</body></html>';
    exit;
}

// ---------------------------------------------------------------------------
// MENSAJES FLASH (POST-REDIRECT-GET)
// ---------------------------------------------------------------------------

/**
 * Añade un mensaje flash a la sesión actual (se usará tras un redirect).
 * 
 * @param string $mensaje El texto del mensaje.
 * @param string $tipo_mensaje 'success', 'danger', 'warning', 'info' etc.
 */
function setFlashMessage(string $mensaje, string $tipo_mensaje = 'success'): void {
    $tabId = getTabId();
    if (!isset($_SESSION['tabs'][$tabId]['flash'])) {
        $_SESSION['tabs'][$tabId]['flash'] = [];
    }
    $_SESSION['tabs'][$tabId]['flash'][] = [
        'mensaje' => $mensaje,
        'tipo' => $tipo_mensaje
    ];
}

/**
 * Obtiene los mensajes flash pendientes y los limpia de la sesión.
 * 
 * @return array Lista de mensajes con 'mensaje' y 'tipo'
 */
function getFlashMessages(): array {
    $tabId = getTabId();
    $messages = $_SESSION['tabs'][$tabId]['flash'] ?? [];
    unset($_SESSION['tabs'][$tabId]['flash']);
    return $messages;
}

// Descarta slots de pestaña antiguos antes de que la sesión crezca sin tope.
recolectarPestanas();

// Validación CSRF global para toda petición POST. Es el único punto donde
// se exige: Router::dispatch() y los controladores la llamaban además por
// su cuenta, pero basta con que ocurra una vez y aquí cubre también los
// puntos de entrada que no pasan por el router (login, recover, la API de
// notificaciones).
requireCsrf();

