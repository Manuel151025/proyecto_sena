<?php
declare(strict_types=1);

/**
 * Pone la base de datos al día.
 *
 *   php bin/migrar.php            aplica las migraciones pendientes, en orden
 *   php bin/migrar.php --estado   lista aplicadas y pendientes, sin cambiar nada
 *
 * Es el comando que hay que ejecutar tras cada despliegue: el despliegue
 * automático actualiza el código, no la base. Todas las migraciones son
 * idempotentes, así que ejecutarlo de más no hace daño.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

use Core\Support\Migrador;

$db = conexion();
$migrador = new Migrador($db, dirname(__DIR__) . '/database/migraciones');
$migrador->asegurarTabla();

if (opcion('--estado')) {
    $aplicadas = $migrador->aplicadas();
    foreach ($migrador->disponibles() as $id => $ruta) {
        linea(isset($aplicadas[$id])
            ? sprintf('  aplicada   %-45s %s', $id, $aplicadas[$id])
            : sprintf('  PENDIENTE  %s', $id));
    }
    $n = count($migrador->pendientes());
    linea();
    linea($n === 0 ? 'La base está al día.' : "Pendientes: $n. Ejecuta: php bin/migrar.php");
    exit($n === 0 ? 0 : 2);
}

$pendientes = $migrador->pendientes();
if ($pendientes === []) {
    linea('La base está al día: no hay migraciones pendientes.');
    exit(0);
}

linea('Base: ' . DB_NAME . ' en ' . DB_HOST . ' — ' . count($pendientes) . ' migración(es) pendiente(s).');
$r = $migrador->migrar(static fn(string $m) => linea($m));

if ($r['error'] !== null) {
    abortar(PHP_EOL . 'ERROR en ' . $r['error'] . PHP_EOL
        . 'Las migraciones anteriores quedaron registradas. Corrige la causa y vuelve a ejecutar: '
        . 'todas son idempotentes.');
}

linea();
linea('Listo: ' . count($r['aplicadas']) . ' migración(es) aplicada(s).');
