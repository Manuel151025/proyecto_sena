<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Reconstruye las filas que faltan en `historial_evaluaciones`.
 *
 * Por qué hace falta:
 * RNF02 exige que todo juicio evaluativo tenga trazabilidad. Hasta la
 * introducción de Core\Services\EvaluacionService había cuatro caminos que
 * escribían `evaluaciones.concepto` y solo dos dejaban historial:
 * calificar una evidencia no registraba nada, y el importador solo
 * registraba al ACTUALIZAR, nunca al crear. De ahí que existan 987 juicios
 * emitidos (A o D) y apenas 15 filas de historial.
 *
 * Qué hace:
 * Por cada evaluación con concepto 'A' o 'D' que no tenga NINGUNA fila de
 * historial, crea una fila que documenta el juicio vigente.
 *
 * Qué NO inventa:
 *   - `usuario_id`         sale de `evaluaciones.instructor_id` (dato real:
 *                          es a quien el sistema atribuye la nota).
 *   - `concepto_nuevo`     es el concepto que la evaluación tiene ahora.
 *   - `concepto_anterior`  se fija en 'pendiente', que es literalmente lo
 *                          que había registrado antes: ningún juicio.
 *   - `fecha_cambio`       sale de `evaluaciones.fecha_evaluacion`, o de
 *                          `fecha_creacion` si aquella está vacía.
 *
 * El `motivo` dice de forma explícita que la fila es una reconstrucción y
 * no el registro de un cambio observado. Eso hace la operación auditable y
 * reversible: se identifican y se borran por ese texto.
 *
 * Las evaluaciones 'pendiente' quedan fuera a propósito: no hay juicio que
 * trazar y llenarlas sería ruido.
 *
 * Uso:
 *   php migrations/backfill_historial_evaluaciones.php            (simulacro)
 *   php migrations/backfill_historial_evaluaciones.php --aplicar  (escribe)
 *   php migrations/backfill_historial_evaluaciones.php --revertir (deshace)
 *
 * Es idempotente: ejecutarlo dos veces no duplica nada.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

const MOTIVO_RECONSTRUIDO = 'Registro reconstruido: juicio anterior a la trazabilidad automática (RNF02)';

$aplicar  = in_array('--aplicar', $argv ?? [], true);
$revertir = in_array('--revertir', $argv ?? [], true);

try {
    $db = Database::getConnection();

    // ---------------------------------------------------------------
    // REVERTIR
    // ---------------------------------------------------------------
    if ($revertir) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM historial_evaluaciones WHERE motivo = ?");
        $stmt->execute([MOTIVO_RECONSTRUIDO]);
        $n = (int)$stmt->fetchColumn();

        if ($n === 0) {
            echo "No hay filas reconstruidas que revertir.\n";
            exit(0);
        }

        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM historial_evaluaciones WHERE motivo = ?");
        $stmt->execute([MOTIVO_RECONSTRUIDO]);
        $db->commit();

        echo "Revertido: $n filas reconstruidas eliminadas.\n";
        echo "Las filas de historial reales no se tocaron.\n";
        exit(0);
    }

    // ---------------------------------------------------------------
    // DIAGNÓSTICO
    // ---------------------------------------------------------------
    $total       = (int)$db->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
    $emitidos    = (int)$db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto IN ('A','D')")->fetchColumn();
    $historial   = (int)$db->query("SELECT COUNT(*) FROM historial_evaluaciones")->fetchColumn();

    $sqlHuecos = "
        SELECT e.id, e.instructor_id, e.concepto,
               COALESCE(e.fecha_evaluacion, DATE(e.fecha_creacion)) AS fecha
          FROM evaluaciones e
         WHERE e.concepto IN ('A','D')
           AND NOT EXISTS (
                 SELECT 1 FROM historial_evaluaciones h WHERE h.evaluacion_id = e.id
               )
    ";
    $huecos = $db->query($sqlHuecos)->fetchAll(PDO::FETCH_ASSOC);

    echo "Estado actual\n";
    echo "  evaluaciones totales .................. $total\n";
    echo "  juicios emitidos (A o D) .............. $emitidos\n";
    echo "  filas de historial existentes ......... $historial\n";
    echo "  juicios emitidos SIN historial ........ " . count($huecos) . "\n\n";

    if (count($huecos) === 0) {
        echo "No hay nada que reconstruir.\n";
        exit(0);
    }

    $porConcepto = ['A' => 0, 'D' => 0];
    foreach ($huecos as $h) {
        $porConcepto[$h['concepto']]++;
    }
    echo "Se crearía una fila por cada uno:\n";
    echo "  concepto_anterior 'pendiente' -> 'A' .. {$porConcepto['A']}\n";
    echo "  concepto_anterior 'pendiente' -> 'D' .. {$porConcepto['D']}\n";
    echo "  motivo ................................ \"" . MOTIVO_RECONSTRUIDO . "\"\n\n";

    if (!$aplicar) {
        echo "SIMULACRO: no se ha escrito nada.\n";
        echo "Para aplicarlo:  php migrations/backfill_historial_evaluaciones.php --aplicar\n";
        exit(0);
    }

    // ---------------------------------------------------------------
    // APLICAR
    // ---------------------------------------------------------------
    $db->beginTransaction();

    $insert = $db->prepare("
        INSERT INTO historial_evaluaciones
            (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo, fecha_cambio)
        VALUES (?, ?, 'pendiente', ?, ?, ?)
    ");

    $creadas = 0;
    $omitidas = 0;
    foreach ($huecos as $h) {
        // `historial_evaluaciones.usuario_id` tiene clave foránea a
        // `usuarios.id`. Una evaluación cuyo instructor ya no exista no se
        // puede reconstruir sin inventarse un autor, así que se omite y se
        // informa, en vez de atribuirla a alguien que no fue.
        $comprobar = $db->prepare("SELECT 1 FROM usuarios WHERE id = ?");
        $comprobar->execute([$h['instructor_id']]);
        if (!$comprobar->fetchColumn()) {
            $omitidas++;
            continue;
        }

        $insert->execute([
            (int)$h['id'],
            (int)$h['instructor_id'],
            $h['concepto'],
            MOTIVO_RECONSTRUIDO,
            $h['fecha'] . ' 00:00:00',
        ]);
        $creadas++;
    }

    $db->commit();

    echo "Aplicado.\n";
    echo "  filas de historial creadas ............ $creadas\n";
    if ($omitidas > 0) {
        echo "  omitidas (instructor inexistente) ..... $omitidas\n";
    }
    echo "  historial total ....................... " .
        $db->query("SELECT COUNT(*) FROM historial_evaluaciones")->fetchColumn() . "\n\n";
    echo "Reversible con: php migrations/backfill_historial_evaluaciones.php --revertir\n";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
