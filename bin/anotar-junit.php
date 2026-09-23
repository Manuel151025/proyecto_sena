<?php
declare(strict_types=1);

/**
 * Convierte los fallos de un informe JUnit de PHPUnit en anotaciones de
 * GitHub Actions (`::error ...::`).
 *
 *   php bin/anotar-junit.php build/seguridad.xml
 *
 * Las anotaciones aparecen junto al commit y se pueden consultar por la API
 * de GitHub sin permisos de administrador, a diferencia de los registros
 * completos del trabajo. Así cualquiera que colabore puede saber qué prueba
 * falló y por qué.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$archivo = $argv[1] ?? '';
if ($archivo === '' || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php bin/anotar-junit.php <informe.xml>\n");
    exit(0);
}

$xml = @simplexml_load_file($archivo);
if ($xml === false) {
    exit(0);
}

$escapar = static fn(string $s): string => str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $s);
$n = 0;
foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $caso) {
    $detalle = (string)($caso->failure ?? $caso->error ?? '');
    $titulo = trim((string)$caso['class'] . '::' . (string)$caso['name'], ':');
    $archivoCaso = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', (string)$caso['file']);
    $linea = (int)$caso['line'];
    echo '::error file=' . $archivoCaso . ',line=' . $linea . ',title=' . $escapar($titulo) . '::'
        . $escapar(mb_substr($detalle, 0, 1500)) . PHP_EOL;
    if (++$n >= 20) {
        break;
    }
}
