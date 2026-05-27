<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Si ya está logueado, redirigir al index
if (isAuthenticated()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$loginError   = null;
$loginSuccess = null;
$isBlocked    = false;

// Mensaje flash de éxito (ej. desde recover.php después de cambiar contraseña)
if (isset($_SESSION['_flash_success'])) {
    $loginSuccess = $_SESSION['_flash_success'];
    unset($_SESSION['_flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $email    = $_POST['email']    ?? '';
    $password = $_POST['password'] ?? '';

    $user = attemptLogin($email, $password);

    if ($user) {
        unset($_SESSION['login_attempts']);
        header('Location: ' . APP_URL . '/index.php');
        exit;
    } else {
        $loginError = "Credenciales incorrectas. Verifica tu correo y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — SENA · Sistema de Seguimiento de Proyectos Formativos</title>
  <meta name="description" content="Plataforma institucional del SENA para la gestión de fichas, instructores y aprendices.">
  <!-- Iconos -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- CSS Nano Login -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/login-nano.css">
</head>
<body>

<!-- Tab ID -->
<script>
(function () {
  var t = sessionStorage.getItem('sena_tab_id');
  if (!t) {
    t = Math.random().toString(36).slice(2,12) + Math.random().toString(36).slice(2,6);
    sessionStorage.setItem('sena_tab_id', t);
  }
  window.__tabId = t;
  document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
  document.addEventListener('submit', function(e) {
    document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
    if (!e.target.querySelector('input[name="_tab"]')) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = '_tab'; inp.value = t;
      e.target.appendChild(inp);
    }
  }, true);
})();
</script>

<!-- Canvas de fondo animado -->
<canvas id="nano-canvas"></canvas>

<div class="login-shell">

  <!-- ══════════════ PANEL IZQUIERDO — BRANDING ══════════════ -->
  <div class="nano-brand">
    <div class="scan-lines"></div>
    <div class="hex-grid">
      <svg viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <pattern id="hex" width="60" height="52" patternUnits="userSpaceOnUse" patternTransform="scale(1.5)">
            <polygon points="30,1 58,16 58,46 30,61 2,46 2,16"
              fill="none" stroke="rgba(57,169,0,0.18)" stroke-width="0.8"/>
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#hex)"/>
        <!-- Hexágonos iluminados aleatorios -->
        <polygon points="90,53 118,68 118,98 90,113 62,98 62,68" fill="rgba(57,169,0,0.06)" stroke="rgba(57,169,0,0.4)" stroke-width="1">
          <animate attributeName="opacity" values="0.3;1;0.3" dur="3s" repeatCount="indefinite"/>
        </polygon>
        <polygon points="210,131 238,146 238,176 210,191 182,176 182,146" fill="rgba(57,169,0,0.04)" stroke="rgba(57,169,0,0.3)" stroke-width="1">
          <animate attributeName="opacity" values="1;0.2;1" dur="4.5s" repeatCount="indefinite"/>
        </polygon>
        <polygon points="360,53 388,68 388,98 360,113 332,98 332,68" fill="rgba(57,169,0,0.06)" stroke="rgba(57,169,0,0.5)" stroke-width="1">
          <animate attributeName="opacity" values="0.4;1;0.4" dur="2.8s" repeatCount="indefinite"/>
        </polygon>
        <polygon points="150,287 178,302 178,332 150,347 122,332 122,302" fill="rgba(57,169,0,0.05)" stroke="rgba(57,169,0,0.35)" stroke-width="1">
          <animate attributeName="opacity" values="0.6;0.1;0.6" dur="5s" repeatCount="indefinite"/>
        </polygon>
        <polygon points="480,183 508,198 508,228 480,243 452,228 452,198" fill="rgba(57,169,0,0.08)" stroke="rgba(57,169,0,0.6)" stroke-width="1.2">
          <animate attributeName="opacity" values="0.2;1;0.2" dur="3.5s" repeatCount="indefinite"/>
        </polygon>
        <!-- Líneas de conexión -->
        <line x1="90" y1="83" x2="210" y2="161" stroke="rgba(57,169,0,0.15)" stroke-width="0.6" stroke-dasharray="4,6">
          <animate attributeName="stroke-dashoffset" from="0" to="-100" dur="3s" repeatCount="indefinite"/>
        </line>
        <line x1="210" y1="161" x2="360" y2="83" stroke="rgba(57,169,0,0.12)" stroke-width="0.6" stroke-dasharray="4,6">
          <animate attributeName="stroke-dashoffset" from="0" to="100" dur="4s" repeatCount="indefinite"/>
        </line>
        <line x1="150" y1="317" x2="480" y2="213" stroke="rgba(57,169,0,0.1)" stroke-width="0.5" stroke-dasharray="3,8">
          <animate attributeName="stroke-dashoffset" from="0" to="-120" dur="5s" repeatCount="indefinite"/>
        </line>
      </svg>
    </div>

    <!-- Partículas flotantes -->
    <div class="nano-particles">
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
    </div>

    <!-- Contenido superior -->
    <div class="brand-top">
      <div class="nano-logo-wrap">
        <div class="nano-logo-img">
          <img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="Logo SENA">
        </div>
        <div class="nano-logo-text">
          <span class="abbr">SENA</span>
          <span class="full">Servicio Nacional de Aprendizaje</span>
        </div>
      </div>

      <div class="nano-title">
        <span class="accent">// SSPF · v<?= APP_VERSION ?></span>
        Sistema de<br>Seguimiento de<br>Proyectos Formativos
      </div>

      <div class="nano-subtitle-box">
        <p class="nano-desc">
          Plataforma institucional del <strong>SENA Colombia</strong> para la gestión integral
          de <strong>Fichas de Formación</strong>, instructores, aprendices y el seguimiento
          de <strong>Proyectos Formativos</strong>.
        </p>
      </div>

    </div>


    <!-- Orbe central animado -->
    <div class="brand-center">
      <div class="nano-orb-wrap">
        <div class="nano-orb">
          <div class="orbit-ring"></div>
          <div class="orbit-ring"></div>
          <div class="orbit-ring"></div>
          <div class="orb-inner">
            <img src="<?= APP_URL ?>/assets/img/sena_logo.png"
                 alt="SENA" class="orb-center-img">
          </div>
        </div>
      </div>
    </div>

    <!-- Footer del brand -->
    <div class="brand-footer">
      <div class="nano-footer-text">© 2026 Servicio Nacional de Aprendizaje · Colombia</div>
      <div class="system-status">
        <span class="status-dot"></span>
        Sistema en línea · Entorno <?= DEV_MODE ? 'Desarrollo' : 'Producción' ?>
      </div>
    </div>
  </div>

  <!-- ══════════════ PANEL DERECHO — FORMULARIO ══════════════ -->
  <div class="nano-form-panel">
    <div class="nano-form">

      <!-- Header -->
      <div class="form-nano-header">
        <div class="form-nano-logo">
          <img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="SENA">
        </div>
        <h2>Bienvenido de <span class="highlight">nuevo</span></h2>
        <p class="subtitle">// Sistema de Seguimiento de Proyectos Formativos</p>
      </div>

      <div class="nano-divider"></div>

      <!-- Alertas -->
      <?php if ($loginSuccess): ?>
        <div class="nano-alert success" role="alert">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($loginSuccess) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($loginError): ?>
        <div class="nano-alert danger" role="alert">
          <i class="bi bi-shield-exclamation"></i>
          <span><?= htmlspecialchars($loginError) ?></span>
        </div>
      <?php endif; ?>

      <!-- Formulario -->
      <form method="post" id="login-form" autocomplete="on">

        <!-- Email -->
        <div class="nano-field">
          <div class="nano-label">
            <span>Correo institucional</span>
          </div>
          <div class="nano-input-wrap">
            <input
              type="email"
              name="email"
              id="login-email"
              class="nano-input"
              placeholder="usuario@sena.edu.co"
              autocomplete="email"
              <?= $isBlocked ? 'disabled' : '' ?>
              required>
            <i class="bi bi-envelope-fill nano-input-icon"></i>
          </div>
        </div>

        <!-- Contraseña -->
        <div class="nano-field">
          <div class="nano-label">
            <span>Contraseña</span>
            <a href="recover.php">¿Olvidaste tu contraseña?</a>
          </div>
          <div class="nano-input-wrap">
            <input
              type="password"
              name="password"
              id="pw-login"
              class="nano-input"
              placeholder="••••••••"
              autocomplete="current-password"
              <?= $isBlocked ? 'disabled' : '' ?>
              required>
            <i class="bi bi-lock-fill nano-input-icon"></i>
            <button type="button" class="pw-toggle-btn" data-pw-toggle="#pw-login" <?= $isBlocked ? 'disabled' : '' ?>>
              <i class="bi bi-eye" id="pw-eye-icon"></i>
            </button>
          </div>
        </div>

        <!-- Botón submit -->
        <button type="submit" id="login-submit" class="nano-btn" <?= $isBlocked ? 'disabled' : '' ?>>
          <i class="bi bi-shield-lock-fill"></i>
          Ingresar al sistema
          <i class="bi bi-arrow-right"></i>
        </button>
      </form>

      <!-- Footer del form -->
      <div class="form-nano-footer">
        <button type="button" class="theme-btn" onclick="toggleTheme()" aria-label="Cambiar tema">
          <i class="bi bi-moon-stars" id="theme-icon"></i>
        </button>
        <span class="nano-copyright">SENA · Sistema Institucional · <?= date('Y') ?></span>
      </div>

      <?php if (DEV_MODE): ?>
        <div style="margin-top:1.5rem; padding: 0.75rem 1rem; background: rgba(57,169,0,0.06); border: 1px dashed rgba(57,169,0,0.2); border-radius:8px; font-size:0.72rem; font-family: 'JetBrains Mono', monospace; color: rgba(57,169,0,0.7); text-align:center;">
          <i class="bi bi-code-slash"></i> DEV_MODE activo —
          <a href="recover.php" style="color: rgba(57,169,0,0.9);">Recuperar contraseña</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.login-shell -->

<script>
/* ══════════════════════════════════════════
   SENA — Login Nanotecnología · Scripts
══════════════════════════════════════════ */

/* ── Toggle contraseña ── */
document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.querySelector(btn.dataset.pwToggle);
    const icon  = btn.querySelector('i');
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
  });
});

