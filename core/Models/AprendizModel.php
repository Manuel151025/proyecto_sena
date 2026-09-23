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
 * Acceso a `aprendices` (y a la cuenta de usuario de cada uno).
 * Reglas de negocio en MatriculasService.
 */
class AprendizModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array{0:string, 1:array} */
    private function construirConsulta(array $f, Actor $actor): array {
        $from = "
            FROM aprendices a
            JOIN usuarios u ON a.usuario_id = u.id
            LEFT JOIN fichas f ON a.ficha_id = f.id
            LEFT JOIN programas p ON f.programa_id = p.id
            LEFT JOIN usuarios u2 ON a.instructor_seguimiento_id = u2.id
            WHERE 1=1";
        $params = [];
        if ($actor->esInstructor()) {
            // Misma definición de "sus aprendices" que el resto del sistema:
            // antes aquí solo contaban la ficha liderada y el seguimiento,
            // y un instructor asignado por competencia no veía a nadie.
            $from .= " AND (a.ficha_id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ") OR a.instructor_seguimiento_id = ?)";
            array_push($params, $actor->id, $actor->id, $actor->id, $actor->id);
        } elseif (!$actor->esCoordinador()) {
            $from .= " AND 1 = 0";
        }
        if (($f['search'] ?? '') !== '') {
            $t = '%' . Validador::escaparLike((string)$f['search']) . '%';
            $from .= " AND (u.nombre LIKE ? OR a.numero_documento LIKE ? OR u.email LIKE ?)";
            array_push($params, $t, $t, $t);
        }
        if (!empty($f['ficha_id'])) {
            $from .= " AND a.ficha_id = ?";
            $params[] = (int)$f['ficha_id'];
        }
        if (($f['estado'] ?? '') !== '') {
            $from .= " AND a.estado = ?";
            $params[] = in_array($f['estado'], Enums::APRENDIZ_ESTADO, true) ? $f['estado'] : "\x00";
        }
        return [$from, $params];
    }

    public function contar(array $filtros, Actor $actor): int {
        [$from, $p] = $this->construirConsulta($filtros, $actor);
        $st = $this->db->prepare("SELECT COUNT(*) $from");
        $st->execute($p);
        return (int)$st->fetchColumn();
    }

    public function listar(array $filtros, Actor $actor, int $limite, int $offset): array {
        [$from, $p] = $this->construirConsulta($filtros, $actor);
        $limite = max(1, min($limite, 100));
        $offset = max(0, $offset);
        $st = $this->db->prepare("
            SELECT a.*, u.nombre, u.email, u.avatar_color, u.estado AS estado_usuario,
                   f.numero_ficha, p.nombre AS programa_nombre, u2.nombre AS instructor_seguimiento_nombre
            $from
            ORDER BY u.nombre
            LIMIT $limite OFFSET $offset
        ");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paraExportar(array $filtros, Actor $actor, int $maximo): array {
        [$from, $p] = $this->construirConsulta($filtros, $actor);
        $maximo = max(1, $maximo);
        $st = $this->db->prepare("SELECT a.*, u.nombre, u.email, f.numero_ficha, p.nombre AS programa_nombre $from ORDER BY f.numero_ficha, u.nombre LIMIT $maximo");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT a.*, u.nombre, u.email FROM aprendices a JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeDocumento(string $doc, ?int $exceptoId = null): bool {
        $st = $this->db->prepare("SELECT 1 FROM aprendices WHERE numero_documento = ? AND id <> ?");
        $st->execute([$doc, $exceptoId ?? 0]);
        return (bool)$st->fetchColumn();
    }

    public function existeEmail(string $email, ?int $exceptoUsuarioId = null): bool {
        $st = $this->db->prepare("SELECT 1 FROM usuarios WHERE email = ? AND id <> ?");
        $st->execute([$email, $exceptoUsuarioId ?? 0]);
        return (bool)$st->fetchColumn();
    }

    /** Crea la cuenta y la matrícula. @return int id del aprendiz */
    public function crear(array $d, string $hash, string $color): int {
        $this->db->prepare("
            INSERT INTO usuarios (nombre, email, password, debe_cambiar_password, rol, avatar_color, estado)
            VALUES (?, ?, ?, 1, 'aprendiz', ?, 'activo')
        ")->execute([$d['nombre'], $d['email'], $hash, $color]);
        $usuarioId = (int)$this->db->lastInsertId();
        $this->db->prepare("
            INSERT INTO aprendices (usuario_id, ficha_id, instructor_seguimiento_id, numero_documento, tipo_documento, genero,
                                    fecha_nacimiento, telefono, ciudad, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'matriculado')
        ")->execute([$usuarioId, $d['ficha_id'], $d['instructor_seguimiento_id'], $d['numero_documento'], $d['tipo_documento'],
                     $d['genero'], $d['fecha_nacimiento'], $d['telefono'] !== '' ? $d['telefono'] : null, $d['ciudad'] !== '' ? $d['ciudad'] : null]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, int $usuarioId, array $d): void {
        $this->db->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?")->execute([$d['nombre'], $d['email'], $usuarioId]);
        $this->db->prepare("
            UPDATE aprendices SET ficha_id = ?, instructor_seguimiento_id = ?, estado = ?, tipo_documento = ?, numero_documento = ?,
                                  genero = ?, fecha_nacimiento = ?, telefono = ?, ciudad = ?
             WHERE id = ?
        ")->execute([$d['ficha_id'], $d['instructor_seguimiento_id'], $d['estado'], $d['tipo_documento'], $d['numero_documento'],
                     $d['genero'], $d['fecha_nacimiento'], $d['telefono'] !== '' ? $d['telefono'] : null, $d['ciudad'] !== '' ? $d['ciudad'] : null, $id]);
    }

    /**
     * Mueve los registros académicos del aprendiz a la nueva ficha (el
     * `ficha_id` de evaluaciones y evidencias está desnormalizado): sin esto,
     * recalificar un RAP tras el traslado chocaba con la unicidad.
     */
    public function trasladarRegistros(int $aprendizId, int $fichaNueva): void {
        $this->db->prepare("UPDATE evaluaciones SET ficha_id = ? WHERE aprendiz_id = ?")->execute([$fichaNueva, $aprendizId]);
        $this->db->prepare("UPDATE evidencias SET ficha_id = ? WHERE aprendiz_id = ?")->execute([$fichaNueva, $aprendizId]);
        $this->db->prepare("UPDATE planes_mejoramiento SET ficha_id = ? WHERE aprendiz_id = ?")->execute([$fichaNueva, $aprendizId]);
    }

    public function marcarDesertado(int $id): void {
        $this->db->prepare("UPDATE aprendices SET estado = 'desertado' WHERE id = ?")->execute([$id]);
    }

    public function cambiarEstadoCuenta(int $usuarioId, string $estado): void {
        $this->db->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")->execute([$estado, $usuarioId]);
    }

    public function juiciosEmitidos(int $aprendizId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM evaluaciones WHERE aprendiz_id = ? AND concepto IN ('A','D')");
        $st->execute([$aprendizId]);
        return (int)$st->fetchColumn();
    }

    /** Programa, proyecto y líder de una ficha. */
    public function datosFicha(int $fichaId): ?array {
        $st = $this->db->prepare("SELECT id, numero_ficha, programa_id, instructor_id, estado FROM fichas WHERE id = ?");
        $st->execute([$fichaId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function esInstructorActivo(int $id): bool {
        $st = $this->db->prepare("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'instructor' AND estado = 'activo'");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }
}
