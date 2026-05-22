<div class="mb-3">
  <h1>Crear Nuevo Usuario</h1>
  <p class="text-muted mb-0">Completa el formulario para registrar un nuevo usuario en el sistema.</p>
</div>

<?php if (!empty($mensaje)): ?>
<div class="alert-flat <?= htmlspecialchars($tipo_mensaje) ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= MODULES_PATH ?>/usuarios/">Volver a la lista →</a>
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

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Carlos Andrés Martínez" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email institucional</label>
            <input type="email" name="email" class="form-control" placeholder="usuario@sena.edu.co" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-select" required>
              <option value="aprendiz" <?= ($_POST['rol'] ?? '') === 'aprendiz' ? 'selected' : '' ?>>Aprendiz</option>
              <option value="instructor" <?= ($_POST['rol'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructor</option>
              <option value="coordinador" <?= ($_POST['rol'] ?? '') === 'coordinador' ? 'selected' : '' ?>>Coordinador</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Color de Avatar</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
              <?php foreach ($colors as $color): ?>
              <input type="radio" name="avatar_color" id="color-<?= htmlspecialchars($color) ?>" value="<?= htmlspecialchars($color) ?>" <?= ($_POST['avatar_color'] ?? '#39A900') === $color ? 'checked' : '' ?> style="display: none;">
              <label for="color-<?= htmlspecialchars($color) ?>" style="width: 50px; height: 50px; background: <?= htmlspecialchars($color) ?>; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;" onclick="document.querySelectorAll('input[name=avatar_color]').forEach(el => document.querySelector('label[for='+el.id+']').style.borderColor = 'transparent'); this.style.borderColor = '#000'"></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Crear Usuario</button>
            <a href="<?= MODULES_PATH ?>/usuarios/" class="btn btn-soft">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h5><i class="bi bi-info-circle text-primary"></i> Información importante</h5>
        <ul style="font-size: 0.9rem; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
          <li><strong>Email único:</strong> Cada usuario debe tener un email único en el sistema.</li>
          <li><strong>Contraseña:</strong> Se guardará de manera encriptada. Mínimo 6 caracteres.</li>
          <li><strong>Roles:</strong>
            <ul>
              <li><strong>Coordinador:</strong> Gestión completa del sistema</li>
              <li><strong>Instructor:</strong> Gestión de fichas y evaluaciones</li>
              <li><strong>Aprendiz:</strong> Acceso a su información y actividades</li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
<script>
// Select default radio visually
document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="avatar_color"]:checked');
  if (checked) {
    document.querySelector('label[for="'+checked.id+'"]').style.borderColor = '#000';
  }
});
</script>
