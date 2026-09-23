<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Índices compuestos para las consultas que más se repiten.
 *
 * Los índices existentes son de una sola columna, y MariaDB solo puede
 * aprovechar uno por tabla y acceso. Dos consultas que ahora se ejecutan
 * en cada carga de pantalla filtran por dos columnas a la vez:
 *
 *  - aprendices(ficha_id, estado): el recuento de matriculados de cada
 *    ficha, que sustituyó al contador desnormalizado. Se evalúa una vez
 *    por fila del listado de fichas y del panel del instructor.
 *
 *  - evaluaciones(ficha_id, concepto): la matriz de seguimiento y la
 *    detección de RAP en 'D' para los planes de mejoramiento. Es la tabla
 *    grande del sistema (3.900 filas y creciendo con cada importación).
 *
 * Con el volumen actual la diferencia es de milisegundos; el motivo de
 * añadirlos es que son las dos consultas cuyo coste crece con los datos,
 * y el sistema está pensado para acumular varias promociones.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/** Crea el índice solo si no hay ya uno que empiece por esas columnas. */
function crearIndice(PDO $db, string $tabla, string $nombre, array $columnas): void {
    $existentes = [];
    foreach ($db->query("SHOW INDEX FROM `$tabla`") as $i) {
        $existentes[$i['Key_name']][(int)$i['Seq_in_index']] = $i['Column_name'];
    }

    foreach ($existentes as $k => $partes) {
        ksort($partes);
        if (array_slice(array_values($partes), 0, count($columnas)) === $columnas) {
            printf("  ya existe   %-22s (%s) como '%s'\n", $tabla, implode(',', $columnas), $k);
            return;
        }
    }

    $cols = implode(', ', array_map(static fn($c) => "`$c`", $columnas));
    $db->exec("ALTER TABLE `$tabla` ADD INDEX `$nombre` ($cols)");
    printf("  creado      %-22s (%s) como '%s'\n", $tabla, implode(',', $columnas), $nombre);
}

try {
    $db = Database::getConnection();

    crearIndice($db, 'aprendices',   'idx_ficha_estado',    ['ficha_id', 'estado']);
    crearIndice($db, 'evaluaciones', 'idx_ficha_concepto',  ['ficha_id', 'concepto']);

    echo "\nComprobación del plan de ejecución:\n";
    $consultas = [
        "SELECT COUNT(*) FROM aprendices WHERE ficha_id = 1 AND estado <> 'desertado'",
        "SELECT COUNT(*) FROM evaluaciones WHERE ficha_id = 1 AND concepto = 'D'",
    ];
    foreach ($consultas as $sql) {
        $p = $db->query('EXPLAIN ' . $sql)->fetch(PDO::FETCH_ASSOC);
        printf("  %-62s type=%-6s key=%s\n",
            mb_substr($sql, 0, 60), $p['type'] ?? '?', $p['key'] ?? '(ninguno)');
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
