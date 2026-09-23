<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/** Reglas de entrada de una competencia y de un resultado de aprendizaje. */
final class CompetenciaFormulario {
    public const MAX_NOMBRE = 255;
    public const MAX_CODIGO = 30;
    public const MAX_DESCRIPCION = 2000;
    public const MAX_HORAS = 5000;
    public const MAX_DENOMINACION = 2000;
    public const MAX_CODIGO_RAP = 50;

    public static function validar(Validador $v): array {
        return [
            'programa_id'       => $v->id('programa_id', 'El programa'),
            'codigo'            => $v->codigo('codigo', 'El código', 2, self::MAX_CODIGO),
            'nombre'            => mb_strtoupper($v->nombre('nombre', 'El nombre de la competencia', 5, self::MAX_NOMBRE), 'UTF-8'),
            'descripcion'       => $v->texto('descripcion', 'La descripción', 0, self::MAX_DESCRIPCION, false),
            'horas'             => $v->entero('horas', 'La duración en horas', 1, self::MAX_HORAS),
            'estado'            => $v->enum('estado', 'El estado', Enums::COMPETENCIA_ESTADO, 'activo'),
            'es_etapa_practica' => $v->booleano('es_etapa_practica'),
        ];
    }

    public static function validarRap(Validador $v): array {
        return [
            'competencia_id' => $v->id('competencia_id', 'La competencia'),
            'codigo'         => $v->codigo('codigo', 'El código del RAP', 2, self::MAX_CODIGO_RAP),
            'denominacion'   => mb_strtoupper($v->texto('denominacion', 'La denominación', 5, self::MAX_DENOMINACION), 'UTF-8'),
        ];
    }
}
