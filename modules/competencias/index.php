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

// Procesar formulario de creación de competencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden registrar competencias.';
    } else {
        $programa_id = (int)($_POST['programa_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $horas = (int)($_POST['horas'] ?? 0);
        $estado = $_POST['estado'] ?? 'activo';

        if ($programa_id <= 0) $errors[] = 'Debe seleccionar un programa válido.';
        if (empty($nombre)) $errors[] = 'El nombre de la competencia es obligatorio.';
        if (empty($codigo)) $errors[] = 'El código de la competencia es obligatorio.';
        if ($horas <= 0) $errors[] = 'La duración en horas debe ser mayor a 0.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO competencias (programa_id, nombre, codigo, descripcion, horas, estado)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$programa_id, $nombre, $codigo, $descripcion, $horas, $estado]);
                $successMessage = 'Competencia registrada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al registrar competencia: ' . $e->getMessage();
            }
        }
    }
}

// Procesar edición de competencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden editar competencias.';
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $programa_id = (int)($_POST['programa_id'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $codigo      = trim($_POST['codigo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $horas       = (int)($_POST['horas'] ?? 0);
        $estado      = $_POST['estado'] ?? 'activo';

        if ($id <= 0)          $errors[] = 'Competencia no válida.';
        if ($programa_id <= 0) $errors[] = 'Debe seleccionar un programa válido.';
        if (empty($nombre))    $errors[] = 'El nombre de la competencia es obligatorio.';
        if (empty($codigo))    $errors[] = 'El código de la competencia es obligatorio.';
        if ($horas <= 0)       $errors[] = 'La duración en horas debe ser mayor a 0.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE competencias
                    SET programa_id=?, nombre=?, codigo=?, descripcion=?, horas=?, estado=?
                    WHERE id=?
                ");
                $stmt->execute([$programa_id, $nombre, $codigo, $descripcion, $horas, $estado, $id]);
                $successMessage = 'Competencia actualizada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al actualizar competencia: ' . $e->getMessage();
            }
        }
    }
}

// Procesar eliminación de competencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden eliminar competencias.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Competencia no válida.';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM competencias WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = 'Competencia eliminada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'No se puede eliminar: la competencia tiene registros asociados.';
            }
        }
    }
}

// Obtener programas para los filtros y el formulario
$programas = [];
try {
    $programas = $db->query("SELECT id, nombre, codigo FROM programas WHERE estado = 'activo' ORDER BY nombre")->fetchAll();
} catch (Exception $e) {
    $errors[] = 'Error al cargar programas.';
}

// Obtener filtros de búsqueda
$search = trim($_GET['search'] ?? '');
$filter_programa = (int)($_GET['programa_id'] ?? 0);
$filter_estado = $_GET['estado'] ?? '';

// Construir consulta de competencias
$sql = "
    SELECT c.*, p.nombre as programa_nombre, p.codigo as programa_codigo
    FROM competencias c
    JOIN programas p ON c.programa_id = p.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.nombre LIKE ? OR c.codigo LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_programa > 0) {
    $sql .= " AND c.programa_id = ?";
    $params[] = $filter_programa;
}
if (!empty($filter_estado)) {
    $sql .= " AND c.estado = ?";
    $params[] = $filter_estado;
}

$sql .= " ORDER BY p.nombre, c.codigo";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $competencias = $stmt->fetchAll();
} catch (Exception $e) {
    $competencias = [];
    $errors[] = 'Error al cargar las competencias.';
}

