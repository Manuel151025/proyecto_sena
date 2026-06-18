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

    /**
     * Obtiene todos los proyectos formativos con el conteo de sus fases.
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.descripcion, pr.estado, pr.fecha_creacion,
                       COUNT(f.id) as total_fases
                FROM proyectos pr
                LEFT JOIN fases_proyecto f ON f.proyecto_id = pr.id
                GROUP BY pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.descripcion, pr.estado, pr.fecha_creacion
                ORDER BY pr.nombre ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener todos los proyectos: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un proyecto formativo específico por su ID.
     */
    public function findById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM proyectos WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $proj = $stmt->fetch(PDO::FETCH_ASSOC);
            return $proj ?: null;
        } catch (Exception $e) {
            throw new Exception("Error al buscar el proyecto: " . $e->getMessage());
        }
    }

    /**
     * Registra un nuevo proyecto formativo.
     */
    public function create(array $data): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO proyectos (nombre, codigo, objetivo, descripcion, estado)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['nombre'],
                $data['codigo'],
                $data['objetivo'] ?? null,
                $data['descripcion'] ?? null,
                $data['estado'] ?? 'activo'
            ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("El código de proyecto ya existe.");
            }
            throw new Exception("Error al registrar el proyecto: " . $e->getMessage());
        }
    }

    /**
     * Actualiza un proyecto formativo existente.
     */
    public function update(int $id, array $data): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE proyectos
                SET nombre = ?, codigo = ?, objetivo = ?, descripcion = ?, estado = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['nombre'],
                $data['codigo'],
                $data['objetivo'] ?? null,
                $data['descripcion'] ?? null,
                $data['estado'],
                $id
            ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("El código ingresado ya está registrado para otro proyecto.");
            }
            throw new Exception("Error al actualizar el proyecto: " . $e->getMessage());
        }
    }

    /**
     * Elimina un proyecto formativo.
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM proyectos WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            throw new Exception("Error de base de datos al eliminar el proyecto: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error al eliminar el proyecto: " . $e->getMessage());
        }
    }
}
