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

    /**
     * Obtiene una ficha con información completa.
     */
    public function getFichaCompleta(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                f.id,
                f.numero_ficha,
                f.estado,
                f.cantidad_aprendices,
                f.fecha_inicio,
                f.fecha_fin,
                f.cumplimiento_porcentaje,
                p.nombre as programa,
                p.id as programa_id,
                u.nombre as instructor,
                u.id as instructor_id
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            JOIN usuarios u ON f.instructor_id = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res : null;
    }

    /**
     * Obtiene los aprendices matriculados en una ficha.
     */
    public function getAprendicesFicha(int $id): array {
        $stmt = $this->db->prepare("
            SELECT 
                a.id,
                a.numero_documento,
                a.tipo_documento,
                u.nombre,
                u.avatar_color,
                a.estado,
                u2.nombre as instructor_seguimiento_nombre
            FROM aprendices a
            JOIN usuarios u ON a.usuario_id = u.id
            LEFT JOIN usuarios u2 ON a.instructor_seguimiento_id = u2.id
            WHERE a.ficha_id = ?
            ORDER BY u.nombre
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFichaParaEditar(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT f.*, p.nombre as programa_nombre, u.nombre as instructor_nombre
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            JOIN usuarios u ON f.instructor_id = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res : null;
    }

    public function getProgramasActivos(): array {
        return $this->db->query("SELECT id, nombre FROM programas WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInstructoresActivos(): array {
        return $this->db->query("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProyectosActivos(): array {
        return $this->db->query("SELECT id, nombre, codigo FROM proyectos WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createFicha(string $numero_ficha, ?int $proyecto_id, int $programa_id, int $instructor_id, int $coordinador_id, string $estado, int $cantidad_aprendices, ?string $fecha_inicio, ?string $fecha_fin, float $cumplimiento_porcentaje): void {
        $stmt = $this->db->prepare("
            INSERT INTO fichas (numero_ficha, proyecto_id, programa_id, instructor_id, coordinador_id, estado, cantidad_aprendices, fecha_inicio, fecha_fin, cumplimiento_porcentaje)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $numero_ficha, $proyecto_id, $programa_id, $instructor_id, $coordinador_id,
            $estado, $cantidad_aprendices, $fecha_inicio, $fecha_fin, $cumplimiento_porcentaje
        ]);
    }

    public function updateFicha(int $id, string $numero_ficha, ?int $proyecto_id, int $programa_id, int $instructor_id, string $estado, int $cantidad_aprendices, ?string $fecha_inicio, ?string $fecha_fin, float $cumplimiento_porcentaje): void {
        $stmt = $this->db->prepare("
            UPDATE fichas
            SET numero_ficha = ?, proyecto_id = ?, programa_id = ?, instructor_id = ?,
                estado = ?, cantidad_aprendices = ?, fecha_inicio = ?, fecha_fin = ?,
                cumplimiento_porcentaje = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $numero_ficha, $proyecto_id, $programa_id, $instructor_id, $estado,
            $cantidad_aprendices, $fecha_inicio, $fecha_fin, $cumplimiento_porcentaje, $id
        ]);
    }
}
