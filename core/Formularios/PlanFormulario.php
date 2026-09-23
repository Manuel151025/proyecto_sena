<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Validador;

/** Reglas de entrada de un plan de mejoramiento. */
final class PlanFormulario {
    public const MAX_ACTIVIDADES = 2000;
    public const MAX_OBSERVACIONES = 1000;
    /** Un plan más largo deja de ser una nivelación. */
    public const MAX_DIAS = 180;

    public static function validarCreacion(Validador $v): array {
        $d = [
            'evaluacion_id' => $v->id('evaluacion_id', 'El resultado de aprendizaje'),
            'actividades'   => $v->texto('actividades', 'Las actividades del plan', 10, self::MAX_ACTIVIDADES),
            'fecha_inicio'  => $v->fecha('fecha_inicio', 'La fecha de inicio', false) ?? date('Y-m-d'),
            'fecha_limite'  => $v->fecha('fecha_limite', 'La fecha límite'),
        ];
        self::plazo($v, $d['fecha_inicio'], $d['fecha_limite']);
        return $d;
    }

    public static function validarEdicion(Validador $v): array {
        return [
            'id'           => $v->id('id', 'El plan'),
            'actividades'  => $v->texto('actividades', 'Las actividades del plan', 10, self::MAX_ACTIVIDADES),
            'fecha_limite' => $v->fecha('fecha_limite', 'La fecha límite'),
        ];
    }

    public static function validarCierre(Validador $v): array {
        return [
            'id'            => $v->id('id', 'El plan'),
            'resultado'     => $v->enum('resultado', 'El resultado', ['cumplido', 'no_cumplido']),
            'observaciones' => $v->texto('observaciones', 'Las observaciones del cierre', 5, self::MAX_OBSERVACIONES),
        ];
    }

    public static function plazo(Validador $v, ?string $inicio, ?string $limite): void {
        if ($inicio === null || $limite === null) {
            return;
        }
        $v->rangoFechas($inicio, $limite, 'La fecha límite');
        if ((strtotime($limite) - strtotime($inicio)) / 86400 > self::MAX_DIAS) {
            $v->agregarError('El plazo del plan no puede superar ' . self::MAX_DIAS . ' días.');
        }
    }
}
