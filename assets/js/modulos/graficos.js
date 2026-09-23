/**
 * Gráficos declarativos con Chart.js.
 *
 *   <div class="grafico"><canvas data-grafico='{"tipo":"doughnut","etiquetas":[...],
 *        "series":[{"nombre":"A","datos":[...],"color":"#22c55e"}], "apilado":false,
 *        "horizontal":false, "sufijo":"%"}' aria-label="..."></canvas></div>
 *
 * Los datos los escribe el servidor (datosJson) en el atributo: la página no
 * lleva scripts en línea y el mismo código sirve a los tres paneles. Los
 * colores de ejes y leyendas salen de las variables CSS, así que funcionan
 * con el tema claro y el oscuro.
 */
(function () {
  'use strict';
  if (!window.Chart) return;

  var PALETA = ['#39A900', '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];
  var graficos = [];

  function variable(nombre, respaldo) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
    return v || respaldo;
  }

  function configuracion(d) {
    var circular = d.tipo === 'doughnut' || d.tipo === 'pie';
    var texto = variable('--text-muted', '#64748b');
    var rejilla = variable('--border', 'rgba(100,116,139,.2)');
    var sufijo = d.sufijo || '';
    var datasets = (d.series || []).map(function (s, i) {
      var color = s.color || PALETA[i % PALETA.length];
      return {
        label: s.nombre,
        data: s.datos,
        backgroundColor: circular ? (s.colores || PALETA) : (d.tipo === 'line' ? color + '33' : color),
        borderColor: circular ? variable('--surface', '#fff') : color,
        borderWidth: circular ? 2 : (d.tipo === 'line' ? 2 : 0),
        borderRadius: d.tipo === 'bar' ? 4 : 0,
        fill: d.tipo === 'line',
        tension: 0.3,
        pointRadius: d.tipo === 'line' ? 2 : 0
      };
    });
    var opciones = {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 400 },
      plugins: {
        legend: { display: circular || datasets.length > 1, position: 'bottom', labels: { color: texto, boxWidth: 12, usePointStyle: true } },
        tooltip: { callbacks: { label: function (c) {
          var v = circular ? c.parsed : (d.horizontal ? c.parsed.x : c.parsed.y);
          return ' ' + (c.dataset.label ? c.dataset.label + ': ' : (circular ? c.label + ': ' : '')) + v + sufijo;
        } } }
      }
    };
    if (circular) {
      opciones.cutout = d.tipo === 'doughnut' ? '65%' : 0;
    } else {
      var eje = { ticks: { color: texto }, grid: { color: rejilla }, stacked: !!d.apilado, beginAtZero: true };
      var ejeValor = Object.assign({}, eje, { ticks: { color: texto, callback: function (v) { return v + sufijo; } } });
      if (d.maximo) ejeValor.max = d.maximo;
      opciones.indexAxis = d.horizontal ? 'y' : 'x';
      opciones.scales = d.horizontal
        ? { x: ejeValor, y: Object.assign({}, eje, { grid: { display: false } }) }
        : { x: Object.assign({}, eje, { grid: { display: false } }), y: ejeValor };
    }
    return { type: d.tipo === 'pie' ? 'pie' : d.tipo, data: { labels: d.etiquetas || [], datasets: datasets }, options: opciones };
  }

  function dibujar() {
    graficos.forEach(function (g) { g.destroy(); });
    graficos = [];
    document.querySelectorAll('canvas[data-grafico]').forEach(function (canvas) {
      var d;
      try { d = JSON.parse(canvas.getAttribute('data-grafico')); } catch (_) { return; }
      var vacio = !(d.series || []).some(function (s) { return (s.datos || []).some(function (v) { return Number(v) > 0; }); });
      var aviso = canvas.parentElement.querySelector('.grafico-vacio');
      if (vacio) {
        canvas.hidden = true;
        if (aviso) aviso.hidden = false;
        return;
      }
      graficos.push(new window.Chart(canvas, configuracion(d)));
    });
  }

  dibujar();
  // Al cambiar de tema se redibujan con los colores nuevos.
  new MutationObserver(dibujar).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'data-bs-theme', 'class'] });
})();
