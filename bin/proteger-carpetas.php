<?php
declare(strict_types=1);

/**
 * Deja un .htaccess de denegación en las carpetas que se generan al
 * instalar o al ejecutar (vendor/ y cache/), que no pueden versionarlo.
 *
 * Composer lo ejecuta solo tras cada `composer install`/`dump-autoload`
 * (ver "post-autoload-dump" en composer.json). Es la segunda capa: la
 * primera es la regla de mod_rewrite del .htaccess de la raíz.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$contenido = <<<HT
# Generado por bin/proteger-carpetas.php: carpeta interna, nunca pública.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Deny,Allow
    Deny from all
</IfModule>

HT;

foreach (['vendor', 'cache'] as $carpeta) {
    $ruta = $raiz . DIRECTORY_SEPARATOR . $carpeta;
    if (!is_dir($ruta) && !@mkdir($ruta, 0775, true)) {
        fwrite(STDERR, "No se pudo crear $carpeta/\n");
        continue;
    }
    $archivo = $ruta . DIRECTORY_SEPARATOR . '.htaccess';
    if (@file_put_contents($archivo, $contenido) === false) {
        fwrite(STDERR, "No se pudo escribir $carpeta/.htaccess\n");
    }
}
