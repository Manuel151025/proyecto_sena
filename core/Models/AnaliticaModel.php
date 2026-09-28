<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Semaforo;
use PDO;

/**
 * Indicadores de los paneles (RF04), calculados al leer y acotados por rol.
 *
 * Alcance:
 *  - coordinación: todo el centro;
 *  - instructor: las fichas en las que tiene autoridad (líder, asignación o
 *    seguimiento), más su carga personal (lo que él califica);
 *  - aprendiz: lo suyo, y como referencia el promedio de su ficha.
 *
 * Definiciones (las mismas en paneles, fichas y reportes):
 *  - En formación: aprendices matriculados, suspendidos o en etapa práctica.
 *  - Desempeño: RAP en A sobre los ya evaluados (A + D). Es lo que clasifica
 *    el semáforo.
 *  - Avance: RAP en A sobre el total del programa. Mide cuánto falta.
 *  - Deserción: desertados sobre el total de aprendices que tuvo el alcance.
 *
 * Antes cada panel usaba `fichas.cumplimiento_porcentaje`, un número que
 * nadie recalculaba (y que el módulo de evidencias sobrescribía con otra
 * fórmula), y las cifras del coordinador no cuadraban con las de la ficha.
 */
class AnaliticaModel {
    private const EN_FORMACION = "('matriculado','suspendido','etapa_practica')";

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** Condición sobre el alias `f` y sus parámetros. @return array{0:string, 1:array} */
    private function alcance(Actor $actor): array {
        if ($actor->esCoordinador()) {
            return ['1=1', []];
        }
        if ($actor->esInstructor()) {
            return ['f.id IN (' . InstructorAccessService::sqlFichasDelInstructor() . ')', [$actor->id, $actor->id, $actor->id]];
        }
        return ['f.id IN (SELECT ficha_id FROM aprendices WHERE usuario_id = ?)', [$actor->id]];
    }

