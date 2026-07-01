<div class="mb-3">
  <h1><?= $id && $ficha ? 'Editar Ficha' : 'Crear Nueva Ficha' ?></h1>
  <p class="text-muted mb-0"><?= $id && $ficha ? 'Modifica los datos de la ficha' : 'Completa el formulario para registrar una nueva ficha de formaciÃ³n' ?>.</p>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= MODULES_PATH ?>/fichas/">Volver a fichas â†’</a>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3">
  <i class="bi bi-exclamation-circle"></i>
  <div>
    <?php foreach ($errors as $error): ?>
    <div><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!($id > 0 && !$ficha)): ?>
<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">NÃºmero de Ficha</label>
            <input type="text" name="numero_ficha" class="form-control" placeholder="Ej: 2845671" value="<?= htmlspecialchars($ficha['numero_ficha'] ?? $_POST['numero_ficha'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Proyecto Formativo <span class="text-muted small">(opcional)</span></label>
            <select name="proyecto_id" class="form-select">
              <option value="">-- Sin proyecto asignado --</option>
              <?php foreach ($proyectos as $proy): ?>
              <option value="<?= $proy['id'] ?>" <?= ($ficha['proyecto_id'] ?? $_POST['proyecto_id'] ?? 0) == $proy['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($proy['codigo'] . ' â€” ' . $proy['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Programa Formativo</label>
            <select name="programa_id" class="form-select" required>
              <option value="">-- Selecciona un programa --</option>
              <?php foreach ($programas as $prog): ?>
              <option value="<?= $prog['id'] ?>" <?= ($ficha['programa_id'] ?? $_POST['programa_id'] ?? 0) == $prog['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($prog['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Instructor Responsable</label>
            <select name="instructor_id" class="form-select" required>
              <option value="">-- Selecciona un instructor --</option>
              <?php foreach ($instructores as $inst): ?>
              <option value="<?= $inst['id'] ?>" <?= ($ficha['instructor_id'] ?? $_POST['instructor_id'] ?? 0) == $inst['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($inst['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" required>
              <option value="planeacion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'planeacion' ? 'selected' : '' ?>>PlaneaciÃ³n</option>
              <option value="induccion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'induccion' ? 'selected' : '' ?>>InducciÃ³n</option>
              <option value="ejecucion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'ejecucion' ? 'selected' : '' ?>>EjecuciÃ³n</option>
              <option value="cierre" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'cierre' ? 'selected' : '' ?>>Cierre</option>
            </select>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Cantidad de Aprendices</label>
              <input type="number" name="cantidad_aprendices" class="form-control" min="0" value="<?= htmlspecialchars($ficha['cantidad_aprendices'] ?? $_POST['cantidad_aprendices'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Cumplimiento (%)</label>
              <input type="number" name="cumplimiento_porcentaje" class="form-control" min="0" max="100" step="0.1" value="<?= htmlspecialchars($ficha['cumplimiento_porcentaje'] ?? $_POST['cumplimiento_porcentaje'] ?? '') ?>">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Fecha de Inicio</label>
              <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($ficha['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Fecha de Fin</label>
              <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($ficha['fecha_fin'] ?? $_POST['fecha_fin'] ?? '') ?>">
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $id && $ficha ? 'Guardar Cambios' : 'Crear Ficha' ?></button>
            <a href="<?= MODULES_PATH ?>/fichas/" class="btn btn-soft">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h5>â„¹ï¸ InformaciÃ³n importante</h5>
        <ul style="font-size: 0.9rem; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
          <li><strong>NÃºmero Ãºnico:</strong> Cada ficha debe tener un nÃºmero Ãºnico en el sistema.</li>
          <li><strong>Estados:</strong>
            <ul>
              <li><strong>PlaneaciÃ³n:</strong> Fase inicial de preparaciÃ³n</li>
              <li><strong>InducciÃ³n:</strong> PresentaciÃ³n del programa</li>
              <li><strong>EjecuciÃ³n:</strong> Desarrollo del programa</li>
              <li><strong>Cierre:</strong> FinalizaciÃ³n del programa</li>
            </ul>
          </li>
          <li><strong>Cumplimiento:</strong> Porcentaje de avance del programa (0-100%).</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
