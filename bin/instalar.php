<?php
declare(strict_types=1);

/**
 * Instalación desde cero.
 *
 *   php bin/instalar.php --confirmar-borrado-total           esquema vacío
 *   php bin/instalar.php --confirmar-borrado-total --demo    con datos de demostración
 *
 * BORRA la base configurada en .env (DB_NAME) y la vuelve a crear con
 * database/esquema.sql; después aplica las migraciones (que sobre un
 * esquema completo solo se registran y siembran los valores iniciales de
 * configuración).
 *
 * Sustituye a install.php, que estaba en la raíz web, empezaba con DROP
 * DATABASE y durante meses se pudo ejecutar desde el navegador.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

use Core\Support\Migrador;

if (!opcion('--confirmar-borrado-total')) {
    abortar(
        "ABORTADO: falta la confirmación explícita.\n\n"
        . 'Este comando elimina la base "' . DB_NAME . '" en ' . DB_HOST . " con todo su contenido\n"
        . "(usuarios, fichas, matrículas, juicios evaluativos e historial).\n\n"
        . "Si estás seguro:\n  php bin/instalar.php --confirmar-borrado-total [--demo]"
    );
}

$nombre = DB_NAME;
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $nombre)) {
    abortar("DB_NAME no es un nombre de base válido: $nombre");
}

$esquema = dirname(__DIR__) . '/database/esquema.sql';
if (!is_file($esquema)) {
    abortar("No existe $esquema. Genéralo con: php bin/volcar-esquema.php");
}

linea("Recreando la base \"$nombre\" en " . DB_HOST . '...');
$srv = conexion(false);
$srv->exec("DROP DATABASE IF EXISTS `$nombre`");
$srv->exec("CREATE DATABASE `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$db = conexion();

// El archivo lo genera bin/volcar-esquema.php: cada sentencia termina en
// ";" al final de línea y no contiene datos, así que basta con partir ahí.
$sentencias = preg_split('/;\s*$/m', (string)file_get_contents($esquema)) ?: [];
$n = 0;
foreach ($sentencias as $s) {
    $s = trim(preg_replace('/^--.*$/m', '', $s) ?? '');
    if ($s === '') {
        continue;
    }
    $db->exec($s);
    $n++;
}
linea("Esquema cargado ($n sentencias).");

$migrador = new Migrador($db, dirname(__DIR__) . '/database/migraciones');
$r = $migrador->migrar(static fn(string $m) => linea('  ' . $m));
if ($r['error'] !== null) {
    abortar('ERROR en la migración ' . $r['error']);
}
linea('Migraciones registradas: ' . count($r['aplicadas']) . '.');

if (opcion('--demo')) {
    linea('Sembrando datos de demostración...');
    $sembrar = require dirname(__DIR__) . '/database/semillas/demo.php';
    $resumen = $sembrar($db);
    foreach ($resumen as $clave => $valor) {
        linea(sprintf('  %-28s %s', $clave, $valor));
    }
    linea();
    linea('Cuentas de demostración (contraseña: Demo2026*):');
    linea('  coordinador@sena.edu.co');
    linea('  instructor@sena.edu.co … instructor5@sena.edu.co');
    linea('  aprendiz@sena.edu.co   … aprendiz5@sena.edu.co');
}

linea();
linea('Instalación terminada.');
if (!opcion('--demo')) {
    linea('La base no tiene usuarios. Crea la primera cuenta de coordinación con:');
    linea('  php bin/crear-coordinador.php correo@dominio "NOMBRE COMPLETO"');
}
