<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Todo aprendiz en formación debe tener una fila 'pendiente' por cada RAP
 * del programa de su ficha: sin ella el RAP no aparece en pantalla y no se
 * puede calificar. Rellena las que falten (idempotente: nunca toca un
 * juicio ya emitido).
 * Antes: migrations/backfill_evaluaciones_pendientes.php
 */
return new class extends Migracion {
    public string $descripcion = 'Filas pendientes de evaluación que falten';

    public function aplicar(PDO $db): void {
        $r = (new Core\Services\EvaluacionesSyncService($db))->sincronizar([]);
        $this->informar("filas creadas: {$r['creadas']}");
        if ($r['omitidas_sin_instructor'] > 0) {
            $this->informar("omitidas por ficha sin instructor líder: {$r['omitidas_sin_instructor']}");
        }
    }
};
