<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Support\Validador;
use PDO;

/**
 * Acceso a `resultados_aprendizaje` (RAP).
 */
class ResultadosAprendizajeModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Competencias activas con sus RAP, en DOS consultas.
     *
     * Antes era una consulta por competencia (N+1): 39 viajes a la base
     * para pintar esta pantalla, y creciendo con el catálogo.
     */
    public function competenciasConRaps(?int $programaId = null, string $busqueda = ''): array {
        $sql = "SELECT c.id, c.nombre, c.codigo, c.es_etapa_practica, p.nombre AS programa, p.codigo AS programa_codigo
                  FROM competencias c JOIN programas p ON p.id = c.programa_id
                 WHERE c.estado = 'activo'";
        $params = [];
        if ($programaId !== null && $programaId > 0) {
            $sql .= " AND c.programa_id = ?";
            $params[] = $programaId;
        }
        if ($busqueda !== '') {
            $t = '%' . Validador::escaparLike($busqueda) . '%';
            $sql .= " AND (c.nombre LIKE ? OR c.codigo LIKE ?
                           OR EXISTS (SELECT 1 FROM resultados_aprendizaje rx
                                       WHERE rx.competencia_id = c.id AND (rx.codigo LIKE ? OR rx.denominacion LIKE ?)))";
            array_push($params, $t, $t, $t, $t);
        }
        $st = $this->db->prepare($sql . " ORDER BY p.nombre, c.codigo");
        $st->execute($params);
        $competencias = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($competencias === []) {
            return [];
        }

        $ids = array_map(static fn($c) => (int)$c['id'], $competencias);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("
            SELECT ra.id, ra.competencia_id, ra.codigo, ra.denominacion,
                   (SELECT COUNT(*) FROM evaluaciones e WHERE e.resultado_aprendizaje_id = ra.id AND e.concepto IN ('A','D')) AS juicios
              FROM resultados_aprendizaje ra
             WHERE ra.competencia_id IN ($marcas)
             ORDER BY ra.codigo
        ");
        $st->execute($ids);
        $porCompetencia = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $rap) {
            $porCompetencia[(int)$rap['competencia_id']][] = $rap;
        }
        foreach ($competencias as &$c) {
            $c['raps'] = $porCompetencia[(int)$c['id']] ?? [];
        }
        unset($c);
        return $competencias;
    }

    /** Compatibilidad con las pruebas existentes. */
    public function getCompetenciasWithRaps(): array {
        return $this->competenciasConRaps();
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM resultados_aprendizaje WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): int {
        $this->db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)")
                 ->execute([$d['competencia_id'], $d['codigo'], $d['denominacion']]);
        return (int)$this->db->lastInsertId();
    }

    /** @return bool true si lo creó; false si el código ya existía. */
    public function crearSiNoExiste(array $d): bool {
        $st = $this->db->prepare("INSERT IGNORE INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
        $st->execute([$d['competencia_id'], $d['codigo'], $d['denominacion']]);
        return $st->rowCount() === 1;
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("UPDATE resultados_aprendizaje SET competencia_id = ?, codigo = ?, denominacion = ? WHERE id = ?")
                 ->execute([$d['competencia_id'], $d['codigo'], $d['denominacion'], $id]);
    }

    /** @return array{juicios:int, pendientes:int, evidencias:int} */
    public function uso(int $id): array {
        $st = $this->db->prepare("
            SELECT SUM(e.concepto IN ('A','D')) AS juicios, SUM(e.concepto = 'pendiente') AS pendientes,
                   (SELECT COUNT(*) FROM evidencias ev JOIN evaluaciones e2 ON e2.id = ev.evaluacion_id
                     WHERE e2.resultado_aprendizaje_id = ?) AS evidencias
              FROM evaluaciones e WHERE e.resultado_aprendizaje_id = ?
        ");
        $st->execute([$id, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['juicios' => (int)($r['juicios'] ?? 0), 'pendientes' => (int)($r['pendientes'] ?? 0), 'evidencias' => (int)($r['evidencias'] ?? 0)];
    }

    /**
     * Borra el RAP y sus filas 'pendiente' (las que el sistema creó solo).
     * Solo se llama cuando no tiene juicios emitidos ni evidencias.
     */
    public function eliminarConPendientes(int $id): void {
        $this->db->prepare("DELETE FROM evaluaciones WHERE resultado_aprendizaje_id = ? AND concepto = 'pendiente'")->execute([$id]);
        $this->db->prepare("DELETE FROM resultados_aprendizaje WHERE id = ?")->execute([$id]);
    }
}
