<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class FasesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function crearFase(int $proyecto_id, int $numero_fase, string $nombre, string $descripcion, ?string $fecha_inicio, ?string $fecha_fin, float $cumplimiento, string $estado): void {
        $stmt = $this->db->prepare("
            INSERT INTO fases_proyecto (proyecto_id, numero_fase, nombre, descripcion, fecha_inicio, fecha_fin, cumplimiento_porcentaje, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$proyecto_id, $numero_fase, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $cumplimiento, $estado]);
    }

    public function editarFase(int $id, int $numero_fase, string $nombre, string $descripcion, ?string $fecha_inicio, ?string $fecha_fin, float $cumplimiento, string $estado): void {
        $stmt = $this->db->prepare("
            UPDATE fases_proyecto
            SET numero_fase=?, nombre=?, descripcion=?, fecha_inicio=?, fecha_fin=?,
                cumplimiento_porcentaje=?, estado=?
            WHERE id=?
        ");
        $stmt->execute([$numero_fase, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $cumplimiento, $estado, $id]);
    }

    public function eliminarFase(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM fases_proyecto WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function getAprendizProyectoId(int $user_id): int {
        $stmt = $this->db->prepare("
            SELECT f.proyecto_id 
            FROM aprendices ap
            JOIN fichas f ON ap.ficha_id = f.id
            WHERE ap.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function getProyecto(int $proyecto_id): ?array {
        $stmt = $this->db->prepare("SELECT id, nombre, codigo FROM proyectos WHERE id = ?");
        $stmt->execute([$proyecto_id]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $res ? $res : null;
    }

    public function getTodosProyectos(): array {
        return $this->db->query("SELECT id, nombre, codigo FROM proyectos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProyectoActual(int $proyecto_id): ?array {
        $stmt = $this->db->prepare("SELECT pr.*, GROUP_CONCAT(DISTINCT f.numero_ficha ORDER BY f.numero_ficha SEPARATOR ', ') as fichas_vinculadas FROM proyectos pr LEFT JOIN fichas f ON f.proyecto_id = pr.id WHERE pr.id = ? GROUP BY pr.id");
        $stmt->execute([$proyecto_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res : null;
    }

    public function getFases(int $proyecto_id): array {
        $stmt = $this->db->prepare("
            SELECT fp.* 
            FROM fases_proyecto fp
            WHERE fp.proyecto_id = ?
            ORDER BY fp.numero_fase ASC
        ");
        $stmt->execute([$proyecto_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
