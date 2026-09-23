<?php
declare(strict_types=1);

/**
 * Crea `configuraciones_sistema`.
 *
 * La tabla no existía en la base de datos, pero ConfiguracionModel la
 * consultaba. El módulo /configuracion parecía funcionar y no guardaba
 * nada: `getAll()` atrapaba la excepción y devolvía un array vacío, de
 * modo que la pantalla se rellenaba con los valores por defecto del
 * controlador y el coordinador veía sus cambios desaparecer al recargar,
 * sin ningún mensaje de error.
 *
 * Ni install.php ni el volcado sena_seguimiento.sql la incluían: es el
 * síntoma de tener tres fuentes de esquema que no coinciden.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS configuraciones_sistema (
            clave               VARCHAR(60) PRIMARY KEY,
            valor               TEXT NOT NULL,
            fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tabla 'configuraciones_sistema' lista.\n";

    // Valores iniciales: los mismos que el controlador usaba como respaldo
    // cuando la consulta fallaba, para que la pantalla no cambie de aspecto.
    $iniciales = [
        'system_title' => 'SENA - Seguimiento de Fichas',
        'regional'     => 'Regional Antioquia - Centro de Servicios y Gestión',
        'pass_score'   => '70%',
        'smtp_server'  => 'smtp.soy.sena.edu.co',
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO configuraciones_sistema (clave, valor) VALUES (?, ?)");
    $creadas = 0;
    foreach ($iniciales as $clave => $valor) {
        $stmt->execute([$clave, $valor]);
        $creadas += $stmt->rowCount();
    }
    echo "Valores iniciales insertados: $creadas (los ya existentes se respetan).\n";

    foreach ($db->query("SELECT clave, valor FROM configuraciones_sistema ORDER BY clave") as $f) {
        printf("  %-14s %s\n", $f['clave'], $f['valor']);
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
