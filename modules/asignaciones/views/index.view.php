<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
if ($esCoordinador) {
    $scriptsVista[] = 'modulos/asignaciones.js';
}
$hayFiltros = $busqueda !== '' || $fichaId || $instructorId;
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Asignación de Instructores</h1>
    <p class="text-muted mb-0">Quién califica cada competencia en cada ficha. Sin asignación, la califica el instructor líder.</p>
  </div>
  <?php if ($esCoordinador): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsignar"><i class="bi bi-person-plus me-1"></i>Asignar instructor</button>
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
        <label class="form-label text-muted small" for="f_search">Buscar</label>
        <input type="search" name="search" id="f_search" class="form-control" maxlength="100" placeholder="Instructor, competencia o ficha..." value="<?= e($busqueda) ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label text-muted small" for="f_inst">Instructor</label>
        <select name="instructor_id" id="f_inst" class="form-select" data-autoenvio data-picker data-picker-label="Instructor">
          <option value="0">Todos</option>
          <?php foreach ($instructores as $i): ?><option value="<?= (int)$i['id'] ?>" <?= $instructorId === (int)$i['id'] ? 'selected' : '' ?>><?= e($i['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-soft flex-grow-1">Filtrar</button>
        <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= e(APP_URL . '/index.php/asignaciones') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Ficha</th><th>Competencia</th><th>Instructor</th><th class="text-center">Pendientes</th><th>Desde</th><?php if ($esCoordinador): ?><th class="text-end">Acciones</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($asignaciones as $asg): ?>
      <tr>
        <td><strong>Ficha <?= e($asg['numero_ficha']) ?></strong><small class="d-block text-muted"><?= e($asg['programa_nombre']) ?></small></td>
        <td><span class="badge bg-soft info font-monospace"><?= e($asg['competencia_codigo']) ?></span><small class="d-block texto-recortado-2"><?= e($asg['competencia_nombre']) ?></small></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="avatar sm" style="background: <?= e($asg['avatar_color'] ?: '#39A900') ?>"><?= e(getInitials($asg['instructor_nombre'])) ?></div>
            <div><strong class="d-block"><?= e($asg['instructor_nombre']) ?></strong><small class="text-muted"><?= e($asg['instructor_email']) ?></small></div>
          </div>
        </td>
        <td class="text-center"><?= (int)$asg['pendientes'] ?></td>
        <td class="small"><?= e(date('d/m/Y', strtotime((string)$asg['fecha_asignacion']))) ?></td>
        <?php if ($esCoordinador): ?>
        <td class="text-end">
          <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-soft" data-modal="#modalReasignar" aria-label="Cambiar instructor"
                    data-valores="<?= datosJson(['id' => (int)$asg['id'], 'instructor_id' => (int)$asg['instructor_id'],
                        'resumen' => 'Ficha ' . $asg['numero_ficha'] . ' · ' . $asg['competencia_codigo']]) ?>"><i class="bi bi-arrow-left-right"></i></button>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Quitar la asignación? La competencia ' . $asg['competencia_codigo'] . ' volverá al instructor líder de la ficha.') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$asg['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Eliminar asignación"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($asignaciones)): ?>
      <tr><td colspan="6" class="celda-vacia"><div><i class="bi bi-diagram-3 d-block fs-3 opacity-50 mb-2"></i><?= $hayFiltros ? 'Ninguna asignación coincide.' : 'Aún no hay asignaciones: todas las competencias las califica el instructor líder de cada ficha.' ?></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($esCoordinador): ?>
<script type="application/json" id="datosAsignaciones"><?= jsonParaScript([
    'fichas' => array_map(static fn($f) => ['id' => (int)$f['id'], 'programa' => (int)$f['programa_id']], $fichas),
    'competencias' => array_map(static fn($c) => ['id' => (int)$c['id'], 'programa' => (int)$c['programa_id'], 'texto' => $c['codigo'] . ' — ' . $c['nombre']], $competencias),
]) ?></script>

<div class="modal fade" id="modalAsignar" tabindex="-1" aria-labelledby="tituloAsignar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" data-form-asignacion>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="asignar">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloAsignar"><i class="bi bi-person-plus"></i>Asignar instructor a una competencia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="asg_ficha">Ficha <span class="text-danger">*</span></label>
            <select name="ficha_id" id="asg_ficha" class="form-select" required data-rol="ficha" data-picker data-picker-label="Ficha">
              <option value="" disabled selected>Seleccione…</option>
              <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>">Ficha <?= e($f['numero_ficha']) ?> (<?= e($f['programa_codigo']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="asg_comp">Competencia <span class="text-danger">*</span></label>
            <select name="competencia_id" id="asg_comp" class="form-select" required data-rol="competencia" data-picker data-picker-label="Competencia" data-picker-placeholder="Código o nombre...">
              <option value="">Elija primero la ficha</option>
            </select>
            <small class="text-muted">Solo las competencias del programa de la ficha. Las de etapa práctica no se asignan.</small>
          </div>
          <div>
            <label class="form-label small fw-semibold" for="asg_inst">Instructor <span class="text-danger">*</span></label>
            <select name="instructor_id" id="asg_inst" class="form-select" required data-picker data-picker-label="Instructor">
              <option value="" disabled selected>Seleccione…</option>
              <?php foreach ($instructores as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Asignar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalReasignar" tabindex="-1" aria-labelledby="tituloReasignar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reasignar">
        <input type="hidden" name="id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloReasignar"><i class="bi bi-arrow-left-right"></i>Cambiar instructor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold" data-campo="resumen"></p>
          <label class="form-label small fw-semibold" for="re_inst">Nuevo instructor</label>
          <select name="instructor_id" id="re_inst" class="form-select" required data-picker data-picker-label="Instructor">
            <?php foreach ($instructores as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['nombre']) ?></option><?php endforeach; ?>
          </select>
          <p class="small text-muted mt-2 mb-0">Las evaluaciones pendientes pasan al nuevo instructor; los juicios ya emitidos conservan a su autor.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Cambiar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
