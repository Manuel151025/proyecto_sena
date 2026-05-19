<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class UsuarioModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene todos los usuarios ordenados por fecha de creación descendente.
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("SELECT id, nombre, email, rol, estado, fecha_creacion FROM usuarios ORDER BY fecha_creacion DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Se puede registrar el error en un log si se desea
            throw new Exception("Error al cargar usuarios: " . $e->getMessage());
        }
    }

    /**
     * Elimina un usuario por su ID.
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            throw new Exception("Error al eliminar usuario: " . $e->getMessage());
        }
    }
}
