<?php
declare(strict_types=1);
?>
<div class="mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="mb-1">Editar Proyecto Formativo</h1>
    <p class="text-muted mb-0">Modifica los detalles generales del proyecto del programa.</p>
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

<?php if ($proyecto): ?>
<div class="row">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="POST">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label fw-bold">Nombre del Proyecto</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Implementación de un modelo integral..." value="<?= htmlspecialchars($proyecto['nombre']) ?>" required maxlength="255">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Código del Proyecto</label>
            <input type="text" name="codigo" class="form-control" placeholder="Ej: 3240214" value="<?= htmlspecialchars($proyecto['codigo']) ?>" required maxlength="50">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Objetivo General</label>
            <textarea name="objetivo" class="form-control" rows="4" placeholder="Describe el objetivo general del proyecto..." maxlength="5000"><?= htmlspecialchars($proyecto['objetivo'] ?? '') ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Descripción / Justificación</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="Descripción adicional del proyecto..." maxlength="5000"><?= htmlspecialchars($proyecto['descripcion'] ?? '') ?></textarea>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold">Estado del Proyecto</label>
            <select name="estado" class="form-select" required>
              <option value="activo" <?= $proyecto['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
              <option value="inactivo" <?= $proyecto['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
              <option value="finalizado" <?= $proyecto['estado'] === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
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
        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Información sobre Proyectos</h5>
        <p class="text-muted" style="font-size: 0.92rem; line-height: 1.6;">
          Los proyectos formativos estructuran el avance práctico de las competencias en el SENA. Al actualizar el código o la información del proyecto:
        </p>
        <ul class="text-muted" style="font-size: 0.92rem; line-height: 1.8; padding-left: 1.25rem;">
          <li>Las fases asociadas al proyecto (Análisis, Planeación, Ejecución, Evaluación) seguirán vigentes de forma normal.</li>
          <li>Las fichas asociadas a este proyecto verán reflejados los cambios de inmediato.</li>
          <li>Si cambias el estado a **Finalizado**, representará que el proyecto formativo ha completado su ejecución grupal.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