/* ── Toggle de tema ── */
function toggleTheme() {
  const root   = document.documentElement;
  const icon   = document.getElementById('theme-icon');
  const isDark = root.dataset.theme === 'dark';
  root.dataset.theme = isDark ? 'light' : 'dark';
  icon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars';
  localStorage.setItem('sena_theme', root.dataset.theme);
}
(function() {
  const saved = localStorage.getItem('sena_theme');
  if (saved) {
    document.documentElement.dataset.theme = saved;
    const icon = document.getElementById('theme-icon');
    if (icon) icon.className = saved === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun-fill';
  }
})();

/* ── Canvas Red Neuronal Nano ── */
(function() {
  const canvas = document.getElementById('nano-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, nodes = [], mouse = { x: -999, y: -999 }, animId;
  const GREEN = '94,203,0';

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  function mkNode() {
    return {
      x:   Math.random() * W,
      y:   Math.random() * H,
      vx:  (Math.random() - 0.5) * 0.45,
      vy:  (Math.random() - 0.5) * 0.45,
      r:   Math.random() * 1.8 + 0.5,
      phi: Math.random() * Math.PI * 2
    };
  }

  function initNodes() {
    nodes = [];
    const count = Math.min(70, Math.floor(W * H / 15000));
    for (let i = 0; i < count; i++) nodes.push(mkNode());
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    const maxD = 150, mouseD = 180;

    /* Conexiones */
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x;
        const dy = nodes[i].y - nodes[j].y;
        const d  = Math.hypot(dx, dy);
        if (d < maxD) {
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = `rgba(${GREEN},${(1 - d / maxD) * 0.4})`;
          ctx.lineWidth   = 0.7;
          ctx.stroke();
        }
      }
    }

    /* Nodos */
    nodes.forEach(n => {
      n.phi += 0.012;
      const glow = Math.sin(n.phi) * 0.35 + 0.55;

      /* Repulsión/atracción al mouse */
      const mdx = n.x - mouse.x, mdy = n.y - mouse.y;
      const md  = Math.hypot(mdx, mdy);
      if (md < mouseD && md > 0) {
        const force = (1 - md / mouseD) * 0.6;
        n.vx += (mdx / md) * force;
        n.vy += (mdy / md) * force;
      }
      /* Damping */
      n.vx *= 0.97; n.vy *= 0.97;

      ctx.beginPath();
      ctx.arc(n.x, n.y, n.r * (md < mouseD ? 1 + (1 - md/mouseD) * 1.5 : 1), 0, Math.PI * 2);
      ctx.fillStyle   = `rgba(${GREEN},${glow})`;
      ctx.shadowBlur  = md < mouseD ? 18 : 9;
      ctx.shadowColor = `rgba(${GREEN},0.7)`;
      ctx.fill();
      ctx.shadowBlur  = 0;

      n.x += n.vx;
      n.y += n.vy;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    });

    animId = requestAnimationFrame(draw);
  }

  /* Mouse tracking */
  window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
  window.addEventListener('mouseleave', () => { mouse.x = -999; mouse.y = -999; });

  /* Click: crear onda expansiva de nodos */
  window.addEventListener('click', e => {
    for (let i = 0; i < 6; i++) {
      const n = mkNode();
      n.x = e.clientX; n.y = e.clientY;
      const angle = (Math.PI * 2 / 6) * i;
      n.vx = Math.cos(angle) * 2.5;
      n.vy = Math.sin(angle) * 2.5;
      nodes.push(n);
      if (nodes.length > 100) nodes.shift();
    }
  });

  resize(); initNodes(); draw();
  window.addEventListener('resize', () => {
    cancelAnimationFrame(animId);
    resize(); initNodes(); draw();
  });
})();

