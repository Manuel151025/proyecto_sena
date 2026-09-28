<?php
declare(strict_types=1);

/**
 * RECOVER.PHP — Flujo de recuperación de contraseña con BD real
 *
 * Estados:
 *   step=1  → formulario para ingresar email institucional
 *   step=2  → confirmación de envío (link a "correo" + en modo DEV se muestra)
 *   step=3  → formulario de nueva contraseña (requiere ?token=xxx válido)
 *
 * Seguridad:
 *   - Token de 32 bytes generado con random_bytes() (criptográficamente seguro)
 *   - Token hasheado en BD (no se guarda en texto plano)
 *   - Expira en 30 minutos
 *   - De un solo uso (campo `usado`)
 *   - El mensaje "revisa tu correo" se muestra exista o no el email
 *     (previene enumeración de cuentas)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/correo_recuperacion.php';
require_once __DIR__ . '/core/Database.php';

use Core\Database;
use Core\Services\MailService;

// =====================================================================
// CONFIGURACIÓN
// =====================================================================

// Vida útil del token en minutos
define('TOKEN_TTL_MIN', 30);

// Archivo de log para tokens generados (útil para demo y debugging)
define('RESET_LOG', __DIR__ . '/logs/password_resets.log');

// =====================================================================
// VARIABLES DE ESTADO
// =====================================================================

$step       = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$token_url  = $_GET['token'] ?? '';
$errors     = [];
$success    = '';
$dev_link   = ''; // link a mostrar en modo DEV en step=2

// =====================================================================
// HELPERS
// =====================================================================

/**
 * Registra el enlace de recuperación para poder probarlo sin correo.
 *
 * Solo en DEV_MODE. El enlace lleva el token en claro, es decir, la llave
 * para cambiar la contraseña de esa cuenta: escribirlo siempre convertía
 * `logs/password_resets.log` en una lista de llaves válidas. De hecho ese
 * archivo llegó a versionarse en git con enlaces reales dentro.
 *
 * Fuera de desarrollo se registra únicamente que hubo una solicitud, sin
 * el token y sin el correo completo.
 */
function log_reset_link(string $email, string $link): void {
    $log_dir = dirname(RESET_LOG);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    if (defined('DEV_MODE') && DEV_MODE) {
        $line = sprintf("[%s] %s -> %s\n", date('Y-m-d H:i:s'), $email, $link);
    } else {
        // Solo el dominio: basta para diagnosticar y no identifica a nadie.
        $dominio = strstr($email, '@') ?: '@?';
        $line = sprintf("[%s] solicitud de recuperación para una cuenta %s\n", date('Y-m-d H:i:s'), $dominio);
    }

    @file_put_contents(RESET_LOG, $line, FILE_APPEND | LOCK_EX);
}

function build_reset_link(string $token): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . APP_HOST . APP_URL . '/recover.php?step=3&token=' . urlencode($token);
}

