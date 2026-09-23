<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();
    echo "Conectado a la base de datos...\n";

    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'debe_cambiar_password'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        echo "Agregando columna 'debe_cambiar_password' a la tabla 'usuarios'...\n";
        $db->exec("ALTER TABLE usuarios ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password");
        echo "Columna añadida correctamente.\n";
    } else {
        echo "La columna 'debe_cambiar_password' ya existe.\n";
    }

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
