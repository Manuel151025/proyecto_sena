<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class PerfilModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getPerfil(int $user_id): ?array {
        $stmt = $this->db->prepare("SELECT nombre, email, rol, avatar_color, fecha_creacion FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    public function updateProfile(int $user_id, string $nombre, string $color): void {
        $stmt = $this->db->prepare("
            UPDATE usuarios
            SET nombre = ?, avatar_color = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $color, $user_id]);
    }

    public function verifyPassword(int $user_id, string $actual): bool {
        $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash_actual = $stmt->fetchColumn();
        if ($hash_actual && password_verify($actual, (string)$hash_actual)) {
            return true;
        }
        return false;
    }

    public function changePassword(int $user_id, string $nueva): void {
        $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            UPDATE usuarios
            SET password = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nuevo_hash, $user_id]);
    }
}
