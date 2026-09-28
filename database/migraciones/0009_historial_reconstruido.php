<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * RNF02: cada juicio emitido (A o D) debe tener al menos una fila de
 * historial. Los que entraron antes de la trazabilidad automática reciben
 * una fila reconstruida, marcada como tal en el motivo; autor y fecha salen
 * de la propia evaluación, no se inventan.
 * Antes: migrations/backfill_historial_evaluaciones.php
 */
return new class extends Migracion {
    public string $descripcion = 'Historial reconstruido de los juicios anteriores a RNF02';

    public const MOTIVO = 'Registro reconstruido: juicio anterior a la trazabilidad automática (RNF02)';

    public function aplicar(PDO $db): void {
        $st = $db->prepare("
            INSERT INTO historial_evaluaciones
                (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo, fecha_cambio)
            SELECT e.id, e.instructor_id, 'pendiente', e.concepto, ?,
                   CONCAT(COALESCE(e.fecha_evaluacion, DATE(e.fecha_creacion)), ' 00:00:00')
              FROM evaluaciones e
              JOIN usuarios u ON u.id = e.instructor_id
             WHERE e.concepto IN ('A','D')
               AND NOT EXISTS (SELECT 1 FROM historial_evaluaciones h WHERE h.evaluacion_id = e.id)
        ");
        $st->execute([self::MOTIVO]);
        $this->informar('filas reconstruidas: ' . $st->rowCount());
    }
};
