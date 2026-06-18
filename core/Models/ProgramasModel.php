<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class ProgramasModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todos los programas cargados con el conteo de sus competencias.
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id, p.nombre, p.codigo, p.descripcion, p.duracion_horas, p.estado, p.fecha_creacion,
                       COUNT(c.id) as total_competencias
                FROM programas p
                LEFT JOIN competencias c ON c.programa_id = p.id
                GROUP BY p.id, p.nombre, p.codigo, p.descripcion, p.duracion_horas, p.estado, p.fecha_creacion
                ORDER BY p.nombre ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener programas: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un programa específico por su ID.
     */
    public function findById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM programas WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $prog = $stmt->fetch(PDO::FETCH_ASSOC);
            return $prog ?: null;
        } catch (Exception $e) {
            throw new Exception("Error al buscar programa: " . $e->getMessage());
        }
    }

    /**
     * Registra un nuevo programa.
     */
    public function create(array $data): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO programas (nombre, codigo, descripcion, duracion_horas, estado)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['nombre'],
                $data['codigo'],
                $data['descripcion'] ?? null,
                $data['duracion_horas'],
                $data['estado'] ?? 'activo'
            ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("El código de programa ya existe.");
            }
            throw new Exception("Error al registrar el programa: " . $e->getMessage());
        }
    }

    /**
     * Actualiza un programa existente.
     */
    public function update(int $id, array $data): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE programas
                SET nombre = ?, codigo = ?, descripcion = ?, duracion_horas = ?, estado = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['nombre'],
                $data['codigo'],
                $data['descripcion'] ?? null,
                $data['duracion_horas'],
                $data['estado'],
                $id
            ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("El código ingresado ya está registrado para otro programa.");
            }
            throw new Exception("Error al actualizar el programa: " . $e->getMessage());
        }
    }

    /**
     * Elimina un programa.
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM programas WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new Exception("No se puede eliminar el programa porque tiene fichas de formación asociadas u otros registros vinculados.");
            }
            throw new Exception("Error de base de datos al eliminar el programa: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error al eliminar el programa: " . $e->getMessage());
        }
    }
}
