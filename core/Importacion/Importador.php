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

    /**
     * Ajusta las filas leídas antes de reconocer los encabezados. Sirve a
     * los formatos que no empiezan por la fila de títulos, como el reporte
     * de juicios de Sofia Plus (trae la ficha y el programa arriba).
     *
     * @param list<array> $filas Filas tal como se leyeron.
     * @return array{filas: list<array>, contexto: array, avisos?: string[]}
     * @throws \Core\Support\ErrorDeNegocio si el archivo no sirve para esta importación.
     */
    public function prepararFilas(array $filas, array $contexto, Actor $actor): array {
        return ['filas' => $filas, 'contexto' => $contexto];
    }

    /** Columnas que se muestran en la vista previa (por defecto, todas). */
    public function columnasVistaPrevia(): array {
        return array_keys($this->columnas());
    }

    /**
     * Cifras que resumen la vista previa, además de válidas / con error.
     *
     * @param list<array> $filas Filas analizadas (con `datos`, `errores`, `avisos`).
     * @return list<array{0:string, 1:int|string}> [etiqueta, valor]
     */
    public function resumen(array $filas, array $contexto): array {
        return [];
    }

    /** Indicaciones que acompañan al formulario de subida. */
    public function instrucciones(): array {
        return [
            'La primera fila debe llevar los encabezados (se aceptan en cualquier orden, con o sin tildes).',
            'El separador del CSV (coma o punto y coma) y la codificación (UTF-8 o la de Excel en Windows) se detectan solos.',
            'Reimportar el mismo archivo no duplica: lo que ya existe se omite.',
        ];
    }

    /** Campos extra del formulario de subida (p. ej. la ficha de destino). */
    public function opcionesFormulario(Actor $actor): array {
        return [];
    }
}
