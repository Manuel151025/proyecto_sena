<?php
/**
 * Database.php — Wrapper de base de datos con PDO
 */

namespace Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Retorna la instancia de conexión PDO (Singleton)
     * 
     * @return PDO
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                // Si por alguna razón no se han definido las constantes de BD
                if (!defined('DB_HOST')) {
                    require_once __DIR__ . '/../config/database.php';
                }

                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Según corrección
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]);

                // Modo estricto: sin esto, MariaDB acepta en silencio lo que
                // no cabe. Comprobado en esta misma base: un enum con un
                // valor inventado se guardaba como cadena vacía y una fecha
                // ilegible como '0000-00-00', sin lanzar ningún error. Un
                // estado de ficha corrupto no lo detecta nadie hasta que una
                // pantalla no sabe qué pintar.
                //
                // Va aquí y no en my.cnf para que la garantía viaje con el
                // proyecto y no dependa del servidor donde se despliegue.
                self::$instance->exec(
                    "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,"
                    . "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
                );
                // La misma hora que PHP para NOW() y CURDATE(): con la zona
                // del servidor de base de datos (UTC en Docker) un plan
                // vencía a las 7 de la noche. Se usa el desfase y no el
                // nombre porque las tablas de zonas de MySQL no suelen estar
                // cargadas.
                self::$instance->exec("SET time_zone = '" . date('P') . "'");
            } catch (PDOException $e) {
                // El mensaje de PDO lleva host, usuario y nombre de base de
                // datos: se registra, pero no se enseña.
                error_log('Fallo de conexión a la base de datos: ' . $e->getMessage());
                http_response_code(503);
                die("Error crítico: No se pudo conectar a la base de datos.");
            }
        }
        return self::$instance;
    }
}
