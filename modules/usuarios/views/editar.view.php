<div class="mb-3">
  <h1>Editar Usuario</h1>
  <p class="text-muted mb-0">Modifica la información del usuario seleccionado.</p>
</div>

<?php if (!empty($mensaje)): ?>
<div class="alert-flat <?= htmlspecialchars($tipo_mensaje) ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= APP_URL ?>/index.php/usuarios">Volver a la lista →</a>
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
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? $usuario['nombre'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email institucional</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $usuario['email'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Contraseña (Opcional)</label>
            <input type="password" name="password" class="form-control" placeholder="Déjalo en blanco para mantener la actual">
            <small class="text-muted">Mínimo 6 caracteres si decides cambiarla.</small>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Rol</label>
              <select name="rol" class="form-select" required>
                <?php $currentRol = $_POST['rol'] ?? $usuario['rol'] ?? ''; ?>
                <option value="aprendiz" <?= $currentRol === 'aprendiz' ? 'selected' : '' ?>>Aprendiz</option>
                <option value="instructor" <?= $currentRol === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                <option value="coordinador" <?= $currentRol === 'coordinador' ? 'selected' : '' ?>>Coordinador</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select" required>
                <?php $currentEstado = $_POST['estado'] ?? $usuario['estado'] ?? ''; ?>
                <option value="activo" <?= $currentEstado === 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= $currentEstado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                <option value="bloqueado" <?= $currentEstado === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Color de Avatar</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
              <?php 
              $currentAvatar = $_POST['avatar_color'] ?? $usuario['avatar_color'] ?? '#39A900';
              foreach ($colors as $color): 
              ?>
              <input type="radio" name="avatar_color" id="color-<?= htmlspecialchars($color) ?>" value="<?= htmlspecialchars($color) ?>" <?= $currentAvatar === $color ? 'checked' : '' ?> style="display: none;">
              <label for="color-<?= htmlspecialchars($color) ?>" style="width: 50px; height: 50px; background: <?= htmlspecialchars($color) ?>; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;" onclick="document.querySelectorAll('input[name=avatar_color]').forEach(el => document.querySelector('label[for='+el.id+']').style.borderColor = 'transparent'); this.style.borderColor = '#000'"></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="<?= APP_URL ?>/index.php/usuarios" class="btn btn-soft">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="avatar_color"]:checked');
  if (checked) {
    document.querySelector('label[for="'+checked.id+'"]').style.borderColor = '#000';
  }
});
</script>
