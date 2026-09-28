<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Índices de las dos consultas cuyo coste crece con los datos: aprendices
 * activos por ficha y juicios por ficha y concepto.
 * Antes: migrations/add_indices_compuestos.php
 */
return new class extends Migracion {
    public string $descripcion = 'Índices (ficha_id, estado) y (ficha_id, concepto)';

    public function aplicar(PDO $db): void {
        $this->asegurarIndice($db, 'aprendices', 'idx_ficha_estado', ['ficha_id', 'estado']);
        $this->asegurarIndice($db, 'evaluaciones', 'idx_ficha_concepto', ['ficha_id', 'concepto']);
    }
};
