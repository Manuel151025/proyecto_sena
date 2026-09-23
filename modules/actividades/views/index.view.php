<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
if ($puedeGestionar) {
    $scriptsVista[] = 'modulos/actividades.js';
}
$fechaCorta = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$hayFiltros = $filtros['search'] !== '' || $filtros['ficha_id'] || $filtros['fase_id'] || $filtros['proyecto_id'] || $filtros['estado'] !== '';
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Actividades de Aprendizaje</h1>
    <p class="text-muted mb-0">
      <?= $user_rol === ROL_APRENDIZ
          ? 'Las actividades del proyecto formativo de tu ficha, por fase.'
          : 'Planea y haz seguimiento a las actividades de cada fase del proyecto formativo.' ?>
    </p>
  </div>
  <?php if ($puedeGestionar && !empty($fichas)): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="bi bi-plus-lg me-1"></i>Nueva actividad
    </button>
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <?php if ($filtros['fase_id']): ?><input type="hidden" name="fase_id" value="<?= (int)$filtros['fase_id'] ?>"><?php endif; ?>
      <?php if ($filtros['proyecto_id']): ?><input type="hidden" name="proyecto_id" value="<?= (int)$filtros['proyecto_id'] ?>"><?php endif; ?>
      <div class="col-md-4">
        <label class="form-label text-muted small" for="f_search">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
          <input type="search" name="search" id="f_search" class="form-control" maxlength="100"
                 placeholder="Nombre o descripción..." value="<?= e($filtros['search']) ?>">
        </div>
      </div>
      <?php if (count($fichas) > 1): ?>
      <div class="col-md-3">
        <label class="form-label text-muted small" for="f_ficha">Ficha</label>
        <select name="ficha_id" id="f_ficha" class="form-select" data-picker data-picker-label="Filtrar por ficha">
          <option value="0">Todas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= (int)$f['id'] ?>" <?= $filtros['ficha_id'] === (int)$f['id'] ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label text-muted small" for="f_estado">Estado</label>
        <select name="estado" id="f_estado" class="form-select" data-picker data-picker-label="Estado de la actividad">
          <option value="">Todos</option>
          <?php foreach ($estados_label as $valor => [$texto]): ?>
            <option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-soft flex-grow-1">Filtrar</button>
        <?php if ($hayFiltros): ?>
          <a href="<?= e(APP_URL . '/index.php/actividades') ?>" class="btn btn-soft" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($actividades as $act):
      [$estTxt, $estCls] = $estados_label[$act['estado']] ?? [$act['estado'], 'secondary'];
      $pct = (float)$act['cumplimiento_porcentaje'];
  ?>
  <div class="col-md-6 col-xl-4">
    <article class="card glass-card h-100 border-0 shadow-sm">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2 gap-2 flex-wrap">
          <div class="d-flex gap-1 flex-wrap">
            <span class="badge bg-soft primary">Ficha <?= e($act['numero_ficha']) ?></span>
            <?php if ($act['fase_nombre']): ?>
              <span class="badge bg-soft info">Fase <?= (int)$act['numero_fase'] ?> · <?= e($act['fase_nombre']) ?></span>
            <?php endif; ?>
          </div>
          <span class="badge-soft <?= e($estCls) ?>"><?= e($estTxt) ?></span>
        </div>
        <h2 class="h6 fw-bold mb-1"><?= e($act['nombre']) ?></h2>
        <?php if ($act['comp_codigo']): ?>
          <small class="text-muted d-block font-monospace mb-2 texto-recortado-2" title="<?= e($act['comp_nombre']) ?>">
            <i class="bi bi-diagram-3 me-1"></i><?= e($act['comp_codigo']) ?> · <?= e($act['comp_nombre']) ?>
          </small>
        <?php endif; ?>
        <p class="text-muted small flex-grow-1"><?= e($act['descripcion'] ?: 'Sin descripción.') ?></p>

        <div class="panel-cifras mb-3">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Inicio</span><span class="fw-semibold"><?= e($fechaCorta($act['fecha_inicio'])) ?></span></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Límite</span>
            <span class="fw-semibold <?= $act['vencida'] ? 'text-danger' : '' ?>"><?= e($fechaCorta($act['fecha_fin'])) ?><?= $act['vencida'] ? ' · vencida' : '' ?></span></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Responsable</span><span class="fw-semibold text-end"><?= e($act['responsable_nombre'] ?: 'Sin asignar') ?></span></div>
          <div class="progress barra-avance" role="progressbar" aria-label="Avance de la actividad"
               aria-valuenow="<?= (int)round($pct) ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-<?= claseAvance($pct) ?>" style="width: <?= (int)round($pct) ?>%"></div>
          </div>
          <div class="text-end fw-bold mt-1 small"><?= (int)round($pct) ?>%</div>
        </div>

        <?php if ($user_rol === ROL_APRENDIZ && !in_array($act['estado'], ['completada', 'cancelada'], true)): ?>
          <a href="<?= e(APP_URL . '/index.php/evidencias') ?>" class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Enviar evidencia</a>
        <?php elseif ($puedeGestionar): ?>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-soft flex-grow-1" data-modal="#modalAvance"
                    data-valores="<?= datosJson(['id' => (int)$act['id'], 'estado' => $act['estado'], 'cumplimiento_porcentaje' => $pct, 'titulo_actividad' => $act['nombre']]) ?>">
              <i class="bi bi-graph-up-arrow me-1"></i>Avance
            </button>
            <button type="button" class="btn btn-sm btn-soft" aria-label="Editar actividad" data-modal="#modalEditar"
                    data-valores="<?= datosJson([
                        'id' => (int)$act['id'], 'ficha_id' => (int)$act['ficha_id'], 'fase_id' => (int)($act['fase_id'] ?? 0),
                        'competencia_id' => (int)($act['competencia_id'] ?? 0), 'nombre' => $act['nombre'],
                        'descripcion' => $act['descripcion'] ?? '', 'fecha_inicio' => $act['fecha_inicio'] ?? '',
                        'fecha_fin' => $act['fecha_fin'] ?? '', 'responsable_id' => (int)($act['responsable_id'] ?? 0),
                        'estado' => $act['estado'], 'cumplimiento_porcentaje' => $pct,
                    ]) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar la actividad «' . $act['nombre'] . '»?') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$act['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Eliminar actividad"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </article>
  </div>
  <?php endforeach; ?>

  <?php if (empty($actividades)): ?>
  <div class="col-12 estado-vacio">
    <i class="bi bi-check2-square"></i>
    <?= $hayFiltros ? 'Ninguna actividad coincide con los filtros.' : 'No hay actividades registradas todavía.' ?>
  </div>
  <?php endif; ?>
