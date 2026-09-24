<?php
declare(strict_types=1);

/**
 * Genera la documentación que sale del propio código, para que no se
 * desactualice a mano:
 *
 *   docs/RUTAS_Y_PERMISOS.md   tabla de rutas y acciones con sus roles
 *   docs/DATOS.md              diccionario de datos y diagrama entidad-relación
 *   docs/FORMATOS_IMPORTACION.md  columnas y reglas de cada importación
 *
 * Uso:  php bin/generar-docs.php
 * (lee la base configurada en .env; ejecútalo tras migrar)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/config.php';

use Core\Database;
use Core\Router;

$raiz = dirname(__DIR__);
// Sin fecha: regenerar sin cambios de fondo no debe producir un diff.
$aviso = "<!-- Generado por bin/generar-docs.php. No editar a mano: vuelve a ejecutar el script. -->\n\n";
$rolCorto = [ROL_COORDINADOR => 'Coordinador', ROL_INSTRUCTOR => 'Instructor', ROL_APRENDIZ => 'Aprendiz'];
$roles = static fn(array $r) => implode(', ', array_map(static fn($x) => $rolCorto[$x] ?? $x, $r));
$corto = static fn(string $c) => str_replace('Core\\Controllers\\', '', $c);

// -------------------------------------------------------------------------
// RUTAS Y PERMISOS
// -------------------------------------------------------------------------
$router = new Router();
(require $raiz . '/config/rutas.php')($router);
$md = "# Rutas y permisos\n\n" . $aviso;
$md .= "La tabla de rutas (`config/rutas.php`) es también la matriz de control de acceso: cada pantalla y cada operación declara qué roles la alcanzan. "
     . "El enrutador rechaza a los demás antes de llegar al controlador, y los servicios vuelven a comprobar el permiso sobre el dato concreto "
     . "(la ficha, el RAP, el aprendiz).\n\n";
$md .= "- **Pantallas y descargas** (`GET`), y envíos sin `action` (`POST`).\n- **Acciones**: `POST` a la misma ruta con el campo `action`; cada una con su propio permiso.\n\n";
$md .= "| Método | Ruta | Acción | Controlador::método | Roles |\n|---|---|---|---|---|\n";
$filas = [];
foreach ($router->rutas() as $clave => $r) {
    [$metodo, $ruta] = explode(' ', $clave, 2);
    $filas[] = [$ruta, $metodo === 'GET' ? 0 : 1, "| $metodo | `$ruta` | — | " . $corto($r['controller']) . '::' . $r['action'] . ' | ' . $roles($r['roles']) . ' |'];
}
foreach ($router->acciones() as $ruta => $acciones) {
    foreach ($acciones as $nombre => $r) {
        $filas[] = [$ruta, 2, "| POST | `$ruta` | `$nombre` | " . $corto($r['controller']) . '::' . $r['action'] . ' | ' . $roles($r['roles']) . ' |'];
    }
}
usort($filas, static fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
$md .= implode("\n", array_column($filas, 2)) . "\n\n";
$total = count($filas);
$md .= "**$total destinos.** La prueba `tests/Integration/RutasYPermisosTest.php` cuenta este número: añadir o quitar una ruta obliga a revisar su permiso.\n";
file_put_contents($raiz . '/docs/RUTAS_Y_PERMISOS.md', $md);
echo "docs/RUTAS_Y_PERMISOS.md ($total destinos)\n";

// -------------------------------------------------------------------------
// DICCIONARIO DE DATOS
// -------------------------------------------------------------------------
$db = Database::getConnection();
$descripciones = [
    'usuarios' => 'Cuentas de acceso de los tres roles. La contraseña se guarda con password_hash; `debe_cambiar_password` obliga a cambiar la temporal.',
    'intentos_acceso' => 'Intentos fallidos de inicio de sesión por correo e IP, para el bloqueo temporal.',
    'password_resets' => 'Solicitudes de recuperación de contraseña; el token se guarda con hash y caduca.',
    'programas' => 'Programas de formación del SENA (ADSO, Contabilidad…).',
    'competencias' => 'Competencias de cada programa; `es_etapa_practica` marca las que califica el instructor de seguimiento.',
    'resultados_aprendizaje' => 'Resultados de aprendizaje (RAP) de cada competencia: la unidad que se evalúa.',
    'proyectos' => 'Proyectos formativos, asociados a un programa.',
    'fases_proyecto' => 'Fases de cada proyecto formativo (análisis, planeación, ejecución, evaluación…).',
    'fichas' => 'Grupos de formación: programa, proyecto formativo, instructor líder, estado y fechas.',
    'aprendices' => 'Matrícula de cada aprendiz en una ficha, con su documento, estado e instructor de seguimiento.',
    'asignaciones' => 'Quién califica cada competencia en una ficha (si no hay asignación, el líder).',
    'actividades' => 'Actividades de aprendizaje de una ficha dentro de una fase del proyecto; su avance da el avance del proyecto.',
    'evaluaciones' => 'Un juicio por aprendiz y RAP: A, D o pendiente. `instructor_id` es quien responde por él.',
    'historial_evaluaciones' => 'Cada cambio de juicio: anterior, nuevo, motivo, quién y cuándo (RNF02).',
    'evidencias' => 'Entregas de los aprendices, opcionalmente ligadas a un RAP, con su revisión.',
    'retroalimentacion' => 'Fortalezas, aspectos a mejorar y recomendaciones; las privadas no las ve el aprendiz.',
    'planes_mejoramiento' => 'Planes de mejoramiento de RAP en D: actividades, plazo, estado y cierre.',
    'notificaciones' => 'Avisos internos a cada usuario.',
    'eventos_calendario' => 'Eventos creados a mano en el calendario de una ficha.',
    'logs_sistema' => 'Bitácora de auditoría: quién hizo qué, sobre qué registro y desde qué IP.',
    'configuraciones_sistema' => 'Parámetros institucionales editables (nombre del sistema, centro de formación).',
    'migraciones' => 'Migraciones de esquema aplicadas (bin/migrar.php).',
];
$tablas = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
$fks = $db->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                     FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
$fkPor = [];
foreach ($fks as $f) {
    $fkPor[$f['TABLE_NAME']][$f['COLUMN_NAME']] = $f['REFERENCED_TABLE_NAME'] . '.' . $f['REFERENCED_COLUMN_NAME'];
}
$md = "# Diccionario de datos\n\n" . $aviso;
$md .= "Base MariaDB/MySQL, motor InnoDB, `utf8mb4`. " . count($tablas) . " tablas. El esquema versionado está en `database/esquema.sql` "
     . "y los cambios en `database/migraciones/`.\n\n## Diagrama entidad-relación\n\n```mermaid\nerDiagram\n";
foreach ($fks as $f) {
    $md .= sprintf("    %s ||--o{ %s : \"%s\"\n", $f['REFERENCED_TABLE_NAME'], $f['TABLE_NAME'], $f['COLUMN_NAME']);
}
$md .= "```\n\n## Tablas\n\n";
foreach ($tablas as $t) {
    $md .= "### `$t`\n\n" . ($descripciones[$t] ?? '') . "\n\n| Columna | Tipo | Nulo | Por defecto | Clave / referencia |\n|---|---|---|---|---|\n";
    $cols = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $db->quote($t) . " ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        $clave = trim(($c['COLUMN_KEY'] === 'PRI' ? 'PK ' : ($c['COLUMN_KEY'] === 'UNI' ? 'Única ' : ''))
               . (isset($fkPor[$t][$c['COLUMN_NAME']]) ? '→ ' . $fkPor[$t][$c['COLUMN_NAME']] : '')
               . (str_contains((string)$c['EXTRA'], 'auto_increment') ? ' autoincremental' : ''));
        $md .= sprintf("| `%s` | %s | %s | %s | %s |\n", $c['COLUMN_NAME'], str_replace('|', '\\|', $c['COLUMN_TYPE']),
            $c['IS_NULLABLE'] === 'YES' ? 'sí' : 'no', $c['COLUMN_DEFAULT'] === null ? '—' : '`' . $c['COLUMN_DEFAULT'] . '`', $clave);
    }
    $md .= "\n";
}
file_put_contents($raiz . '/docs/DATOS.md', $md);
echo "docs/DATOS.md (" . count($tablas) . " tablas)\n";

// -------------------------------------------------------------------------
// FORMATOS DE IMPORTACIÓN
// -------------------------------------------------------------------------
$importadores = [
    '/usuarios/importar' => [Core\Importacion\ImportadorUsuarios::class, 'Coordinación'],
    '/competencias/importar' => [Core\Importacion\ImportadorCompetencias::class, 'Coordinación'],
    '/resultados-aprendizaje/importar' => [Core\Importacion\ImportadorResultados::class, 'Coordinación'],
    '/matriculas/importar' => [Core\Importacion\ImportadorMatriculas::class, 'Coordinación'],
    '/evaluaciones/importar' => [Core\Importacion\ImportadorJuicios::class, 'Instructor y coordinación'],
];
$md = "# Formatos de importación\n\n" . $aviso;
$md .= "Todas las importaciones tabulares siguen dos pasos: **analizar** (se lee el archivo, se valida fila por fila y se muestra una vista previa; "
     . "no se guarda nada) y **confirmar** (se guardan solo las filas válidas, en una transacción). Detalles comunes:\n\n"
     . "- Formatos: CSV (coma, punto y coma, tabulador o barra; UTF-8 o Windows-1252, detectados solos), XLSX y XLS.\n"
     . "- Tamaño máximo: " . Core\Importacion\LectorTabular::MAX_MB . " MB. El contenido se comprueba (tipo real y firma del archivo), no solo la extensión.\n"
     . "- Encabezados en cualquier orden, con o sin tildes; se aceptan los alias indicados. Sin encabezado reconocible, se usa el orden de la plantilla.\n"
     . "- Cada pantalla ofrece la **plantilla CSV** con los encabezados y una fila de ejemplo.\n"
     . "- La vista previa se guarda fuera de la web y caduca a la hora.\n\n";
foreach ($importadores as $ruta => [$clase, $quien]) {
    $db->beginTransaction();
    $imp = new $clase($db);
    $db->rollBack();
    $md .= "## " . ucfirst($imp->titulo()) . "\n\nRuta: `$ruta` · Quién: $quien · Máximo de filas: " . number_format($imp->maxFilas(), 0, ',', '.') . "\n\n";
    $md .= "| Columna | Descripción | Obligatoria | También se acepta |\n|---|---|---|---|\n";
    foreach ($imp->columnas() as $col => $def) {
        $md .= sprintf("| `%s` | %s%s | %s | %s |\n", $col, $def['etiqueta'], isset($def['ayuda']) ? ' — ' . $def['ayuda'] : '',
            !empty($def['obligatorio']) ? 'sí' : 'no', implode(', ', array_map(static fn($a) => "`$a`", $def['alias'] ?? [])) ?: '—');
    }
    $md .= "\n**Ejemplo:** " . implode(' · ', array_map(static fn($k, $v) => "$k=`$v`", array_keys($imp->ejemplo()), $imp->ejemplo())) . "\n\n";
    $md .= "**Reglas:**\n\n" . implode("\n", array_map(static fn($l) => "- $l", $imp->instrucciones())) . "\n\n";
}
file_put_contents($raiz . '/docs/FORMATOS_IMPORTACION.md', $md);
echo "docs/FORMATOS_IMPORTACION.md (" . count($importadores) . " importaciones)\n";
