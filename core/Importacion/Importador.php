<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Support\Actor;

/**
 * Lo que distingue a un tipo de importación (usuarios, competencias, RAP,
 * matrículas): sus columnas, cómo se valida una fila y cómo se guarda.
 *
 * Todo lo demás —leer el archivo, reconocer encabezados, detectar filas
 * repetidas, previsualizar y confirmar— lo hace ImportacionService igual
 * para todos. Antes cada controlador tenía 150 líneas propias para eso.
 */
abstract class Importador {
    /** Clave corta: 'usuarios', 'matriculas'... Nombra la plantilla y la sesión. */
    abstract public function clave(): string;

    /** Qué se importa, en plural y minúsculas ("usuarios"). */
    abstract public function titulo(): string;

    /**
     * Columnas en el orden de la plantilla.
     *
     * @return array<string, array{etiqueta:string, alias?:string[], obligatorio?:bool, ayuda?:string}>
     */
    abstract public function columnas(): array;

    /** Una fila de ejemplo para la plantilla descargable. */
    abstract public function ejemplo(): array;

    /**
     * Valida una fila ya mapeada por nombre de columna.
     *
     * @param array<string,string> $fila
     * @return array{datos: array, errores: string[], avisos?: string[], clave?: string}
     *         `clave` identifica la fila para detectar repetidas en el mismo archivo.
     */
    abstract public function validarFila(array $fila, Actor $actor, array $contexto): array;

    /**
     * Guarda las filas válidas, en una transacción.
     *
     * @param list<array> $datos
     * @return array{creados:int, omitidos:int, detalle?:list<string>, credenciales?:list<array>}
     */
    abstract public function guardar(array $datos, Actor $actor, array $contexto): array;

    /** Límite de filas por archivo. */
    public function maxFilas(): int {
        return 2000;
    }

    /**
     * Datos del formulario que acompañan al archivo (p. ej. la ficha de
     * destino de una matrícula), ya validados.
     *
     * @return array{0: array, 1: string[]} [contexto, errores]
     */
    public function contexto(array $post, Actor $actor): array {
        return [[], []];
    }

    /** ¿Puede el actor usar esta importación? */
    public function permitido(Actor $actor): bool {
        return $actor->esCoordinador();
    }
}
