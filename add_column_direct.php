<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/core/Database.php';

use Core\Database;

echo "<h1>Direct DB Migration Diagnostic</h1>";

try {
    $db = Database::getConnection();
    echo "<p>Connected to database successfully.</p>";

    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM aprendices LIKE 'instructor_seguimiento_id'");
    $col = $stmt->fetch();
    if ($col) {
        echo "<p style='color:green;'>Column 'instructor_seguimiento_id' already exists in table 'aprendices'.</p>";
    } else {
        echo "<p>Column 'instructor_seguimiento_id' does not exist. Attempting to create it...</p>";
        try {
            $db->exec("ALTER TABLE aprendices ADD COLUMN instructor_seguimiento_id INT NULL DEFAULT NULL AFTER ficha_id");
            echo "<p style='color:green;'>SUCCESS: Column 'instructor_seguimiento_id' added.</p>";
        } catch (PDOException $ex) {
            echo "<p style='color:red;'>ERROR adding column: " . $ex->getMessage() . "</p>";
        }
    }

    // Try adding foreign key constraint
    try {
        echo "<p>Attempting to add foreign key constraint fk_aprendiz_instructor_seguimiento...</p>";
        $db->exec("ALTER TABLE aprendices ADD CONSTRAINT fk_aprendiz_instructor_seguimiento FOREIGN KEY (instructor_seguimiento_id) REFERENCES usuarios(id) ON DELETE SET NULL");
        echo "<p style='color:green;'>SUCCESS: Foreign key constraint added.</p>";
    } catch (PDOException $ex) {
        echo "<p style='color:orange;'>INFO/ERROR adding foreign key: " . $ex->getMessage() . " (This is normal if it already exists or if the engine doesn't support it, as long as the column is added).</p>";
    }

    // Update ENUM
    try {
        echo "<p>Attempting to update status ENUM in 'aprendices'...</p>";
        $db->exec("ALTER TABLE aprendices MODIFY COLUMN estado ENUM('matriculado','suspendido','desertado','egresado','etapa_practica') DEFAULT 'matriculado'");
        echo "<p style='color:green;'>SUCCESS: Status ENUM updated.</p>";
    } catch (PDOException $ex) {
        echo "<p style='color:red;'>ERROR updating ENUM: " . $ex->getMessage() . "</p>";
    }

    echo "<p><strong>Finished execution.</strong> Please check the messages above.</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>FATAL ERROR: " . $e->getMessage() . "</p>";
}
