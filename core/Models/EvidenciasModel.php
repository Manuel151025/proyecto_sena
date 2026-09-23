<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Validador;
use PDO;

/**
 * Evidencias que envían los aprendices, acotadas por rol.
 *
 * Visibilidad (la misma que el permiso de revisarlas):
 *  - aprendiz: las suyas;
 *  - instructor: si la evidencia está ligada a un RAP, las de los RAP que
 *    califica; si no, las de los aprendices con los que tiene relación
 *    (líder de su ficha, asignación en ella o seguimiento);
 *  - coordinación: todas.
 */
class EvidenciasModel {
    public const ESTADOS = ['enviada', 'revisada', 'aprobada', 'rechazada'];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array{0:string, 1:array} */
    private function construirConsulta(Actor $actor, array $f): array {
        $sql = "
            FROM evidencias ev
            JOIN aprendices ap ON ap.id = ev.aprendiz_id
            JOIN usuarios u_ap ON u_ap.id = ap.usuario_id
            JOIN fichas f ON f.id = ev.ficha_id
            LEFT JOIN evaluaciones e ON e.id = ev.evaluacion_id
            LEFT JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
            LEFT JOIN competencias c ON c.id = ra.competencia_id
            WHERE 1=1";
        $p = [];
        if ($actor->esAprendiz()) {
            $sql .= " AND ap.usuario_id = ?";
            $p[] = $actor->id;
        } elseif ($actor->esInstructor()) {
            $sql .= " AND ((ev.evaluacion_id IS NOT NULL AND (" . InstructorAccessService::sqlCondicionAcceso() . "))
                        OR (ev.evaluacion_id IS NULL AND (f.instructor_id = ? OR ap.instructor_seguimiento_id = ?
                            OR EXISTS (SELECT 1 FROM asignaciones asg2 WHERE asg2.ficha_id = f.id AND asg2.instructor_id = ?))))";
            array_push($p, $actor->id, $actor->id, $actor->id, $actor->id, $actor->id, $actor->id);
        } elseif (!$actor->esCoordinador()) {
            $sql .= " AND 1 = 0";
        }
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $sql .= " AND (ev.titulo LIKE ? OR u_ap.nombre LIKE ? OR ap.numero_documento LIKE ? OR ra.codigo LIKE ?)";
            array_push($p, $t, $t, $t, $t);
        }
        if (($f['estado'] ?? '') !== '') {
            $sql .= " AND ev.estado = ?";
            $p[] = in_array($f['estado'], self::ESTADOS, true) ? $f['estado'] : "\x00";
        }
        if (!empty($f['ficha_id'])) {
            $sql .= " AND ev.ficha_id = ?";
            $p[] = (int)$f['ficha_id'];
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
            SELECT ev.id, ev.titulo, ev.descripcion, ev.archivo_url, ev.tipo_archivo, ev.`tamaño_kb` AS tamano_kb, ev.estado,
                   ev.retroalimentacion, ev.fecha_envio, ev.fecha_revision, ev.evaluacion_id, ev.aprendiz_id,
                   f.numero_ficha, u_ap.nombre AS aprendiz_nombre, ap.numero_documento, ap.usuario_id AS aprendiz_usuario_id,
                   ra.codigo AS ra_codigo, ra.denominacion AS ra_denominacion, e.concepto
            $desde
            ORDER BY ev.estado = 'enviada' DESC, ev.fecha_envio DESC, ev.id DESC
            LIMIT $limite OFFSET $offset");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Evidencias por revisar entre las visibles (para el aviso de la pantalla). */
    public function porRevisar(Actor $actor): int {
        return $this->contar($actor, ['estado' => 'enviada']);
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("
            SELECT ev.*, ev.`tamaño_kb` AS tamano_kb, ap.usuario_id AS aprendiz_usuario_id, ap.ficha_id AS ficha_aprendiz,
                   f.numero_ficha, ra.codigo AS ra_codigo, e.concepto
              FROM evidencias ev
              JOIN aprendices ap ON ap.id = ev.aprendiz_id
              JOIN fichas f ON f.id = ev.ficha_id
              LEFT JOIN evaluaciones e ON e.id = ev.evaluacion_id
              LEFT JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
             WHERE ev.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): int {
        $this->db->prepare("
            INSERT INTO evidencias (aprendiz_id, ficha_id, evaluacion_id, titulo, descripcion, archivo_url, tipo_archivo, `tamaño_kb`, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'enviada')
        ")->execute([$d['aprendiz_id'], $d['ficha_id'], $d['evaluacion_id'], $d['titulo'], $d['descripcion'] !== '' ? $d['descripcion'] : null,
                     $d['archivo_url'], $d['tipo_archivo'], $d['tamano_kb']]);
        return (int)$this->db->lastInsertId();
    }

    public function revisar(int $id, string $estado, string $retroalimentacion): void {
        $this->db->prepare("UPDATE evidencias SET estado = ?, retroalimentacion = ?, fecha_revision = CURRENT_DATE WHERE id = ?")
                 ->execute([$estado, $retroalimentacion, $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM evidencias WHERE id = ?")->execute([$id]);
    }

    public function registrarRetroalimentacion(?int $evaluacionId, int $aprendizId, int $instructorId, string $tipo, string $contenido): void {
        $this->db->prepare("INSERT INTO retroalimentacion (evaluacion_id, aprendiz_id, instructor_id, tipo, contenido) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$evaluacionId, $aprendizId, $instructorId, $tipo, $contenido]);
    }

    /** Aprendiz del usuario, con su ficha y estado. */
    public function aprendizDeUsuario(int $usuarioId): ?array {
        $st = $this->db->prepare("SELECT id, ficha_id, estado, instructor_seguimiento_id FROM aprendices WHERE usuario_id = ?");
        $st->execute([$usuarioId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** RAP del aprendiz para ligar la evidencia: primero lo pendiente y lo no aprobado. */
    public function rapsDelAprendiz(int $aprendizId): array {
        $st = $this->db->prepare("
            SELECT e.id, e.concepto, ra.codigo, ra.denominacion
              FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
             WHERE e.aprendiz_id = ?
             ORDER BY FIELD(e.concepto, 'D', 'pendiente', 'A'), ra.codigo");
        $st->execute([$aprendizId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function evaluacionDelAprendiz(int $evaluacionId, int $aprendizId): bool {
        $st = $this->db->prepare("SELECT 1 FROM evaluaciones WHERE id = ? AND aprendiz_id = ?");
        $st->execute([$evaluacionId, $aprendizId]);
        return (bool)$st->fetchColumn();
    }

    /** A quién avisar de una evidencia nueva: el responsable del RAP o, sin RAP, el líder y el de seguimiento. */
    public function instructoresAAvisar(int $aprendizId, ?int $evaluacionId): array {
        if ($evaluacionId) {
            $st = $this->db->prepare("SELECT instructor_id FROM evaluaciones WHERE id = ?");
            $st->execute([$evaluacionId]);
            return array_filter([(int)$st->fetchColumn()]);
        }
        $st = $this->db->prepare("SELECT f.instructor_id, ap.instructor_seguimiento_id FROM aprendices ap JOIN fichas f ON f.id = ap.ficha_id WHERE ap.id = ?");
        $st->execute([$aprendizId]);
        $r = $st->fetch(PDO::FETCH_NUM) ?: [];
        return array_values(array_unique(array_filter(array_map('intval', $r))));
    }

    /** Fichas para el filtro. */
    public function fichasDelActor(Actor $actor): array {
        if ($actor->esAprendiz()) {
            return [];
        }
        $sql = "SELECT id, numero_ficha FROM fichas";
        $p = [];
        if ($actor->esInstructor()) {
            $sql .= " WHERE id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            $p = [$actor->id, $actor->id, $actor->id];
        }
        $st = $this->db->prepare($sql . " ORDER BY numero_ficha");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
