<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Services\ReportesService;
use Core\Support\Semaforo;

/** @var \Core\Support\Actor $actor */
$pct = static fn($v) => $v === null ? '—' : ((int)round((float)$v)) . '%';
$accion = e(APP_URL . '/index.php/reportes/descargar');
$hoy = date('Y-m-d');
$hace90 = date('Y-m-d', strtotime('-90 days'));
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Reportes</h1>
    <p class="text-muted mb-0"><?= $actor->esCoordinador()
        ? 'Reportes del centro por ficha, instructor y competencia, en Excel (.xlsx), CSV o PDF.'
        : 'Reportes de lo que calificas y de tus fichas, en Excel (.xlsx), CSV o PDF.' ?></p>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<?php if ($resumen): ?>
<div class="row g-3 mb-4">
  <?php foreach ([
      ['etiqueta' => 'Fichas activas', 'valor' => $resumen['fichas_activas'], 'icono' => 'bi-journal-bookmark'],
      ['etiqueta' => 'Aprendices en formación', 'valor' => $resumen['aprendices'], 'icono' => 'bi-people'],
      ['etiqueta' => 'Desempeño', 'valor' => $pct($resumen['desempeno']), 'icono' => 'bi-speedometer2', 'clase' => 'text-' . Semaforo::clase($resumen['semaforo'])],
      ['etiqueta' => 'Avance de RAP', 'valor' => $pct($resumen['avance']), 'icono' => 'bi-graph-up-arrow'],
  ] as $kpi): ?>
    <div class="col-6 col-lg-3"><?php require BASE_PATH . 'components/kpi.php'; ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($tipos as $tipo => [$titulo, $descripcion, $icono]): ?>
  <div class="col-md-6 col-xl-4">
    <form method="GET" action="<?= $accion ?>" class="card border-0 shadow-sm h-100">
      <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
      <div class="card-body d-flex flex-column">
        <h2 class="h6 fw-bold"><i class="bi <?= e($icono) ?> text-success me-2"></i><?= e($titulo) ?></h2>
        <p class="small text-muted"><?= e($descripcion) ?></p>
        <?php if ($tipo === 'ficha'): ?>
          <label class="form-label small fw-semibold" for="rep_ficha">Ficha</label>
          <select name="ficha_id" id="rep_ficha" class="form-select mb-3" required data-picker data-picker-label="Ficha">
            <option value="" disabled selected>Seleccione…</option>
            <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>">Ficha <?= e($f['numero_ficha']) ?></option><?php endforeach; ?>
          </select>
        <?php elseif ($tipo === 'historial'): ?>
          <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label small fw-semibold" for="rep_desde">Desde</label>
              <input type="date" name="desde" id="rep_desde" class="form-control" value="<?= e($hace90) ?>" max="<?= e($hoy) ?>"></div>
            <div class="col-6"><label class="form-label small fw-semibold" for="rep_hasta">Hasta</label>
              <input type="date" name="hasta" id="rep_hasta" class="form-control" value="<?= e($hoy) ?>" max="<?= e($hoy) ?>"></div>
            <div class="col-12"><small class="text-muted">Hasta <?= ReportesService::MAX_DIAS_HISTORIAL ?> días por archivo.</small></div>
          </div>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-auto" role="group" aria-label="Formato de <?= e($titulo) ?>">
          <button type="submit" name="formato" value="xlsx" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          <button type="submit" name="formato" value="pdf" class="btn btn-sm btn-soft flex-grow-1"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
          <button type="submit" name="formato" value="csv" class="btn btn-sm btn-soft flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
        </div>
      </div>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<p class="small text-muted mt-3">Los planes de mejoramiento y los juicios con filtros se exportan desde sus pantallas (<a href="<?= e(APP_URL . '/index.php/mejoramiento') ?>">Planes</a>, <a href="<?= e(APP_URL . '/index.php/evaluaciones') ?>">Juicios</a>).</p>
