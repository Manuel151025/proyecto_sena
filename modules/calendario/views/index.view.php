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

/* ── Modal de detalle de evento ──
   Contenedor y encabezado los estilan .ui-modal-overlay / .modal-* de
   theme.css; aquí solo queda lo propio del contenido del evento. ── */
.modal-event-dot {
    width: 12px; height: 12px; border-radius: 50%; display: inline-block;
    flex-shrink: 0;
}
.cal-meta-row {
    display: flex; align-items: flex-start; gap: .5rem;
    font-size: .82rem; color: var(--text-muted); margin-bottom: .35rem;
}
.cal-meta-row strong { color: var(--text); white-space: nowrap; }

/* ── Selector de vista (Día / Semana / Mes / Agenda) para móvil:
      sustituye a los botones nativos de FullCalendar, que no caben. ── */
.cal-view-switcher {
    display: none;
    gap: .35rem;
    margin-bottom: .85rem;
    padding: .25rem;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
}
.cal-view-btn {
    flex: 1;
    background: transparent;
    border: 0;
    color: var(--text-muted);
    font-size: .8rem;
    font-weight: 600;
    padding: .5rem .25rem;
    border-radius: 8px;
    min-height: 40px;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.cal-view-btn:hover { color: var(--text); }
.cal-view-btn.is-active {
    background: var(--sena-primary);
    color: #fff;
    box-shadow: 0 2px 8px rgba(57,169,0,.25);
}
/* Pista visual de swipe (solo aparece en móvil) */
.cal-swipe-hint {
    display: none;
    text-align: center;
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .5rem;
}

/* ── Adaptabilidad Móvil (Responsive CSS overrides) ── */
@media (max-width: 768px) {
    .cal-wrap {
        gap: 1rem;
    }
    .cal-card {
        padding: 0.75rem;
    }
    .cal-view-switcher {
        display: flex;
    }
    .cal-swipe-hint {
        display: block;
    }
    .fc .fc-toolbar {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.6rem;
    }
    /* Sin botones de vista nativos en móvil: solo título + navegación prev/next/hoy */
    .fc .fc-toolbar-chunk:nth-child(2) {
        order: 1;
        text-align: center;
    }
    .fc .fc-toolbar-chunk:nth-child(1) {
        order: 2;
        display: flex;
        justify-content: center;
        gap: .35rem;
    }
    .fc .fc-toolbar-title {
        font-size: 1.05rem !important;
    }
    .fc .fc-button {
        padding: 0.4rem 0.75rem;
        font-size: 0.78rem;
        min-height: 38px;
    }
    /* Celdas del mes más compactas para que quepan 7 columnas */
    .fc .fc-daygrid-day-number {
        font-size: .75rem;
        padding: 2px 4px;
    }
    .fc .fc-col-header-cell-cushion {
        font-size: .72rem;
        padding: 6px 2px;
    }
    .fc .fc-daygrid-day-frame {
        min-height: 62px;
    }
    .fc .fc-event {
        font-size: .68rem;
        padding: 1px 3px;
    }
    .fc .fc-daygrid-more-link {
        font-size: .68rem;
    }
    /* Agenda: filas más cómodas para el dedo */
    .fc .fc-list-event-title {
        font-size: .85rem;
    }
    .fc .fc-list-event td {
        padding: .65rem .5rem;
    }
    /* La leyenda pasa a una fila deslizable en vez de ocupar cuatro líneas */
    .cal-legend {
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 0.4rem;
        padding-bottom: 2px;
        scrollbar-width: none;
    }
    .cal-legend::-webkit-scrollbar {
        display: none;
    }
    .cal-legend-item {
        flex: 0 0 auto;
        white-space: nowrap;
        font-size: 0.72rem;
        padding: 0.25rem 0.55rem;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 999px;
    }
}

@media (max-width: 576px) {
    .cal-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.6rem;
    }
    .cal-header h1 {
        font-size: 1.25rem;
    }
    .cal-header .cal-header-actions {
        justify-content: space-between;
    }
    .cal-header .role-badge {
        padding: 0.25rem 0.65rem;
        font-size: 0.75rem;
    }
    .cal-view-btn {
        font-size: .74rem;
        padding: .5rem .15rem;
    }
    .fc .fc-daygrid-day-frame {
        min-height: 54px;
    }
    /* En pantallas muy angostas los eventos del mes se reducen a un punto:
       el detalle se consulta con un toque (abre el modal). */
    .fc .fc-daygrid-event .fc-event-time,
    .fc .fc-daygrid-event .fc-event-title {
        font-size: .65rem;
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
    <div class="cal-header-actions" style="display:flex; align-items:center; gap:.6rem;">
      <?php if ($puedeCrearEvento): ?>
        <button type="button" class="btn btn-primary btn-sm" style="border-radius:8px;" onclick="openCrearEventoModal()">
          <i class="bi bi-plus-lg me-1"></i>Nuevo evento
        </button>
      <?php endif; ?>
      <span class="role-badge">
        <i class="bi bi-person-circle"></i> <?= $rolLabels[$rol] ?? $rol ?>
      </span>
    </div>
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
    <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#f59e0b"></span> Evento manual</span>
  </div>

  <!-- Tarjeta con FullCalendar -->
  <div class="cal-card">
    <div class="cal-view-switcher" role="group" aria-label="Cambiar vista del calendario">
      <button type="button" class="cal-view-btn" data-cal-view="dayGridDay">Día</button>
      <button type="button" class="cal-view-btn" data-cal-view="dayGridWeek">Semana</button>
      <button type="button" class="cal-view-btn" data-cal-view="dayGridMonth">Mes</button>
      <button type="button" class="cal-view-btn" data-cal-view="listWeek">Agenda</button>
    </div>
    <div id="sena-calendar"></div>
    <div class="cal-swipe-hint"><i class="bi bi-arrow-left-right"></i> Desliza para cambiar de período</div>
  </div>

</div>

<!-- Modal de detalle -->
<div id="cal-modal-overlay" class="ui-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cal-modal-titulo">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="cal-modal-titulo">
        <span class="modal-event-dot" id="cal-modal-dot"></span>
        <span id="cal-modal-title"></span>
      </h5>
      <button type="button" class="ui-modal-close" onclick="closeCalModal()" aria-label="Cerrar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-body">
      <span id="cal-modal-type" class="badge-soft mb-3" style="font-size:.72rem;"></span>
      <div id="cal-modal-meta" class="mt-3"></div>
    </div>
    <div class="modal-footer">
      <form id="cal-modal-delete-form" method="post" action="<?= APP_URL ?>/index.php/calendario" style="display:none; margin-right:auto;" onsubmit="return confirm('¿Eliminar este evento del calendario?');">
        <input type="hidden" name="action" value="eliminar_evento">
        <input type="hidden" name="evento_id" id="cal-modal-delete-id" value="">
        <button type="submit" class="btn btn-soft text-danger">
          <i class="bi bi-trash me-1"></i>Eliminar
        </button>
      </form>
      <button type="button" onclick="closeCalModal()" class="btn btn-soft">Cerrar</button>
      <a id="cal-modal-link" href="#" class="btn btn-primary">
        <i class="bi bi-arrow-right me-1"></i>Ir al módulo
      </a>
    </div>
  </div>
</div>

<?php if ($puedeCrearEvento): ?>
<!-- Modal de creación de evento -->
<div id="cal-crear-overlay" class="ui-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cal-crear-titulo">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="cal-crear-titulo"><i class="bi bi-calendar-plus"></i>Nuevo evento</h5>
      <button type="button" class="ui-modal-close" onclick="closeCrearEventoModal()" aria-label="Cerrar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <form method="post" action="<?= APP_URL ?>/index.php/calendario">
      <input type="hidden" name="action" value="crear_evento">
      <div class="modal-body">
        <div class="mb-3">
          <label for="cal-ev-titulo" class="form-label">Título</label>
          <input type="text" class="form-control" id="cal-ev-titulo" name="titulo" maxlength="150" required placeholder="Ej: Reunión de seguimiento">
        </div>
        <div class="mb-3">
          <label for="cal-ev-fecha" class="form-label">Fecha</label>
          <input type="date" class="form-control" id="cal-ev-fecha" name="fecha" required>
        </div>
        <div class="mb-3">
          <label for="cal-ev-ficha" class="form-label">Ficha</label>
          <select class="form-select" id="cal-ev-ficha" name="ficha_id" required
                  data-picker
                  data-picker-label="Seleccionar ficha"
                  data-picker-placeholder="Número de ficha o programa...">
            <option value="" disabled selected>Seleccione una ficha…</option>
            <?php foreach ($fichasDisponibles as $f): ?>
              <option value="<?= (int)$f['id'] ?>"
                      data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-0">
          <label for="cal-ev-desc" class="form-label">Descripción (opcional)</label>
          <textarea class="form-control" id="cal-ev-desc" name="descripcion" rows="2" maxlength="500"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeCrearEventoModal()" class="btn btn-soft">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar evento</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calEl = document.getElementById('sena-calendar');
    const viewButtons = Array.from(document.querySelectorAll('.cal-view-btn'));

    // Punto de corte único para toda la lógica responsive de esta vista
    const MOBILE_BP = 768;
    const isNarrow = () => window.innerWidth < MOBILE_BP;

    // Detectar si es pantalla pequeña
    let isMobile = isNarrow();
    const initialView = isMobile ? 'listWeek' : 'dayGridMonth';

    // Los eventos (inicio/fin de ficha, evaluaciones, fases) son siempre de día completo,
    // por eso se usan vistas "dayGrid" (no "timeGrid", que dejaría una grilla de horas vacía).
    const calendar = new FullCalendar.Calendar(calEl, {
        locale: 'es',
        initialView: initialView,
        height: isMobile ? 'auto' : 650,
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  isMobile
                    ? ''
                    : 'dayGridMonth,dayGridWeek,dayGridDay,listWeek'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week:  'Semana',
            day:   'Día',
            list:  'Agenda',
        },
        views: {
            listWeek: { buttonText: 'Agenda' }
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
        dayMaxEvents: isMobile ? 2 : 3,
    });

    calendar.render();

    // Selector de vista para móvil (Día / Semana / Mes / Agenda)
    function markActiveViewButton() {
        const current = calendar.view.type;
        viewButtons.forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.calView === current);
        });
    }
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            calendar.changeView(this.dataset.calView);
            markActiveViewButton();
        });
    });
    markActiveViewButton();

    // Navegación por gestos (swipe) en móvil: deslizar para ir al período
    // anterior/siguiente. Se registra siempre (no solo si arranca en móvil)
    // porque la ventana puede cambiar de tamaño; en escritorio no molesta.
    let touchStartX = 0, touchStartY = 0;
    calEl.addEventListener('touchstart', function (e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });
    calEl.addEventListener('touchend', function (e) {
        const dx = e.changedTouches[0].clientX - touchStartX;
        const dy = e.changedTouches[0].clientY - touchStartY;
        // Solo actuar si el gesto es principalmente horizontal
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
            if (dx < 0) calendar.next(); else calendar.prev();
        }
    }, { passive: true });

    // Al cruzar el punto de corte hay que reconfigurar el calendario, no solo
    // redibujarlo: en móvil la vista por defecto es la agenda, la altura es
    // automática y los botones de vista nativos se ocultan (los sustituye
    // .cal-view-switcher). Antes esto se decidía una única vez al cargar, así
    // que al rotar el teléfono o redimensionar quedaba la configuración vieja.
    function applyResponsiveLayout() {
        const narrow = isNarrow();
        if (narrow === isMobile) {
            calendar.updateSize();
            return;
        }
        isMobile = narrow;
        calendar.setOption('height', narrow ? 'auto' : 650);
        calendar.setOption('dayMaxEvents', narrow ? 2 : 3);
        calendar.setOption('headerToolbar', {
            left:   'prev,next today',
            center: 'title',
            right:  narrow ? '' : 'dayGridMonth,dayGridWeek,dayGridDay,listWeek'
        });
        // Solo se cambia de vista si la actual no es cómoda en el nuevo ancho
        if (narrow && calendar.view.type === 'dayGridMonth') {
            calendar.changeView('listWeek');
        } else if (!narrow && calendar.view.type === 'listWeek') {
            calendar.changeView('dayGridMonth');
        }
        markActiveViewButton();
        calendar.updateSize();
    }

    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyResponsiveLayout, 150);
    });
    window.addEventListener('orientationchange', function () {
        setTimeout(applyResponsiveLayout, 200);
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
    if (ext.descripcion) rows.push(['📝 Descripción', ext.descripcion]);
    if (ext.creador)   rows.push(['👤 Creado por', ext.creador]);

    const fecha = event.startStr ? event.startStr.substring(0, 10) : '';
    if (fecha) rows.push(['📅 Fecha', fecha]);

    document.getElementById('cal-modal-meta').innerHTML = rows
        .map(([k, v]) =>
            `<div class="cal-meta-row"><strong>${k}</strong><span>${v}</span></div>`
        ).join('');

    const deleteForm = document.getElementById('cal-modal-delete-form');
    if (ext.manual && ext.puedeEliminar) {
        document.getElementById('cal-modal-delete-id').value = ext.eventoId;
        deleteForm.style.display = '';
    } else {
        deleteForm.style.display = 'none';
    }

    document.getElementById('cal-modal-overlay').classList.add('is-open');
}

function closeCalModal() {
    document.getElementById('cal-modal-overlay').classList.remove('is-open');
}

// Cerrar modal al hacer clic fuera
document.getElementById('cal-modal-overlay').addEventListener('click', function (e) {
    if (e.target === this) closeCalModal();
});

// Cerrar con Escape
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    // Si hay un searchable-picker abierto por encima, Escape es suyo:
    // cerrarlo no debe descartar también el formulario de nuevo evento.
    if (document.querySelector('.sp-modal.is-open')) return;
    closeCalModal();
    closeCrearEventoModal();
});

/* ── Modal de creación de evento ── */
function openCrearEventoModal() {
    const overlay = document.getElementById('cal-crear-overlay');
    if (!overlay) return;
    const fechaInput = document.getElementById('cal-ev-fecha');
    if (fechaInput && !fechaInput.value) {
        fechaInput.value = new Date().toISOString().substring(0, 10);
    }
    overlay.classList.add('is-open');
}

function closeCrearEventoModal() {
    const overlay = document.getElementById('cal-crear-overlay');
    if (overlay) overlay.classList.remove('is-open');
}

document.getElementById('cal-crear-overlay')?.addEventListener('click', function (e) {
    if (e.target === this) closeCrearEventoModal();
});
</script>
