<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'SENA') ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Theme CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/theme.css">
    <!-- Searchable picker -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/picker.css">
    
    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#39A900">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/sena_logo.png">
</head>
<body>
<script>
(function () {
  // Recuperar o generar el ID de pestaña persistido en sessionStorage
  // (sessionStorage es exclusivo de cada pestaña, a diferencia de localStorage)
  var t = sessionStorage.getItem('sena_tab_id');
  if (!t) {
    t = Math.random().toString(36).slice(2, 12) + Math.random().toString(36).slice(2, 6);
    sessionStorage.setItem('sena_tab_id', t);
  }
  window.__tabId = t;

  // Establecer la cookie inmediatamente (cubre recargas y navegaciones directas)
  document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';

  // JIT antes de cualquier clic en enlace
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (a) document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
  }, true);

  // JIT antes de cualquier envío de formulario + inyectar _tab como hidden
  document.addEventListener('submit', function (e) {
    document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
    if (!e.target.querySelector('input[name="_tab"]')) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = '_tab'; inp.value = t;
      e.target.appendChild(inp);
    }
  }, true);

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
