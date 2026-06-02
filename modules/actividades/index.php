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

$user_id = (int)getCurrentUser()['id'];
$user_rol = getCurrentRole();

// Si es aprendiz, buscar su ficha
$aprendiz_ficha_id = 0;
if ($user_rol === ROL_APRENDIZ) {
    try {
        $stmt = $db->prepare("SELECT ficha_id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $aprendiz_ficha_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        $errors[] = 'Error al verificar ficha de aprendiz.';
    }
}

// Procesar formulario de creación de actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $ficha_id = (int)($_POST['ficha_id'] ?? 0);
        $competencia_id = (int)($_POST['competencia_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $responsable_id = (int)($_POST['responsable_id'] ?? 0);
        $estado = $_POST['estado'] ?? 'pendiente';

        if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha.';
        if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia.';
        if (empty($nombre)) $errors[] = 'El nombre de la actividad es obligatorio.';
        if ($responsable_id <= 0) $errors[] = 'Debe asignar un responsable.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO actividades (ficha_id, competencia_id, nombre, descripcion, fecha_inicio, fecha_fin, responsable_id, estado, cumplimiento_porcentaje)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00)
                ");
                $stmt->execute([$ficha_id, $competencia_id, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $responsable_id, $estado]);

                // Registrar log
                $stmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Crear', 'Actividades', 'actividades', ?, ?)
                ");
                $stmt->execute([$user_id, (int)$db->lastInsertId(), "Creó la actividad $nombre para ficha id $ficha_id"]);

                $successMessage = 'Actividad académica registrada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al registrar actividad: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'No tiene permisos para crear actividades.';
    }
}

// Procesar edición de actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para editar actividades.';
    } else {
        $id             = (int)($_POST['id'] ?? 0);
        $ficha_id       = (int)($_POST['ficha_id'] ?? 0);
        $competencia_id = (int)($_POST['competencia_id'] ?? 0);
        $nombre         = trim($_POST['nombre'] ?? '');
        $descripcion    = trim($_POST['descripcion'] ?? '');
        $fecha_inicio   = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $fecha_fin      = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $responsable_id = (int)($_POST['responsable_id'] ?? 0);
        $estado         = $_POST['estado'] ?? 'pendiente';
        $cumplimiento   = (float)($_POST['cumplimiento_porcentaje'] ?? 0);

        if ($id <= 0)             $errors[] = 'Actividad no válida.';
        if ($ficha_id <= 0)       $errors[] = 'Debe seleccionar una ficha.';
        if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia.';
        if (empty($nombre))       $errors[] = 'El nombre de la actividad es obligatorio.';
        if ($responsable_id <= 0) $errors[] = 'Debe asignar un responsable.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE actividades
                    SET ficha_id=?, competencia_id=?, nombre=?, descripcion=?,
                        fecha_inicio=?, fecha_fin=?, responsable_id=?, estado=?,
                        cumplimiento_porcentaje=?
                    WHERE id=?
                ");
                $stmt->execute([$ficha_id, $competencia_id, $nombre, $descripcion,
                    $fecha_inicio, $fecha_fin, $responsable_id, $estado, $cumplimiento, $id]);

                $stmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Editar', 'Actividades', 'actividades', ?, ?)
                ");
                $stmt->execute([$user_id, $id, "Editó la actividad $nombre"]);

                $successMessage = 'Actividad actualizada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al actualizar actividad: ' . $e->getMessage();
            }
        }
    }
}

// Procesar eliminación de actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para eliminar actividades.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Actividad no válida.';
        } else {
            try {
                $db->prepare("DELETE FROM actividades WHERE id = ?")->execute([$id]);

                $stmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Eliminar', 'Actividades', 'actividades', ?, 'Eliminó la actividad')
                ");
                $stmt->execute([$user_id, $id]);

                $successMessage = 'Actividad eliminada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'No se puede eliminar: la actividad tiene evidencias asociadas.';
            }
        }
    }
}

// Obtener datos auxiliares (solo si es gestor)
$fichas = [];
$competencias = [];
$instructores = [];

