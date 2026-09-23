/**
 * Zonas de "arrastra aquí tu archivo".
 *
 *   <div class="upload-dropzone" data-zona-archivo="#input" data-pulsar="#input">
 *     <input type="file" id="input" data-nombre-en="#etiqueta">
 *
 * El clic lo resuelve comportamientos.js (data-pulsar); aquí solo se añade
 * soltar el archivo y el teclado (Enter/Espacio), para que la zona se pueda
 * usar sin ratón.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-zona-archivo]').forEach(function (zona) {
    var input = document.querySelector(zona.getAttribute('data-zona-archivo'));
    if (!input) return;

    ['dragenter', 'dragover'].forEach(function (ev) {
      zona.addEventListener(ev, function (e) {
        e.preventDefault();
        zona.classList.add('arrastrando');
      });
    });
    ['dragleave', 'dragend'].forEach(function (ev) {
      zona.addEventListener(ev, function () { zona.classList.remove('arrastrando'); });
    });
    zona.addEventListener('drop', function (e) {
      e.preventDefault();
      zona.classList.remove('arrastrando');
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        // Asignar .files por código no dispara 'change'.
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    zona.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        input.click();
      }
    });
    input.addEventListener('change', function () {
      zona.classList.toggle('con-archivo', !!(input.files && input.files.length));
    });
  });
})();
