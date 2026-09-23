<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/**
 * Reglas de entrada de una actividad del proyecto formativo.
 */
final class ActividadFormulario {
    public const MAX_NOMBRE = 200;
    public const MAX_DESCRIPCION = 3000;

    public static function validar(Validador $v, bool $conAvance = false): array {
        $d = [
            'ficha_id'       => $v->id('ficha_id', 'La ficha'),
            'fase_id'        => $v->id('fase_id', 'La fase', false) ?: null,
            'competencia_id' => $v->id('competencia_id', 'La competencia', false) ?: null,
            'nombre'         => $v->nombre('nombre', 'El nombre de la actividad', 3, self::MAX_NOMBRE),
            'descripcion'    => $v->texto('descripcion', 'La descripción', 0, self::MAX_DESCRIPCION, false),
            'fecha_inicio'   => $v->fecha('fecha_inicio', 'La fecha de inicio', false),
            'fecha_fin'      => $v->fecha('fecha_fin', 'La fecha límite', false),
            'responsable_id' => $v->id('responsable_id', 'El instructor responsable'),
            'estado'         => $v->enum('estado', 'El estado', Enums::ACTIVIDAD_ESTADO, 'pendiente'),
            'cumplimiento_porcentaje' => $conAvance ? $v->decimal('cumplimiento_porcentaje', 'El avance', 0, 100, false) : 0.0,
        ];
        $v->rangoFechas($d['fecha_inicio'], $d['fecha_fin'], 'La fecha límite');
        return self::coherente($d);
    }

    /** Solo estado y avance: la actualización rápida desde la tarjeta. */
    public static function validarAvance(Validador $v): array {
        return self::coherente([
            'estado' => $v->enum('estado', 'El estado', Enums::ACTIVIDAD_ESTADO),
            'cumplimiento_porcentaje' => $v->decimal('cumplimiento_porcentaje', 'El avance', 0, 100),
        ]);
    }

    /**
     * Estado y porcentaje no pueden contradecirse: una actividad completada
     * está al 100 %, y una pendiente no puede haber avanzado. Antes se
     * guardaban por separado y la tarjeta podía decir "Completada · 20 %".
     */
    private static function coherente(array $d): array {
        $pct = (float)$d['cumplimiento_porcentaje'];
        $d['cumplimiento_porcentaje'] = match ($d['estado']) {
            'completada' => 100.0,
            'pendiente'  => 0.0,
            default      => round(min(100.0, max(0.0, $pct)), 2),
        };
        return $d;
    }
}
