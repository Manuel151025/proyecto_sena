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

$loginError = null;
$loginSuccess = null;
$isBlocked = false;

// Mensaje flash de éxito (ej. desde recover.php después de cambiar contraseña)
if (isset($_SESSION['_flash_success'])) {
    $loginSuccess = $_SESSION['_flash_success'];
    unset($_SESSION['_flash_success']);
}

// Comprobar bloqueo (Eliminado por petición del usuario)
// $isBlocked siempre será false.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = attemptLogin($email, $password);

    if ($user) {
        // Login exitoso
        unset($_SESSION['login_attempts']);
        header('Location: ' . APP_URL . '/index.php');
        exit;
    } else {
        // Error de login
        $loginError = "Credenciales incorrectas.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SENA</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- CSS Institucional -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/theme.css">
</head>
<body>
<script>
(function () {
  var t = sessionStorage.getItem('sena_tab_id');
  if (!t) {
    t = Math.random().toString(36).slice(2, 12) + Math.random().toString(36).slice(2, 6);
    sessionStorage.setItem('sena_tab_id', t);
  }
  window.__tabId = t;
  document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
  document.addEventListener('submit', function (e) {
    document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
    if (!e.target.querySelector('input[name="_tab"]')) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = '_tab'; inp.value = t;
      e.target.appendChild(inp);
    }
  }, true);
})();
</script>
<div class="auth-shell">
  <div class="auth-brand">
    <div class="brand-content">
      <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
      </div>
      <div class="logo-wrapper">
        <div class="logo pulse">S</div>
      </div>
      <h1 class="mt-4 fade-in">Sistema de Seguimiento de Proyectos Formativos</h1>
      <p class="mt-3 fade-in-delay">Plataforma institucional del SENA para la gestión académica de fichas, instructores y aprendices.</p>
    </div>
    <div class="footer">© 2026 Servicio Nacional de Aprendizaje · Colombia</div>
  </div>
  <div class="auth-form-wrap">
    <form class="auth-form" method="post">
      <div class="form-header">
        <h2>Bienvenido de nuevo</h2>
        <p class="subtitle">Ingresa con tu correo institucional para continuar.</p>
      </div>
      
      <?php if ($loginSuccess): ?>
          <div class="alert-flat success mb-3">
            <i class="bi bi-check-circle"></i>
            <div><?= htmlspecialchars($loginSuccess) ?></div>
          </div>
      <?php endif; ?>

      <?php if ($loginError): ?>
          <div class="alert-flat danger mb-3">
            <i class="bi bi-exclamation-circle"></i>
            <div><?= htmlspecialchars($loginError) ?></div>
          </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Correo institucional</label>
        <input type="email" name="email" class="form-control" placeholder="usuario@sena.edu.co" autocomplete="email" <?= $isBlocked ? 'disabled' : '' ?> required>
      </div>
      <div class="mb-3">
        <label class="form-label d-flex justify-content-between">
          <span>Contraseña</span>
          <a href="recover.php" class="small">Olvidé mi contraseña</a>
        </label>
        <div class="position-relative">
          <input type="password" name="password" id="pw-login" class="form-control pe-5" placeholder="••••••••" autocomplete="current-password" <?= $isBlocked ? 'disabled' : '' ?> required>
          <button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted" data-pw-toggle="#pw-login" style="height:100%" <?= $isBlocked ? 'disabled' : '' ?>><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button class="btn btn-primary w-100" <?= $isBlocked ? 'disabled' : '' ?>>Ingresar</button>
      <div class="text-center mt-3">
        <button type="button" class="icon-btn mx-auto" onclick="toggleTheme()" aria-label="Tema"><i class="bi bi-moon-stars" data-theme-icon></i></button>
      </div>
    </form>
  </div>
</div>
<!-- JS Institucional -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>