</div>

<?php $paginador = $paginacion; $paginacionEtiqueta = 'actividades'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($puedeGestionar): ?>
<!-- Catálogos para los selectores dependientes (ficha → fases y competencias).
     Es JSON, no código: el navegador no lo ejecuta y la CSP no lo bloquea. -->
<script type="application/json" id="datosActividades"><?= jsonParaScript([
    'fichas' => array_map(static fn($f) => ['id' => (int)$f['id'], 'programa' => (int)$f['programa_id'], 'proyecto' => (int)$f['proyecto_id']], $fichas),
    'fases' => array_map(static fn($f) => ['id' => (int)$f['id'], 'proyecto' => (int)$f['proyecto_id'], 'texto' => 'Fase ' . $f['numero_fase'] . ' · ' . $f['nombre']], $fases),
    'competencias' => array_map(static fn($c) => ['id' => (int)$c['id'], 'programa' => (int)$c['programa_id'], 'texto' => $c['codigo'] . ' — ' . $c['nombre']], $competencias),
]) ?></script>

<?php foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" data-form-actividad>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>">
            <i class="bi <?= $accion === 'crear' ? 'bi-check2-square' : 'bi-pencil-square' ?>"></i>
            <?= $accion === 'crear' ? 'Nueva actividad de aprendizaje' : 'Editar actividad' ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_ficha">Ficha <span class="text-danger">*</span></label>
              <select name="ficha_id" id="<?= $accion ?>_ficha" class="form-select" required data-rol="ficha"
                      data-picker data-picker-label="Seleccionar ficha" data-picker-placeholder="Número de ficha...">
                <option value="" disabled selected>Seleccione…</option>
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= (int)$f['id'] ?>" <?= $filtros['ficha_id'] === (int)$f['id'] && $accion === 'crear' ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_fase">Fase del proyecto</label>
              <select name="fase_id" id="<?= $accion ?>_fase" class="form-select" data-rol="fase"
                      data-picker data-picker-label="Fase del proyecto" data-picker-placeholder="Elija primero la ficha">
                <option value="">Elija primero la ficha</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_competencia">Competencia</label>
              <select name="competencia_id" id="<?= $accion ?>_competencia" class="form-select" data-rol="competencia"
                      data-picker data-picker-label="Competencia" data-picker-placeholder="Código o nombre...">
                <option value="">Sin competencia específica</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="<?= $accion ?>_nombre">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="<?= $accion ?>_nombre" class="form-control" required
                   minlength="3" maxlength="<?= (int)$limites['nombre'] ?>" data-filtro="nombre" placeholder="Ej.: Levantamiento de requisitos con el cliente">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="<?= $accion ?>_descripcion">Descripción del entregable</label>
            <textarea name="descripcion" id="<?= $accion ?>_descripcion" class="form-control" rows="3"
                      maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
          </div>
          <div class="row g-3">
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_inicio">Inicio</label>
              <input type="date" name="fecha_inicio" id="<?= $accion ?>_inicio" class="form-control" min="2000-01-01" max="2100-12-31">
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_fin">Fecha límite</label>
              <input type="date" name="fecha_fin" id="<?= $accion ?>_fin" class="form-control" min="2000-01-01" max="2100-12-31">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_responsable">Instructor responsable <span class="text-danger">*</span></label>
              <select name="responsable_id" id="<?= $accion ?>_responsable" class="form-select" required
                      data-picker data-picker-label="Instructor responsable" data-picker-placeholder="Nombre...">
                <?php foreach ($instructores as $inst): ?>
                  <option value="<?= (int)$inst['id'] ?>" <?= (int)$inst['id'] === $user_id ? 'selected' : '' ?>><?= e($inst['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_estado">Estado</label>
              <select name="estado" id="<?= $accion ?>_estado" class="form-select" data-picker data-picker-label="Estado">
                <?php foreach ($estados_label as $valor => [$texto]): ?>
                  <?php if ($accion === 'crear' && in_array($valor, ['completada', 'cancelada'], true)) continue; ?>
                  <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($accion === 'editar'): ?>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="editar_avance">Avance (%)</label>
              <input type="number" name="cumplimiento_porcentaje" id="editar_avance" class="form-control" min="0" max="100" step="1" inputmode="numeric">
            </div>
            <?php endif; ?>
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
<?php endforeach; ?>

<div class="modal fade" id="modalAvance" tabindex="-1" aria-labelledby="tituloAvance" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="avance">
        <input type="hidden" name="id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloAvance"><i class="bi bi-graph-up-arrow"></i>Registrar avance</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-3" data-campo="titulo_actividad"></p>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="avance_estado">Estado</label>
              <select name="estado" id="avance_estado" class="form-select" data-picker data-picker-label="Estado">
                <?php foreach ($estados_label as $valor => [$texto]): ?>
                  <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="avance_pct">Avance (%)</label>
              <input type="number" name="cumplimiento_porcentaje" id="avance_pct" class="form-control" min="0" max="100" step="1" required inputmode="numeric">
            </div>
          </div>
          <p class="text-muted small mt-2 mb-0">Una actividad completada queda al 100 % y una pendiente al 0 %.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar avance</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
