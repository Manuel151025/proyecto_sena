<?php
declare(strict_types=1);
?>
<div class="mb-3">
  <h1><?= $esEdicion ? 'Editar' : 'Crear nuevo' ?> Programa</h1>
  <p class="text-muted mb-0">Completa el formulario para <?= $esEdicion ? 'actualizar' : 'registrar' ?> un programa de formación.</p>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-flat <?= $tipo_mensaje ?> mb-3 alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle me-2"></i>
  <div>
    <?= htmlspecialchars($mensaje) ?>
    <br><a href="<?= APP_URL ?>/index.php/programas">Volver a la lista →</a>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errores)): ?>
<div class="alert alert-flat danger mb-3 alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-circle me-2"></i>
  <div>
    <?php foreach ($errores as $error): ?>
    <div><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Nombre del Programa</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Análisis y Desarrollo de Software" value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>" maxlength="100" minlength="3" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]/g, '')" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Código</label>
              <input type="text" name="codigo" class="form-control" placeholder="Ej: ADSO" value="<?= htmlspecialchars($valores['codigo'] ?? '') ?>" maxlength="20" minlength="2" pattern="^[a-zA-Z0-9\-]+$" oninput="this.value = this.value.replace(/[^a-zA-Z0-9\-]/g, '').toUpperCase()" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Duración (horas)</label>
              <input type="number" name="duracion_horas" class="form-control" placeholder="Ej: 2880" value="<?= htmlspecialchars((string)($valores['duracion_horas'] ?? '')) ?>" min="1" max="99999" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="4" placeholder="Describe el programa de formación..." maxlength="1000" oninput="this.value = this.value.replace(/[<>]/g, '')"><?= htmlspecialchars($valores['descripcion'] ?? '') ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <option value="activo" <?= ($valores['estado'] ?? 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option>
              <option value="inactivo" <?= ($valores['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
              <option value="archivado" <?= ($valores['estado'] ?? '') === 'archivado' ? 'selected' : '' ?>>Archivado</option>
            </select>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-lg me-1"></i><?= $esEdicion ? 'Actualizar' : 'Crear' ?> Programa
            </button>
            <a href="<?= APP_URL ?>/index.php/programas" class="btn btn-soft">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h5>ℹ️ Información importante</h5>
        <ul style="font-size: 0.9rem; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
          <li><strong>Código único:</strong> Identificador corto del programa (ej: ADSO, MM, CONT)</li>
          <li><strong>Duración:</strong> Horas totales de formación del programa</li>
          <li><strong>Estado:</strong>
            <ul>
              <li><strong>Activo:</strong> Disponible para crear fichas</li>
              <li><strong>Inactivo:</strong> No disponible temporalmente</li>
              <li><strong>Archivado:</strong> Cerrado permanentemente</li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
