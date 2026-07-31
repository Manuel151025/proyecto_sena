<?php
declare(strict_types=1);

namespace Core\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

/**
 * MailService — Envío de correo por SMTP con PHPMailer.
 *
 * Toda la configuración se lee de variables de entorno, así que cambiar de
 * proveedor (Gmail, SendGrid, Mailgun, el SMTP del centro…) no requiere tocar
 * código: basta con cambiar las variables en el entorno de despliegue.
 *
 *   MAIL_HOST           host SMTP           (def. smtp.gmail.com)
 *   MAIL_PORT           puerto              (def. 587)
 *   MAIL_USERNAME       usuario SMTP        (obligatorio para enviar)
 *   MAIL_PASSWORD       contraseña / app password (obligatorio para enviar)
 *   MAIL_FROM_ADDRESS   remitente           (def. MAIL_USERNAME)
 *   MAIL_FROM_NAME      nombre visible      (def. SENA Seguimiento)
 *   MAIL_ENCRYPTION     tls | ssl | none    (def. tls)
 *   MAIL_TIMEOUT        segundos de espera  (def. 15)   [opcional]
 *   MAIL_DEBUG          1 para volcar el diálogo SMTP al log [opcional]
 *
 * Nunca lanza excepciones hacia afuera: si algo falla devuelve false y deja
 * constancia en logs/mail.log. El flujo que lo llama (p. ej. la recuperación
 * de contraseña) debe poder continuar aunque el correo no salga.
 */
class MailService {
    /** Ruta del log de correo. */
    private string $logFile;

    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private string $encryption;
    private int $timeout;
    private bool $debug;

    public function __construct() {
        $this->logFile = dirname(__DIR__, 2) . '/logs/mail.log';

        $this->host        = $this->env('MAIL_HOST', 'smtp.gmail.com');
        $this->port        = (int)$this->env('MAIL_PORT', '587');
        $this->username    = $this->env('MAIL_USERNAME');
        $this->password    = $this->env('MAIL_PASSWORD');
        $this->fromAddress = $this->env('MAIL_FROM_ADDRESS', $this->username);
        $this->fromName    = $this->env('MAIL_FROM_NAME', 'SENA Seguimiento');
        $this->encryption  = strtolower($this->env('MAIL_ENCRYPTION', 'tls'));
        $this->timeout     = (int)$this->env('MAIL_TIMEOUT', '15');
        $this->debug       = $this->env('MAIL_DEBUG') === '1';

        if ($this->port <= 0) {
            $this->port = 587;
        }
        if ($this->timeout <= 0) {
            $this->timeout = 15;
        }
    }

    /**
     * Lee una variable de entorno. getenv() cubre tanto las definidas por el
     * contenedor (Dokploy) como las cargadas desde .env por env_loader.php,
     * que usa putenv().
     */
    private function env(string $key, string $default = ''): string {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $value = trim($value);
        return $value === '' ? $default : $value;
    }

    /**
     * Indica si hay credenciales suficientes para intentar un envío.
     * Útil para que quien llama pueda decidir sin provocar un intento fallido.
     */
    public function isConfigured(): bool {
        return $this->username !== '' && $this->password !== '';
    }

    /**
     * Envía un correo.
     *
     * @param string      $to        Destinatario
     * @param string      $subject   Asunto
     * @param string      $bodyText  Cuerpo en texto plano (siempre requerido)
     * @param string|null $bodyHtml  Cuerpo HTML opcional; si viene, el texto
     *                               plano se usa como alternativa (AltBody)
     * @return bool true si el servidor SMTP aceptó el mensaje
     */
    public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool {
        if (!$this->isConfigured()) {
            $this->log('OMITIDO: faltan MAIL_USERNAME y/o MAIL_PASSWORD; no se intenta el envío. Destino: ' . $to);
            return false;
        }

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->log('ERROR: destinatario inválido (' . $to . ')');
            return false;
        }

        if (!class_exists(PHPMailer::class)) {
            $this->log('ERROR: PHPMailer no está disponible. ¿Se ejecutó composer install?');
            return false;
        }

        try {
            $mail = new PHPMailer(true); // true = lanza excepciones, se capturan abajo

            // Acentos y ñ: PHPMailer usa ISO-8859-1 por defecto.
            $mail->CharSet = 'UTF-8';

            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->Port       = $this->port;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->Timeout    = $this->timeout;

            // 'none' permite un relay interno sin cifrado; con cualquier otro
            // valor se fuerza STARTTLS (tls) o SMTPS implícito (ssl).
            if ($this->encryption === 'none' || $this->encryption === '') {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            } elseif ($this->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            if ($this->debug) {
                $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) {
                    $this->log('SMTP[' . $level . '] ' . trim((string)$str));
                };
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);

            $mail->Subject = $subject;
            if ($bodyHtml !== null && $bodyHtml !== '') {
                $mail->isHTML(true);
                $mail->Body    = $bodyHtml;
                $mail->AltBody = $bodyText;
            } else {
                $mail->isHTML(false);
                $mail->Body = $bodyText;
            }

            $mail->send();
            $this->log('OK: enviado a ' . $to . ' | asunto: ' . $subject);
            return true;
        } catch (Throwable $e) {
            // Se capturan también los errores de PHP (Throwable, no solo
            // Exception) para que un fallo de correo nunca tumbe la petición.
            $detalle = isset($mail) && $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            $this->log('ERROR: no se pudo enviar a ' . $to . ' | ' . $detalle);
            return false;
        }
    }

    /**
     * Deja constancia en logs/mail.log. Nunca interrumpe el flujo: si el
     * directorio no se puede crear o escribir, simplemente no se registra.
     */
    private function log(string $mensaje): void {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $linea = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $mensaje);
        @file_put_contents($this->logFile, $linea, FILE_APPEND | LOCK_EX);
    }
}
