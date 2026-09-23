<?php
declare(strict_types=1);

/**
 * Comprueba que la base coincide con lo que el código espera.
 *
 *   php bin/verificar-esquema.php
 *
 * 1. No quedan migraciones pendientes.
 * 2. Core\Support\Enums coincide con cada columna ENUM de la base, en los
 *    dos sentidos. Si alguien añade un valor al ENUM y olvida la constante,
 *    el validador rechazaría un valor legítimo y el fallo se vería como un
 *    "no puedo guardar" sin explicación.
 *
 * Termina con código 1 si algo no cuadra, para que el CI lo detenga.
 * Antes: migrations/verificar_enums.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

use Core\Support\Enums;
use Core\Support\Migrador;

$db = conexion();
$problemas = 0;

$pendientes = (new Migrador($db, dirname(__DIR__) . '/database/migraciones'))->pendientes();
if ($pendientes !== []) {
    linea('  PENDIENTES    ' . implode(', ', array_keys($pendientes)));
    $problemas++;
} else {
    linea('  OK            migraciones al día');
}

$stmt = $db->query("
    SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS ct
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'enum'
     ORDER BY t, c
");
$enBase = [];
foreach ($stmt as $r) {
    preg_match_all("/'((?:[^']|'')*)'/", $r['ct'], $m);
    $enBase[$r['t'] . '.' . $r['c']] = array_map(static fn($v) => str_replace("''", "'", $v), $m[1]);
}

$enPhp = Enums::mapaEsquema();
foreach ($enBase as $columna => $valores) {
    if (!isset($enPhp[$columna])) {
        linea(sprintf('  SIN DECLARAR  %-44s (%s)', $columna, implode(', ', $valores)));
        $problemas++;
        continue;
    }
    $faltan = array_diff($valores, $enPhp[$columna]);
    $sobran = array_diff($enPhp[$columna], $valores);
    if ($faltan === [] && $sobran === []) {
        linea(sprintf('  OK            %-44s (%d valores)', $columna, count($valores)));
        continue;
    }
    $problemas++;
    linea("  DESALINEADO   $columna");
    if ($faltan !== []) {
        linea('                faltan en Enums.php: ' . implode(', ', $faltan));
    }
    if ($sobran !== []) {
        linea('                sobran en Enums.php: ' . implode(', ', $sobran));
    }
}
foreach (array_diff_key($enPhp, $enBase) as $columna => $_) {
    linea(sprintf('  NO EXISTE     %-44s declarada en Enums.php pero no en la base', $columna));
    $problemas++;
}

linea();
if ($problemas > 0) {
    abortar("$problemas problema(s). Revisa las migraciones y Core\\Support\\Enums.");
}
linea('Esquema coherente con el código.');
