<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;
use Exception;

class SeguimientoModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function checkInstructorPermission(int $ra_id, int $aprendiz_id_p, int $ficha_id_p, int $user_id): bool {
        $stmtRA = $this->db->prepare("SELECT competencia_id FROM resultados_aprendizaje WHERE id = ?");
        $stmtRA->execute([$ra_id]);
        $competencia_id = (int)($stmtRA->fetchColumn() ?: 0);

        $stmtAuth = $this->db->prepare("
            SELECT 1 FROM fichas f
            JOIN resultados_aprendizaje ra ON ra.id = ?
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN aprendices ap ON ap.id = ?
            WHERE f.id = ? AND (
                EXISTS (
                    SELECT 1 FROM asignaciones asg
                    WHERE asg.ficha_id = f.id 
                      AND asg.competencia_id = c.id 
                      AND asg.instructor_id = ?
                )
                OR
                (
                    f.instructor_id = ?
                    AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg
                        WHERE asg.ficha_id = f.id
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
        $stmtAuth->execute([$ra_id, $aprendiz_id_p, $ficha_id_p, $user_id, $user_id, $user_id]);
        return (bool)$stmtAuth->fetchColumn();
    }

    public function registrarEvaluacion(int $ra_id, int $aprendiz_id_p, int $ficha_id_p, string $concepto, string $comentario, string $motivo, int $user_id): void {
        $stmt = $this->db->prepare("
            SELECT id, concepto FROM evaluaciones
            WHERE resultado_aprendizaje_id = ? AND aprendiz_id = ? AND ficha_id = ?
        ");
        $stmt->execute([$ra_id, $aprendiz_id_p, $ficha_id_p]);
        $eval_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($eval_row) {
            $eval_id = (int)$eval_row['id'];
            $conceptoAnterior = $eval_row['concepto'];

            if ($conceptoAnterior !== $concepto) {
                if (in_array($conceptoAnterior, ['A', 'D']) && empty($motivo)) {
                    throw new Exception('El motivo del cambio de calificación es requerido.');
                }

                $stmt = $this->db->prepare("
                    UPDATE evaluaciones
                    SET concepto = ?, comentario = ?, instructor_id = ?, fecha_evaluacion = ?, fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$concepto, $comentario, $user_id, date('Y-m-d'), $eval_id]);

                $stmtHist = $this->db->prepare("
                    INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtHist->execute([$eval_id, $user_id, $conceptoAnterior, $concepto, $motivo ?: 'Calificación inicial']);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE evaluaciones
                    SET comentario = ?, instructor_id = ?, fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$comentario, $user_id, $eval_id]);
            }
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO evaluaciones
                    (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ra_id, $aprendiz_id_p, $user_id, $ficha_id_p, $concepto, $comentario, date('Y-m-d')]);
            $new_eval_id = (int)$this->db->lastInsertId();

            $stmtHist = $this->db->prepare("
                INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo)
                VALUES (?, ?, 'pendiente', ?, 'Calificación inicial')
            ");
            $stmtHist->execute([$new_eval_id, $user_id, $concepto]);
        }
    }

