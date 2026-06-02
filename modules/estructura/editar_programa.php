<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$id = (int) ($_GET['id'] ?? 0);
$db = Database::getConnection();
$mensaje = '';
$tipo_mensaje = '';
$errors = [];

// Obtener programa existente
$programa = null;
if ($id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM programas WHERE id = ?");
        $stmt->execute([$id]);
        $programa = $stmt->fetch();
        if (!$programa) {
            $errors[] = 'Programa no encontrado';
        }
    } catch (Exception $e) {
        $errors[] = 'Error al cargar el programa';
    }
} else {
    $errors[] = 'ID de programa inválido';
}

$pageTitle = 'Editar Programa · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $programa) {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $duracion_horas = (int) ($_POST['duracion_horas'] ?? 0);
    $estado = $_POST['estado'] ?? 'activo';

    // Validaciones
    if (empty($nombre)) {
        $errors[] = 'El nombre del programa es requerido';
    } elseif (mb_strlen($nombre) > 200) {
        $errors[] = 'El nombre del programa no puede exceder los 200 caracteres';
    }

    if (empty($codigo)) {
        $errors[] = 'El código del programa es requerido';
    } elseif (mb_strlen($codigo) > 50) {
        $errors[] = 'El código del programa no puede exceder los 50 caracteres';
    }

    if ($duracion_horas <= 0) {
        $errors[] = 'La duración en horas debe ser un número positivo';
    } elseif ($duracion_horas > 99999) {
        $errors[] = 'La duración en horas no puede superar las 99,999 horas';
    }

    if (!in_array($estado, ['activo', 'inactivo'])) {
        $errors[] = 'Estado inválido';
    }

    if (empty($errors)) {
        try {
            // Verificar código duplicado en otros programas
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM programas WHERE codigo = ? AND id != ?");
            $stmtCheck->execute([$codigo, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errors[] = 'El código ingresado ya está registrado para otro programa';
            } else {
                $stmtUpdate = $db->prepare("
                    UPDATE programas
                    SET nombre = ?, codigo = ?, duracion_horas = ?, estado = ?, fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$nombre, $codigo, $duracion_horas, $estado, $id]);
                
                $mensaje = 'Programa actualizado correctamente';
                $tipo_mensaje = 'success';
                
                // Actualizar datos del programa para mostrar en el formulario
                $programa['nombre'] = $nombre;
                $programa['codigo'] = $codigo;
                $programa['duracion_horas'] = $duracion_horas;
                $programa['estado'] = $estado;
            }
        } catch (Exception $e) {
            $errors[] = 'Error al actualizar el programa: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="mb-1">Editar Programa de Formación</h1>
    <p class="text-muted mb-0">Modifica los detalles generales del programa.</p>
  </div>
  <div>
    <a href="<?= MODULES_PATH ?>/estructura/" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
  </div>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-4">
  <i class="bi bi-check-circle-fill"></i>
  <div>
    <?= htmlspecialchars($mensaje) ?>
    <br><a href="<?= MODULES_PATH ?>/estructura/" class="alert-link">Volver a la estructura curricular →</a>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-4">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>
    <?php foreach ($errors as $error): ?>
    <div><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($programa): ?>
<div class="row">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="POST">
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
            <a href="<?= MODULES_PATH ?>/estructura/" class="btn btn-soft">Cancelar</a>
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
