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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - SENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/theme.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-brand">
    <div>
      <div class="logo">S</div>
      <h1 class="mt-4">Recupera el acceso a tu cuenta</h1>
      <p class="mt-3">Te ayudamos a restablecer tu contraseña en pocos pasos.</p>
    </div>
    <div class="footer">© 2026 SENA</div>
  </div>
  <div class="auth-form-wrap">
    <div class="auth-form">
      <a href="login.php" class="small d-inline-block mb-3"><i class="bi bi-arrow-left"></i> Volver al inicio</a>

      <?php if (!empty($errors)): ?>
        <div class="alert-flat danger mb-3">
          <i class="bi bi-exclamation-circle"></i>
          <div>
            <?php foreach ($errors as $err): ?>
              <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
        <!-- ===== STEP 1: Solicitar recuperación ===== -->
        <h2>Restablecer contraseña</h2>
        <p class="subtitle">Ingresa el correo institucional asociado a tu cuenta.</p>
        <form method="POST" action="recover.php">
          <input type="hidden" name="action" value="request">
          <div class="mb-3">
            <label class="form-label">Correo institucional</label>
            <input type="email" name="email" class="form-control"
                   placeholder="usuario@sena.edu.co"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
          </div>
          <button type="submit" class="btn btn-primary w-100">Enviar enlace de recuperación</button>
        </form>

      <?php elseif ($step === 2): ?>
        <!-- ===== STEP 2: Confirmación de envío ===== -->
        <div class="text-center py-3">
          <div style="width:64px;height:64px;border-radius:50%;background:var(--sena-primary-50);color:var(--sena-primary-600);font-size:1.6rem;display:grid;place-items:center;margin:0 auto 1rem">
            <i class="bi bi-envelope-check"></i>
          </div>
          <h2>Revisa tu correo</h2>
          <p class="subtitle">Si el correo existe en el sistema, recibirás un enlace para restablecer tu contraseña en los próximos minutos. El enlace expira en <?= TOKEN_TTL_MIN ?> minutos.</p>
        </div>

        <?php if (DEV_MODE && !empty($dev_link)): ?>
          <div class="alert-flat warning mb-3" style="font-size:.85rem">
            <i class="bi bi-tools"></i>
            <div>
              <strong>Modo desarrollo:</strong> El envío de email no está configurado en este entorno.
              Para continuar la demostración, usa este enlace directamente:
              <div class="mt-2">
                <a href="<?= htmlspecialchars($dev_link) ?>" class="fw-semibold" style="word-break:break-all">
                  <?= htmlspecialchars($dev_link) ?>
                </a>
              </div>
              <div class="small text-muted mt-2">
                (También quedó guardado en <code>/logs/password_resets.log</code>)
              </div>
            </div>
          </div>
        <?php endif; ?>

        <a href="login.php" class="btn btn-soft w-100 mt-2">Volver al inicio de sesión</a>

      <?php elseif ($step === 3): ?>
        <!-- ===== STEP 3: Cambiar contraseña ===== -->
        <h2>Crear nueva contraseña</h2>
        <p class="subtitle">Define una contraseña segura para tu cuenta.</p>
        <form method="POST" action="recover.php">
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token_url) ?>">

          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <div class="position-relative">
              <input type="password" name="password" id="pw-new" class="form-control pe-5"
                     data-pw-strength placeholder="••••••••" required minlength="8">
              <button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted"
                      data-pw-toggle="#pw-new" style="height:100%"><i class="bi bi-eye"></i></button>
            </div>
            <div class="pw-strength mt-2"><span></span><span></span><span></span><span></span></div>
            <div class="mt-2">
              <div class="pw-req" data-req="len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</div>
              <div class="pw-req" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div>
              <div class="pw-req" data-req="num"><i class="bi bi-circle"></i> Contiene números</div>
              <div class="pw-req" data-req="upper"><i class="bi bi-circle"></i> Una mayúscula (recomendado)</div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirm" class="form-control"
                   placeholder="••••••••" required minlength="8">
          </div>
          <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>