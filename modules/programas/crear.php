<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$db = Database::getConnection();
$id = (int) ($_GET['id'] ?? 0);
$esEdicion = $id > 0;
$pageTitle = ($esEdicion ? 'Editar' : 'Crear') . ' Programa · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$programa = null;
$errores = [];
$mensaje = '';
$tipo_mensaje = '';

if ($esEdicion) {
    try {
        $stmt = $db->prepare("SELECT * FROM programas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $programa = $stmt->fetch();
        if (!$programa) {
            die('Programa no encontrado');
        }
    } catch (Exception $e) {
        die('Error al cargar programa');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $duracion_horas = (int) ($_POST['duracion_horas'] ?? 0);
    $estado = $_POST['estado'] ?? 'activo';

    if (empty($nombre)) $errores[] = 'El nombre es requerido';
    if (empty($codigo)) $errores[] = 'El código es requerido';
    if ($duracion_horas <= 0) $errores[] = 'La duración debe ser mayor a 0 horas';

    if (empty($errores)) {
        try {
            if ($esEdicion) {
                $stmt = $db->prepare("UPDATE programas SET nombre = ?, codigo = ?, descripcion = ?, duracion_horas = ?, estado = ?, fecha_actualizacion = NOW() WHERE id = ?");
                $stmt->execute([$nombre, $codigo, $descripcion, $duracion_horas, $estado, $id]);
                $mensaje = 'Programa actualizado correctamente';
            } else {
                $stmt = $db->prepare("INSERT INTO programas (nombre, codigo, descripcion, duracion_horas, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $codigo, $descripcion, $duracion_horas, $estado]);
                $mensaje = 'Programa creado correctamente';
            }
            $tipo_mensaje = 'success';
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errores[] = 'Este código de programa ya existe';
            } else {
                $errores[] = 'Error al guardar programa';
            }
        }
    }
}

$valores = $programa ?? $_POST;
?>

<div class="mb-3">
  <h1><?= $esEdicion ? 'Editar' : 'Crear nuevo' ?> Programa</h1>
  <p class="text-muted mb-0">Completa el formulario para <?= $esEdicion ? 'actualizar' : 'registrar' ?> un programa de formación.</p>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= MODULES_PATH ?>/programas/">Volver a la lista →</a>
</div>
<?php endif; ?>

<?php if (!empty($errores)): ?>
<div class="alert-flat danger mb-3">
  <i class="bi bi-exclamation-circle"></i>
  <div>
    <?php foreach ($errores as $error): ?>
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
            <label class="form-label">Nombre del Programa</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Análisis y Desarrollo de Software" value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Código</label>
              <input type="text" name="codigo" class="form-control" placeholder="Ej: ADSO" value="<?= htmlspecialchars($valores['codigo'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Duración (horas)</label>
              <input type="number" name="duracion_horas" class="form-control" placeholder="Ej: 2880" value="<?= htmlspecialchars($valores['duracion_horas'] ?? '') ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="4" placeholder="Describe el programa de formación..."><?= htmlspecialchars($valores['descripcion'] ?? '') ?></textarea>
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
            <a href="<?= MODULES_PATH ?>/programas/" class="btn btn-soft">Cancelar</a>
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
