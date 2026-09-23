<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Instructor de seguimiento individual del aprendiz (etapa práctica) y el
 * estado `etapa_practica` en la matrícula.
 * Antes: migrations/add_instructor_seguimiento.php
 */
return new class extends Migracion {
    public string $descripcion = 'Instructor de seguimiento y estado etapa_practica en aprendices';

    public function aplicar(PDO $db): void {
        if (!$this->existeColumna($db, 'aprendices', 'instructor_seguimiento_id')) {
            $db->exec("ALTER TABLE aprendices ADD COLUMN instructor_seguimiento_id INT NULL DEFAULT NULL AFTER ficha_id");
            $this->informar('columna aprendices.instructor_seguimiento_id creada');
        }
        if (!$this->existeRestriccion($db, 'aprendices', 'fk_aprendiz_instructor_seguimiento')) {
            $db->exec("ALTER TABLE aprendices ADD CONSTRAINT fk_aprendiz_instructor_seguimiento
                       FOREIGN KEY (instructor_seguimiento_id) REFERENCES usuarios(id) ON DELETE SET NULL");
        }
        $tipo = (string)$this->tipoColumna($db, 'aprendices', 'estado');
        if (!str_contains($tipo, "'etapa_practica'")) {
            $db->exec("ALTER TABLE aprendices MODIFY COLUMN estado
                       ENUM('matriculado','suspendido','desertado','egresado','etapa_practica') NOT NULL DEFAULT 'matriculado'");
            $this->informar('enum aprendices.estado ampliado con etapa_practica');
        }
    }
};