/* ── Efecto 3D Tilt en el formulario ── */
(function() {
  const card = document.querySelector('.nano-form');
  if (!card) return;
  const panel = document.querySelector('.nano-form-panel');

  let ticking = false;
  panel?.addEventListener('mousemove', e => {
    if (!ticking) {
      requestAnimationFrame(() => {
        const rect = card.getBoundingClientRect();
        const cx   = rect.left + rect.width  / 2;
        const cy   = rect.top  + rect.height / 2;
        const dx   = (e.clientX - cx) / (rect.width  / 2);
        const dy   = (e.clientY - cy) / (rect.height / 2);
        card.style.transform =
          `perspective(900px) rotateY(${dx * 5}deg) rotateX(${-dy * 4}deg) translateZ(4px)`;
        ticking = false;
      });
      ticking = true;
    }
  });
  panel?.addEventListener('mouseleave', () => {
    card.style.transform = '';
    card.style.transition = 'transform 0.6s cubic-bezier(0.22,1,0.36,1), border-color 0.4s, box-shadow 0.4s';
    setTimeout(() => { card.style.transition = ''; }, 620);
  });
})();

/* ── Hover en stats: efecto conteo ── */
document.querySelectorAll('.nano-stat').forEach(stat => {
  stat.addEventListener('mouseenter', () => {
    stat.style.transition = 'all 0.3s cubic-bezier(0.34,1.56,0.64,1)';
  });
});

