<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Validador;

/**
 * Reglas de entrada de un juicio evaluativo emitido a mano.
 *
 * Solo A o D: devolver un juicio a «pendiente» sería borrarlo, y eso no
 * es una calificación. El motivo es obligatorio cuando se cambia un juicio
 * ya emitido (lo exige EvaluacionService, que es quien conoce el anterior).
 */
final class JuicioFormulario {
    public const MAX_COMENTARIO = 1000;
    public const MAX_MOTIVO = 255;

    public static function validar(Validador $v): array {
        return [
            'evaluacion_id' => $v->id('evaluacion_id', 'La evaluación'),
            'concepto'      => $v->enum('concepto', 'El concepto', ['A', 'D']),
            'comentario'    => $v->texto('comentario', 'El comentario', 0, self::MAX_COMENTARIO, false),
            'motivo'        => $v->texto('motivo', 'El motivo del cambio', 0, self::MAX_MOTIVO, false),
        ];
    }
}
