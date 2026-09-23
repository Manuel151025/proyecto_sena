<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Avisos internos de la campana. El script antiguo además insertaba avisos
 * de ejemplo; eso ahora es cosa de la semilla de demostración.
 * Antes: migrations/create_notificaciones.php
 */
return new class extends Migracion {
    public string $descripcion = 'Tabla notificaciones';

    public function aplicar(PDO $db): void {
        if ($this->existeTabla($db, 'notificaciones')) {
            return;
        }
        $db->exec("
            CREATE TABLE notificaciones (
                id int(11) NOT NULL AUTO_INCREMENT,
                usuario_id int(11) NOT NULL,
                titulo varchar(255) NOT NULL,
                mensaje text NOT NULL,
                tipo varchar(50) DEFAULT 'info',
                url varchar(255) DEFAULT NULL,
                leida tinyint(1) DEFAULT 0,
                fecha_creacion timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                KEY idx_usuario_leida (usuario_id, leida),
                CONSTRAINT notificaciones_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->informar('tabla notificaciones creada');
    }
};