    private function una(string $sql, array $p): array {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function todas(string $sql, array $p): array {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function pct(int|float $parte, int|float $total): ?float {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }

    // -----------------------------------------------------------------
    // CIFRAS GENERALES
    // -----------------------------------------------------------------

    public function resumen(Actor $actor): array {
        [$w, $p] = $this->alcance($actor);
        $ap = $this->una("SELECT COUNT(*) AS fichas_activas FROM fichas f WHERE f.estado <> 'cierre' AND $w", $p);
        // Los aprendices cuentan en cualquier ficha del alcance (también en
        // las que están en cierre), igual que el semáforo y los juicios.
        $ap += $this->una("
            SELECT COALESCE(SUM(a.estado IN " . self::EN_FORMACION . "), 0) AS aprendices,
                   COALESCE(SUM(a.estado = 'desertado'), 0) AS desertados, COUNT(*) AS aprendices_total
              FROM aprendices a JOIN fichas f ON f.id = a.ficha_id WHERE $w", $p);
        $ev = $this->una("
            SELECT COALESCE(SUM(e.concepto = 'A'), 0) AS a, COALESCE(SUM(e.concepto = 'D'), 0) AS d,
                   COALESCE(SUM(e.concepto = 'pendiente'), 0) AS pendientes, COUNT(*) AS total
              FROM evaluaciones e JOIN aprendices a ON a.id = e.aprendiz_id JOIN fichas f ON f.id = e.ficha_id
             WHERE a.estado IN " . self::EN_FORMACION . " AND $w", $p);
        $pl = $this->una("
            SELECT COALESCE(SUM(pm.estado IN ('abierto','en_curso')), 0) AS planes_vigentes,
                   COALESCE(SUM(pm.estado IN ('abierto','en_curso') AND pm.fecha_limite < CURDATE()), 0) AS planes_vencidos,
                   COALESCE(SUM(pm.estado = 'cumplido'), 0) AS planes_cumplidos
              FROM planes_mejoramiento pm JOIN fichas f ON f.id = pm.ficha_id WHERE $w", $p);
        $evi = $this->una("SELECT COUNT(*) AS evidencias_por_revisar FROM evidencias ev JOIN fichas f ON f.id = ev.ficha_id
                            WHERE ev.estado = 'enviada' AND $w", $p);
        $r = array_map('intval', $ap + $ev + $pl + $evi);
        $r['desempeno'] = self::pct($r['a'], $r['a'] + $r['d']);
        $r['avance'] = self::pct($r['a'], $r['total']);
        $r['desercion'] = self::pct($r['desertados'], $r['aprendices_total']);
        $r['semaforo'] = Semaforo::porcentaje($r['desempeno']);
        if ($actor->esCoordinador()) {
            $r['instructores'] = (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'instructor' AND estado = 'activo'")->fetchColumn();
        }
        return $r;
    }

    /** Aprendices en formación por semáforo. @return array<string,int> */
    public function semaforo(Actor $actor): array {
        [$w, $p] = $this->alcance($actor);
        $filas = $this->todas("
            SELECT " . Semaforo::sqlAprendiz('t.pct_a', 't.en_d') . " AS semaforo, COUNT(*) AS n
              FROM (SELECT a.id,
                           SUM(e.concepto = 'D') AS en_d,
                           SUM(e.concepto = 'A') * 100 / NULLIF(SUM(e.concepto IN ('A','D')), 0) AS pct_a
                      FROM aprendices a JOIN fichas f ON f.id = a.ficha_id
                      LEFT JOIN evaluaciones e ON e.aprendiz_id = a.id AND e.ficha_id = a.ficha_id
                     WHERE a.estado IN " . self::EN_FORMACION . " AND $w
                     GROUP BY a.id) t
             GROUP BY 1", $p);
        $r = [Semaforo::CRITICO => 0, Semaforo::RIESGO => 0, Semaforo::AL_DIA => 0, Semaforo::SIN_DATOS => 0];
        foreach ($filas as $f) {
            $r[$f['semaforo']] = (int)$f['n'];
        }
        return $r;
    }

    /** Aprendices críticos y en riesgo, los peores primero. */
    public function aprendicesEnRiesgo(Actor $actor, int $limite = 10): array {
        [$w, $p] = $this->alcance($actor);
        $filas = $this->todas("
            SELECT * FROM (
                SELECT a.id, u.nombre, a.numero_documento, f.id AS ficha_id, f.numero_ficha,
                       COALESCE(SUM(e.concepto = 'A'), 0) AS aprobados, COALESCE(SUM(e.concepto = 'D'), 0) AS en_d,
                       COALESCE(SUM(e.concepto = 'pendiente'), 0) AS pendientes,
                       SUM(e.concepto = 'A') * 100 / NULLIF(SUM(e.concepto IN ('A','D')), 0) AS pct_a,
                       (SELECT COUNT(*) FROM planes_mejoramiento pm WHERE pm.aprendiz_id = a.id AND pm.estado IN ('abierto','en_curso')) AS planes
                  FROM aprendices a JOIN usuarios u ON u.id = a.usuario_id JOIN fichas f ON f.id = a.ficha_id
                  LEFT JOIN evaluaciones e ON e.aprendiz_id = a.id AND e.ficha_id = a.ficha_id
                 WHERE a.estado IN " . self::EN_FORMACION . " AND $w
                 GROUP BY a.id) t
             WHERE " . Semaforo::sqlAprendiz('t.pct_a', 't.en_d') . " IN ('" . Semaforo::CRITICO . "','" . Semaforo::RIESGO . "')
             ORDER BY t.en_d DESC, t.pct_a ASC
             LIMIT " . max(1, min($limite, 5000)), $p);
        return array_map(static function (array $r): array {
            $r['pct_a'] = $r['pct_a'] !== null ? round((float)$r['pct_a'], 1) : null;
            $r['semaforo'] = Semaforo::aprendiz($r['pct_a'], (int)$r['en_d']);
            return $r;
        }, $filas);
    }

    // -----------------------------------------------------------------
    // DESGLOSES
    // -----------------------------------------------------------------

    /** Por programa (coordinación). */
    public function porPrograma(): array {
        return array_map(function (array $r): array {
            $r = array_map(static fn($v) => is_numeric($v) ? (int)$v : $v, $r);
            $r['desempeno'] = self::pct($r['a'], $r['a'] + $r['d']);
            $r['avance'] = self::pct($r['a'], $r['total']);
            $r['desercion'] = self::pct($r['desertados'], $r['aprendices_total']);
            return $r;
        }, $this->todas("
            SELECT p.codigo, p.nombre,
                   (SELECT COUNT(*) FROM fichas f2 WHERE f2.programa_id = p.id AND f2.estado <> 'cierre') AS fichas,
                   (SELECT COUNT(*) FROM aprendices a2 JOIN fichas f3 ON f3.id = a2.ficha_id WHERE f3.programa_id = p.id AND a2.estado IN " . self::EN_FORMACION . ") AS aprendices,
                   (SELECT COUNT(*) FROM aprendices a3 JOIN fichas f4 ON f4.id = a3.ficha_id WHERE f4.programa_id = p.id AND a3.estado = 'desertado') AS desertados,
                   (SELECT COUNT(*) FROM aprendices a4 JOIN fichas f5 ON f5.id = a4.ficha_id WHERE f5.programa_id = p.id) AS aprendices_total,
                   COALESCE(SUM(e.concepto = 'A'), 0) AS a, COALESCE(SUM(e.concepto = 'D'), 0) AS d, COUNT(e.id) AS total
              FROM programas p
              LEFT JOIN fichas f ON f.programa_id = p.id
              LEFT JOIN aprendices a ON a.ficha_id = f.id AND a.estado IN " . self::EN_FORMACION . "
              LEFT JOIN evaluaciones e ON e.aprendiz_id = a.id AND e.ficha_id = f.id
             WHERE p.estado = 'activo'
             GROUP BY p.id
             ORDER BY p.nombre", []));
    }

    /**
     * Carga y actividad de cada instructor (coordinación): lo que tiene
     * pendiente de calificar, los D a su cargo, los juicios que emitió en
     * los últimos 30 días y sus planes vigentes y vencidos.
     */
    public function porInstructor(): array {
        return array_map(static fn($r) => array_map(static fn($v) => is_numeric($v) ? (int)$v : $v, $r), $this->todas("
            SELECT u.id, u.nombre,
                   (SELECT COUNT(*) FROM fichas f WHERE f.instructor_id = u.id AND f.estado <> 'cierre') AS fichas_lider,
                   (SELECT COUNT(*) FROM evaluaciones e JOIN aprendices a ON a.id = e.aprendiz_id
                     WHERE e.instructor_id = u.id AND e.concepto = 'pendiente' AND a.estado IN " . self::EN_FORMACION . ") AS pendientes,
                   (SELECT COUNT(*) FROM evaluaciones e JOIN aprendices a ON a.id = e.aprendiz_id
                     WHERE e.instructor_id = u.id AND e.concepto = 'D' AND a.estado IN " . self::EN_FORMACION . ") AS en_d,
                   (SELECT COUNT(*) FROM historial_evaluaciones h
                     WHERE h.usuario_id = u.id AND h.concepto_nuevo IN ('A','D') AND h.fecha_cambio >= CURDATE() - INTERVAL 30 DAY) AS juicios_30d,
                   (SELECT COUNT(*) FROM planes_mejoramiento pm WHERE pm.instructor_id = u.id AND pm.estado IN ('abierto','en_curso')) AS planes_vigentes,
                   (SELECT COUNT(*) FROM planes_mejoramiento pm WHERE pm.instructor_id = u.id AND pm.estado IN ('abierto','en_curso') AND pm.fecha_limite < CURDATE()) AS planes_vencidos
              FROM usuarios u
             WHERE u.rol = 'instructor' AND u.estado = 'activo'
             ORDER BY pendientes DESC, u.nombre", []));
    }

    /** Competencias con mayor proporción de D entre lo evaluado (mínimo 5 juicios). */
    public function competenciasCriticas(Actor $actor, int $limite = 8): array {
        [$w, $p] = $this->alcance($actor);
        return array_map(static function (array $r): array {
            $r['evaluados'] = (int)$r['evaluados'];
            $r['d'] = (int)$r['d'];
            $r['pct_d'] = round($r['d'] * 100 / max(1, $r['evaluados']), 1);
            return $r;
        }, $this->todas("
            SELECT c.codigo, c.nombre, p.codigo AS programa, COUNT(*) AS evaluados, SUM(e.concepto = 'D') AS d
              FROM evaluaciones e
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c ON c.id = ra.competencia_id
              JOIN programas p ON p.id = c.programa_id
              JOIN fichas f ON f.id = e.ficha_id
             WHERE e.concepto IN ('A','D') AND $w
             GROUP BY c.id
            HAVING evaluados >= 5 AND d > 0
             ORDER BY SUM(e.concepto = 'D') / COUNT(*) DESC, SUM(e.concepto = 'D') DESC
             LIMIT " . max(1, min($limite, 50)), $p));
    }

    /**
     * Juicios A y D emitidos por semana (según el historial), las últimas
     * `$semanas` semanas. @return array{etiquetas:list<string>, a:list<int>, d:list<int>}
     */
    public function tendencia(Actor $actor, int $semanas = 12): array {
        [$w, $p] = $this->alcance($actor);
        $semanas = max(4, min($semanas, 52));
        $desde = date('Y-m-d', strtotime('monday this week -' . ($semanas - 1) . ' weeks'));
        $filas = $this->todas("
            SELECT DATE_SUB(DATE(h.fecha_cambio), INTERVAL WEEKDAY(h.fecha_cambio) DAY) AS semana,
                   SUM(h.concepto_nuevo = 'A') AS a, SUM(h.concepto_nuevo = 'D') AS d
              FROM historial_evaluaciones h
              JOIN evaluaciones e ON e.id = h.evaluacion_id
              JOIN fichas f ON f.id = e.ficha_id
             WHERE h.fecha_cambio >= ? AND h.concepto_nuevo IN ('A','D') AND $w
             GROUP BY semana", array_merge([$desde], $p));
        $porSemana = [];
        foreach ($filas as $f) {
            $porSemana[$f['semana']] = [(int)$f['a'], (int)$f['d']];
        }
        $r = ['etiquetas' => [], 'a' => [], 'd' => []];
        for ($i = 0; $i < $semanas; $i++) {
            $s = date('Y-m-d', strtotime("$desde +$i weeks"));
            $r['etiquetas'][] = date('d/m', strtotime($s));
            $r['a'][] = $porSemana[$s][0] ?? 0;
            $r['d'][] = $porSemana[$s][1] ?? 0;
        }
        return $r;
    }

    /** Actividades del proyecto formativo vencidas o que vencen en `$dias` días. */
    public function actividadesProximas(Actor $actor, int $dias = 14, int $limite = 8): array {
        [$w, $p] = $this->alcance($actor);
        return $this->todas("
            SELECT act.id, act.nombre, act.fecha_fin, act.estado, act.cumplimiento_porcentaje, f.numero_ficha,
                   DATEDIFF(act.fecha_fin, CURDATE()) AS dias
              FROM actividades act JOIN fichas f ON f.id = act.ficha_id
             WHERE act.estado NOT IN ('completada','cancelada') AND act.fecha_fin IS NOT NULL
               AND act.fecha_fin <= CURDATE() + INTERVAL ? DAY AND $w
             ORDER BY act.fecha_fin
             LIMIT " . max(1, min($limite, 50)), array_merge([max(1, $dias)], $p));
    }

    // -----------------------------------------------------------------
    // CARGA PERSONAL DEL INSTRUCTOR
    // -----------------------------------------------------------------

    /** Lo que el instructor tiene que atender: pendientes por calificar, D sin plan, planes vencidos, evidencias. */
    public function cargaInstructor(Actor $actor): array {
        $cond = InstructorAccessService::sqlCondicionAcceso();
        $id = $actor->id;
        $r = $this->una("
            SELECT COALESCE(SUM(e.concepto = 'pendiente'), 0) AS por_calificar,
                   COALESCE(SUM(e.concepto = 'D' AND NOT EXISTS (SELECT 1 FROM planes_mejoramiento pm WHERE pm.evaluacion_id = e.id AND pm.estado IN ('abierto','en_curso'))), 0) AS d_sin_plan
              FROM evaluaciones e
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c ON c.id = ra.competencia_id
              JOIN fichas f ON f.id = e.ficha_id
              JOIN aprendices ap ON ap.id = e.aprendiz_id
             WHERE ap.estado IN " . self::EN_FORMACION . " AND ($cond)", [$id, $id, $id]);
        $r += $this->una("SELECT COUNT(*) AS planes_vencidos FROM planes_mejoramiento
                          WHERE instructor_id = ? AND estado IN ('abierto','en_curso') AND fecha_limite < CURDATE()", [$id]);
        $r['evidencias_por_revisar'] = (new EvidenciasModel($this->db))->porRevisar($actor);
        return array_map('intval', $r);
    }

    // -----------------------------------------------------------------
    // APRENDIZ
    // -----------------------------------------------------------------

    /** Promedio de avance y desempeño de la ficha (referencia para el aprendiz). */
    public function promedioFicha(int $fichaId): array {
        $r = $this->una("
            SELECT AVG(t.avance) AS avance, AVG(t.pct_a) AS desempeno FROM (
                SELECT SUM(e.concepto = 'A') * 100 / NULLIF(COUNT(e.id), 0) AS avance,
                       SUM(e.concepto = 'A') * 100 / NULLIF(SUM(e.concepto IN ('A','D')), 0) AS pct_a
                  FROM aprendices a LEFT JOIN evaluaciones e ON e.aprendiz_id = a.id AND e.ficha_id = a.ficha_id
                 WHERE a.ficha_id = ? AND a.estado IN " . self::EN_FORMACION . "
                 GROUP BY a.id) t", [$fichaId]);
        return ['avance' => $r['avance'] !== null ? round((float)$r['avance'], 1) : null,
                'desempeno' => $r['desempeno'] !== null ? round((float)$r['desempeno'], 1) : null];
    }
}
