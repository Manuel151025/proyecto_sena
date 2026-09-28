<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Quién califica dependía de `c.nombre LIKE '%ETAPA PRÁCTICA%'`: una tilde
 * de menos cambiaba el control de acceso. Pasa a una columna.
 * Antes: migrations/add_competencias_etapa_practica.php
 */
return new class extends Migracion {
    public string $descripcion = 'competencias.es_etapa_practica';

    public function aplicar(PDO $db): void {
        if (!$this->existeColumna($db, 'competencias', 'es_etapa_practica')) {
            $db->exec("
                ALTER TABLE competencias
                  ADD COLUMN es_etapa_practica TINYINT(1) NOT NULL DEFAULT 0
                      COMMENT 'Califica el instructor de seguimiento en lugar del líder de ficha'
                      AFTER nombre,
                  ADD INDEX idx_etapa_practica (es_etapa_practica)
            ");
            $this->informar('columna competencias.es_etapa_practica creada');
            // Solo en la creación: después, la marca la decide el coordinador.
            $st = $db->prepare("UPDATE competencias SET es_etapa_practica = 1
                                 WHERE nombre LIKE '%ETAPA PRÁCTICA%' OR nombre LIKE '%ETAPA PRACTICA%'");
            $st->execute();
            $this->informar('marcadas como etapa práctica: ' . $st->rowCount());
        }
    }
};