    public function checkRetroalimentacionPermission(int $aprendiz_id_r, int $user_id): bool {
        $stmtCheckAp = $this->db->prepare("
            SELECT 1 FROM aprendices ap
            JOIN fichas f ON ap.ficha_id = f.id
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
            WHERE ap.id = ? AND (f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?)
        ");
        $stmtCheckAp->execute([$aprendiz_id_r, $user_id, $user_id, $user_id]);
        return (bool)$stmtCheckAp->fetchColumn();
    }

    public function agregarRetroalimentacion(int $aprendiz_id_r, int $user_id, string $tipo, string $contenido, int $privada): void {
        $stmt = $this->db->prepare("
            INSERT INTO retroalimentacion (aprendiz_id, instructor_id, tipo, contenido, privada)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$aprendiz_id_r, $user_id, $tipo, $contenido, $privada]);

        $logStmt = $this->db->prepare("
            INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
            VALUES (?, 'Crear', 'Seguimiento', 'retroalimentacion', ?, ?)
        ");
        $logStmt->execute([$user_id, (int)$this->db->lastInsertId(), "Registró anotación de seguimiento tipo $tipo para aprendiz id $aprendiz_id_r"]);
    }

    public function getPerfilAprendiz(int $user_id): ?array {
        $stmt = $this->db->prepare("
            SELECT ap.id, ap.ficha_id, ap.estado as aprendiz_estado,
                   f.numero_ficha, p.nombre as programa_nombre, p.id as programa_id,
                   u_inst.nombre as instructor_nombre, u_coor.nombre as coordinador_nombre,
                   u_seg.nombre as instructor_seguimiento_nombre
            FROM aprendices ap
            JOIN fichas f    ON ap.ficha_id = f.id
            JOIN programas p ON f.programa_id = p.id
            LEFT JOIN usuarios u_inst ON f.instructor_id = u_inst.id
            LEFT JOIN usuarios u_coor ON f.coordinador_id = u_coor.id
            LEFT JOIN usuarios u_seg  ON ap.instructor_seguimiento_id = u_seg.id
            WHERE ap.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getMisActividades(int $ap_id, int $ficha_id, int $programa_id): array {
        $stmt = $this->db->prepare("
            SELECT
                ra.id            AS ra_id,
                ra.denominacion  AS ra_nombre,
                ra.codigo        AS ra_codigo,
                c.codigo         AS competencia_codigo,
                c.nombre         AS competencia_nombre,
                eval.concepto,
                eval.comentario,
                eval.fecha_evaluacion,
                u_inst.nombre    AS instructor_nombre
            FROM resultados_aprendizaje ra
            JOIN competencias c ON ra.competencia_id = c.id
            LEFT JOIN evaluaciones eval
                ON eval.resultado_aprendizaje_id = ra.id
                AND eval.aprendiz_id = ? AND eval.ficha_id = ?
            LEFT JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
            WHERE c.programa_id = ?
            ORDER BY c.codigo, ra.codigo
        ");
        $stmt->execute([$ap_id, $ficha_id, $programa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMisRetroalimentaciones(int $ap_id): array {
        $stmt = $this->db->prepare("
            SELECT r.*, u.nombre AS instructor_nombre
            FROM retroalimentacion r
            JOIN usuarios u ON r.instructor_id = u.id
            WHERE r.aprendiz_id = ? AND r.privada = 0
            ORDER BY r.fecha_creacion DESC
        ");
        $stmt->execute([$ap_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFichas(int $user_id, string $user_rol): array {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $this->db->prepare("
                SELECT DISTINCT f.id, f.numero_ficha, p.nombre AS programa
                FROM fichas f
                JOIN programas p ON f.programa_id = p.id
                LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
                LEFT JOIN aprendices ap ON ap.ficha_id = f.id
                WHERE f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?
                ORDER BY f.numero_ficha
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $this->db->query("
                SELECT f.id, f.numero_ficha, p.nombre AS programa
                FROM fichas f
                JOIN programas p ON f.programa_id = p.id
                ORDER BY f.numero_ficha
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getFichaDetalle(int $selected_ficha_id): ?array {
        $stmt = $this->db->prepare("
            SELECT f.*, p.nombre AS programa_nombre,
                   u_inst.nombre AS instructor_nombre, u_coor.nombre AS coordinador_nombre
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            LEFT JOIN usuarios u_inst ON f.instructor_id = u_inst.id
            LEFT JOIN usuarios u_coor ON f.coordinador_id = u_coor.id
            WHERE f.id = ?
        ");
        $stmt->execute([$selected_ficha_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAprendicesStats(int $selected_ficha_id, int $selected_programa_id, string $user_rol, int $user_id): array {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $this->db->prepare("
                SELECT
                    ap.id            AS aprendiz_id,
                    u.nombre         AS aprendiz_nombre,
                    u.email          AS aprendiz_email,
                    ap.numero_documento,
                    ap.tipo_documento,
                    ap.genero,
                    ap.telefono,
                    ap.ciudad,
                    ap.estado        AS aprendiz_estado,
                    ap.instructor_seguimiento_id,
                    u2.nombre        AS instructor_seguimiento_nombre,
                    (SELECT COUNT(DISTINCT ra.id)
                     FROM resultados_aprendizaje ra
                     JOIN competencias c ON ra.competencia_id = c.id
                     JOIN fichas f ON f.id = ?
                     WHERE c.programa_id = ?
                       AND (
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
                    ) AS total_actividades,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                     JOIN competencias c ON ra.competencia_id = c.id
                     JOIN fichas f ON eval.ficha_id = f.id
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'A'
                       AND (
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
                    ) AS aprobadas,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                     JOIN competencias c ON ra.competencia_id = c.id
                     JOIN fichas f ON eval.ficha_id = f.id
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'D'
                       AND (
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
                    ) AS en_proceso,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
                     JOIN competencias c ON ra.competencia_id = c.id
                     JOIN fichas f ON eval.ficha_id = f.id
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'pendiente'
                       AND (
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
                    ) AS no_aplica
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                LEFT JOIN usuarios u2 ON ap.instructor_seguimiento_id = u2.id
                WHERE ap.ficha_id = ?
                  AND (
                      EXISTS (
                          SELECT 1 FROM fichas f
                          WHERE f.id = ? 
                            AND (f.instructor_id = ? OR EXISTS (
                                SELECT 1 FROM asignaciones asg 
                                WHERE asg.ficha_id = f.id AND asg.instructor_id = ?
                            ))
                      )
                      OR ap.instructor_seguimiento_id = ?
                  )
                ORDER BY u.nombre
            ");
            $stmt->execute([
                $selected_ficha_id, $selected_programa_id, $user_id, $user_id, $user_id,
                $selected_ficha_id, $user_id, $user_id, $user_id,
                $selected_ficha_id, $user_id, $user_id, $user_id,
                $selected_ficha_id, $user_id, $user_id, $user_id,
                $selected_ficha_id,
                $selected_ficha_id, $user_id, $user_id,
                $user_id
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->prepare("
                SELECT
                    ap.id            AS aprendiz_id,
                    u.nombre         AS aprendiz_nombre,
                    u.email          AS aprendiz_email,
                    ap.numero_documento,
                    ap.tipo_documento,
                    ap.genero,
                    ap.telefono,
                    ap.ciudad,
                    ap.estado        AS aprendiz_estado,
                    ap.instructor_seguimiento_id,
                    u2.nombre        AS instructor_seguimiento_nombre,
                    (SELECT COUNT(DISTINCT ra.id)
                     FROM resultados_aprendizaje ra
                     JOIN competencias c ON ra.competencia_id = c.id
                     WHERE c.programa_id = ?) AS total_actividades,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'A') AS aprobadas,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'D') AS en_proceso,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'pendiente') AS no_aplica
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                LEFT JOIN usuarios u2 ON ap.instructor_seguimiento_id = u2.id
                WHERE ap.ficha_id = ?
                ORDER BY u.nombre
            ");
            $stmt->execute([$selected_programa_id, $selected_ficha_id, $selected_ficha_id, $selected_ficha_id, $selected_ficha_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getTodasActividades(int $selected_ficha_id, int $selected_programa_id, string $user_rol, int $user_id): array {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $this->db->prepare("
                SELECT ra.id AS ra_id, ra.denominacion AS ra_nombre, ra.codigo AS ra_codigo,
                       c.codigo AS competencia_codigo, c.nombre AS competencia_nombre
                FROM resultados_aprendizaje ra
                JOIN competencias c ON ra.competencia_id = c.id
                JOIN fichas f ON f.id = ?
                WHERE c.programa_id = ?
                  AND (
                      EXISTS (
                          SELECT 1 FROM asignaciones asg
                          WHERE asg.ficha_id = f.id
                            AND asg.competencia_id = c.id
                            AND asg.instructor_id = ?
                      )
                      OR
                      (
                          f.instructor_id = ?
                          AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                          AND NOT EXISTS (
                              SELECT 1 FROM asignaciones asg
                              WHERE asg.ficha_id = f.id
                                AND asg.competencia_id = c.id
                          )
                      )
                      OR
                      (
                          (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                          AND EXISTS (
                              SELECT 1 FROM aprendices ap
                              WHERE ap.ficha_id = f.id
                                AND ap.instructor_seguimiento_id = ?
                          )
                      )
                  )
                ORDER BY c.codigo, ra.codigo
            ");
            $stmt->execute([$selected_ficha_id, $selected_programa_id, $user_id, $user_id, $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->prepare("
                SELECT ra.id AS ra_id, ra.denominacion AS ra_nombre, ra.codigo AS ra_codigo,
                       c.codigo AS competencia_codigo, c.nombre AS competencia_nombre
                FROM resultados_aprendizaje ra
                JOIN competencias c ON ra.competencia_id = c.id
                WHERE c.programa_id = ?
                ORDER BY c.codigo, ra.codigo
            ");
            $stmt->execute([$selected_programa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getTodasEvaluaciones(int $selected_ficha_id): array {
        $stmt = $this->db->prepare("
            SELECT eval.concepto, eval.comentario, eval.fecha_evaluacion,
                   eval.resultado_aprendizaje_id, eval.aprendiz_id
            FROM evaluaciones eval
            WHERE eval.ficha_id = ?
        ");
        $stmt->execute([$selected_ficha_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRetroalimentacionesFicha(int $selected_ficha_id): array {
        $stmt = $this->db->prepare("
            SELECT r.*, u.nombre AS instructor_nombre
            FROM retroalimentacion r
            JOIN usuarios u ON r.instructor_id = u.id
            WHERE r.aprendiz_id IN (SELECT id FROM aprendices WHERE ficha_id = ?)
            ORDER BY r.fecha_creacion DESC
        ");
        $stmt->execute([$selected_ficha_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
