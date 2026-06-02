<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

class FichaModel {
    /**
     * Get all Fichas with program name
     */
    public static function getAll(): array {
        $db = Database::getConnection();
        return $db->query("
            SELECT f.id, f.numero_ficha, p.nombre as programa 
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id 
            ORDER BY f.numero_ficha
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Fichas assigned to an instructor (as leader or through assignments or tracking)
     */
    public static function getByInstructor(int $instructorId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT DISTINCT f.id, f.numero_ficha, p.nombre as programa 
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id 
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
            LEFT JOIN aprendices ap ON ap.ficha_id = f.id
            WHERE f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?
            ORDER BY f.numero_ficha
        ");
        $stmt->execute([$instructorId, $instructorId, $instructorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
