/*
 * Tema aplicado antes de pintar: se carga en el <head> (síncrono) para que
 * el modo oscuro no llegue tarde y la página no parpadee en blanco.
 * data-bs-theme acompaña a data-theme para que Bootstrap también se oscurezca.
 */
(function () {
  try {
    if (localStorage.getItem('sena-theme') === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
      document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
  } catch (_) { /* almacenamiento bloqueado: tema claro */ }
})();
