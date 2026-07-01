<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class ActividadesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAprendizFichaId(int $userId): int {
        try {
            $stmt = $this->db->prepare("SELECT ficha_id FROM aprendices WHERE usuario_id = ?");
            $stmt->execute([$userId]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function create(array $data, int $userId): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO actividades (ficha_id, competencia_id, nombre, descripcion, fecha_inicio, fecha_fin, responsable_id, estado, cumplimiento_porcentaje)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00)
            ");
            $stmt->execute([
                $data['ficha_id'], $data['competencia_id'], $data['nombre'],
                $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                $data['responsable_id'], $data['estado']
            ]);

            $newId = (int)$this->db->lastInsertId();
            $stmtLog = $this->db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Crear', 'Actividades', 'actividades', ?, ?)
            ");
            $stmtLog->execute([$userId, $newId, "Creó la actividad {$data['nombre']} para ficha id {$data['ficha_id']}"]);
        } catch (Exception $e) {
            throw new Exception("Error al registrar actividad: " . $e->getMessage());
        }
    }

    public function update(int $id, array $data, int $userId): void {
        try {
            $stmt = $this->db->prepare("
                UPDATE actividades
                SET ficha_id=?, competencia_id=?, nombre=?, descripcion=?,
                    fecha_inicio=?, fecha_fin=?, responsable_id=?, estado=?,
                    cumplimiento_porcentaje=?
                WHERE id=?
            ");
            $stmt->execute([
                $data['ficha_id'], $data['competencia_id'], $data['nombre'],
                $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'],
                $data['responsable_id'], $data['estado'], $data['cumplimiento_porcentaje'], $id
            ]);

            $stmtLog = $this->db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Editar', 'Actividades', 'actividades', ?, ?)
            ");
            $stmtLog->execute([$userId, $id, "Editó la actividad {$data['nombre']}"]);
        } catch (Exception $e) {
            throw new Exception("Error al actualizar actividad: " . $e->getMessage());
        }
    }

    public function delete(int $id, int $userId): void {
        try {
            $this->db->prepare("DELETE FROM actividades WHERE id = ?")->execute([$id]);

            $stmtLog = $this->db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Eliminar', 'Actividades', 'actividades', ?, 'Eliminó la actividad')
            ");
            $stmtLog->execute([$userId, $id]);
        } catch (Exception $e) {
            throw new Exception("No se puede eliminar: la actividad tiene evidencias asociadas.");
        }
    }

    public function getFichas(string $userRol, int $userId): array {
        try {
            if ($userRol === ROL_INSTRUCTOR) {
                $stmt = $this->db->prepare("SELECT id, numero_ficha, programa_id FROM fichas WHERE instructor_id = ? ORDER BY numero_ficha");
                $stmt->execute([$userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return $this->db->query("SELECT id, numero_ficha, programa_id FROM fichas ORDER BY numero_ficha")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getCompetencias(): array {
        try {
            return $this->db->query("SELECT id, codigo, nombre, programa_id FROM competencias WHERE estado = 'activo' ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getInstructores(): array {
        try {
            return $this->db->query("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getActividadesList(string $userRol, int $userId, int $aprendizFichaId, array $filters): array {
        $sql = "
            SELECT act.*, f.numero_ficha, comp.codigo as comp_codigo, comp.nombre as comp_nombre, u.nombre as responsable_nombre
            FROM actividades act
            JOIN fichas f ON act.ficha_id = f.id
            LEFT JOIN competencias comp ON act.competencia_id = comp.id
            LEFT JOIN usuarios u ON act.responsable_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($userRol === ROL_APRENDIZ) {
            $sql .= " AND act.ficha_id = ?";
            $params[] = $aprendizFichaId;
        } elseif ($userRol === ROL_INSTRUCTOR) {
            $sql .= " AND f.instructor_id = ?";
            $params[] = $userId;
            if ($filters['ficha_id'] > 0) {
                $sql .= " AND act.ficha_id = ?";
                $params[] = $filters['ficha_id'];
            }
        } else {
            if ($filters['ficha_id'] > 0) {
                $sql .= " AND act.ficha_id = ?";
                $params[] = $filters['ficha_id'];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (act.nombre LIKE ? OR act.descripcion LIKE ?)";
            $params[] = "%" . $filters['search'] . "%";
            $params[] = "%" . $filters['search'] . "%";
        }

        if (!empty($filters['estado'])) {
            $sql .= " AND act.estado = ?";
            $params[] = $filters['estado'];
        }

        $sql .= " ORDER BY act.fecha_fin ASC, act.id DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