// =====================================================================
// POST: STEP 1 — Solicitud de recuperación
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    requireCsrf();

    $bruto = $_POST['email'] ?? '';
    $email = is_array($bruto) ? '' : strip_tags(trim((string)$bruto));

    // Sin límite, este formulario permitía dos abusos: averiguar qué correos
    // están dados de alta (probando de uno en uno y midiendo la respuesta) y
    // bombardear de correos a una víctima pulsando "enviar" en bucle.
    $limitador = new Core\Services\LimitadorIntentos();
    $bloqueo   = $limitador->segundosBloqueo('recuperacion', $email);

    if ($bloqueo > 0) {
        $minutos = Core\Services\LimitadorIntentos::minutos($bloqueo);
        $errors[] = "Demasiadas solicitudes. Inténtalo de nuevo en {$minutos} minuto(s).";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Por favor ingresa un correo válido.';
    } elseif (mb_strlen($email, 'UTF-8') > 100) {
        $errors[] = 'El correo no puede exceder los 100 caracteres.';
    } else {
        // Se cuenta toda solicitud, exista la cuenta o no: contar solo las
        // que aciertan volvería a distinguir unas de otras.
        $limitador->registrarFallo('recuperacion', $email);

        try {
            $db = Database::getConnection();

            // Buscar usuario (no revelamos si existe o no)
            $stmt = $db->prepare("SELECT id, nombre, email FROM usuarios WHERE email = ? AND estado = 'activo' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Invalidar tokens previos no usados de este usuario (limpieza)
                $stmt = $db->prepare("UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0");
                $stmt->execute([(int)$user['id']]);

                // Generar token nuevo
                $token_plain = bin2hex(random_bytes(32)); // 64 chars hex
                $token_hash  = password_hash($token_plain, PASSWORD_DEFAULT);
                $expira_en   = (new DateTime('+' . TOKEN_TTL_MIN . ' minutes'))->format('Y-m-d H:i:s');
                $ip          = $_SERVER['REMOTE_ADDR'] ?? null;

                // Guardar en BD
                $stmt = $db->prepare("
                    INSERT INTO password_resets (usuario_id, token_hash, expira_en, ip_solicitud)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([(int)$user['id'], $token_hash, $expira_en, $ip]);

                $link = build_reset_link($token_plain);

                // Loguear para demo / debugging
                log_reset_link($email, $link);

                // Enviar el correo por SMTP (PHPMailer). MailService nunca
                // lanza excepciones: si el envío falla devuelve false y lo
                // registra en logs/mail.log, para que un problema de correo
                // no altere la respuesta ni rompa la anti-enumeración.
                $subject = 'Recuperación de contraseña - SENA';
                $message = "Hola " . $user['nombre'] . ",\n\n"
                         . "Recibimos una solicitud para restablecer tu contraseña.\n"
                         . "Haz clic en el siguiente enlace para continuar:\n\n"
                         . $link . "\n\n"
                         . "Este enlace expira en " . TOKEN_TTL_MIN . " minutos.\n"
                         . "Si no fuiste tú, ignora este mensaje.\n\n"
                         . "— Sistema de Seguimiento SENA";

                // Versión HTML del mismo mensaje (mismo enlace, misma lógica).
                // Todo lo que viene de la BD se escapa: el correo no debe ser
                // un vector de inyección de HTML.
                $nombre_html = htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8');
                $link_html   = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                $ttl_min     = TOKEN_TTL_MIN;
                $anio        = date('Y');

                // Plantilla institucional SENA. Reglas de HTML para correo:
                //   - maquetación con <table>, no con divs/flex/grid
                //   - todos los estilos inline (Gmail elimina el <head>)
                //   - fuentes web-safe, sin JavaScript, sin imágenes externas
                //   - ancho máximo 600px, centrado
                $html = plantillaCorreoRecuperacion($nombre_html, $link_html, $ttl_min, $anio);

                (new MailService())->send($email, $subject, $message, $html);

                if (DEV_MODE) {
                    $_SESSION['_dev_reset_link'] = $link;
                }
            }
            // Si no existe el usuario, no hacemos nada visible (anti-enumeración)

            // Siempre redirigir a step=2 con el mismo mensaje
            header('Location: ' . APP_URL . '/recover.php?step=2');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Hubo un error al procesar tu solicitud. Inténtalo más tarde.';
        }
    }
}

// =====================================================================
// POST: STEP 3 — Cambiar contraseña
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    requireCsrf();

    $token_plain = $_POST['token'] ?? '';
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password_confirm'] ?? '';

    // La misma política que el perfil y la gestión de usuarios.
    array_push($errors, ...\Core\Support\PoliticaContrasena::errores($password));
    if ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden.';
    }
    if (empty($token_plain)) {
        $errors[] = 'Token inválido o ausente.';
    }

    if (empty($errors)) {
        try {
            $db = Database::getConnection();

            // Buscar tokens vigentes (no usados, no expirados)
            $stmt = $db->prepare("
                SELECT id, usuario_id, token_hash
                FROM password_resets
                WHERE usado = 0 AND expira_en > NOW()
                ORDER BY id DESC
                LIMIT 20
            ");
            $stmt->execute();
            $candidates = $stmt->fetchAll();

            // Como guardamos el token hasheado, hay que verificar contra cada candidato.
            // Limitamos a los 20 más recientes para no hacer brute-force loops.
            $match = null;
            foreach ($candidates as $c) {
                if (password_verify($token_plain, $c['token_hash'])) {
                    $match = $c;
                    break;
                }
            }

            if (!$match) {
                $errors[] = 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.';
                $step = 1; // Volver al inicio
            } else {
                // Transacción: actualizar password + marcar token como usado
                $db->beginTransaction();

                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET password = ?, fecha_actualizacion = NOW() WHERE id = ?");
                $stmt->execute([$new_hash, (int)$match['usuario_id']]);

                $stmt = $db->prepare("UPDATE password_resets SET usado = 1 WHERE id = ?");
                $stmt->execute([(int)$match['id']]);

                $db->commit();

                // Limpiar intentos de bloqueo si existían
                unset($_SESSION['login_attempts']);
                unset($_SESSION['blocked_until']);

                // Redirigir a login con mensaje de éxito
                $_SESSION['_flash_success'] = 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.';
                header('Location: ' . APP_URL . '/login.php');
                exit;
            }

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = 'No se pudo actualizar la contraseña. Inténtalo de nuevo.';
        }
    }

    // Si hubo errores en step=3, conservamos el token en el formulario
    $token_url = $token_plain;
    if (!isset($step) || $step !== 1) {
        $step = 3;
    }
}

