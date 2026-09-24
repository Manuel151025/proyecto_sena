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

use Core\Services\LimitadorIntentos;
use Core\Support\Validador;

$loginError   = null;
$loginSuccess = null;

// El bloqueo por intentos fallidos vive en la base de datos, no en la
// sesión: el contador anterior estaba en $_SESSION, así que bastaba con
// descartar la cookie entre peticiones para empezar de cero en cada
// intento. Ver Core\Services\LimitadorIntentos.
$limitador = new LimitadorIntentos();

// El correo enviado es lo que identifica el cupo; en un GET no hay ninguno,
// de modo que solo se evalúa el límite por IP.
$emailIntento = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bruto = $_POST['email'] ?? '';
    $emailIntento = is_array($bruto) ? '' : mb_strtolower(trim((string)$bruto), 'UTF-8');
}

$segundosBloqueo = $limitador->segundosBloqueo('login', $emailIntento);
$isBlocked = $segundosBloqueo > 0;

if ($isBlocked) {
    $minutos = LimitadorIntentos::minutos($segundosBloqueo);
    $loginError = "Has excedido el límite de intentos. Acceso bloqueado. Inténtalo de nuevo en {$minutos} minuto(s).";
}

if (isset($_SESSION['_flash_success'])) {
    $loginSuccess = $_SESSION['_flash_success'];
    unset($_SESSION['_flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    // El token CSRF ya lo validó requireCsrf() al cargar session.php; no se
    // repite aquí para que exista un solo punto donde se comprueba.

    $v        = new Validador($_POST);
    $email    = $v->email('email', 'El correo');
    $password = (string)($_POST['password'] ?? '');

    if ($v->hayErrores()) {
        // Un correo mal formado no consume cupo: no es un intento de
        // adivinar credenciales, es un error de tecleo.
        $loginError = $v->primerError();
    } elseif ($password === '' || strlen($password) > 200) {
        // El tope de longitud evita alimentar a bcrypt con megabytes de
        // relleno, que es un modo barato de consumir CPU del servidor.
        $loginError = 'Credenciales incorrectas.';
        $limitador->registrarFallo('login', $email);
    } elseif (attemptLogin($email, $password)) {
        $limitador->registrarExito('login', $email);
        header('Location: ' . APP_URL . '/index.php');
        exit;
    } else {
        $limitador->registrarFallo('login', $email);
        (new Core\Services\Auditoria())->accesoFallido($email);

        // El mensaje no distingue "no existe esa cuenta" de "la contraseña
        // no es esa": decirlo permitiría averiguar qué correos están dados
        // de alta. Tampoco se anuncia cuántos intentos quedan, que es
        // información útil solo para quien está probando a ciegas.
        $loginError = 'Credenciales incorrectas.';

        if ($limitador->estaBloqueado('login', $email)) {
            $isBlocked = true;
            $loginError = 'Has excedido el límite de intentos. Acceso bloqueado temporalmente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-app-url="<?= e(APP_URL) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — SENA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap Icons for modern inputs -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"
        integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  
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

  </style>
</head>
<body>
<canvas id="particle-canvas"></canvas>
<script src="<?= APP_URL ?>/assets/js/pestana.js?v=<?= filemtime(__DIR__ . '/assets/js/pestana.js') ?>"></script>

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
            <input type="email" name="email" id="login-email" placeholder="usuario@sena.edu.co" autocomplete="email" maxlength="100" required <?= $isBlocked ? 'disabled' : '' ?>>
            <i class="bi bi-envelope input-icon"></i>
          </div>
        </div>
        <div class="field">
          <label for="pw-login">Contraseña</label>
          <div class="input-group">
            <input type="password" name="password" id="pw-login" placeholder="••••••••" autocomplete="current-password" minlength="6" maxlength="60" required <?= $isBlocked ? 'disabled' : '' ?>>
            <i class="bi bi-lock input-icon"></i>
            <button type="button" class="toggle-password" id="toggle-pw-btn" aria-label="Mostrar contraseña">
              <i class="bi bi-eye" id="toggle-pw-icon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="submit-btn" <?= $isBlocked ? 'disabled' : '' ?>><?= $isBlocked ? 'Acceso Bloqueado' : 'Ingresar al sistema' ?></button>
      </form>

      <div class="card-footer">
        <a href="recover.php">¿Olvidaste tu contraseña?</a>
      </div>
    </div>
  </div>

</div>

<script src="<?= APP_URL ?>/assets/js/publico/login.js?v=<?= filemtime(__DIR__ . '/assets/js/publico/login.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/publico/particulas.js?v=<?= filemtime(__DIR__ . '/assets/js/publico/particulas.js') ?>"></script>
</body>
</html>