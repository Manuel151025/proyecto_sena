<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Exception;
use PDO;

class AprendizDashboardModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene la información básica del aprendiz, programa y proyecto
     */
    public function getAprendizInfo(int $usuarioId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT a.id, a.ficha_id, f.numero_ficha, f.estado as ficha_estado,
                       p.nombre as programa, p.codigo as programa_codigo,
                       pr.nombre as proyecto_nombre, pr.codigo as proyecto_codigo,
                       u_inst.nombre as instructor_nombre,
                       f.proyecto_id
                FROM aprendices a
                JOIN fichas f ON a.ficha_id = f.id
                JOIN programas p ON f.programa_id = p.id
                LEFT JOIN proyectos pr ON f.proyecto_id = pr.id
                JOIN usuarios u_inst ON f.instructor_id = u_inst.id
                WHERE a.usuario_id = ?
            ");
            $stmt->execute([$usuarioId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene el progreso global de resultados de aprendizaje evaluados
     */
    public function getProgresoGlobal(int $aprendizId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_ra,
                    SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                    SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                    SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM evaluaciones
                WHERE aprendiz_id = ?
            ");
            $stmt->execute([$aprendizId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: ['total_ra' => 0, 'aprobados' => 0, 'reprobados' => 0, 'pendientes' => 0];
        } catch (Exception $e) {
            return ['total_ra' => 0, 'aprobados' => 0, 'reprobados' => 0, 'pendientes' => 0];
        }
    }

    /**
     * Obtiene el progreso de resultados aprobados agrupados por competencia
     */
    public function getProgresoCompetencias(int $fichaId, int $aprendizId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.nombre as competencia,
                    c.codigo as comp_codigo,
                    COUNT(e.id) as total_ra,
                    SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                    SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                    u_inst.nombre as instructor_nombre
                FROM evaluaciones e
                JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                LEFT JOIN asignaciones asg ON asg.competencia_id = c.id AND asg.ficha_id = ?
                LEFT JOIN usuarios u_inst ON asg.instructor_id = u_inst.id
                WHERE e.aprendiz_id = ?
                GROUP BY c.id, c.nombre, c.codigo, u_inst.nombre
                ORDER BY c.codigo
            ");
            $stmt->execute([$fichaId, $aprendizId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene las fases del proyecto formativo
     */
    public function getFasesProyecto(int $proyectoId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT nombre, cumplimiento_porcentaje, estado, numero_fase
                FROM fases_proyecto 
                WHERE proyecto_id = ?
                ORDER BY numero_fase
            ");
            $stmt->execute([$proyectoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene las evaluaciones recientes (excluye pendientes)
     */
    public function getRecentEvaluations(int $aprendizId, int $limit = 6): array {
        try {
            $stmt = $this->db->prepare("
                SELECT ra.codigo as ra_codigo, ra.denominacion, e.concepto, e.fecha_evaluacion, e.comentario, u.nombre as instructor_evaluador
                FROM evaluaciones e
                JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                LEFT JOIN usuarios u ON e.instructor_id = u.id
                WHERE e.aprendiz_id = ? AND e.concepto != 'pendiente'
                ORDER BY e.fecha_evaluacion DESC
                LIMIT :limit
            ");
            $stmt->bindValue(1, $aprendizId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene los planes de mejoramiento pendientes (con concepto D)
     */
    public function getAlertasD(int $aprendizId, int $limit = 3): array {
        try {
            $stmt = $this->db->prepare("
                SELECT ra.codigo as ra_codigo, ra.denominacion as ra_denominacion, 
                       u.nombre as instructor_nombre, e.fecha_evaluacion, e.comentario
                FROM evaluaciones e
                JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                LEFT JOIN usuarios u ON e.instructor_id = u.id
                WHERE e.aprendiz_id = ? AND e.concepto = 'D'
                ORDER BY e.fecha_evaluacion DESC
                LIMIT :limit
            ");
            $stmt->bindValue(1, $aprendizId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
}
