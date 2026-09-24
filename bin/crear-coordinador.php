<?php
declare(strict_types=1);

/**
 * Crea una cuenta de coordinación desde la consola.
 *
 *   php bin/crear-coordinador.php correo@dominio "NOMBRE COMPLETO"
 *
 * Una instalación sin --demo nace sin usuarios y la única forma de crear
 * cuentas es desde la pantalla de Usuarios, que exige ya ser coordinador.
 * Este comando rompe ese círculo, y sirve también para recuperar el acceso
 * si la coordinación pierde su cuenta.
 *
 * La contraseña la genera el sistema, se muestra una sola vez y se exige
 * cambiarla al entrar, igual que al crear una cuenta desde la aplicación.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

use Core\Services\Auditoria;
use Core\Support\PoliticaContrasena;

$email  = mb_strtolower(trim((string)($argv[1] ?? '')), 'UTF-8');
$nombre = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string)($argv[2] ?? '')) ?? ''), 'UTF-8');

if ($email === '' || $nombre === '') {
    abortar("Uso: php bin/crear-coordinador.php correo@dominio \"NOMBRE COMPLETO\"");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
    abortar("El correo \"$email\" no es válido.");
}
if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150 || !preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $nombre)) {
    abortar('El nombre debe tener entre 3 y 150 caracteres y solo letras, espacios, punto, apóstrofo o guion.');
}

$db = conexion();
$st = $db->prepare('SELECT id, rol, estado FROM usuarios WHERE LOWER(email) = ?');
$st->execute([$email]);
if ($existente = $st->fetch()) {
    abortar("Ya existe una cuenta con ese correo (rol {$existente['rol']}, estado {$existente['estado']}). "
        . 'Para darle acceso de coordinación, edítala desde la aplicación.');
}

$temporal = PoliticaContrasena::temporal();
$db->prepare("INSERT INTO usuarios (email, password, debe_cambiar_password, nombre, rol, estado)
              VALUES (?, ?, 1, ?, 'coordinador', 'activo')")
   ->execute([$email, password_hash($temporal, PASSWORD_DEFAULT), $nombre]);
$id = (int)$db->lastInsertId();

(new Auditoria($db))->registrar(null, Auditoria::CREAR, 'Consola', "Creó la cuenta de coordinación $email desde bin/crear-coordinador.php", 'usuarios', $id);

linea("Cuenta de coordinación creada: $email");
linea("Contraseña temporal (se muestra solo esta vez): $temporal");
linea('Se pedirá cambiarla al iniciar sesión.');
