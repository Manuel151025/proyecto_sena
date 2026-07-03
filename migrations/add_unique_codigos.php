<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/**
 * competencias.codigo y resultados_aprendizaje.codigo son codigos oficiales
 * SENA (analogos a programas.codigo / proyectos.codigo, que si son UNIQUE),
 * pero solo tenian un indice simple: nada impedia registrar el mismo codigo
 * dos veces.
 */
try {
    $db = Database::getConnection();
    echo "Conectado a la base de datos...\n";

    $fixes = [
        ['competencias', 'codigo', 'uq_competencias_codigo'],
        ['resultados_aprendizaje', 'codigo', 'uq_resultados_aprendizaje_codigo'],
    ];

    foreach ($fixes as [$table, $column, $indexName]) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $indexName]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            echo "El indice '$indexName' ya existe en '$table', no se requiere cambio.\n";
            continue;
        }

        $dupStmt = $db->query("
            SELECT `$column`, COUNT(*) c FROM `$table`
            WHERE `$column` IS NOT NULL
            GROUP BY `$column` HAVING c > 1
        ");
        $duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($duplicates)) {
            echo "  ADVERTENCIA: '$table.$column' tiene valores duplicados, no se puede agregar UNIQUE todavia:\n";
            foreach ($duplicates as $d) {
                echo "    - '{$d[$column]}' aparece {$d['c']} veces\n";
            }
            continue;
        }

        echo "Agregando indice UNIQUE '$indexName' a '$table.$column'...\n";
        $db->exec("ALTER TABLE `$table` ADD UNIQUE KEY `$indexName` (`$column`)");
        echo "  Listo.\n";
    }

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
