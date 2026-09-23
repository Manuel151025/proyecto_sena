<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/**
 * Reglas de entrada de un programa de formación.
 */
final class ProgramaFormulario {
    public const MAX_NOMBRE = 200;
    public const MAX_CODIGO = 30;
    public const MAX_DESCRIPCION = 2000;
    /** Un tecnólogo SENA ronda las 4.000 h; 20.000 deja margen sin admitir disparates. */
    public const MAX_HORAS = 20000;

    /** @return array{nombre:string, codigo:string, descripcion:string, duracion_horas:int, estado:string} */
    public static function validar(Validador $v): array {
        return [
            'nombre'         => $v->nombre('nombre', 'El nombre del programa', 3, self::MAX_NOMBRE),
            'codigo'         => $v->codigo('codigo', 'El código', 2, self::MAX_CODIGO),
            'descripcion'    => $v->texto('descripcion', 'La descripción', 0, self::MAX_DESCRIPCION, false),
            'duracion_horas' => $v->entero('duracion_horas', 'La duración en horas', 1, self::MAX_HORAS),
            'estado'         => $v->enum('estado', 'El estado', Enums::PROGRAMA_ESTADO, 'activo'),
        ];
    }
}
