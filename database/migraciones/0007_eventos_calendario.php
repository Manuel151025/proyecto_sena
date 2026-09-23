<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Eventos manuales del calendario académico, por ficha.
 * Antes: migrations/create_eventos_calendario.php
 */
return new class extends Migracion {
    public string $descripcion = 'Tabla eventos_calendario';

    public function aplicar(PDO $db): void {
        if ($this->existeTabla($db, 'eventos_calendario')) {
            return;
        }
        $db->exec("
            CREATE TABLE eventos_calendario (
                id int(11) NOT NULL AUTO_INCREMENT,
                titulo varchar(150) NOT NULL,
                descripcion text NULL,
                fecha date NOT NULL,
                ficha_id int(11) NOT NULL,
                creado_por int(11) NOT NULL,
                color varchar(7) NOT NULL DEFAULT '#f59e0b',
                fecha_creacion timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                KEY ficha_id (ficha_id),
                KEY creado_por (creado_por),
                CONSTRAINT evcal_ibfk_1 FOREIGN KEY (ficha_id) REFERENCES fichas (id) ON DELETE CASCADE,
                CONSTRAINT evcal_ibfk_2 FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->informar('tabla eventos_calendario creada');
    }
};
