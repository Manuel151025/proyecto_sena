<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

// Cualquier rol autenticado puede acceder
requireAuth();

$pageTitle  = 'Calendario · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$rol   = getCurrentRole();
$user  = getCurrentUser();

$rolLabels = [
    ROL_COORDINADOR => 'Coordinador',
    ROL_INSTRUCTOR  => 'Instructor',
    ROL_APRENDIZ    => 'Aprendiz',
];

$roleColors = [
    ROL_COORDINADOR => '#39A900',
    ROL_INSTRUCTOR  => '#3B82F6',
    ROL_APRENDIZ    => '#8B5CF6',
];

$apiUrl = APP_URL . '/modules/calendario/api_events.php';
?>

<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<style>
/* ── Contenedor general ── */
.cal-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── Encabezado de página ── */
.cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
}
.cal-header h1 {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}
.cal-header .role-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .8rem;
    border-radius: 999px;
    font-size: .8rem;
    font-weight: 600;
    color: #fff;
    background: <?= $roleColors[$rol] ?? '#39A900' ?>;
}

/* ── Leyenda de colores ── */
.cal-legend {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
}
.cal-legend-item {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .78rem;
    font-weight: 500;
    color: var(--text-muted);
}
.cal-legend-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Tarjeta del calendario ── */
.cal-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: 1.25rem;
}

/* ── Override FullCalendar para el tema del sistema ── */
.fc {
    font-family: var(--font-sans);
    --fc-border-color: var(--border);
    --fc-today-bg-color: rgba(57,169,0,.07);
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: var(--surface-2);
    --fc-list-event-hover-bg-color: var(--surface-2);
    --fc-button-bg-color: var(--surface-2);
    --fc-button-border-color: var(--border);
    --fc-button-hover-bg-color: var(--border);
    --fc-button-active-bg-color: var(--sena-primary);
    --fc-button-active-border-color: var(--sena-primary);
    --fc-button-text-color: var(--text);
}
.fc .fc-toolbar-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text);
}
.fc .fc-button {
    border-radius: 8px !important;
    font-size: .8rem;
    font-weight: 500;
    padding: .35rem .75rem;
    text-transform: capitalize;
}
.fc .fc-button:focus { box-shadow: 0 0 0 3px rgba(57,169,0,.2); }
.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active {
    background: var(--sena-primary) !important;
    border-color: var(--sena-primary) !important;
    color: #fff !important;
}
.fc .fc-col-header-cell-cushion,
.fc .fc-daygrid-day-number {
    color: var(--text);
    text-decoration: none;
    font-weight: 500;
    font-size: .82rem;
}
.fc .fc-event {
    border-radius: 6px !important;
    border: none !important;
    padding: 2px 5px;
    font-size: .75rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s, transform .15s;
}
.fc .fc-event:hover {
    opacity: .9;
    transform: translateY(-1px);
}
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
    background: var(--sena-primary);
    color: #fff;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
}
.fc .fc-list-event-title a { color: var(--text) !important; text-decoration: none; }
.fc .fc-list-empty { color: var(--text-muted); }
.fc .fc-list-day-cushion { background: var(--surface-2) !important; }

/* ── Modal de detalle de evento ── */
#cal-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
#cal-modal-overlay.open { display: flex; }
#cal-modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.5rem;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,.15);
    animation: modalPop .2s cubic-bezier(.34,1.56,.64,1);
}
@keyframes modalPop {
    from { transform: scale(.9); opacity: 0; }
    to   { transform: scale(1);  opacity: 1; }
}
#cal-modal .modal-event-dot {
    width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: .4rem;
}
#cal-modal h4 { font-size: 1rem; font-weight: 700; margin: .5rem 0 .75rem; line-height: 1.3; }
#cal-modal .meta-row {
    display: flex; align-items: flex-start; gap: .5rem;
    font-size: .82rem; color: var(--text-muted); margin-bottom: .35rem;
}
#cal-modal .meta-row strong { color: var(--text); white-space: nowrap; }

/* ── Adaptabilidad Móvil (Responsive CSS overrides) ── */
@media (max-width: 768px) {
    .cal-card {
        padding: 0.75rem;
    }
    .fc .fc-toolbar {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.75rem;
    }
    /* Reordenar barra de herramientas: Título arriba, luego controles de navegación y vistas */
    .fc .fc-toolbar-chunk:nth-child(2) {
        order: 1;
        text-align: center;
    }
    .fc .fc-toolbar-chunk:nth-child(1) {
        order: 2;
        display: flex;
        justify-content: center;
    }
    .fc .fc-toolbar-chunk:nth-child(3) {
        order: 3;
        display: flex;
        justify-content: center;
    }
    .fc .fc-toolbar-title {
        font-size: 1.05rem !important;
    }
    .fc .fc-button {
        padding: 0.3rem 0.6rem;
        font-size: 0.75rem;
    }
    .cal-legend {
        gap: 0.4rem;
    }
    .cal-legend-item {
        font-size: 0.72rem;
        padding: 0.2rem 0.5rem;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 6px;
    }
}

@media (max-width: 576px) {
    .cal-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .cal-header h1 {
        font-size: 1.3rem;
    }
    .cal-header .role-badge {
        padding: 0.25rem 0.65rem;
        font-size: 0.75rem;
    }
}
</style>

