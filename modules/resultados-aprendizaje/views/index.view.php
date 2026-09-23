<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$totalRap = array_sum(array_map(static fn($c) => count($c['raps']), $competencias));
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Resultados de Aprendizaje (RAP)</h1>
    <p class="text-muted mb-0">Los RAP de cada competencia son las unidades sobre las que se emite el juicio A / D.</p>
  </div>
  <?php if ($puedeEditar): ?>
  <div class="d-flex gap-2">
    <a href="<?= e(APP_URL . '/index.php/resultados-aprendizaje/importar') ?>" class="btn btn-soft"><i class="bi bi-upload me-1"></i>Importar</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-plus-lg me-1"></i>Nuevo RAP</button>
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label text-muted small" for="f_programa">Programa</label>
        <select name="programa_id" id="f_programa" class="form-select" data-autoenvio data-picker data-picker-label="Programa">
          <option value="0">Todos los programas</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $programaId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['codigo'] . ' — ' . $p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label text-muted small" for="f_search">Buscar competencia o RAP</label>
        <input type="search" name="search" id="f_search" class="form-control" maxlength="100" value="<?= e($busqueda) ?>" placeholder="Código o texto...">
      </div>
      <div class="col-md-2 d-grid"><button type="submit" class="btn btn-soft">Buscar</button></div>
    </form>
    <p class="small text-muted mt-2 mb-0"><?= count($competencias) ?> competencias · <?= $totalRap ?> resultados de aprendizaje</p>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($competencias as $comp): ?>
    <div class="col-lg-6">
      <article class="card glass-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
            <span class="badge bg-soft info font-monospace"><?= e($comp['codigo']) ?></span>
            <small class="text-muted text-end"><?= e($comp['programa']) ?></small>
          </div>
          <h2 class="h6 fw-bold mb-2 text-uppercase-visual"><?= e($comp['nombre']) ?></h2>
          <?php if ((int)$comp['es_etapa_practica'] === 1): ?><span class="badge-soft warning mb-2 d-inline-block">Etapa práctica</span><?php endif; ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($comp['raps'] as $rap): ?>
              <li class="d-flex gap-2 align-items-start py-1 border-bottom">
                <span class="badge bg-success flex-shrink-0 font-monospace"><?= e($rap['codigo']) ?></span>
                <span class="flex-grow-1"><?= e($rap['denominacion']) ?>
                  <?php if ((int)$rap['juicios'] > 0): ?><span class="text-muted">· <?= (int)$rap['juicios'] ?> juicios</span><?php endif; ?></span>
                <?php if ($puedeEditar): ?>
                <div class="d-flex gap-1 flex-shrink-0">
                  <button type="button" class="btn btn-sm btn-soft py-0 px-1" aria-label="Editar RAP" data-modal="#modalEditar"
                          data-valores="<?= datosJson(['id' => (int)$rap['id'], 'competencia_id' => (int)$comp['id'], 'codigo' => $rap['codigo'], 'denominacion' => $rap['denominacion']]) ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?php if ((int)$rap['juicios'] === 0): ?>
                  <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar el RAP ' . $rap['codigo'] . '? Se quitarán también sus evaluaciones pendientes.') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$rap['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-soft py-0 px-1 text-danger" aria-label="Eliminar RAP"><i class="bi bi-trash"></i></button>
                  </form>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
            <?php if (empty($comp['raps'])): ?>
              <li class="text-muted py-1"><i class="bi bi-info-circle me-1"></i>Sin resultados de aprendizaje todavía.</li>
            <?php endif; ?>
          </ul>
        </div>
      </article>
    </div>
  <?php endforeach; ?>
  <?php if (empty($competencias)): ?>
    <div class="col-12 estado-vacio"><i class="bi bi-clipboard-check"></i>No hay competencias que coincidan.</div>
  <?php endif; ?>
</div>

<?php if ($puedeEditar):
    foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>"><i class="bi <?= $accion === 'crear' ? 'bi-clipboard-plus' : 'bi-pencil-square' ?>"></i>
            <?= $accion === 'crear' ? 'Nuevo resultado de aprendizaje' : 'Editar resultado de aprendizaje' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="<?= $accion ?>_competencia">Competencia <span class="text-danger">*</span></label>
            <select name="competencia_id" id="<?= $accion ?>_competencia" class="form-select" required data-picker data-picker-label="Competencia" data-picker-placeholder="Código o nombre...">
              <option value="" disabled selected>Seleccione…</option>
              <?php foreach ($opciones as $o): ?>
                <option value="<?= (int)$o['id'] ?>" data-search="<?= e($o['codigo'] . ' ' . $o['nombre'] . ' ' . $o['programa_codigo']) ?>"><?= e($o['programa_codigo'] . ' · ' . $o['codigo'] . ' — ' . $o['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="<?= $accion ?>_codigo">Código <span class="text-danger">*</span></label>
            <input type="text" name="codigo" id="<?= $accion ?>_codigo" class="form-control" required minlength="2" maxlength="<?= (int)$limites['codigo'] ?>" data-filtro="codigo-punto" placeholder="Ej.: 220501094-01">
          </div>
          <div>
            <label class="form-label small fw-semibold" for="<?= $accion ?>_denominacion">Denominación <span class="text-danger">*</span></label>
            <textarea name="denominacion" id="<?= $accion ?>_denominacion" class="form-control text-uppercase" rows="3" required minlength="5" maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
          </div>
          <?php if ($accion === 'crear'): ?><p class="small text-muted mt-2 mb-0">Al guardarlo, los aprendices ya matriculados en el programa reciben su evaluación pendiente.</p><?php endif; ?>
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
