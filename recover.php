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
require_once __DIR__ . '/core/Database.php';

use Core\Database;

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

function log_reset_link(string $email, string $link): void {
    $log_dir = dirname(RESET_LOG);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $line = sprintf(
        "[%s] %s -> %s\n",
        date('Y-m-d H:i:s'),
        $email,
        $link
    );
    @file_put_contents(RESET_LOG, $line, FILE_APPEND | LOCK_EX);
}

function build_reset_link(string $token): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . APP_URL . '/recover.php?step=3&token=' . urlencode($token);
}

// =====================================================================
// POST: STEP 1 — Solicitud de recuperación
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {

    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Por favor ingresa un correo válido.';
    } else {
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

                // Intentar enviar email real (puede fallar silenciosamente en XAMPP)
                $subject = 'Recuperación de contraseña - SENA';
                $message = "Hola " . $user['nombre'] . ",\n\n"
                         . "Recibimos una solicitud para restablecer tu contraseña.\n"
                         . "Haz clic en el siguiente enlace para continuar:\n\n"
                         . $link . "\n\n"
                         . "Este enlace expira en " . TOKEN_TTL_MIN . " minutos.\n"
                         . "Si no fuiste tú, ignora este mensaje.\n\n"
                         . "— Sistema de Seguimiento SENA";
                $headers = "From: no-reply@sena.edu.co\r\n"
                         . "Content-Type: text/plain; charset=utf-8\r\n";
                @mail($email, $subject, $message, $headers);

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

    $token_plain = $_POST['token'] ?? '';
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener letras y números.';
    }
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
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer Contrasena - SENA - Sistema de Seguimiento de Proyectos Formativos</title>
  <meta name="description" content="Recupera el acceso a tu cuenta institucional SENA de forma segura.">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/login-nano.css">
  <style>
