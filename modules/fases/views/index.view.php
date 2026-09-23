<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$fechaCorta = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Fases del Proyecto Formativo</h1>
    <p class="text-muted mb-0">El avance de cada fase se calcula con las actividades registradas en ella.</p>
  </div>
  <?php if ($puedeGestionar && $proyectoId > 0): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="bi bi-plus-lg me-1"></i>Nueva fase
    </button>
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<?php if (count($proyectos) > 1 || count($fichas) > 1): ?>
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <?php if (count($proyectos) > 1): ?>
      <div class="col-md-7">
        <label class="form-label text-muted small" for="filtro_proyecto">Proyecto formativo</label>
        <select name="proyecto_id" id="filtro_proyecto" class="form-select" data-autoenvio
                data-picker data-picker-label="Seleccionar proyecto" data-picker-placeholder="Código o nombre...">
          <?php foreach ($proyectos as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $proyectoId === (int)$p['id'] ? 'selected' : '' ?>
                    data-search="<?= e($p['codigo'] . ' ' . $p['nombre']) ?>">
              <?= e($p['codigo']) ?> — <?= e($p['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="proyecto_id" value="<?= (int)$proyectoId ?>">
      <?php endif; ?>
      <?php if (count($fichas) > 1): ?>
      <div class="col-md-5">
        <label class="form-label text-muted small" for="filtro_ficha">Avance de la ficha</label>
        <select name="ficha_id" id="filtro_ficha" class="form-select" data-autoenvio data-picker data-picker-label="Ficha">
          <option value="0">Todas mis fichas del proyecto</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= (int)$f['id'] ?>" <?= $fichaId === (int)$f['id'] ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <noscript><div class="col-12"><button type="submit" class="btn btn-soft">Aplicar</button></div></noscript>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($proyectoActual): ?>
<div class="card mb-4 tarjeta-lateral">
  <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="min-w-0">
      <h2 class="h5 mb-0 fw-bold text-uppercase-visual"><?= e($proyectoActual['nombre']) ?></h2>
      <small class="text-muted"><?= e($proyectoActual['objetivo'] ?: 'Sin objetivo definido.') ?></small>
    </div>
    <div class="lista-chips">
      <?php foreach ($fichas as $f): ?>
        <span class="badge bg-soft primary">Ficha <?= e($f['numero_ficha']) ?></span>
      <?php endforeach; ?>
      <?php if (empty($fichas)): ?><span class="badge-soft secondary">Sin fichas vinculadas</span><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($fases as $fase):
      [$estTxt, $estCls] = $estados_label[$fase['estado']] ?? [$fase['estado'], 'secondary'];
      $total = (int)$fase['total_actividades'];
      $avance = $total > 0 ? (float)$fase['avance'] : null;
  ?>
    <div class="col-md-6 col-lg-4">
      <article class="card glass-card h-100 border-0 shadow-sm tarjeta-elevable">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft info fw-bold">Fase <?= (int)$fase['numero_fase'] ?></span>
            <span class="badge-soft <?= e($estCls) ?>"><?= e($estTxt) ?></span>
          </div>
          <h3 class="h5 fw-bold mb-1"><?= e($fase['nombre']) ?></h3>
          <p class="text-muted small flex-grow-1"><?= e($fase['descripcion'] ?: 'Sin descripción.') ?></p>

          <div class="panel-cifras mb-3">
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Inicio</span><span class="fw-semibold"><?= e($fechaCorta($fase['fecha_inicio'])) ?></span></div>
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Fin</span><span class="fw-semibold"><?= e($fechaCorta($fase['fecha_fin'])) ?></span></div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Actividades</span>
              <span class="fw-semibold"><?= (int)$fase['actividades_completadas'] ?> / <?= $total ?> completadas</span></div>
            <?php if ($avance === null): ?>
              <div class="small text-muted fst-italic">Sin actividades en esta fase.</div>
            <?php else: ?>
              <div class="progress barra-avance" role="progressbar" aria-label="Avance de la fase"
                   aria-valuenow="<?= (int)round($avance) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-<?= claseAvance($avance) ?>" style="width: <?= (int)round($avance) ?>%"></div>
              </div>
              <div class="text-end fw-bold mt-1 small"><?= (int)round($avance) ?>%</div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-soft flex-grow-1"
               href="<?= e(APP_URL . '/index.php/actividades?fase_id=' . (int)$fase['id'] . ($fichaId > 0 ? '&ficha_id=' . $fichaId : '')) ?>">
              <i class="bi bi-check2-square me-1"></i>Actividades
            </a>
            <?php if ($puedeGestionar): ?>
              <button type="button" class="btn btn-sm btn-soft" aria-label="Editar fase"
                      data-modal="#modalEditar"
                      data-valores="<?= datosJson([
                          'id' => (int)$fase['id'], 'numero_fase' => (int)$fase['numero_fase'], 'nombre' => $fase['nombre'],
                          'descripcion' => $fase['descripcion'] ?? '', 'fecha_inicio' => $fase['fecha_inicio'] ?? '',
                          'fecha_fin' => $fase['fecha_fin'] ?? '', 'estado' => $fase['estado'],
                      ]) ?>">
                <i class="bi bi-pencil"></i>
              </button>
            <?php endif; ?>
            <?php if ($puedeEliminar): ?>
              <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar la fase ' . $fase['nombre'] . '?') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$fase['id'] ?>">
                <input type="hidden" name="proyecto_id" value="<?= (int)$proyectoId ?>">
                <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Eliminar fase"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>

  <?php if (empty($fases)): ?>
    <div class="col-12 estado-vacio">
      <i class="bi bi-list-task"></i>
      <?php if ($proyectoId === 0): ?>
        <?= $user_rol === ROL_APRENDIZ ? 'Tu ficha no tiene un proyecto formativo asignado.' : 'No hay proyectos formativos disponibles.' ?>
      <?php else: ?>
        Este proyecto aún no tiene fases registradas.
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($puedeGestionar && $proyectoId > 0):
    foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <input type="hidden" name="proyecto_id" value="<?= (int)$proyectoId ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>">
            <i class="bi <?= $accion === 'crear' ? 'bi-list-task' : 'bi-pencil' ?>"></i>
            <?= $accion === 'crear' ? 'Nueva fase del proyecto' : 'Editar fase' ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_numero">Fase n.º</label>
              <input type="number" name="numero_fase" id="<?= $accion ?>_numero" class="form-control" required
                     min="1" max="<?= (int)$limites['numero'] ?>" step="1" inputmode="numeric">
            </div>
            <div class="col-8">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_nombre">Nombre</label>
              <input type="text" name="nombre" id="<?= $accion ?>_nombre" class="form-control" required
                     minlength="3" maxlength="<?= (int)$limites['nombre'] ?>" data-filtro="nombre" placeholder="Ej.: Análisis">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="<?= $accion ?>_descripcion">Descripción</label>
            <textarea name="descripcion" id="<?= $accion ?>_descripcion" class="form-control" rows="3"
                      maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_inicio">Fecha de inicio</label>
              <input type="date" name="fecha_inicio" id="<?= $accion ?>_inicio" class="form-control" min="2000-01-01" max="2100-12-31">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_fin">Fecha de fin</label>
              <input type="date" name="fecha_fin" id="<?= $accion ?>_fin" class="form-control" min="2000-01-01" max="2100-12-31">
            </div>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="<?= $accion ?>_estado">Estado</label>
            <select name="estado" id="<?= $accion ?>_estado" class="form-select" data-picker data-picker-label="Estado de la fase">
              <?php foreach ($estados_label as $valor => [$texto]): ?>
                <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
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
