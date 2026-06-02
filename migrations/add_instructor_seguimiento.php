<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();
    echo "Conectado a la base de datos...\n";

    // 1. Verificar si la columna instructor_seguimiento_id existe
    $stmt = $db->query("SHOW COLUMNS FROM aprendices LIKE 'instructor_seguimiento_id'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        echo "Agregando columna 'instructor_seguimiento_id' a la tabla 'aprendices'...\n";
        $db->exec("ALTER TABLE aprendices ADD COLUMN instructor_seguimiento_id INT NULL DEFAULT NULL AFTER ficha_id");
        
        echo "Agregando llave foránea a 'usuarios(id)'...\n";
        $db->exec("ALTER TABLE aprendices ADD CONSTRAINT fk_aprendiz_instructor_seguimiento FOREIGN KEY (instructor_seguimiento_id) REFERENCES usuarios(id) ON DELETE SET NULL");
        
        echo "Columna y llave foránea añadidas correctamente.\n";
    } else {
        echo "La columna 'instructor_seguimiento_id' ya existe.\n";
    }

    // 2. Modificar el ENUM de estado para incluir 'etapa_practica'
    echo "Actualizando ENUM de estado en la tabla 'aprendices'...\n";
    $db->exec("ALTER TABLE aprendices MODIFY COLUMN estado ENUM('matriculado','suspendido','desertado','egresado','etapa_practica') DEFAULT 'matriculado'");
    echo "ENUM de estado actualizado correctamente.\n";

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "ERROR durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
