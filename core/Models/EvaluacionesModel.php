<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class EvaluacionesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAprendizId(int $user_id): int {
        $stmt = $this->db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function getEvaluacionAnterior(int $eval_id, int $user_rol, int $user_id) {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmtCurrent = $this->db->prepare("
                SELECT eval.concepto 
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
                WHERE eval.id = ? AND (
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
            ");
            $stmtCurrent->execute([$eval_id, $user_id, $user_id, $user_id]);
        } else {
            $stmtCurrent = $this->db->prepare("SELECT concepto FROM evaluaciones WHERE id = ?");
            $stmtCurrent->execute([$eval_id]);
        }
        return $stmtCurrent->fetchColumn();
    }

    public function actualizarEvaluacion(int $eval_id, string $nuevo_concepto, string $comentario, string $motivo, int $user_id, string $conceptoAnterior): void {
        $stmtUpdate = $this->db->prepare("UPDATE evaluaciones SET concepto = ?, comentario = ?, instructor_id = ?, fecha_evaluacion = CURDATE(), fecha_actualizacion = NOW() WHERE id = ?");
        $stmtUpdate->execute([$nuevo_concepto, $comentario, $user_id, $eval_id]);

        if ($conceptoAnterior !== $nuevo_concepto) {
            $stmtHist = $this->db->prepare("INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo) VALUES (?, ?, ?, ?, ?)");
            $stmtHist->execute([$eval_id, $user_id, $conceptoAnterior, $nuevo_concepto, $motivo ?: 'Calificación inicial']);
        }
    }

    public function getFichas(int $user_rol, int $user_id): array {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmtF = $this->db->prepare("
                SELECT DISTINCT f.id, f.numero_ficha 
                FROM fichas f 
                LEFT JOIN asignaciones asg ON asg.ficha_id = f.id 
                WHERE f.instructor_id = ? OR asg.instructor_id = ? 
                ORDER BY f.numero_ficha
            ");
            $stmtF->execute([$user_id, $user_id]);
            return $stmtF->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $this->db->query("SELECT id, numero_ficha FROM fichas ORDER BY numero_ficha")->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getEvaluaciones(int $user_rol, int $user_id, int $aprendiz_id, int $filter_ficha, string $filter_concepto, string $search): array {
        $sql = "
            SELECT eval.id, eval.concepto, eval.comentario, eval.fecha_evaluacion,
                   ra.codigo as ra_codigo, ra.denominacion as ra_denominacion,
                   c.nombre as competencia_nombre, c.codigo as competencia_codigo,
                   f.numero_ficha, f.id as ficha_id,
                   u_ap.nombre as aprendiz_nombre, u_ap.email as aprendiz_email,
                   u_inst.nombre as instructor_nombre
            FROM evaluaciones eval
            JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN fichas f ON eval.ficha_id = f.id
            JOIN aprendices ap ON eval.aprendiz_id = ap.id
            JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
            LEFT JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
            WHERE 1=1
        ";
        $params = [];

        if ($user_rol === ROL_APRENDIZ) {
            $sql .= " AND eval.aprendiz_id = ?";
            $params[] = $aprendiz_id;
        } elseif ($user_rol === ROL_INSTRUCTOR) {
            $sql .= " AND (
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
            )";
            $params[] = $user_id;
            $params[] = $user_id;
            $params[] = $user_id;
            if ($filter_ficha > 0) {
                $sql .= " AND eval.ficha_id = ?";
                $params[] = $filter_ficha;
            }
        } else {
            if ($filter_ficha > 0) {
                $sql .= " AND eval.ficha_id = ?";
                $params[] = $filter_ficha;
            }
        }

        if (!empty($search)) {
            $sql .= " AND (u_ap.nombre LIKE ? OR ra.codigo LIKE ? OR ra.denominacion LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($filter_concepto)) {
            $sql .= " AND eval.concepto = ?";
            $params[] = $filter_concepto;
        }

        $sql .= " ORDER BY eval.fecha_evaluacion DESC, eval.id DESC LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatsEval(int $user_rol, int $user_id, int $aprendiz_id): array {
        if ($user_rol === ROL_APRENDIZ) {
            $sqlStats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM evaluaciones WHERE aprendiz_id = " . (int)$aprendiz_id;
        } elseif ($user_rol === ROL_INSTRUCTOR) {
            $sqlStats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN eval.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN eval.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN eval.concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
                WHERE (
                    EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = eval.ficha_id 
                          AND asg.competencia_id = c.id 
                          AND asg.instructor_id = " . (int)$user_id . "
                    )
                    OR
                    (
                        f.instructor_id = " . (int)$user_id . "
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
                        AND ap.instructor_seguimiento_id = " . (int)$user_id . "
                    )
                )";
        } else {
            $sqlStats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM evaluaciones";
        }
        return $this->db->query($sqlStats)->fetch(PDO::FETCH_ASSOC);
    }
}
