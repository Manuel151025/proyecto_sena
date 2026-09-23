<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\Semaforo;
use Core\Support\Validador;
use PDO;

/**
 * Fichas de formación.
 *
 * Los indicadores se calculan al leer, nunca se guardan:
 *  - aprendices activos: matriculados que no desertaron;
 *  - cumplimiento: RAP en A sobre el total de RAP a evaluar de esos
 *    aprendices (el avance académico de la ficha);
 *  - avance del proyecto: promedio del avance de sus actividades.
 *
 * `fichas.cantidad_aprendices` y `fichas.cumplimiento_porcentaje` siguen en
 * el esquema pero ya no se leen: eran contadores con varios escritores que
 * nadie recalculaba, desviados en 5 de las 7 fichas.
 */
class FichaModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** Subconsultas de indicadores; esperan el alias `f` para la ficha. */
    private const INDICADORES = "
        (SELECT COUNT(*) FROM aprendices a WHERE a.ficha_id = f.id AND a.estado <> 'desertado') AS aprendices_activos,
        (SELECT COUNT(*) FROM evaluaciones e JOIN aprendices a ON a.id = e.aprendiz_id
          WHERE e.ficha_id = f.id AND a.estado <> 'desertado') AS total_evaluaciones,
        (SELECT COUNT(*) FROM evaluaciones e JOIN aprendices a ON a.id = e.aprendiz_id
          WHERE e.ficha_id = f.id AND a.estado <> 'desertado' AND e.concepto = 'A') AS aprobados,
        (SELECT COUNT(*) FROM evaluaciones e JOIN aprendices a ON a.id = e.aprendiz_id
          WHERE e.ficha_id = f.id AND a.estado <> 'desertado' AND e.concepto = 'D') AS en_d,
        (SELECT AVG(CASE WHEN act.estado = 'completada' THEN 100 ELSE act.cumplimiento_porcentaje END)
           FROM actividades act WHERE act.ficha_id = f.id AND act.estado <> 'cancelada') AS avance_proyecto,
        (SELECT COUNT(*) FROM planes_mejoramiento pm WHERE pm.ficha_id = f.id AND pm.estado IN ('abierto','en_curso')) AS planes_abiertos";

    /** @return array{0:string, 1:array} */
    private function construirFiltro(Actor $actor, array $f): array {
        $sql = " FROM fichas f
                 JOIN programas p ON p.id = f.programa_id
                 JOIN usuarios u ON u.id = f.instructor_id
                 LEFT JOIN proyectos pr ON pr.id = f.proyecto_id
                WHERE 1=1";
        $params = [];
        if ($actor->esInstructor()) {
            $sql .= " AND f.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            array_push($params, $actor->id, $actor->id, $actor->id);
        } elseif ($actor->esAprendiz()) {
            $sql .= " AND f.id IN (SELECT ficha_id FROM aprendices WHERE usuario_id = ?)";
            $params[] = $actor->id;
        }
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $sql .= " AND (f.numero_ficha LIKE ? OR p.nombre LIKE ? OR u.nombre LIKE ?)";
            array_push($params, $t, $t, $t);
        }
        if (!empty($f['programa_id'])) {
            $sql .= " AND f.programa_id = ?";
            $params[] = (int)$f['programa_id'];
        }
        if (($f['estado'] ?? '') !== '') {
            $sql .= " AND f.estado = ?";
            $params[] = in_array($f['estado'], Enums::FICHA_ESTADO, true) ? $f['estado'] : "\x00";
        }
        return [$sql, $params];
    }

    public function contar(Actor $actor, array $filtros = []): int {
        [$desde, $p] = $this->construirFiltro($actor, $filtros);
        $st = $this->db->prepare("SELECT COUNT(*) $desde");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    public function listar(Actor $actor, array $filtros, int $limite, int $offset): array {
        return $this->consultar($actor, $filtros, max(1, min($limite, 100)), max(0, $offset));
    }

    /** Listado completo con indicadores, para exportar (con tope). */
    public function paraExportar(Actor $actor, array $filtros, int $maximo): array {
        return $this->consultar($actor, $filtros, max(1, $maximo), 0);
    }

    private function consultar(Actor $actor, array $filtros, int $limite, int $offset): array {
        [$desde, $p] = $this->construirFiltro($actor, $filtros);
        $st = $this->db->prepare("
            SELECT f.id, f.numero_ficha, f.estado, f.fecha_inicio, f.fecha_fin, f.programa_id, f.proyecto_id, f.instructor_id,
                   p.nombre AS programa, p.codigo AS codigo_programa, u.nombre AS instructor,
                   pr.nombre AS proyecto_nombre, pr.codigo AS proyecto_codigo,
                   " . self::INDICADORES . "
            $desde
            ORDER BY FIELD(f.estado, 'ejecucion', 'induccion', 'planeacion', 'cierre'), f.numero_ficha
            LIMIT $limite OFFSET $offset
        ");
        $st->execute($p);
        return array_map([self::class, 'conPorcentajes'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Ficha con sus datos y sus indicadores. */
    public function detalle(int $id): ?array {
        $st = $this->db->prepare("
            SELECT f.*, p.nombre AS programa, p.codigo AS codigo_programa, u.nombre AS instructor,
                   pr.nombre AS proyecto_nombre, pr.codigo AS proyecto_codigo, pr.objetivo AS proyecto_objetivo,
                   " . self::INDICADORES . "
              FROM fichas f
              JOIN programas p ON p.id = f.programa_id
              JOIN usuarios u ON u.id = f.instructor_id
              LEFT JOIN proyectos pr ON pr.id = f.proyecto_id
             WHERE f.id = ?
        ");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? self::conPorcentajes($r) : null;
    }

    /**
     * Aprendices de la ficha con su situación académica y su semáforo.
     * Una sola consulta agrupada: antes el seguimiento traía cada juicio a
     * PHP para contarlos allí.
     */
    public function aprendicesConIndicadores(int $fichaId): array {
        $st = $this->db->prepare("
            SELECT a.id, a.numero_documento, a.tipo_documento, a.estado, a.usuario_id,
                   u.nombre, u.email, u.avatar_color, u2.nombre AS instructor_seguimiento_nombre,
                   SUM(e.concepto = 'A') AS aprobados, SUM(e.concepto = 'D') AS en_d, SUM(e.concepto = 'pendiente') AS pendientes,
                   COUNT(e.id) AS total,
                   (SELECT COUNT(*) FROM planes_mejoramiento pm WHERE pm.aprendiz_id = a.id AND pm.estado IN ('abierto','en_curso')) AS planes_abiertos
              FROM aprendices a
              JOIN usuarios u ON u.id = a.usuario_id
              LEFT JOIN usuarios u2 ON u2.id = a.instructor_seguimiento_id
              LEFT JOIN evaluaciones e ON e.aprendiz_id = a.id AND e.ficha_id = a.ficha_id
             WHERE a.ficha_id = ?
             GROUP BY a.id
             ORDER BY a.estado = 'desertado', u.nombre
        ");
        $st->execute([$fichaId]);
        return array_map(static function (array $r): array {
            $evaluados = (int)$r['aprobados'] + (int)$r['en_d'];
            $r['pct_a'] = $evaluados > 0 ? round((int)$r['aprobados'] * 100 / $evaluados, 1) : null;
            $r['avance'] = (int)$r['total'] > 0 ? round((int)$r['aprobados'] * 100 / (int)$r['total'], 1) : 0.0;
            $r['semaforo'] = $r['estado'] === 'desertado' ? Semaforo::SIN_DATOS : Semaforo::aprendiz($r['pct_a'], (int)$r['en_d']);
            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Dos indicadores distintos que antes se mezclaban:
     *  - cumplimiento: RAP en A sobre el total (avance de la formación).
     *    Una ficha que empieza tiene un avance bajo y eso es normal.
     *  - pct_a y semaforo: RAP en A sobre los ya evaluados (desempeño). Es
     *    lo que el semáforo clasifica; sin juicios no hay semáforo. Antes el
     *    semáforo se aplicaba al avance y marcaba «Crítico» a toda ficha
     *    recién iniciada aunque no tuviera un solo RAP en D.
     */
    private static function conPorcentajes(array $f): array {
        $total = (int)($f['total_evaluaciones'] ?? 0);
        $evaluados = (int)$f['aprobados'] + (int)$f['en_d'];
        $f['cumplimiento'] = $total > 0 ? round((int)$f['aprobados'] * 100 / $total, 1) : null;
        $f['pct_a'] = $evaluados > 0 ? round((int)$f['aprobados'] * 100 / $evaluados, 1) : null;
        $f['semaforo'] = Semaforo::porcentaje($f['pct_a']);
        $f['avance_proyecto'] = $f['avance_proyecto'] !== null ? round((float)$f['avance_proyecto'], 1) : null;
        return $f;
    }

    // -----------------------------------------------------------------
    // ESCRITURA
    // -----------------------------------------------------------------

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM fichas WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d, int $coordinadorId): int {
        $this->db->prepare("
            INSERT INTO fichas (numero_ficha, programa_id, proyecto_id, instructor_id, coordinador_id, estado, fecha_inicio, fecha_fin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$d['numero_ficha'], $d['programa_id'], $d['proyecto_id'], $d['instructor_id'], $coordinadorId,
                     $d['estado'], $d['fecha_inicio'], $d['fecha_fin']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("
            UPDATE fichas SET numero_ficha = ?, programa_id = ?, proyecto_id = ?, instructor_id = ?, estado = ?, fecha_inicio = ?, fecha_fin = ?
             WHERE id = ?
        ")->execute([$d['numero_ficha'], $d['programa_id'], $d['proyecto_id'], $d['instructor_id'], $d['estado'],
                     $d['fecha_inicio'], $d['fecha_fin'], $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM fichas WHERE id = ?")->execute([$id]);
    }

    /** @return array{aprendices:int, juicios:int, actividades:int, actividades_con_fase:int} */
    public function dependencias(int $id): array {
        $st = $this->db->prepare("
            SELECT (SELECT COUNT(*) FROM aprendices WHERE ficha_id = ?) AS aprendices,
                   (SELECT COUNT(*) FROM evaluaciones WHERE ficha_id = ? AND concepto IN ('A','D')) AS juicios,
                   (SELECT COUNT(*) FROM actividades WHERE ficha_id = ?) AS actividades,
                   (SELECT COUNT(*) FROM actividades WHERE ficha_id = ? AND fase_id IS NOT NULL) AS actividades_con_fase
        ");
        $st->execute([$id, $id, $id, $id]);
        return array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    public function esInstructorActivo(int $id): bool {
        $st = $this->db->prepare("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'instructor' AND estado = 'activo'");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    // -----------------------------------------------------------------
    // CATÁLOGOS Y CONSULTAS AUXILIARES
    // -----------------------------------------------------------------

    public function getAll(): array {
        return $this->db->query("SELECT f.id, f.numero_ficha, p.nombre AS programa FROM fichas f
                                  JOIN programas p ON p.id = f.programa_id ORDER BY f.numero_ficha")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Fichas del instructor por cualquiera de sus tres vías de autoridad. */
    public function getByInstructor(int $instructorId): array {
        $st = $this->db->prepare("SELECT f.id, f.numero_ficha, p.nombre AS programa FROM fichas f
                                   JOIN programas p ON p.id = f.programa_id
                                  WHERE f.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")
                                  ORDER BY f.numero_ficha");
        $st->execute([$instructorId, $instructorId, $instructorId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Fichas visibles para el actor, para selectores. */
    public function opciones(Actor $actor): array {
        if ($actor->esCoordinador()) {
            return $this->getAll();
        }
        if ($actor->esInstructor()) {
            return $this->getByInstructor($actor->id);
        }
        $id = $this->getFichaIdByUsuarioId($actor->id);
        return $id ? array_values(array_filter($this->getAll(), static fn($f) => (int)$f['id'] === $id)) : [];
    }

    public function getFichaIdByUsuarioId(int $usuarioId): ?int {
        $st = $this->db->prepare("SELECT ficha_id FROM aprendices WHERE usuario_id = ?");
        $st->execute([$usuarioId]);
        $v = $st->fetchColumn();
        return $v !== false && $v !== null ? (int)$v : null;
    }

    public function contarAprendices(int $fichaId): int {
        if ($fichaId <= 0) {
            return 0;
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM aprendices WHERE ficha_id = ? AND estado <> 'desertado'");
        $st->execute([$fichaId]);
        return (int)$st->fetchColumn();
    }

    public function getProgramasActivos(): array {
        return $this->db->query("SELECT id, codigo, nombre FROM programas WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInstructoresActivos(): array {
        return $this->db->query("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProyectosActivos(): array {
        return $this->db->query("SELECT id, nombre, codigo FROM proyectos WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
}
