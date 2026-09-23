<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Unicidad de códigos: la competencia por (programa, código) —una
 * competencia transversal repite código entre programas— y el RAP global.
 * Antes: migrations/add_unique_codigos.php
 */
return new class extends Migracion {
    public string $descripcion = 'UNIQUE en códigos de competencias y RAP';

    public function aplicar(PDO $db): void {
        if ($this->existeIndice($db, 'competencias', 'uq_competencias_codigo')) {
            $db->exec("ALTER TABLE competencias DROP INDEX uq_competencias_codigo");
        }
        if (!$this->existeIndice($db, 'competencias', 'uq_competencias_programa_codigo')) {
            $dup = (int)$db->query("SELECT COUNT(*) FROM (SELECT 1 FROM competencias WHERE codigo IS NOT NULL
                                    GROUP BY programa_id, codigo HAVING COUNT(*) > 1) d")->fetchColumn();
            if ($dup > 0) {
                throw new RuntimeException("hay $dup códigos de competencia repetidos dentro de un mismo programa; corríjalos antes de continuar");
            }
            $db->exec("ALTER TABLE competencias ADD UNIQUE KEY uq_competencias_programa_codigo (programa_id, codigo)");
            $this->informar('UNIQUE (programa_id, codigo) en competencias');
        }
        if (!$this->existeIndice($db, 'resultados_aprendizaje', 'uq_resultados_aprendizaje_codigo')
            && !$this->existeIndice($db, 'resultados_aprendizaje', 'codigo')) {
            $dup = (int)$db->query("SELECT COUNT(*) FROM (SELECT 1 FROM resultados_aprendizaje
                                    GROUP BY codigo HAVING COUNT(*) > 1) d")->fetchColumn();
            if ($dup > 0) {
                throw new RuntimeException("hay $dup códigos de RAP repetidos; corríjalos antes de continuar");
            }
            $db->exec("ALTER TABLE resultados_aprendizaje ADD UNIQUE KEY uq_resultados_aprendizaje_codigo (codigo)");
            $this->informar('UNIQUE (codigo) en resultados_aprendizaje');
        }
    }
};
