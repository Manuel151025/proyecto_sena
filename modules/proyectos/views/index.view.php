<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}

$esCoordinador = $user_rol === ROL_COORDINADOR;
$estados = ['activo' => ['Activo', 'success'], 'inactivo' => ['Inactivo', 'secondary'], 'finalizado' => ['Finalizado', 'info']];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Proyectos Formativos</h1>
    <p class="text-muted mb-0">
      <?= $user_rol === ROL_APRENDIZ
          ? 'El proyecto que desarrolla tu ficha y cómo avanza.'
          : 'Cada proyecto integra las competencias de un programa en fases y actividades.' ?>
    </p>
  </div>
  <?php if ($esCoordinador): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="bi bi-plus-lg me-1"></i>Nuevo proyecto
    </button>
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="row g-4">
  <?php foreach ($proyectos as $proj):
      $avance = $proj['avance'] !== null ? (float)$proj['avance'] : null;
      [$estadoTxt, $estadoCls] = $estados[$proj['estado']] ?? [$proj['estado'], 'secondary'];
  ?>
    <div class="col-md-6 col-lg-4">
      <article class="card glass-card h-100 border-0 shadow-sm tarjeta-elevable tarjeta-acento">
        <div class="card-body d-flex flex-column p-4">
          <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
            <span class="badge bg-soft primary fw-semibold text-uppercase-visual"><?= e($proj['codigo']) ?></span>
            <span class="badge-soft <?= e($estadoCls) ?>"><?= e($estadoTxt) ?></span>
          </div>
          <h2 class="h5 fw-bold mb-1 text-uppercase-visual"><?= e($proj['nombre']) ?></h2>
          <p class="text-muted small mb-3 texto-recortado-2"><?= e($proj['objetivo'] ?: 'Sin objetivo definido.') ?></p>

          <div class="panel-cifras mb-3 flex-grow-1">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-folder2-open me-1"></i>Fichas</span>
              <span class="fw-bold"><?= (int)$proj['total_fichas'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-people me-1"></i>Aprendices</span>
              <span class="fw-bold"><?= (int)$proj['total_aprendices'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-list-task me-1"></i>Fases completadas</span>
              <span class="fw-bold"><?= (int)$proj['fases_completadas'] ?> / <?= (int)$proj['total_fases'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted"><i class="bi bi-check2-square me-1"></i>Actividades</span>
              <span class="fw-bold"><?= (int)$proj['total_actividades'] ?></span>
            </div>

            <div class="text-muted small mb-1">Avance según actividades</div>
            <?php if ($avance === null): ?>
              <div class="small text-muted fst-italic">Sin actividades registradas todavía.</div>
            <?php else: ?>
              <div class="progress barra-avance" role="progressbar" aria-label="Avance del proyecto"
                   aria-valuenow="<?= (int)round($avance) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-<?= claseAvance($avance) ?>" style="width: <?= (int)round($avance) ?>%"></div>
              </div>
              <div class="text-end fw-bold mt-1 small"><?= (int)round($avance) ?>%</div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <a href="<?= e(APP_URL . '/index.php/fases?proyecto_id=' . (int)$proj['id']) ?>" class="btn btn-primary flex-grow-1">
              <i class="bi bi-list-task me-1"></i>Fases
            </a>
            <a href="<?= e(APP_URL . '/index.php/actividades?proyecto_id=' . (int)$proj['id']) ?>" class="btn btn-soft flex-grow-1">
              <i class="bi bi-check2-square me-1"></i>Actividades
            </a>
            <?php if ($esCoordinador): ?>
              <button type="button" class="btn btn-soft px-3" aria-label="Editar proyecto"
                      data-modal="#modalEditar"
                      data-valores="<?= datosJson([
                          'id' => (int)$proj['id'], 'nombre' => $proj['nombre'], 'codigo' => $proj['codigo'],
                          'objetivo' => $proj['objetivo'] ?? '', 'descripcion' => $proj['descripcion'] ?? '',
                          'estado' => $proj['estado'],
                      ]) ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar el proyecto ' . $proj['nombre'] . '?') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$proj['id'] ?>">
                <button type="submit" class="btn btn-soft text-danger px-3" aria-label="Eliminar proyecto">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>

  <?php if (empty($proyectos)): ?>
    <div class="col-12 estado-vacio">
      <i class="bi bi-kanban"></i>
      <?= $user_rol === ROL_APRENDIZ
          ? 'Tu ficha todavía no tiene un proyecto formativo asignado.'
          : 'No hay proyectos formativos para mostrar.' ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($esCoordinador):
    // Un solo marcado para crear y editar: solo cambian la acción, el
    // título y si se muestra el estado.
    foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>">
            <i class="bi <?= $accion === 'crear' ? 'bi-kanban' : 'bi-pencil' ?>"></i>
            <?= $accion === 'crear' ? 'Nuevo proyecto formativo' : 'Editar proyecto formativo' ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_nombre">Nombre del proyecto <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="<?= $accion ?>_nombre" class="form-control" required
                   minlength="3" maxlength="<?= (int)$limites['nombre'] ?>" data-filtro="nombre"
                   placeholder="Ej.: Sistema de gestión de inventarios web">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_codigo">Código <span class="text-danger">*</span></label>
            <input type="text" name="codigo" id="<?= $accion ?>_codigo" class="form-control" required
                   minlength="2" maxlength="<?= (int)$limites['codigo'] ?>" data-filtro="codigo-punto"
                   autocapitalize="characters" placeholder="Ej.: PF-ADSO-02">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_objetivo">Objetivo</label>
            <textarea name="objetivo" id="<?= $accion ?>_objetivo" class="form-control" rows="2"
                      maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
          </div>
          <div class="<?= $accion === 'editar' ? 'mb-3' : 'mb-0' ?>">
            <label class="form-label fw-semibold" for="<?= $accion ?>_descripcion">Descripción</label>
            <textarea name="descripcion" id="<?= $accion ?>_descripcion" class="form-control" rows="2"
                      maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
          </div>
          <?php if ($accion === 'editar'): ?>
          <div class="mb-0">
            <label class="form-label fw-semibold" for="editar_estado">Estado</label>
            <select name="estado" id="editar_estado" class="form-select" data-picker data-picker-label="Estado del proyecto">
              <?php foreach ($estados as $valor => [$texto]): ?>
                <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
