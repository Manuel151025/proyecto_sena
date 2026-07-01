<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class MejoramientoModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAprendizId(int $user_id): int {
        $stmt = $this->db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function getPlanesMejoramiento(int $user_rol, int $user_id, int $aprendiz_id): array {
        if ($user_rol === ROL_APRENDIZ) {
            $stmt = $this->db->prepare("
                SELECT eval.id,
                       ra.denominacion as actividad_nombre,
                       ra.codigo as ra_codigo,
                       u_inst.nombre as instructor_nombre,
                       eval.fecha_evaluacion,
                       eval.comentario
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
                WHERE eval.aprendiz_id = ? AND eval.concepto = 'D'
                ORDER BY eval.fecha_evaluacion DESC
            ");
            $stmt->execute([$aprendiz_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $this->db->prepare("
                SELECT eval.id,
                       ra.denominacion as actividad_nombre,
                       ra.codigo as ra_codigo,
                       u_inst.nombre as instructor_nombre,
                       u_ap.nombre as aprendiz_nombre,
                       f.numero_ficha,
                       eval.fecha_evaluacion,
                       eval.comentario
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
                JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
                JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
                WHERE eval.concepto = 'D' AND (
                    EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = eval.ficha_id 
                          AND asg.competencia_id = c.id 
                          AND asg.instructor_id = ?
                    )
                    OR
                    (
                        f.instructor_id = ?
                        AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                        AND NOT EXISTS (
                            SELECT 1 FROM asignaciones asg 
                            WHERE asg.ficha_id = eval.ficha_id 
                              AND asg.competencia_id = c.id
                        )
                    )
                    OR
                    (
                        (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                        AND ap.instructor_seguimiento_id = ?
                    )
                )
                ORDER BY eval.fecha_evaluacion DESC
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->prepare("
                SELECT eval.id,
                       ra.denominacion as actividad_nombre,
                       ra.codigo as ra_codigo,
                       u_inst.nombre as instructor_nombre,
                       u_ap.nombre as aprendiz_nombre,
                       f.numero_ficha,
                       eval.fecha_evaluacion,
                       eval.comentario
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
                JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
                JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
                WHERE eval.concepto = 'D'
                ORDER BY eval.fecha_evaluacion DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
