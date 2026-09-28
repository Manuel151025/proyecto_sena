<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/** Reglas de entrada de una retroalimentación u observación de seguimiento. */
final class RetroalimentacionFormulario {
    public const MAX_CONTENIDO = 2000;
    public const TIPOS = [
        'fortaleza'       => ['Fortaleza', 'success', 'bi-hand-thumbs-up'],
        'aspecto_mejorar' => ['Aspecto a mejorar', 'warning', 'bi-exclamation-triangle'],
        'recomendacion'   => ['Recomendación', 'info', 'bi-lightbulb'],
    ];

    public static function validar(Validador $v): array {
        return [
            'aprendiz_id'   => $v->id('aprendiz_id', 'El aprendiz'),
            'tipo'          => $v->enum('tipo', 'El tipo', Enums::RETROALIMENTACION_TIPO, 'recomendacion'),
            'contenido'     => $v->texto('contenido', 'El contenido', 10, self::MAX_CONTENIDO),
            'privada'       => $v->booleano('privada'),
            'evaluacion_id' => $v->id('evaluacion_id', 'El resultado de aprendizaje', false) ?: null,
        ];
    }
}
