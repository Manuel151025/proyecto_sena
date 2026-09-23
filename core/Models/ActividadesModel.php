<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Validador;
use PDO;

/**
 * Actividades de aprendizaje del proyecto formativo, por ficha y fase.
 *
 * El listado y su conteo comparten `construirConsulta()`, para que el
 * total no pueda desalinearse de las filas (ver Paginator).
 */
class ActividadesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM actividades WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): int {
        $this->db->prepare("
            INSERT INTO actividades (ficha_id, fase_id, competencia_id, nombre, descripcion, fecha_inicio, fecha_fin,
                                     responsable_id, estado, cumplimiento_porcentaje)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$d['ficha_id'], $d['fase_id'], $d['competencia_id'], $d['nombre'], $d['descripcion'],
                     $d['fecha_inicio'], $d['fecha_fin'], $d['responsable_id'], $d['estado'], $d['cumplimiento_porcentaje']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("
            UPDATE actividades
               SET ficha_id = ?, fase_id = ?, competencia_id = ?, nombre = ?, descripcion = ?, fecha_inicio = ?,
                   fecha_fin = ?, responsable_id = ?, estado = ?, cumplimiento_porcentaje = ?
             WHERE id = ?
        ")->execute([$d['ficha_id'], $d['fase_id'], $d['competencia_id'], $d['nombre'], $d['descripcion'],
                     $d['fecha_inicio'], $d['fecha_fin'], $d['responsable_id'], $d['estado'], $d['cumplimiento_porcentaje'], $id]);
    }

    public function actualizarAvance(int $id, string $estado, float $pct): void {
        $this->db->prepare("UPDATE actividades SET estado = ?, cumplimiento_porcentaje = ? WHERE id = ?")
                 ->execute([$estado, $pct, $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM actividades WHERE id = ?")->execute([$id]);
    }

    /** Datos de la ficha que condicionan una actividad: programa y proyecto. */
    public function contextoFicha(int $fichaId): ?array {
        $st = $this->db->prepare("SELECT id, numero_ficha, programa_id, proyecto_id FROM fichas WHERE id = ?");
        $st->execute([$fichaId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function faseDeProyecto(int $faseId, int $proyectoId): bool {
        $st = $this->db->prepare("SELECT 1 FROM fases_proyecto WHERE id = ? AND proyecto_id = ?");
        $st->execute([$faseId, $proyectoId]);
        return (bool)$st->fetchColumn();
    }

    public function competenciaDePrograma(int $competenciaId, int $programaId): bool {
        $st = $this->db->prepare("SELECT 1 FROM competencias WHERE id = ? AND programa_id = ?");
        $st->execute([$competenciaId, $programaId]);
        return (bool)$st->fetchColumn();
    }

    public function esInstructorActivo(int $usuarioId): bool {
        $st = $this->db->prepare("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'instructor' AND estado = 'activo'");
        $st->execute([$usuarioId]);
        return (bool)$st->fetchColumn();
    }

    // -----------------------------------------------------------------
    // LISTADO
    // -----------------------------------------------------------------

    /**
     * @param array{search?:string, ficha_id?:int, fase_id?:int, proyecto_id?:int, estado?:string} $f
     * @return array{0:string, 1:array}
     */
    private function construirConsulta(Actor $actor, array $f): array {
        $sql = "
              FROM actividades act
              JOIN fichas f ON f.id = act.ficha_id
              LEFT JOIN fases_proyecto fp ON fp.id = act.fase_id
              LEFT JOIN competencias comp ON comp.id = act.competencia_id
              LEFT JOIN usuarios u ON u.id = act.responsable_id
             WHERE 1 = 1";
        $p = [];

        if ($actor->esAprendiz()) {
            $sql .= " AND act.ficha_id IN (SELECT ficha_id FROM aprendices WHERE usuario_id = ? AND ficha_id IS NOT NULL)";
            $p[] = $actor->id;
        } elseif ($actor->esInstructor()) {
            $sql .= " AND act.ficha_id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            array_push($p, $actor->id, $actor->id, $actor->id);
        }
        if (!empty($f['ficha_id'])) {
            $sql .= " AND act.ficha_id = ?";
            $p[] = (int)$f['ficha_id'];
        }
        if (!empty($f['fase_id'])) {
            $sql .= " AND act.fase_id = ?";
            $p[] = (int)$f['fase_id'];
        }
        if (!empty($f['proyecto_id'])) {
            $sql .= " AND f.proyecto_id = ?";
            $p[] = (int)$f['proyecto_id'];
        }
        if (($f['estado'] ?? '') !== '') {
            $sql .= " AND act.estado = ?";
            $p[] = $f['estado'];
        }
        if (($f['search'] ?? '') !== '') {
            $sql .= " AND (act.nombre LIKE ? OR act.descripcion LIKE ?)";
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            array_push($p, $t, $t);
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
            SELECT act.*, f.numero_ficha, f.proyecto_id,
                   fp.numero_fase, fp.nombre AS fase_nombre,
                   comp.codigo AS comp_codigo, comp.nombre AS comp_nombre,
                   u.nombre AS responsable_nombre,
                   (act.fecha_fin IS NOT NULL AND act.fecha_fin < CURDATE()
                    AND act.estado IN ('pendiente','en_progreso')) AS vencida
            $desde
            ORDER BY act.estado = 'completada', act.estado = 'cancelada', act.fecha_fin IS NULL, act.fecha_fin, act.id DESC
            LIMIT $limite OFFSET $offset
        ");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // CATÁLOGOS PARA LOS FORMULARIOS
    // -----------------------------------------------------------------

    /** Fichas del actor con el programa y el proyecto de cada una. */
    public function fichasDelActor(Actor $actor): array {
        $sql = "SELECT f.id, f.numero_ficha, f.programa_id, f.proyecto_id FROM fichas f";
        $p = [];
        if ($actor->esInstructor()) {
            $sql .= " WHERE f.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            $p = [$actor->id, $actor->id, $actor->id];
        } elseif ($actor->esAprendiz()) {
            $sql .= " WHERE f.id IN (SELECT ficha_id FROM aprendices WHERE usuario_id = ?)";
            $p = [$actor->id];
        }
        $st = $this->db->prepare($sql . " ORDER BY f.numero_ficha");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function competenciasDeProgramas(array $programaIds): array {
        if ($programaIds === []) {
            return [];
        }
        $m = implode(',', array_fill(0, count($programaIds), '?'));
        $st = $this->db->prepare("SELECT id, codigo, nombre, programa_id FROM competencias
                                   WHERE estado = 'activo' AND programa_id IN ($m) ORDER BY codigo");
        $st->execute(array_values(array_map('intval', $programaIds)));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function instructoresActivos(): array {
        return $this->db->query("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }
}
