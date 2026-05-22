<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$db = Database::getConnection();
$errors = [];
$successMessage = '';

// Procesar formulario de asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden gestionar asignaciones.';
    } else {
        if ($_POST['action'] === 'asignar') {
            $ficha_id = (int)($_POST['ficha_id'] ?? 0);
            $competencia_id = (int)($_POST['competencia_id'] ?? 0);
            $instructor_id = (int)($_POST['instructor_id'] ?? 0);

            if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha válida.';
            if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia válida.';
            if ($instructor_id <= 0) $errors[] = 'Debe seleccionar un instructor válido.';

            if (empty($errors)) {
                try {
                    // Verificar si ya existe asignación para esa ficha y competencia
                    $stmt = $db->prepare("SELECT id FROM asignaciones WHERE ficha_id = ? AND competencia_id = ?");
                    $stmt->execute([$ficha_id, $competencia_id]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Ya existe un instructor asignado a esta competencia en esta ficha. Elimine la asignación previa primero.';
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO asignaciones (ficha_id, competencia_id, instructor_id)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$ficha_id, $competencia_id, $instructor_id]);

                        // Registrar en log
                        $logStmt = $db->prepare("
                            INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                            VALUES (?, 'Crear', 'Asignaciones', 'asignaciones', ?, ?)
                        ");
                        $logStmt->execute([(int)getCurrentUser()['id'], (int)$db->lastInsertId(), "Asignó al instructor id $instructor_id a la competencia id $competencia_id en la ficha id $ficha_id"]);

                        $successMessage = 'Instructor asignado exitosamente a la competencia.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Error al realizar la asignación: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'eliminar') {
            $asignacion_id = (int)($_POST['asignacion_id'] ?? 0);
            if ($asignacion_id <= 0) {
                $errors[] = 'ID de asignación no válido.';
            } else {
                try {
                    $db->prepare("DELETE FROM asignaciones WHERE id = ?")->execute([$asignacion_id]);

                    // Registrar en log
                    $logStmt = $db->prepare("
                        INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                        VALUES (?, 'Eliminar', 'Asignaciones', 'asignaciones', ?, ?)
                    ");
                    $logStmt->execute([(int)getCurrentUser()['id'], $asignacion_id, "Eliminó la asignación id $asignacion_id"]);

                    $successMessage = 'Asignación eliminada exitosamente.';
                } catch (Exception $e) {
                    $errors[] = 'Error al eliminar la asignación: ' . $e->getMessage();
                }
            }
        }
    }
}

// Obtener filtros
$search = trim($_GET['search'] ?? '');
$filter_ficha = (int)($_GET['ficha_id'] ?? 0);
$filter_instructor = (int)($_GET['instructor_id'] ?? 0);

// Construir consulta de asignaciones
$sql = "
    SELECT a.id, a.fecha_asignacion, f.numero_ficha, p.nombre as programa_nombre, 
           c.codigo as competencia_codigo, c.nombre as competencia_nombre, 
           u.nombre as instructor_nombre, u.email as instructor_email, u.avatar_color
    FROM asignaciones a
    JOIN fichas f ON a.ficha_id = f.id
    JOIN programas p ON f.programa_id = p.id
    JOIN competencias c ON a.competencia_id = c.id
    JOIN usuarios u ON a.instructor_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nombre LIKE ? OR c.nombre LIKE ? OR c.codigo LIKE ? OR f.numero_ficha LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_ficha > 0) {
    $sql .= " AND a.ficha_id = ?";
    $params[] = $filter_ficha;
}
if ($filter_instructor > 0) {
    $sql .= " AND a.instructor_id = ?";
    $params[] = $filter_instructor;
}

$sql .= " ORDER BY f.numero_ficha, c.codigo";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $asignaciones = $stmt->fetchAll();
} catch (Exception $e) {
    $asignaciones = [];
    $errors[] = 'Error al cargar asignaciones.';
}

