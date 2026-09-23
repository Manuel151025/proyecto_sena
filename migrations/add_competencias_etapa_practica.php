<?php
declare(strict_types=1);


// Solo por consola. Estos scripts alteran el esquema o reescriben datos;
// accesibles por URL, cualquiera podía ejecutarlos desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * Añade `competencias.es_etapa_practica`.
 *
 * Quién puede calificar un RAP depende de si su competencia es de etapa
 * práctica: en ese caso manda el instructor de seguimiento del aprendiz y
 * no el instructor líder de la ficha. Esa condición se resolvía así, en
 * cuatro consultas distintas:
 *
 *     c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%'
 *
 * Es decir, el control de acceso dependía de cómo estuviera escrito un
 * campo de texto libre. Una competencia registrada como "Etapa Practica"
 * en minúsculas coincide por el COLLATE del esquema, pero "Etapa
 * Práctica-II" con guion, "ETAPA  PRÁCTICA" con doble espacio o cualquier
 * abreviatura dejan de coincidir, y el permiso cambia en silencio: el
 * instructor de seguimiento pierde el acceso y lo gana el líder de ficha.
 * Nadie recibe un error; simplemente califica quien no debía.
 *
 * Con una columna, la regla es explícita, indexable y editable sin tocar
 * el nombre que ve el usuario.
 *
 * La carga inicial aplica el mismo LIKE que había, para no cambiar el
 * comportamiento de golpe: lo que hoy se considera etapa práctica sigue
 * siéndolo. A partir de aquí se corrige en la ficha de la competencia.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();

    $existe = $db->query("SHOW COLUMNS FROM competencias LIKE 'es_etapa_practica'")->fetch();

    if (!$existe) {
        $db->exec("
            ALTER TABLE competencias
              ADD COLUMN es_etapa_practica TINYINT(1) NOT NULL DEFAULT 0
                  COMMENT 'Determina si califica el instructor de seguimiento en lugar del lider de ficha'
                  AFTER nombre,
              ADD INDEX idx_etapa_practica (es_etapa_practica)
        ");
        echo "Columna 'es_etapa_practica' creada.\n";
    } else {
        echo "La columna 'es_etapa_practica' ya existe.\n";
    }

    // Carga inicial con el mismo criterio textual que se usaba antes.
    $stmt = $db->prepare("
        UPDATE competencias
           SET es_etapa_practica = 1
         WHERE es_etapa_practica = 0
           AND (nombre LIKE '%ETAPA PRÁCTICA%' OR nombre LIKE '%ETAPA PRACTICA%')
    ");
    $stmt->execute();
    echo "Competencias marcadas como etapa práctica: " . $stmt->rowCount() . "\n";

    $total   = (int)$db->query("SELECT COUNT(*) FROM competencias")->fetchColumn();
    $marcadas = (int)$db->query("SELECT COUNT(*) FROM competencias WHERE es_etapa_practica = 1")->fetchColumn();
    echo "Estado: $marcadas de $total competencias son de etapa práctica.\n";

    if ($marcadas > 0) {
        echo "\nCuáles:\n";
        foreach ($db->query("SELECT id, codigo, nombre FROM competencias WHERE es_etapa_practica = 1") as $c) {
            printf("  #%-4s %-14s %s\n", $c['id'], $c['codigo'], mb_substr($c['nombre'], 0, 60));
        }
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
