<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireAuth();

$db = Database::getConnection();
$errors = [];
$successMessage = '';

$user_rol = getCurrentRole();
$selected_proyecto_id = (int)($_GET['proyecto_id'] ?? 0);

// Procesar formulario de nueva fase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para administrar fases.';
    } else {
        $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
        $numero_fase = (int)($_POST['numero_fase'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $cumplimiento = (float)($_POST['cumplimiento_porcentaje'] ?? 0);
        $estado = $_POST['estado'] ?? 'planeada';

        if ($proyecto_id <= 0) $errors[] = 'Debe seleccionar un proyecto.';
        if ($numero_fase <= 0) $errors[] = 'El número de fase debe ser mayor a 0.';
        if (empty($nombre)) $errors[] = 'El nombre de la fase es obligatorio.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO fases_proyecto (proyecto_id, numero_fase, nombre, descripcion, fecha_inicio, fecha_fin, cumplimiento_porcentaje, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$proyecto_id, $numero_fase, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $cumplimiento, $estado]);
                
                $successMessage = 'Fase de proyecto registrada exitosamente.';
                $selected_proyecto_id = $proyecto_id;
            } catch (Exception $e) {
                $errors[] = 'Error al registrar la fase: ' . $e->getMessage();
            }
        }
    }
}

// Procesar edición de fase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para editar fases.';
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $numero_fase = (int)($_POST['numero_fase'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $fecha_fin    = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $cumplimiento = (float)($_POST['cumplimiento_porcentaje'] ?? 0);
        $estado       = $_POST['estado'] ?? 'planeada';

        if ($id <= 0)          $errors[] = 'Fase no válida.';
        if ($numero_fase <= 0) $errors[] = 'El número de fase debe ser mayor a 0.';
        if (empty($nombre))    $errors[] = 'El nombre de la fase es obligatorio.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE fases_proyecto
                    SET numero_fase=?, nombre=?, descripcion=?, fecha_inicio=?, fecha_fin=?,
                        cumplimiento_porcentaje=?, estado=?
                    WHERE id=?
                ");
                $stmt->execute([$numero_fase, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $cumplimiento, $estado, $id]);
                $successMessage = 'Fase actualizada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al actualizar la fase: ' . $e->getMessage();
            }
        }
    }
}

// Procesar eliminación de fase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para eliminar fases.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Fase no válida.';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM fases_proyecto WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = 'Fase eliminada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'No se puede eliminar: la fase tiene registros asociados.';
            }
        }
    }
}

$user_id = (int)getCurrentUser()['id'];

// Obtener proyectos para el filtro
$proyectos = [];
$aprendiz_proyecto_id = 0;

