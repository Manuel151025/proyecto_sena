<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Exception;
use PDO;

class AprendizModel {
    /**
     * Get learners with filters and search
     */
    public static function getFilteredList(array $filters = [], ?int $instructorId = null): array {
        $db = Database::getConnection();

        $sql = "
            SELECT a.*, u.nombre, u.email, u.avatar_color, f.numero_ficha, p.nombre as programa_nombre,
                   u2.nombre as instructor_seguimiento_nombre
            FROM aprendices a
            JOIN usuarios u ON a.usuario_id = u.id
            LEFT JOIN fichas f ON a.ficha_id = f.id
            LEFT JOIN programas p ON f.programa_id = p.id
            LEFT JOIN usuarios u2 ON a.instructor_seguimiento_id = u2.id
            WHERE 1=1
        ";
        $params = [];

        if ($instructorId !== null) {
            $sql .= " AND (f.instructor_id = ? OR a.instructor_seguimiento_id = ?)";
            $params[] = $instructorId;
            $params[] = $instructorId;
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.nombre LIKE ? OR a.numero_documento LIKE ? OR u.email LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if (!empty($filters['ficha_id'])) {
            $sql .= " AND a.ficha_id = ?";
            $params[] = (int)$filters['ficha_id'];
        }

        if (!empty($filters['estado'])) {
            $sql .= " AND a.estado = ?";
            $params[] = $filters['estado'];
        }

        $sql .= " ORDER BY u.nombre";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enroll a new apprentice (Transaction: User + Apprentice + Evaluations + Ficha count)
     */
    public static function matricular(array $data, int $createdByUserId): int {
        $db = Database::getConnection();
        
        // Validar duplicados
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            throw new Exception('El correo electrónico ya se encuentra registrado.');
        }

        $stmt = $db->prepare("SELECT id FROM aprendices WHERE numero_documento = ?");
        $stmt->execute([$data['numero_documento']]);
        if ($stmt->fetch()) {
            throw new Exception('El número de documento ya se encuentra matriculado.');
        }

        try {
            $db->beginTransaction();

            // 1. Crear el usuario
            $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
            $avatar_color = $colors[array_rand($colors)];
            $password_hash = password_hash('Sena2026', PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado)
                VALUES (?, ?, ?, 'aprendiz', ?, 'activo')
            ");
            $stmt->execute([$data['nombre'], $data['email'], $password_hash, $avatar_color]);
            $usuario_id = (int)$db->lastInsertId();

            // 2. Crear aprendiz
            $stmt = $db->prepare("
                INSERT INTO aprendices (usuario_id, ficha_id, instructor_seguimiento_id, numero_documento, tipo_documento, genero, fecha_nacimiento, telefono, ciudad, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'matriculado')
            ");
            $stmt->execute([
                $usuario_id, 
                $data['ficha_id'], 
                $data['instructor_seguimiento_id'], 
                $data['numero_documento'], 
                $data['tipo_documento'], 
                $data['genero'], 
                $data['fecha_nacimiento'] ?: null, 
                $data['telefono'] ?: '', 
                $data['ciudad'] ?: ''
            ]);
            $new_aprendiz_id = (int)$db->lastInsertId();

            // 3. Inicializar evaluaciones como 'pendiente'
            if (function_exists('inicializarEvaluacionesAprendiz')) {
                inicializarEvaluacionesAprendiz($db, $new_aprendiz_id, (int)$data['ficha_id']);
            }

            // 4. Incrementar contador en la ficha
            $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$data['ficha_id']]);

            // 5. Registrar log
            $stmtLog = $db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Crear', 'Matriculas', 'aprendices', ?, ?)
            ");
            $stmtLog->execute([$createdByUserId, $new_aprendiz_id, "Matriculó al aprendiz {$data['nombre']} en ficha ID {$data['ficha_id']}"]);

            $db->commit();
            return $new_aprendiz_id;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update apprentice enrollment details
     */
    public static function editarMatricula(int $aprendizId, array $data, int $updatedByUserId): void {
        $db = Database::getConnection();

        // Obtener datos actuales
        $stmt = $db->prepare("SELECT ficha_id, usuario_id FROM aprendices WHERE id = ?");
        $stmt->execute([$aprendizId]);
        $old_ap = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_ap) {
            throw new Exception('No se encontró el registro del aprendiz.');
        }

        $old_ficha_id = (int)$old_ap['ficha_id'];
        $usuario_id = (int)$old_ap['usuario_id'];

        // Verificar duplicados
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $usuario_id]);
        if ($stmt->fetch()) {
            throw new Exception('El correo electrónico ya se encuentra registrado por otro usuario.');
        }

        $stmt = $db->prepare("SELECT id FROM aprendices WHERE numero_documento = ? AND id != ?");
        $stmt->execute([$data['numero_documento'], $aprendizId]);
        if ($stmt->fetch()) {
            throw new Exception('El número de documento ya se encuentra registrado por otro aprendiz.');
        }

        try {
            $db->beginTransaction();

            // 1. Actualizar usuarios
            $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
            $stmt->execute([$data['nombre'], $data['email'], $usuario_id]);

            // 2. Actualizar aprendices
            $stmt = $db->prepare("
                UPDATE aprendices 
                SET ficha_id = ?, instructor_seguimiento_id = ?, estado = ?, tipo_documento = ?, numero_documento = ?, 
                    genero = ?, fecha_nacimiento = ?, telefono = ?, ciudad = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $data['ficha_id'],
                $data['instructor_seguimiento_id'],
                $data['estado'],
                $data['tipo_documento'],
                $data['numero_documento'],
                $data['genero'],
                $data['fecha_nacimiento'] ?: null,
                $data['telefono'] ?: '',
                $data['ciudad'] ?: '',
                $aprendizId
            ]);

            // 3. Si cambió de ficha, actualizar los contadores
            if ($old_ficha_id !== (int)$data['ficha_id']) {
                $db->prepare("UPDATE fichas SET cantidad_aprendices = GREATEST(0, cantidad_aprendices - 1) WHERE id = ?")->execute([$old_ficha_id]);
                $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$data['ficha_id']]);
            }

            // 4. Registrar log
            $stmtLog = $db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Editar', 'Matriculas', 'aprendices', ?, ?)
            ");
            $stmtLog->execute([$updatedByUserId, $aprendizId, "Actualizó información de matrícula y datos personales del aprendiz: {$data['nombre']}"]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete/unregister an apprentice
     */
    public static function eliminar(int $aprendizId, int $deletedByUserId): void {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT ficha_id, usuario_id FROM aprendices WHERE id = ?");
        $stmt->execute([$aprendizId]);
        $ap = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ap) {
            throw new Exception('No se encontró el registro del aprendiz.');
        }

        $ficha_id = (int)$ap['ficha_id'];
        $usuario_id = (int)$ap['usuario_id'];

        try {
            $db->beginTransaction();

            // Eliminar de aprendices
            $db->prepare("DELETE FROM aprendices WHERE id = ?")->execute([$aprendizId]);

            // Desactivar el usuario
            $db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?")->execute([$usuario_id]);

            // Decrementar contador
            $db->prepare("UPDATE fichas SET cantidad_aprendices = GREATEST(0, cantidad_aprendices - 1) WHERE id = ?")->execute([$ficha_id]);

            // Registrar log
            $stmtLog = $db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Eliminar', 'Matriculas', 'aprendices', ?, ?)
            ");
            $stmtLog->execute([$deletedByUserId, $aprendizId, "Eliminó la matrícula del aprendiz ID $aprendizId y desactivó su usuario"]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