$pageTitle = 'Gestión de Competencias · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Competencias de Formación</h1>
    <p class="text-muted mb-0">Administra las competencias académicas asociadas a los programas de formación.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR)): ?>
  <div class="d-flex gap-2">
    <a href="importar.php" class="btn btn-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar Masivo
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="bi bi-plus-lg me-1"></i> Nueva Competencia
    </button>
  </div>
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
        <label class="form-label text-muted small">Buscar competencia</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0" style="border-color:rgba(255,255,255,0.15)"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Nombre o código..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Programa</label>
        <select name="programa_id" class="form-select">
          <option value="0">Todos los programas</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filter_programa === (int)$p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="activo" <?= $filter_estado === 'activo' ? 'selected' : '' ?>>Activo</option>
          <option value="inactivo" <?= $filter_estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de competencias -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Código</th>
            <th>Competencia</th>
            <th>Programa de Formación</th>
            <th>Horas</th>
            <th>Estado</th>
            <th class="pe-4 text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($competencias as $comp): ?>
          <tr>
            <td class="ps-4 font-monospace fw-bold text-primary"><?= htmlspecialchars($comp['codigo']) ?></td>
            <td>
              <div class="fw-semibold text-wrap" style="max-width:350px;">
                <?= htmlspecialchars($comp['nombre']) ?>
              </div>
              <small class="text-muted d-block text-truncate text-wrap" style="max-width:350px;">
                <?= htmlspecialchars($comp['descripcion'] ?? 'Sin descripción') ?>
              </small>
            </td>
            <td>
              <span class="badge bg-soft info"><?= htmlspecialchars($comp['programa_codigo']) ?></span>
              <span class="small text-muted text-wrap d-inline-block ms-1" style="max-width:200px;">
                <?= htmlspecialchars($comp['programa_nombre']) ?>
              </span>
            </td>
            <td><?= $comp['horas'] ?> hrs</td>
            <td>
              <span class="badge-soft <?= $comp['estado'] === 'activo' ? 'success' : 'danger' ?>">
                <?= ucfirst($comp['estado']) ?>
              </span>
            </td>
            <td class="pe-4 text-end">
              <?php if (hasRole(ROL_COORDINADOR)): ?>
              <button class="btn btn-sm btn-soft" onclick="abrirModalEditar(
                  <?= $comp['id'] ?>, <?= $comp['programa_id'] ?>,
                  <?= json_encode($comp['codigo']) ?>, <?= json_encode($comp['nombre']) ?>,
                  <?= json_encode($comp['descripcion'] ?? '') ?>, <?= $comp['horas'] ?>,
                  <?= json_encode($comp['estado']) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('¿Eliminar la competencia <?= htmlspecialchars(addslashes($comp['nombre'])) ?>? Esta acción no se puede deshacer.')">
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="id" value="<?= $comp['id'] ?>">
                <button type="submit" class="btn btn-sm btn-soft text-danger">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($competencias)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-diagram-3 d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
              No se encontraron competencias registradas.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Editar Competencia -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Editar Competencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Programa de Formación</label>
            <select name="programa_id" id="edit_programa_id" class="form-select" required>
              <?php foreach ($programas as $p): ?>
                <option value="<?= $p['id'] ?>">
                  <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label text-muted small fw-semibold">Código</label>
              <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Horas</label>
              <input type="number" name="horas" id="edit_horas" class="form-control" min="1" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Nombre de Competencia</label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Descripción (Opcional)</label>
            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Estado</label>
            <select name="estado" id="edit_estado" class="form-select">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalEditar(id, programaId, codigo, nombre, descripcion, horas, estado) {
    document.getElementById('edit_id').value          = id;
    document.getElementById('edit_programa_id').value = programaId;
    document.getElementById('edit_codigo').value      = codigo;
    document.getElementById('edit_nombre').value      = nombre;
    document.getElementById('edit_descripcion').value = descripcion;
    document.getElementById('edit_horas').value       = horas;
    document.getElementById('edit_estado').value      = estado;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
<?php endif; ?>

<!-- Modal Crear Competencia -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalCrear" tabindex="-1" aria-labelledby="modalCrearLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalCrearLabel">Nueva Competencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="crear">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Programa de Formación</label>
            <select name="programa_id" class="form-select" required>
              <option value="" disabled selected>Seleccione un programa...</option>
              <?php foreach ($programas as $p): ?>
                <option value="<?= $p['id'] ?>">
                  <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label text-muted small fw-semibold">Código</label>
              <input type="text" name="codigo" class="form-control" placeholder="Ej. 220501096" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Horas</label>
              <input type="number" name="horas" class="form-control" placeholder="Horas" min="1" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Nombre de Competencia</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. Diseñar la estructura de datos..." required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Descripción (Opcional)</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles de la competencia..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Estado</label>
            <select name="estado" class="form-select">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar Competencia</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
