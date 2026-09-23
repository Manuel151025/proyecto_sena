<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Support\Validador;
use Core\Database;
use Core\Services\EvaluacionService;
use Core\Services\InstructorAccessService;
use PDO;
use Exception;

class EvaluacionesModel {
    private PDO $db;
    private EvaluacionService $evaluacionService;
    private InstructorAccessService $accessService;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->evaluacionService = new EvaluacionService($this->db);
        $this->accessService = new InstructorAccessService($this->db);
    }

    public function getAprendizId(int $user_id): int {
        $stmt = $this->db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Concepto actual de una evaluación, o false si el instructor no tiene
     * autoridad sobre ella.
     *
     * La comprobación de permiso era una copia literal del SQL de
     * InstructorAccessService, la tercera de cuatro que había en el
     * proyecto. Ahora delega en el servicio, que es donde vive la regla.
     */
    public function getEvaluacionAnterior(int $eval_id, string $user_rol, int $user_id): string|false {
        if ($user_rol === ROL_INSTRUCTOR
            && !$this->accessService->tieneAccesoEvaluacion($eval_id, $user_id)) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT concepto FROM evaluaciones WHERE id = ?");
        $stmt->execute([$eval_id]);
        return $stmt->fetchColumn();
    }

    public function actualizarEvaluacion(int $eval_id, string $nuevo_concepto, string $comentario, string $motivo, int $user_id, string $conceptoAnterior): void {
        $this->evaluacionService->actualizarPorId($eval_id, [
            'concepto'   => $nuevo_concepto,
            'comentario' => $comentario,
            'motivo'     => $motivo,
            'usuario_id' => $user_id,
        ]);
    }

    public function getFichas(string $user_rol, int $user_id): array {
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

    public function getEvaluaciones(string $user_rol, int $user_id, int $aprendiz_id, int $filter_ficha, string $filter_concepto, string $search, ?int $limit = null, int $offset = 0): array {
        [$from, $params] = $this->construirConsulta($user_rol, $user_id, $aprendiz_id, $filter_ficha, $filter_concepto, $search);

        $sql = "
            SELECT eval.id, eval.concepto, eval.comentario, eval.fecha_evaluacion,
                   ra.codigo as ra_codigo, ra.denominacion as ra_denominacion,
                   c.nombre as competencia_nombre, c.codigo as competencia_codigo,
                   f.numero_ficha, f.id as ficha_id,
                   u_ap.nombre as aprendiz_nombre, u_ap.email as aprendiz_email,
                   u_inst.nombre as instructor_nombre
            $from
            ORDER BY eval.fecha_evaluacion DESC, eval.id DESC
        ";

        if ($limit !== null) {
            // Enteros interpolados: con ATTR_EMULATE_PREPARES en false,
            // MariaDB no acepta parámetros ligados en LIMIT/OFFSET.
            $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . max(0, $offset);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total de evaluaciones visibles con esos filtros y para ese rol.
     *
     * Imprescindible para paginar bien: el permiso del instructor se
     * resuelve dentro del WHERE (asignaciones, ficha propia, etapa
     * práctica), así que el total tiene que calcularse con las mismas
     * condiciones o mostraría páginas que no existen.
     */
    public function contarEvaluaciones(string $user_rol, int $user_id, int $aprendiz_id, int $filter_ficha, string $filter_concepto, string $search): int {
        [$from, $params] = $this->construirConsulta($user_rol, $user_id, $aprendiz_id, $filter_ficha, $filter_concepto, $search);
        $stmt = $this->db->prepare("SELECT COUNT(*) $from");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * FROM + WHERE (visibilidad por rol + filtros) compartidos por el
     * listado y el conteo.
     *
     * @return array{0:string, 1:array}
     */
    private function construirConsulta(string $user_rol, int $user_id, int $aprendiz_id, int $filter_ficha, string $filter_concepto, string $search): array {
        $sql = "
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
            $sql .= " AND (" . InstructorAccessService::sqlCondicionAcceso() . ")";
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
            $params[] = "%" . Validador::escaparLike($search) . "%";
            $params[] = "%" . Validador::escaparLike($search) . "%";
            $params[] = "%" . Validador::escaparLike($search) . "%";
        }

        if (!empty($filter_concepto)) {
            $sql .= " AND eval.concepto = ?";
            $params[] = $filter_concepto;
        }

        // Aquí había un `LIMIT 200` fijo. Con 3.873 evaluaciones el
        // coordinador veía las 200 más recientes y nada indicaba que
        // existieran las demás: era un recorte silencioso de datos, no una
        // medida de rendimiento. Ahora el límite lo pone la paginación.
        return [$sql, $params];
    }

    public function getStatsEval(string $user_rol, int $user_id, int $aprendiz_id): array {
        $params = [];
        
        if ($user_rol === ROL_APRENDIZ) {
            $sqlStats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM evaluaciones WHERE aprendiz_id = ?";
            $params[] = $aprendiz_id;
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
                          AND asg.instructor_id = ?
                    )
                    OR
                    (
                        f.instructor_id = ?
                        AND c.es_etapa_practica = 0
                        AND NOT EXISTS (
                            SELECT 1 FROM asignaciones asg 
                            WHERE asg.ficha_id = eval.ficha_id 
                              AND asg.competencia_id = c.id
                        )
                    )
                    OR
                    (
                        c.es_etapa_practica = 1
                        AND ap.instructor_seguimiento_id = ?
                    )
                )";
            $params = [$user_id, $user_id, $user_id];
        } else {
            $sqlStats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM evaluaciones";
        }
        
        $stmt = $this->db->prepare($sqlStats);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : [];
    }
}
