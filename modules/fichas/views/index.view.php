<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Support\Semaforo;

$hayFiltros = $filtros['search'] !== '' || $filtros['programa_id'] || $filtros['estado'] !== '';
$pct = static fn($v) => $v === null ? '—' : ((int)round((float)$v)) . '%';
$exportar = APP_URL . '/index.php/fichas/exportar?' . http_build_query(array_filter($filtros, static fn($x) => $x !== '' && $x !== 0));
?>
<div class="page-header">
  <div>
    <h1>Fichas de Formación</h1>
    <p class="text-muted mb-0">Cumplimiento de RAP y avance del proyecto formativo de cada ficha.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e($exportar . '&formato=xlsx') ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
    <?php if ($esCoordinador): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-plus-lg me-1"></i>Nueva ficha</button>
    <?php endif; ?>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<form method="GET" class="toolbar mb-3">
  <div class="search">
    <i class="bi bi-search"></i>
    <label class="visually-hidden" for="f_search">Buscar ficha</label>
    <input type="search" name="search" id="f_search" class="form-control" maxlength="100" placeholder="Número, programa o instructor..." value="<?= e($filtros['search']) ?>">
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_programa">Programa</label>
    <select name="programa_id" id="f_programa" class="form-select" data-autoenvio data-picker data-picker-label="Programa">
      <option value="0">Todos los programas</option>
      <?php foreach ($programas as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $filtros['programa_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_estado">Estado</label>
    <select name="estado" id="f_estado" class="form-select" data-autoenvio data-picker data-picker-label="Estado">
      <option value="">Todos los estados</option>
      <?php foreach ($estados_label as $v => [$t]): ?>
        <option value="<?= e($v) ?>" <?= $filtros['estado'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-soft"><i class="bi bi-funnel me-1"></i>Filtrar</button>
  <?php if ($hayFiltros): ?><a href="<?= e(APP_URL . '/index.php/fichas') ?>" class="btn btn-soft text-muted">Limpiar</a><?php endif; ?>
</form>

<div class="row g-3">
  <?php foreach ($fichas as $f):
      [$estTxt, $estCls] = $estados_label[$f['estado']] ?? [$f['estado'], 'secondary'];
      $sem = $f['semaforo'];
  ?>
  <div class="col-md-6 col-xl-4">
    <article class="card h-100 tarjeta-elevable">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
          <h2 class="h5 mb-0 fw-bold">Ficha <?= e($f['numero_ficha']) ?></h2>
          <span class="badge-soft <?= e($estCls) ?>"><?= e($estTxt) ?></span>
        </div>
        <div class="small text-muted text-uppercase-visual mb-2"><?= e($f['programa']) ?></div>
        <div class="small mb-3"><i class="bi bi-person-badge me-1"></i><?= e($f['instructor']) ?>
          <?php if ($f['proyecto_codigo']): ?><br><i class="bi bi-kanban me-1"></i><?= e($f['proyecto_codigo']) ?> · <?= e($f['proyecto_nombre']) ?><?php endif; ?></div>

        <div class="panel-cifras mb-3 flex-grow-1">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Aprendices activos</span><strong><?= (int)$f['aprendices_activos'] ?></strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">RAP en D</span><strong class="<?= (int)$f['en_d'] > 0 ? 'text-danger' : '' ?>"><?= (int)$f['en_d'] ?></strong></div>
          <div class="d-flex justify-content-between mb-1" title="RAP en A sobre los ya evaluados"><span class="text-muted">Desempeño</span>
            <strong><?= e($pct($f['pct_a'])) ?> <span class="badge-soft <?= e(Semaforo::clase($sem)) ?> ms-1"><?= e(Semaforo::etiqueta($sem)) ?></span></strong></div>
          <div class="d-flex justify-content-between mb-1" title="RAP en A sobre el total del programa"><span class="text-muted">Avance de RAP</span><strong><?= e($pct($f['cumplimiento'])) ?></strong></div>
          <div class="progress barra-avance mb-2" role="progressbar" aria-label="Avance de RAP" aria-valuenow="<?= (int)round((float)$f['cumplimiento']) ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-success" style="width: <?= (int)round((float)$f['cumplimiento']) ?>%"></div>
          </div>
          <div class="d-flex justify-content-between"><span class="text-muted">Avance del proyecto</span><strong><?= e($pct($f['avance_proyecto'])) ?></strong></div>
        </div>

        <div class="d-flex gap-2">
          <a href="<?= e(APP_URL . '/index.php/fichas/ver?id=' . (int)$f['id']) ?>" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-eye me-1"></i>Ver seguimiento</a>
          <?php if ($esCoordinador): ?>
            <button type="button" class="btn btn-soft btn-sm" aria-label="Editar ficha" data-modal="#modalEditar"
                    data-valores="<?= datosJson(['id' => (int)$f['id'], 'numero_ficha' => $f['numero_ficha'], 'programa_id' => (int)$f['programa_id'],
                        'proyecto_id' => (int)($f['proyecto_id'] ?? 0), 'instructor_id' => (int)$f['instructor_id'], 'estado' => $f['estado'],
                        'fecha_inicio' => $f['fecha_inicio'] ?? '', 'fecha_fin' => $f['fecha_fin'] ?? '']) ?>"><i class="bi bi-pencil"></i></button>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar la ficha ' . $f['numero_ficha'] . '?') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
              <button type="submit" class="btn btn-soft btn-sm text-danger" aria-label="Eliminar ficha"><i class="bi bi-trash"></i></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </article>
  </div>
  <?php endforeach; ?>
  <?php if (empty($fichas)): ?>
  <div class="col-12 estado-vacio"><i class="bi bi-folder2-open"></i><?= $hayFiltros ? 'Ninguna ficha coincide con los filtros.' : 'No hay fichas para mostrar.' ?></div>
  <?php endif; ?>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'fichas'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($esCoordinador):
    foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>"><i class="bi <?= $accion === 'crear' ? 'bi-folder-plus' : 'bi-pencil' ?>"></i><?= $accion === 'crear' ? 'Nueva ficha' : 'Editar ficha' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_numero">Número de ficha <span class="text-danger">*</span></label>
              <input type="text" name="numero_ficha" id="<?= $accion ?>_numero" class="form-control" required minlength="3" maxlength="20" data-filtro="codigo" inputmode="numeric">
            </div>
            <div class="col-sm-8">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_programa">Programa <span class="text-danger">*</span></label>
              <select name="programa_id" id="<?= $accion ?>_programa" class="form-select" required data-picker data-picker-label="Programa">
                <option value="" disabled selected>Seleccione…</option>
                <?php foreach ($programas as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['codigo'] . ' — ' . $p['nombre']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_instructor">Instructor líder <span class="text-danger">*</span></label>
              <select name="instructor_id" id="<?= $accion ?>_instructor" class="form-select" required data-picker data-picker-label="Instructor líder">
                <option value="" disabled selected>Seleccione…</option>
                <?php foreach ($instructores as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['nombre']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_proyecto">Proyecto formativo</label>
              <select name="proyecto_id" id="<?= $accion ?>_proyecto" class="form-select" data-picker data-picker-label="Proyecto formativo">
                <option value="0">Sin proyecto asignado</option>
                <?php foreach ($proyectos as $pr): ?><option value="<?= (int)$pr['id'] ?>"><?= e($pr['codigo'] . ' — ' . $pr['nombre']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_estado">Estado</label>
              <select name="estado" id="<?= $accion ?>_estado" class="form-select" data-picker data-picker-label="Estado">
                <?php foreach ($estados_label as $v => [$t]): ?><option value="<?= e($v) ?>"><?= e($t) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_inicio">Fecha de inicio</label>
              <input type="date" name="fecha_inicio" id="<?= $accion ?>_inicio" class="form-control" min="2000-01-01" max="2100-12-31">
            </div>
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_fin">Fecha de fin</label>
              <input type="date" name="fecha_fin" id="<?= $accion ?>_fin" class="form-control" min="2000-01-01" max="2100-12-31">
            </div>
          </div>
          <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>El número de aprendices y el cumplimiento se calculan solos a partir de las matrículas y los juicios.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
