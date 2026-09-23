<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * `fichas.cantidad_aprendices` tenía dos escritores en conflicto y estaba
 * desviado en 5 de 7 fichas. La aplicación ya lo calcula al leer; esto
 * alinea la columna guardada para quien la consulte directamente.
 * Antes: migrations/reconciliar_cantidad_aprendices.php
 */
return new class extends Migracion {
    public string $descripcion = 'Recuento guardado de aprendices por ficha alineado con el real';

    public function aplicar(PDO $db): void {
        $n = $db->exec("
            UPDATE fichas f
               SET f.cantidad_aprendices = (SELECT COUNT(*) FROM aprendices a
                                             WHERE a.ficha_id = f.id AND a.estado <> 'desertado')
             WHERE f.cantidad_aprendices <> (SELECT COUNT(*) FROM aprendices a
                                              WHERE a.ficha_id = f.id AND a.estado <> 'desertado')
        ");
        if ($n > 0) {
            $this->informar("fichas corregidas: $n");
        }
    }
};
