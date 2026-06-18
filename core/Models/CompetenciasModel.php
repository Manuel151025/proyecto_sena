<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class CompetenciasModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todas las competencias.
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, p.nombre as programa_nombre, p.codigo as programa_codigo
                FROM competencias c
                JOIN programas p ON c.programa_id = p.id
                ORDER BY p.nombre, c.codigo
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener todas las competencias: " . $e->getMessage());
        }
    }

    /**
     * Obtiene competencias con filtros aplicados.
     */
    public function getFilteredList(array $filters = []): array {
        try {
            $sql = "
                SELECT c.*, p.nombre as programa_nombre, p.codigo as programa_codigo
                FROM competencias c
                JOIN programas p ON c.programa_id = p.id
                WHERE 1=1
            ";
            $params = [];

            if (!empty($filters['search'])) {
                $sql .= " AND (c.nombre LIKE ? OR c.codigo LIKE ?)";
                $params[] = "%{$filters['search']}%";
                $params[] = "%{$filters['search']}%";
            }
            if (!empty($filters['programa_id'])) {
                $sql .= " AND c.programa_id = ?";
                $params[] = (int)$filters['programa_id'];
            }
            if (!empty($filters['estado'])) {
                $sql .= " AND c.estado = ?";
                $params[] = $filters['estado'];
            }

            $sql .= " ORDER BY p.nombre, c.codigo";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al filtrar competencias: " . $e->getMessage());
        }
    }

    /**
     * Crea una nueva competencia.
     */
    public function create(array $data): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO competencias (programa_id, codigo, nombre, descripcion, horas, estado)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['programa_id'],
                $data['codigo'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['horas'],
                $data['estado'] ?? 'activo'
            ]);
        } catch (Exception $e) {
            throw new Exception("Error al registrar competencia: " . $e->getMessage());
        }
    }

    /**
     * Actualiza una competencia.
     */
    public function update(int $id, array $data): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE competencias
                SET programa_id = ?, codigo = ?, nombre = ?, descripcion = ?, horas = ?, estado = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['programa_id'],
                $data['codigo'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['horas'],
                $data['estado'],
                $id
            ]);
        } catch (Exception $e) {
            throw new Exception("Error al actualizar competencia: " . $e->getMessage());
        }
    }

    /**
     * Elimina una competencia.
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM competencias WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            throw new Exception("No se puede eliminar: la competencia tiene registros asociados o resultados de aprendizaje en uso.");
        }
    }
}
