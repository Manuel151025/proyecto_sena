<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use PDO;
use Exception;

class RetroalimentacionModel {
    private PDO $db;
    private InstructorAccessService $accessService;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->accessService = new InstructorAccessService($this->db);
    }

    public function getAprendizId(int $user_id): int {
        $stmt = $this->db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function checkPermisoInstructor(int $aprendiz_post, int $user_id): bool {
        return $this->accessService->tieneAccesoAprendiz($aprendiz_post, $user_id);
    }

    public function guardarRetroalimentacion(int $aprendiz_post, int $user_id, string $tipo, string $contenido, int $privada): void {
        $stmt = $this->db->prepare("
            INSERT INTO retroalimentacion
                (aprendiz_id, instructor_id, tipo, contenido, privada)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$aprendiz_post, $user_id, $tipo, $contenido, $privada]);
    }

    public function getFeedbacks(string $user_rol, int $user_id, int $aprendiz_id): array {
        if ($user_rol === ROL_APRENDIZ) {
            $stmt = $this->db->prepare("
                SELECT r.*, u_inst.nombre as instructor_nombre, u_inst.avatar_color as inst_color
                FROM retroalimentacion r
                JOIN usuarios u_inst ON r.instructor_id = u_inst.id
                WHERE r.aprendiz_id = ? AND r.privada = 0
                ORDER BY r.fecha_creacion DESC
            ");
            $stmt->execute([$aprendiz_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "
                SELECT r.*, u_ap.nombre as aprendiz_nombre, u_ap.email as aprendiz_email,
                       u_inst.nombre as instructor_nombre
                FROM retroalimentacion r
                JOIN aprendices ap   ON r.aprendiz_id = ap.id
                JOIN usuarios u_ap   ON ap.usuario_id = u_ap.id
                JOIN usuarios u_inst ON r.instructor_id = u_inst.id
            ";
            if ($user_rol === ROL_INSTRUCTOR) {
                $sql .= " WHERE r.instructor_id = ? ";
            }
            $sql .= " ORDER BY r.fecha_creacion DESC";

            $stmt = $this->db->prepare($sql);
            if ($user_rol === ROL_INSTRUCTOR) {
                $stmt->execute([$user_id]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getAprendicesDisponibles(string $user_rol, int $user_id): array {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $this->db->prepare("
                SELECT ap.id, u.nombre, f.numero_ficha,
                       ap.numero_documento, ap.tipo_documento
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                JOIN fichas f   ON ap.ficha_id = f.id
                WHERE f.instructor_id = ? AND ap.estado = 'matriculado'
                ORDER BY f.numero_ficha, u.nombre
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($user_rol === ROL_COORDINADOR) {
            $stmt = $this->db->prepare("
                SELECT ap.id, u.nombre, f.numero_ficha,
                       ap.numero_documento, ap.tipo_documento
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                LEFT JOIN fichas f ON ap.ficha_id = f.id
                WHERE ap.estado = 'matriculado'
                ORDER BY f.numero_ficha, u.nombre
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }
}
