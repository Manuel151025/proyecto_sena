<?php
/**
 * AUTH.PHP — Lógica de login/logout con soporte de sesiones por pestaña.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/**
 * Hash de relleno contra el que se verifica cuando el correo no existe,
 * para que las dos ramas de `attemptLogin` cuesten lo mismo.
 *
 * Tiene que ser un bcrypt VÁLIDO y del MISMO COSTE que los hashes reales.
 * El primer intento fue una cadena inventada con pinta de bcrypt, y salió
 * peor el remedio: `password_verify` contra un hash malformado tarda unos
 * 200 ms, frente a los 50 ms de uno de coste 10, así que la rama «esta
 * cuenta no existe» pasó a ser CUATRO VECES MÁS LENTA que la otra. La fuga
 * seguía ahí, solo que invertida.
 *
 * Es el hash de una cadena aleatoria que no se guardó en ningún sitio: no
 * existe contraseña que lo satisfaga.
 *
 * Si alguna vez sube el coste por defecto de PHP, este valor se queda
 * corto y hay que regenerarlo. Lo avisa
 * `Tests\Security\AutenticacionTest::testCosteDelRellenoCoincide`.
 */
const HASH_RELLENO = '$2y$10$MB5vKj1Pt1rz39C70jGHaevBL8pmIxdDaLsOGrmtfj0zhfH/JHPWm';

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
        // La contraseña se trae en la misma consulta: antes hacían falta dos
        // viajes a la base para el mismo usuario.
        $stmt = $db->prepare(
            "SELECT id, nombre, email, rol, avatar_color, estado, password
             FROM usuarios
             WHERE email = ? AND estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el usuario no existe se verifica igualmente contra un hash de
        // relleno. Sin esto, un correo inexistente respondía de inmediato y
        // uno real tardaba lo que tarda bcrypt: esa diferencia de tiempo
        // permite enumerar qué cuentas existen.
        $hash = $user['password'] ?? HASH_RELLENO;

        if (!password_verify($password, $hash) || !$user) {
            return false;
        }

        // Si el coste de hash configurado ha subido desde que se registró la
        // contraseña, se rehashea ahora que la tenemos en claro.
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $nuevo = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
               ->execute([$nuevo, (int)$user['id']]);
        }

        // Resolver tabId de la pestaña que hace login
        $tabId = $_POST['_tab'] ?? ($_COOKIE['sena_tab'] ?? '');
        if (!preg_match('/^[a-z0-9]{8,24}$/', $tabId)) {
            $tabId = 'default';
        }

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        (new Core\Services\Auditoria($db))->accesoCorrecto((int)$user['id'], (string)$user['rol']);

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
 * Cerrar sesión de la pestaña actual.
 * Si era la última pestaña activa, destruye la sesión completa.
 */
function logout(): void {
    $usuario = getCurrentUser();
    if ($usuario !== null) {
        (new Core\Services\Auditoria())->cierreSesion((int)$usuario['id']);
    }

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
