<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - SENA</title>
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
      
      <?php if ($step === 1 || !in_array($step, [1, 2, 3])): ?>
      <h2>Restablecer contraseña</h2>
      <p class="subtitle">Ingresa el correo institucional asociado a tu cuenta.</p>
      <div class="mb-3">
        <label class="form-label">Correo institucional</label>
        <input type="email" class="form-control" placeholder="usuario@sena.edu.co" required>
      </div>
      <a href="recover.php?step=2" class="btn btn-primary w-100">Enviar enlace</a>
      <?php endif; ?>

      <?php if ($step === 2): ?>
      <div class="text-center py-3">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--sena-primary-50);color:var(--sena-primary-600);font-size:1.6rem;display:grid;place-items:center;margin:0 auto 1rem">
          <i class="bi bi-envelope-check"></i>
        </div>
        <h2>Revisa tu correo</h2>
        <p class="subtitle">Si el correo existe en el sistema, recibirás un enlace para restablecer tu contraseña en los próximos minutos.</p>
        <a href="login.php" class="btn btn-soft w-100 mt-2">Volver al inicio</a>
      </div>
      <?php endif; ?>

      <?php if ($step === 3): ?>
      <h2>Crear nueva contraseña</h2>
      <p class="subtitle">Define una contraseña segura para tu cuenta.</p>
      <div class="mb-3">
        <label class="form-label">Nueva contraseña</label>
        <div class="position-relative">
          <input type="password" id="pw-new" class="form-control pe-5" data-pw-strength placeholder="••••••••" required>
          <button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted" data-pw-toggle="#pw-new" style="height:100%"><i class="bi bi-eye"></i></button>
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
        <input type="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button class="btn btn-primary w-100">Guardar contraseña</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- JS Institucional -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
