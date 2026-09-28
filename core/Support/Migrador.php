<?php
declare(strict_types=1);

namespace Core\Support;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Ejecuta las migraciones pendientes y lleva el registro de las aplicadas.
 *
 * Antes había 15 scripts sueltos en `migrations/`, sin orden declarado ni
 * registro de cuáles se habían ejecutado en cada base: la única forma de
 * saber si producción estaba al día era ir probándolos. Ahora cada base
 * guarda en la tabla `migraciones` lo que ya recibió, y `bin/migrar.php`
 * aplica lo que falta, en orden.
 *
 * El orden es el del nombre de archivo (`0001_...`, `0002_...`). Un id,
 * una vez publicado, no se renombra ni se reutiliza.
 */
final class Migrador {
    public const TABLA = 'migraciones';

    public function __construct(
        private PDO $db,
        private string $carpeta,
    ) {}

    public function asegurarTabla(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLA . "` (
                id           VARCHAR(100) NOT NULL PRIMARY KEY,
                descripcion  VARCHAR(255) NOT NULL DEFAULT '',
                aplicada_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                duracion_ms  INT          NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /** @return array<string, string> id => ruta, en orden de aplicación */
    public function disponibles(): array {
        $archivos = glob(rtrim($this->carpeta, '/\\') . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9][0-9]_*.php') ?: [];
        sort($archivos, SORT_STRING);
        $lista = [];
        foreach ($archivos as $ruta) {
            $lista[basename($ruta, '.php')] = $ruta;
        }
        return $lista;
    }

    /** @return array<string, string> id => fecha de aplicación */
    public function aplicadas(): array {
        $this->asegurarTabla();
        $filas = $this->db->query("SELECT id, aplicada_en FROM `" . self::TABLA . "` ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map('strval', $filas ?: []);
    }

    /** @return array<string, string> id => ruta */
    public function pendientes(): array {
        return array_diff_key($this->disponibles(), $this->aplicadas());
    }

    public function cargar(string $ruta): Migracion {
        $m = require $ruta;
        if (!$m instanceof Migracion) {
            throw new RuntimeException(basename($ruta) . ' no devuelve una instancia de Core\Support\Migracion.');
        }
        return $m;
    }

    /**
     * Aplica una migración y la registra.
     *
     * No se envuelve en transacción: en MySQL/MariaDB cada sentencia DDL
     * confirma implícitamente, así que una transacción daría una falsa
     * sensación de atomicidad. Por eso las migraciones son idempotentes: si
     * una falla a medias, se corrige la causa y se vuelve a ejecutar.
     *
     * @param callable(string):void $salida
     */
    public function aplicar(string $id, string $ruta, callable $salida): void {
        $m = $this->cargar($ruta)->conSalida($salida);
        $inicio = hrtime(true);
        $m->aplicar($this->db);
        $ms = (int)((hrtime(true) - $inicio) / 1_000_000);
        $this->registrar($id, $m->descripcion, $ms);
    }

    public function registrar(string $id, string $descripcion, int $ms = 0): void {
        $st = $this->db->prepare("
            INSERT INTO `" . self::TABLA . "` (id, descripcion, duracion_ms) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)
        ");
        $st->execute([$id, mb_substr($descripcion, 0, 255), $ms]);
    }

    /**
     * Aplica todas las pendientes en orden. Se detiene en la primera que
     * falle: las siguientes pueden depender de ella.
     *
     * @param callable(string):void $salida
     * @return array{aplicadas:string[], error:?string}
     */
    public function migrar(callable $salida): array {
        $hechas = [];
        foreach ($this->pendientes() as $id => $ruta) {
            $salida("→ $id");
            try {
                $this->aplicar($id, $ruta, static fn(string $m) => $salida("    $m"));
                $hechas[] = $id;
            } catch (Throwable $e) {
                return ['aplicadas' => $hechas, 'error' => "$id: " . $e->getMessage()];
            }
        }
        return ['aplicadas' => $hechas, 'error' => null];
    }
}
