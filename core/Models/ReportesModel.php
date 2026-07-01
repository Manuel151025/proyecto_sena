<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

class ReportesModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getInstructorStats(int $user_id): array {
        $stmtStats = $this->db->prepare("
            SELECT 
                COUNT(*) as total_evaluaciones,
                SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN e.concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
            FROM evaluaciones e
            JOIN fichas f ON e.ficha_id = f.id
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN aprendices ap ON e.aprendiz_id = ap.id
            WHERE (
                EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id AND asg.instructor_id = ?
                )
                OR
                (
                    f.instructor_id = ?
                    AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id
                    )
                )
                OR
                (
                    (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND ap.instructor_seguimiento_id = ?
                )
            )
        ");
        $stmtStats->execute([$user_id, $user_id, $user_id]);
        $rowStats = $stmtStats->fetch(PDO::FETCH_ASSOC);

        $stats = [];
        $stats['total_evaluaciones'] = (int)($rowStats['total_evaluaciones'] ?? 0);
        $stats['aprobados']          = (int)($rowStats['aprobados'] ?? 0);
        $stats['reprobados']         = (int)($rowStats['reprobados'] ?? 0);
        $stats['pendientes']         = (int)($rowStats['pendientes'] ?? 0);
        
        $stmtFichas = $this->db->prepare("
            SELECT COUNT(DISTINCT f.id) 
            FROM fichas f 
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id 
            LEFT JOIN aprendices ap ON ap.ficha_id = f.id
            WHERE f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?
        ");
        $stmtFichas->execute([$user_id, $user_id, $user_id]);
        $stats['total_fichas'] = (int)$stmtFichas->fetchColumn();
        
        $stmtHist = $this->db->prepare("
            SELECT COUNT(*) 
            FROM historial_evaluaciones he
            JOIN evaluaciones e ON he.evaluacion_id = e.id
            JOIN fichas f ON e.ficha_id = f.id
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN aprendices ap ON e.aprendiz_id = ap.id
            WHERE (
                EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id AND asg.instructor_id = ?
                )
                OR
                (
                    f.instructor_id = ?
                    AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id
                    )
                )
                OR
                (
                    (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND ap.instructor_seguimiento_id = ?
                )
            )
        ");
        $stmtHist->execute([$user_id, $user_id, $user_id]);
        $stats['cambios_historial'] = (int)$stmtHist->fetchColumn();

        return $stats;
    }

    public function getGlobalStats(): array {
        $stats = [];
        $stats['total_evaluaciones'] = (int)$this->db->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
        $stats['aprobados'] = (int)$this->db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto = 'A'")->fetchColumn();
        $stats['reprobados'] = (int)$this->db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto = 'D'")->fetchColumn();
        $stats['pendientes'] = (int)$this->db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto = 'pendiente'")->fetchColumn();
        $stats['total_fichas'] = (int)$this->db->query("SELECT COUNT(*) FROM fichas")->fetchColumn();
        $stats['cambios_historial'] = (int)$this->db->query("SELECT COUNT(*) FROM historial_evaluaciones")->fetchColumn();
        return $stats;
    }

