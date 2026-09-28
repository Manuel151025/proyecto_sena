<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Validador;
use PDO;

/**
 * Consultas de juicios evaluativos (tabla `evaluaciones`), acotadas por rol.
 *
 * La escritura no vive aquí: pasa por EvaluacionService (historial y
 * transacción) a través de JuiciosService (permisos).
 *
 * Visibilidad:
 *  - coordinación: todo;
 *  - instructor: los RAP que califica (misma condición que el permiso de
 *    calificar: InstructorAccessService::sqlCondicionAcceso);
 *  - aprendiz: los suyos.
 */
class EvaluacionesModel {
    public const CONCEPTOS = ['A', 'D', 'pendiente'];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array{0:string, 1:array} FROM + WHERE comunes al listado, el conteo y las cifras. */
    private function construirConsulta(Actor $actor, array $f, bool $conConcepto = true): array {
        $sql = "
            FROM evaluaciones e
            JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
            JOIN competencias c ON c.id = ra.competencia_id
            JOIN fichas f ON f.id = e.ficha_id
            JOIN aprendices ap ON ap.id = e.aprendiz_id
            JOIN usuarios u_ap ON u_ap.id = ap.usuario_id
            LEFT JOIN usuarios u_inst ON u_inst.id = e.instructor_id
            WHERE 1=1";
        $p = [];
        if ($actor->esAprendiz()) {
            $sql .= " AND ap.usuario_id = ?";
            $p[] = $actor->id;
        } elseif ($actor->esInstructor()) {
            $sql .= " AND (" . InstructorAccessService::sqlCondicionAcceso() . ")";
            array_push($p, $actor->id, $actor->id, $actor->id);
        } elseif (!$actor->esCoordinador()) {
            $sql .= " AND 1 = 0";
        }
        if (!empty($f['ficha_id'])) {
            $sql .= " AND e.ficha_id = ?";
            $p[] = (int)$f['ficha_id'];
        }
        if (!empty($f['competencia_id'])) {
            $sql .= " AND c.id = ?";
            $p[] = (int)$f['competencia_id'];
        }
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $sql .= " AND (u_ap.nombre LIKE ? OR ap.numero_documento LIKE ? OR ra.codigo LIKE ? OR ra.denominacion LIKE ?)";
            array_push($p, $t, $t, $t, $t);
        }
        if ($conConcepto && ($f['concepto'] ?? '') !== '') {
            $sql .= " AND e.concepto = ?";
            // Un valor fuera de la lista no debe devolver "todo".
            $p[] = in_array($f['concepto'], self::CONCEPTOS, true) ? $f['concepto'] : "\x00";
        }
        return [$sql, $p];
    }

    private const COLUMNAS = "
        e.id, e.concepto, e.comentario, e.fecha_evaluacion, e.ficha_id, e.aprendiz_id,
        ra.codigo AS ra_codigo, ra.denominacion AS ra_denominacion,
        c.codigo AS competencia_codigo, c.nombre AS competencia_nombre, c.es_etapa_practica,
        f.numero_ficha, ap.numero_documento, ap.estado AS aprendiz_estado,
        u_ap.nombre AS aprendiz_nombre, u_ap.email AS aprendiz_email,
        u_inst.nombre AS instructor_nombre";

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
        // Primero lo que falta por calificar y lo no aprobado: es lo que
        // alguien tiene que atender.
        $st = $this->db->prepare("SELECT " . self::COLUMNAS . " $desde
            ORDER BY FIELD(e.concepto, 'D', 'pendiente', 'A'), e.fecha_evaluacion DESC, f.numero_ficha, u_ap.nombre, ra.codigo
            LIMIT $limite OFFSET $offset");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paraExportar(Actor $actor, array $filtros, int $maximo): array {
        [$desde, $p] = $this->construirConsulta($actor, $filtros);
        $maximo = max(1, $maximo);
        $st = $this->db->prepare("SELECT " . self::COLUMNAS . " $desde ORDER BY f.numero_ficha, u_ap.nombre, ra.codigo LIMIT $maximo");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales por concepto con los mismos filtros (salvo el concepto). */
    public function cifras(Actor $actor, array $filtros): array {
        [$desde, $p] = $this->construirConsulta($actor, $filtros, false);
        $st = $this->db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(e.concepto = 'A'), 0) AS a,
                                         COALESCE(SUM(e.concepto = 'D'), 0) AS d, COALESCE(SUM(e.concepto = 'pendiente'), 0) AS pendientes $desde");
        $st->execute($p);
        return array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'a' => 0, 'd' => 0, 'pendientes' => 0]);
    }

    /**
     * Historial de cambios de varias evaluaciones en una consulta (RNF02).
     *
     * @param int[] $ids
     * @return array<int, list<array>> por id de evaluación, del más reciente al más antiguo
     */
    public function historial(array $ids, int $porEvaluacion = 10): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("
            SELECT h.evaluacion_id, h.concepto_anterior, h.concepto_nuevo, h.motivo, h.fecha_cambio, u.nombre AS usuario
              FROM historial_evaluaciones h LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.evaluacion_id IN ($marcas)
             ORDER BY h.fecha_cambio DESC, h.id DESC");
        $st->execute($ids);
        $r = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $id = (int)$h['evaluacion_id'];
            if (count($r[$id] ?? []) < $porEvaluacion) {
                $r[$id][] = $h;
            }
        }
        return $r;
    }

    /** Evaluación con lo necesario para decidir si se puede calificar. */
    public function paraCalificar(int $id): ?array {
        $st = $this->db->prepare("
            SELECT e.id, e.concepto, e.ficha_id, e.aprendiz_id, e.resultado_aprendizaje_id, ap.estado AS aprendiz_estado, ra.codigo AS ra_codigo
              FROM evaluaciones e
              JOIN aprendices ap ON ap.id = e.aprendiz_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
             WHERE e.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Fichas para el filtro, según el rol. */
    public function fichasDelActor(Actor $actor): array {
        if ($actor->esAprendiz()) {
            return [];
        }
        $sql = "SELECT f.id, f.numero_ficha FROM fichas f";
        $p = [];
        if ($actor->esInstructor()) {
            $sql .= " WHERE f.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            $p = [$actor->id, $actor->id, $actor->id];
        }
        $st = $this->db->prepare($sql . " ORDER BY f.numero_ficha");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
