/**
 * Formularios de actividades: al elegir la ficha, la fase solo ofrece las
 * del proyecto de esa ficha y la competencia solo las de su programa.
 *
 * Es ayuda al usuario: el servidor vuelve a comprobar las dos cosas
 * (ActividadesService::validarContexto) porque el formulario se puede
 * enviar a mano con cualquier combinación.
 */
(function () {
  'use strict';

  var nodo = document.getElementById('datosActividades');
  if (!nodo) return;
  var datos;
  try { datos = JSON.parse(nodo.textContent || '{}'); } catch (_) { return; }

  var fichas = {};
  (datos.fichas || []).forEach(function (f) { fichas[f.id] = f; });

  function opcion(valor, texto) {
    var o = document.createElement('option');
    o.value = String(valor);
    o.textContent = texto;
    return o;
  }

  function rellenar(select, items, vacio) {
    var previo = select.value;
    select.replaceChildren(opcion('', vacio));
    items.forEach(function (it) {
      var o = opcion(it.id, it.texto);
      o.setAttribute('data-search', it.texto);
      select.appendChild(o);
    });
    // Conserva la elección si sigue siendo válida para la nueva ficha.
    if (items.some(function (it) { return String(it.id) === previo; })) {
      select.value = previo;
    } else {
      select.value = '';
    }
  }

  function alCambiarFicha(form) {
    var ficha = fichas[parseInt(form.querySelector('[data-rol="ficha"]').value, 10)];
    var selFase = form.querySelector('[data-rol="fase"]');
    var selComp = form.querySelector('[data-rol="competencia"]');
    if (!ficha) {
      rellenar(selFase, [], 'Elija primero la ficha');
      rellenar(selComp, [], 'Sin competencia específica');
      return;
    }
    var fases = (datos.fases || []).filter(function (f) { return f.proyecto === ficha.proyecto; });
    rellenar(selFase, fases, ficha.proyecto ? 'Seleccione la fase…' : 'La ficha no tiene proyecto');
    // Con proyecto, la fase es obligatoria (RF02).
    selFase.required = !!ficha.proyecto;
    var comps = (datos.competencias || []).filter(function (c) { return c.programa === ficha.programa; });
    rellenar(selComp, comps, 'Sin competencia específica');
  }

  document.querySelectorAll('form[data-form-actividad]').forEach(function (form) {
    var selFicha = form.querySelector('[data-rol="ficha"]');
    if (!selFicha) return;
    selFicha.addEventListener('change', function () { alCambiarFicha(form); });
    // Estado inicial (la ficha puede venir preseleccionada por el filtro).
    alCambiarFicha(form);
  });
})();
