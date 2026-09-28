<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es" data-app-url="<?= e(APP_URL) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'SENA') ?></title>
    <meta name="csrf-token" content="<?= e(getCsrfToken()) ?>">
    <!-- Tema antes de pintar (síncrono): evita el parpadeo del modo oscuro. -->
    <script src="<?= APP_URL ?>/assets/js/tema-inicial.js?v=<?= filemtime(BASE_PATH . 'assets/js/tema-inicial.js') ?>"></script>
    <!-- Los recursos de CDN llevan integrity (SRI): si jsdelivr sirviera un
         archivo distinto al esperado, el navegador lo descarta en vez de
         ejecutarlo. Sin esto, un compromiso del CDN se traducia en control
         total sobre la sesion de cualquiera que abriera la aplicacion.
         Al cambiar de version hay que recalcular el hash. -->
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Theme CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/theme.css?v=<?= filemtime(BASE_PATH . 'assets/css/theme.css') ?>">
    <!-- Searchable picker -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/picker.css?v=<?= filemtime(BASE_PATH . 'assets/css/picker.css') ?>">
    
    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#39A900">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/sena_logo.png">
</head>
<body>
<!-- Sesión por pestaña y CSRF antes de cualquier formulario. -->
<script src="<?= APP_URL ?>/assets/js/pestana.js?v=<?= filemtime(BASE_PATH . 'assets/js/pestana.js') ?>"></script>
