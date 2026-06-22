<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

class FichaModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Get all Fichas with program name
     */
    public function getAll(): array {
        return $this->db->query("
            SELECT f.id, f.numero_ficha, p.nombre as programa 
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id 
            ORDER BY f.numero_ficha
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Fichas assigned to an instructor (as leader or through assignments or tracking)
     */
    public function getByInstructor(int $instructorId): array {
        $stmt = $this->db->prepare("
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

    /**
     * Obtiene el ID de la ficha de un aprendiz a partir de su ID de usuario.
     */
    public function getFichaIdByUsuarioId(int $usuarioId): ?int {
        $stmt = $this->db->prepare("SELECT ficha_id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    /**
     * Obtiene la lista detallada de fichas con programas, instructores y proyectos vinculados.
     */
    public function getDetailedList(?int $instructorId = null): array {
        $sql = "
            SELECT 
                f.id, 
                f.numero_ficha, 
                f.estado, 
                f.cantidad_aprendices, 
                f.fecha_fin,
                f.cumplimiento_porcentaje,
                p.nombre as programa,
                p.codigo as codigo_programa,
                u.nombre as instructor,
                u.id as instructor_id,
                pr.nombre as proyecto_nombre,
                pr.codigo as proyecto_codigo
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            JOIN usuarios u ON f.instructor_id = u.id
            LEFT JOIN proyectos pr ON f.proyecto_id = pr.id
        ";
        
        $params = [];
        if ($instructorId !== null) {
            $sql .= " WHERE f.instructor_id = ?";
            $params[] = $instructorId;
        }
        
        $sql .= " ORDER BY f.fecha_fin DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina una ficha por su ID.
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM fichas WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
