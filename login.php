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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    // Validar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        die('Error 403: Solicitud rechazada por validación de seguridad (Token CSRF inválido o ausente).');
    }

    $email    = $_POST['email']    ?? '';
    $password = $_POST['password'] ?? '';

    $user = attemptLogin($email, $password);

    if ($user) {
        unset($_SESSION['login_attempts']);
        unset($_SESSION['blocked_until']);
        header('Location: ' . APP_URL . '/index.php');
        exit;
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
    }

    .card:hover {
      border-color: var(--border-hover);
      box-shadow: 0 0 60px rgba(52, 211, 153, 0.04);
    }

    .card-header {
      text-align: center;
      margin-bottom: 36px;
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
          <input type="email" name="email" id="login-email" placeholder="usuario@sena.edu.co" autocomplete="email" required <?= $isBlocked ? 'disabled' : '' ?>>
        </div>
        <div class="field">
          <label for="pw-login">Contraseña</label>
          <input type="password" name="password" id="pw-login" placeholder="••••••••" autocomplete="current-password" required <?= $isBlocked ? 'disabled' : '' ?>>
        </div>
        <button type="submit" class="submit-btn" <?= $isBlocked ? 'disabled' : '' ?>><?= $isBlocked ? 'Acceso Bloqueado' : 'Ingresar al sistema' ?></button>
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
</body>
</html>