<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$pageTitle = 'Crear Usuario · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$db = Database::getConnection();
$mensaje = '';
$tipo_mensaje = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'aprendiz';
    $avatar_color = $_POST['avatar_color'] ?? '#39A900';

    // Validaciones
    if (empty($nombre)) $errors[] = 'El nombre es requerido';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
    if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    if (!in_array($rol, ['coordinador', 'instructor', 'aprendiz'])) $errors[] = 'Rol inválido';

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
            $stmt->execute([
                $nombre,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $rol,
                $avatar_color
            ]);
            $mensaje = 'Usuario creado correctamente';
            $tipo_mensaje = 'success';
            $_POST = [];
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = 'Este email ya está registrado';
            } else {
                $errors[] = 'Error al crear el usuario';
            }
        }
    }
}

$colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
?>

<div class="mb-3">
  <h1>Crear Nuevo Usuario</h1>
  <p class="text-muted mb-0">Completa el formulario para registrar un nuevo usuario en el sistema.</p>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
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
              <input type="radio" name="avatar_color" id="color-<?= $color ?>" value="<?= $color ?>" <?= ($_POST['avatar_color'] ?? '#39A900') === $color ? 'checked' : '' ?> style="display: none;">
              <label for="color-<?= $color ?>" style="width: 50px; height: 50px; background: <?= $color ?>; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;" onclick="this.style.borderColor = '#000'"></label>
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
        <h5>ℹ️ Información importante</h5>
        <ul style="font-size: 0.9rem; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
          <li><strong>Email único:</strong> Cada usuario debe tener un email único en el sistema.</li>
          <li><strong>Contraseña:</strong> Se enviará encriptada. Mínimo 6 caracteres.</li>
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
