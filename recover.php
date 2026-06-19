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
    requireCsrf();

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
    requireCsrf();

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
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer Contraseña — SENA</title>
  <meta name="description" content="Recupera el acceso a tu cuenta institucional SENA de forma segura.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg-primary: #0a0f0d;
      --bg-card: rgba(14, 22, 17, 0.65);
      --emerald: #34d399;
      --emerald-dim: #059669;
      --emerald-glow: rgba(52, 211, 153, 0.12);
      --text-primary: #f0fdf4;
      --text-secondary: #a7b5ae;
      --text-muted: #5a6b62;
      --border: rgba(52, 211, 153, 0.1);
      --border-hover: rgba(52, 211, 153, 0.25);
      --input-bg: rgba(255, 255, 255, 0.04);
      --input-border: rgba(255, 255, 255, 0.08);
      --radius: 12px;
    }

    html, body {
      height: 100%;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
      overflow: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Canvas ── */
    #particle-canvas {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
    }

    /* ── Ambient glow spots ── */
    body::before,
    body::after {
      content: '';
      position: fixed;
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
      filter: blur(100px);
    }

    body::before {
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(52, 211, 153, 0.08), transparent 70%);
      top: -10%;
      left: -5%;
      animation: floatA 20s ease-in-out infinite;
    }

    body::after {
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(6, 182, 212, 0.06), transparent 70%);
      bottom: -15%;
      right: -5%;
      animation: floatB 24s ease-in-out infinite;
    }

    @keyframes floatA {
      0%, 100% { transform: translate(0, 0); }
      50% { transform: translate(40px, 30px); }
    }
    @keyframes floatB {
      0%, 100% { transform: translate(0, 0); }
      50% { transform: translate(-30px, -40px); }
    }

    /* ── Shell ── */
    .shell {
      position: relative;
      z-index: 1;
      width: 100vw;
      height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }

    /* ── Left Panel ── */
    .brand {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 5rem;
      border-right: 1px solid var(--border);
      position: relative;
    }

    .brand-logo {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 3rem;
    }

    .brand-logo img {
      width: 44px;
      height: 44px;
      object-fit: contain;
      filter: drop-shadow(0 0 8px var(--emerald-glow));
    }

    .brand-logo span {
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.18em;
      color: var(--emerald);
      text-transform: uppercase;
    }

    .brand h1 {
      font-size: clamp(2rem, 4vw, 3.2rem);
      font-weight: 800;
      line-height: 1.08;
      color: var(--text-primary);
      margin-bottom: 1.2rem;
      letter-spacing: -0.03em;
    }

    .brand h1 em {
      font-style: normal;
      background: linear-gradient(135deg, var(--emerald), #6ee7b7);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .brand p {
      font-size: 1rem;
      color: var(--text-secondary);
      line-height: 1.7;
      max-width: 440px;
    }

    .brand-features {
      margin-top: 2.5rem;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .brand-features .feat {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 0.88rem;
      color: var(--text-secondary);
    }

    .brand-features .feat-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .brand-footer {
      position: absolute;
      bottom: 2.5rem;
      left: 5rem;
      font-size: 0.72rem;
      color: var(--text-muted);
    }

    /* ── Right Panel ── */
    .form-side {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .card {
      width: 100%;
      max-width: 420px;
      background: var(--bg-card);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 40px 36px;
      transition: border-color 0.5s ease, box-shadow 0.5s ease;
    }

    .card:hover {
      border-color: var(--border-hover);
      box-shadow: 0 0 60px rgba(52, 211, 153, 0.04);
    }

    /* ── Back Link ── */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--text-secondary);
      text-decoration: none;
      margin-bottom: 1.5rem;
      padding: 6px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--input-bg);
      transition: all 0.25s;
    }

    .back-link:hover {
      color: var(--emerald);
      border-color: var(--border-hover);
      background: rgba(52, 211, 153, 0.04);
      transform: translateX(-2px);
    }

    .back-link i {
      font-size: 0.85rem;
      transition: transform 0.25s;
    }

    .back-link:hover i {
      transform: translateX(-2px);
    }

    /* ── Step Track ── */
    .step-track {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
      position: relative;
    }

    .step-node {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      position: relative;
      z-index: 2;
    }

    .step-node .circle {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      border: 1px solid var(--border);
      background: var(--bg-primary);
      display: grid;
      place-items: center;
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--text-muted);
      transition: all 0.3s ease;
    }

    .step-node.active .circle {
      border-color: var(--emerald);
      color: var(--emerald);
      box-shadow: 0 0 12px rgba(52, 211, 153, 0.2);
    }

    .step-node.done .circle {
      border-color: var(--emerald);
      background: var(--emerald);
      color: #022c22;
      box-shadow: 0 0 12px rgba(52, 211, 153, 0.2);
    }

    .step-node .slabel {
      font-size: 0.62rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      transition: color 0.3s;
    }

    .step-node.active .slabel {
      color: var(--text-primary);
    }

    .step-node.done .slabel {
      color: var(--emerald);
    }

    .step-line {
      flex: 1;
      height: 1px;
      background: var(--border);
      transform: translateY(-9px);
      z-index: 1;
      transition: background 0.3s;
    }

    .step-line.done {
      background: var(--emerald);
    }

    .divider {
      height: 1px;
      background: var(--border);
      margin: 20px 0;
    }

    /* ── Form elements ── */
    .field {
      margin-bottom: 20px;
    }

    .field label {
      display: block;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-bottom: 8px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .input-icon-wrap {
      position: relative;
    }

    .input-icon-wrap input {
      width: 100%;
      padding: 13px 16px 13px 42px;
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      border-radius: var(--radius);
      font-family: inherit;
      font-size: 0.92rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    .input-icon-wrap input[type="password"] {
      padding-right: 42px;
    }

    .input-icon-wrap input::placeholder {
      color: var(--text-muted);
    }

    .input-icon-wrap input:focus {
      outline: none;
      border-color: var(--emerald);
      background: rgba(255, 255, 255, 0.06);
      box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1), 0 0 20px rgba(52, 211, 153, 0.05);
    }

    .input-icon-wrap .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      pointer-events: none;
      font-size: 1.05rem;
      transition: color 0.3s;
    }

    .input-icon-wrap input:focus ~ .input-icon {
      color: var(--emerald);
    }

    .pw-toggle-btn {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 1.1rem;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.3s;
    }

    .pw-toggle-btn:hover {
      color: var(--text-primary);
    }

    /* ── Buttons ── */
    .submit-btn {
      width: 100%;
      padding: 14px;
      margin-top: 4px;
      background: var(--emerald-dim);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      font-family: inherit;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .submit-btn::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1), transparent);
      transform: translateX(-100%);
      transition: transform 0.5s ease;
    }

    .submit-btn:hover {
      background: var(--emerald);
      color: #022c22;
      box-shadow: 0 4px 20px rgba(52, 211, 153, 0.3);
      transform: translateY(-1px);
    }

    .submit-btn:hover::before {
      transform: translateX(100%);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

    .btn-outline {
      width: 100%;
      padding: 14px;
      border: 1px solid var(--border-hover);
      border-radius: var(--radius);
      background: transparent;
      font-family: inherit;
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--emerald);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .btn-outline:hover {
      background: rgba(52, 211, 153, 0.05);
      border-color: var(--emerald);
      box-shadow: 0 0 20px rgba(52, 211, 153, 0.1);
      transform: translateY(-1px);
    }

    /* ── Alerts ── */
    .alert {
      padding: 12px 16px;
      border-radius: var(--radius);
      font-size: 0.82rem;
      margin-bottom: 24px;
      line-height: 1.5;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .alert i {
      font-size: 1rem;
      flex-shrink: 0;
      margin-top: 1px;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.08);
      border: 1px solid rgba(239, 68, 68, 0.2);
      color: #fca5a5;
    }

    .alert-success {
      background: rgba(52, 211, 153, 0.08);
      border: 1px solid rgba(52, 211, 153, 0.2);
      color: #6ee7b7;
    }

    /* ── Step 2 Specific ── */
    .success-orb {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: rgba(52, 211, 153, 0.05);
      border: 1px solid rgba(52, 211, 153, 0.2);
      display: grid;
      place-items: center;
      margin: 0 auto 1.2rem;
      font-size: 1.5rem;
      color: var(--emerald);
      box-shadow: 0 0 20px rgba(52, 211, 153, 0.1);
    }

    /* ── Step 3: Password strength & requirements ── */
    .pw-strength {
      display: flex;
      gap: 4px;
      margin: 8px 0 12px;
    }

    .pw-strength span {
      flex: 1;
      height: 3px;
      border-radius: 2px;
      background: rgba(255, 255, 255, 0.05);
      transition: background 0.3s;
    }

    .pw-strength.s1 span:nth-child(1) { background: #ef4444; }
    .pw-strength.s2 span:nth-child(-n+2) { background: #f59e0b; }
    .pw-strength.s3 span:nth-child(-n+3) { background: #3b82f6; }
    .pw-strength.s4 span { background: var(--emerald); }

    .pw-req {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.72rem;
      color: var(--text-muted);
      margin: 4px 0;
      transition: color 0.25s;
    }

    .pw-req i {
      font-size: 0.75rem;
      transition: all 0.25s;
    }

    .pw-req.ok {
      color: var(--emerald);
    }

    /* ── Dev Mode Box ── */
    .dev-box {
      background: rgba(52, 211, 153, 0.03);
      border: 1px dashed rgba(52, 211, 153, 0.2);
      border-radius: var(--radius);
      padding: 16px;
      font-size: 0.78rem;
      color: var(--text-secondary);
      margin-bottom: 20px;
    }

    .dev-box strong {
      color: var(--emerald);
      display: block;
      margin-bottom: 6px;
    }

    .dev-box a {
      color: #6ee7b7;
      word-break: break-all;
      text-decoration: underline;
    }

    .dev-box .dev-note {
      color: var(--text-muted);
      margin-top: 6px;
      font-size: 0.7rem;
    }

    .card-footer {
      text-align: center;
      margin-top: 24px;
      font-size: 0.72rem;
      color: var(--text-muted);
    }

    /* ── Responsive ── */
    @media (max-width: 960px) {
      .shell { grid-template-columns: 1fr; }
      .brand { display: none; }
      .card { max-width: 420px; }
    }

    @media (max-width: 480px) {
      .card { padding: 32px 24px; border-radius: 16px; }
    }
  </style>
</head>
<body>
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
        <div style="margin-bottom:1.4rem;">
          <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:6px;color:var(--text-primary);">Restablecer contraseña</h2>
          <p style="font-size:0.82rem;color:var(--text-muted);">Ingresa tu correo institucional para recibir el enlace de recuperación.</p>
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
                     required autofocus>
              <i class="bi bi-envelope-fill input-icon"></i>
            </div>
          </div>
          <button type="submit" id="recover-submit" class="submit-btn">
            <i class="bi bi-send-fill"></i> Enviar enlace de recuperación
          </button>
        </form>

      <?php elseif ($step === 2): ?>
        <div style="text-align:center;padding:0.5rem 0 1.4rem;">
          <div class="success-orb"><i class="bi bi-envelope-check-fill"></i></div>
          <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:8px;color:var(--text-primary);">Revisa tu correo</h2>
          <p style="font-size:0.84rem;color:var(--text-secondary);line-height:1.75;margin-bottom:12px;">
            Si el correo electrónico existe en el sistema, recibirás un enlace seguro para restablecer tu contraseña.
          </p>
          <p style="font-size:0.78rem;color:var(--text-muted);">
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
        <div style="margin-bottom:1.4rem;">
          <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:6px;color:var(--text-primary);">Nueva contraseña</h2>
          <p style="font-size:0.82rem;color:var(--text-muted);">Define una contraseña segura para tu cuenta institucional.</p>
        </div>

        <form method="POST" action="recover.php" id="reset-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token_url) ?>">

          <div class="field">
            <label for="pw-new">Nueva contraseña</label>
            <div class="input-icon-wrap">
              <input type="password" name="password" id="pw-new"
                     placeholder="••••••••" required minlength="8">
              <i class="bi bi-lock-fill input-icon"></i>
              <button type="button" class="pw-toggle-btn" data-pw-toggle="#pw-new"><i class="bi bi-eye"></i></button>
            </div>
            <div class="pw-strength" id="pw-bar"><span></span><span></span><span></span><span></span></div>
            <div style="margin-top: 8px;">
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
                     placeholder="••••••••" required minlength="8">
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

<script>
// Toggle Password Visibility
document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.querySelector(btn.dataset.pwToggle);
    const icon = btn.querySelector('i');
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('bi-eye', inp.type === 'password');
    icon.classList.toggle('bi-eye-slash', inp.type !== 'password');
  });
});