// Obtener fichas, competencias e instructores para los formularios y filtros
$fichas = [];
$competencias = [];
$instructores = [];
try {
    $fichas = $db->query("
        SELECT f.id, f.numero_ficha, p.codigo as programa_codigo 
        FROM fichas f
        JOIN programas p ON f.programa_id = p.id
        ORDER BY f.numero_ficha
    ")->fetchAll();

    $competencias = $db->query("
        SELECT c.id, c.codigo, c.nombre, p.codigo as programa_codigo 
        FROM competencias c
        JOIN programas p ON c.programa_id = p.id
        WHERE c.estado = 'activo'
        ORDER BY p.codigo, c.codigo
    ")->fetchAll();

    $instructores = $db->query("
        SELECT id, nombre, email 
        FROM usuarios 
        WHERE rol = 'instructor' AND estado = 'activo' 
        ORDER BY nombre
    ")->fetchAll();
} catch (Exception $e) {
    $errors[] = 'Error al cargar datos auxiliares.';
}

$pageTitle = 'Asignaciones de Instructores · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Asignaciones de Instructores</h1>
    <p class="text-muted mb-0">Asocia instructores a competencias específicas dentro de cada ficha técnica.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR)): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsignar">
    <i class="bi bi-person-plus me-1"></i> Nueva Asignación
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 glass-card text-danger" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <ul class="mb-0 ps-3 d-inline-block">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Barra de filtros -->
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar asignación</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0" style="border-color:rgba(255,255,255,0.15)"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ficha, instructor o competencia..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select"
                data-picker
                data-picker-label="Filtrar por ficha"
                data-picker-placeholder="Número de ficha o programa...">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>
                    data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa_codigo']) ?>">
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa_codigo']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Instructor</label>
        <select name="instructor_id" class="form-select"
                data-picker
                data-picker-label="Filtrar por instructor"
                data-picker-placeholder="Nombre del instructor...">
          <option value="0">Todos los instructores</option>
          <?php foreach ($instructores as $inst): ?>
            <option value="<?= $inst['id'] ?>" <?= $filter_instructor === (int)$inst['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($inst['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de asignaciones -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Ficha</th>
            <th>Competencia</th>
            <th>Instructor Asignado</th>
            <th>Fecha Asignación</th>
            <?php if (hasRole(ROL_COORDINADOR)): ?>
            <th class="pe-4 text-end">Acciones</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignaciones as $asg): ?>
          <tr>
            <td class="ps-4">
              <span class="badge bg-soft primary font-monospace fs-6">#<?= htmlspecialchars($asg['numero_ficha']) ?></span>
              <div class="text-muted small mt-1"><?= htmlspecialchars($asg['programa_nombre']) ?></div>
            </td>
            <td>
              <div class="fw-bold text-dark font-monospace" style="font-size:0.9rem;"><?= htmlspecialchars($asg['competencia_codigo']) ?></div>
              <small class="text-muted text-wrap d-inline-block" style="max-width:320px;"><?= htmlspecialchars($asg['competencia_nombre']) ?></small>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar" style="width:36px; height:36px; font-size:0.9rem; background:<?= htmlspecialchars($asg['avatar_color']) ?>">
                  <?= strtoupper(substr($asg['instructor_nombre'], 0, 2)) ?>
                </div>
                <div>
                  <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($asg['instructor_nombre']) ?></h6>
                  <small class="text-muted"><?= htmlspecialchars($asg['instructor_email']) ?></small>
                </div>
              </div>
            </td>
            <td class="text-muted small">
              <?= date('d/m/Y h:i A', strtotime($asg['fecha_asignacion'])) ?>
            </td>
            <?php if (hasRole(ROL_COORDINADOR)): ?>
            <td class="pe-4 text-end">
              <button class="btn btn-sm btn-soft text-danger" onclick="confirmarEliminarAsignacion(<?= $asg['id'] ?>, <?= json_encode($asg['instructor_nombre']) ?>, <?= json_encode($asg['competencia_codigo']) ?>)">
                <i class="bi bi-trash me-1"></i> Quitar
              </button>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($asignaciones)): ?>
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="bi bi-person-badge d-block mb-2" style="font-size:2.5rem; opacity:0.4;"></i>
              No se encontraron asignaciones de instructores.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Asignar Instructor -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalAsignar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Asignar Instructor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="asignar">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Ficha de Destino</label>
            <select name="ficha_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar ficha"
                    data-picker-placeholder="Número de ficha o programa...">
              <option value="" disabled selected>Seleccione Ficha...</option>
              <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id'] ?>"
                        data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa_codigo']) ?>">
                  Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa_codigo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Competencia Formativa</label>
            <select name="competencia_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar competencia"
                    data-picker-placeholder="Código o nombre de la competencia...">
              <option value="" disabled selected>Seleccione Competencia...</option>
              <?php foreach ($competencias as $c): ?>
                <option value="<?= $c['id'] ?>"
                        data-search="<?= htmlspecialchars($c['programa_codigo'] . ' ' . $c['codigo'] . ' ' . $c['nombre']) ?>">
                  <?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Instructor Responsable</label>
            <select name="instructor_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar instructor"
                    data-picker-placeholder="Nombre o email del instructor...">
              <option value="" disabled selected>Seleccione Instructor...</option>
              <?php foreach ($instructores as $inst): ?>
                <option value="<?= $inst['id'] ?>"
                        data-search="<?= htmlspecialchars($inst['email']) ?>">
                  <?= htmlspecialchars($inst['nombre']) ?> — <?= htmlspecialchars($inst['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Asignar Instructor</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<form id="formEliminarAsignacion" method="POST" style="display:none;">
  <input type="hidden" name="action" value="eliminar">
  <input type="hidden" name="asignacion_id" id="eliminar_asignacion_id">
</form>

<script>
function confirmarEliminarAsignacion(id, instructor, competencia) {
    if (confirm('¿Estás seguro de que deseas desvincular al instructor ' + instructor + ' de la competencia ' + competencia + '?')) {
        document.getElementById('eliminar_asignacion_id').value = id;
        document.getElementById('formEliminarAsignacion').submit();
    }
}
</script>
