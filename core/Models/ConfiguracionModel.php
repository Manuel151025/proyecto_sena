<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Support\ErrorDeNegocio;
use Core\Database;
use PDO;
use Exception;

class ConfiguracionModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function save(string $clave, string $valor): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO configuraciones_sistema (clave, valor) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)
            ");
            return $stmt->execute([$clave, $valor]);
        } catch (Exception $e) {
            throw new ErrorDeNegocio("Error al guardar configuración: " . $e->getMessage());
        }
    }

    /**
     * Estos `catch` devolvían un array vacío en silencio. Como la tabla no
     * existía, el módulo aparentaba funcionar: mostraba los valores por
     * defecto del controlador y descartaba lo guardado sin avisar a nadie.
     * Ahora el fallo se registra; se sigue devolviendo el respaldo para no
     * tumbar la pantalla, pero deja rastro en el log.
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("SELECT clave, valor FROM configuraciones_sistema");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return $result ?: [];
        } catch (Exception $e) {
            error_log('ConfiguracionModel::getAll — no se pudo leer la configuración: ' . $e->getMessage());
            return [];
        }
    }

    public function get(string $clave, string $default = ''): string {
        try {
            $stmt = $this->db->prepare("SELECT valor FROM configuraciones_sistema WHERE clave = ?");
            $stmt->execute([$clave]);
            $result = $stmt->fetchColumn();
            return $result !== false ? (string)$result : $default;
        } catch (Exception $e) {
            error_log("ConfiguracionModel::get('$clave') — no se pudo leer: " . $e->getMessage());
            return $default;
        }
    }
}
