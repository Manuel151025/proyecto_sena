<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

class LogsModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getLogs(string $search, string $filter_accion): array {
        $sql = "
            SELECT logs.*, u.nombre as usuario_nombre, u.email as usuario_email, u.rol as usuario_rol
            FROM logs_sistema logs
            LEFT JOIN usuarios u ON logs.usuario_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (u.nombre LIKE ? OR logs.descripcion LIKE ? OR logs.modulo LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($filter_accion)) {
            $sql .= " AND logs.accion = ?";
            $params[] = $filter_accion;
        }

        $sql .= " ORDER BY logs.fecha DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
