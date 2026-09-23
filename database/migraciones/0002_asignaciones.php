<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Asignación de instructores por competencia dentro de una ficha. Es la
 * tabla de la que depende el control de acceso fino del instructor.
 * Antes: migrations/create_asignaciones.php
 */
return new class extends Migracion {
    public string $descripcion = 'Tabla asignaciones (instructor por competencia y ficha)';

    public function aplicar(PDO $db): void {
        if ($this->existeTabla($db, 'asignaciones')) {
            return;
        }
        $db->exec("
            CREATE TABLE asignaciones (
                id int(11) NOT NULL AUTO_INCREMENT,
                ficha_id int(11) NOT NULL,
                competencia_id int(11) NOT NULL,
                instructor_id int(11) NOT NULL,
                fecha_asignacion timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                UNIQUE KEY unique_ficha_competencia (ficha_id, competencia_id),
                KEY competencia_id (competencia_id),
                KEY instructor_id (instructor_id),
                CONSTRAINT asig_ibfk_1 FOREIGN KEY (ficha_id) REFERENCES fichas (id) ON DELETE CASCADE,
                CONSTRAINT asig_ibfk_2 FOREIGN KEY (competencia_id) REFERENCES competencias (id) ON DELETE CASCADE,
                CONSTRAINT asig_ibfk_3 FOREIGN KEY (instructor_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->informar('tabla asignaciones creada');
    }
};
