<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use PDO;

/**
 * Única fuente de verdad para "¿este instructor tiene autoridad sobre este
 * aprendiz/resultado de aprendizaje?". Un instructor puede tener relación
 * con un aprendiz por tres vías: ser el instructor líder de su ficha, tener
 * una asignación por competencia (tabla asignaciones), o ser su instructor
 * de seguimiento individual (aprendices.instructor_seguimiento_id, propio
 * de etapa práctica).
 *
 * Antes de esta clase, esta misma regla estaba copiada de forma divergente
 * en SeguimientoModel y RetroalimentacionModel: la copia de Retroalimentacion
 * solo validaba el instructor líder, dejando fuera instructores asignados
 * por competencia o por seguimiento individual.
 */
class InstructorAccessService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * ¿El instructor tiene alguna relación con este aprendiz (ficha líder,
     * asignación por cualquier competencia, o seguimiento individual)?
     * Usado para acciones que no dependen de una competencia específica,
     * como registrar retroalimentación general.
     */
    public function tieneAccesoAprendiz(int $aprendizId, int $instructorId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM aprendices ap
            JOIN fichas f ON ap.ficha_id = f.id
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
            WHERE ap.id = ? AND (f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$aprendizId, $instructorId, $instructorId, $instructorId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * ¿El instructor tiene autoridad para calificar/evaluar este Resultado de
     * Aprendizaje específico de este aprendiz en esta ficha? Considera:
     * asignación por competencia, instructor líder de la ficha (salvo
     * competencias de Etapa Práctica), o instructor de seguimiento en
     * Etapa Práctica.
     */
    public function tieneAccesoResultadoAprendizaje(int $raId, int $aprendizId, int $fichaId, int $instructorId): bool {
        $stmt = $this->db->prepare("
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
        $stmt->execute([$raId, $aprendizId, $fichaId, $instructorId, $instructorId, $instructorId]);
        return (bool)$stmt->fetchColumn();
    }
}
