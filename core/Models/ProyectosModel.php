<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use PDO;

/**
 * Proyectos formativos.
 *
 * Un proyecto es la plantilla: sus fases valen para todas las fichas que
 * lo desarrollan. Lo que cambia de una ficha a otra son las actividades, y
 * por eso el avance se calcula a partir de ellas y dentro del alcance de
 * quien consulta: el aprendiz ve el avance de SU ficha, el instructor el de
 * las suyas y el coordinador el de todas.
 *
 * Antes el avance era el promedio de `fases_proyecto.cumplimiento_porcentaje`,
 * un número tecleado a mano en el formulario de fase, igual para todas las
 * fichas y sin relación con lo que de verdad se había ejecutado.
 */
class ProyectosModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function crear(array $d): int {
        $this->db->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, descripcion, estado) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$d['nombre'], $d['codigo'], $d['objetivo'], $d['descripcion'], $d['estado']]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $d): bool {
        $st = $this->db->prepare("UPDATE proyectos SET nombre = ?, codigo = ?, objetivo = ?, descripcion = ?, estado = ? WHERE id = ?");
        $st->execute([$d['nombre'], $d['codigo'], $d['objetivo'], $d['descripcion'], $d['estado'], $id]);
        return $st->rowCount() > 0 || $this->existe($id);
    }

    public function eliminar(int $id): bool {
        $st = $this->db->prepare("DELETE FROM proyectos WHERE id = ?");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    public function existe(int $id): bool {
        $st = $this->db->prepare("SELECT 1 FROM proyectos WHERE id = ?");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM proyectos WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Fichas que impiden borrar el proyecto. */
    public function contarFichas(int $id): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM fichas WHERE proyecto_id = ?");
        $st->execute([$id]);
        return (int)$st->fetchColumn();
    }

    /**
     * Condición y parámetros que limitan las fichas al alcance del actor.
     *
     * @return array{0:string, 1:array}
     */
    private function alcanceFichas(Actor $actor, string $alias = 'f'): array {
        if ($actor->esCoordinador()) {
            return ['1=1', []];
        }
        if ($actor->esInstructor()) {
            return ["$alias.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")",
                    [$actor->id, $actor->id, $actor->id]];
        }
        return ["$alias.id IN (SELECT ficha_id FROM aprendices WHERE usuario_id = ? AND ficha_id IS NOT NULL)", [$actor->id]];
    }

    /**
     * Proyectos visibles para el actor, con sus cifras dentro de su alcance.
     *
     * El coordinador ve también los proyectos sin fichas (para poder
     * asignarlos); instructor y aprendiz, solo los de sus fichas.
     */
    public function listar(Actor $actor): array {
        [$cond, $params] = $this->alcanceFichas($actor);

        $filtroProyecto = $actor->esCoordinador()
            ? ''
            : "WHERE pr.id IN (SELECT f.proyecto_id FROM fichas f WHERE f.proyecto_id IS NOT NULL AND $cond)";

        // Las subconsultas repiten la condición de alcance: cada cifra se
        // calcula solo con las fichas que el actor puede ver.
        $sql = "
            SELECT pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.descripcion, pr.estado,
                   (SELECT COUNT(*) FROM fichas f WHERE f.proyecto_id = pr.id AND $cond) AS total_fichas,
                   (SELECT COUNT(*) FROM aprendices ap JOIN fichas f ON f.id = ap.ficha_id
                     WHERE f.proyecto_id = pr.id AND ap.estado <> 'desertado' AND $cond) AS total_aprendices,
                   (SELECT COUNT(*) FROM fases_proyecto fp WHERE fp.proyecto_id = pr.id) AS total_fases,
                   (SELECT COUNT(*) FROM fases_proyecto fp WHERE fp.proyecto_id = pr.id AND fp.estado = 'completada') AS fases_completadas,
                   (SELECT COUNT(*) FROM actividades a JOIN fichas f ON f.id = a.ficha_id
                     WHERE f.proyecto_id = pr.id AND a.estado <> 'cancelada' AND $cond) AS total_actividades,
                   (SELECT AVG(CASE WHEN a.estado = 'completada' THEN 100 ELSE a.cumplimiento_porcentaje END)
                      FROM actividades a JOIN fichas f ON f.id = a.ficha_id
                     WHERE f.proyecto_id = pr.id AND a.estado <> 'cancelada' AND $cond) AS avance
              FROM proyectos pr
              $filtroProyecto
             ORDER BY pr.estado = 'activo' DESC, pr.nombre
        ";
        // La condición aparece cuatro veces en el SELECT y, para quien no es
        // coordinador, una más en el WHERE.
        $todos = array_merge($params, $params, $params, $params, $actor->esCoordinador() ? [] : $params);

        $st = $this->db->prepare($sql);
        $st->execute($todos);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista corta para selectores. */
    public function opciones(Actor $actor): array {
        if ($actor->esCoordinador()) {
            return $this->db->query("SELECT id, codigo, nombre FROM proyectos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        }
        return array_map(static fn($p) => ['id' => $p['id'], 'codigo' => $p['codigo'], 'nombre' => $p['nombre']], $this->listar($actor));
    }

    /** Fichas que desarrollan el proyecto, dentro del alcance del actor. */
    public function fichasDelProyecto(int $proyectoId, Actor $actor): array {
        [$cond, $params] = $this->alcanceFichas($actor);
        $st = $this->db->prepare("SELECT f.id, f.numero_ficha, f.estado FROM fichas f WHERE f.proyecto_id = ? AND $cond ORDER BY f.numero_ficha");
        $st->execute(array_merge([$proyectoId], $params));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function puedeVer(int $proyectoId, Actor $actor): bool {
        if ($actor->esCoordinador()) {
            return $this->existe($proyectoId);
        }
        return $this->fichasDelProyecto($proyectoId, $actor) !== [];
    }
}
