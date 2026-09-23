<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * El plan de mejoramiento como entidad.
 *
 * Hasta ahora /mejoramiento era un `SELECT ... WHERE concepto = 'D'`: el
 * sistema detectaba qué resultados necesitaban nivelación, pero no podía
 * registrar ni el plan (qué debe hacer el aprendiz y hasta cuándo) ni su
 * cierre. Era la mitad del proceso: nada distinguía un RAP en D sin
 * atender de uno con plan en curso, y la nivelación no dejaba rastro.
 *
 * Estados:
 *   abierto      creado por el instructor, aún sin entregas
 *   en_curso     el aprendiz ya trabaja en él (entregó evidencia)
 *   cumplido     cerrado con éxito: el RAP pasa a A por EvaluacionService
 *   no_cumplido  cerrado sin éxito: el RAP sigue en D
 *
 * Un RAP solo puede tener un plan vigente a la vez; lo garantiza el
 * servicio (MySQL no admite índices únicos parciales).
 */
return new class extends Migracion {
    public string $descripcion = 'Tabla planes_mejoramiento';

    public function aplicar(PDO $db): void {
        if ($this->existeTabla($db, 'planes_mejoramiento')) {
            return;
        }
        $db->exec("
            CREATE TABLE planes_mejoramiento (
                id                    INT(11) NOT NULL AUTO_INCREMENT,
                evaluacion_id         INT(11) NOT NULL,
                aprendiz_id           INT(11) NOT NULL,
                ficha_id              INT(11) NOT NULL,
                instructor_id         INT(11) NOT NULL COMMENT 'responsable del plan',
                actividades           TEXT NOT NULL COMMENT 'qué debe hacer el aprendiz',
                fecha_inicio          DATE NOT NULL,
                fecha_limite          DATE NOT NULL,
                estado                ENUM('abierto','en_curso','cumplido','no_cumplido') NOT NULL DEFAULT 'abierto',
                observaciones_cierre  TEXT NULL,
                fecha_cierre          DATETIME NULL,
                cerrado_por           INT(11) NULL,
                creado_por            INT(11) NOT NULL,
                fecha_creacion        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_evaluacion (evaluacion_id),
                KEY idx_aprendiz_estado (aprendiz_id, estado),
                KEY idx_ficha_estado (ficha_id, estado),
                KEY idx_instructor_estado (instructor_id, estado),
                KEY idx_limite (fecha_limite),
                CONSTRAINT fk_plan_evaluacion  FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones (id),
                CONSTRAINT fk_plan_aprendiz    FOREIGN KEY (aprendiz_id)   REFERENCES aprendices (id),
                CONSTRAINT fk_plan_ficha       FOREIGN KEY (ficha_id)      REFERENCES fichas (id),
                CONSTRAINT fk_plan_instructor  FOREIGN KEY (instructor_id) REFERENCES usuarios (id),
                CONSTRAINT fk_plan_cerrado_por FOREIGN KEY (cerrado_por)   REFERENCES usuarios (id),
                CONSTRAINT fk_plan_creado_por  FOREIGN KEY (creado_por)    REFERENCES usuarios (id),
                CONSTRAINT chk_plan_fechas CHECK (fecha_limite >= fecha_inicio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->informar('tabla planes_mejoramiento creada');
    }
};
