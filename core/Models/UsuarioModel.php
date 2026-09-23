<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Support\Enums;
use Core\Support\Validador;
use Core\Database;
use Core\Interfaces\UsuarioRepositoryInterface;
use PDO;
use Exception;

class UsuarioModel implements UsuarioRepositoryInterface {
    /**
     * Valor que no existe en ningún ENUM del esquema. Se usa cuando un
     * filtro llega con un valor desconocido: así la consulta no devuelve
     * nada, en lugar de ignorar el filtro y devolverlo todo.
     */
    private const VALOR_IMPOSIBLE = "\x00__sin_coincidencia__";

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todos los usuarios ordenados por fecha de creación descendente.
     *
     * Se conserva sin filtros ni paginación porque forma parte de
     * UsuarioRepositoryInterface. Para el listado de pantalla usar
     * `getFilteredList()`, que sí pagina.
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
     * Listado de usuarios con búsqueda, filtros y paginación.
     *
     * La búsqueda se hace en SQL y no en JavaScript (como hacía
     * `assets/js/modules/usuarios.js`, ocultando filas ya renderizadas):
     * al paginar, un filtro de cliente solo alcanzaría a las 25 filas
     * visibles y daría la impresión de que el resto no existe.
     *
     * @param array{search?:string, rol?:string, estado?:string} $filters
     */
    public function getFilteredList(array $filters = [], ?int $limit = null, int $offset = 0): array {
        [$where, $params] = $this->construirFiltro($filters);
        try {
            $sql = "SELECT id, nombre, email, rol, estado, fecha_creacion
                      FROM usuarios
                     WHERE 1=1 $where
                     ORDER BY fecha_creacion DESC, id DESC";
            if ($limit !== null) {
                // LIMIT/OFFSET van interpolados como enteros ya saneados:
                // MariaDB no acepta parámetros preparados en esa posición
                // cuando emulate_prepares está desactivado.
                $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . max(0, $offset);
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al cargar usuarios: " . $e->getMessage());
        }
    }

    /**
     * Cuenta los usuarios que cumplen los mismos filtros, para saber
     * cuántas páginas hay.
     */
    public function contarFiltrados(array $filters = []): int {
        [$where, $params] = $this->construirFiltro($filters);
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE 1=1 $where");
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("Error al contar usuarios: " . $e->getMessage());
        }
    }

    /**
     * Fragmento WHERE compartido por el listado y el conteo: si se
     * construyeran por separado, el total y las filas podrían dejar de
     * coincidir en cuanto se añada un filtro a uno y no al otro.
     *
     * @return array{0:string, 1:array}
     */
    private function construirFiltro(array $filters): array {
        $where = '';
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where .= " AND (nombre LIKE ? OR email LIKE ?)";
            $params[] = "%" . Validador::escaparLike($search) . "%";
            $params[] = "%" . Validador::escaparLike($search) . "%";
        }

        // Un filtro con un valor que no existe NO debe ignorarse: hacerlo
        // significaba que al manipular `?rol=cualquier-cosa` el listado
        // devolvía TODOS los usuarios en lugar de ninguno, es decir, que
        // trastear con la URL mostraba más de lo que la pantalla ofrecía.
        // Ahora un valor desconocido no encaja con nada, que es la
        // respuesta honesta a "enséñame los usuarios con el rol X".
        $rol = (string)($filters['rol'] ?? '');
        if ($rol !== '') {
            $where .= " AND rol = ?";
            $params[] = in_array($rol, Enums::USUARIO_ROL, true) ? $rol : self::VALOR_IMPOSIBLE;
        }

        $estado = (string)($filters['estado'] ?? '');
        if ($estado !== '') {
            $where .= " AND estado = ?";
            $params[] = in_array($estado, Enums::USUARIO_ESTADO, true) ? $estado : self::VALOR_IMPOSIBLE;
        }

        return [$where, $params];
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
     * Importación masiva idempotente: inserta solo los usuarios cuyo email no
     * está registrado todavía y omite el resto.
     *
     * A diferencia de createMultiple(), un email repetido no aborta la carga
     * completa: se salta esa fila y se sigue. Así, reimportar el mismo archivo
     * no crea duplicados ni falla.
     *
     * Devuelve:
     *   [
     *     'insertados' => [ ...filas de $usersData realmente creadas... ],
     *     'omitidos'   => [ ...emails que ya existían... ]
     *   ]
     * Se devuelven las filas insertadas (no solo el conteo) porque la vista
     * necesita mostrar la contraseña temporal únicamente de los usuarios que
     * sí se crearon.
     *
     * Nota: este importador escribe SOLO en `usuarios`. La fila de `aprendices`
     * la crea el módulo de Matrículas (AprendizModel::matricular / carga CSV de
     * matrículas), así que aquí no hay riesgo de dejar un usuario sin su
     * aprendiz asociado.
     */
    public function importMultiple(array $usersData): array {
        $insertados = [];
        $omitidos = [];

        try {
            $this->db->beginTransaction();

            $check = $this->db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            // INSERT IGNORE además del SELECT: cubre emails repetidos dentro del
            // mismo archivo y carreras entre importaciones simultáneas sin que
            // la clave única aborte la transacción entera.
            $insert = $this->db->prepare("
                INSERT IGNORE INTO usuarios (nombre, email, password, rol, avatar_color, estado, debe_cambiar_password)
                VALUES (?, ?, ?, ?, ?, 'activo', 1)
            ");

            foreach ($usersData as $data) {
                $check->execute([$data['email']]);
                if ($check->fetch()) {
                    $omitidos[] = $data['email'];
                    continue;
                }

                // El hash se calcula solo para las filas que sí se van a crear.
                $insert->execute([
                    $data['nombre'],
                    $data['email'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['rol'],
                    $data['avatar_color']
                ]);

                if ($insert->rowCount() === 1) {
                    $insertados[] = $data;
                } else {
                    $omitidos[] = $data['email'];
                }
            }

            $this->db->commit();
            return ['insertados' => $insertados, 'omitidos' => $omitidos];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
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
