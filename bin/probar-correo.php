<?php
declare(strict_types=1);

/**
 * Comprueba la configuración de correo enviando un mensaje de prueba.
 *
 *   php bin/probar-correo.php destino@dominio
 *
 * Para usar después de rotar la contraseña de aplicación de Gmail
 * (MAIL_PASSWORD) o de cambiar de proveedor: sin esto, un SMTP mal
 * configurado solo se nota cuando alguien intenta recuperar su contraseña.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/_arranque.php';

use Core\Services\MailService;

$destino = trim((string)($argv[1] ?? ''));
if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
    abortar('Uso: php bin/probar-correo.php destino@dominio');
}

$correo = new MailService();
if (!$correo->isConfigured()) {
    abortar('El correo no está configurado: faltan MAIL_USERNAME y/o MAIL_PASSWORD en el .env.');
}

linea('Servidor: ' . (getenv('MAIL_HOST') ?: 'smtp.gmail.com') . ':' . (getenv('MAIL_PORT') ?: '587')
    . ' · cifrado: ' . (getenv('MAIL_ENCRYPTION') ?: 'tls') . ' · usuario: ' . getenv('MAIL_USERNAME'));

$ok = $correo->send($destino, 'Prueba de correo · ' . APP_NAME,
    "Este es un mensaje de prueba enviado con bin/probar-correo.php el " . date('d/m/Y H:i') . ".\n"
    . "Si lo recibes, la recuperación de contraseña y los avisos por correo funcionan.");

if (!$ok) {
    abortar('El servidor SMTP rechazó el envío. El motivo está en logs/mail.log '
        . '(con MAIL_DEBUG=1 se registra el diálogo completo).');
}
linea("Enviado a $destino. Revisa la bandeja de entrada (y la de spam).");
