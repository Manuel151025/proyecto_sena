<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS `asignaciones` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ficha_id` int(11) NOT NULL,
            `competencia_id` int(11) NOT NULL,
            `instructor_id` int(11) NOT NULL,
            `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_ficha_competencia` (`ficha_id`, `competencia_id`),
            KEY `competencia_id` (`competencia_id`),
            KEY `instructor_id` (`instructor_id`),
            CONSTRAINT `asig_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
            CONSTRAINT `asig_ibfk_2` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`) ON DELETE CASCADE,
            CONSTRAINT `asig_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "✅ Tabla 'asignaciones' creada correctamente.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
