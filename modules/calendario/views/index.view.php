<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
/** @var \Core\Support\Actor $actor */
$gestiona = $actor->gestiona();
$scriptsCdn[] = 'fullcalendar';
$scriptsVista[] = 'modulos/calendario.js';
$leyenda = array_filter([
    ['#f59e0b', 'Evento'],
    ['#39A900', 'Inicio de ficha'],
    ['#6366f1', 'Fin de ficha'],
    ['#3b82f6', 'Fase del proyecto'],
    ['#14b8a6', 'Entrega de actividad'],
    ['#ef4444', 'Vence un plan de mejoramiento'],
    $actor->esCoordinador() ? null : ['#22c55e', 'Juicio A'],
    $actor->esCoordinador() ? null : ['#dc2626', 'Juicio D'],
]);
?>
<link rel="stylesheet" href="<?= e(APP_URL . '/assets/css/calendario.css?v=' . filemtime(BASE_PATH . 'assets/css/calendario.css')) ?>">

<div class="page-header">
  <div>
    <h1 class="mb-1">Calendario</h1>
    <p class="text-muted mb-0"><?= $actor->esAprendiz()
        ? 'Fechas de tu ficha: fases del proyecto, entregas, planes de mejoramiento y eventos.'
        : 'Fechas de tus fichas: inicio y fin, fases del proyecto, entregas, planes que vencen y eventos.' ?></p>
  </div>
  <?php if ($gestiona): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEvento"><i class="bi bi-calendar-plus me-1"></i>Nuevo evento</button>
  <?php endif; ?>
</div>

<div class="cal-legend mb-3">
  <?php foreach ($leyenda as [$color, $texto]): ?>
    <span class="cal-legend-item"><span class="cal-legend-dot" style="background: <?= e($color) ?>"></span><?= e($texto) ?></span>
  <?php endforeach; ?>
</div>

<div class="cal-card">
  <div class="cal-view-switcher" role="group" aria-label="Cambiar vista del calendario">
    <button type="button" class="cal-view-btn" data-cal-view="dayGridDay">Día</button>
    <button type="button" class="cal-view-btn" data-cal-view="dayGridWeek">Semana</button>
    <button type="button" class="cal-view-btn" data-cal-view="dayGridMonth">Mes</button>
    <button type="button" class="cal-view-btn" data-cal-view="listWeek">Agenda</button>
  </div>
  <div id="sena-calendar" data-api="<?= e($apiUrl) ?>"></div>
  <div class="cal-swipe-hint"><i class="bi bi-arrow-left-right"></i> Desliza para cambiar de período</div>
</div>

<!-- Detalle de un evento: los textos se ponen con textContent (calendario.js) -->
<div class="modal fade" id="modalDetalleEvento" tabindex="-1" aria-labelledby="tituloDetalleEvento" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2" id="tituloDetalleEvento"><span class="modal-event-dot" data-evento="color"></span><span data-evento="titulo"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <span class="badge-soft secondary" data-evento="tipo"></span>
        <dl class="row small mt-3 mb-0" data-evento="datos"></dl>
      </div>
      <div class="modal-footer">
        <?php if ($gestiona): ?>
        <form method="POST" class="me-auto" data-evento="eliminar" hidden data-confirmar="¿Eliminar este evento del calendario?">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="eliminar">
          <input type="hidden" name="evento_id" value="">
          <button type="submit" class="btn btn-soft text-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
        </form>
        <?php endif; ?>
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cerrar</button>
        <a href="#" class="btn btn-primary" data-evento="enlace" hidden><i class="bi bi-arrow-right me-1"></i>Ir al módulo</a>
      </div>
    </div>
  </div>
</div>

<?php if ($gestiona): ?>
<div class="modal fade" id="modalEvento" tabindex="-1" aria-labelledby="tituloEvento" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="crear">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloEvento"><i class="bi bi-calendar-plus"></i>Nuevo evento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="ev_titulo" class="form-label fw-semibold">Título <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="ev_titulo" name="titulo" minlength="3" maxlength="150" required data-filtro="nombre" placeholder="Ej.: Socialización de la fase 2">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-sm-5">
              <label for="ev_fecha" class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="ev_fecha" name="fecha" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="col-sm-7">
              <label for="ev_ficha" class="form-label fw-semibold">Ficha <span class="text-danger">*</span></label>
              <select class="form-select" id="ev_ficha" name="ficha_id" required data-picker data-picker-label="Ficha">
                <option value="" disabled selected>Seleccione…</option>
                <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>">Ficha <?= e($f['numero_ficha']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <label for="ev_desc" class="form-label fw-semibold">Descripción</label>
          <textarea class="form-control" id="ev_desc" name="descripcion" rows="3" maxlength="1000" data-filtro="sin-html"></textarea>
          <small class="text-muted">Los aprendices de la ficha recibirán un aviso.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar evento</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
