
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Asignaciones de Instructores</h1>
    <p class="text-muted mb-0">Asocia instructores a competencias especÃ­ficas dentro de cada ficha tÃ©cnica.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR)): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsignar">
    <i class="bi bi-person-plus me-1"></i> Nueva AsignaciÃ³n
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 glass-card text-danger" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <ul class="mb-0 ps-3 d-inline-block">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Barra de filtros -->
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar asignaciÃ³n</label>
        <div class="input-group">
          <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ficha, instructor o competencia..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select"
                data-picker
                data-picker-label="Filtrar por ficha"
                data-picker-placeholder="NÃºmero de ficha o programa...">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>
                    data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa_codigo']) ?>">
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> â€” <?= htmlspecialchars($f['programa_codigo']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Instructor</label>
        <select name="instructor_id" class="form-select"
                data-picker
                data-picker-label="Filtrar por instructor"
                data-picker-placeholder="Nombre del instructor...">
          <option value="0">Todos los instructores</option>
          <?php foreach ($instructores as $inst): ?>
            <option value="<?= $inst['id'] ?>" <?= $filter_instructor === (int)$inst['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($inst['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de asignaciones -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Ficha</th>
            <th>Competencia</th>
            <th>Instructor Asignado</th>
            <th>Fecha AsignaciÃ³n</th>
            <?php if (hasRole(ROL_COORDINADOR)): ?>
            <th class="pe-4 text-end">Acciones</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignaciones as $asg): ?>
          <tr>
            <td class="ps-4">
              <span class="badge bg-soft primary font-monospace fs-6">#<?= htmlspecialchars($asg['numero_ficha']) ?></span>
              <div class="text-muted small mt-1"><?= htmlspecialchars($asg['programa_nombre']) ?></div>
            </td>
            <td>
              <div class="fw-bold text-dark font-monospace" style="font-size:0.9rem;"><?= htmlspecialchars($asg['competencia_codigo']) ?></div>
              <small class="text-muted text-wrap d-inline-block" style="max-width:320px;"><?= htmlspecialchars($asg['competencia_nombre']) ?></small>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar" style="width:36px; height:36px; font-size:0.9rem; background:<?= htmlspecialchars($asg['avatar_color']) ?>">
                  <?= strtoupper(substr($asg['instructor_nombre'], 0, 2)) ?>
                </div>
                <div>
                  <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($asg['instructor_nombre']) ?></h6>
                  <small class="text-muted"><?= htmlspecialchars($asg['instructor_email']) ?></small>
                </div>
              </div>
            </td>
            <td class="text-muted small">
              <?= date('d/m/Y h:i A', strtotime($asg['fecha_asignacion'])) ?>
            </td>
            <?php if (hasRole(ROL_COORDINADOR)): ?>
            <td class="pe-4 text-end">
              <button class="btn btn-sm btn-soft text-danger" onclick="confirmarEliminarAsignacion(<?= $asg['id'] ?>, <?= json_encode($asg['instructor_nombre']) ?>, <?= json_encode($asg['competencia_codigo']) ?>)">
                <i class="bi bi-trash me-1"></i> Quitar
              </button>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($asignaciones)): ?>
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="bi bi-person-badge d-block mb-2" style="font-size:2.5rem; opacity:0.4;"></i>
              No se encontraron asignaciones de instructores.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Asignar Instructor -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalAsignar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Asignar Instructor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="asignar">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Ficha de Destino</label>
            <select name="ficha_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar ficha"
                    data-picker-placeholder="NÃºmero de ficha o programa...">
              <option value="" disabled selected>Seleccione Ficha...</option>
              <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id'] ?>"
                        data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa_codigo']) ?>">
                  Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> â€” <?= htmlspecialchars($f['programa_codigo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Competencia Formativa</label>
            <select name="competencia_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar competencia"
                    data-picker-placeholder="CÃ³digo o nombre de la competencia...">
              <option value="" disabled selected>Seleccione Competencia...</option>
              <?php foreach ($competencias as $c): ?>
                <option value="<?= $c['id'] ?>"
                        data-search="<?= htmlspecialchars($c['programa_codigo'] . ' ' . $c['codigo'] . ' ' . $c['nombre']) ?>">
                  <?= htmlspecialchars($c['codigo']) ?> â€” <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Instructor Responsable</label>
            <select name="instructor_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar instructor"
                    data-picker-placeholder="Nombre o email del instructor...">
              <option value="" disabled selected>Seleccione Instructor...</option>
              <?php foreach ($instructores as $inst): ?>
                <option value="<?= $inst['id'] ?>"
                        data-search="<?= htmlspecialchars($inst['email']) ?>">
                  <?= htmlspecialchars($inst['nombre']) ?> â€” <?= htmlspecialchars($inst['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Asignar Instructor</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<form id="formEliminarAsignacion" method="POST" style="display:none;">
  <input type="hidden" name="action" value="eliminar">
  <input type="hidden" name="asignacion_id" id="eliminar_asignacion_id">
</form>

<script>
function confirmarEliminarAsignacion(id, instructor, competencia) {
    if (confirm('Â¿EstÃ¡s seguro de que deseas desvincular al instructor ' + instructor + ' de la competencia ' + competencia + '?')) {
        document.getElementById('eliminar_asignacion_id').value = id;
        document.getElementById('formEliminarAsignacion').submit();
    }
}
</script>