if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
    try {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmtF = $db->prepare("SELECT id, numero_ficha, programa_id FROM fichas WHERE instructor_id = ? ORDER BY numero_ficha");
            $stmtF->execute([$user_id]);
            $fichas = $stmtF->fetchAll();
        } else {
            $fichas = $db->query("SELECT id, numero_ficha, programa_id FROM fichas ORDER BY numero_ficha")->fetchAll();
        }
        $competencias = $db->query("SELECT id, codigo, nombre, programa_id FROM competencias WHERE estado = 'activo' ORDER BY codigo")->fetchAll();
        $instructores = $db->query("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre")->fetchAll();
    } catch (Exception $e) {
        $errors[] = 'Error al cargar auxiliares.';
    }
}

// Obtener filtros de búsqueda
$search = trim($_GET['search'] ?? '');
$filter_ficha = (int)($_GET['ficha_id'] ?? 0);
$filter_estado = $_GET['estado'] ?? '';

// Construir consulta de actividades
$sql = "
    SELECT act.*, f.numero_ficha, comp.codigo as comp_codigo, comp.nombre as comp_nombre, u.nombre as responsable_nombre
    FROM actividades act
    JOIN fichas f ON act.ficha_id = f.id
    LEFT JOIN competencias comp ON act.competencia_id = comp.id
    LEFT JOIN usuarios u ON act.responsable_id = u.id
    WHERE 1=1
";
$params = [];

if ($user_rol === ROL_APRENDIZ) {
    $sql .= " AND act.ficha_id = ?";
    $params[] = $aprendiz_ficha_id;
} elseif ($user_rol === ROL_INSTRUCTOR) {
    $sql .= " AND f.instructor_id = ?";
    $params[] = $user_id;
    if ($filter_ficha > 0) {
        $sql .= " AND act.ficha_id = ?";
        $params[] = $filter_ficha;
    }
} else {
    if ($filter_ficha > 0) {
        $sql .= " AND act.ficha_id = ?";
        $params[] = $filter_ficha;
    }
}

if (!empty($search)) {
    $sql .= " AND (act.nombre LIKE ? OR act.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_estado)) {
    $sql .= " AND act.estado = ?";
    $params[] = $filter_estado;
}

$sql .= " ORDER BY act.fecha_fin ASC, act.id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $actividades = $stmt->fetchAll();
} catch (Exception $e) {
    $actividades = [];
    $errors[] = 'Error al cargar las actividades.';
}

$estados_label = [
    'pendiente' => ['Pendiente', 'secondary'],
    'en_progreso' => ['En Progreso', 'warning'],
    'completada' => ['Completada', 'success'],
    'cancelada' => ['Cancelada', 'danger']
];

$pageTitle = 'Actividades Académicas · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Actividades de Aprendizaje</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Visualiza el cronograma de actividades y tareas correspondientes a tu ficha técnica.
      <?php else: ?>
        Planifica y haz seguimiento a las tareas asignadas a cada ficha del centro.
      <?php endif; ?>
    </p>
  </div>
  <?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
    <i class="bi bi-plus-lg me-1"></i> Nueva Actividad
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

<!-- Barra de filtros (Solo para coordinadores/instructores) -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar Actividad</label>
        <div class="input-group">
          <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Nombre de tarea..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select"
                data-picker
                data-picker-label="Filtrar por ficha"
                data-picker-placeholder="Número de ficha...">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>
                    data-search="<?= htmlspecialchars($f['numero_ficha']) ?>">
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Estado de Actividad</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="pendiente" <?= $filter_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
          <option value="en_progreso" <?= $filter_estado === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
          <option value="completada" <?= $filter_estado === 'completada' ? 'selected' : '' ?>>Completada</option>
          <option value="cancelada" <?= $filter_estado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Listado de Actividades -->
