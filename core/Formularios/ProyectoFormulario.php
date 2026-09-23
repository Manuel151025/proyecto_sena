<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/**
 * Reglas de entrada de un proyecto formativo.
 *
 * Cada formulario del sistema tiene su clase con las reglas de sus campos,
 * y los límites coinciden con las columnas de la base (`proyectos.nombre`
 * es VARCHAR(255), `codigo` VARCHAR(50)): antes los límites estaban
 * escritos a mano en el controlador, dos veces (crear y editar), y ya no
 * coincidían entre sí.
 */
final class ProyectoFormulario {
    public const MAX_NOMBRE = 200;
    public const MAX_CODIGO = 30;
    public const MAX_TEXTO  = 2000;

    /** @return array{nombre:string, codigo:string, objetivo:string, descripcion:string, estado:string} */
    public static function validar(Validador $v, bool $conEstado = false): array {
        return [
            'nombre'      => $v->nombre('nombre', 'El nombre del proyecto', 3, self::MAX_NOMBRE),
            'codigo'      => $v->codigo('codigo', 'El código', 2, self::MAX_CODIGO),
            'objetivo'    => $v->texto('objetivo', 'El objetivo', 0, self::MAX_TEXTO, false),
            'descripcion' => $v->texto('descripcion', 'La descripción', 0, self::MAX_TEXTO, false),
            'estado'      => $conEstado
                ? $v->enum('estado', 'El estado', Enums::PROYECTO_ESTADO, 'activo')
                : 'activo',
        ];
    }
}