<div class="cal-wrap">

  <!-- Encabezado -->
  <div class="cal-header">
    <h1>
      <i class="bi bi-calendar3" style="color:<?= $roleColors[$rol] ?? '#39A900' ?>"></i>
      Calendario Académico
    </h1>
    <span class="role-badge">
      <i class="bi bi-person-circle"></i> <?= $rolLabels[$rol] ?? $rol ?>
    </span>
  </div>

  <!-- Leyenda -->
  <div class="cal-legend">
    <?php if ($rol === ROL_COORDINADOR || $rol === ROL_INSTRUCTOR): ?>
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#39A900"></span> Inicio de Ficha</span>
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#6366f1"></span> Fin de Ficha</span>
    <?php endif; ?>
    <?php if ($rol === ROL_APRENDIZ): ?>
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#3B82F6"></span> Fase en Ejecución</span>
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#39A900"></span> Fase Completada</span>
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#6366f1"></span> Fin de Fase</span>
    <?php endif; ?>
    <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#10b981"></span> Evaluación Aprobada (A)</span>
    <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#ef4444"></span> Plan de Mejora / Eval. D</span>
  </div>

  <!-- Tarjeta con FullCalendar -->
  <div class="cal-card">
    <div id="sena-calendar"></div>
  </div>

</div>

<!-- Modal de detalle -->
<div id="cal-modal-overlay">
  <div id="cal-modal">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.25rem;">
      <span id="cal-modal-type" class="badge-soft" style="font-size:.72rem;"></span>
      <button onclick="closeCalModal()" style="background:none;border:none;font-size:1.3rem;color:var(--text-muted);cursor:pointer;line-height:1;">×</button>
    </div>
    <h4>
      <span class="modal-event-dot" id="cal-modal-dot"></span>
      <span id="cal-modal-title"></span>
    </h4>
    <div id="cal-modal-meta"></div>
    <div style="margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap;">
      <a id="cal-modal-link" href="#" class="btn btn-primary btn-sm" style="border-radius:8px;">
        <i class="bi bi-arrow-right me-1"></i>Ir al módulo
      </a>
      <button onclick="closeCalModal()" class="btn btn-soft btn-sm" style="border-radius:8px;">Cerrar</button>
    </div>
  </div>
</div>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calEl = document.getElementById('sena-calendar');

    // Detectar si es pantalla pequeña
    const isMobile = window.innerWidth < 768;

    const calendar = new FullCalendar.Calendar(calEl, {
        locale: 'es',
        initialView: isMobile ? 'listMonth' : 'dayGridMonth',
        height: isMobile ? 'auto' : 650,
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  isMobile
                    ? 'listMonth,listWeek'
                    : 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: {
            today:     'Hoy',
            month:     'Mes',
            week:      'Semana',
            listMonth: 'Agenda mes',
            listWeek:  'Agenda semana',
        },
        events: {
            url: '<?= $apiUrl ?>',
            method: 'GET',
            failure: function () {
                console.warn('Error al cargar eventos del calendario.');
            }
        },
        loading: function (isLoading) {
            calEl.style.opacity = isLoading ? '.5' : '1';
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            openCalModal(info.event);
        },
        eventDidMount: function (info) {
            // Tooltip nativo mientras no haya hover personalizado
            info.el.title = info.event.title;
        },
        noEventsContent: '✨ Sin eventos en este período',
        dayMaxEvents: 3,
    });

    calendar.render();

    // Redibujar al cambiar tamaño de ventana
    window.addEventListener('resize', function () {
        calendar.updateSize();
    });
});

/* ── Modal helpers ── */
function openCalModal(event) {
    const ext   = event.extendedProps || {};
    const color = event.backgroundColor || '#39A900';
    const url   = event.url || '#';

    document.getElementById('cal-modal-dot').style.background   = color;
    document.getElementById('cal-modal-title').textContent      = event.title;
    document.getElementById('cal-modal-type').textContent       = ext.tipo || 'Evento';
    document.getElementById('cal-modal-link').href              = url;

    // Construir filas de metadata
    const rows = [];
    if (ext.programa)  rows.push(['📚 Programa', ext.programa]);
    if (ext.estado)    rows.push(['📌 Estado',   ext.estado]);
    if (ext.cumpl)     rows.push(['📊 Cumplimiento', ext.cumpl]);
    if (ext.ficha)     rows.push(['📋 Ficha',    ext.ficha]);
    if (ext.ra)        rows.push(['🔖 RA',       ext.ra]);
    if (ext.aprendiz)  rows.push(['🎓 Aprendiz', ext.aprendiz]);
    if (ext.instructor)rows.push(['👨‍🏫 Instructor', ext.instructor]);

    const fecha = event.startStr ? event.startStr.substring(0, 10) : '';
    if (fecha) rows.push(['📅 Fecha', fecha]);

    document.getElementById('cal-modal-meta').innerHTML = rows
        .map(([k, v]) =>
            `<div class="meta-row"><strong>${k}</strong><span>${v}</span></div>`
        ).join('');

    document.getElementById('cal-modal-overlay').classList.add('open');
}

function closeCalModal() {
    document.getElementById('cal-modal-overlay').classList.remove('open');
}

// Cerrar modal al hacer clic fuera
document.getElementById('cal-modal-overlay').addEventListener('click', function (e) {
    if (e.target === this) closeCalModal();
});

// Cerrar con Escape
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCalModal();
});
</script>
