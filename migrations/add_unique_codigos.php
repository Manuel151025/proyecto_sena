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
 *
 * competencias.codigo NO es unico globalmente: SENA tiene competencias
 * transversales (Ingles, Etica, Etapa Practica, Constitucion Politica) que
 * comparten el mismo codigo oficial en varios programas de formacion
 * distintos, cada una como fila independiente. Confirmado contra datos
 * reales de produccion (codigos '1','2','3226','3227','38558' repetidos
 * con el mismo nombre pero distinto programa_id). El indice correcto es
 * compuesto: unico por (programa_id, codigo), no por codigo solo.
 *
 * resultados_aprendizaje.codigo si se mantiene unico globalmente: no se
 * encontraron duplicados reales y su formato (ligado a competencia_id) no
 * presenta el mismo patron transversal.
 */
try {
    $db = Database::getConnection();
    echo "Conectado a la base de datos...\n";

    // Revertir el indice global (solo codigo) de una version anterior de esta
    // migracion, si llegó a aplicarse: rompe con las competencias transversales.
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competencias' AND INDEX_NAME = 'uq_competencias_codigo'
    ");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) {
        echo "Eliminando indice global incorrecto 'uq_competencias_codigo' (no soporta competencias transversales)...\n";
        $db->exec("ALTER TABLE `competencias` DROP INDEX `uq_competencias_codigo`");
        echo "  Listo.\n";
    }

    // --- competencias: UNIQUE compuesto (programa_id, codigo) ---
    $indexName = 'uq_competencias_programa_codigo';
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competencias' AND INDEX_NAME = ?
    ");
    $stmt->execute([$indexName]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "El indice '$indexName' ya existe en 'competencias', no se requiere cambio.\n";
    } else {
        $dupStmt = $db->query("
            SELECT programa_id, codigo, COUNT(*) c FROM competencias
            WHERE codigo IS NOT NULL
            GROUP BY programa_id, codigo HAVING c > 1
        ");
        $duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($duplicates)) {
            echo "  ADVERTENCIA: 'competencias' tiene el mismo codigo repetido dentro del mismo programa, no se puede agregar UNIQUE todavia:\n";
            foreach ($duplicates as $d) {
                echo "    - programa_id {$d['programa_id']}, codigo '{$d['codigo']}' aparece {$d['c']} veces\n";
            }
        } else {
            echo "Agregando indice UNIQUE compuesto '$indexName' a 'competencias' (programa_id, codigo)...\n";
            $db->exec("ALTER TABLE `competencias` ADD UNIQUE KEY `$indexName` (`programa_id`, `codigo`)");
            echo "  Listo.\n";
        }
    }

    // --- resultados_aprendizaje: UNIQUE simple (codigo) ---
    $table = 'resultados_aprendizaje';
    $column = 'codigo';
    $indexName = 'uq_resultados_aprendizaje_codigo';

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $indexName]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if ($exists) {
        echo "El indice '$indexName' ya existe en '$table', no se requiere cambio.\n";
    } else {
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
        } else {
            echo "Agregando indice UNIQUE '$indexName' a '$table.$column'...\n";
            $db->exec("ALTER TABLE `$table` ADD UNIQUE KEY `$indexName` (`$column`)");
            echo "  Listo.\n";
        }
    }

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
