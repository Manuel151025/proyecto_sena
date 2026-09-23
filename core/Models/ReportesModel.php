<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use PDO;

/**
 * Consultas de los reportes (RF05). Devuelven filas por posición, en el
 * orden de las columnas que declara ReportesService.
 *
 * Alcance: coordinación, todo; instructor, lo que califica (la misma
 * condición que el listado de juicios y el permiso de calificar). Antes
 * cada reporte llevaba su propia copia de esa condición.
 */
class ReportesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** Condición sobre los alias e, c, f, ap. @return array{0:string, 1:array} */
    private function alcance(Actor $actor): array {
        if ($actor->esCoordinador()) {
            return ['1=1', []];
        }
        if ($actor->esInstructor()) {
            return ['(' . InstructorAccessService::sqlCondicionAcceso() . ')', [$actor->id, $actor->id, $actor->id]];
        }
        return ['1=0', []];
    }

    private function filas(string $sql, array $p): array {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_NUM);
    }

    private const DESDE = "
        FROM evaluaciones e
        JOIN aprendices ap ON ap.id = e.aprendiz_id
        JOIN usuarios u ON u.id = ap.usuario_id
        JOIN fichas f ON f.id = e.ficha_id
        JOIN programas p ON p.id = f.programa_id
        JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
        JOIN competencias c ON c.id = ra.competencia_id
        LEFT JOIN usuarios ui ON ui.id = e.instructor_id";

    /** Juicios de una ficha, aprendiz por aprendiz. */
    public function juiciosDeFicha(int $fichaId, Actor $actor): array {
        [$w, $p] = $this->alcance($actor);
        return $this->filas("
            SELECT u.nombre, ap.numero_documento, ap.estado, c.codigo, ra.codigo, ra.denominacion, e.concepto,
                   COALESCE(DATE_FORMAT(e.fecha_evaluacion, '%Y-%m-%d'), ''), COALESCE(ui.nombre, '')
            " . self::DESDE . " WHERE e.ficha_id = ? AND $w
            ORDER BY u.nombre, c.codigo, ra.codigo", array_merge([$fichaId], $p));
    }

    /**
     * Cumplimiento por instructor responsable, ficha y competencia. Se
     * agrupa por quien califica (`evaluaciones.instructor_id`), no por el
     * líder de la ficha: con asignaciones, el líder no califica todo.
     */
    public function porInstructor(Actor $actor): array {
        [$w, $p] = $this->alcance($actor);
        return array_map([self::class, 'conPorcentajes'], $this->filas("
            SELECT COALESCE(ui.nombre, '—'), f.numero_ficha, p.codigo, c.codigo, c.nombre,
                   COUNT(*), SUM(e.concepto = 'A'), SUM(e.concepto = 'D'), SUM(e.concepto = 'pendiente')
            " . self::DESDE . " WHERE ap.estado IN ('matriculado','suspendido','etapa_practica') AND $w
            GROUP BY ui.id, f.id, c.id
            ORDER BY ui.nombre, f.numero_ficha, c.codigo", $p));
    }

    /** Cumplimiento por competencia (todas las fichas del alcance). */
    public function porCompetencia(Actor $actor): array {
        [$w, $p] = $this->alcance($actor);
        return array_map([self::class, 'conPorcentajes'], $this->filas("
            SELECT p.codigo, c.codigo, c.nombre, COUNT(DISTINCT e.ficha_id), COUNT(DISTINCT e.aprendiz_id),
                   COUNT(*), SUM(e.concepto = 'A'), SUM(e.concepto = 'D'), SUM(e.concepto = 'pendiente')
            " . self::DESDE . " WHERE ap.estado IN ('matriculado','suspendido','etapa_practica') AND $w
            GROUP BY c.id
            ORDER BY p.codigo, c.codigo", $p));
    }

    /**
     * Cambios de juicio entre dos fechas (RNF02): quién, cuándo, de qué a
     * qué y por qué.
     */
    public function historial(Actor $actor, string $desde, string $hasta): array {
        [$w, $p] = $this->alcance($actor);
        return $this->filas("
            SELECT DATE_FORMAT(h.fecha_cambio, '%Y-%m-%d %H:%i'), f.numero_ficha, u.nombre, ap.numero_documento, ra.codigo,
                   h.concepto_anterior, h.concepto_nuevo, COALESCE(h.motivo, ''), COALESCE(uh.nombre, '')
              FROM historial_evaluaciones h
              JOIN evaluaciones e ON e.id = h.evaluacion_id
              JOIN aprendices ap ON ap.id = e.aprendiz_id
              JOIN usuarios u ON u.id = ap.usuario_id
              JOIN fichas f ON f.id = e.ficha_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c ON c.id = ra.competencia_id
              LEFT JOIN usuarios uh ON uh.id = h.usuario_id
             WHERE h.fecha_cambio >= ? AND h.fecha_cambio < ? + INTERVAL 1 DAY AND $w
             ORDER BY h.fecha_cambio DESC, h.id DESC", array_merge([$desde, $hasta], $p));
    }

    /**
     * Añade % de desempeño (A sobre A+D) y % de avance (A sobre el total)
     * a una fila cuyas cuatro últimas columnas son total, A, D, pendientes.
     */
    private static function conPorcentajes(array $f): array {
        $n = count($f);
        [$total, $a, $d] = [(int)$f[$n - 4], (int)$f[$n - 3], (int)$f[$n - 2]];
        $f[$n - 4] = $total;
        $f[$n - 3] = $a;
        $f[$n - 2] = $d;
        $f[$n - 1] = (int)$f[$n - 1];
        $f[] = $a + $d > 0 ? round($a * 100 / ($a + $d), 1) : '';
        $f[] = $total > 0 ? round($a * 100 / $total, 1) : '';
        return $f;
    }
}