// =====================================================================
// STEP 2: leer el link DEV si existe
// =====================================================================

if ($step === 2 && DEV_MODE && isset($_SESSION['_dev_reset_link'])) {
    $dev_link = $_SESSION['_dev_reset_link'];
    unset($_SESSION['_dev_reset_link']);
}

// =====================================================================
// STEP 3: validar que el token llegó (no validamos vs BD hasta que envíen
//         el form — si es inválido, lo verán al hacer submit)
// =====================================================================

if ($step === 3 && empty($token_url) && empty($_POST['token'])) {
    $errors[] = 'Falta el token de recuperación. Solicita un nuevo enlace.';
    $step = 1;
}

?>
<!DOCTYPE html>
<html lang="es" data-app-url="<?= e(APP_URL) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer Contraseña — SENA</title>
  <meta name="description" content="Recupera el acceso a tu cuenta institucional SENA de forma segura.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  
  <!-- PWA Manifest & Meta Tags -->
  <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
  <meta name="theme-color" content="#39A900">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/sena_logo.png">



  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/publico.css?v=<?= filemtime(__DIR__ . '/assets/css/publico.css') ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/recuperar.css?v=<?= filemtime(__DIR__ . '/assets/css/recuperar.css') ?>">
</head>
<body>
<script src="<?= APP_URL ?>/assets/js/pestana.js?v=<?= filemtime(__DIR__ . '/assets/js/pestana.js') ?>"></script>
<canvas id="particle-canvas"></canvas>

