<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Validador;
use PDO;

/**
 * Planes de mejoramiento (tabla `planes_mejoramiento`) y RAP en D que aún
 * no tienen plan, acotados por rol:
 *  - aprendiz: los suyos;
 *  - instructor: los que tiene a cargo o los de RAP que califica;
 *  - coordinación: todos.
 */
class MejoramientoModel {
    public const VIGENTES = ['abierto', 'en_curso'];
    /** Filtros de estado: los de la tabla más dos derivados. */
    public const FILTROS = ['vigente', 'vencido', 'abierto', 'en_curso', 'cumplido', 'no_cumplido'];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    private const DESDE = "
        FROM planes_mejoramiento pm
        JOIN evaluaciones e ON e.id = pm.evaluacion_id
        JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
        JOIN competencias c ON c.id = ra.competencia_id
        JOIN fichas f ON f.id = pm.ficha_id
        JOIN aprendices ap ON ap.id = pm.aprendiz_id
        JOIN usuarios u_ap ON u_ap.id = ap.usuario_id
        JOIN usuarios u_inst ON u_inst.id = pm.instructor_id";

    /** Visibilidad por rol. Espera los alias e, c, f, ap. @return array{0:string, 1:array} */
    private function alcance(Actor $actor, bool $conResponsable): array {
        if ($actor->esAprendiz()) {
            return [" AND ap.usuario_id = ?", [$actor->id]];
        }
        if ($actor->esInstructor()) {
            $cond = "(" . InstructorAccessService::sqlCondicionAcceso() . ")";
            $p = [$actor->id, $actor->id, $actor->id];
            if ($conResponsable) {
                $cond = "(pm.instructor_id = ? OR $cond)";
                array_unshift($p, $actor->id);
            }
            return [" AND $cond", $p];
        }
        return $actor->esCoordinador() ? ['', []] : [' AND 1 = 0', []];
    }

