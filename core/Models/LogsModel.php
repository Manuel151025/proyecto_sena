<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Support\Validador;
use PDO;

/**
 * Bitácora de auditoría (`logs_sistema`), solo lectura. La escribe
 * Core\Services\Auditoria.
 */
class LogsModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array{0:string, 1:array} FROM + WHERE comunes al listado, el conteo y la exportación. */
    private function construirConsulta(array $f): array {
        $sql = " FROM logs_sistema l LEFT JOIN usuarios u ON u.id = l.usuario_id WHERE 1=1";
        $p = [];
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR l.descripcion LIKE ? OR l.ip_address LIKE ?)";
            array_push($p, $t, $t, $t, $t);
        }
        foreach (['accion' => 'l.accion', 'modulo' => 'l.modulo'] as $clave => $col) {
            if (($f[$clave] ?? '') !== '') {
                $sql .= " AND $col = ?";
                $p[] = (string)$f[$clave];
            }
        }
        if (!empty($f['usuario_id'])) {
            $sql .= " AND l.usuario_id = ?";
            $p[] = (int)$f['usuario_id'];
        }
        if (!empty($f['desde'])) {
            $sql .= " AND l.fecha >= ?";
            $p[] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $sql .= " AND l.fecha < ? + INTERVAL 1 DAY";
            $p[] = $f['hasta'];
        }
        return [$sql, $p];
    }

    public function contar(array $filtros): int {
        [$desde, $p] = $this->construirConsulta($filtros);
        $st = $this->db->prepare("SELECT COUNT(*) $desde");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    public function listar(array $filtros, int $limite, int $offset): array {
        [$desde, $p] = $this->construirConsulta($filtros);
        $st = $this->db->prepare("SELECT l.id, l.fecha, l.accion, l.modulo, l.tabla_afectada, l.id_registro, l.descripcion, l.ip_address,
                   u.nombre AS usuario_nombre, u.email AS usuario_email, u.rol AS usuario_rol
            $desde ORDER BY l.fecha DESC, l.id DESC LIMIT " . max(1, min($limite, 200)) . ' OFFSET ' . max(0, $offset));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paraExportar(array $filtros, int $maximo): array {
        [$desde, $p] = $this->construirConsulta($filtros);
        $st = $this->db->prepare("SELECT DATE_FORMAT(l.fecha, '%Y-%m-%d %H:%i:%s'), COALESCE(u.nombre, 'Sistema'), COALESCE(u.rol, ''), l.accion,
                   COALESCE(l.modulo, ''), COALESCE(l.tabla_afectada, ''), COALESCE(l.id_registro, ''), COALESCE(l.descripcion, ''), COALESCE(l.ip_address, '')
            $desde ORDER BY l.fecha DESC, l.id DESC LIMIT " . max(1, $maximo));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_NUM);
    }

    /** Valores que existen, para los filtros (la bitácora crece con acciones nuevas). */
    public function valores(string $columna): array {
        $col = match ($columna) { 'accion' => 'accion', 'modulo' => 'modulo', default => throw new \InvalidArgumentException('Columna no permitida') };
        return $this->db->query("SELECT DISTINCT $col FROM logs_sistema WHERE $col IS NOT NULL AND $col <> '' ORDER BY $col")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function usuarios(): array {
        return $this->db->query("SELECT DISTINCT u.id, u.nombre FROM logs_sistema l JOIN usuarios u ON u.id = l.usuario_id ORDER BY u.nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
}
