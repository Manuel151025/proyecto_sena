<?php
declare(strict_types=1);

/**
 * Regenera database/esquema.sql a partir de la base actual.
 *
 *   php bin/volcar-esquema.php
 *
 * Se ejecuta después de aplicar una migración nueva en desarrollo, para
 * que una instalación desde cero nazca ya con el esquema completo. Solo
 * estructura: ningún dato sale de la base (ni siquiera los de prueba).
 *
 * Antes había tres fuentes del esquema que no coincidían entre sí
 * (install.php, el volcado sena_seguimiento.sql y los scripts sueltos de
 * migrations/), y a cada una le faltaban tablas que las otras sí tenían.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

$db = conexion();
$tablas = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                       ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);

$sql = [];
$sql[] = '-- =====================================================================';
$sql[] = '-- Esquema de la base de datos — Sistema de Seguimiento de Proyectos';
$sql[] = '-- Formativos SENA.';
$sql[] = '--';
$sql[] = '-- GENERADO por bin/volcar-esquema.php: no editar a mano. Los cambios de';
$sql[] = '-- esquema se hacen con una migración en database/migraciones/ y después';
$sql[] = '-- se regenera este archivo.';
$sql[] = '--';
$sql[] = '-- Lo carga bin/instalar.php en una instalación nueva.';
$sql[] = '-- =====================================================================';
$sql[] = '';
$sql[] = 'SET NAMES utf8mb4;';
$sql[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$sql[] = '';

foreach ($tablas as $t) {
    $fila = $db->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
    $ddl = (string)$fila[1];
    // El contador de autoincremento es estado de los datos, no esquema.
    $ddl = preg_replace('/\sAUTO_INCREMENT=\d+/', '', $ddl);
    $sql[] = "DROP TABLE IF EXISTS `$t`;";
    $sql[] = $ddl . ';';
    $sql[] = '';
}

$sql[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$sql[] = '';

$destino = dirname(__DIR__) . '/database/esquema.sql';
file_put_contents($destino, implode("\n", $sql));
linea('Escrito ' . $destino . ' (' . count($tablas) . ' tablas).');
