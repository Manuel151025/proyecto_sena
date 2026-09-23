<?php
declare(strict_types=1);

namespace Core\Support;

use PDO;

/**
 * Base de una migración del esquema (`database/migraciones/NNNN_nombre.php`).
 *
 * Toda migración de este proyecto tiene que ser IDEMPOTENTE: comprueba el
 * estado antes de cambiarlo, de modo que ejecutarla sobre una base que ya
 * tiene el cambio no hace nada. Es lo que permite usar la misma lista para
 * tres situaciones distintas sin mantener tres caminos:
 *
 *  - una instalación nueva, cargada desde `database/esquema.sql` (ya trae
 *    todo, así que las migraciones solo se registran);
 *  - la base de desarrollo, que recibió los cambios a mano con los scripts
 *    sueltos que había antes en `migrations/`;
 *  - la base de producción, a la que le faltaban varios de esos scripts.
 *
 * Los ayudantes de abajo son las comprobaciones que se repetían en cada
 * script antiguo.
 */
abstract class Migracion {
    /** Frase corta que aparece en `bin/migrar.php --estado`. */
    public string $descripcion = '';

    /** @var callable(string):void|null */
    private $salida = null;

    abstract public function aplicar(PDO $db): void;

    /** @param callable(string):void $salida */
    public function conSalida(callable $salida): static {
        $this->salida = $salida;
        return $this;
    }

    protected function informar(string $mensaje): void {
        if ($this->salida !== null) {
            ($this->salida)($mensaje);
        }
    }

    // -----------------------------------------------------------------
    // COMPROBACIONES DEL ESQUEMA
    // -----------------------------------------------------------------

    protected function existeTabla(PDO $db, string $tabla): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->execute([$tabla]);
        return (int)$st->fetchColumn() > 0;
    }

    protected function existeColumna(PDO $db, string $tabla, string $columna): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$tabla, $columna]);
        return (int)$st->fetchColumn() > 0;
    }

    protected function existeIndice(PDO $db, string $tabla, string $indice): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $st->execute([$tabla, $indice]);
        return (int)$st->fetchColumn() > 0;
    }

    protected function existeRestriccion(PDO $db, string $tabla, string $nombre): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
        $st->execute([$tabla, $nombre]);
        return (int)$st->fetchColumn() > 0;
    }

    /** Regla ON DELETE de una clave foránea, o null si no existe. */
    protected function reglaBorrado(PDO $db, string $tabla, string $restriccion): ?string {
        $st = $db->prepare("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
        $st->execute([$tabla, $restriccion]);
        $r = $st->fetchColumn();
        return $r === false ? null : (string)$r;
    }

    /** Definición de una columna (`enum('a','b')`, `int(11)`...). */
    protected function tipoColumna(PDO $db, string $tabla, string $columna): ?string {
        $st = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$tabla, $columna]);
        $r = $st->fetchColumn();
        return $r === false ? null : (string)$r;
    }

    /**
     * Crea un índice si no hay ya uno que empiece por esas mismas columnas
     * (un índice (a,b,c) ya sirve para buscar por (a,b)).
     *
     * @param string[] $columnas
     */
    protected function asegurarIndice(PDO $db, string $tabla, string $nombre, array $columnas): void {
        $existentes = [];
        foreach ($db->query("SHOW INDEX FROM `$tabla`") as $i) {
            $existentes[$i['Key_name']][(int)$i['Seq_in_index']] = $i['Column_name'];
        }
        foreach ($existentes as $partes) {
            ksort($partes);
            if (array_slice(array_values($partes), 0, count($columnas)) === $columnas) {
                return;
            }
        }
        $cols = implode(', ', array_map(static fn($c) => "`$c`", $columnas));
        $db->exec("ALTER TABLE `$tabla` ADD INDEX `$nombre` ($cols)");
        $this->informar("índice $tabla.$nombre creado");
    }
}
