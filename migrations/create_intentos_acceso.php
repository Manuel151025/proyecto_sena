<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Tabla de intentos para el limitador de fuerza bruta.
 *
 * Sustituye al contador que vivía en `$_SESSION['login_attempts']`, que
 * solo frenaba a quien conservara la cookie: descartándola entre peticiones
 * el contador volvía a cero. La recuperación de contraseña, además, no
 * tenía ningún límite.
 *
 * `clave` guarda un hash, nunca el correo ni la IP en claro: es un control
 * de abuso, no un registro de quién intentó entrar.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS intentos_acceso (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            accion         VARCHAR(40)  NOT NULL COMMENT 'login | recuperacion',
            clave          VARCHAR(80)  NOT NULL COMMENT 'hash de identidad (id:) o de IP (ip:)',
            intentos       INT          NOT NULL DEFAULT 0,
            primer_intento TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ultimo_intento TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unico_accion_clave (accion, clave),
            KEY idx_ultimo (ultimo_intento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "Tabla 'intentos_acceso' lista.\n";

    // Higiene: las filas viejas no sirven para nada y la tabla crece sola.
    $borradas = $db->exec("DELETE FROM intentos_acceso WHERE ultimo_intento < (NOW() - INTERVAL 7 DAY)");
    if ($borradas > 0) {
        echo "Purgadas $borradas filas de más de 7 días.\n";
    }

    $n = $db->query("SELECT COUNT(*) FROM intentos_acceso")->fetchColumn();
    echo "Filas actuales: $n\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
