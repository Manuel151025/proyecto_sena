<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Interfaces\UsuarioRepositoryInterface;
use PDO;
use Exception;

class UsuarioModel implements UsuarioRepositoryInterface {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todos los usuarios ordenados por fecha de creación descendente.
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("SELECT id, nombre, email, rol, estado, fecha_creacion FROM usuarios ORDER BY fecha_creacion DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Se puede registrar el error en un log si se desea
            throw new Exception("Error al cargar usuarios: " . $e->getMessage());
        }
    }

    /**
     * Desactiva un usuario por su ID (soft-delete).
     *
     * No se borra físicamente: tablas como fichas, evaluaciones,
     * actividades o logs_sistema referencian usuarios.id sin
     * ON DELETE CASCADE, por lo que un DELETE físico falla en cuanto
     * el usuario tiene cualquier actividad registrada en el sistema.
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            throw new Exception("Error al eliminar usuario: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un usuario por su ID.
     */
    public function findById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT id, nombre, email, rol, estado, avatar_color FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Exception $e) {
            throw new Exception("Error al cargar usuario: " . $e->getMessage());
        }
    }

    /**
     * Crea un nuevo usuario.
     */
    public function create(array $data): bool {
        try {
            $stmt = $this->db->prepare("INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
            return $stmt->execute([
                $data['nombre'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['rol'],
                $data['avatar_color']
            ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception('Este email ya está registrado');
            }
            throw new Exception("Error al crear usuario: " . $e->getMessage());
        }
    }

    /**
     * Crea múltiples usuarios dentro de una transacción.
     * Retorna el número de registros insertados.
     */
    public function createMultiple(array $usersData): int {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado, debe_cambiar_password) VALUES (?, ?, ?, ?, ?, 'activo', 1)");

            $count = 0;
            foreach ($usersData as $data) {
                $stmt->execute([
                    $data['nombre'],
                    $data['email'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['rol'],
                    $data['avatar_color']
                ]);
                $count++;
            }
            
            $this->db->commit();
            return $count;
        } catch (Exception $e) {
            $this->db->rollBack();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                // Parseamos el email duplicado si es posible
                preg_match("/Duplicate entry '(.*)' for key/", $e->getMessage(), $matches);
                $dupEmail = $matches[1] ?? 'un correo';
                throw new Exception("Error: El email '$dupEmail' ya está registrado en la base de datos.");
            }
            throw new Exception("Error en la importación masiva: " . $e->getMessage());
        }
    }

    /**
     * Actualiza un usuario existente.
     */
    public function update(int $id, array $data): bool {
        try {
            // 1. Obtener los datos actuales del usuario antes del UPDATE
            $stmtOld = $this->db->prepare("SELECT rol FROM usuarios WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldUser = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $rol_antiguo = $oldUser ? $oldUser['rol'] : '';
            $rol_nuevo = $data['rol'];

            if (!empty($data['password'])) {
                $stmt = $this->db->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ?, estado = ?, avatar_color = ? WHERE id = ?");
                $result = $stmt->execute([
                    $data['nombre'],
                    $data['email'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['rol'],
                    $data['estado'],
                    $data['avatar_color'],
                    $id
                ]);
            } else {
                $stmt = $this->db->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, estado = ?, avatar_color = ? WHERE id = ?");
                $result = $stmt->execute([
                    $data['nombre'],
                    $data['email'],
                    $data['rol'],
                    $data['estado'],
                    $data['avatar_color'],
                    $id
                ]);
            }

            // Registrar la acción en logs_sistema si la actualización fue exitosa
            if ($result && function_exists('getCurrentUser') && getCurrentUser()) {
                $currentUserId = (int)getCurrentUser()['id'];
                
                // 2 y 3. Comparar roles y crear el mensaje dinámico
                $mensajeLog = "Editó al usuario {$data['nombre']} / ID: $id";
                if ($rol_antiguo !== '' && $rol_antiguo !== $rol_nuevo) {
                    $mensajeLog .= " - Cambio de rol: de {$rol_antiguo} a {$rol_nuevo}";
                }

                // 4. Registrar en logs_sistema
                $stmtLog = $this->db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Editar', 'Usuarios', 'usuarios', ?, ?)
                ");
                $stmtLog->execute([$currentUserId, $id, $mensajeLog]);
            }

            return $result;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception('Este email ya está registrado por otro usuario');
            }
            throw new Exception("Error al actualizar usuario: " . $e->getMessage());
        }
    }
}