/* ── Ripple en el botón ── */
document.querySelector('.nano-btn')?.addEventListener('click', function(e) {
  if (this.disabled) return;
  const ripple = document.createElement('span');
  const rect   = this.getBoundingClientRect();
  const size   = Math.max(rect.width, rect.height) * 2;
  ripple.style.cssText = `
    position:absolute;border-radius:50%;pointer-events:none;
    width:${size}px;height:${size}px;
    left:${e.clientX - rect.left - size/2}px;
    top:${e.clientY - rect.top  - size/2}px;
    background:rgba(255,255,255,0.25);
    transform:scale(0);animation:ripple-anim 0.6s ease-out forwards;
  `;
  this.appendChild(ripple);
  setTimeout(() => ripple.remove(), 700);
});

/* ── Animación de carga en el submit ── */
document.getElementById('login-form')?.addEventListener('submit', function() {
  const btn = document.getElementById('login-submit');
  if (btn && !btn.disabled) {
    btn.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="animation:spin .7s linear infinite">
        <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
        <path d="M12 2a10 10 0 0 1 10 10" stroke="white" stroke-width="3" stroke-linecap="round"/>
      </svg>
      Verificando acceso...`;
    btn.disabled = true;
  }
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes ripple-anim {
  to { transform: scale(1); opacity: 0; }
}
</style>
</body>
</html>