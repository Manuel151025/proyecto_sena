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
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/theme.css?v=<?= filemtime(BASE_PATH . 'assets/css/theme.css') ?>">
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
  window.__csrfToken = '<?= getCsrfToken() ?>';

  // Establecer la cookie inmediatamente (cubre recargas y navegaciones directas)
  document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';

  // Migración de cookies de biometría a localStorage con registro nativo de WebAuthn
  var cookies = document.cookie.split(';');
  var bioEmail = '', bioToken = '';
  for (var i = 0; i < cookies.length; i++) {
    var c = cookies[i].trim();
    if (c.indexOf('sena_bio_email=') === 0) {
      bioEmail = decodeURIComponent(c.substring('sena_bio_email='.length));
    }
    if (c.indexOf('sena_bio_token=') === 0) {
      bioToken = decodeURIComponent(c.substring('sena_bio_token='.length));
    }
  }
  
  function clearBioCookies() {
    document.cookie = 'sena_bio_email=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
    document.cookie = 'sena_bio_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
  }

  function registerBiometricCredential(email, token, callback) {
    var challenge = new Uint8Array(32);
    window.crypto.getRandomValues(challenge);
    var rpId = window.location.hostname;
    
    var options = {
      publicKey: {
        challenge: challenge,
        rp: {
          name: "SENA Seguimiento",
          id: rpId
        },
        user: {
          id: Uint8Array.from(email, function(c) { return c.charCodeAt(0); }),
          name: email,
          displayName: email
        },
        pubKeyCredParams: [{type: "public-key", alg: -7}], // ES256
        authenticatorSelection: {
          authenticatorAttachment: "platform",
          userVerification: "required"
        },
        timeout: 60000,
        attestation: "none"
      }
    };

    navigator.credentials.create(options)
      .then(function(credential) {
        var rawId = new Uint8Array(credential.rawId);
        var binary = '';
        for (var i = 0; i < rawId.byteLength; i++) {
          binary += String.fromCharCode(rawId[i]);
        }
        var base64Id = btoa(binary);
        
        localStorage.setItem('sena_bio_cred_id', base64Id);
        localStorage.setItem('sena_bio_email', email);
        localStorage.setItem('sena_bio_token', token);

        if (navigator.vibrate) navigator.vibrate([80, 50, 80]);
        alert("¡Huella digital vinculada con éxito en este dispositivo!");
        callback();
      })
      .catch(function(err) {
        console.error("Error al registrar biometría:", err);
        alert("No se pudo registrar la huella digital: " + err.message);
        callback();
      });
  }

  function showBiometricRegisterPrompt(email, token) {
    var style = document.createElement('style');
    style.textContent = `
      .bio-prompt-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
      }
      .bio-prompt-card {
        width: 100%;
        max-width: 480px;
        background: #0e1611;
        border-top: 3px solid #39A900;
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        padding: 30px 24px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.5);
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-align: center;
      }
      .bio-prompt-overlay.active {
        opacity: 1;
      }
      .bio-prompt-overlay.active .bio-prompt-card {
        transform: translateY(0);
      }
      .bio-prompt-icon {
        width: 60px;
        height: 60px;
        background: rgba(57, 169, 0, 0.1);
        color: #34d399;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px auto;
        font-size: 2.2rem;
      }
      .bio-prompt-title {
        color: #ffffff;
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 10px;
        font-family: 'Inter', sans-serif;
      }
      .bio-prompt-desc {
        color: #a7b5ae;
        font-size: 0.88rem;
        line-height: 1.5;
        margin-bottom: 24px;
        font-family: 'Inter', sans-serif;
      }
      .bio-prompt-buttons {
        display: flex;
        gap: 12px;
      }
      .bio-btn {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-family: 'Inter', sans-serif;
      }
      .bio-btn-primary {
        background: #39A900;
        color: white;
      }
      .bio-btn-primary:hover {
        background: #34d399;
        box-shadow: 0 4px 15px rgba(52, 211, 153, 0.3);
      }
      .bio-btn-secondary {
        background: rgba(255,255,255,0.05);
        color: #a7b5ae;
        border: 1px solid rgba(255,255,255,0.1);
      }
      .bio-btn-secondary:hover {
        background: rgba(255,255,255,0.1);
        color: white;
      }
    `;
    document.head.appendChild(style);

    var overlay = document.createElement('div');
    overlay.className = 'bio-prompt-overlay';
    
    var card = document.createElement('div');
    card.className = 'bio-prompt-card';
    
    card.innerHTML = `
      <div class="bio-prompt-icon"><i class="bi bi-fingerprint"></i></div>
      <div class="bio-prompt-title">¿Activar inicio con huella?</div>
      <div class="bio-prompt-desc">Puedes usar el lector de huellas o reconocimiento facial de tu dispositivo para ingresar de forma rápida y segura la próxima vez.</div>
      <div class="bio-prompt-buttons">
        <button type="button" class="bio-btn bio-btn-secondary" id="bio-opt-out">Más tarde</button>
        <button type="button" class="bio-btn bio-btn-primary" id="bio-opt-in">Activar huella</button>
      </div>
    `;
    
    overlay.appendChild(card);
    document.body.appendChild(overlay);

    setTimeout(function() {
      overlay.classList.add('active');
    }, 50);

    function closePrompt() {
      overlay.classList.remove('active');
      setTimeout(function() {
        overlay.remove();
        style.remove();
      }, 300);
      clearBioCookies();
    }

    document.getElementById('bio-opt-out').addEventListener('click', function() {
      localStorage.setItem('sena_bio_dismissed', '1');
      closePrompt();
    });
    document.getElementById('bio-opt-in').addEventListener('click', function() {
      registerBiometricCredential(email, token, closePrompt);
    });
  }

  if (bioEmail && bioToken) {
    var alreadyLinked = localStorage.getItem('sena_bio_cred_id');
    var isDismissed = localStorage.getItem('sena_bio_dismissed');

    if (alreadyLinked || isDismissed) {
      clearBioCookies();
    } else if (window.PublicKeyCredential) {
      PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
        .then(function(available) {
          if (available) {
            showBiometricRegisterPrompt(bioEmail, bioToken);
          } else {
            clearBioCookies();
          }
        })
        .catch(function() {
          clearBioCookies();
        });
    } else {
      clearBioCookies();
    }
  }

  // JIT antes de cualquier clic en enlace
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (a) document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
  }, true);

  // JIT antes de cualquier envío de formulario + inyectar _tab y csrf_token como hidden
  document.addEventListener('submit', function (e) {
    document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax';
    if (!e.target.querySelector('input[name="_tab"]')) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = '_tab'; inp.value = t;
      e.target.appendChild(inp);
    }
    if (e.target.method && e.target.method.toUpperCase() === 'POST' && !e.target.querySelector('input[name="csrf_token"]')) {
      var csrfInp = document.createElement('input');
      csrfInp.type = 'hidden'; csrfInp.name = 'csrf_token'; csrfInp.value = window.__csrfToken || '';
      e.target.appendChild(csrfInp);
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
