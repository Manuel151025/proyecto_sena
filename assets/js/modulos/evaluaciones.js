/**
 * Juicios de evaluación:
 *  - el motivo se pide solo al cambiar un juicio ya emitido (A o D);
 *  - el detalle pinta el historial de cambios de la fila elegida.
 * El servidor valida lo mismo (EvaluacionService exige el motivo).
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-form-juicio]');
  if (form) {
    var anterior = form.querySelector('[data-anterior]');
    var bloque = form.querySelector('[data-bloque-motivo]');
    var motivo = bloque ? bloque.querySelector('input') : null;

    var actualizar = function () {
      var elegido = form.querySelector('input[name="concepto"]:checked');
      var previo = anterior ? anterior.value : '';
      var pide = !!elegido && (previo === 'A' || previo === 'D') && elegido.value !== previo;
      if (bloque) bloque.hidden = !pide;
      if (motivo) {
        motivo.required = pide;
        if (!pide) motivo.value = '';
      }
    };
    form.addEventListener('change', function (e) {
      if (e.target && (e.target.name === 'concepto' || e.target.name === '_anterior')) actualizar();
    });
  }

  var ETIQUETA = { A: 'A', D: 'D', pendiente: 'Pendiente' };
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-historial]');
    if (!btn) return;
    var lista = document.querySelector('#modalDetalle [data-lista-historial]');
    if (!lista) return;
    var items = [];
    try { items = JSON.parse(btn.getAttribute('data-historial') || '[]'); } catch (_) { items = []; }
    lista.replaceChildren();
    if (!items.length) {
      var vacio = document.createElement('li');
      vacio.className = 'text-muted';
      vacio.textContent = 'Sin cambios registrados.';
      lista.appendChild(vacio);
      return;
    }
    items.forEach(function (h) {
      var li = document.createElement('li');
      li.className = 'border-start border-2 ps-2 mb-2';
      var cab = document.createElement('div');
      cab.className = 'fw-semibold';
      cab.textContent = (ETIQUETA[h.de] || h.de) + ' → ' + (ETIQUETA[h.a] || h.a) + ' · ' + h.fecha;
      var det = document.createElement('div');
      det.className = 'text-muted';
      det.textContent = h.quien + (h.motivo ? ' — ' + h.motivo : '');
      li.appendChild(cab);
      li.appendChild(det);
      lista.appendChild(li);
    });
  });
})();
