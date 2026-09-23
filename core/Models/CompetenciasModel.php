<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Support\Enums;
use Core\Support\Validador;
use PDO;

/**
 * Acceso a `competencias`. Reglas de negocio en CompetenciasService.
 */
class CompetenciasModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array{0:string, 1:array} */
    private function construirFiltro(array $f): array {
        $sql = " FROM competencias c JOIN programas p ON p.id = c.programa_id WHERE 1=1";
        $params = [];
        if (($f['search'] ?? '') !== '') {
            $sql .= " AND (c.nombre LIKE ? OR c.codigo LIKE ?)";
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            array_push($params, $t, $t);
        }
        if (!empty($f['programa_id'])) {
            $sql .= " AND c.programa_id = ?";
            $params[] = (int)$f['programa_id'];
        }
        if (($f['estado'] ?? '') !== '') {
            $sql .= " AND c.estado = ?";
            $params[] = in_array($f['estado'], Enums::COMPETENCIA_ESTADO, true) ? $f['estado'] : "\x00";
        }
        return [$sql, $params];
    }

    public function contar(array $filtros): int {
        [$desde, $p] = $this->construirFiltro($filtros);
        $st = $this->db->prepare("SELECT COUNT(*) $desde");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    public function listar(array $filtros, int $limite, int $offset): array {
        [$desde, $p] = $this->construirFiltro($filtros);
        $limite = max(1, min($limite, 100));
        $offset = max(0, $offset);
        $st = $this->db->prepare("
            SELECT c.*, p.nombre AS programa_nombre, p.codigo AS programa_codigo,
                   (SELECT COUNT(*) FROM resultados_aprendizaje ra WHERE ra.competencia_id = c.id) AS total_rap
            $desde
            ORDER BY p.nombre, c.codigo
            LIMIT $limite OFFSET $offset
        ");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todas, sin paginar: para el catálogo agrupado de RAP y los selectores. */
    public function opciones(?int $programaId = null, bool $soloActivas = true): array {
        $sql = "SELECT c.id, c.codigo, c.nombre, c.programa_id, c.es_etapa_practica, p.nombre AS programa_nombre, p.codigo AS programa_codigo
                  FROM competencias c JOIN programas p ON p.id = c.programa_id WHERE 1=1";
        $params = [];
        if ($soloActivas) {
            $sql .= " AND c.estado = 'activo'";
        }
        if ($programaId !== null && $programaId > 0) {
            $sql .= " AND c.programa_id = ?";
            $params[] = $programaId;
        }
        $st = $this->db->prepare($sql . " ORDER BY p.nombre, c.codigo");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM competencias WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return int[] ids de competencias con ese código (puede repetirse entre programas). */
    public function idsPorCodigo(string $codigo, ?int $programaId = null): array {
        $sql = "SELECT id FROM competencias WHERE codigo = ?";
        $p = [$codigo];
        if ($programaId !== null) {
            $sql .= " AND programa_id = ?";
            $p[] = $programaId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function crear(array $d): int {
        $this->db->prepare("
            INSERT INTO competencias (programa_id, codigo, nombre, es_etapa_practica, descripcion, horas, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$d['programa_id'], $d['codigo'], $d['nombre'], (int)$d['es_etapa_practica'],
                     $d['descripcion'] !== '' ? $d['descripcion'] : null, $d['horas'], $d['estado']]);
        return (int)$this->db->lastInsertId();
    }

    /** Inserta si no existe (programa + código). @return bool true si la creó. */
    public function crearSiNoExiste(array $d): bool {
        $st = $this->db->prepare("
            INSERT IGNORE INTO competencias (programa_id, codigo, nombre, es_etapa_practica, descripcion, horas, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'activo')
        ");
        $st->execute([$d['programa_id'], $d['codigo'], $d['nombre'], (int)($d['es_etapa_practica'] ?? 0),
                      ($d['descripcion'] ?? '') !== '' ? $d['descripcion'] : null, $d['horas']]);
        return $st->rowCount() === 1;
    }

    public function actualizar(int $id, array $d): void {
        $this->db->prepare("
            UPDATE competencias
               SET programa_id = ?, codigo = ?, nombre = ?, es_etapa_practica = ?, descripcion = ?, horas = ?, estado = ?
             WHERE id = ?
        ")->execute([$d['programa_id'], $d['codigo'], $d['nombre'], (int)$d['es_etapa_practica'],
                     $d['descripcion'] !== '' ? $d['descripcion'] : null, $d['horas'], $d['estado'], $id]);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM competencias WHERE id = ?")->execute([$id]);
    }

    /** @return array{rap:int, evaluaciones:int, fichas_programa:int} */
    public function dependencias(int $id): array {
        $st = $this->db->prepare("
            SELECT (SELECT COUNT(*) FROM resultados_aprendizaje WHERE competencia_id = ?) AS rap,
                   (SELECT COUNT(*) FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
                     WHERE ra.competencia_id = ?) AS evaluaciones
        ");
        $st->execute([$id, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['rap' => (int)($r['rap'] ?? 0), 'evaluaciones' => (int)($r['evaluaciones'] ?? 0)];
    }
}
