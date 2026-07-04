<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS `eventos_calendario` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo` varchar(150) NOT NULL,
            `descripcion` text NULL,
            `fecha` date NOT NULL,
            `ficha_id` int(11) NOT NULL,
            `creado_por` int(11) NOT NULL,
            `color` varchar(7) NOT NULL DEFAULT '#f59e0b',
            `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `ficha_id` (`ficha_id`),
            KEY `creado_por` (`creado_por`),
            CONSTRAINT `evcal_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
            CONSTRAINT `evcal_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "✅ Tabla 'eventos_calendario' creada correctamente.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
