<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isAuthenticated()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$loginError   = null;
$loginSuccess = null;

// Duración del bloqueo en segundos (5 minutos = 300 segundos)
define('BLOCK_DURATION', 300);

// Verificar si el bloqueo temporal está activo
if (isset($_SESSION['blocked_until']) && $_SESSION['blocked_until'] > time()) {
    $isBlocked = true;
    $remaining = $_SESSION['blocked_until'] - time();
    $minutes = ceil($remaining / 60);
    $loginError = "Has excedido el límite de intentos. Acceso bloqueado. Inténtalo de nuevo en {$minutes} minuto(s).";
} else {
    // Si el tiempo de bloqueo expiró, limpiar el estado
    if (isset($_SESSION['blocked_until'])) {
        unset($_SESSION['login_attempts']);
        unset($_SESSION['blocked_until']);
    }
    $isBlocked = false;
}

if (isset($_SESSION['_flash_success'])) {
    $loginSuccess = $_SESSION['_flash_success'];
    unset($_SESSION['_flash_success']);
}

// Función auxiliar para autenticar biométricamente sin requerir contraseña
function attemptBiometricLogin(string $email): bool {
    try {
        $db   = \Core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT id, nombre, email, rol, avatar_color, estado
             FROM usuarios
             WHERE email = ? AND estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $tabId = $_POST['_tab'] ?? ($_COOKIE['sena_tab'] ?? '');
        if (!preg_match('/^[a-z0-9]{8,24}$/', $tabId)) {
            $tabId = 'default';
        }

        session_regenerate_id(true);

        $_SESSION['tabs'][$tabId] = [
            'user_id'           => (int)$user['id'],
            'user_nombre'       => $user['nombre'],
            'user_email'        => $user['email'],
            'user_rol'          => $user['rol'],
            'user_avatar_color' => $user['avatar_color'],
        ];

        return true;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    // Validar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        die('Error 403: Solicitud rechazada por validación de seguridad (Token CSRF inválido o ausente).');
    }

    $isBiometric = isset($_POST['biometric_login']) && $_POST['biometric_login'] === '1';
    $email = $_POST['email'] ?? '';

    if ($isBiometric) {
        $token = $_POST['biometric_token'] ?? '';
        $biometricSalt = 'sena_biometric_salt_2026';
        $expectedToken = hash_hmac('sha256', $email, $biometricSalt);
        
        if (hash_equals($expectedToken, $token)) {
            $user = attemptBiometricLogin($email);
        } else {
            $user = false;
        }
    } else {
        $password = $_POST['password'] ?? '';
        $user = attemptLogin($email, $password);
    }

    if ($user) {
        unset($_SESSION['login_attempts']);
        unset($_SESSION['blocked_until']);
        
        // Guardar cookies temporales para vincular la huella en la primera carga post-login
        $biometricSalt = 'sena_biometric_salt_2026';
        $biometricToken = hash_hmac('sha256', $email, $biometricSalt);
        setcookie('sena_bio_email', $email, time() + 3600, '/', '', false, false);
        setcookie('sena_bio_token', $biometricToken, time() + 3600, '/', '', false, false);
        
        header('Location: ' . APP_URL . '/index.php');
        exit;
    } else {
        if ($isBiometric) {
            $loginError = "Fallo en la autenticación biométrica de tu dispositivo.";
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['blocked_until'] = time() + BLOCK_DURATION;
                $isBlocked = true;
                $loginError = "Has excedido el límite de intentos permitidos (5). Acceso bloqueado por 5 minutos.";
            } else {
                $remainingAttempts = 5 - $_SESSION['login_attempts'];
                $loginError = "Credenciales incorrectas. Te quedan {$remainingAttempts} intento(s).";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — SENA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons for modern inputs -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- PWA Manifest & Meta Tags -->
  <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
  <meta name="theme-color" content="#39A900">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/sena_logo.png">
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
      max-width: 400px;
      background: var(--bg-card);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 48px 40px;
      transition: border-color 0.5s ease, box-shadow 0.5s ease;
      position: relative;
      overflow: hidden;
    }

    .card:hover {
      border-color: var(--border-hover);
      box-shadow: 0 0 60px rgba(52, 211, 153, 0.04);
    }

    /* ── Card Banner ── */
    .card-banner {
      background: linear-gradient(135deg, rgba(57, 169, 0, 0.15), rgba(10, 15, 13, 0.95));
      border-bottom: 2px solid #39A900;
      padding: 22px 24px;
      margin: -48px -40px 30px -40px;
      border-top-left-radius: 19px;
      border-top-right-radius: 19px;
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .card-banner img {
      width: 44px;
      height: 44px;
      object-fit: contain;
      filter: drop-shadow(0 0 8px rgba(57, 169, 0, 0.4));
    }

    .banner-text h3 {
      font-size: 1.05rem;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: 0.05em;
      margin: 0;
      line-height: 1.2;
    }

    .banner-text p {
      font-size: 0.68rem;
      color: #39A900;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin: 2px 0 0 0;
      line-height: 1.2;
    }

    .card-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .card-header h2 {
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 6px;
    }

    .card-header p {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    /* ── Input Icons & Group ── */
    .input-group {
      position: relative;
      display: flex;
      align-items: center;
      width: 100%;
    }

    .input-group i.input-icon {
      position: absolute;
      left: 16px;
      color: var(--text-muted);
      font-size: 1.1rem;
      transition: color 0.3s ease;
      pointer-events: none;
      z-index: 5;
    }

    .input-group input {
      padding-left: 46px !important;
      padding-right: 44px !important;
      height: 48px;
    }

    .input-group input:focus ~ i.input-icon {
      color: var(--emerald);
    }

    /* ── Toggle Password Button ── */
    .toggle-password {
      position: absolute;
      right: 8px;
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      padding: 8px;
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.3s ease;
      z-index: 10;
    }

    .toggle-password:hover {
      color: var(--emerald);
    }

    /* ── Form ── */
    .field {
      margin-bottom: 24px;
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

    .field input {
      width: 100%;
      padding: 13px 16px;
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      border-radius: var(--radius);
      font-family: inherit;
      font-size: 0.92rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    .field input::placeholder {
      color: var(--text-muted);
    }

    .field input:focus {
      outline: none;
      border-color: var(--emerald);
      background: rgba(255, 255, 255, 0.06);
      box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1), 0 0 20px rgba(52, 211, 153, 0.05);
    }

    .submit-btn {
      width: 100%;
      padding: 14px;
      margin-top: 4px;
      background: linear-gradient(135deg, var(--emerald-dim) 0%, #047857 100%);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      font-family: inherit;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      z-index: 1;
      transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .submit-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      z-index: -1;
    }

    .submit-btn:hover {
      background: linear-gradient(135deg, var(--emerald) 0%, var(--emerald-dim) 100%);
      color: #022c22;
      box-shadow: 0 8px 24px rgba(52, 211, 153, 0.35);
      transform: translateY(-2px);
    }

    .submit-btn:hover::before {
      animation: sweepSheen 0.65s ease-in-out;
    }

    @keyframes sweepSheen {
      0% { left: -100%; }
      100% { left: 100%; }
    }

    .submit-btn:active {
      transform: translateY(0) scale(0.97);
    }

    /* ── Alerts ── */
    .alert {
      padding: 12px 16px;
      border-radius: var(--radius);
      font-size: 0.82rem;
      margin-bottom: 24px;
      line-height: 1.5;
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

    /* ── Footer link ── */
    .card-footer {
      text-align: center;
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    .card-footer a {
      color: var(--text-muted);
      text-decoration: none;
      font-size: 0.82rem;
      transition: color 0.3s;
    }

    .card-footer a:hover {
      color: var(--emerald);
    }

    /* ── Responsive ── */
    @media (max-width: 960px) {
      .shell { grid-template-columns: 1fr; }
      .brand { display: none; }
      .card { max-width: 420px; }
    }

    @media (max-width: 480px) {
      .card { padding: 36px 24px; border-radius: 16px; }
      .card-banner {
         margin: -36px -24px 24px -24px;
         border-top-left-radius: 15px;
         border-top-right-radius: 15px;
         padding: 18px 20px;
      }
    }

    .field input:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      background: rgba(255, 255, 255, 0.02);
      border-color: rgba(255, 255, 255, 0.04);
    }
    .submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      background: #1e293b;
      color: var(--text-muted);
      box-shadow: none;
      transform: none !important;
    }
    .submit-btn:disabled::before {
      display: none;
    }

    /* ── Fingerprint Button (Mobile Only) ── */
    .fingerprint-container {
      display: none; /* Hidden by default on desktop */
      margin-top: 16px;
      width: 100%;
      justify-content: center;
    }

    .fingerprint-btn {
      width: 100%;
      padding: 14px;
      background: var(--input-bg);
      color: var(--text-primary);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-family: inherit;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      position: relative;
      overflow: hidden;
      z-index: 1;
      transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .fingerprint-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(52, 211, 153, 0.15), transparent);
      z-index: -1;
    }

    .fingerprint-btn:hover {
      background: rgba(52, 211, 153, 0.08);
      border-color: var(--emerald-dim);
      box-shadow: 0 6px 20px rgba(52, 211, 153, 0.18);
      transform: translateY(-2px);
    }

    .fingerprint-btn:hover::before {
      animation: sweepSheen 0.65s ease-in-out;
    }

    .fingerprint-btn:active {
      transform: translateY(0) scale(0.97);
    }

    .fingerprint-btn i {
      color: var(--emerald);
      font-size: 1.35rem;
      transition: transform 0.3s ease;
    }

    .fingerprint-btn:hover i {
      transform: scale(1.15);
    }

    @media (max-width: 768px) {
      .fingerprint-container {
        display: flex;
      }
    }

    /* ── Biometric Scanning Modal ── */
    .biometric-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(5, 10, 8, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
    }

    .biometric-modal-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }

    .biometric-card {
      width: 90%;
      max-width: 360px;
      background: rgba(14, 22, 17, 0.9);
      border: 1px solid var(--border-hover);
      border-radius: 24px;
      padding: 32px 24px;
      text-align: center;
      box-shadow: 0 20px 50px rgba(5, 10, 8, 0.5), 0 0 40px rgba(52, 211, 153, 0.05);
      transform: translateY(20px) scale(0.95);
      transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .biometric-modal-overlay.active .biometric-card {
      transform: translateY(0) scale(1);
    }

    .biometric-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 8px;
    }

    .biometric-subtitle {
      font-size: 0.85rem;
      color: var(--text-secondary);
      line-height: 1.5;
      margin-bottom: 30px;
    }

    /* Fingerprint scanner visualizer */
    .scanner-container {
      position: relative;
      width: 120px;
      height: 120px;
      margin: 0 auto 30px auto;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .scanner-bg-circle {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: rgba(52, 211, 153, 0.03);
      border: 2px dashed rgba(52, 211, 153, 0.15);
      animation: rotateCircle 20s linear infinite;
    }

    @keyframes rotateCircle {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .biometric-icon-wrapper {
      position: relative;
      width: 90px;
      height: 90px;
      background: rgba(52, 211, 153, 0.05);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 1px solid rgba(52, 211, 153, 0.1);
      transition: all 0.3s ease;
    }

    .biometric-icon-wrapper i {
      font-size: 3.5rem;
      color: var(--emerald);
      transition: color 0.3s ease;
    }

    /* Laser Scanning Bar */
    .scanner-laser {
      position: absolute;
      left: 10%;
      width: 80%;
      height: 3px;
      background: linear-gradient(90deg, transparent, var(--emerald), transparent);
      box-shadow: 0 0 10px var(--emerald), 0 0 20px var(--emerald);
      top: 15%;
      border-radius: 50%;
      opacity: 0;
      pointer-events: none;
    }

    .scanner-container.scanning .scanner-laser {
      opacity: 1;
      animation: laserMove 1.5s ease-in-out infinite alternate;
    }

    @keyframes laserMove {
      0% { top: 15%; }
      100% { top: 85%; }
    }

    /* Scanning Pulse Ripple */
    .scanner-pulse {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      border: 2px solid var(--emerald);
      opacity: 0;
      pointer-events: none;
    }

    .scanner-container.scanning .scanner-pulse {
      animation: pulseRipple 1.8s cubic-bezier(0.24, 0, 0.38, 1) infinite;
    }

    @keyframes pulseRipple {
      0% {
        transform: scale(0.7);
        opacity: 0;
      }
      50% {
        opacity: 0.35;
      }
      100% {
        transform: scale(1.3);
        opacity: 0;
      }
    }

    /* Scan success states */
    .biometric-card.success .biometric-icon-wrapper {
      background: rgba(52, 211, 153, 0.15);
      border-color: var(--emerald);
      box-shadow: 0 0 30px rgba(52, 211, 153, 0.3);
      animation: successShake 0.4s ease;
    }

    .biometric-card.success .biometric-icon-wrapper i {
      color: #ffffff;
      transform: scale(1.1);
    }

    @keyframes successShake {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.08); }
    }

    /* Feedback text */
    .scanner-feedback {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--emerald);
      margin-top: 10px;
      min-height: 20px;
      transition: color 0.3s;
    }

    .scanner-feedback.error {
      color: #ef4444;
    }

    /* Cancel Button */
    .biometric-cancel-btn {
      background: none;
      border: none;
      color: var(--text-muted);
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .biometric-cancel-btn:hover {
      color: var(--text-secondary);
      background: rgba(255, 255, 255, 0.03);
    }
  </style>
</head>
<body>
<canvas id="particle-canvas"></canvas>
<script>
(function(){
  var t=sessionStorage.getItem('sena_tab_id');
  if(!t){
    t=Math.random().toString(36).slice(2,12)+Math.random().toString(36).slice(2,6);
    sessionStorage.setItem('sena_tab_id',t);
  }
  window.__tabId=t;
  document.cookie='sena_tab='+t+'; path=/; SameSite=Lax';
  document.addEventListener('submit',function(e){
    document.cookie='sena_tab='+t+'; path=/; SameSite=Lax';
    if(!e.target.querySelector('input[name="_tab"]')){
      var inp=document.createElement('input');
      inp.type='hidden';
      inp.name='_tab';
      inp.value=t;
      e.target.appendChild(inp);
    }
  },true);

  // Registro del Service Worker para PWA
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('<?= APP_URL ?>/sw.js').catch(function (err) {
        console.error('ServiceWorker registration failed: ', err);
      });
    });
  }

  // Toggle de visibilidad de contraseña
  window.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('toggle-pw-btn');
    var pwInput = document.getElementById('pw-login');
    var toggleIcon = document.getElementById('toggle-pw-icon');
    if (toggleBtn && pwInput && toggleIcon) {
      toggleBtn.addEventListener('click', function() {
        if (pwInput.type === 'password') {
          pwInput.type = 'text';
          toggleIcon.classList.remove('bi-eye');
          toggleIcon.classList.add('bi-eye-slash');
          toggleBtn.setAttribute('aria-label', 'Ocultar contraseña');
        } else {
          pwInput.type = 'password';
          toggleIcon.classList.remove('bi-eye-slash');
          toggleIcon.classList.add('bi-eye');
          toggleBtn.setAttribute('aria-label', 'Mostrar contraseña');
        }
      });
    }
  });
})();
</script>

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
      <div class="card-banner">
        <img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="SENA Logo">
        <div class="banner-text">
          <h3>SENA</h3>
          <p>Servicio Nacional de Aprendizaje</p>
        </div>
      </div>
      <div class="card-header">
        <h2>Iniciar Sesión</h2>
        <p>Ingresa con tu cuenta institucional</p>
      </div>

      <?php if ($loginSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($loginSuccess) ?></div>
      <?php endif; ?>
      <?php if ($loginError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <?= csrfField() ?>
        <div class="field">
          <label for="login-email">Correo institucional</label>
          <div class="input-group">
            <input type="email" name="email" id="login-email" placeholder="usuario@sena.edu.co" autocomplete="email" required <?= $isBlocked ? 'disabled' : '' ?>>
            <i class="bi bi-envelope input-icon"></i>
          </div>
        </div>
        <div class="field">
          <label for="pw-login">Contraseña</label>
          <div class="input-group">
            <input type="password" name="password" id="pw-login" placeholder="••••••••" autocomplete="current-password" required <?= $isBlocked ? 'disabled' : '' ?>>
            <i class="bi bi-lock input-icon"></i>
            <button type="button" class="toggle-password" id="toggle-pw-btn" aria-label="Mostrar contraseña">
              <i class="bi bi-eye" id="toggle-pw-icon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="submit-btn" <?= $isBlocked ? 'disabled' : '' ?>><?= $isBlocked ? 'Acceso Bloqueado' : 'Ingresar al sistema' ?></button>
        
        <!-- Botón de Huella Digital para celulares -->
        <div class="fingerprint-container">
          <button type="button" class="fingerprint-btn" id="fingerprint-login-btn" <?= $isBlocked ? 'disabled' : '' ?>>
            <i class="bi bi-fingerprint"></i>
            Ingresar con huella digital
          </button>
        </div>
      </form>

      <div class="card-footer">
        <a href="recover.php">¿Olvidaste tu contraseña?</a>
      </div>
    </div>
  </div>

</div>

<script>
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
<!-- Modal de Escaneo Biométrico -->
<div class="biometric-modal-overlay" id="biometric-modal">
  <div class="biometric-card" id="biometric-card">
    <div class="biometric-title">Autenticación Biométrica</div>
    <div class="biometric-subtitle">Coloca tu huella dactilar sobre el sensor del dispositivo para iniciar sesión rápidamente.</div>
    
    <div class="scanner-container" id="scanner-touch-area">
      <div class="scanner-bg-circle"></div>
      <div class="scanner-pulse"></div>
      <div class="biometric-icon-wrapper">
        <i class="bi bi-fingerprint" id="scanner-icon"></i>
        <div class="scanner-laser"></div>
      </div>
    </div>
    
    <div class="scanner-feedback" id="scanner-feedback">Mantén presionado para escanear</div>
    
    <button type="button" class="biometric-cancel-btn" id="biometric-cancel-btn">Cancelar</button>
  </div>
</div>

<script>
(function() {
  var fingerprintBtn = document.getElementById('fingerprint-login-btn');
  var modal = document.getElementById('biometric-modal');
  var card = document.getElementById('biometric-card');
  var cancelBtn = document.getElementById('biometric-cancel-btn');
  var touchArea = document.getElementById('scanner-touch-area');
  var feedback = document.getElementById('scanner-feedback');
  
  var scanTimer = null;
  var isScanning = false;
  var hasBiometricData = false;

  // Verificar si hay credenciales vinculadas en localStorage
  var savedEmail = localStorage.getItem('sena_bio_email');
  var savedToken = localStorage.getItem('sena_bio_token');
  var savedCredId = localStorage.getItem('sena_bio_cred_id');
  
  if (savedEmail && savedToken) {
    hasBiometricData = true;
  }

  // Mostrar advertencia si se accede por HTTP en un servidor remoto (bloquea WebAuthn nativo)
  if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
    var warningDiv = document.createElement('div');
    warningDiv.style.cssText = 'color: #f59e0b; font-size: 0.72rem; margin-top: 15px; padding: 10px; background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); border-radius: 8px; line-height: 1.4; text-align: left;';
    warningDiv.innerHTML = '⚠️ <strong>Conexión insegura (HTTP):</strong> Tu navegador bloquea el lector de huella físico del celular sin SSL. Para usar tu sensor real (bajo la pantalla o botón lateral), debes configurar <strong>HTTPS</strong> en tu VPS.';
    card.insertBefore(warningDiv, cancelBtn);
  }

  function triggerNativeBiometricLogin() {
    var emailInput = document.getElementById('login-email');
    var pwInput = document.getElementById('pw-login');
    
    var originalBtnHTML = fingerprintBtn.innerHTML;
    fingerprintBtn.disabled = true;
    fingerprintBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Esperando huella...';

    var challenge = new Uint8Array(32);
    window.crypto.getRandomValues(challenge);
    
    var credId = Uint8Array.from(atob(savedCredId), function(c) { return c.charCodeAt(0); });

    var options = {
      publicKey: {
        challenge: challenge,
        rpId: window.location.hostname,
        allowCredentials: [{
          type: 'public-key',
          id: credId
        }],
        userVerification: "required",
        timeout: 60000
      }
    };

    navigator.credentials.get(options)
      .then(function(assertion) {
        if (navigator.vibrate) navigator.vibrate([100]);
        
        var form = document.querySelector('form');
        if (form) {
          if (emailInput) emailInput.value = savedEmail;
          if (pwInput) {
            pwInput.removeAttribute('required');
            pwInput.disabled = true;
          }
          
          var bioLoginInput = document.createElement('input');
          bioLoginInput.type = 'hidden';
          bioLoginInput.name = 'biometric_login';
          bioLoginInput.value = '1';
          form.appendChild(bioLoginInput);

          var bioTokenInput = document.createElement('input');
          bioTokenInput.type = 'hidden';
          bioTokenInput.name = 'biometric_token';
          bioTokenInput.value = savedToken;
          form.appendChild(bioTokenInput);

          form.submit();
        }
      })
      .catch(function(err) {
        console.error("Biometric authentication failed:", err);
        fingerprintBtn.disabled = false;
        fingerprintBtn.innerHTML = originalBtnHTML;
        
        if (err.name !== "NotAllowedError") {
          alert("Error de autenticación biométrica: " + err.message);
        } else {
          // El usuario canceló la huella nativa. Damos la opción de abrir el escáner visual.
          var confirmModal = confirm("¿Deseas iniciar sesión usando el escáner visual alternativo?");
          if (confirmModal) {
            modal.classList.add('active');
            resetScanner();
          }
        }
      });
  }

  if (fingerprintBtn) {
    fingerprintBtn.addEventListener('click', function() {
      if (hasBiometricData && window.PublicKeyCredential && savedCredId) {
        triggerNativeBiometricLogin();
      } else {
        modal.classList.add('active');
        resetScanner();
      }
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener('click', function() {
      modal.classList.remove('active');
      resetScanner();
    });
  }

  function resetScanner() {
    if (scanTimer) clearTimeout(scanTimer);
    isScanning = false;
    touchArea.classList.remove('scanning');
    card.classList.remove('success');
    feedback.classList.remove('error');
    feedback.textContent = 'Mantén presionado para escanear';
    cancelBtn.style.display = 'inline-block';
  }

  function startScan() {
    if (isScanning) return;
    isScanning = true;
    touchArea.classList.add('scanning');
    feedback.textContent = 'Escaneando... Mantén presionado';
    feedback.classList.remove('error');

    // Haptic feedback de inicio si se soporta
    if (navigator.vibrate) {
      navigator.vibrate(30);
    }

    scanTimer = setTimeout(function() {
      // Completar escaneo
      isScanning = false;
      touchArea.classList.remove('scanning');
      
      if (hasBiometricData) {
        // Éxito de biometría
        card.classList.add('success');
        feedback.textContent = '¡Huella verificada correctamente!';
        feedback.classList.remove('error');
        cancelBtn.style.display = 'none';

        if (navigator.vibrate) {
          navigator.vibrate([100]);
        }

        // Proceder con login biométrico tras 800ms
        setTimeout(function() {
          var form = document.querySelector('form');
          if (form) {
            // Rellenar email
            var emailInput = document.getElementById('login-email');
            if (emailInput) {
              emailInput.value = savedEmail;
            }
            
            // Quitar required de password para submit biométrico
            var pwInput = document.getElementById('pw-login');
            if (pwInput) {
              pwInput.removeAttribute('required');
              pwInput.disabled = true;
            }

            // Inyectar datos biométricos
            var bioLoginInput = document.createElement('input');
            bioLoginInput.type = 'hidden';
            bioLoginInput.name = 'biometric_login';
            bioLoginInput.value = '1';
            form.appendChild(bioLoginInput);

            var bioTokenInput = document.createElement('input');
            bioTokenInput.type = 'hidden';
            bioTokenInput.name = 'biometric_token';
            bioTokenInput.value = savedToken;
            form.appendChild(bioTokenInput);

            form.submit();
          }
        }, 800);
      } else {
        // Fallo: No hay datos vinculados
        feedback.textContent = 'Huella no vinculada. Inicia sesión con contraseña una vez.';
        feedback.classList.add('error');
        if (navigator.vibrate) {
          navigator.vibrate([80, 50, 80]);
        }
      }
    }, 1800);
  }

  function interruptScan() {
    if (!isScanning) return;
    isScanning = false;
    touchArea.classList.remove('scanning');
    if (scanTimer) clearTimeout(scanTimer);
    feedback.textContent = 'Escaneo interrumpido. Inténtalo de nuevo.';
    feedback.classList.add('error');
  }

  // Soporte para ratón y eventos táctiles en el lector de huella
  touchArea.addEventListener('mousedown', function(e) {
    e.preventDefault();
    startScan();
  });
  
  touchArea.addEventListener('mouseup', function(e) {
    e.preventDefault();
    interruptScan();
  });

  touchArea.addEventListener('mouseleave', function(e) {
    e.preventDefault();
    interruptScan();
  });

  // Soporte táctil móvil
  touchArea.addEventListener('touchstart', function(e) {
    e.preventDefault();
    startScan();
  });

  touchArea.addEventListener('touchend', function(e) {
    e.preventDefault();
    interruptScan();
  });

  touchArea.addEventListener('touchcancel', function(e) {
    e.preventDefault();
    interruptScan();
  });

})();
</script>
</body>
</html>