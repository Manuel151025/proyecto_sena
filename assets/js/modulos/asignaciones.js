/**
 * Asignar instructor: la competencia se filtra por el programa de la ficha
 * elegida. El servidor lo vuelve a comprobar (AsignacionesService).
 */
(function () {
  'use strict';
  var nodo = document.getElementById('datosAsignaciones');
  var form = document.querySelector('form[data-form-asignacion]');
  if (!nodo || !form) return;
  var datos;
  try { datos = JSON.parse(nodo.textContent || '{}'); } catch (_) { return; }

  var fichas = {};
  (datos.fichas || []).forEach(function (f) { fichas[f.id] = f; });
  var selFicha = form.querySelector('[data-rol="ficha"]');
  var selComp = form.querySelector('[data-rol="competencia"]');

  function opcion(v, t) {
    var o = document.createElement('option');
    o.value = String(v);
    o.textContent = t;
    o.setAttribute('data-search', t);
    return o;
  }

  selFicha.addEventListener('change', function () {
    var f = fichas[parseInt(selFicha.value, 10)];
    selComp.replaceChildren(opcion('', f ? 'Seleccione la competencia…' : 'Elija primero la ficha'));
    if (!f) return;
    (datos.competencias || []).filter(function (c) { return c.programa === f.programa; })
      .forEach(function (c) { selComp.appendChild(opcion(c.id, c.texto)); });
    selComp.value = '';
  });
})();