.step-track{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:1.75rem;}
.step-node{display:flex;flex-direction:column;align-items:center;gap:.35rem;}
.step-node .circle{width:36px;height:36px;border-radius:50%;border:1.5px solid rgba(94,203,0,.22);background:rgba(10,25,12,.8);display:grid;place-items:center;font-size:.8rem;font-weight:700;font-family:var(--mono);color:var(--nano-soft);transition:all .35s cubic-bezier(0.34,1.56,0.64,1);}
.step-node.active .circle{border-color:var(--g1);background:rgba(94,203,0,.14);color:var(--g1);box-shadow:0 0 16px rgba(94,203,0,.32);}
.step-node.done .circle{border-color:var(--g1);background:var(--g1);color:#fff;box-shadow:0 0 12px rgba(94,203,0,.4);}
.step-node .slabel{font-size:.6rem;font-family:var(--mono);text-transform:uppercase;letter-spacing:.06em;color:var(--nano-soft);transition:color .3s;white-space:nowrap;}
.step-node.active .slabel{color:var(--g1);}.step-node.done .slabel{color:var(--g2);}
.step-line{width:50px;height:1px;background:linear-gradient(90deg,rgba(94,203,0,.2),rgba(94,203,0,.1));margin-bottom:1.35rem;flex-shrink:0;transition:background .3s;}
.step-line.done{background:linear-gradient(90deg,rgba(94,203,0,.55),rgba(94,203,0,.28));}
.success-orb{width:78px;height:78px;border-radius:50%;background:radial-gradient(circle,rgba(94,203,0,.2) 0%,rgba(94,203,0,.04) 70%);border:1.5px solid rgba(94,203,0,.38);display:grid;place-items:center;margin:0 auto 1.2rem;font-size:1.75rem;color:var(--g1);box-shadow:0 0 35px rgba(94,203,0,.25),inset 0 0 20px rgba(94,203,0,.07);animation:orb-pulse 3s ease-in-out infinite;}
.pw-req-nano{display:flex;align-items:center;gap:.45rem;font-size:.74rem;font-family:var(--mono);color:var(--nano-soft);margin:.22rem 0;transition:color .25s;}
.pw-req-nano i{font-size:.76rem;transition:all .25s;}.pw-req-nano.ok{color:var(--g2);}.pw-req-nano.ok i{text-shadow:0 0 8px rgba(94,203,0,.6);}
.pw-strength-nano{display:flex;gap:4px;margin:.45rem 0 .65rem;}
.pw-strength-nano span{flex:1;height:3px;border-radius:2px;background:rgba(94,203,0,.1);transition:background .3s;}
.pw-strength-nano.s1 span:nth-child(1){background:#e74c3c;}.pw-strength-nano.s2 span:nth-child(-n+2){background:#f39c12;}
.pw-strength-nano.s3 span:nth-child(-n+3){background:#3498db;}.pw-strength-nano.s4 span{background:var(--g1);box-shadow:0 0 6px rgba(94,203,0,.4);}
.dev-box{background:rgba(94,203,0,.06);border:1px dashed rgba(94,203,0,.28);border-radius:10px;padding:.9rem 1rem;font-size:.75rem;font-family:var(--mono);color:var(--nano-muted);margin-bottom:1.1rem;}
.dev-box strong{color:var(--g1);display:block;margin-bottom:.35rem;}.dev-box a{color:var(--g2);word-break:break-all;text-decoration:none;}.dev-box .dev-note{color:var(--nano-soft);margin-top:.45rem;font-size:.68rem;}
.back-link{display:inline-flex;align-items:center;gap:.4rem;font-size:.76rem;font-family:var(--mono);color:var(--nano-soft);text-decoration:none;margin-bottom:1.4rem;padding:.3rem .65rem;border:1px solid rgba(94,203,0,.12);border-radius:6px;transition:all .25s;}
.back-link:hover{color:var(--g1);border-color:rgba(94,203,0,.35);background:rgba(94,203,0,.06);transform:translateX(-3px);}
.back-link i{transition:transform .25s;}.back-link:hover i{transform:translateX(-2px);}
.nano-btn-outline{width:100%;padding:.9rem 1.5rem;border:1.5px solid rgba(94,203,0,.32);border-radius:11px;background:transparent;font-family:var(--sans);font-size:.95rem;font-weight:600;color:var(--g1);cursor:pointer;transition:all .3s cubic-bezier(0.34,1.56,0.64,1);display:flex;align-items:center;justify-content:center;gap:.6rem;margin-top:.65rem;text-decoration:none;}
.nano-btn-outline:hover{background:rgba(94,203,0,.1);border-color:var(--g1);box-shadow:0 0 20px rgba(94,203,0,.2);color:var(--g2);transform:translateY(-2px);}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes ripple-anim{to{transform:scale(1);opacity:0;}}
  </style>
</head>
<body>
<canvas id="nano-canvas"></canvas>
<div class="login-shell">

  <div class="nano-brand">
    <div class="scan-lines"></div>
    <div class="light-sweep"></div>
    <div class="hex-grid">
      <svg viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <defs><pattern id="hex" width="60" height="52" patternUnits="userSpaceOnUse" patternTransform="scale(1.5)">
          <polygon points="30,1 58,16 58,46 30,61 2,46 2,16" fill="none" stroke="rgba(94,203,0,0.18)" stroke-width="0.8"/>
        </pattern></defs>
        <rect width="100%" height="100%" fill="url(#hex)"/>
        <polygon points="90,53 118,68 118,98 90,113 62,98 62,68" fill="rgba(94,203,0,0.06)" stroke="rgba(94,203,0,0.4)" stroke-width="1">
          <animate attributeName="opacity" values="0.3;1;0.3" dur="3s" repeatCount="indefinite"/>
        </polygon>
        <polygon points="300,131 328,146 328,176 300,191 272,176 272,146" fill="rgba(94,203,0,0.04)" stroke="rgba(94,203,0,0.35)" stroke-width="1">
          <animate attributeName="opacity" values="1;0.2;1" dur="4.5s" repeatCount="indefinite"/>
        </polygon>
        <line x1="90" y1="83" x2="300" y2="161" stroke="rgba(94,203,0,0.14)" stroke-width="0.6" stroke-dasharray="4,6">
          <animate attributeName="stroke-dashoffset" from="0" to="-100" dur="3s" repeatCount="indefinite"/>
        </line>
      </svg>
    </div>
    <div class="nano-particles">
      <div class="particle"></div><div class="particle"></div><div class="particle"></div>
      <div class="particle"></div><div class="particle"></div><div class="particle"></div>
      <div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div>
    </div>
    <div class="brand-top">
      <div class="nano-logo-wrap">
        <div class="nano-logo-img"><img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="Logo SENA"></div>
        <div class="nano-logo-text">
          <span class="abbr">SENA</span>
          <span class="full">Servicio Nacional de Aprendizaje</span>
        </div>
      </div>
      <div class="nano-title">
        <span class="accent">// Seguridad &middot; Acceso</span>
        Recupera el<br>acceso a tu<br>cuenta
      </div>
      <div class="nano-subtitle-box">
        <p class="nano-desc">
          Restablece tu contrasena de forma <strong>segura</strong>.
          El enlace de recuperacion expira en <strong><?= TOKEN_TTL_MIN ?> minutos</strong>
          y es de un <strong>solo uso</strong>.
        </p>
      </div>
    </div>
    <div class="brand-center">
      <div class="nano-orb-wrap">
        <div class="nano-orb">
          <div class="orbit-ring"></div><div class="orbit-ring"></div><div class="orbit-ring"></div>
          <div class="orb-inner">
            <img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="SENA" class="orb-center-img">
          </div>
        </div>
      </div>
    </div>
    <div class="brand-footer">
      <div class="nano-footer-text">&copy; <?= date('Y') ?> Servicio Nacional de Aprendizaje &middot; Colombia</div>
      <div class="system-status"><span class="status-dot"></span>Sistema de Seguimiento de Proyectos Formativos</div>
    </div>
  </div>

  <div class="nano-form-panel">
    <div class="nano-form">

      <a href="login.php" class="back-link"><i class="bi bi-arrow-left"></i> Volver al inicio de sesion</a>

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

      <div class="nano-divider"></div>

      <?php if (!empty($errors)): ?>
        <div class="nano-alert danger" role="alert">
          <i class="bi bi-shield-exclamation"></i>
          <div><?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?></div>
        </div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
        <div style="margin-bottom:1.4rem;">
          <h2 style="font-size:1.35rem;margin-bottom:.25rem;color:var(--nano-text);">Restablecer <span style="color:var(--g1);">contrasena</span></h2>
          <p style="font-size:.76rem;font-family:var(--mono);color:var(--nano-muted);">// Ingresa tu correo institucional para recibir el enlace</p>
        </div>
        <form method="POST" action="recover.php" id="recover-form">
          <input type="hidden" name="action" value="request">
          <div class="nano-field">
            <div class="nano-label"><span>Correo institucional</span></div>
            <div class="nano-input-wrap">
              <input type="email" name="email" id="recover-email" class="nano-input"
                     placeholder="usuario@sena.edu.co"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                     required autofocus>
              <i class="bi bi-envelope-fill nano-input-icon"></i>
            </div>
          </div>
          <button type="submit" id="recover-submit" class="nano-btn">
            <i class="bi bi-send-fill"></i> Enviar enlace de recuperacion <i class="bi bi-arrow-right"></i>
          </button>
        </form>

      <?php elseif ($step === 2): ?>
        <div style="text-align:center;padding:.5rem 0 1.4rem;">
          <div class="success-orb"><i class="bi bi-envelope-check-fill"></i></div>
          <h2 style="font-size:1.4rem;margin-bottom:.55rem;">Revisa tu <span style="color:var(--g1);">correo</span></h2>
          <p style="font-size:.84rem;color:var(--nano-muted);font-family:var(--mono);line-height:1.75;">
            Si el correo existe en el sistema, recibiras<br>un enlace para restablecer tu contrasena.<br>
            <span style="color:var(--nano-soft);">Expira en <?= TOKEN_TTL_MIN ?> minutos.</span>
          </p>
        </div>
        <?php if (DEV_MODE && !empty($dev_link)): ?>
          <div class="dev-box">
            <strong><i class="bi bi-code-slash"></i> DEV_MODE - Enlace de prueba:</strong>
            <a href="<?= htmlspecialchars($dev_link) ?>"><?= htmlspecialchars($dev_link) ?></a>
            <div class="dev-note">Tambien guardado en <code>/logs/password_resets.log</code></div>
          </div>
        <?php endif; ?>
        <a href="login.php" class="nano-btn-outline"><i class="bi bi-box-arrow-in-right"></i> Volver al inicio de sesion</a>

      <?php elseif ($step === 3): ?>
        <div style="margin-bottom:1.4rem;">
          <h2 style="font-size:1.35rem;margin-bottom:.25rem;color:var(--nano-text);">Nueva <span style="color:var(--g1);">contrasena</span></h2>
          <p style="font-size:.76rem;font-family:var(--mono);color:var(--nano-muted);">// Define una contrasena segura para tu cuenta</p>
        </div>
        <form method="POST" action="recover.php" id="reset-form">
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token_url) ?>">
          <div class="nano-field">
            <div class="nano-label"><span>Nueva contrasena</span></div>
            <div class="nano-input-wrap">
              <input type="password" name="password" id="pw-new" class="nano-input"
                     data-pw-strength placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required minlength="8">
              <i class="bi bi-lock-fill nano-input-icon"></i>
              <button type="button" class="pw-toggle-btn" data-pw-toggle="#pw-new"><i class="bi bi-eye"></i></button>
            </div>
            <div class="pw-strength-nano" id="pw-bar"><span></span><span></span><span></span><span></span></div>
            <div>
              <div class="pw-req-nano" data-req="len"><i class="bi bi-circle"></i> Minimo 8 caracteres</div>
              <div class="pw-req-nano" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div>
              <div class="pw-req-nano" data-req="num"><i class="bi bi-circle"></i> Contiene numeros</div>
              <div class="pw-req-nano" data-req="upper"><i class="bi bi-circle"></i> Una mayuscula (recomendado)</div>
            </div>
          </div>
          <div class="nano-field">
            <div class="nano-label"><span>Confirmar contrasena</span></div>
            <div class="nano-input-wrap">
              <input type="password" name="password_confirm" id="pw-confirm" class="nano-input"
                     placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required minlength="8">
              <i class="bi bi-shield-lock-fill nano-input-icon"></i>
              <button type="button" class="pw-toggle-btn" data-pw-toggle="#pw-confirm"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <button type="submit" id="reset-submit" class="nano-btn">
            <i class="bi bi-shield-check-fill"></i> Guardar nueva contrasena <i class="bi bi-arrow-right"></i>
          </button>
        </form>
      <?php endif; ?>

      <div class="form-nano-footer" style="margin-top:1.25rem;">
        <button type="button" class="theme-btn" onclick="toggleTheme()" aria-label="Cambiar tema"><i class="bi bi-moon-stars" id="theme-icon"></i></button>
        <span class="nano-copyright">SENA &middot; Sistema Institucional &middot; <?= date('Y') ?></span>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-pw-toggle]').forEach(btn=>{btn.addEventListener('click',()=>{const inp=document.querySelector(btn.dataset.pwToggle),icon=btn.querySelector('i');if(!inp)return;inp.type=inp.type==='password'?'text':'password';icon.classList.toggle('bi-eye',inp.type==='password');icon.classList.toggle('bi-eye-slash',inp.type!=='password');});});
function toggleTheme(){const r=document.documentElement,i=document.getElementById('theme-icon'),d=r.dataset.theme==='dark';r.dataset.theme=d?'light':'dark';i.className=d?'bi bi-sun-fill':'bi bi-moon-stars';localStorage.setItem('sena_theme',r.dataset.theme);}
(function(){const s=localStorage.getItem('sena_theme');if(s){document.documentElement.dataset.theme=s;const i=document.getElementById('theme-icon');if(i)i.className=s==='dark'?'bi bi-moon-stars':'bi bi-sun-fill';}})();
const pwInput=document.getElementById('pw-new'),pwBar=document.getElementById('pw-bar'),pwConf=document.getElementById('pw-confirm');
if(pwInput){pwInput.addEventListener('input',()=>{const v=pwInput.value,r={len:v.length>=8,letter:/[A-Za-z]/.test(v),num:/[0-9]/.test(v),upper:/[A-Z]/.test(v)};document.querySelectorAll('.pw-req-nano').forEach(el=>{const ok=r[el.dataset.req];el.classList.toggle('ok',ok);el.querySelector('i').className=ok?'bi bi-check-circle-fill':'bi bi-circle';});if(pwBar)pwBar.className='pw-strength-nano'+(Object.values(r).filter(Boolean).length?` s${Object.values(r).filter(Boolean).length}`:'');});}
if(pwConf&&pwInput){pwConf.addEventListener('input',()=>{const m=pwConf.value&&pwConf.value!==pwInput.value;pwConf.style.borderColor=m?'rgba(220,50,40,.6)':'';pwConf.style.boxShadow=m?'0 0 0 3px rgba(220,50,40,.12)':'';});}
['recover-form','reset-form'].forEach(id=>{document.getElementById(id)?.addEventListener('submit',function(){const btn=this.querySelector('[type="submit"]');if(btn&&!btn.disabled){btn.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin .7s linear infinite"><circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/><path d="M12 2a10 10 0 0 1 10 10" stroke="white" stroke-width="3" stroke-linecap="round"/></svg> Procesando...';btn.disabled=true;}});});
document.querySelectorAll('.nano-btn').forEach(btn=>{btn.addEventListener('click',function(e){if(this.disabled)return;const r=this.getBoundingClientRect(),s=Math.max(r.width,r.height)*2,rp=document.createElement('span');rp.style.cssText='position:absolute;border-radius:50%;pointer-events:none;width:'+s+'px;height:'+s+'px;left:'+(e.clientX-r.left-s/2)+'px;top:'+(e.clientY-r.top-s/2)+'px;background:rgba(255,255,255,.22);transform:scale(0);animation:ripple-anim .6s ease-out forwards;';this.appendChild(rp);setTimeout(()=>rp.remove(),700);});});
(function(){const canvas=document.getElementById('nano-canvas');if(!canvas)return;const ctx=canvas.getContext('2d');let W,H,nodes=[],mouse={x:-999,y:-999},animId;const G='94,203,0';function resize(){W=canvas.width=window.innerWidth;H=canvas.height=window.innerHeight;}function mk(){return{x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.45,vy:(Math.random()-.5)*.45,r:Math.random()*1.8+.5,phi:Math.random()*Math.PI*2};}function init(){nodes=[];const n=Math.min(65,Math.floor(W*H/15000));for(let i=0;i<n;i++)nodes.push(mk());}function draw(){ctx.clearRect(0,0,W,H);const mD=150,md2=180;for(let i=0;i<nodes.length;i++)for(let j=i+1;j<nodes.length;j++){const d=Math.hypot(nodes[i].x-nodes[j].x,nodes[i].y-nodes[j].y);if(d<mD){ctx.beginPath();ctx.moveTo(nodes[i].x,nodes[i].y);ctx.lineTo(nodes[j].x,nodes[j].y);ctx.strokeStyle='rgba('+G+','+(1-d/mD)*.4+')';ctx.lineWidth=.7;ctx.stroke();}}nodes.forEach(n=>{n.phi+=.012;const g=Math.sin(n.phi)*.35+.55,dx=n.x-mouse.x,dy=n.y-mouse.y,md=Math.hypot(dx,dy);if(md<md2&&md>0){const f=(1-md/md2)*.6;n.vx+=(dx/md)*f;n.vy+=(dy/md)*f;}n.vx*=.97;n.vy*=.97;ctx.beginPath();ctx.arc(n.x,n.y,n.r*(md<md2?1+(1-md/md2)*1.5:1),0,Math.PI*2);ctx.fillStyle='rgba('+G+','+g+')';ctx.shadowBlur=md<md2?18:9;ctx.shadowColor='rgba('+G+',.7)';ctx.fill();ctx.shadowBlur=0;n.x+=n.vx;n.y+=n.vy;if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;});animId=requestAnimationFrame(draw);}window.addEventListener('mousemove',e=>{mouse.x=e.clientX;mouse.y=e.clientY;});window.addEventListener('mouseleave',()=>{mouse.x=-999;mouse.y=-999;});window.addEventListener('click',e=>{for(let i=0;i<6;i++){const n=mk();n.x=e.clientX;n.y=e.clientY;const a=(Math.PI*2/6)*i;n.vx=Math.cos(a)*2.5;n.vy=Math.sin(a)*2.5;nodes.push(n);if(nodes.length>100)nodes.shift();}});resize();init();draw();window.addEventListener('resize',()=>{cancelAnimationFrame(animId);resize();init();draw();});})();
(function(){const card=document.querySelector('.nano-form'),panel=document.querySelector('.nano-form-panel');if(!card||!panel)return;let tick=false;panel.addEventListener('mousemove',e=>{if(!tick){requestAnimationFrame(()=>{const r=card.getBoundingClientRect(),dx=(e.clientX-r.left-r.width/2)/(r.width/2),dy=(e.clientY-r.top-r.height/2)/(r.height/2);card.style.transform='perspective(900px) rotateY('+(dx*5)+'deg) rotateX('+(-dy*4)+'deg) translateZ(4px)';tick=false;});tick=true;}});panel.addEventListener('mouseleave',()=>{card.style.transform='';card.style.transition='transform .6s cubic-bezier(0.22,1,0.36,1)';setTimeout(()=>{card.style.transition='';},620);});})();
</script>
</body>
</html>