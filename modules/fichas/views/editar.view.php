<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos: el resultado eran
// avisos de PHP con rutas del servidor, y fragmentos de la pagina.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
?>
<div class="mb-3">
  <h1><?= $id && $ficha ? 'Editar Ficha' : 'Crear Nueva Ficha' ?></h1>
  <p class="text-muted mb-0"><?= $id && $ficha ? 'Modifica los datos de la ficha' : 'Completa el formulario para registrar una nueva ficha de formación' ?>.</p>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= APP_URL ?>/index.php/fichas">Volver a fichas →</a>
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
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Número de Ficha</label>
            <input type="text" name="numero_ficha" class="form-control" placeholder="Ej: 2845671" value="<?= htmlspecialchars($ficha['numero_ficha'] ?? $_POST['numero_ficha'] ?? '') ?>" maxlength="20" minlength="4" pattern="^[a-zA-Z0-9\-]+$" oninput="this.value=this.value.replace(/[^a-zA-Z0-9\-]/g, '')" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Proyecto Formativo <span class="text-muted small">(opcional)</span></label>
            <select name="proyecto_id" class="form-select"
                    data-picker
                    data-picker-label="Proyecto formativo"
                    data-picker-placeholder="Código o nombre del proyecto...">
              <option value="">-- Sin proyecto asignado --</option>
              <?php foreach ($proyectos as $proy): ?>
              <option value="<?= $proy['id'] ?>" <?= ($ficha['proyecto_id'] ?? $_POST['proyecto_id'] ?? 0) == $proy['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($proy['codigo'] . ' — ' . $proy['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Programa Formativo</label>
            <select name="programa_id" class="form-select" required
                    data-picker
                    data-picker-label="Programa formativo"
                    data-picker-placeholder="Nombre del programa...">
              <option value="" disabled selected>-- Selecciona un programa --</option>
              <?php foreach ($programas as $prog): ?>
              <option value="<?= $prog['id'] ?>" <?= ($ficha['programa_id'] ?? $_POST['programa_id'] ?? 0) == $prog['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($prog['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Instructor Responsable</label>
            <select name="instructor_id" class="form-select" required
                    data-picker
                    data-picker-label="Instructor responsable"
                    data-picker-placeholder="Nombre del instructor...">
              <option value="" disabled selected>-- Selecciona un instructor --</option>
              <?php foreach ($instructores as $inst): ?>
              <option value="<?= $inst['id'] ?>" <?= ($ficha['instructor_id'] ?? $_POST['instructor_id'] ?? 0) == $inst['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($inst['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" required
                    data-picker
                    data-picker-label="Estado de la ficha"
                    data-picker-placeholder="Seleccionar estado...">
              <option value="planeacion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'planeacion' ? 'selected' : '' ?>>Planeación</option>
              <option value="induccion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'induccion' ? 'selected' : '' ?>>Inducción</option>
              <option value="ejecucion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'ejecucion' ? 'selected' : '' ?>>Ejecución</option>
              <option value="cierre" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'cierre' ? 'selected' : '' ?>>Cierre</option>
            </select>
          </div>

          <?php
          // Estos dos valores son derivados y ya no se teclean: la cantidad
          // de aprendices sale de la tabla `aprendices` y el cumplimiento lo
          // recalcula el sistema al calificar. Poder editarlos a mano era lo
          // que los desincronizaba de la realidad. Se muestran como lectura.
          ?>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Aprendices matriculados</label>
              <input type="text" class="form-control" readonly disabled
                     value="<?= (int)($ficha['cantidad_aprendices'] ?? 0) ?>">
              <div class="form-text">Se calcula desde las matrículas de la ficha.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Cumplimiento (%)</label>
              <input type="text" class="form-control" readonly disabled
                     value="<?= number_format((float)($ficha['cumplimiento_porcentaje'] ?? 0), 1) ?>">
              <div class="form-text">Lo actualiza el sistema al calificar evidencias.</div>
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
            <a href="<?= APP_URL ?>/index.php/fichas" class="btn btn-soft">Cancelar</a>
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
          <li><strong>Número único:</strong> Cada ficha debe tener un número único en el sistema.</li>
          <li><strong>Estados:</strong>
            <ul>
              <li><strong>Planeación:</strong> Fase inicial de preparación</li>
              <li><strong>Inducción:</strong> Presentación del programa</li>
              <li><strong>Ejecución:</strong> Desarrollo del programa</li>
              <li><strong>Cierre:</strong> Finalización del programa</li>
            </ul>
          </li>
          <li><strong>Cumplimiento:</strong> Porcentaje de avance del programa (0-100%).</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