// Password Strength Checker
const pwInput = document.getElementById('pw-new');
const pwBar = document.getElementById('pw-bar');
const pwConf = document.getElementById('pw-confirm');

if (pwInput) {
  pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    const r = {
      len: v.length >= 8,
      letter: /[A-Za-z]/.test(v),
      num: /[0-9]/.test(v),
      upper: /[A-Z]/.test(v)
    };

    document.querySelectorAll('.pw-req').forEach(el => {
      const ok = r[el.dataset.req];
      el.classList.toggle('ok', ok);
      el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
    });

    if (pwBar) {
      const score = Object.values(r).filter(Boolean).length;
      pwBar.className = 'pw-strength' + (score ? ` s${score}` : '');
    }
  });
}

if (pwConf && pwInput) {
  pwConf.addEventListener('input', () => {
    const mismatch = pwConf.value && pwConf.value !== pwInput.value;
    pwConf.style.borderColor = mismatch ? 'rgba(239, 68, 68, 0.6)' : '';
    pwConf.style.boxShadow = mismatch ? '0 0 0 3px rgba(239, 68, 68, 0.12)' : '';
  });
}

// Button loading state spinners
['recover-form', 'reset-form'].forEach(id => {
  document.getElementById(id)?.addEventListener('submit', function() {
    const btn = this.querySelector('[type="submit"]');
    if (btn && !btn.disabled) {
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin .7s linear infinite; margin-right:8px;"><circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/><path d="M12 2a10 10 0 0 1 10 10" stroke="white" stroke-width="3" stroke-linecap="round"/></svg> Procesando...';
      btn.disabled = true;
    }
  });
});

