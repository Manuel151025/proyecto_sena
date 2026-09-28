<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/** Reglas de entrada de una ficha de formación. */
final class FichaFormulario {
    public const MAX_NUMERO = 20;

    public static function validar(Validador $v): array {
        $d = [
            // Los números de ficha de Sofia Plus son numéricos; se admiten
            // letras y guion por las fichas de convenio.
            'numero_ficha'  => $v->patron('numero_ficha', 'El número de ficha', '/^[A-Za-z0-9\-]+$/', 'letras, números y guion', 3, self::MAX_NUMERO),
            'programa_id'   => $v->id('programa_id', 'El programa'),
            'proyecto_id'   => $v->id('proyecto_id', 'El proyecto', false) ?: null,
            'instructor_id' => $v->id('instructor_id', 'El instructor líder'),
            'estado'        => $v->enum('estado', 'El estado', Enums::FICHA_ESTADO, 'planeacion'),
            'fecha_inicio'  => $v->fecha('fecha_inicio', 'La fecha de inicio', false),
            'fecha_fin'     => $v->fecha('fecha_fin', 'La fecha de fin', false),
        ];
        $v->rangoFechas($d['fecha_inicio'], $d['fecha_fin']);
        return $d;
    }
}