<div class="shell">

  <!-- Left: Branding -->
  <div class="brand">
    <div>
      <div class="brand-logo">
        <img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="SENA">
        <span>Sena Colombia</span>
      </div>
      <h1>Gestión de<br>Proyectos <em>Formativos</em></h1>
      <p>Plataforma institucional para el seguimiento integral de fichas, instructores, aprendices y proyectos de formación.</p>

      <div class="brand-features">
        <div class="feat">
          <div class="feat-icon">📋</div>
          Gestión de fichas y programas de formación
        </div>
        <div class="feat">
          <div class="feat-icon">👥</div>
          Seguimiento de instructores y aprendices
        </div>
        <div class="feat">
          <div class="feat-icon">📊</div>
          Reportes y análisis en tiempo real
        </div>
      </div>
    </div>
    <div class="brand-footer">© <?= date('Y') ?> Servicio Nacional de Aprendizaje · Colombia</div>
  </div>

  <!-- Right: Form -->
  <div class="form-side">
    <div class="card">

      <a href="login.php" class="back-link"><i class="bi bi-arrow-left"></i> Volver a iniciar sesión</a>

      <div class="step-track">
        <div class="step-node <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
          <div class="circle"><?= $step > 1 ? '<i class="bi bi-check2"></i>' : '01' ?></div>
          <span class="slabel">Solicitud</span>
        </div>
        <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
        <div class="step-node <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
          <div class="circle"><?= $step > 2 ? '<i class="bi bi-check2"></i>' : '02' ?></div>
          <span class="slabel">Verificar</span>
        </div>
        <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
        <div class="step-node <?= $step >= 3 ? 'active' : '' ?>">
          <div class="circle">03</div>
          <span class="slabel">Nueva clave</span>
        </div>
      </div>

      <div class="divider"></div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
          <i class="bi bi-shield-exclamation"></i>
          <div>
            <?php foreach ($errors as $err): ?>
              <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
        <div class="rec-encabezado">
          <h2 class="rec-titulo">Restablecer contraseña</h2>
          <p class="rec-subtitulo">Ingresa tu correo institucional para recibir el enlace de recuperación.</p>
        </div>

        <form method="POST" action="recover.php" id="recover-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="request">
          <div class="field">
            <label for="recover-email">Correo institucional</label>
            <div class="input-icon-wrap">
              <input type="email" name="email" id="recover-email"
                     placeholder="usuario@sena.edu.co"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                     maxlength="100" required autofocus>
              <i class="bi bi-envelope-fill input-icon"></i>
            </div>
          </div>
          <button type="submit" id="recover-submit" class="submit-btn">
            <i class="bi bi-send-fill"></i> Enviar enlace de recuperación
          </button>
        </form>

      <?php elseif ($step === 2): ?>
        <div class="rec-exito">
          <div class="success-orb"><i class="bi bi-envelope-check-fill"></i></div>
          <h2 class="rec-titulo rec-titulo-exito">Revisa tu correo</h2>
          <p class="rec-texto">
            Si el correo electrónico existe en el sistema, recibirás un enlace seguro para restablecer tu contraseña.
          </p>
          <p class="rec-nota">
            El enlace de recuperación expira en <?= TOKEN_TTL_MIN ?> minutos.
          </p>
        </div>

        <?php if (DEV_MODE && !empty($dev_link)): ?>
          <div class="dev-box">
            <strong><i class="bi bi-code-slash"></i> MODO DESARROLLO - Enlace de prueba:</strong>
            <a href="<?= htmlspecialchars($dev_link) ?>"><?= htmlspecialchars($dev_link) ?></a>
            <div class="dev-note">También guardado en <code>/logs/password_resets.log</code></div>
          </div>
        <?php endif; ?>

        <a href="login.php" class="btn-outline"><i class="bi bi-box-arrow-in-right"></i> Volver a iniciar sesión</a>

      <?php elseif ($step === 3): ?>
        <div class="rec-encabezado">
          <h2 class="rec-titulo">Nueva contraseña</h2>
          <p class="rec-subtitulo">Define una contraseña segura para tu cuenta institucional.</p>
        </div>

        <form method="POST" action="recover.php" id="reset-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token_url) ?>">

          <div class="field">
            <label for="pw-new">Nueva contraseña</label>
            <div class="input-icon-wrap">
              <input type="password" name="password" id="pw-new"
                     placeholder="••••••••" required minlength="8" maxlength="72" autocomplete="new-password">
              <i class="bi bi-lock-fill input-icon"></i>
              <button type="button" class="pw-toggle-btn" data-pw-toggle="#pw-new"><i class="bi bi-eye"></i></button>
            </div>
            <div class="pw-strength" id="pw-bar"><span></span><span></span><span></span><span></span></div>
            <div class="mt-2">
              <div class="pw-req" data-req="len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</div>
              <div class="pw-req" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div>
              <div class="pw-req" data-req="num"><i class="bi bi-circle"></i> Contiene números</div>
              <div class="pw-req" data-req="upper"><i class="bi bi-circle"></i> Una mayúscula (recomendado)</div>
            </div>
          </div>

          <div class="field">
            <label for="pw-confirm">Confirmar contraseña</label>
            <div class="input-icon-wrap">
              <input type="password" name="password_confirm" id="pw-confirm"
                     placeholder="••••••••" required minlength="8" maxlength="72" autocomplete="new-password">
              <i class="bi bi-shield-lock-fill input-icon"></i>
              <button type="button" class="pw-toggle-btn" data-pw-toggle="#pw-confirm"><i class="bi bi-eye"></i></button>
            </div>
          </div>

          <button type="submit" id="reset-submit" class="submit-btn">
            <i class="bi bi-shield-check-fill"></i> Guardar nueva contraseña
          </button>
        </form>
      <?php endif; ?>

      <div class="card-footer">
        © <?= date('Y') ?> Servicio Nacional de Aprendizaje · Colombia
      </div>

    </div>
  </div>

</div>

<script src="<?= APP_URL ?>/assets/js/publico/recuperar.js?v=<?= filemtime(__DIR__ . '/assets/js/publico/recuperar.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/publico/particulas.js?v=<?= filemtime(__DIR__ . '/assets/js/publico/particulas.js') ?>"></script>
</body>
</html>