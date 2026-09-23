<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * RF02 pide "asignación de proyectos formativos a fichas con fases y
 * actividades". Las tres cosas existían, pero sueltas: una actividad
 * pertenecía a una ficha y a una competencia, y a ninguna fase. No había
 * forma de responder "¿cómo va la ficha en la fase de Ejecución?", que es
 * la pregunta que hace el seguimiento de un proyecto formativo.
 *
 * Con `fase_id`, el avance de cada fase para cada ficha se calcula a partir
 * de sus actividades, en lugar de un porcentaje tecleado a mano.
 *
 * RESTRICT: no se puede borrar una fase que ya tiene actividades
 * registradas; se perdería la trazabilidad de lo ejecutado.
 */
return new class extends Migracion {
    public string $descripcion = 'actividades.fase_id: cada actividad pertenece a una fase del proyecto';

    public function aplicar(PDO $db): void {
        if (!$this->existeColumna($db, 'actividades', 'fase_id')) {
            $db->exec("ALTER TABLE actividades ADD COLUMN fase_id INT(11) NULL DEFAULT NULL AFTER competencia_id");
            $this->informar('columna actividades.fase_id creada');
        }
        if (!$this->existeRestriccion($db, 'actividades', 'fk_actividades_fase')) {
            $db->exec("ALTER TABLE actividades ADD CONSTRAINT fk_actividades_fase
                       FOREIGN KEY (fase_id) REFERENCES fases_proyecto(id) ON DELETE RESTRICT");
        }
        $this->asegurarIndice($db, 'actividades', 'idx_ficha_fase', ['ficha_id', 'fase_id']);
    }
};
