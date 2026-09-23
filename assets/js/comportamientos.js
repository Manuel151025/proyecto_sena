/**
 * Comportamientos declarativos de la interfaz.
 *
 * Las vistas llevaban 105 manejadores en línea (onclick, oninput,
 * onsubmit...) y 46 bloques <script> incrustados. Con eso la política de
 * seguridad de contenido (CSP) tenía que admitir 'unsafe-inline', que es
 * justo lo que anula su protección frente a un XSS: cualquier marcado
 * inyectado podía ejecutar código.
 *
 * Aquí se sustituyen por atributos data-* que un único script, cargado
 * desde un archivo, interpreta por delegación:
 *
 *   <form data-confirmar="¿Eliminar la fase X?">          confirma antes de enviar
 *   <button data-confirmar="...">                         ídem, en el clic
 *   <input data-filtro="nombre|persona|codigo|...">       limpia lo tecleado
 *   <select data-autoenvio>                               envía su formulario al cambiar
 *   <button data-modal="#modalEditar"
 *           data-valores='{"id":3,"nombre":"..."}'>       rellena y abre un modal
 *   <button data-accion="imprimir|menu|tema">             acciones de la interfaz
 *   <button data-alerta="texto">                          muestra un aviso
 *   <button data-pulsar="#pdf_estructura">                pulsa otro elemento
 *   <input type="file" data-nombre-en="#etiqueta">        muestra el archivo elegido
 *
 * Los textos llegan en atributos (escapados por PHP) y se usan como texto:
 * nunca se interpretan como HTML ni como código.
 */
(function () {
  'use strict';

  // -------------------------------------------------------------------
  // FILTROS DE ENTRADA
  // Son una ayuda al usuario, no una defensa: el servidor valida igual.
  // -------------------------------------------------------------------
  var FILTROS = {
    // Nombres de entidades: letras, números y puntuación básica.
    nombre:   { quitar: /[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ0-9\s\-_.,()]/g },
    // Nombres de persona: solo letras y espacios.
    persona:  { quitar: /[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]/g },
    // Códigos: letras, números y guion, en mayúsculas.
    codigo:   { quitar: /[^a-zA-Z0-9\-]/g, mayusculas: true },
    'codigo-espacios': { quitar: /[^a-zA-Z0-9\-\s]/g, mayusculas: true },
    'codigo-punto':    { quitar: /[^a-zA-Z0-9.\-_]/g, mayusculas: true },
    // Solo dígitos (documentos, teléfonos, números de ficha).
    digitos:  { quitar: /[^0-9]/g },
    // Texto libre: solo se impide el marcado.
    'sin-html': { quitar: /[<>]/g },
    'nombre-simple': { quitar: /[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s]/g }
  };

  document.addEventListener('input', function (e) {
    var el = e.target;
    if (!el || !el.getAttribute) return;
    var tipo = el.getAttribute('data-filtro');
    if (!tipo || !FILTROS[tipo]) return;
    var f = FILTROS[tipo];
    var antes = el.value;
    var despues = antes.replace(f.quitar, '');
    if (f.mayusculas) despues = despues.toUpperCase();
    if (despues !== antes) {
      var pos = el.selectionStart;
      el.value = despues;
      // Mantener el cursor donde estaba en lugar de mandarlo al final.
      if (typeof pos === 'number' && el.setSelectionRange) {
        var p = Math.max(0, pos - (antes.length - despues.length));
        try { el.setSelectionRange(p, p); } catch (_) { /* tipos sin selección */ }
      }
    }
  });

  // -------------------------------------------------------------------
  // CONFIRMACIONES
  // -------------------------------------------------------------------
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form.getAttribute && form.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-confirmar], a[data-confirmar]');
    if (!btn || btn.form && btn.form.hasAttribute('data-confirmar')) return;
    if (!window.confirm(btn.getAttribute('data-confirmar'))) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  // -------------------------------------------------------------------
  // AUTOENVÍO DE FILTROS
  // -------------------------------------------------------------------
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.hasAttribute && el.hasAttribute('data-autoenvio') && el.form) {
      if (typeof el.form.requestSubmit === 'function') el.form.requestSubmit();
      else el.form.submit();
    }
  });

  // -------------------------------------------------------------------
  // MODALES DE EDICIÓN
  // data-valores es JSON con {nombreDeCampo: valor}. Cada valor se asigna
  // al control con ese name dentro del formulario del modal. Los <select>
  // con data-picker se actualizan solos (searchable-picker intercepta la
  // asignación de .value).
  // -------------------------------------------------------------------
  function rellenar(modal, valores) {
    Object.keys(valores).forEach(function (campo) {
      var controles = modal.querySelectorAll('[name="' + campo.replace(/"/g, '') + '"]');
      controles.forEach(function (c) {
        var v = valores[campo];
        if (c.type === 'checkbox') {
          c.checked = v === true || v === 1 || v === '1';
        } else if (c.type === 'radio') {
          c.checked = String(c.value) === String(v);
        } else {
          c.value = v === null || v === undefined ? '' : String(v);
        }
        c.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    // Textos informativos: <span data-campo="nombre"></span>
    modal.querySelectorAll('[data-campo]').forEach(function (n) {
      var v = valores[n.getAttribute('data-campo')];
      if (v !== undefined) n.textContent = v === null ? '' : String(v);
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-modal]');
    if (!btn) return;
    var modal = document.querySelector(btn.getAttribute('data-modal'));
    if (!modal) return;
    e.preventDefault();
    var bruto = btn.getAttribute('data-valores');
    if (bruto) {
      try { rellenar(modal, JSON.parse(bruto)); } catch (_) { return; }
    }
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  });

  // -------------------------------------------------------------------
  // ACCIONES DE INTERFAZ
  // -------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-accion]');
    if (!el) return;
    var accion = el.getAttribute('data-accion');
    if (accion === 'imprimir') { e.preventDefault(); window.print(); }
    else if (accion === 'menu' && window.toggleSidebar) { e.preventDefault(); window.toggleSidebar(); }
    else if (accion === 'tema' && window.toggleTheme) { e.preventDefault(); window.toggleTheme(); }
    else if (accion === 'volver') { e.preventDefault(); window.history.back(); }
  });

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-alerta]');
    if (!el) return;
    e.preventDefault();
    window.alert(el.getAttribute('data-alerta'));
  });

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-pulsar]');
    if (!el) return;
    var destino = document.querySelector(el.getAttribute('data-pulsar'));
    if (destino) { e.preventDefault(); destino.click(); }
  });

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || el.type !== 'file' || !el.hasAttribute('data-nombre-en')) return;
    var destino = document.querySelector(el.getAttribute('data-nombre-en'));
    if (destino) {
      destino.textContent = el.files && el.files.length ? el.files[0].name : '';
    }
  });

  // -------------------------------------------------------------------
  // LÍMITE DE TAMAÑO DE ARCHIVOS
  // <input type="file" data-max-mb="10">: avisa antes de subir algo que el
  // servidor va a rechazar, en lugar de esperar a que termine la subida.
  // -------------------------------------------------------------------
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || el.type !== 'file' || !el.hasAttribute('data-max-mb')) return;
    var max = parseFloat(el.getAttribute('data-max-mb')) * 1024 * 1024;
    var f = el.files && el.files[0];
    if (f && f.size > max) {
      window.alert('El archivo pesa ' + (f.size / 1048576).toFixed(1) + ' MB y el máximo es '
        + el.getAttribute('data-max-mb') + ' MB.');
      el.value = '';
    }
  });
})();
