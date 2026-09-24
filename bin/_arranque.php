<?php
declare(strict_types=1);

/**
 * Arranque común de los comandos de consola de bin/.
 *
 * Carga la configuración y el autoloader sin sesión ni cabeceras HTTP, que
 * en consola no tienen sentido.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);

require_once $raiz . '/includes/env_loader.php';
require_once $raiz . '/config/app.php';
require_once $raiz . '/config/database.php';
require_once $raiz . '/vendor/autoload.php';
require_once $raiz . '/includes/functions.php';

/** Escribe una línea en la salida estándar. */
function linea(string $texto = ''): void {
    fwrite(STDOUT, $texto . PHP_EOL);
}

/** Escribe en la salida de error y termina con código 1. */
function abortar(string $texto): never {
    fwrite(STDERR, $texto . PHP_EOL);
    exit(1);
}

/** ¿Se pasó esta opción en la línea de comandos? */
function opcion(string $nombre): bool {
    global $argv;
    return in_array($nombre, $argv ?? [], true);
}

/**
 * Conexión para los comandos. No usa Core\Database porque esa clase, ante
 * un fallo, responde con un 503 pensado para el navegador.
 */
function conexion(bool $conBase = true): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ($conBase ? ';dbname=' . DB_NAME : '') . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        abortar('No se pudo conectar a MySQL/MariaDB en ' . DB_HOST . ': ' . $e->getMessage());
    }
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}
