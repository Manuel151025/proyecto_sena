<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class ProyectosModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function crearProyecto(string $nombre, string $codigo, string $objetivo, string $descripcion): void {
        $stmt = $this->db->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, descripcion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $codigo, $objetivo, $descripcion]);
    }

    public function eliminarProyecto(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM proyectos WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function editarProyecto(int $id, string $nombre, string $codigo, string $objetivo, string $descripcion, string $estado): void {
        $stmt = $this->db->prepare("
            UPDATE proyectos SET nombre=?, codigo=?, objetivo=?, descripcion=?, estado=?
            WHERE id=?
        ");
        $stmt->execute([$nombre, $codigo, $objetivo, $descripcion, $estado, $id]);
    }

    public function getProyectos(int $user_rol, int $user_id): array {
        if ($user_rol === ROL_APRENDIZ) {
            $stmt = $this->db->prepare("
                SELECT 
                    pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.estado,
                    COUNT(DISTINCT f.id) as total_fichas,
                    SUM(f.cantidad_aprendices) as total_aprendices,
                    COUNT(DISTINCT fp.id) as total_fases,
                    SUM(CASE WHEN fp.estado = 'completada' THEN 1 ELSE 0 END) as fases_completadas,
                    AVG(fp.cumplimiento_porcentaje) as avance_promedio
                FROM proyectos pr
                JOIN fichas f ON f.proyecto_id = pr.id
                JOIN aprendices ap ON ap.ficha_id = f.id
                LEFT JOIN fases_proyecto fp ON fp.proyecto_id = pr.id
                WHERE ap.usuario_id = ?
                GROUP BY pr.id
                ORDER BY pr.nombre
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $this->db->prepare("
                SELECT 
                    pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.estado,
                    COUNT(DISTINCT f.id) as total_fichas,
                    SUM(f.cantidad_aprendices) as total_aprendices,
                    COUNT(DISTINCT fp.id) as total_fases,
                    SUM(CASE WHEN fp.estado = 'completada' THEN 1 ELSE 0 END) as fases_completadas,
                    AVG(fp.cumplimiento_porcentaje) as avance_promedio
                FROM proyectos pr
                JOIN fichas f ON f.proyecto_id = pr.id
                LEFT JOIN fases_proyecto fp ON fp.proyecto_id = pr.id
                WHERE f.instructor_id = ?
                GROUP BY pr.id
                ORDER BY pr.nombre
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $this->db->query("
                SELECT 
                    pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.estado,
                    COUNT(DISTINCT f.id) as total_fichas,
                    SUM(f.cantidad_aprendices) as total_aprendices,
                    COUNT(DISTINCT fp.id) as total_fases,
                    SUM(CASE WHEN fp.estado = 'completada' THEN 1 ELSE 0 END) as fases_completadas,
                    AVG(fp.cumplimiento_porcentaje) as avance_promedio
                FROM proyectos pr
                LEFT JOIN fichas f ON f.proyecto_id = pr.id
                LEFT JOIN fases_proyecto fp ON fp.proyecto_id = pr.id
                GROUP BY pr.id
                ORDER BY pr.nombre
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getAll(): array {
        return $this->getProyectos(ROL_COORDINADOR, 0);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM proyectos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function delete(int $id): void {
        $this->eliminarProyecto($id);
    }

    public function update(int $id, array $data): void {
        $this->editarProyecto(
            $id,
            $data['nombre'] ?? '',
            $data['codigo'] ?? '',
            $data['objetivo'] ?? '',
            $data['descripcion'] ?? '',
            $data['estado'] ?? 'activo'
        );
    }
}
