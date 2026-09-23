/**
 * Campana de notificaciones de la barra superior.
 *
 * Antes era un <script> incrustado en navbar.php que reconstruía la lista
 * con plantillas de texto dentro de innerHTML: el título, el mensaje y la
 * URL de cada aviso entraban sin escapar. Cualquier aviso que llevara un
 * nombre escrito por un usuario era un XSS persistente, y una URL
 * `javascript:` se ejecutaba al hacer clic. Ahora cada nodo se crea con
 * createElement y los textos van por textContent, que nunca interpreta
 * marcado; la URL solo se acepta si es una ruta interna de la aplicación.
 */
(function () {
  'use strict';

  var menu = document.getElementById('notifDropdown');
  if (!menu) return;

  var API_URL = menu.getAttribute('data-api');
  var btnTodas = document.getElementById('btnMarcarTodas');
  var lista = document.getElementById('notifListContainer');
  var REFRESCO_MS = 60000;

  var ICONOS = {
    info:    ['bi-info-circle-fill', 'text-primary'],
    success: ['bi-check-circle-fill', 'text-success'],
    warning: ['bi-exclamation-triangle-fill', 'text-warning'],
    danger:  ['bi-exclamation-circle-fill', 'text-danger']
  };

  function tab() {
    return window.__tabId || 'default';
  }

  function cuerpo(params) {
    params._tab = tab();
    params.csrf_token = window.__csrfToken || '';
    return new URLSearchParams(params).toString();
  }

  function post(params) {
    return fetch(API_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: cuerpo(params)
    });
  }

  /** Solo rutas internas: nada de esquemas ni de otros dominios. */
  function urlSegura(url) {
    if (typeof url !== 'string' || url === '') return '#';
    if (!/^\/[A-Za-z0-9_\-]*\/?index\.php\/[A-Za-z0-9\/_\-]*(\?[A-Za-z0-9_\-=&%.]*)?$/.test(url)
        && !/^\/index\.php\//.test(url)) {
      return '#';
    }
    return url;
  }

  function vacio() {
    var div = document.createElement('div');
    div.className = 'text-center text-muted py-4';
    div.id = 'notifEmpty';
    var i = document.createElement('i');
    i.className = 'bi bi-bell-slash fs-2 d-block mb-2';
    div.appendChild(i);
    div.appendChild(document.createTextNode('No tienes notificaciones nuevas'));
    return div;
  }

  function item(n) {
    var icono = ICONOS[n.tipo] || ICONOS.info;
    var a = document.createElement('a');
    a.href = urlSegura(n.url);
    a.className = 'dropdown-item d-flex gap-3 py-2 px-3 border-bottom notif-item';
    a.setAttribute('data-notif-id', String(parseInt(n.id, 10) || 0));

    var col1 = document.createElement('div');
    col1.className = 'flex-shrink-0 mt-1';
    var i = document.createElement('i');
    i.className = 'bi ' + icono[0] + ' ' + icono[1];
    col1.appendChild(i);

    var col2 = document.createElement('div');
    col2.className = 'flex-grow-1 overflow-hidden';
    var t = document.createElement('div');
    t.className = 'fw-semibold small';
    t.textContent = String(n.titulo || '');
    var m = document.createElement('div');
    m.className = 'text-muted small text-truncate';
    var msg = String(n.mensaje || '');
    m.textContent = msg.length > 80 ? msg.substring(0, 80) + '…' : msg;
    var h = document.createElement('div');
    h.className = 'text-muted notif-tiempo';
    h.textContent = String(n.tiempo_relativo || '');
    col2.appendChild(t);
    col2.appendChild(m);
    col2.appendChild(h);

    a.appendChild(col1);
    a.appendChild(col2);
    return a;
  }

  function pintarContador(total) {
    var btn = document.getElementById('btnNotificaciones');
    var b = document.getElementById('notifBadge');
    if (total > 0) {
      if (!b) {
        b = document.createElement('span');
        b.className = 'dot';
        b.id = 'notifBadge';
        if (btn) btn.appendChild(b);
      }
      b.textContent = total > 99 ? '99+' : String(total);
      if (btnTodas) btnTodas.hidden = false;
    } else {
      if (b) b.remove();
      if (btnTodas) btnTodas.hidden = true;
    }
  }

  function pintarLista(items) {
    lista.replaceChildren();
    if (!items.length) {
      lista.appendChild(vacio());
      return;
    }
    items.forEach(function (n) { lista.appendChild(item(n)); });
  }

  function restarUno() {
    var b = document.getElementById('notifBadge');
    var actual = b ? parseInt(b.textContent, 10) || 0 : 0;
    pintarContador(Math.max(0, actual - 1));
    if (!lista.querySelector('.notif-item')) {
      pintarLista([]);
    }
  }

  // Clic en un aviso: se marca como leído y se quita de la lista.
  menu.addEventListener('click', function (e) {
    var it = e.target.closest('.notif-item');
    if (!it) return;
    var id = it.getAttribute('data-notif-id');
    if (!id) return;
    post({ action: 'marcar_leida', id: id }).catch(function () {});
    it.remove();
    restarUno();
  });

  if (btnTodas) {
    btnTodas.addEventListener('click', function () {
      post({ action: 'marcar_todas' }).then(function (r) {
        if (!r.ok) return;
        pintarLista([]);
        pintarContador(0);
      }).catch(function () {});
    });
  }

  function refrescar() {
    // Sin pestaña visible no se consulta: con varias pestañas abiertas
    // todo el día, cada una sondeaba el servidor cada minuto.
    if (document.hidden) return;
    fetch(API_URL + '?_tab=' + encodeURIComponent(tab()), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.ok || !Array.isArray(data.notificaciones)) return;
        pintarContador(parseInt(data.count, 10) || 0);
        pintarLista(data.notificaciones);
      })
      .catch(function () {});
  }

  setInterval(refrescar, REFRESCO_MS);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) refrescar();
  });
})();
