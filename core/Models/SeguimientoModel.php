<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Semaforo;
use PDO;

/**
 * Expediente de seguimiento de un aprendiz: sus RAP agrupados por
 * competencia, quién califica cada uno y si el actor puede hacerlo, los
 * planes vigentes y la retroalimentación.
 *
 * Sustituye a 415 líneas que armaban la matriz aprendiz × RAP en PHP con
 * una consulta por aprendiz, copiaban la condición de acceso y la regla del
 * semáforo, y la volvían a calcular en JavaScript dentro de la vista.
 */
class SeguimientoModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function aprendizDeUsuario(int $usuarioId): ?int {
        $st = $this->db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $st->execute([$usuarioId]);
        return ($id = $st->fetchColumn()) !== false ? (int)$id : null;
    }

    public function fichaDeAprendiz(int $aprendizId): ?int {
        $st = $this->db->prepare("SELECT ficha_id FROM aprendices WHERE id = ?");
        $st->execute([$aprendizId]);
        return ($id = $st->fetchColumn()) !== false ? (int)$id : null;
    }

    /** Datos del aprendiz con sus cifras y su semáforo. */
    public function resumen(int $aprendizId): ?array {
        $st = $this->db->prepare("
            SELECT ap.id, ap.numero_documento, ap.tipo_documento, ap.estado, ap.ficha_id,
                   u.nombre, u.email, u.avatar_color, f.numero_ficha, p.nombre AS programa,
                   ul.nombre AS lider, us.nombre AS instructor_seguimiento,
                   COALESCE(SUM(e.concepto = 'A'), 0) AS aprobados, COALESCE(SUM(e.concepto = 'D'), 0) AS en_d,
                   COALESCE(SUM(e.concepto = 'pendiente'), 0) AS pendientes, COUNT(e.id) AS total,
                   (SELECT COUNT(*) FROM planes_mejoramiento pm WHERE pm.aprendiz_id = ap.id AND pm.estado IN ('abierto','en_curso')) AS planes_vigentes
              FROM aprendices ap
              JOIN usuarios u ON u.id = ap.usuario_id
              JOIN fichas f ON f.id = ap.ficha_id
              JOIN programas p ON p.id = f.programa_id
              LEFT JOIN usuarios ul ON ul.id = f.instructor_id
              LEFT JOIN usuarios us ON us.id = ap.instructor_seguimiento_id
              LEFT JOIN evaluaciones e ON e.aprendiz_id = ap.id AND e.ficha_id = ap.ficha_id
             WHERE ap.id = ?
             GROUP BY ap.id");
        $st->execute([$aprendizId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $evaluados = (int)$r['aprobados'] + (int)$r['en_d'];
        $r['pct_a'] = $evaluados > 0 ? round((int)$r['aprobados'] * 100 / $evaluados, 1) : null;
        $r['avance'] = (int)$r['total'] > 0 ? round((int)$r['aprobados'] * 100 / (int)$r['total'], 1) : 0.0;
        $r['semaforo'] = $r['estado'] === 'desertado' ? Semaforo::SIN_DATOS : Semaforo::aprendiz($r['pct_a'], (int)$r['en_d']);
        return $r;
    }

    /**
     * RAP del aprendiz agrupados por competencia.
     *
     * `puede_calificar` sale de la misma condición que el permiso de
     * JuiciosService, evaluada en SQL: la vista no decide quién califica.
     *
     * @return list<array{codigo:string, nombre:string, etapa_practica:bool, a:int, d:int, pendientes:int, raps:list<array>}>
     */
    public function competencias(int $aprendizId, Actor $actor): array {
        $puede = match (true) {
            $actor->esCoordinador() => '1',
            $actor->esInstructor()  => '(' . InstructorAccessService::sqlCondicionAcceso() . ')',
            default                 => '0',
        };
        $st = $this->db->prepare("
            SELECT e.id AS evaluacion_id, e.concepto, e.fecha_evaluacion, e.comentario,
                   ra.codigo AS ra_codigo, ra.denominacion AS ra_denominacion,
                   c.codigo AS competencia_codigo, c.nombre AS competencia_nombre, c.es_etapa_practica,
                   ui.nombre AS responsable,
                   pm.id AS plan_id, pm.fecha_limite AS plan_limite, pm.estado AS plan_estado,
                   $puede AS puede_calificar
              FROM evaluaciones e
              JOIN aprendices ap ON ap.id = e.aprendiz_id
              JOIN fichas f ON f.id = e.ficha_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c ON c.id = ra.competencia_id
              LEFT JOIN usuarios ui ON ui.id = e.instructor_id
              LEFT JOIN planes_mejoramiento pm ON pm.evaluacion_id = e.id AND pm.estado IN ('abierto','en_curso')
             WHERE e.aprendiz_id = ?
             ORDER BY c.es_etapa_practica, c.codigo, ra.codigo");
        $params = $actor->esInstructor() ? [$actor->id, $actor->id, $actor->id, $aprendizId] : [$aprendizId];
        $st->execute($params);

        $grupos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = $r['competencia_codigo'];
            $grupos[$k] ??= ['codigo' => $k, 'nombre' => $r['competencia_nombre'], 'etapa_practica' => (bool)$r['es_etapa_practica'],
                             'a' => 0, 'd' => 0, 'pendientes' => 0, 'raps' => []];
            $grupos[$k][$r['concepto'] === 'A' ? 'a' : ($r['concepto'] === 'D' ? 'd' : 'pendientes')]++;
            $r['puede_calificar'] = (bool)$r['puede_calificar'];
            $grupos[$k]['raps'][] = $r;
        }
        return array_values($grupos);
    }
}
