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
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/login.css?v=<?= filemtime(__DIR__ . '/assets/css/login.css') ?>">
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