<div class="row g-3">
  <?php foreach ($actividades as $act): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card glass-card h-100 border-0 shadow-sm">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="badge bg-soft primary">Ficha #<?= htmlspecialchars($act['numero_ficha']) ?></span>
          <?php $el = $estados_label[$act['estado']] ?? ['Desconocido', 'secondary']; ?>
          <span class="badge-soft <?= $el[1] ?>">
            <?= $el[0] ?>
          </span>
        </div>
        <h5 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($act['nombre']) ?></h5>
        <small class="text-muted d-block font-monospace mb-2" style="font-size:0.75rem;">
          <i class="bi bi-diagram-3 me-1"></i><?= htmlspecialchars($act['comp_codigo'] ?: 'General') ?>
        </small>
        <p class="card-text text-muted small flex-grow-1">
          <?= htmlspecialchars($act['descripcion'] ?: 'Sin descripción provista para esta actividad académica.') ?>
        </p>
        
        <div class="bg-light-soft p-2 rounded mb-3" style="background: rgba(0,0,0,0.02); font-size: 0.8rem;">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Inicio:</span>
            <span class="fw-semibold text-dark"><?= $act['fecha_inicio'] ? date('d/m/Y', strtotime($act['fecha_inicio'])) : 'N/A' ?></span>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Límite:</span>
            <span class="fw-semibold text-danger"><?= $act['fecha_fin'] ? date('d/m/Y', strtotime($act['fecha_fin'])) : 'N/A' ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Instructor:</span>
            <span class="fw-semibold text-dark"><?= htmlspecialchars($act['responsable_nombre'] ?: 'No asignado') ?></span>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <div class="small fw-bold">Avance: <?= (int)$act['cumplimiento_porcentaje'] ?>%</div>
          <?php if ($user_rol === ROL_APRENDIZ && $act['estado'] !== 'completada'): ?>
            <a href="<?= MODULES_PATH ?>/evidencias/" class="btn btn-sm btn-primary">
              <i class="bi bi-upload me-1"></i>Enviar Evidencia
            </a>
          <?php elseif (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-soft"
                onclick="abrirModalEditarActividad(
                  <?= $act['id'] ?>, <?= $act['ficha_id'] ?>, <?= $act['competencia_id'] ?? 0 ?>,
                  <?= json_encode($act['nombre']) ?>, <?= json_encode($act['descripcion'] ?? '') ?>,
                  <?= json_encode($act['fecha_inicio'] ?? '') ?>, <?= json_encode($act['fecha_fin'] ?? '') ?>,
                  <?= $act['responsable_id'] ?? 0 ?>, <?= json_encode($act['estado']) ?>,
                  <?= (float)$act['cumplimiento_porcentaje'] ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('¿Eliminar esta actividad?')">
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="id" value="<?= $act['id'] ?>">
                <button type="submit" class="btn btn-sm btn-soft text-danger">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($actividades)): ?>
  <div class="col-12 text-center py-5 text-muted">
    <i class="bi bi-check2-square d-block mb-2" style="font-size:3rem; opacity:0.3;"></i>
    No hay actividades registradas para mostrar.
  </div>
  <?php endif; ?>
</div>

