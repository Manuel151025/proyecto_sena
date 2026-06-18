<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class ResultadosAprendizajeModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene competencias activas y sus resultados de aprendizaje asociados.
     */
    public function getCompetenciasWithRaps(): array {
        try {
            $competencias = $this->db->query("
                SELECT c.id, c.nombre, c.codigo, p.nombre as programa 
                FROM competencias c
                JOIN programas p ON c.programa_id = p.id 
                WHERE c.estado = 'activo'
                ORDER BY p.nombre, c.codigo
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Cargar RAPs para cada competencia
            foreach ($competencias as &$comp) {
                $stmt = $this->db->prepare("
                    SELECT id, codigo, denominacion 
                    FROM resultados_aprendizaje 
                    WHERE competencia_id = ? 
                    ORDER BY codigo
                ");
                $stmt->execute([$comp['id']]);
                $comp['raps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($comp);
            
            return $competencias;
        } catch (Exception $e) {
            throw new Exception("Error al obtener competencias con RAPs: " . $e->getMessage());
        }
    }

    /**
     * Registra un nuevo RAP.
     */
    public function create(array $data, int $userId): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion)
                VALUES (?, ?, ?)
            ");
            $result = $stmt->execute([
                $data['competencia_id'],
                $data['codigo'],
                $data['denominacion']
            ]);

            if ($result) {
                $newId = (int)$this->db->lastInsertId();
                // Registrar log
                $logStmt = $this->db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Crear', 'RAPs', 'resultados_aprendizaje', ?, ?)
                ");
                $logStmt->execute([
                    $userId,
                    $newId,
                    "Creó el RAP {$data['codigo']} para competencia ID {$data['competencia_id']}"
                ]);
            }

            return $result;
        } catch (Exception $e) {
            throw new Exception("Error al registrar RAP: " . $e->getMessage());
        }
    }

    /**
     * Actualiza un RAP.
     */
    public function update(int $id, array $data, int $userId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE resultados_aprendizaje
                SET competencia_id = ?, codigo = ?, denominacion = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $data['competencia_id'],
                $data['codigo'],
                $data['denominacion'],
                $id
            ]);

            if ($result) {
                // Registrar log
                $logStmt = $this->db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Editar', 'RAPs', 'resultados_aprendizaje', ?, ?)
                ");
                $logStmt->execute([
                    $userId,
                    $id,
                    "Editó el RAP {$data['codigo']}"
                ]);
            }

            return $result;
        } catch (Exception $e) {
            throw new Exception("Error al actualizar RAP: " . $e->getMessage());
        }
    }

    /**
     * Elimina un RAP.
     */
    public function delete(int $id, int $userId): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM resultados_aprendizaje WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                // Registrar log
                $logStmt = $this->db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Eliminar', 'RAPs', 'resultados_aprendizaje', ?, 'Eliminó el RAP')
                ");
                $logStmt->execute([$userId, $id]);
            }

            return $result;
        } catch (Exception $e) {
            throw new Exception("No se puede eliminar: el RAP tiene evaluaciones asociadas u otros registros vinculados.");
        }
    }
}
