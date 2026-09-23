<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Validador;

/** Reglas de entrada del envío y de la revisión de una evidencia. */
final class EvidenciaFormulario {
    public const MAX_TITULO = 150;
    public const MAX_DESCRIPCION = 2000;
    public const MAX_RETRO = 1000;
    /** Formatos admitidos (ArchivoSubido comprueba contenido y firma). */
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'txt'];
    /** Igual al límite de subida del servidor de producción (Dockerfile). */
    public const MAX_MB = 10;
    /** Estados que puede dar quien revisa. */
    public const REVISION = [
        'aprobada'  => 'Aprobada',
        'revisada'  => 'Requiere ajustes',
        'rechazada' => 'Rechazada',
    ];

    public static function validarEnvio(Validador $v): array {
        return [
            'titulo'        => $v->nombre('titulo', 'El título', 3, self::MAX_TITULO),
            'descripcion'   => $v->texto('descripcion', 'La descripción', 0, self::MAX_DESCRIPCION, false),
            'evaluacion_id' => $v->id('evaluacion_id', 'El resultado de aprendizaje', false) ?: null,
        ];
    }

    public static function validarRevision(Validador $v): array {
        return [
            'id'                => $v->id('id', 'La evidencia'),
            'estado'            => $v->enum('estado', 'La decisión', array_keys(self::REVISION)),
            'retroalimentacion' => $v->texto('retroalimentacion', 'La retroalimentación', 5, self::MAX_RETRO),
            // Cambiar el juicio del RAP es una decisión aparte y explícita.
            'juicio'            => $v->enum('juicio', 'El juicio del RAP', ['', 'A', 'D'], '', false),
        ];
    }
}
