<?php
declare(strict_types=1);
?>
<div class="mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="mb-1">Editar Programa de Formación</h1>
    <p class="text-muted mb-0">Modifica los detalles generales del programa.</p>
  </div>
  <div>
    <a href="<?= APP_URL ?>/index.php/estructura" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
  </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-flat <?= $tipo_mensaje ?> mb-4 alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i>
  <div>
    <?= htmlspecialchars($mensaje) ?>
    <br><a href="<?= APP_URL ?>/index.php/estructura" class="alert-link">Volver a la estructura curricular →</a>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-flat danger mb-4 alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <div>
    <?php foreach ($errors as $error): ?>
    <div><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($programa): ?>
<div class="row">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="POST">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label fw-bold">Nombre del Programa</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Tecnólogo en Análisis y Desarrollo de Software" value="<?= htmlspecialchars($programa['nombre']) ?>" required maxlength="200">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Código del Programa</label>
            <input type="text" name="codigo" class="form-control" placeholder="Ej: 228118" value="<?= htmlspecialchars($programa['codigo']) ?>" required maxlength="50">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Duración total (Horas)</label>
            <input type="number" name="duracion_horas" class="form-control" min="1" max="99999" placeholder="Ej: 3984" value="<?= htmlspecialchars((string)$programa['duracion_horas']) ?>" required>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold">Estado del Programa</label>
            <select name="estado" class="form-select" required>
              <option value="activo" <?= $programa['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
              <option value="inactivo" <?= $programa['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Guardar Cambios</button>
            <a href="<?= APP_URL ?>/index.php/estructura" class="btn btn-soft">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Información sobre Programas</h5>
        <p class="text-muted" style="font-size: 0.92rem; line-height: 1.6;">
          Los programas de formación representan las estructuras curriculares dictadas en el SENA. Al actualizar el código o el nombre:
        </p>
        <ul class="text-muted" style="font-size: 0.92rem; line-height: 1.8; padding-left: 1.25rem;">
          <li>Las competencias y los resultados de aprendizaje (RAs) ya asociados al programa se mantendrán vinculados sin cambios.</li>
          <li>Las fichas asociadas a este programa verán el nombre actualizado de forma inmediata.</li>
          <li>Si el programa se desactiva (**Inactivo**), no estará disponible para crear nuevas fichas de formación en el sistema.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
