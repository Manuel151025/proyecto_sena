<div class="mb-4">
  <h1 class="mb-1">ConfiguraciÃ³n General</h1>
  <p class="text-muted mb-0">Ajusta los parÃ¡metros acadÃ©micos, nombres institucionales y credenciales del sistema.</p>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <h5 class="fw-bold text-dark mb-4"><i class="bi bi-gear-fill me-2 text-primary"></i>ParÃ¡metros Institucionales</h5>
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Nombre del Sistema</label>
            <input type="text" name="system_title" class="form-control" value="<?= htmlspecialchars($system_title) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Regional / Centro de FormaciÃ³n</label>
            <input type="text" name="regional" class="form-control" value="<?= htmlspecialchars($regional) ?>" required>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Porcentaje MÃ­nimo AprobaciÃ³n</label>
              <select name="pass_score" class="form-select">
                <option value="60%" <?= $pass_score === '60%' ? 'selected' : '' ?>>60%</option>
                <option value="70%" <?= $pass_score === '70%' ? 'selected' : '' ?>>70% (Por defecto)</option>
                <option value="80%" <?= $pass_score === '80%' ? 'selected' : '' ?>>80%</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Servidor SMTP de Correo Institucional</label>
              <input type="text" name="smtp_server" class="form-control" value="<?= htmlspecialchars($smtp_server) ?>" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Cambios</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card bg-light border-0">
      <div class="card-body">
        <h5><i class="bi bi-info-circle-fill text-primary me-2"></i>InformaciÃ³n TÃ©cnica</h5>
        <ul class="text-muted small ps-3 mb-0 mt-3">
          <li class="mb-2"><strong>VersiÃ³n del Sistema:</strong> 1.5.0-premium</li>
          <li class="mb-2"><strong>Motor de BD:</strong> MySQL 8.0 (PDO utf8mb4)</li>
          <li class="mb-2"><strong>LÃ­mite de subida:</strong> 15MB por evidencia</li>
          <li class="mb-2"><strong>Zona Horaria:</strong> America/Bogota</li>
        </ul>
      </div>
    </div>
  </div>
</div>
