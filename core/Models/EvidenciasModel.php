<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class EvidenciasModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAprendizPerfil(int $user_id): ?array {
        $stmt = $this->db->prepare("SELECT id, ficha_id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardarEvidencia(int $aprendiz_id, int $ficha_id, string $titulo, string $descripcion, ?string $archivo_url, ?string $tipo_archivo, int $tamanio_kb, int $user_id): void {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO evidencias (aprendiz_id, ficha_id, titulo, descripcion, archivo_url, tipo_archivo, tamaño_kb, estado, fecha_envio)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'enviada', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$aprendiz_id, $ficha_id, $titulo, $descripcion, $archivo_url, $tipo_archivo, $tamanio_kb]);
            $evidencia_id = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Crear', 'Evidencias', 'evidencias', ?, ?)
            ");
            $stmt->execute([$user_id, $evidencia_id, "Subió evidencia: $titulo"]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getEvidencia(int $evidencia_id): ?array {
        $stmt = $this->db->prepare("SELECT id, evaluacion_id, aprendiz_id, ficha_id, titulo FROM evidencias WHERE id = ?");
        $stmt->execute([$evidencia_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function checkPermisoCalificar(array $evidencia, int $user_id): bool {
        if ($evidencia['evaluacion_id']) {
            $stmtCheck = $this->db->prepare("
                SELECT 1 FROM evaluaciones eval
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
            $stmtCheck->execute([$evidencia['evaluacion_id'], $user_id, $user_id, $user_id]);
        } else {
            $stmtCheck = $this->db->prepare("
                SELECT 1 FROM fichas f
                JOIN aprendices ap ON ap.id = ?
                WHERE f.id = ? AND (
                    f.instructor_id = ?
                    OR EXISTS (
                        SELECT 1 FROM asignaciones asg 
                        WHERE asg.ficha_id = f.id AND asg.instructor_id = ?
                    )
                    OR ap.instructor_seguimiento_id = ?
                )
            ");
            $stmtCheck->execute([$evidencia['aprendiz_id'], $evidencia['ficha_id'], $user_id, $user_id, $user_id]);
        }
        return (bool)$stmtCheck->fetchColumn();
    }

    public function calificarEvidencia(array $evidencia, string $estado_evidencia, string $concepto_db, string $comentario, string $tipo_retro, int $user_id): void {
        $this->db->beginTransaction();
        try {
            $eval_id = $evidencia['evaluacion_id'];
            $evidencia_id = $evidencia['id'];

            $stmt = $this->db->prepare("
                UPDATE evidencias
                SET estado = ?, retroalimentacion = ?, fecha_revision = CURRENT_DATE
                WHERE id = ?
            ");
            $stmt->execute([$estado_evidencia, $comentario, $evidencia_id]);

            if ($eval_id) {
                $stmt = $this->db->prepare("
                    UPDATE evaluaciones
                    SET concepto = ?, comentario = ?, instructor_id = ?, fecha_evaluacion = CURRENT_DATE
                    WHERE id = ?
                ");
                $stmt->execute([$concepto_db, $comentario, $user_id, $eval_id]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO retroalimentacion (evaluacion_id, aprendiz_id, instructor_id, tipo, contenido, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$eval_id ?: null, $evidencia['aprendiz_id'], $user_id, $tipo_retro, $comentario]);

            $ficha_id_ev = $evidencia['ficha_id'];
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total, SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas
                FROM evidencias WHERE ficha_id = ?
            ");
            $stmt->execute([$ficha_id_ev]);
            $stats = $stmt->fetch();
            if ($stats && (int)$stats['total'] > 0) {
                $cump = ((float)$stats['aprobadas'] / (float)$stats['total']) * 100;
                $this->db->prepare("UPDATE fichas SET cumplimiento_porcentaje = ? WHERE id = ?")->execute([$cump, $ficha_id_ev]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, 'Calificar', 'Evidencias', 'evidencias', ?, ?)
            ");
            $stmt->execute([$user_id, $evidencia_id, "Calificó evidencia: " . $evidencia['titulo'] . " como " . $concepto_db]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getEvidencias(int $user_rol, int $user_id, int $aprendiz_id): array {
        if ($user_rol === ROL_APRENDIZ) {
            $stmt = $this->db->prepare("
                SELECT ev.*, ra.denominacion AS ra_denominacion, u.nombre AS instructor_revisor
                FROM evidencias ev
                LEFT JOIN evaluaciones eval ON ev.evaluacion_id = eval.id
                LEFT JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                LEFT JOIN usuarios u ON eval.instructor_id = u.id
                WHERE ev.aprendiz_id = ?
                ORDER BY ev.fecha_envio DESC
            ");
            $stmt->execute([$aprendiz_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            if ($user_rol === ROL_INSTRUCTOR) {
                $sql = "
                    SELECT ev.*, ra.denominacion AS ra_denominacion,
                           f.numero_ficha, u_ap.nombre AS aprendiz_nombre, u_ap.email AS aprendiz_email
                    FROM evidencias ev
                    LEFT JOIN evaluaciones eval ON ev.evaluacion_id = eval.id
                    LEFT JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                    LEFT JOIN competencias c ON ra.competencia_id = c.id
                    JOIN fichas f    ON ev.ficha_id = f.id
                    JOIN aprendices ap ON ev.aprendiz_id = ap.id
                    JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
                    WHERE (
                        EXISTS (
                            SELECT 1 FROM asignaciones asg 
                            WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id AND asg.instructor_id = ?
                        )
                        OR
                        (
                            f.instructor_id = ?
                            AND (c.id IS NULL OR (NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                            AND NOT EXISTS (
                                SELECT 1 FROM asignaciones asg 
                                WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id
                            )))
                        )
                        OR
                        (
                            (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                            AND ap.instructor_seguimiento_id = ?
                        )
                        OR
                        (
                            eval.id IS NULL AND (
                                f.instructor_id = ?
                                OR EXISTS (
                                    SELECT 1 FROM asignaciones asg 
                                    WHERE asg.ficha_id = f.id AND asg.instructor_id = ?
                                )
                                OR ap.instructor_seguimiento_id = ?
                            )
                        )
                    )
                ";
                $params = [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id];
            } else {
                $sql = "
                    SELECT ev.*, ra.denominacion AS ra_denominacion,
                           f.numero_ficha, u_ap.nombre AS aprendiz_nombre, u_ap.email AS aprendiz_email
                    FROM evidencias ev
                    LEFT JOIN evaluaciones eval ON ev.evaluacion_id = eval.id
                    LEFT JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                    JOIN fichas f    ON ev.ficha_id = f.id
                    JOIN aprendices ap ON ev.aprendiz_id = ap.id
                    JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
                ";
                $params = [];
            }
            $sql .= " ORDER BY ev.estado = 'enviada' DESC, ev.fecha_envio DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