if ($user_rol === ROL_APRENDIZ) {
    try {
        $stmt = $db->prepare("
            SELECT f.proyecto_id 
            FROM aprendices ap
            JOIN fichas f ON ap.ficha_id = f.id
            WHERE ap.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        $aprendiz_proyecto_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        $errors[] = 'Error al obtener el proyecto del aprendiz.';
    }
}

try {
    if ($user_rol === ROL_APRENDIZ) {
        if ($aprendiz_proyecto_id > 0) {
            $stmt = $db->prepare("SELECT id, nombre, codigo FROM proyectos WHERE id = ?");
            $stmt->execute([$aprendiz_proyecto_id]);
            $proyectos = $stmt->fetchAll();
        } else {
            $proyectos = [];
        }
        $selected_proyecto_id = $aprendiz_proyecto_id;
    } else {
        $proyectos = $db->query("SELECT id, nombre, codigo FROM proyectos ORDER BY nombre")->fetchAll();
        if ($selected_proyecto_id === 0 && !empty($proyectos)) {
            $selected_proyecto_id = (int)$proyectos[0]['id'];
        }
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar proyectos.';
}

// Obtener el proyecto seleccionado
$proyectoActual = null;
if ($selected_proyecto_id > 0) {
    try {
        $stmt = $db->prepare("SELECT pr.*, GROUP_CONCAT(DISTINCT f.numero_ficha ORDER BY f.numero_ficha SEPARATOR ', ') as fichas_vinculadas FROM proyectos pr LEFT JOIN fichas f ON f.proyecto_id = pr.id WHERE pr.id = ? GROUP BY pr.id");
        $stmt->execute([$selected_proyecto_id]);
        $proyectoActual = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Obtener fases del proyecto seleccionado
$fases = [];
if ($selected_proyecto_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT fp.* 
            FROM fases_proyecto fp
            WHERE fp.proyecto_id = ?
            ORDER BY fp.numero_fase ASC
        ");
        $stmt->execute([$selected_proyecto_id]);
        $fases = $stmt->fetchAll();
    } catch (Exception $e) {
        $errors[] = 'Error al cargar fases del proyecto.';
    }
}

$estados_label = [
    'planeada' => ['Planeada', 'secondary'],
    'en_ejecucion' => ['En Ejecución', 'warning'],
    'completada' => ['Completada', 'success']
];

$pageTitle = 'Fases de Proyecto · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Fases del Proyecto Formativo</h1>
    <p class="text-muted mb-0">Monitorea y planea el progreso del proyecto de formación estructurado en fases consecutivas.</p>
  </div>
  <?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
    <i class="bi bi-plus-lg me-1"></i> Nueva Fase
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert-flat success mb-3"><i class="bi bi-check-circle-fill"></i><div><?= htmlspecialchars($successMessage) ?></div></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i>
  <div><?php foreach ($errors as $err) echo htmlspecialchars($err) . '<br>'; ?></div>
</div>
<?php endif; ?>

<?php if ($user_rol !== ROL_APRENDIZ): ?>
<!-- Selección de Proyecto -->
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label text-muted small">Seleccionar Proyecto Formativo</label>
        <select name="proyecto_id" class="form-select" onchange="this.form.submit()"
                data-picker
                data-picker-label="Seleccionar proyecto"
                data-picker-placeholder="Código o nombre del proyecto...">
          <?php foreach ($proyectos as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $selected_proyecto_id === (int)$p['id'] ? 'selected' : '' ?>
                    data-search="<?= htmlspecialchars($p['codigo'] . ' ' . $p['nombre']) ?>">
              <?= htmlspecialchars($p['codigo']) ?> — <?= htmlspecialchars($p['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-grid">
        <button type="submit" class="btn btn-soft">Cargar fases</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($proyectoActual): ?>
<div class="card mb-4" style="border-left: 4px solid var(--sena-primary); border-radius: 8px;">
  <div class="card-body py-3">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($proyectoActual['nombre']) ?></h5>
        <small class="text-muted"><?= htmlspecialchars($proyectoActual['objetivo'] ?? 'Sin objetivo definido') ?></small>
      </div>
      <div class="text-end">
        <?php if ($proyectoActual['fichas_vinculadas']): ?>
        <small class="text-muted d-block">Fichas vinculadas:</small>
        <span class="badge bg-soft primary">#<?= htmlspecialchars(str_replace(', ', '</span> <span class="badge bg-soft primary">#', $proyectoActual['fichas_vinculadas'])) ?></span>
        <?php else: ?>
        <span class="badge-soft secondary">Sin fichas vinculadas</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Listado de fases -->
<div class="row g-3">
  <?php foreach ($fases as $fase): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card glass-card h-100 border-0 shadow-sm" style="transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft info fw-bold" style="font-size: 0.85rem;">Fase <?= htmlspecialchars((string)$fase['numero_fase']) ?></span>
            <span class="badge-soft <?= $estados_label[$fase['estado']][1] ?>">
              <?= $estados_label[$fase['estado']][0] ?>
            </span>
          </div>
          <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($fase['nombre']) ?></h5>
          <p class="card-text text-muted small flex-grow-1">
            <?= htmlspecialchars($fase['descripcion'] ?: 'Sin descripción.') ?>
          </p>

          <div class="p-2 rounded mb-3" style="background: rgba(0,0,0,0.02); font-size: 0.8rem;">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">Inicio:</span>
              <span class="fw-semibold text-dark"><?= $fase['fecha_inicio'] ? date('d/m/Y', strtotime($fase['fecha_inicio'])) : 'N/A' ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Fin:</span>
              <span class="fw-semibold text-dark"><?= $fase['fecha_fin'] ? date('d/m/Y', strtotime($fase['fecha_fin'])) : 'N/A' ?></span>
            </div>

            <div class="text-muted small mb-1">Cumplimiento:</div>
            <div class="progress" style="height: 6px; border-radius: 10px;">
              <?php $pct = (int)$fase['cumplimiento_porcentaje']; ?>
              <div class="progress-bar" style="width: <?= $pct ?>%; background: <?= $pct >= 75 ? 'var(--sena-primary)' : ($pct >= 40 ? '#eab308' : '#ef4444') ?>; border-radius: 10px;"></div>
            </div>
            <div class="text-end fw-bold mt-1 text-dark" style="font-size:0.75rem;"><?= $pct ?>%</div>
          </div>
          
          <?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-soft flex-grow-1"
                onclick="abrirModalEditarFase(
                  <?= $fase['id'] ?>, <?= $fase['numero_fase'] ?>,
                  <?= htmlspecialchars(json_encode($fase['nombre']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($fase['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                  <?= htmlspecialchars(json_encode($fase['fecha_inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($fase['fecha_fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                  <?= (float)$fase['cumplimiento_porcentaje'] ?>, <?= htmlspecialchars(json_encode($fase['estado']), ENT_QUOTES, 'UTF-8') ?>)">
                <i class="bi bi-pencil me-1"></i>Editar
              </button>
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('¿Eliminar la fase <?= htmlspecialchars(addslashes($fase['nombre'])) ?>?')">
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="id" value="<?= $fase['id'] ?>">
                <button type="submit" class="btn btn-sm btn-soft text-danger px-3">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($fases)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-list-task d-block mb-2" style="font-size:3rem; opacity:0.3;"></i>
      <?php if ($user_rol === ROL_APRENDIZ && $aprendiz_proyecto_id === 0): ?>
        Tu ficha no tiene un proyecto formativo asignado actualmente.
      <?php else: ?>
        No hay fases registradas para este proyecto.
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Editar Fase -->
<?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Fase</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="edit_fase_id">
        <div class="modal-body p-4">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Fase N°</label>
              <input type="number" name="numero_fase" id="edit_fase_numero" class="form-control" min="1" required>
            </div>
            <div class="col-md-8">
              <label class="form-label text-muted small fw-semibold">Nombre de la Fase</label>
              <input type="text" name="nombre" id="edit_fase_nombre" class="form-control" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Descripción</label>
            <textarea name="descripcion" id="edit_fase_descripcion" class="form-control" rows="3"></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Inicio</label>
              <input type="date" name="fecha_inicio" id="edit_fase_inicio" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Fin</label>
              <input type="date" name="fecha_fin" id="edit_fase_fin" class="form-control">
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Cumplimiento (%)</label>
              <input type="number" name="cumplimiento_porcentaje" id="edit_fase_cumplimiento" class="form-control" min="0" max="100">
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Estado</label>
              <select name="estado" id="edit_fase_estado" class="form-select">
                <option value="planeada">Planeada</option>
                <option value="en_ejecucion">En Ejecución</option>
                <option value="completada">Completada</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalEditarFase(id, numero, nombre, descripcion, fechaInicio, fechaFin, cumplimiento, estado) {
    document.getElementById('edit_fase_id').value           = id;
    document.getElementById('edit_fase_numero').value       = numero;
    document.getElementById('edit_fase_nombre').value       = nombre;
    document.getElementById('edit_fase_descripcion').value  = descripcion;
    document.getElementById('edit_fase_inicio').value       = fechaInicio;
    document.getElementById('edit_fase_fin').value          = fechaFin;
    document.getElementById('edit_fase_cumplimiento').value = cumplimiento;
    document.getElementById('edit_fase_estado').value       = estado;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
<?php endif; ?>

<!-- Modal Crear Fase -->
<?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
<div class="modal fade" id="modalCrear" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
        <h5 class="modal-title"><i class="bi bi-list-task me-2"></i>Nueva Fase de Proyecto</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="crear">
        <input type="hidden" name="proyecto_id" value="<?= $selected_proyecto_id ?>">
        <div class="modal-body p-4">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Fase N°</label>
              <input type="number" name="numero_fase" class="form-control" placeholder="Ej. 1" min="1" required>
            </div>
            <div class="col-md-8">
              <label class="form-label text-muted small fw-semibold">Nombre de la Fase</label>
              <input type="text" name="nombre" class="form-control" placeholder="Ej. Fase de Análisis" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="Requerimientos, objetivos..."></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Inicio</label>
              <input type="date" name="fecha_inicio" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Fin</label>
              <input type="date" name="fecha_fin" class="form-control">
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Cumplimiento (%)</label>
              <input type="number" name="cumplimiento_porcentaje" class="form-control" value="0" min="0" max="100">
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Estado Inicial</label>
              <select name="estado" class="form-select">
                <option value="planeada">Planeada</option>
                <option value="en_ejecucion">En Ejecución</option>
                <option value="completada">Completada</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear Fase</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