<!-- Modal Editar Actividad -->
<?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Editar Actividad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="edit_act_id">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Ficha Asociada</label>
              <select name="ficha_id" id="edit_act_ficha" class="form-select" required>
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= $f['id'] ?>" data-programa-id="<?= $f['programa_id'] ?>">Ficha #<?= htmlspecialchars($f['numero_ficha']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Competencia</label>
              <select name="competencia_id" id="edit_act_competencia" class="form-select" required>
                <?php foreach ($competencias as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Título / Nombre</label>
            <input type="text" name="nombre" id="edit_act_nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Descripción</label>
            <textarea name="descripcion" id="edit_act_descripcion" class="form-control" rows="3"></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Inicio</label>
              <input type="date" name="fecha_inicio" id="edit_act_inicio" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Límite</label>
              <input type="date" name="fecha_fin" id="edit_act_fin" class="form-control">
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Instructor</label>
              <select name="responsable_id" id="edit_act_responsable" class="form-select" required>
                <?php foreach ($instructores as $inst): ?>
                  <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Estado</label>
              <select name="estado" id="edit_act_estado" class="form-select">
                <option value="pendiente">Pendiente</option>
                <option value="en_progreso">En Progreso</option>
                <option value="completada">Completada</option>
                <option value="cancelada">Cancelada</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Avance (%)</label>
              <input type="number" name="cumplimiento_porcentaje" id="edit_act_cumplimiento" class="form-control" min="0" max="100">
            </div>
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
const todasCompetencias = <?= json_encode(array_map(function($c) {
    return [
        'id' => (int)$c['id'],
        'codigo' => $c['codigo'],
        'nombre' => $c['nombre'],
        'programa_id' => (int)$c['programa_id']
    ];
}, $competencias)) ?>;

function filtrarCompetencias(fichaSelectId, competenciaSelectId) {
    const fichaSelect = document.getElementById(fichaSelectId);
    const competenciaSelect = document.getElementById(competenciaSelectId);
    if (!fichaSelect || !competenciaSelect) return;

    const selectedOption = fichaSelect.options[fichaSelect.selectedIndex];
    const programaId = selectedOption ? parseInt(selectedOption.dataset.programaId || 0, 10) : 0;

    const prevValue = competenciaSelect.value;
    competenciaSelect.innerHTML = '<option value="" disabled selected>Seleccione...</option>';

    if (programaId > 0) {
        todasCompetencias.forEach(c => {
            if (c.programa_id === programaId) {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.codigo + ' — ' + c.nombre;
                opt.dataset.search = c.codigo + ' ' + c.nombre;
                if (String(c.id) === String(prevValue)) {
                    opt.selected = true;
                }
                competenciaSelect.appendChild(opt);
            }
        });
    }

    // Si es un searchable-picker, notificar el cambio
    competenciaSelect.dispatchEvent(new Event('change', { bubbles: true }));
}

// Escuchar cambios en la selección de ficha del modal de creación
document.getElementById('crear_act_ficha')?.addEventListener('change', function() {
    filtrarCompetencias('crear_act_ficha', 'crear_act_competencia');
});

// Escuchar cambios en la selección de ficha del modal de edición
document.getElementById('edit_act_ficha')?.addEventListener('change', function() {
    filtrarCompetencias('edit_act_ficha', 'edit_act_competencia');
});

function abrirModalEditarActividad(id, fichaId, competenciaId, nombre, descripcion, fechaInicio, fechaFin, responsableId, estado, cumplimiento) {
    document.getElementById('edit_act_id').value           = id;
    document.getElementById('edit_act_ficha').value        = fichaId;
    
    // Filtrar competencias para la ficha seleccionada antes de setear el valor de la competencia
    filtrarCompetencias('edit_act_ficha', 'edit_act_competencia');
    
    document.getElementById('edit_act_competencia').value  = competenciaId;
    document.getElementById('edit_act_nombre').value       = nombre;
    document.getElementById('edit_act_descripcion').value  = descripcion;
    document.getElementById('edit_act_inicio').value       = fechaInicio;
    document.getElementById('edit_act_fin').value          = fechaFin;
    document.getElementById('edit_act_responsable').value  = responsableId;
    document.getElementById('edit_act_estado').value       = estado;
    document.getElementById('edit_act_cumplimiento').value = cumplimiento;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
<?php endif; ?>

<!-- Modal Registrar Actividad -->
<?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
<div class="modal fade" id="modalCrear" tabindex="-1" aria-labelledby="modalCrearLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalCrearLabel">Nueva Actividad de Aprendizaje</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="crear">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Ficha Asociada</label>
              <select name="ficha_id" id="crear_act_ficha" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar ficha"
                      data-picker-placeholder="Número de ficha...">
                <option value="" disabled selected>Seleccione Ficha...</option>
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= $f['id'] ?>"
                          data-search="<?= htmlspecialchars($f['numero_ficha']) ?>"
                          data-programa-id="<?= $f['programa_id'] ?>">
                    Ficha #<?= htmlspecialchars($f['numero_ficha']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Competencia Relacionada</label>
              <select name="competencia_id" id="crear_act_competencia" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar competencia"
                      data-picker-placeholder="Código o nombre de la competencia...">
                <option value="" disabled selected>Seleccione...</option>
                <?php foreach ($competencias as $c): ?>
                  <option value="<?= $c['id'] ?>"
                          data-search="<?= htmlspecialchars($c['codigo'] . ' ' . $c['nombre']) ?>"
                          data-programa-id="<?= $c['programa_id'] ?>">
                    <?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Título / Nombre de la Tarea</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. Taller Práctico de CSS Grid" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Descripción del Entregable</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="Instrucciones, requerimientos técnicos, links..."></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha Inicio</label>
              <input type="date" name="fecha_inicio" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Fecha de Vencimiento</label>
              <input type="date" name="fecha_fin" class="form-control" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Instructor Responsable</label>
              <select name="responsable_id" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar instructor responsable"
                      data-picker-placeholder="Nombre del instructor...">
                <option value="" disabled selected>Asignar a...</option>
                <?php foreach ($instructores as $inst): ?>
                  <option value="<?= $inst['id'] ?>" <?= $inst['id'] == $user_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($inst['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Estado Inicial</label>
              <select name="estado" class="form-select">
                <option value="pendiente">Pendiente</option>
                <option value="en_progreso">En Progreso</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear Actividad</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
