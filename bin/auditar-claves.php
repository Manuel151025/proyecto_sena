<?php
declare(strict_types=1);

/**
 * Detecta y anula contraseñas conocidas.
 *
 *   php bin/auditar-claves.php             informe (no cambia nada)
 *   php bin/auditar-claves.php --aplicar   asigna claves temporales nuevas
 *
 * El historial público del repositorio incluye un volcado con 123 cuentas
 * cuya contraseña es `admin123`, y la base del VPS nació de ese volcado.
 * Ejecutarlo en producción es lo primero tras conocer la filtración.
 *
 * Con --aplicar, las claves temporales se imprimen UNA vez (correo;rol;clave)
 * para entregarlas; cada persona deberá cambiarla al entrar. Quien tenga el
 * correo configurado también puede usar "¿Olvidaste tu contraseña?".
 * Guarda esa salida fuera de la carpeta web y bórrala tras entregarlas.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

use Core\Services\AuditoriaContrasenas;

$auditor = new AuditoriaContrasenas(conexion());
fwrite(STDERR, 'Comprobando contraseñas (bcrypt es lento a propósito; puede tardar un par de minutos)...' . PHP_EOL);
$halladas = $auditor->buscar(null, static function (int $hechas, int $total): void {
    if ($hechas % 25 === 0 || $hechas === $total) {
        fwrite(STDERR, "  $hechas / $total cuentas\r" . ($hechas === $total ? PHP_EOL : ''));
    }
});

if ($halladas === []) {
    linea('Ninguna cuenta usa una contraseña conocida.');
    exit(0);
}

$porRol = [];
foreach ($halladas as $h) {
    $porRol[$h['rol']] = ($porRol[$h['rol']] ?? 0) + 1;
}
linea(count($halladas) . ' cuenta(s) con una contraseña conocida: '
    . implode(', ', array_map(static fn($r, $n) => "$n $r", array_keys($porRol), $porRol)) . '.');

if (!opcion('--aplicar')) {
    foreach ($halladas as $h) {
        linea(sprintf('  %-12s %-9s %s', $h['rol'], $h['estado'], $h['email']));
    }
    linea();
    linea('Para anularlas:  php bin/auditar-claves.php --aplicar > claves-temporales.csv');
    exit(1);
}

$anuladas = $auditor->anular($halladas);
fwrite(STDERR, count($anuladas) . ' contraseña(s) anulada(s). Claves temporales (se muestran una sola vez):' . PHP_EOL);
linea('correo;rol;clave_temporal');
foreach ($anuladas as $a) {
    linea("{$a['email']};{$a['rol']};{$a['temporal']}");
}
