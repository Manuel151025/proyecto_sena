/**
 * Filtro en el cliente para listados CORTOS que no se paginan (programas,
 * aprendices de una ficha). En un listado paginado la búsqueda tiene que
 * ir al servidor: aquí solo alcanzaría a la página visible.
 *
 *   <div data-filtro-tarjetas="#contenedor">
 *     <input data-filtro-texto>
 *     <select data-filtro-estado>
 *   </div>
 *   <div id="contenedor">
 *     <div data-texto="nombre en minúsculas" data-estado="activo">…</div>
 *     <div data-sin-resultados hidden>…</div>
 *   </div>
 */
(function () {
  'use strict';

  function normalizar(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  document.querySelectorAll('[data-filtro-tarjetas]').forEach(function (barra) {
    var cont = document.querySelector(barra.getAttribute('data-filtro-tarjetas'));
    if (!cont) return;
    var texto = barra.querySelector('[data-filtro-texto]');
    var estado = barra.querySelector('[data-filtro-estado]');
    var vacio = cont.querySelector('[data-sin-resultados]');
    var items = Array.prototype.slice.call(cont.querySelectorAll('[data-texto]'));

    function aplicar() {
      var q = normalizar(texto ? texto.value : '');
      var e = estado ? estado.value : '';
      var visibles = 0;
      items.forEach(function (it) {
        var ok = (!q || normalizar(it.getAttribute('data-texto')).indexOf(q) !== -1)
              && (!e || it.getAttribute('data-estado') === e);
        it.hidden = !ok;
        if (ok) visibles++;
      });
      if (vacio && items.length) vacio.hidden = visibles > 0;
    }

    if (texto) texto.addEventListener('input', aplicar);
    if (estado) estado.addEventListener('change', aplicar);
  });
})();
