<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Interfaces\UsuarioRepositoryInterface;
use Core\Support\Enums;
use Core\Support\Validador;
use PDO;

/**
 * Acceso a `usuarios`. Sin reglas de negocio (ver UsuariosService).
 */
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
     * Fragmento WHERE compartido por el listado y el conteo: si se
     * construyeran por separado, el total y las filas podrían dejar de
     * coincidir en cuanto se añada un filtro a uno y no al otro.
     *
     * @return array{0:string, 1:array}
     */
    private function construirFiltro(array $f): array {
        $where = '';
        $p = [];
        $search = trim((string)($f['search'] ?? ''));
        if ($search !== '') {
            $where .= " AND (nombre LIKE ? OR email LIKE ?)";
            $t = '%' . Validador::escaparLike($search) . '%';
            array_push($p, $t, $t);
        }
        // Un filtro con un valor inventado no encaja con nada: ignorarlo
        // hacía que `?rol=loquesea` devolviera TODOS los usuarios.
        foreach (['rol' => Enums::USUARIO_ROL, 'estado' => Enums::USUARIO_ESTADO] as $campo => $validos) {
            $v = (string)($f[$campo] ?? '');
            if ($v !== '') {
                $where .= " AND $campo = ?";
                $p[] = in_array($v, $validos, true) ? $v : self::VALOR_IMPOSIBLE;
            }
        }
        return [$where, $p];
    }

    public function listar(array $filtros, int $limite, int $offset): array {
        [$where, $p] = $this->construirFiltro($filtros);
        $limite = max(1, min($limite, 100));
        $offset = max(0, $offset);
        $st = $this->db->prepare("
            SELECT id, nombre, email, rol, estado, avatar_color, debe_cambiar_password, fecha_creacion
              FROM usuarios
             WHERE 1=1 $where
             ORDER BY fecha_creacion DESC, id DESC
             LIMIT $limite OFFSET $offset
        ");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros): int {
        [$where, $p] = $this->construirFiltro($filtros);
        $st = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE 1=1 $where");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    /** Todos los que cumplen el filtro, sin paginar: para exportar. */
    public function paraExportar(array $filtros, int $maximo): array {
        [$where, $p] = $this->construirFiltro($filtros);
        $maximo = max(1, $maximo);
        $st = $this->db->prepare("SELECT id, nombre, email, rol, estado, fecha_creacion FROM usuarios WHERE 1=1 $where
                                   ORDER BY nombre LIMIT $maximo");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Nunca devuelve el hash de la contraseña. */
    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT id, nombre, email, rol, estado, avatar_color, debe_cambiar_password FROM usuarios WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeEmail(string $email, ?int $exceptoId = null): bool {
        $st = $this->db->prepare("SELECT 1 FROM usuarios WHERE email = ? AND id <> ?");
        $st->execute([$email, $exceptoId ?? 0]);
        return (bool)$st->fetchColumn();
    }

    public function crear(array $d, string $hash, bool $debeCambiar): int {
        $this->db->prepare("
            INSERT INTO usuarios (nombre, email, password, debe_cambiar_password, rol, avatar_color, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'activo')
        ")->execute([$d['nombre'], $d['email'], $hash, $debeCambiar ? 1 : 0, $d['rol'], $d['avatar_color']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, estado = ?, avatar_color = ? WHERE id = ?")
                 ->execute([$d['nombre'], $d['email'], $d['rol'], $d['estado'], $d['avatar_color'], $id]);
    }

    public function cambiarEstado(int $id, string $estado): void {
        $this->db->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")->execute([$estado, $id]);
    }

    public function fijarPassword(int $id, string $hash, bool $debeCambiar): void {
        $this->db->prepare("UPDATE usuarios SET password = ?, debe_cambiar_password = ? WHERE id = ?")
                 ->execute([$hash, $debeCambiar ? 1 : 0, $id]);
    }

    public function contarCoordinadoresActivos(?int $excepto = null): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'coordinador' AND estado = 'activo' AND id <> ?");
        $st->execute([$excepto ?? 0]);
        return (int)$st->fetchColumn();
    }

    /**
     * Inserta las filas cuyo correo no existe todavía; omite el resto.
     * Idempotente: reimportar el mismo archivo no duplica ni falla.
     *
     * Cada fila trae ya su `hash`: el servicio lo calcula para poder
     * entregar la contraseña temporal en claro una sola vez.
     */
    public function importar(array $filas): array {
        $insertados = $omitidos = [];
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            // INSERT IGNORE cubre correos repetidos dentro del mismo archivo
            // y carreras entre importaciones simultáneas.
            $st = $this->db->prepare("
                INSERT IGNORE INTO usuarios (nombre, email, password, debe_cambiar_password, rol, avatar_color, estado)
                VALUES (?, ?, ?, 1, ?, ?, 'activo')
            ");
            foreach ($filas as $f) {
                $st->execute([$f['nombre'], $f['email'], $f['hash'], $f['rol'], $f['avatar_color']]);
                if ($st->rowCount() === 1) {
                    $f['id'] = (int)$this->db->lastInsertId();
                    $insertados[] = $f;
                } else {
                    $omitidos[] = $f['email'];
                }
            }
            if ($propia) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return ['insertados' => $insertados, 'omitidos' => $omitidos];
    }
}
