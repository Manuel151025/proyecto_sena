<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/**
 * Reglas de entrada de una fase del proyecto formativo.
 *
 * Ya no hay campo de cumplimiento: el avance de la fase se calcula a partir
 * de sus actividades (ver FasesModel::listarDeProyecto). Un porcentaje
 * tecleado a mano valía igual para todas las fichas del proyecto y no
 * tenía relación con lo ejecutado.
 */
final class FaseFormulario {
    public const MAX_NOMBRE = 150;
    public const MAX_DESCRIPCION = 2000;
    public const MAX_NUMERO = 20;

    public static function validar(Validador $v): array {
        $d = [
            'numero_fase'  => $v->entero('numero_fase', 'El número de fase', 1, self::MAX_NUMERO),
            'nombre'       => $v->nombre('nombre', 'El nombre de la fase', 3, self::MAX_NOMBRE),
            'descripcion'  => $v->texto('descripcion', 'La descripción', 0, self::MAX_DESCRIPCION, false),
            'fecha_inicio' => $v->fecha('fecha_inicio', 'La fecha de inicio', false),
            'fecha_fin'    => $v->fecha('fecha_fin', 'La fecha de fin', false),
            'estado'       => $v->enum('estado', 'El estado', Enums::FASE_ESTADO, 'planeada'),
        ];
        $v->rangoFechas($d['fecha_inicio'], $d['fecha_fin']);
        return $d;
    }
}
