<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use Core\Support\Validador;
use PDO;

/**
 * Asignación de instructores por competencia dentro de una ficha.
 *
 * Es la tabla de la que depende el acceso fino del instructor
 * (InstructorAccessService): quien tiene la competencia asignada es quien
 * la califica en esa ficha, por encima del líder.
 */
class AsignacionesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM asignaciones WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function checkAsignacionExiste(int $fichaId, int $competenciaId): bool {
        $st = $this->db->prepare("SELECT 1 FROM asignaciones WHERE ficha_id = ? AND competencia_id = ?");
        $st->execute([$fichaId, $competenciaId]);
        return (bool)$st->fetchColumn();
    }

    public function crear(int $fichaId, int $competenciaId, int $instructorId): int {
        $this->db->prepare("INSERT INTO asignaciones (ficha_id, competencia_id, instructor_id) VALUES (?, ?, ?)")
                 ->execute([$fichaId, $competenciaId, $instructorId]);
        return (int)$this->db->lastInsertId();
    }

    public function cambiarInstructor(int $id, int $instructorId): void {
        $this->db->prepare("UPDATE asignaciones SET instructor_id = ?, fecha_asignacion = NOW() WHERE id = ?")->execute([$instructorId, $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM asignaciones WHERE id = ?")->execute([$id]);
    }

    public function listar(Actor $actor, string $busqueda, int $fichaId, int $instructorId): array {
        $sql = "
            SELECT a.id, a.ficha_id, a.competencia_id, a.instructor_id, a.fecha_asignacion,
                   f.numero_ficha, p.nombre AS programa_nombre,
                   c.codigo AS competencia_codigo, c.nombre AS competencia_nombre,
                   u.nombre AS instructor_nombre, u.email AS instructor_email, u.avatar_color,
                   (SELECT COUNT(*) FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
                     WHERE e.ficha_id = a.ficha_id AND ra.competencia_id = a.competencia_id AND e.concepto = 'pendiente') AS pendientes
              FROM asignaciones a
              JOIN fichas f ON f.id = a.ficha_id
              JOIN programas p ON p.id = f.programa_id
              JOIN competencias c ON c.id = a.competencia_id
              JOIN usuarios u ON u.id = a.instructor_id
             WHERE 1=1";
        $params = [];
        if ($actor->esInstructor()) {
            // El instructor ve las asignaciones de sus fichas (para saber
            // quién lleva cada competencia), no las de todo el centro.
            $sql .= " AND a.ficha_id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")";
            array_push($params, $actor->id, $actor->id, $actor->id);
        } elseif (!$actor->esCoordinador()) {
            $sql .= " AND 1 = 0";
        }
        if ($busqueda !== '') {
            $t = '%' . Validador::escaparLike($busqueda) . '%';
            $sql .= " AND (u.nombre LIKE ? OR c.nombre LIKE ? OR c.codigo LIKE ? OR f.numero_ficha LIKE ?)";
            array_push($params, $t, $t, $t, $t);
        }
        if ($fichaId > 0) {
            $sql .= " AND a.ficha_id = ?";
            $params[] = $fichaId;
        }
        if ($instructorId > 0) {
            $sql .= " AND a.instructor_id = ?";
            $params[] = $instructorId;
        }
        $st = $this->db->prepare($sql . " ORDER BY f.numero_ficha, c.codigo LIMIT 500");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Contexto para validar: programa de la ficha, programa y tipo de la competencia. */
    public function contexto(int $fichaId, int $competenciaId): ?array {
        $st = $this->db->prepare("
            SELECT f.numero_ficha, f.programa_id AS programa_ficha, c.programa_id AS programa_competencia,
                   c.codigo, c.es_etapa_practica
              FROM fichas f, competencias c WHERE f.id = ? AND c.id = ?
        ");
        $st->execute([$fichaId, $competenciaId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFichas(): array {
        return $this->db->query("SELECT f.id, f.numero_ficha, f.programa_id, p.codigo AS programa_codigo
                                   FROM fichas f JOIN programas p ON p.id = f.programa_id ORDER BY f.numero_ficha")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCompetencias(): array {
        return $this->db->query("SELECT c.id, c.codigo, c.nombre, c.programa_id, c.es_etapa_practica, p.codigo AS programa_codigo
                                   FROM competencias c JOIN programas p ON p.id = c.programa_id
                                  WHERE c.estado = 'activo' ORDER BY p.codigo, c.codigo")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInstructores(): array {
        return $this->db->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function esInstructorActivo(int $id): bool {
        $st = $this->db->prepare("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'instructor' AND estado = 'activo'");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }
}
