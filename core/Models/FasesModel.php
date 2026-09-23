<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

/**
 * Fases de los proyectos formativos (Análisis, Planeación, Ejecución,
 * Evaluación y las que defina el proyecto).
 */
class FasesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Fases de un proyecto con su avance calculado a partir de las
     * actividades de las fichas indicadas.
     *
     * @param int[]|null $fichas Fichas que cuentan para el avance; null = todas.
     */
    public function listarDeProyecto(int $proyectoId, ?array $fichas = null): array {
        $filtro = '';
        $params = [];
        if ($fichas !== null) {
            if ($fichas === []) {
                $filtro = ' AND 1 = 0';
            } else {
                $filtro = ' AND a.ficha_id IN (' . implode(',', array_fill(0, count($fichas), '?')) . ')';
                $params = array_map('intval', $fichas);
            }
        }
        $st = $this->db->prepare("
            SELECT fp.*,
                   COUNT(a.id) AS total_actividades,
                   SUM(a.estado = 'completada') AS actividades_completadas,
                   AVG(CASE WHEN a.estado = 'completada' THEN 100 ELSE a.cumplimiento_porcentaje END) AS avance
              FROM fases_proyecto fp
              LEFT JOIN actividades a ON a.fase_id = fp.id AND a.estado <> 'cancelada' $filtro
             WHERE fp.proyecto_id = ?
             GROUP BY fp.id
             ORDER BY fp.numero_fase
        ");
        $st->execute(array_merge($params, [$proyectoId]));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista corta para selectores: fases de varios proyectos, agrupables por proyecto. */
    public function opcionesDeProyectos(array $proyectoIds): array {
        if ($proyectoIds === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($proyectoIds), '?'));
        $st = $this->db->prepare("SELECT id, proyecto_id, numero_fase, nombre FROM fases_proyecto
                                   WHERE proyecto_id IN ($marcas) ORDER BY proyecto_id, numero_fase");
        $st->execute(array_map('intval', $proyectoIds));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM fases_proyecto WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(int $proyectoId, array $d): int {
        $this->db->prepare("
            INSERT INTO fases_proyecto (proyecto_id, numero_fase, nombre, descripcion, fecha_inicio, fecha_fin, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$proyectoId, $d['numero_fase'], $d['nombre'], $d['descripcion'], $d['fecha_inicio'], $d['fecha_fin'], $d['estado']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("
            UPDATE fases_proyecto
               SET numero_fase = ?, nombre = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, estado = ?
             WHERE id = ?
        ")->execute([$d['numero_fase'], $d['nombre'], $d['descripcion'], $d['fecha_inicio'], $d['fecha_fin'], $d['estado'], $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM fases_proyecto WHERE id = ?")->execute([$id]);
    }

    public function contarActividades(int $id): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM actividades WHERE fase_id = ?");
        $st->execute([$id]);
        return (int)$st->fetchColumn();
    }
}
