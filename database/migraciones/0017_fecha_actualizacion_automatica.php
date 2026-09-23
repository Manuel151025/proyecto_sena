<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Doce tablas tienen una columna `fecha_actualizacion` que nunca se
 * actualizaba: se declaró con DEFAULT CURRENT_TIMESTAMP pero sin
 * ON UPDATE, así que guardaba la fecha de creación para siempre. Cualquier
 * consulta del tipo "qué cambió esta semana" (la analítica de actividad
 * reciente, por ejemplo) leía un dato falso sin saberlo.
 *
 * Solo cambia la definición: los valores ya guardados no se tocan.
 */
return new class extends Migracion {
    public string $descripcion = 'fecha_actualizacion con ON UPDATE CURRENT_TIMESTAMP';

    public function aplicar(PDO $db): void {
        $st = $db->query("
            SELECT TABLE_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME = 'fecha_actualizacion'
               AND DATA_TYPE = 'timestamp'
               AND EXTRA NOT LIKE '%on update%'
        ");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
            if (!preg_match('/^[a-z_]+$/', (string)$tabla)) {
                continue;
            }
            $db->exec("ALTER TABLE `$tabla` MODIFY `fecha_actualizacion`
                       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            $this->informar("$tabla.fecha_actualizacion");
        }
    }
};