// Add CSS keyframe animation for spinner
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);

// Particle Canvas Animation
(function() {
  var canvas = document.getElementById('particle-canvas');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var W, H, nodes = [], mouse = { x: -999, y: -999 }, animId;

  function resize() {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  function mkNode() {
    return {
      x: Math.random() * W,
      y: Math.random() * H,
      vx: (Math.random() - 0.5) * 0.3,
      vy: (Math.random() - 0.5) * 0.3,
      r: Math.random() * 1.5 + 0.4,
      phi: Math.random() * Math.PI * 2
    };
  }

  function initNodes() {
    nodes = [];
    var count = Math.min(70, Math.floor(W * H / 18000));
    for (var i = 0; i < count; i++) nodes.push(mkNode());
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    var maxD = 140, mouseD = 160;

    for (var i = 0; i < nodes.length; i++) {
      for (var j = i + 1; j < nodes.length; j++) {
        var dx = nodes[i].x - nodes[j].x;
        var dy = nodes[i].y - nodes[j].y;
        var d = Math.hypot(dx, dy);
        if (d < maxD) {
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = 'rgba(52,211,153,' + ((1 - d / maxD) * 0.2) + ')';
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
    }

    for (var k = 0; k < nodes.length; k++) {
      var n = nodes[k];
      n.phi += 0.01;
      var glow = Math.sin(n.phi) * 0.3 + 0.5;

      var mdx = n.x - mouse.x, mdy = n.y - mouse.y;
      var md = Math.hypot(mdx, mdy);
      if (md < mouseD && md > 0) {
        var force = (1 - md / mouseD) * 0.4;
        n.vx += (mdx / md) * force;
        n.vy += (mdy / md) * force;
      }
      n.vx *= 0.97;
      n.vy *= 0.97;

      ctx.beginPath();
      var radius = n.r * (md < mouseD ? 1 + (1 - md / mouseD) * 0.8 : 1);
      ctx.arc(n.x, n.y, radius, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(52,211,153,' + glow + ')';
      ctx.shadowBlur = md < mouseD ? 12 : 6;
      ctx.shadowColor = 'rgba(52,211,153,0.4)';
      ctx.fill();
      ctx.shadowBlur = 0;

      n.x += n.vx;
      n.y += n.vy;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    }

    animId = requestAnimationFrame(draw);
  }

  window.addEventListener('mousemove', function(e) { mouse.x = e.clientX; mouse.y = e.clientY; });
  window.addEventListener('mouseleave', function() { mouse.x = -999; mouse.y = -999; });

  window.addEventListener('click', function(e) {
    for (var i = 0; i < 4; i++) {
      var n = mkNode();
      n.x = e.clientX; n.y = e.clientY;
      var angle = (Math.PI * 2 / 4) * i;
      n.vx = Math.cos(angle) * 1.5;
      n.vy = Math.sin(angle) * 1.5;
      nodes.push(n);
      if (nodes.length > 100) nodes.shift();
    }
  });

  resize(); initNodes(); draw();
  window.addEventListener('resize', function() {
    cancelAnimationFrame(animId);
    resize(); initNodes(); draw();
  });
})();
</script>
</body>
</html>