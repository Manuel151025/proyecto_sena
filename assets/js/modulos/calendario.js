/**
 * Calendario (FullCalendar). Los datos llegan de /calendario/api y el
 * detalle de un evento se pinta con textContent: ningún texto del servidor
 * se interpreta como HTML.
 */
(function () {
  'use strict';
  var el = document.getElementById('sena-calendar');
  if (!el || !window.FullCalendar) return;

  var BP = 768;
  var angosto = function () { return window.innerWidth < BP; };
  var movil = angosto();
  var botones = Array.prototype.slice.call(document.querySelectorAll('.cal-view-btn'));
  var modalEl = document.getElementById('modalDetalleEvento');
  var ETIQUETAS = { ficha: 'Ficha', programa: 'Programa', proyecto: 'Proyecto', estado: 'Estado', avance: 'Avance', ra: 'RAP',
    aprendiz: 'Aprendiz', descripcion: 'Descripción', creador: 'Creado por' };

  function campo(nombre) { return modalEl ? modalEl.querySelector('[data-evento="' + nombre + '"]') : null; }

  function mostrar(evento) {
    if (!modalEl || !window.bootstrap) return;
    var ext = evento.extendedProps || {};
    campo('color').style.background = evento.backgroundColor || '#39A900';
    campo('titulo').textContent = evento.title;
    campo('tipo').textContent = ext.tipo || 'Evento';
    var datos = campo('datos');
    datos.replaceChildren();
    var filas = Object.keys(ETIQUETAS).filter(function (k) { return ext[k] !== undefined && ext[k] !== null && ext[k] !== ''; })
      .map(function (k) { return [ETIQUETAS[k], String(ext[k])]; });
    filas.push(['Fecha', evento.start ? evento.start.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '']);
    filas.forEach(function (f) {
      var dt = document.createElement('dt'); dt.className = 'col-4 text-muted'; dt.textContent = f[0];
      var dd = document.createElement('dd'); dd.className = 'col-8 text-break'; dd.textContent = f[1];
      datos.appendChild(dt); datos.appendChild(dd);
    });
    var enlace = campo('enlace');
    enlace.hidden = !evento.url;
    if (evento.url) enlace.href = evento.url;
    var borrar = campo('eliminar');
    if (borrar) {
      borrar.hidden = !ext.puedeEliminar;
      borrar.querySelector('[name="evento_id"]').value = ext.eventoId || '';
    }
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  var calendario = new window.FullCalendar.Calendar(el, {
    locale: 'es',
    initialView: movil ? 'listWeek' : 'dayGridMonth',
    height: movil ? 'auto' : 650,
    headerToolbar: { left: 'prev,next today', center: 'title', right: movil ? '' : 'dayGridMonth,dayGridWeek,dayGridDay,listWeek' },
    buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día', list: 'Agenda' },
    events: { url: el.getAttribute('data-api'), method: 'GET' },
    loading: function (cargando) { el.style.opacity = cargando ? '.5' : '1'; },
    eventClick: function (info) { info.jsEvent.preventDefault(); mostrar(info.event); },
    eventDidMount: function (info) { info.el.title = info.event.title; },
    noEventsContent: 'Sin eventos en este período',
    dayMaxEvents: movil ? 2 : 3
  });
  calendario.render();

  function marcarVista() {
    botones.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-cal-view') === calendario.view.type); });
  }
  botones.forEach(function (b) {
    b.addEventListener('click', function () { calendario.changeView(b.getAttribute('data-cal-view')); marcarVista(); });
  });
  marcarVista();

  // Deslizar a los lados cambia de período en pantallas táctiles.
  var x0 = 0, y0 = 0;
  el.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
  el.addEventListener('touchend', function (e) {
    var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) { if (dx < 0) calendario.next(); else calendario.prev(); }
  }, { passive: true });

  // Al cruzar el punto de corte se reconfigura (vista, altura y botones).
  var espera;
  function ajustar() {
    var a = angosto();
    if (a === movil) { calendario.updateSize(); return; }
    movil = a;
    calendario.setOption('height', a ? 'auto' : 650);
    calendario.setOption('dayMaxEvents', a ? 2 : 3);
    calendario.setOption('headerToolbar', { left: 'prev,next today', center: 'title', right: a ? '' : 'dayGridMonth,dayGridWeek,dayGridDay,listWeek' });
    if (a && calendario.view.type === 'dayGridMonth') calendario.changeView('listWeek');
    else if (!a && calendario.view.type === 'listWeek') calendario.changeView('dayGridMonth');
    marcarVista();
    calendario.updateSize();
  }
  window.addEventListener('resize', function () { clearTimeout(espera); espera = setTimeout(ajustar, 150); });
  window.addEventListener('orientationchange', function () { setTimeout(ajustar, 200); });
})();