    /** @return array{0:string, 1:array} */
    private function construirConsulta(Actor $actor, array $f): array {
        [$sql, $p] = $this->alcance($actor, true);
        $sql = self::DESDE . " WHERE 1=1" . $sql;
        $estado = $f['estado'] ?? '';
        if ($estado === 'vigente') {
            $sql .= " AND pm.estado IN ('abierto','en_curso')";
        } elseif ($estado === 'vencido') {
            $sql .= " AND pm.estado IN ('abierto','en_curso') AND pm.fecha_limite < CURDATE()";
        } elseif ($estado !== '') {
            $sql .= " AND pm.estado = ?";
            $p[] = in_array($estado, self::FILTROS, true) ? $estado : "\x00";
        }
        if (!empty($f['ficha_id'])) {
            $sql .= " AND pm.ficha_id = ?";
            $p[] = (int)$f['ficha_id'];
        }
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $sql .= " AND (u_ap.nombre LIKE ? OR ap.numero_documento LIKE ? OR ra.codigo LIKE ? OR ra.denominacion LIKE ?)";
            array_push($p, $t, $t, $t, $t);
        }
        return [$sql, $p];
    }

    private const COLUMNAS = "
        pm.id, pm.evaluacion_id, pm.aprendiz_id, pm.ficha_id, pm.instructor_id, pm.actividades, pm.fecha_inicio, pm.fecha_limite,
        pm.estado, pm.observaciones_cierre, pm.fecha_cierre, pm.fecha_creacion,
        (pm.estado IN ('abierto','en_curso') AND pm.fecha_limite < CURDATE()) AS vencido,
        DATEDIFF(pm.fecha_limite, CURDATE()) AS dias_restantes,
        ra.codigo AS ra_codigo, ra.denominacion AS ra_denominacion, c.codigo AS competencia_codigo, e.concepto,
        f.numero_ficha, u_ap.nombre AS aprendiz_nombre, ap.numero_documento, u_inst.nombre AS instructor_nombre";

    public function contar(Actor $actor, array $filtros): int {
        [$desde, $p] = $this->construirConsulta($actor, $filtros);
        $st = $this->db->prepare("SELECT COUNT(*) $desde");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    public function listar(Actor $actor, array $filtros, int $limite, int $offset): array {
        [$desde, $p] = $this->construirConsulta($actor, $filtros);
        $limite = max(1, min($limite, 100));
        $offset = max(0, $offset);
        // Primero lo vigente y, dentro, lo que vence antes.
        $st = $this->db->prepare("SELECT " . self::COLUMNAS . " $desde
            ORDER BY pm.estado IN ('abierto','en_curso') DESC, pm.fecha_limite, pm.id DESC LIMIT $limite OFFSET $offset");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paraExportar(Actor $actor, array $filtros, int $maximo): array {
        [$desde, $p] = $this->construirConsulta($actor, $filtros);
        $st = $this->db->prepare("SELECT " . self::COLUMNAS . " $desde ORDER BY f.numero_ficha, u_ap.nombre, pm.fecha_limite LIMIT " . max(1, $maximo));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales por situación, para las cifras de la pantalla. */
    public function cifras(Actor $actor): array {
        [$sql, $p] = $this->alcance($actor, true);
        $st = $this->db->prepare("SELECT
                COALESCE(SUM(pm.estado IN ('abierto','en_curso')), 0) AS vigentes,
                COALESCE(SUM(pm.estado IN ('abierto','en_curso') AND pm.fecha_limite < CURDATE()), 0) AS vencidos,
                COALESCE(SUM(pm.estado = 'cumplido'), 0) AS cumplidos,
                COALESCE(SUM(pm.estado = 'no_cumplido'), 0) AS no_cumplidos
            " . self::DESDE . " WHERE 1=1" . $sql);
        $st->execute($p);
        $r = array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);
        $r['sin_plan'] = $this->contarSinPlan($actor);
        return $r;
    }

    // -----------------------------------------------------------------
    // RAP EN D SIN PLAN VIGENTE
    // -----------------------------------------------------------------

    private function desdeSinPlan(Actor $actor, int $fichaId, string $busqueda = ''): array {
        [$sql, $p] = $this->alcance($actor, false);
        $sql = "
            FROM evaluaciones e
            JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
            JOIN competencias c ON c.id = ra.competencia_id
            JOIN fichas f ON f.id = e.ficha_id
            JOIN aprendices ap ON ap.id = e.aprendiz_id
            JOIN usuarios u_ap ON u_ap.id = ap.usuario_id
            WHERE e.concepto = 'D' AND ap.estado NOT IN ('desertado','egresado')
              AND NOT EXISTS (SELECT 1 FROM planes_mejoramiento pm WHERE pm.evaluacion_id = e.id AND pm.estado IN ('abierto','en_curso'))" . $sql;
        if ($fichaId > 0) {
            $sql .= " AND e.ficha_id = ?";
            $p[] = $fichaId;
        }
        if ($busqueda !== '') {
            $t = '%' . Validador::escaparLike($busqueda) . '%';
            $sql .= " AND (u_ap.nombre LIKE ? OR ap.numero_documento LIKE ? OR ra.codigo LIKE ?)";
            array_push($p, $t, $t, $t);
        }
        return [$sql, $p];
    }

    public function contarSinPlan(Actor $actor, int $fichaId = 0, string $busqueda = ''): int {
        [$desde, $p] = $this->desdeSinPlan($actor, $fichaId, $busqueda);
        $st = $this->db->prepare("SELECT COUNT(*) $desde");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    public function sinPlan(Actor $actor, int $fichaId = 0, int $limite = 30, string $busqueda = ''): array {
        [$desde, $p] = $this->desdeSinPlan($actor, $fichaId, $busqueda);
        $st = $this->db->prepare("SELECT e.id AS evaluacion_id, e.fecha_evaluacion, e.comentario, ra.codigo AS ra_codigo,
                   ra.denominacion AS ra_denominacion, f.numero_ficha, u_ap.nombre AS aprendiz_nombre, ap.numero_documento
            $desde ORDER BY e.fecha_evaluacion, f.numero_ficha, u_ap.nombre LIMIT " . max(1, min($limite, 100)));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // ESCRITURA Y APOYO A LAS REGLAS
    // -----------------------------------------------------------------

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT pm.*, ra.codigo AS ra_codigo, ap.usuario_id AS aprendiz_usuario_id, e.concepto
              FROM planes_mejoramiento pm
              JOIN evaluaciones e ON e.id = pm.evaluacion_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN aprendices ap ON ap.id = pm.aprendiz_id
             WHERE pm.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Evaluación con lo necesario para abrirle un plan. */
    public function evaluacion(int $id): ?array {
        $st = $this->db->prepare("SELECT e.id, e.concepto, e.aprendiz_id, e.ficha_id, e.instructor_id, ra.codigo AS ra_codigo,
                   ap.estado AS aprendiz_estado, ap.usuario_id AS aprendiz_usuario_id
              FROM evaluaciones e
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN aprendices ap ON ap.id = e.aprendiz_id
             WHERE e.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function vigenteDe(int $evaluacionId): ?array {
        $st = $this->db->prepare("SELECT * FROM planes_mejoramiento WHERE evaluacion_id = ? AND estado IN ('abierto','en_curso') LIMIT 1");
        $st->execute([$evaluacionId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): int {
        $this->db->prepare("
            INSERT INTO planes_mejoramiento (evaluacion_id, aprendiz_id, ficha_id, instructor_id, actividades, fecha_inicio, fecha_limite, estado, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'abierto', ?)
        ")->execute([$d['evaluacion_id'], $d['aprendiz_id'], $d['ficha_id'], $d['instructor_id'], $d['actividades'],
                     $d['fecha_inicio'], $d['fecha_limite'], $d['creado_por']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, string $actividades, string $fechaLimite): void {
        $this->db->prepare("UPDATE planes_mejoramiento SET actividades = ?, fecha_limite = ? WHERE id = ?")->execute([$actividades, $fechaLimite, $id]);
    }

    public function cerrar(int $id, string $estado, string $observaciones, int $usuarioId): void {
        $this->db->prepare("UPDATE planes_mejoramiento SET estado = ?, observaciones_cierre = ?, fecha_cierre = NOW(), cerrado_por = ? WHERE id = ?")
                 ->execute([$estado, $observaciones, $usuarioId, $id]);
    }

    /** El aprendiz entregó evidencia del RAP: el plan abierto pasa a en curso. */
    public function marcarEnCurso(int $evaluacionId): int {
        $st = $this->db->prepare("UPDATE planes_mejoramiento SET estado = 'en_curso' WHERE evaluacion_id = ? AND estado = 'abierto'");
        $st->execute([$evaluacionId]);
        return $st->rowCount();
    }

    public function fichasDelActor(Actor $actor): array {
        return (new EvaluacionesModel($this->db))->fichasDelActor($actor);
    }
}
