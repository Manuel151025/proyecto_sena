<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Rellena las filas 'pendiente' que faltan en `evaluaciones`.
 *
 * Por qué hace falta:
 * `inicializarEvaluacionesAprendiz()` crea una fila 'pendiente' por cada RAP
 * del programa en el momento de matricular. Solo se invoca ahí
 * (MatriculaController::index y AprendizModel::matricular), así que quedan dos
 * huecos:
 *
 *   1. Los aprendices matriculados mientras `includes/functions.php` todavía se
 *      cargaba tarde en el arranque: los `function_exists(...)` de los dos
 *      puntos de llamada devolvían false y la inicialización se saltaba en
 *      silencio. El arranque ya está corregido, pero las filas que no se
 *      crearon entonces nunca se recuperaron.
 *   2. Los RAP creados o importados DESPUÉS de la matrícula: ni
 *      ResultadosAprendizajeModel ni las importaciones de
 *      competencias/estructura rellenan los aprendices ya matriculados.
 *
 * Sin esas filas, el RAP no aparece en /seguimiento ni en /evaluaciones para
 * ese aprendiz: no hay nada que calificar.
 *
 * Es idempotente: solo inserta lo que falta y nunca toca una evaluación ya
 * calificada (el INSERT ... SELECT excluye lo existente, y las nuevas filas
 * entran como 'pendiente' sin comentario ni fecha).
 *
 * Uso:  php migrations/backfill_evaluaciones_pendientes.php [--dry-run]
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    $db = Database::getConnection();
    echo "Conectado a la base de datos" . ($dryRun ? " (simulación, no escribe)" : "") . "...\n";

    // Estados que cuentan como "en formación": son los que necesitan tener sus
    // RAP listos para calificar. 'desertado' y 'egresado' quedan fuera a
    // propósito, para no resucitar expedientes cerrados.
    $estadosActivos = "('matriculado', 'suspendido', 'etapa_practica')";

    $sqlFaltantes = "
        SELECT a.id            AS aprendiz_id,
               a.ficha_id      AS ficha_id,
               f.instructor_id AS instructor_id,
               ra.id           AS ra_id
          FROM aprendices a
          JOIN fichas f                 ON f.id = a.ficha_id
          JOIN competencias c           ON c.programa_id = f.programa_id
          JOIN resultados_aprendizaje ra ON ra.competencia_id = c.id
         WHERE a.estado IN $estadosActivos
           AND NOT EXISTS (
                 SELECT 1 FROM evaluaciones e
                  WHERE e.aprendiz_id = a.id
                    AND e.resultado_aprendizaje_id = ra.id
               )
    ";

    $faltantes = $db->query($sqlFaltantes)->fetchAll(PDO::FETCH_ASSOC);
    $total = count($faltantes);

    if ($total === 0) {
        echo "No falta ninguna evaluación: todos los aprendices activos tienen una fila por cada RAP de su programa.\n";
        exit(0);
    }

    $aprendices = count(array_unique(array_column($faltantes, 'aprendiz_id')));
    echo "Faltan $total filas de evaluación, repartidas en $aprendices aprendices.\n";

    if ($dryRun) {
        $porAprendiz = array_count_values(array_column($faltantes, 'aprendiz_id'));
        arsort($porAprendiz);
        echo "Detalle (aprendiz_id => filas que se crearían):\n";
        foreach (array_slice($porAprendiz, 0, 20, true) as $apId => $n) {
            echo "  aprendiz $apId => $n\n";
        }
        if (count($porAprendiz) > 20) {
            echo "  ... y " . (count($porAprendiz) - 20) . " aprendices más\n";
        }
        echo "Simulación terminada. Ejecuta sin --dry-run para aplicar.\n";
        exit(0);
    }

    $stmt = $db->prepare("
        INSERT INTO evaluaciones
            (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
        VALUES (?, ?, ?, ?, 'pendiente', NULL, NULL)
        ON DUPLICATE KEY UPDATE concepto = concepto
    ");

    $db->beginTransaction();
    $insertadas = 0;
    foreach ($faltantes as $row) {
        $instructorId = (int)($row['instructor_id'] ?? 0);
        $stmt->execute([
            (int)$row['ra_id'],
            (int)$row['aprendiz_id'],
            $instructorId > 0 ? $instructorId : null,
            (int)$row['ficha_id'],
        ]);
        $insertadas += $stmt->rowCount() > 0 ? 1 : 0;
    }
    $db->commit();

    echo "Insertadas $insertadas filas 'pendiente'.\n";

    $restantes = (int)$db->query("SELECT COUNT(*) FROM ($sqlFaltantes) t")->fetchColumn();
    echo $restantes === 0
        ? "Verificación: no queda ninguna evaluación faltante.\n"
        : "ATENCIÓN: todavía quedan $restantes filas faltantes; revisa manualmente.\n";

    echo "Migración completada exitosamente.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
