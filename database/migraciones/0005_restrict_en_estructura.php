<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Borrar un programa arrastraba en cascada sus competencias, sus RAP y
 * TODOS los juicios evaluativos asociados. Pasa a RESTRICT.
 * Antes: migrations/fix_cascade_to_restrict.php
 */
return new class extends Migracion {
    public string $descripcion = 'Claves foráneas de la estructura curricular a RESTRICT';

    public function aplicar(PDO $db): void {
        $fks = [
            ['competencias', 'competencias_ibfk_1', 'programa_id', 'programas'],
            ['resultados_aprendizaje', 'resultados_aprendizaje_ibfk_1', 'competencia_id', 'competencias'],
            ['evaluaciones', 'evaluaciones_ibfk_1', 'resultado_aprendizaje_id', 'resultados_aprendizaje'],
        ];
        foreach ($fks as [$tabla, $fk, $columna, $ref]) {
            if ($this->reglaBorrado($db, $tabla, $fk) === 'CASCADE') {
                $db->exec("ALTER TABLE `$tabla` DROP FOREIGN KEY `$fk`");
                $db->exec("ALTER TABLE `$tabla` ADD CONSTRAINT `$fk` FOREIGN KEY (`$columna`) REFERENCES `$ref` (`id`) ON DELETE RESTRICT");
                $this->informar("$tabla.$fk: CASCADE → RESTRICT");
            }
        }
    }
};