    public function getFichasForInstructor(int $user_id): array {
        $stmtF = $this->db->prepare("
            SELECT DISTINCT f.id, f.numero_ficha, p.nombre as programa 
            FROM fichas f 
            JOIN programas p ON f.programa_id = p.id 
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id 
            LEFT JOIN aprendices ap ON ap.ficha_id = f.id
            WHERE f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?
            ORDER BY f.numero_ficha
        ");
        $stmtF->execute([$user_id, $user_id, $user_id]);
        return $stmtF->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllFichas(): array {
        return $this->db->query("
            SELECT f.id, f.numero_ficha, p.nombre as programa 
            FROM fichas f 
            JOIN programas p ON f.programa_id = p.id 
            ORDER BY f.numero_ficha
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checkFichaInstructorAccess(int $ficha_id, int $user_id): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM fichas f
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
            LEFT JOIN aprendices ap ON ap.ficha_id = f.id
            WHERE f.id = ? AND (f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?)
        ");
        $stmt->execute([$ficha_id, $user_id, $user_id, $user_id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getReportEvaluacionesFicha(int $ficha_id, int $user_id, string $user_rol): array {
        $sql = "
            SELECT u.nombre as aprendiz, ap.numero_documento, ra.codigo as ra_codigo, ra.denominacion,
                   c.nombre as competencia, e.concepto, e.fecha_evaluacion, ui.nombre as instructor
             FROM evaluaciones e
             JOIN aprendices ap ON e.aprendiz_id = ap.id
             JOIN usuarios u ON ap.usuario_id = u.id
             JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
             JOIN competencias c ON ra.competencia_id = c.id
             JOIN usuarios ui ON e.instructor_id = ui.id
             JOIN fichas f ON e.ficha_id = f.id
             WHERE e.ficha_id = ?
        ";
        $params = [$ficha_id];
        if ($user_rol === ROL_INSTRUCTOR) {
            $sql .= " AND (
                EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id AND asg.instructor_id = ?
                )
                OR
                (
                    f.instructor_id = ?
                    AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id
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
        }
        $sql .= " ORDER BY u.nombre, ra.codigo";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }

    public function getReportCumplimientoInstructor(int $user_id, string $user_rol): array {
        $sql = "
            SELECT ui.nombre as instructor, f.numero_ficha, p.nombre as programa,
                   COUNT(e.id) as total_ra,
                   SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                   SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                   SUM(CASE WHEN e.concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                   ROUND(SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(e.id), 0), 1) as pct
            FROM evaluaciones e
            JOIN fichas f ON e.ficha_id = f.id
            JOIN programas p ON f.programa_id = p.id
            JOIN usuarios ui ON f.instructor_id = ui.id
        ";
        $params = [];
        if ($user_rol === ROL_INSTRUCTOR) {
            $sql .= " WHERE (f.instructor_id = ? OR EXISTS (
                SELECT 1 FROM asignaciones asg 
                WHERE asg.ficha_id = f.id AND asg.instructor_id = ?
            ) OR EXISTS (
                SELECT 1 FROM aprendices ap 
                WHERE ap.ficha_id = f.id AND ap.instructor_seguimiento_id = ?
            ))";
            $params[] = $user_id;
            $params[] = $user_id;
            $params[] = $user_id;
        }
        $sql .= " GROUP BY ui.id, f.id ORDER BY ui.nombre, f.numero_ficha";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }

    public function getReportCumplimientoCompetencia(int $user_id, string $user_rol): array {
        $sql = "
            SELECT p.nombre as programa, c.nombre as competencia, c.codigo,
                   COUNT(e.id) as total,
                   SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                   SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                   ROUND(SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(e.id), 0), 1) as pct
            FROM evaluaciones e
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN programas p ON c.programa_id = p.id
            JOIN fichas f ON e.ficha_id = f.id
            JOIN aprendices ap ON e.aprendiz_id = ap.id
            WHERE e.concepto != 'pendiente'
        ";
        $params = [];
        if ($user_rol === ROL_INSTRUCTOR) {
            $sql .= " AND (
                EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id AND asg.instructor_id = ?
                )
                OR
                (
                    f.instructor_id = ?
                    AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id
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
        }
        $sql .= " GROUP BY c.id ORDER BY p.nombre, c.codigo";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }

    public function getReportHistorialCambios(int $user_id, string $user_rol): array {
        $sql = "
            SELECT he.evaluacion_id, u_ap.nombre as aprendiz, ra.codigo,
                   he.concepto_anterior, he.concepto_nuevo, he.motivo,
                   u_mod.nombre as modificado_por, he.fecha_cambio
            FROM historial_evaluaciones he
            JOIN evaluaciones e ON he.evaluacion_id = e.id
            JOIN fichas f ON e.ficha_id = f.id
            JOIN aprendices ap ON e.aprendiz_id = ap.id
            JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN usuarios u_mod ON he.usuario_id = u_mod.id
        ";
        $params = [];
        if ($user_rol === ROL_INSTRUCTOR) {
            $sql .= " WHERE (
                EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id AND asg.instructor_id = ?
                )
                OR
                (
                    f.instructor_id = ?
                    AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id
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
        }
        $sql .= " ORDER BY he.fecha_cambio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }
}
