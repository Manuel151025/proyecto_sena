<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Exception;
use PDO;

class DashboardModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene los indicadores clave de rendimiento (KPIs)
     */
    public function getKpiMetrics(): array {
        try {
            // Fichas activas
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM fichas WHERE estado IN ('induccion', 'ejecucion')");
            $stmt->execute();
            $fichasActivas = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            // Aprendices matriculados
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM aprendices WHERE estado = 'matriculado'");
            $stmt->execute();
            $aprendicesMatriculados = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            // Instructores activos
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'instructor' AND estado = 'activo'");
            $stmt->execute();
            $instructoresActivos = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            // Promedio retención (cumplimiento promedio)
            $stmt = $this->db->prepare("SELECT AVG(cumplimiento_porcentaje) as promedio FROM fichas WHERE cumplimiento_porcentaje > 0");
            $stmt->execute();
            $retencionPromedio = round((float)($stmt->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0), 1);

            return [
                'fichas_activas' => $fichasActivas,
                'aprendices_matriculados' => $aprendicesMatriculados,
                'instructores_activos' => $instructoresActivos,
                'retencion_promedio' => $retencionPromedio
            ];
        } catch (Exception $e) {
            return [
                'fichas_activas' => 0,
                'aprendices_matriculados' => 0,
                'instructores_activos' => 0,
                'retencion_promedio' => 0
            ];
        }
    }

    /**
     * Obtiene las fichas críticas con cumplimiento < 60%
     */
    public function getCriticasFichas(int $limit = 5): array {
        try {
            $stmt = $this->db->prepare("
                SELECT f.id, f.numero_ficha, p.nombre as programa, u.nombre as instructor, f.cumplimiento_porcentaje, f.estado 
                FROM fichas f 
                JOIN programas p ON f.programa_id = p.id 
                JOIN usuarios u ON f.instructor_id = u.id 
                WHERE f.cumplimiento_porcentaje < 60 
                ORDER BY f.cumplimiento_porcentaje ASC 
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene el cumplimiento y volumen por programa
     */
    public function getCumplimientoPorPrograma(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.nombre, 
                    AVG(f.cumplimiento_porcentaje) as promedio,
                    COUNT(DISTINCT a.id) as total_aprendices,
                    MAX(f.cumplimiento_porcentaje) as max_cumplimiento,
                    MIN(f.cumplimiento_porcentaje) as min_cumplimiento
                FROM programas p
                LEFT JOIN fichas f ON p.id = f.programa_id
                LEFT JOIN aprendices a ON f.id = a.ficha_id AND a.estado = 'matriculado'
                GROUP BY p.id, p.nombre 
                ORDER BY promedio DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene estadísticas de retención y deserción por programa para los principales
     */
    public function getStatsProgramasDesercion(int $limit = 5): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.nombre as programa,
                    AVG(f.cumplimiento_porcentaje) as cumplimiento_avg,
                    COUNT(DISTINCT f.id) as fichas_count,
                    SUM(CASE WHEN a.estado = 'matriculado' THEN 1 ELSE 0 END) as matriculados,
                    SUM(CASE WHEN a.estado = 'desertado' THEN 1 ELSE 0 END) as desertados
                FROM programas p
                LEFT JOIN fichas f ON p.id = f.programa_id
                LEFT JOIN aprendices a ON f.id = a.ficha_id
                GROUP BY p.id, p.nombre
                ORDER BY matriculados DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene el top de instructores con base en el cumplimiento promedio
     */
    public function getTopInstructores(int $limit = 5): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.nombre, 
                    u.avatar_color,
                    AVG(f.cumplimiento_porcentaje) as promedio,
                    COUNT(f.id) as fichas_asignadas
                FROM usuarios u 
                JOIN fichas f ON u.id = f.instructor_id 
                WHERE u.rol = 'instructor' 
                GROUP BY u.id, u.nombre, u.avatar_color
                ORDER BY promedio DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene datos agregados para los minigráficos (sparklines) de estados
     */
    public function getSparklineData(): array {
        $data = [
            'fichas_estados' => ['planeacion' => 0, 'induccion' => 0, 'ejecucion' => 0, 'cierre' => 0],
            'aprendices_estados' => ['matriculado' => 0, 'suspendido' => 0, 'desertado' => 0, 'egresado' => 0],
            'instructores_estados' => ['activo' => 0, 'inactivo' => 0, 'bloqueado' => 0],
            'fichas_cumplimiento' => []
        ];

        try {
            // Fichas por estado
            $stmt = $this->db->query("
                SELECT estado, COUNT(*) as count 
                FROM fichas 
                GROUP BY estado 
                ORDER BY FIELD(estado, 'planeacion', 'induccion', 'ejecucion', 'cierre')
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['estado'] !== null && isset($data['fichas_estados'][$row['estado']])) {
                    $data['fichas_estados'][$row['estado']] = (int)$row['count'];
                }
            }

            // Aprendices por estado
            $stmt = $this->db->query("
                SELECT estado, COUNT(*) as count 
                FROM aprendices 
                GROUP BY estado 
                ORDER BY FIELD(estado, 'matriculado', 'suspendido', 'desertado', 'egresado')
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['estado'] !== null && isset($data['aprendices_estados'][$row['estado']])) {
                    $data['aprendices_estados'][$row['estado']] = (int)$row['count'];
                }
            }

            // Instructores por estado
            $stmt = $this->db->query("
                SELECT estado, COUNT(*) as count 
                FROM usuarios 
                WHERE rol = 'instructor' 
                GROUP BY estado 
                ORDER BY FIELD(estado, 'activo', 'inactivo', 'bloqueado')
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['estado'] !== null && isset($data['instructores_estados'][$row['estado']])) {
                    $data['instructores_estados'][$row['estado']] = (int)$row['count'];
                }
            }

            // Fichas cumplimiento para sparkline
            $stmt = $this->db->query("
                SELECT numero_ficha, cumplimiento_porcentaje 
                FROM fichas 
                ORDER BY numero_ficha ASC 
                LIMIT 6
            ");
            $data['fichas_cumplimiento'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (Exception $e) {
            // Mantener datos vacíos
        }

        return $data;
    }

    /**
     * Obtiene las últimas evaluaciones registradas en el sistema
     */
    public function getRecentEvaluations(int $limit = 5): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    e.fecha_evaluacion, 
                    e.concepto, 
                    a.nombre as aprendiz, 
                    r.codigo as rap, 
                    u.nombre as instructor 
                FROM evaluaciones e
                JOIN aprendices a ON e.aprendiz_id = a.id
                JOIN resultados_aprendizaje r ON e.resultado_aprendizaje_id = r.id
                LEFT JOIN usuarios u ON e.instructor_id = u.id
                WHERE e.fecha_evaluacion IS NOT NULL
                ORDER BY e.fecha_evaluacion DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
}
