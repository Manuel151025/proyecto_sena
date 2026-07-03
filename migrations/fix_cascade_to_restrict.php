<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/**
 * programas -> competencias -> resultados_aprendizaje -> evaluaciones estaba
 * encadenado con ON DELETE CASCADE: borrar un programa podia arrastrar todas
 * sus competencias, resultados de aprendizaje y las calificaciones de
 * evaluacion de todos los aprendices ligados a ese programa, en cualquier
 * ficha o cohorte.
 *
 * Los Models (ProgramasModel::delete, CompetenciasModel::delete,
 * ResultadosAprendizajeModel::delete) ya atrapan errores de FK con mensajes
 * amigables ("tiene registros asociados") — ese codigo estaba efectivamente
 * inalcanzable mientras la constraint fuera CASCADE, porque el borrado nunca
 * llegaba a fallar.
 */
try {
    $db = Database::getConnection();
    echo "Conectado a la base de datos...\n";

    $fixes = [
        ['competencias', 'competencias_ibfk_1', 'programa_id', 'programas'],
        ['resultados_aprendizaje', 'resultados_aprendizaje_ibfk_1', 'competencia_id', 'competencias'],
        ['evaluaciones', 'evaluaciones_ibfk_1', 'resultado_aprendizaje_id', 'resultados_aprendizaje'],
    ];

    foreach ($fixes as [$table, $constraint, $column, $refTable]) {
        $stmt = $db->prepare("
            SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ? AND TABLE_NAME = ?
        ");
        $stmt->execute([$constraint, $table]);
        $deleteRule = $stmt->fetchColumn();

        if ($deleteRule === 'CASCADE') {
            echo "Cambiando $table.$constraint de CASCADE a RESTRICT...\n";
            $db->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
            $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$refTable` (`id`) ON DELETE RESTRICT");
            echo "  Listo.\n";
        } elseif ($deleteRule === false) {
            echo "  Advertencia: no se encontro la constraint '$constraint' en '$table'. Revisar manualmente.\n";
        } else {
            echo "  $table.$constraint ya es '$deleteRule', no se requiere cambio.\n";
        }
    }

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
