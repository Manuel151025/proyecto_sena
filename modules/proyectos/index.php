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
$success = '';
$user_rol = getCurrentRole();

// Procesar creación de proyecto (solo coordinador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear' && $user_rol === ROL_COORDINADOR) {
        try {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $objetivo = trim($_POST['objetivo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre) || empty($codigo)) {
                throw new Exception('El nombre y código son obligatorios.');
            }

            $stmt = $db->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, descripcion) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $codigo, $objetivo, $descripcion]);
            $success = 'Proyecto formativo creado exitosamente.';
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
    if ($_POST['action'] === 'delete' && $user_rol === ROL_COORDINADOR) {
        try {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM proyectos WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Proyecto eliminado correctamente.';
        } catch (Exception $e) {
            $errors[] = 'No se puede eliminar: el proyecto tiene fichas o fases asociadas.';
        }
    }
    if ($_POST['action'] === 'editar' && $user_rol === ROL_COORDINADOR) {
        try {
            $id          = (int)($_POST['id'] ?? 0);
            $nombre      = trim($_POST['nombre'] ?? '');
            $codigo      = trim($_POST['codigo'] ?? '');
            $objetivo    = trim($_POST['objetivo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $estado      = $_POST['estado'] ?? 'activo';

            if (empty($nombre) || empty($codigo)) {
                throw new Exception('El nombre y código son obligatorios.');
            }
            $stmt = $db->prepare("
                UPDATE proyectos SET nombre=?, codigo=?, objetivo=?, descripcion=?, estado=?
                WHERE id=?
            ");
            $stmt->execute([$nombre, $codigo, $objetivo, $descripcion, $estado, $id]);
            $success = 'Proyecto actualizado correctamente.';
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Obtener proyectos con fichas y fases asociadas
$proyectos = [];
try {
    $user_id = (int)getCurrentUser()['id'];
    if ($user_rol === ROL_APRENDIZ) {
        $stmt = $db->prepare("
            SELECT 
                pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.estado,
                COUNT(DISTINCT f.id) as total_fichas,
                SUM(f.cantidad_aprendices) as total_aprendices,
                COUNT(DISTINCT fp.id) as total_fases,
                SUM(CASE WHEN fp.estado = 'completada' THEN 1 ELSE 0 END) as fases_completadas,
                AVG(fp.cumplimiento_porcentaje) as avance_promedio
            FROM proyectos pr
            JOIN fichas f ON f.proyecto_id = pr.id
            JOIN aprendices ap ON ap.ficha_id = f.id
            LEFT JOIN fases_proyecto fp ON fp.proyecto_id = pr.id
            WHERE ap.usuario_id = ?
            GROUP BY pr.id
            ORDER BY pr.nombre
        ");
        $stmt->execute([$user_id]);
        $proyectos = $stmt->fetchAll();
    } elseif ($user_rol === ROL_INSTRUCTOR) {
        $stmt = $db->prepare("
            SELECT 
                pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.estado,
                COUNT(DISTINCT f.id) as total_fichas,
                SUM(f.cantidad_aprendices) as total_aprendices,
                COUNT(DISTINCT fp.id) as total_fases,
                SUM(CASE WHEN fp.estado = 'completada' THEN 1 ELSE 0 END) as fases_completadas,
                AVG(fp.cumplimiento_porcentaje) as avance_promedio
            FROM proyectos pr
            JOIN fichas f ON f.proyecto_id = pr.id
            LEFT JOIN fases_proyecto fp ON fp.proyecto_id = pr.id
            WHERE f.instructor_id = ?
            GROUP BY pr.id
            ORDER BY pr.nombre
        ");
        $stmt->execute([$user_id]);
        $proyectos = $stmt->fetchAll();
    } else {
        $proyectos = $db->query("
            SELECT 
                pr.id, pr.nombre, pr.codigo, pr.objetivo, pr.estado,
                COUNT(DISTINCT f.id) as total_fichas,
                SUM(f.cantidad_aprendices) as total_aprendices,
                COUNT(DISTINCT fp.id) as total_fases,
                SUM(CASE WHEN fp.estado = 'completada' THEN 1 ELSE 0 END) as fases_completadas,
                AVG(fp.cumplimiento_porcentaje) as avance_promedio
            FROM proyectos pr
            LEFT JOIN fichas f ON f.proyecto_id = pr.id
            LEFT JOIN fases_proyecto fp ON fp.proyecto_id = pr.id
            GROUP BY pr.id
            ORDER BY pr.nombre
        ")->fetchAll();
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar los proyectos: ' . $e->getMessage();
}

$pageTitle = 'Proyectos Formativos · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Proyectos Formativos</h1>
    <p class="text-muted mb-0">Cada proyecto integra las competencias y resultados de aprendizaje de un programa de formación.</p>
  </div>
  <?php if ($user_rol === ROL_COORDINADOR): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
    <i class="bi bi-plus-lg me-1"></i>Nuevo Proyecto
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-flat success mb-3"><i class="bi bi-check-circle-fill"></i><div><?= htmlspecialchars($success) ?></div></div>
<?php endif; ?>

<div class="row g-4">
  <?php foreach ($proyectos as $proj): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid var(--sena-primary); border-radius: 12px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 30px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.05)';">
        <div class="card-body d-flex flex-column p-4">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft primary fw-semibold" style="letter-spacing: 0.5px;"><?= htmlspecialchars($proj['codigo']) ?></span>
            <span class="badge-soft <?= $proj['estado'] === 'activo' ? 'success' : ($proj['estado'] === 'finalizado' ? 'info' : 'secondary') ?>"><?= ucfirst($proj['estado']) ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($proj['nombre']) ?></h5>
          <small class="text-muted d-block mb-3" style="max-height: 40px; overflow: hidden;"><?= htmlspecialchars($proj['objetivo'] ?? 'Sin objetivo definido') ?></small>
          
          <div class="p-3 rounded-3 mb-3 flex-grow-1" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.04); font-size: 0.85rem;">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-folder2-open me-1"></i>Fichas vinculadas</span>
              <span class="fw-bold"><?= (int)$proj['total_fichas'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-people me-1"></i>Aprendices</span>
              <span class="fw-bold"><?= (int)$proj['total_aprendices'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted"><i class="bi bi-list-task me-1"></i>Fases</span>
              <span class="fw-bold text-success"><?= (int)$proj['fases_completadas'] ?> / <?= (int)$proj['total_fases'] ?></span>
            </div>
            
            <div class="text-muted small mb-1">Avance del proyecto:</div>
            <div class="progress" style="height: 8px; border-radius: 10px;">
              <?php $avance = (int)($proj['avance_promedio'] ?? 0); ?>
              <div class="progress-bar" role="progressbar" style="width: <?= $avance ?>%; background: <?= $avance >= 75 ? 'var(--sena-primary)' : ($avance >= 40 ? '#eab308' : '#ef4444') ?>; border-radius: 10px;"></div>
            </div>
            <div class="text-end fw-bold mt-1" style="font-size: 0.8rem;"><?= $avance ?>%</div>
          </div>

          <div class="d-flex gap-2">
            <a href="<?= MODULES_PATH ?>/fases/?proyecto_id=<?= $proj['id'] ?>" class="btn btn-primary flex-grow-1" style="border-radius: 8px;">
              <i class="bi bi-list-task me-1"></i>Ver Fases
            </a>
            <?php if ($user_rol === ROL_COORDINADOR): ?>
             <button class="btn btn-soft px-3" style="border-radius: 8px;"
              onclick="abrirModalEditarProyecto(
                <?= $proj['id'] ?>, <?= htmlspecialchars(json_encode($proj['nombre']), ENT_QUOTES, 'UTF-8') ?>,
                <?= htmlspecialchars(json_encode($proj['codigo']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($proj['objetivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                <?= htmlspecialchars(json_encode($proj['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($proj['estado']), ENT_QUOTES, 'UTF-8') ?>)">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $proj['id'] ?>">
              <button type="submit" class="btn btn-soft text-danger px-3" style="border-radius: 8px;"
                onclick="return confirm('¿Eliminar el proyecto <?= htmlspecialchars(addslashes($proj['nombre'])) ?>?')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($proyectos)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-kanban d-block mb-2" style="font-size:3rem; opacity:0.3;"></i>
      No hay proyectos formativos creados.
    </div>
  <?php endif; ?>
</div>

<!-- Modal Editar Proyecto -->
<?php if ($user_rol === ROL_COORDINADOR): ?>
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <form method="POST">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Proyecto Formativo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre del Proyecto <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
            <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Objetivo</label>
            <textarea name="objetivo" id="edit_objetivo" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Descripción</label>
            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Estado</label>
            <select name="estado" id="edit_estado" class="form-select">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
              <option value="finalizado">Finalizado</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalEditarProyecto(id, nombre, codigo, objetivo, descripcion, estado) {
    document.getElementById('edit_id').value          = id;
    document.getElementById('edit_nombre').value      = nombre;
    document.getElementById('edit_codigo').value      = codigo;
    document.getElementById('edit_objetivo').value    = objetivo;
    document.getElementById('edit_descripcion').value = descripcion;
    document.getElementById('edit_estado').value      = estado;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
<?php endif; ?>

<!-- Modal Crear Proyecto -->
<?php if ($user_rol === ROL_COORDINADOR): ?>
<div class="modal fade" id="modalCrear" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <form method="POST">
        <input type="hidden" name="action" value="crear">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
          <h5 class="modal-title"><i class="bi bi-kanban me-2"></i>Nuevo Proyecto Formativo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre del Proyecto <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Sistema de Gestión de Inventarios Web">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
            <input type="text" name="codigo" class="form-control" required placeholder="Ej: PF-ADSO-02">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Objetivo</label>
            <textarea name="objetivo" class="form-control" rows="2" placeholder="Objetivo general del proyecto formativo"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción ampliada del proyecto"></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Crear Proyecto</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
