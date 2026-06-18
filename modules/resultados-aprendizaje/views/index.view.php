<?php
declare(strict_types=1);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Resultados de Aprendizaje (RAP)</h1>
    <p class="text-muted mb-0">Listado y gestión de RAPs asociados a las competencias de cada programa formativo.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/index.php/resultados-aprendizaje/importar" class="btn btn-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar Masivo
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearRAP">
      <i class="bi bi-plus-lg me-1"></i> Nuevo RAP
    </button>
  </div>
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

<div class="row g-3">
  <?php foreach ($competencias as $comp): ?>
    <div class="col-md-6">
      <div class="card glass-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft info font-monospace"><?= htmlspecialchars($comp['codigo']) ?></span>
            <small class="text-muted"><?= htmlspecialchars($comp['programa']) ?></small>
          </div>
          <h5 class="fw-bold text-dark mb-3"><?= htmlspecialchars($comp['nombre']) ?></h5>
          
          <h6 class="text-muted small fw-bold mb-2">Resultados de Aprendizaje (RAP) Vinculados:</h6>
          <ul class="list-group list-group-flush small" style="background:transparent;">
            <?php foreach ($comp['raps'] as $rap): ?>
              <li class="list-group-item d-flex gap-2 align-items-start ps-0 border-0" style="background:transparent;">
                <span class="badge bg-success flex-shrink-0"><?= htmlspecialchars($rap['codigo']) ?></span>
                <span class="text-dark flex-grow-1"><?= htmlspecialchars($rap['denominacion']) ?></span>
                <?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
                <div class="d-flex gap-1 flex-shrink-0">
                  <button class="btn btn-sm btn-soft py-0 px-1" style="font-size:.75rem;"
                    onclick="abrirModalEditarRAP(
                      <?= $rap['id'] ?>, <?= $comp['id'] ?>,
                      <?= json_encode($rap['codigo']) ?>,
                      <?= json_encode($rap['denominacion']) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('¿Eliminar este RAP? Esta acción no se puede deshacer.')">
                    <input type="hidden" name="action" value="eliminar_rap">
                    <input type="hidden" name="id" value="<?= $rap['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-soft py-0 px-1 text-danger" style="font-size:.75rem;">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
            <?php if (empty($comp['raps'])): ?>
              <li class="list-group-item ps-0 border-0 text-muted small" style="background:transparent;">
                <i class="bi bi-info-circle me-1"></i>No hay RAPs vinculados a esta competencia todavía.
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($competencias)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-clipboard-check d-block mb-2" style="font-size:3rem; opacity:0.3;"></i>
      No hay competencias registradas en el sistema.
    </div>
  <?php endif; ?>
</div>

<!-- Modal Editar RAP -->
<?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
<div class="modal fade" id="modalEditarRAP" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Editar Resultado de Aprendizaje (RAP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="editar_rap">
        <input type="hidden" name="id" id="edit_rap_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Competencia Asociada</label>
            <select name="competencia_id" id="edit_rap_competencia_id" class="form-select" required>
              <?php foreach ($competencias as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Código del RAP</label>
            <input type="text" name="codigo" id="edit_rap_codigo" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Denominación del Resultado de Aprendizaje</label>
            <textarea name="denominacion" id="edit_rap_denominacion" class="form-control" rows="4" required></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalEditarRAP(id, competenciaId, codigo, denominacion) {
    document.getElementById('edit_rap_id').value             = id;
    document.getElementById('edit_rap_competencia_id').value = competenciaId;
    document.getElementById('edit_rap_codigo').value         = codigo;
    document.getElementById('edit_rap_denominacion').value   = denominacion;
    new bootstrap.Modal(document.getElementById('modalEditarRAP')).show();
}
</script>
<?php endif; ?>

<!-- Modal Registrar RAP -->
<?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
<div class="modal fade" id="modalCrearRAP" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Nuevo Resultado de Aprendizaje (RAP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="crear_rap">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Competencia Asociada</label>
            <select name="competencia_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar competencia"
                    data-picker-placeholder="Código o nombre de la competencia...">
              <option value="" disabled selected>Seleccione Competencia...</option>
              <?php foreach ($competencias as $c): ?>
                <option value="<?= $c['id'] ?>"
                        data-search="<?= htmlspecialchars($c['codigo'] . ' ' . $c['nombre']) ?>">
                  <?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Código del RAP (ej: RAP 1, RAP-02)</label>
            <input type="text" name="codigo" class="form-control" placeholder="Ej. RAP 1" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Denominación del Resultado de Aprendizaje</label>
            <textarea name="denominacion" class="form-control" rows="4" placeholder="Describa el resultado de aprendizaje..." required></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar RAP</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
