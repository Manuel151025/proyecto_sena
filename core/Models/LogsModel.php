<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Support\Validador;
use Core\Database;
use PDO;

class LogsModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getLogs(string $search, string $filter_accion, ?int $limit = null, int $offset = 0): array {
        [$from, $params] = $this->construirConsulta($search, $filter_accion);

        // Antes había un `LIMIT 100` fijo: en una bitácora de auditoría, que
        // solo crece, eso volvía inalcanzable todo el historial anterior a
        // los últimos 100 apuntes. Ahora el corte lo pone la paginación y el
        // total queda visible.
        $sql = "
            SELECT logs.*, u.nombre as usuario_nombre, u.email as usuario_email, u.rol as usuario_rol
            $from
            ORDER BY logs.fecha DESC, logs.id DESC
        ";

        if ($limit !== null) {
            // Enteros interpolados: con ATTR_EMULATE_PREPARES en false,
            // MariaDB no acepta parámetros ligados en LIMIT/OFFSET.
            $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . max(0, $offset);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total de apuntes que cumplen los mismos filtros, para paginar.
     */
    public function contarLogs(string $search, string $filter_accion): int {
        [$from, $params] = $this->construirConsulta($search, $filter_accion);
        $stmt = $this->db->prepare("SELECT COUNT(*) $from");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * FROM + WHERE compartidos por el listado y el conteo.
     *
     * @return array{0:string, 1:array}
     */
    private function construirConsulta(string $search, string $filter_accion): array {
        $from = "
            FROM logs_sistema logs
            LEFT JOIN usuarios u ON logs.usuario_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($search)) {
            $from .= " AND (u.nombre LIKE ? OR logs.descripcion LIKE ? OR logs.modulo LIKE ?)";
            $params[] = "%" . Validador::escaparLike($search) . "%";
            $params[] = "%" . Validador::escaparLike($search) . "%";
            $params[] = "%" . Validador::escaparLike($search) . "%";
        }

        if (!empty($filter_accion)) {
            $from .= " AND logs.accion = ?";
            $params[] = $filter_accion;
        }

        return [$from, $params];
    }
}
