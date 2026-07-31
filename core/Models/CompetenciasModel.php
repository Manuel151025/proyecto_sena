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
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("Ya existe una competencia con el código '{$data['codigo']}' en este programa.");
            }
            throw new Exception("Error al registrar competencia: " . $e->getMessage());
        }
    }

    /**
     * Inserta una competencia solo si no existe todavía. Devuelve true si la
     * fila se creó y false si se omitió porque ya estaba registrada.
     *
     * Pensada para la importación masiva, que debe ser idempotente: volver a
     * subir el mismo archivo no debe fallar ni duplicar. La creación manual
     * sigue usando create(), que necesita lanzar la excepción de duplicado
     * para poder avisar al coordinador.
     *
     * La identidad de una competencia es (programa_id, codigo), no el código
     * suelto: hay competencias transversales del SENA (Inglés, Ética, Etapa
     * Práctica…) que comparten el mismo código oficial en varios programas.
     * Por eso el índice del esquema es UNIQUE (programa_id, codigo).
     *
     * Se combinan dos mecanismos a propósito:
     *  - El SELECT previo detecta las que ya existían y funciona incluso si
     *    el índice UNIQUE compuesto no se ha aplicado en ese entorno
     *    (migrations/add_unique_codigos.php lo omite si encuentra duplicados).
     *  - INSERT IGNORE cubre las repetidas dentro del propio archivo y las
     *    carreras entre importaciones simultáneas, sin abortar la transacción.
     */
    public function createIfNotExists(array $data): bool {
        try {
            $check = $this->db->prepare("
                SELECT id FROM competencias WHERE programa_id = ? AND codigo = ? LIMIT 1
            ");
            $check->execute([$data['programa_id'], $data['codigo']]);
            if ($check->fetch()) {
                return false;
            }

            $stmt = $this->db->prepare("
                INSERT IGNORE INTO competencias (programa_id, codigo, nombre, descripcion, horas, estado)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['programa_id'],
                $data['codigo'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['horas'],
                $data['estado'] ?? 'activo'
            ]);

            // rowCount() === 1 -> insertada; 0 -> la ignoró por duplicado.
            return $stmt->rowCount() === 1;
        } catch (Exception $e) {
            throw new Exception("Error al registrar competencia '{$data['codigo']}': " . $e->getMessage());
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
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("Ya existe otra competencia con el código '{$data['codigo']}' en este programa.");
            }
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
