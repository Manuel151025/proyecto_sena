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
            } catch (PDOException $e) {
                // Se detiene la ejecución para evitar exponer vulnerabilidades o rutas
                die("Error crítico: No se pudo conectar a la base de datos.");
            }
        }
        return self::$instance;
    }
}
