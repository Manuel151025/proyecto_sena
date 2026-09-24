/*
 * Sesión por pestaña y token CSRF.
 *
 * Cada pestaña guarda su identificador en sessionStorage y lo envía en la
 * cookie `sena_tab` y en el campo `_tab` de cada formulario: así dos
 * pestañas pueden tener sesiones de usuarios distintos. El token CSRF se lee
 * de <meta name="csrf-token"> y se añade a los formularios POST que no lo
 * traigan. Se carga al principio del <body>, antes de cualquier formulario.
 *
 * Antes era un <script> en línea en la cabecera; como archivo, la política
 * de seguridad de contenido ya no necesita admitir código en línea.
 */
(function () {
  'use strict';
  var t = null;
  try { t = sessionStorage.getItem('sena_tab_id'); } catch (_) { /* sin almacenamiento */ }
  if (!t) {
    t = Math.random().toString(36).slice(2, 12) + Math.random().toString(36).slice(2, 6);
    try { sessionStorage.setItem('sena_tab_id', t); } catch (_) { /* sin almacenamiento */ }
  }
  var meta = document.querySelector('meta[name="csrf-token"]');
  window.__tabId = t;
  window.__csrfToken = meta ? meta.getAttribute('content') : '';

  var cookie = function () { document.cookie = 'sena_tab=' + t + '; path=/; SameSite=Lax'; };
  cookie();

  // Justo antes de navegar o enviar, por si otra pestaña cambió la cookie.
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('a[href]')) cookie();
  }, true);
  document.addEventListener('submit', function (e) {
    cookie();
    var f = e.target;
    if (!f.querySelector('input[name="_tab"]')) {
      var tab = document.createElement('input');
      tab.type = 'hidden'; tab.name = '_tab'; tab.value = t;
      f.appendChild(tab);
    }
    if ((f.method || '').toUpperCase() === 'POST' && window.__csrfToken && !f.querySelector('input[name="csrf_token"]')) {
      var csrf = document.createElement('input');
      csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = window.__csrfToken;
      f.appendChild(csrf);
    }
  }, true);

  // Service worker de la PWA (la URL base va en <html data-app-url>).
  var base = document.documentElement.getAttribute('data-app-url') || '';
  if ('serviceWorker' in navigator && base !== '') {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(base + '/sw.js').catch(function () { /* sin PWA */ });
    });
  }
})();
