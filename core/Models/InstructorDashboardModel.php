<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Exception;
use PDO;

class InstructorDashboardModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene los KPIs principales para el instructor
     */
    public function getKpis(int $instructorId): array {
        $kpis = [
            'evaluaciones_pendientes' => 0,
            'planes_requeridos' => 0,
            'aprendices_seguimiento' => 0
        ];

        try {
            // KPI 1 — evaluaciones pendientes de calificar
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
                WHERE eval.concepto = 'pendiente' AND (
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
            $stmt->execute([$instructorId, $instructorId, $instructorId]);
            $kpis['evaluaciones_pendientes'] = (int)$stmt->fetchColumn();

            // KPI 2 — aprendices con D en fichas del instructor (que requieren plan)
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT eval.aprendiz_id)
                FROM evaluaciones eval
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
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
            ");
            $stmt->execute([$instructorId, $instructorId, $instructorId]);
            $kpis['planes_requeridos'] = (int)$stmt->fetchColumn();

            // KPI 3 — aprendices en etapa práctica a cargo del instructor
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM aprendices
                WHERE instructor_seguimiento_id = ? AND estado = 'etapa_practica'
            ");
            $stmt->execute([$instructorId]);
            $kpis['aprendices_seguimiento'] = (int)$stmt->fetchColumn();

        } catch (Exception $e) {
            // Continuar con ceros
        }

        return $kpis;
    }

    /**
     * Obtiene las fichas asignadas al instructor
     */
    public function getFichasAsignadas(int $instructorId): array {
        $fichasInstructor = [];

        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT f.id, f.numero_ficha AS numero, p.nombre AS programa,
                       f.cantidad_aprendices AS aprendices,
                       f.cumplimiento_porcentaje AS cumplimiento, f.estado
                FROM fichas f
                JOIN programas p ON f.programa_id = p.id
                LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
                LEFT JOIN aprendices ap ON ap.ficha_id = f.id
                WHERE f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?
                ORDER BY f.cumplimiento_porcentaje ASC
            ");
            $stmt->execute([$instructorId, $instructorId, $instructorId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $f) {
                $badge = 'success';
                $estadoLabel = 'Al día';
                if ((float)$f['cumplimiento'] < 50) {
                    $badge = 'danger';
                    $estadoLabel = 'Crítico';
                } elseif ((float)$f['cumplimiento'] < 75) {
                    $badge = 'warning';
                    $estadoLabel = 'En riesgo';
                }
                $fichasInstructor[] = [
                    'id'           => (int)$f['id'],
                    'numero'       => $f['numero'],
                    'programa'     => $f['programa'],
                    'aprendices'   => (int)$f['aprendices'],
                    'cumplimiento' => (float)$f['cumplimiento'],
                    'estado'       => $estadoLabel,
                    'badge'        => $badge,
                ];
            }
        } catch (Exception $e) {
            // Dejar lista vacía
        }

        return $fichasInstructor;
    }

    /**
     * Obtiene los aprendices con concepto D más recientes (top 10)
     */
    public function getRecentDeficiencies(int $instructorId, int $limit = 10): array {
        try {
            $stmt = $this->db->prepare("
                SELECT u.nombre        AS aprendiz,
                       f.numero_ficha  AS ficha,
                       ra.codigo       AS ra_codigo,
                       ra.denominacion AS ra_nombre,
                       eval.fecha_evaluacion AS fecha,
                       eval.id         AS eval_id
                FROM evaluaciones eval
                JOIN fichas f                ON eval.ficha_id = f.id
                JOIN aprendices ap           ON eval.aprendiz_id = ap.id
                JOIN usuarios u              ON ap.usuario_id = u.id
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c          ON ra.competencia_id = c.id
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
                LIMIT :limit
            ");
            $stmt->bindValue(1, $instructorId, PDO::PARAM_INT);
            $stmt->bindValue(2, $instructorId, PDO::PARAM_INT);
            $stmt->bindValue(3, $instructorId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene la distribución de juicios para graficar
     */
    public function getConceptDistribution(int $instructorId): array {
        $evalConceptos = ['A' => 0, 'D' => 0, 'pendiente' => 0];

        try {
            $stmt = $this->db->prepare("
                SELECT eval.concepto, COUNT(*) as cantidad
                FROM evaluaciones eval
                JOIN fichas f ON eval.ficha_id = f.id
                JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN aprendices ap ON eval.aprendiz_id = ap.id
                WHERE EXISTS (
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
                GROUP BY eval.concepto
            ");
            $stmt->execute([$instructorId, $instructorId, $instructorId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $concepto = $row['concepto'] ?: 'pendiente';
                if (isset($evalConceptos[$concepto])) {
                    $evalConceptos[$concepto] = (int)$row['cantidad'];
                }
            }
        } catch (Exception $e) {
            // Mantener por defecto
        }

        return $evalConceptos;
    }

    /**
     * Obtiene la lista de aprendices en etapa práctica asignados a este instructor
     */
    public function getAprendicesSeguimiento(int $instructorId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT ap.id, u.nombre, f.numero_ficha, p.nombre as programa, ap.telefono, ap.ciudad, f.id as ficha_id
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                LEFT JOIN fichas f ON ap.ficha_id = f.id
                LEFT JOIN programas p ON f.programa_id = p.id
                WHERE ap.instructor_seguimiento_id = ? AND ap.estado = 'etapa_practica'
                ORDER BY u.nombre
            ");
            $stmt->execute([$instructorId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
}
