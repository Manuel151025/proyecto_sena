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

// Obtener ficha existente si estamos editando
$ficha = null;
if ($id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT f.*, p.nombre as programa_nombre, u.nombre as instructor_nombre
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            JOIN usuarios u ON f.instructor_id = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $ficha = $stmt->fetch();
        if (!$ficha) {
            $errors[] = 'Ficha no encontrada';
        }
    } catch (Exception $e) {
        $errors[] = 'Error al cargar ficha';
    }
}

$pageTitle = $id && $ficha ? 'Editar Ficha · SENA' : 'Crear Ficha · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

// Obtener programas e instructores
try {
    $stmt = $db->prepare("SELECT id, nombre FROM programas WHERE estado = 'activo' ORDER BY nombre");
    $stmt->execute();
    $programas = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre");
    $stmt->execute();
    $instructores = $stmt->fetchAll();
} catch (Exception $e) {
    $programas = [];
    $instructores = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_ficha = trim($_POST['numero_ficha'] ?? '');
    $programa_id = (int) ($_POST['programa_id'] ?? 0);
    $instructor_id = (int) ($_POST['instructor_id'] ?? 0);
    $estado = $_POST['estado'] ?? 'planeacion';
    $cantidad_aprendices = (int) ($_POST['cantidad_aprendices'] ?? 0);
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $cumplimiento_porcentaje = (float) ($_POST['cumplimiento_porcentaje'] ?? 0);

    // Validaciones
    if (empty($numero_ficha)) $errors[] = 'El número de ficha es requerido';
    if ($programa_id <= 0) $errors[] = 'Debe seleccionar un programa';
    if ($instructor_id <= 0) $errors[] = 'Debe seleccionar un instructor';
    if (!in_array($estado, ['planeacion', 'induccion', 'ejecucion', 'cierre'])) $errors[] = 'Estado inválido';
    if ($cantidad_aprendices < 0) $errors[] = 'La cantidad de aprendices no puede ser negativa';
    if ($cumplimiento_porcentaje < 0 || $cumplimiento_porcentaje > 100) $errors[] = 'El cumplimiento debe estar entre 0 y 100%';

    if (empty($errors)) {
        try {
            if ($id > 0) {
                // Editar ficha existente
                $stmt = $db->prepare("
                    UPDATE fichas 
                    SET numero_ficha = ?, programa_id = ?, instructor_id = ?, estado = ?, 
                        cantidad_aprendices = ?, fecha_inicio = ?, fecha_fin = ?, 
                        cumplimiento_porcentaje = ?, fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $numero_ficha, $programa_id, $instructor_id, $estado,
                    $cantidad_aprendices, $fecha_inicio ?: null, $fecha_fin ?: null,
                    $cumplimiento_porcentaje, $id
                ]);
                $mensaje = 'Ficha actualizada correctamente';
            } else {
                // Crear nueva ficha
                $coordinador_id = getCurrentUser()['id'];
                $stmt = $db->prepare("
                    INSERT INTO fichas (numero_ficha, programa_id, instructor_id, coordinador_id, estado, cantidad_aprendices, fecha_inicio, fecha_fin, cumplimiento_porcentaje)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $numero_ficha, $programa_id, $instructor_id, $coordinador_id,
                    $estado, $cantidad_aprendices, $fecha_inicio ?: null, $fecha_fin ?: null,
                    $cumplimiento_porcentaje
                ]);
                $mensaje = 'Ficha creada correctamente';
            }
            $tipo_mensaje = 'success';
            $_POST = [];
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = 'Este número de ficha ya existe';
            } else {
                $errors[] = $id > 0 ? 'Error al actualizar ficha' : 'Error al crear ficha';
            }
        }
    }
}

$user = getCurrentUser();
?>

<div class="mb-3">
  <h1><?= $id && $ficha ? 'Editar Ficha' : 'Crear Nueva Ficha' ?></h1>
  <p class="text-muted mb-0"><?= $id && $ficha ? 'Modifica los datos de la ficha' : 'Completa el formulario para registrar una nueva ficha de formación' ?>.</p>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= MODULES_PATH ?>/fichas/">Volver a fichas →</a>
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
            <label class="form-label">Número de Ficha</label>
            <input type="text" name="numero_ficha" class="form-control" placeholder="Ej: 2845671" value="<?= htmlspecialchars($ficha['numero_ficha'] ?? $_POST['numero_ficha'] ?? '') ?>" required>
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
              <option value="planeacion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'planeacion' ? 'selected' : '' ?>>Planeación</option>
              <option value="induccion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'induccion' ? 'selected' : '' ?>>Inducción</option>
              <option value="ejecucion" <?= ($ficha['estado'] ?? $_POST['estado'] ?? 'planeacion') === 'ejecucion' ? 'selected' : '' ?>>Ejecución</option>
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
