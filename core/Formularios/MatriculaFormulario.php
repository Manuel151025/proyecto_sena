<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/** Reglas de entrada de una matrícula (datos personales del aprendiz y su ficha). */
final class MatriculaFormulario {
    public const MAX_NOMBRE = 150;
    public const MAX_CIUDAD = 100;

    public static function validar(Validador $v, bool $conEstado = false): array {
        $d = [
            'nombre'           => mb_strtoupper($v->patron('nombre', 'El nombre', Validador::PATRON_PERSONA, 'letras, espacios, apóstrofo y guion', 3, self::MAX_NOMBRE), 'UTF-8'),
            'email'            => $v->email('email'),
            'tipo_documento'   => $v->enum('tipo_documento', 'El tipo de documento', Enums::TIPO_DOCUMENTO, 'CC'),
            // CC/TI/CE: solo dígitos; PEP y pasaporte admiten letras.
            'numero_documento' => $v->patron('numero_documento', 'El número de documento', '/^[A-Za-z0-9]+$/', 'letras y números, sin puntos ni espacios', 5, 20),
            'ficha_id'         => $v->id('ficha_id', 'La ficha'),
            'genero'           => $v->enum('genero', 'El género', Enums::APRENDIZ_GENERO, 'O'),
            'fecha_nacimiento' => $v->fecha('fecha_nacimiento', 'La fecha de nacimiento', false),
            'telefono'         => $v->patron('telefono', 'El teléfono', '/^\+?[0-9 ]{7,15}$/', 'dígitos (7 a 15), opcionalmente con + al inicio', 7, 20, false),
            'ciudad'           => $v->nombre('ciudad', 'La ciudad', 2, self::MAX_CIUDAD, false),
            'instructor_seguimiento_id' => $v->id('instructor_seguimiento_id', 'El instructor de seguimiento', false) ?: null,
        ];
        if (in_array($d['tipo_documento'], ['CC', 'TI', 'CE'], true) && $d['numero_documento'] !== '' && !ctype_digit($d['numero_documento'])) {
            $v->agregarError('Para CC, TI y CE el número de documento solo lleva dígitos.');
        }
        if ($d['fecha_nacimiento'] !== null) {
            $edad = (int)date_diff(date_create($d['fecha_nacimiento']), date_create('today'))->y;
            if ($edad < 14 || $edad > 90) {
                $v->agregarError('La fecha de nacimiento no corresponde a una edad válida (14 a 90 años).');
            }
        }
        if ($conEstado) {
            $d['estado'] = $v->enum('estado', 'El estado', Enums::APRENDIZ_ESTADO, 'matriculado');
        }
        return $d;
    }
}
