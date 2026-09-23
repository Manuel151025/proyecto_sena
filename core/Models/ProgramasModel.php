<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

/**
 * Acceso a `programas`. Sin reglas de negocio: esas viven en
 * Core\Services\ProgramasService.
 */
class ProgramasModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** Programas con el número de competencias, RAP y fichas de cada uno. */
    public function getAll(): array {
        return $this->db->query("
            SELECT p.id, p.nombre, p.codigo, p.descripcion, p.duracion_horas, p.estado, p.fecha_creacion,
                   (SELECT COUNT(*) FROM competencias c WHERE c.programa_id = p.id) AS total_competencias,
                   (SELECT COUNT(*) FROM resultados_aprendizaje ra JOIN competencias c ON c.id = ra.competencia_id
                     WHERE c.programa_id = p.id) AS total_rap,
                   (SELECT COUNT(*) FROM fichas f WHERE f.programa_id = p.id) AS total_fichas
              FROM programas p
             ORDER BY p.estado = 'activo' DESC, p.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista corta para selectores. */
    public function opciones(bool $soloActivos = true): array {
        $sql = "SELECT id, codigo, nombre FROM programas" . ($soloActivos ? " WHERE estado = 'activo'" : '') . " ORDER BY nombre";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM programas WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): int {
        $this->db->prepare("INSERT INTO programas (nombre, codigo, descripcion, duracion_horas, estado) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$d['nombre'], $d['codigo'], $d['descripcion'] !== '' ? $d['descripcion'] : null, $d['duracion_horas'], $d['estado']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("UPDATE programas SET nombre = ?, codigo = ?, descripcion = ?, duracion_horas = ?, estado = ? WHERE id = ?")
                 ->execute([$d['nombre'], $d['codigo'], $d['descripcion'] !== '' ? $d['descripcion'] : null, $d['duracion_horas'], $d['estado'], $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM programas WHERE id = ?")->execute([$id]);
    }

    /** @return array{fichas:int, competencias:int} */
    public function dependencias(int $id): array {
        $st = $this->db->prepare("SELECT (SELECT COUNT(*) FROM fichas WHERE programa_id = ?) AS fichas,
                                         (SELECT COUNT(*) FROM competencias WHERE programa_id = ?) AS competencias");
        $st->execute([$id, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['fichas' => (int)($r['fichas'] ?? 0), 'competencias' => (int)($r['competencias'] ?? 0)];
    }

    /** Totales de la estructura curricular, para el resumen de /estructura. */
    public function totalesEstructura(): array {
        return $this->db->query("
            SELECT (SELECT COUNT(*) FROM programas) AS programas,
                   (SELECT COUNT(*) FROM competencias) AS competencias,
                   (SELECT COUNT(*) FROM resultados_aprendizaje) AS resultados,
                   (SELECT COUNT(*) FROM proyectos) AS proyectos,
                   (SELECT COUNT(*) FROM fases_proyecto) AS fases
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
