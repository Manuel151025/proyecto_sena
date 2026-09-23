<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\Validador;
use PDO;

/**
 * Retroalimentación y observaciones de seguimiento (tabla `retroalimentacion`).
 *
 * Visibilidad:
 *  - aprendiz: la suya que no sea privada (las privadas son notas internas
 *    del equipo de formación);
 *  - instructor: la de los aprendices con los que tiene relación (líder,
 *    asignación en su ficha o seguimiento), escrita por él o por colegas;
 *    antes solo veía lo que él mismo había escrito;
 *  - coordinación: toda.
 */
class RetroalimentacionModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** Condición «el instructor ? tiene relación con el aprendiz ap de la ficha f» (3 parámetros). */
    public const RELACION_INSTRUCTOR = "(f.instructor_id = ? OR ap.instructor_seguimiento_id = ?
        OR EXISTS (SELECT 1 FROM asignaciones asg_r WHERE asg_r.ficha_id = f.id AND asg_r.instructor_id = ?))";

    /** @return array{0:string, 1:array} */
    private function construirConsulta(Actor $actor, array $f): array {
        $sql = "
            FROM retroalimentacion r
            JOIN aprendices ap ON ap.id = r.aprendiz_id
            JOIN usuarios u_ap ON u_ap.id = ap.usuario_id
            JOIN fichas f ON f.id = ap.ficha_id
            JOIN usuarios u_inst ON u_inst.id = r.instructor_id
            LEFT JOIN evaluaciones e ON e.id = r.evaluacion_id
            LEFT JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
            WHERE 1=1";
        $p = [];
        if ($actor->esAprendiz()) {
            $sql .= " AND ap.usuario_id = ? AND r.privada = 0";
            $p[] = $actor->id;
        } elseif ($actor->esInstructor()) {
            $sql .= " AND (r.instructor_id = ? OR " . self::RELACION_INSTRUCTOR . ")";
            array_push($p, $actor->id, $actor->id, $actor->id, $actor->id);
        } elseif (!$actor->esCoordinador()) {
            $sql .= " AND 1 = 0";
        }
        if (($f['tipo'] ?? '') !== '') {
            $sql .= " AND r.tipo = ?";
            $p[] = in_array($f['tipo'], Enums::RETROALIMENTACION_TIPO, true) ? $f['tipo'] : "\x00";
        }
        if (!empty($f['ficha_id'])) {
            $sql .= " AND ap.ficha_id = ?";
            $p[] = (int)$f['ficha_id'];
        }
        if (!empty($f['aprendiz_id'])) {
            $sql .= " AND r.aprendiz_id = ?";
            $p[] = (int)$f['aprendiz_id'];
        }
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $sql .= " AND (u_ap.nombre LIKE ? OR ap.numero_documento LIKE ? OR r.contenido LIKE ? OR ra.codigo LIKE ?)";
            array_push($p, $t, $t, $t, $t);
        }
        return [$sql, $p];
    }

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
        $st = $this->db->prepare("
            SELECT r.id, r.tipo, r.contenido, r.privada, r.fecha_creacion, r.aprendiz_id, r.evaluacion_id,
                   u_ap.nombre AS aprendiz_nombre, f.numero_ficha, u_inst.nombre AS instructor_nombre, u_inst.avatar_color,
                   ra.codigo AS ra_codigo
            $desde ORDER BY r.fecha_creacion DESC, r.id DESC LIMIT $limite OFFSET $offset");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Aprendices a los que el actor puede escribir. */
    public function aprendicesDisponibles(Actor $actor): array {
        $sql = "SELECT ap.id, u.nombre, f.numero_ficha, ap.numero_documento
                  FROM aprendices ap JOIN usuarios u ON u.id = ap.usuario_id JOIN fichas f ON f.id = ap.ficha_id
                 WHERE ap.estado NOT IN ('desertado','egresado')";
        $p = [];
        if ($actor->esInstructor()) {
            $sql .= " AND " . self::RELACION_INSTRUCTOR;
            $p = [$actor->id, $actor->id, $actor->id];
        } elseif (!$actor->esCoordinador()) {
            return [];
        }
        $st = $this->db->prepare($sql . " ORDER BY f.numero_ficha, u.nombre");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $d, int $instructorId): int {
        $this->db->prepare("INSERT INTO retroalimentacion (evaluacion_id, aprendiz_id, instructor_id, tipo, contenido, privada) VALUES (?, ?, ?, ?, ?, ?)")
                 ->execute([$d['evaluacion_id'], $d['aprendiz_id'], $instructorId, $d['tipo'], $d['contenido'], $d['privada'] ? 1 : 0]);
        return (int)$this->db->lastInsertId();
    }

    /** Aprendiz con su usuario y estado. */
    public function aprendiz(int $id): ?array {
        $st = $this->db->prepare("SELECT ap.id, ap.usuario_id, ap.estado, u.nombre FROM aprendices ap JOIN usuarios u ON u.id = ap.usuario_id WHERE ap.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function evaluacionDelAprendiz(int $evaluacionId, int $aprendizId): bool {
        $st = $this->db->prepare("SELECT 1 FROM evaluaciones WHERE id = ? AND aprendiz_id = ?");
        $st->execute([$evaluacionId, $aprendizId]);
        return (bool)$st->fetchColumn();
    }

    public function fichasDelActor(Actor $actor): array {
        return (new EvaluacionesModel($this->db))->fichasDelActor($actor);
    }
}
