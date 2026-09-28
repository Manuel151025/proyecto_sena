<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

/**
 * Acceso a la tabla `notificaciones`.
 *
 * Sustituye a las funciones sueltas de `includes/notificaciones.php`, que
 * abrían su propia conexión en cada llamada y no se podían inyectar ni
 * probar. Toda consulta filtra por `usuario_id`: una notificación solo la
 * lee y la marca su destinatario.
 */
class NotificacionesModel {
    /** Máximo de avisos que se envían al navegador en una sola respuesta. */
    public const MAX_LISTADO = 20;

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    public function noLeidas(int $usuarioId, int $limite = self::MAX_LISTADO): array {
        $limite = max(1, min($limite, self::MAX_LISTADO));
        $stmt = $this->db->prepare(
            "SELECT id, titulo, mensaje, tipo, url, fecha_creacion
             FROM notificaciones
             WHERE usuario_id = ? AND leida = 0
             ORDER BY fecha_creacion DESC, id DESC
             LIMIT {$limite}"
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarNoLeidas(int $usuarioId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$usuarioId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Marca una notificación como leída.
     *
     * El filtro por `usuario_id` es el control de acceso: sin él, cualquier
     * usuario podía silenciar los avisos de otro probando identificadores.
     *
     * @return bool true si existía y era de ese usuario.
     */
    public function marcarLeida(int $id, int $usuarioId): bool {
        $stmt = $this->db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuarioId]);
        return $stmt->rowCount() > 0;
    }

    public function marcarTodas(int $usuarioId): int {
        $stmt = $this->db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$usuarioId]);
        return $stmt->rowCount();
    }

    /**
     * Inserta un aviso. Lo usa `Core\Services\Notificador`, que es quien
     * valida y recorta los textos; aquí solo se persiste.
     */
    public function crear(int $usuarioId, string $titulo, string $mensaje, string $tipo, ?string $url): int {
        $stmt = $this->db->prepare(
            "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, url) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$usuarioId, $titulo, $mensaje, $tipo, $url]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Borra los avisos leídos más antiguos que `$dias`, para que la tabla
     * no crezca sin límite. Se invoca de forma oportunista al crear avisos.
     */
    public function purgarLeidasAntiguas(int $dias = 90): int {
        $dias = max(7, $dias);
        $stmt = $this->db->prepare(
            "DELETE FROM notificaciones WHERE leida = 1 AND fecha_creacion < (NOW() - INTERVAL {$dias} DAY)"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
