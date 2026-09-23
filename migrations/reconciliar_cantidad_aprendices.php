<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Pone `fichas.cantidad_aprendices` de acuerdo con la realidad.
 *
 * La columna es un contador desnormalizado que se incrementaba y decrementaba
 * a mano en cuatro sitios (MatriculaController, AprendizModel en tres puntos
 * y JuiciosImportService), nadie recalculaba nunca, y además era editable
 * desde el formulario de la ficha. Con dos escritores independientes —el
 * automático y el humano— la desincronización era cuestión de tiempo: al
 * detectarlo, 3 de las 7 fichas mostraban un número que no coincidía con sus
 * matrículas.
 *
 * A partir de ahora los listados calculan el recuento al leer, así que la
 * columna deja de ser la fuente de verdad. Este script la deja coherente
 * para cualquier consulta antigua que todavía la mire y para que el dato
 * almacenado no contradiga a la pantalla.
 *
 * Los aprendices en estado 'desertado' no cuentan: han dejado la ficha.
 *
 * Uso:
 *   php migrations/reconciliar_cantidad_aprendices.php            (simulacro)
 *   php migrations/reconciliar_cantidad_aprendices.php --aplicar
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$aplicar = in_array('--aplicar', $argv ?? [], true);

try {
    $db = Database::getConnection();

    $filas = $db->query("
        SELECT f.id,
               f.numero_ficha,
               f.cantidad_aprendices AS guardado,
               (SELECT COUNT(*) FROM aprendices a
                 WHERE a.ficha_id = f.id AND a.estado <> 'desertado') AS real_n
          FROM fichas f
         ORDER BY f.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $desviadas = array_filter($filas, static fn($f) => (int)$f['guardado'] !== (int)$f['real_n']);

    printf("%-6s %-14s %10s %10s  %s\n", 'FICHA', 'NÚMERO', 'GUARDADO', 'REAL', 'ESTADO');
    echo str_repeat('-', 60) . "\n";
    foreach ($filas as $f) {
        $desvio = (int)$f['guardado'] !== (int)$f['real_n'];
        printf(
            "%-6s %-14s %10s %10s  %s\n",
            $f['id'], $f['numero_ficha'], $f['guardado'], $f['real_n'],
            $desvio ? 'DESVIADO' : 'correcto'
        );
    }

    echo "\nFichas desviadas: " . count($desviadas) . " de " . count($filas) . "\n";

    if (count($desviadas) === 0) {
        echo "No hay nada que reconciliar.\n";
        exit(0);
    }

    if (!$aplicar) {
        echo "\nSIMULACRO: no se ha escrito nada.\n";
        echo "Para aplicarlo:  php migrations/reconciliar_cantidad_aprendices.php --aplicar\n";
        exit(0);
    }

    $db->beginTransaction();
    $stmt = $db->prepare("UPDATE fichas SET cantidad_aprendices = ? WHERE id = ?");
    foreach ($desviadas as $f) {
        $stmt->execute([(int)$f['real_n'], (int)$f['id']]);
    }
    $db->commit();

    echo "\nAplicado: " . count($desviadas) . " fichas corregidas.\n";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
