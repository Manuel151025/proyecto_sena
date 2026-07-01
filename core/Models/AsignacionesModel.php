<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class AsignacionesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function checkAsignacionExiste(int $ficha_id, int $competencia_id): bool {
        $stmt = $this->db->prepare("SELECT id FROM asignaciones WHERE ficha_id = ? AND competencia_id = ?");
        $stmt->execute([$ficha_id, $competencia_id]);
        return (bool)$stmt->fetch();
    }

    public function crearAsignacion(int $ficha_id, int $competencia_id, int $instructor_id, int $user_id): void {
        $stmt = $this->db->prepare("
            INSERT INTO asignaciones (ficha_id, competencia_id, instructor_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ficha_id, $competencia_id, $instructor_id]);
        $asignacion_id = (int)$this->db->lastInsertId();

        $logStmt = $this->db->prepare("
            INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
            VALUES (?, 'Crear', 'Asignaciones', 'asignaciones', ?, ?)
        ");
        $logStmt->execute([$user_id, $asignacion_id, "Asignó al instructor id $instructor_id a la competencia id $competencia_id en la ficha id $ficha_id"]);
    }

    public function eliminarAsignacion(int $asignacion_id, int $user_id): void {
        $this->db->prepare("DELETE FROM asignaciones WHERE id = ?")->execute([$asignacion_id]);

        $logStmt = $this->db->prepare("
            INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
            VALUES (?, 'Eliminar', 'Asignaciones', 'asignaciones', ?, ?)
        ");
        $logStmt->execute([$user_id, $asignacion_id, "Eliminó la asignación id $asignacion_id"]);
    }

    public function getAsignaciones(string $search, int $filter_ficha, int $filter_instructor): array {
        $sql = "
            SELECT a.id, a.fecha_asignacion, f.numero_ficha, p.nombre as programa_nombre, 
                   c.codigo as competencia_codigo, c.nombre as competencia_nombre, 
                   u.nombre as instructor_nombre, u.email as instructor_email, u.avatar_color
            FROM asignaciones a
            JOIN fichas f ON a.ficha_id = f.id
            JOIN programas p ON f.programa_id = p.id
            JOIN competencias c ON a.competencia_id = c.id
            JOIN usuarios u ON a.instructor_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (u.nombre LIKE ? OR c.nombre LIKE ? OR c.codigo LIKE ? OR f.numero_ficha LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($filter_ficha > 0) {
            $sql .= " AND a.ficha_id = ?";
            $params[] = $filter_ficha;
        }
        if ($filter_instructor > 0) {
            $sql .= " AND a.instructor_id = ?";
            $params[] = $filter_instructor;
        }

        $sql .= " ORDER BY f.numero_ficha, c.codigo";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFichas(): array {
        return $this->db->query("
            SELECT f.id, f.numero_ficha, p.codigo as programa_codigo 
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            ORDER BY f.numero_ficha
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCompetencias(): array {
        return $this->db->query("
            SELECT c.id, c.codigo, c.nombre, p.codigo as programa_codigo 
            FROM competencias c
            JOIN programas p ON c.programa_id = p.id
            WHERE c.estado = 'activo'
            ORDER BY p.codigo, c.codigo
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInstructores(): array {
        return $this->db->query("
            SELECT id, nombre, email 
            FROM usuarios 
            WHERE rol = 'instructor' AND estado = 'activo' 
            ORDER BY nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
