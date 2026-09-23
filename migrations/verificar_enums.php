<?php
declare(strict_types=1);

/**
 * Comprueba que Core\Support\Enums siga coincidiendo con el esquema.
 *
 * La lista de valores admitidos vive en PHP para poder validar la entrada
 * antes de tocar la base. El riesgo evidente de esa duplicación es que
 * alguien añada un valor al ENUM y olvide la constante: a partir de ahí el
 * validador rechazaría un valor perfectamente legítimo, y el fallo se
 * manifestaría como "no puedo guardar" sin explicación.
 *
 * Este script compara las dos listas y señala las diferencias en los dos
 * sentidos. Conviene ejecutarlo tras cualquier migración que toque un ENUM.
 *
 * Uso:  php migrations/verificar_enums.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;
use Core\Support\Enums;

try {
    $db = Database::getConnection();

    $stmt = $db->query("
        SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS ct
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'enum'
         ORDER BY t, c
    ");

    $enBase = [];
    foreach ($stmt as $r) {
        preg_match_all("/'((?:[^']|'')*)'/", $r['ct'], $m);
        $enBase[$r['t'] . '.' . $r['c']] = array_map(
            static fn($v) => str_replace("''", "'", $v),
            $m[1]
        );
    }

    $enPhp = Enums::mapaEsquema();
    $problemas = 0;

    foreach ($enBase as $columna => $valores) {
        if (!isset($enPhp[$columna])) {
            printf("  SIN DECLARAR  %-42s (%s)\n", $columna, implode(', ', $valores));
            $problemas++;
            continue;
        }

        $faltanEnPhp  = array_diff($valores, $enPhp[$columna]);
        $sobranEnPhp  = array_diff($enPhp[$columna], $valores);

        if ($faltanEnPhp === [] && $sobranEnPhp === []) {
            printf("  OK            %-42s (%d valores)\n", $columna, count($valores));
            continue;
        }

        $problemas++;
        printf("  DESALINEADO   %s\n", $columna);
        if ($faltanEnPhp !== []) {
            printf("                faltan en Enums.php: %s\n", implode(', ', $faltanEnPhp));
        }
        if ($sobranEnPhp !== []) {
            printf("                sobran en Enums.php: %s\n", implode(', ', $sobranEnPhp));
        }
    }

    foreach (array_diff_key($enPhp, $enBase) as $columna => $_) {
        printf("  NO EXISTE     %-42s declarada en Enums.php pero no en el esquema\n", $columna);
        $problemas++;
    }

    echo "\n";
    if ($problemas === 0) {
        echo "Enums.php coincide con el esquema en las " . count($enBase) . " columnas.\n";
        exit(0);
    }
    echo "$problemas discrepancia(s). Revisa core/Support/Enums.php.\n";
    exit(1);

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
