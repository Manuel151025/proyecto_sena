<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Bibliotecas de terceros que una vista puede pedir por nombre
 * (`$scriptsCdn[] = 'fullcalendar'`). Solo estas, con su hash de integridad
 * (SRI): si el CDN sirviera otro contenido, el navegador no lo ejecuta.
 */
final class RecursosCdn {
    public const SCRIPTS = [
        'fullcalendar' => [
            ['https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js',
             'sha384-5JIwZN3kuxX2zKsavvNmbZ3zhZZMUtu/eQiK3BbXukpSXp0Cd2ZP4OAYKx7mrPgI'],
            // El idioma estaba enlazado desde una ruta que no existe y el
            // calendario salía en inglés.
            ['https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/es.global.min.js',
             'sha384-cbWTKHcCEJ2+hxgYjdtf8NabqzATKWy5P0INfIVl7OYEEQd5JvMTECWiyswNhvyF'],
        ],
    ];

    /** @return list<array{0:string,1:string}> */
    public static function scripts(array $nombres): array {
        $r = [];
        foreach (array_unique($nombres) as $n) {
            foreach (self::SCRIPTS[$n] ?? [] as $s) {
                $r[] = $s;
            }
        }
        return $r;
    }
}
