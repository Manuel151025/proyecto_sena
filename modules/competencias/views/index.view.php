<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$hayFiltros = $filtros['search'] !== '' || $filtros['programa_id'] || $filtros['estado'] !== '';
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Competencias de Formación</h1>
    <p class="text-muted mb-0">Competencias de cada programa. Las de etapa práctica las califica el instructor de seguimiento.</p>
  </div>
  <?php if ($puedeEditar): ?>
  <div class="d-flex gap-2">
    <a href="<?= e(APP_URL . '/index.php/competencias/importar') ?>" class="btn btn-soft"><i class="bi bi-upload me-1"></i>Importar</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-plus-lg me-1"></i>Nueva competencia</button>
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small" for="f_search">Buscar</label>
        <input type="search" name="search" id="f_search" class="form-control" maxlength="100" placeholder="Nombre o código..." value="<?= e($filtros['search']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label text-muted small" for="f_programa">Programa</label>
        <select name="programa_id" id="f_programa" class="form-select" data-autoenvio data-picker data-picker-label="Programa">
          <option value="0">Todos los programas</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $filtros['programa_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['codigo'] . ' — ' . $p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label text-muted small" for="f_estado">Estado</label>
        <select name="estado" id="f_estado" class="form-select" data-autoenvio data-picker data-picker-label="Estado">
          <option value="">Todos</option>
          <option value="activo" <?= $filtros['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
          <option value="inactivo" <?= $filtros['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-soft flex-grow-1">Filtrar</button>
        <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= e(APP_URL . '/index.php/competencias') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table class="table mb-0 align-middle">
    <thead>
      <tr><th>Código</th><th>Competencia</th><th>Programa</th><th class="text-center">Horas</th><th class="text-center">RAP</th><th>Estado</th><?php if ($puedeEditar): ?><th class="text-end">Acciones</th><?php endif; ?></tr>
    </thead>
    <tbody>
      <?php foreach ($competencias as $c): ?>
      <tr>
        <td class="font-monospace fw-bold text-uppercase-visual"><strong><?= e($c['codigo']) ?></strong></td>
        <td>
          <div class="fw-semibold text-uppercase-visual"><?= e($c['nombre']) ?></div>
          <?php if ((int)$c['es_etapa_practica'] === 1): ?><span class="badge-soft warning">Etapa práctica</span><?php endif; ?>
          <?php if (!empty($c['descripcion'])): ?><small class="text-muted d-block texto-recortado-2"><?= e($c['descripcion']) ?></small><?php endif; ?>
        </td>
        <td><span class="badge bg-soft info"><?= e($c['programa_codigo']) ?></span> <small class="text-muted"><?= e($c['programa_nombre']) ?></small></td>
        <td class="text-center"><?= (int)$c['horas'] ?></td>
        <td class="text-center"><a href="<?= e(APP_URL . '/index.php/resultados-aprendizaje?programa_id=' . (int)$c['programa_id'] . '&search=' . rawurlencode((string)$c['codigo'])) ?>"><?= (int)$c['total_rap'] ?></a></td>
        <td><span class="badge-soft <?= $c['estado'] === 'activo' ? 'success' : 'secondary' ?>"><?= e(ucfirst($c['estado'])) ?></span></td>
        <?php if ($puedeEditar): ?>
        <td class="text-end">
          <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-soft" aria-label="Editar competencia" data-modal="#modalEditar"
                    data-valores="<?= datosJson(['id' => (int)$c['id'], 'programa_id' => (int)$c['programa_id'], 'codigo' => $c['codigo'],
                        'nombre' => $c['nombre'], 'descripcion' => $c['descripcion'] ?? '', 'horas' => (int)$c['horas'],
                        'estado' => $c['estado'], 'es_etapa_practica' => (int)$c['es_etapa_practica']]) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar la competencia ' . $c['codigo'] . '?') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Eliminar competencia"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($competencias)): ?>
      <tr><td colspan="7" class="text-center py-5 text-muted"><?= $hayFiltros ? 'Ninguna competencia coincide con los filtros.' : 'No hay competencias registradas.' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'competencias'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($puedeEditar):
    foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>"><i class="bi <?= $accion === 'crear' ? 'bi-diagram-3' : 'bi-pencil' ?>"></i>
            <?= $accion === 'crear' ? 'Nueva competencia' : 'Editar competencia' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_programa">Programa <span class="text-danger">*</span></label>
              <select name="programa_id" id="<?= $accion ?>_programa" class="form-select" required data-picker data-picker-label="Programa">
                <option value="" disabled selected>Seleccione…</option>
                <?php foreach ($programas as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" data-search="<?= e($p['codigo'] . ' ' . $p['nombre']) ?>"><?= e($p['codigo'] . ' — ' . $p['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_codigo">Código <span class="text-danger">*</span></label>
              <input type="text" name="codigo" id="<?= $accion ?>_codigo" class="form-control" required minlength="2" maxlength="<?= (int)$limites['codigo'] ?>" data-filtro="codigo-punto">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_nombre">Nombre <span class="text-danger">*</span></label>
              <input type="text" name="nombre" id="<?= $accion ?>_nombre" class="form-control text-uppercase" required minlength="5" maxlength="<?= (int)$limites['nombre'] ?>" data-filtro="sin-html">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_descripcion">Descripción</label>
              <textarea name="descripcion" id="<?= $accion ?>_descripcion" class="form-control" rows="2" maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
            </div>
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_horas">Horas <span class="text-danger">*</span></label>
              <input type="number" name="horas" id="<?= $accion ?>_horas" class="form-control" required min="1" max="<?= (int)$limites['horas'] ?>" step="1" inputmode="numeric">
            </div>
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="<?= $accion ?>_estado">Estado</label>
              <select name="estado" id="<?= $accion ?>_estado" class="form-select" data-picker data-picker-label="Estado">
                <option value="activo">Activo</option><option value="inactivo">Inactivo</option>
              </select>
            </div>
            <div class="col-sm-4 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="es_etapa_practica" value="1" id="<?= $accion ?>_etapa">
                <label class="form-check-label small" for="<?= $accion ?>_etapa">Etapa práctica</label>
              </div>
            </div>
          </div>
          <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>En una competencia de etapa práctica califica el instructor de seguimiento de cada aprendiz, no el líder de la ficha.</p>
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
