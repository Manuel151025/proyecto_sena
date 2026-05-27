<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$pageTitle = 'Programas de Formación · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$db = Database::getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Eliminar programa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id = (int) $_POST['id'];
        $stmt = $db->prepare("DELETE FROM programas WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = 'Programa eliminado correctamente';
        $tipo_mensaje = 'success';
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $mensaje = 'No se puede eliminar el programa porque tiene fichas de formación asociadas u otros registros vinculados.';
        } else {
            $mensaje = 'Error de base de datos al eliminar el programa: ' . $e->getMessage();
        }
        $tipo_mensaje = 'danger';
    } catch (Exception $e) {
        $mensaje = 'Error al eliminar el programa: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Obtener programas
try {
    $stmt = $db->prepare("SELECT id, nombre, codigo, descripcion, duracion_horas, estado, fecha_creacion FROM programas ORDER BY fecha_creacion DESC");
    $stmt->execute();
    $programas = $stmt->fetchAll();
} catch (Exception $e) {
    $programas = [];
    $mensaje = 'Error al cargar programas';
    $tipo_mensaje = 'danger';
}

$estados_label = [
    'activo' => ['Activo', 'success'],
    'inactivo' => ['Inactivo', 'warning'],
    'archivado' => ['Archivado', 'info']
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Programas de Formación</h1>
    <p class="text-muted mb-0">Administra todos los programas de formación disponibles.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= MODULES_PATH ?>/programas/crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo Programa</a>
  </div>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
  <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
</div>
<?php endif; ?>

<div class="toolbar mb-3">
  <div class="search"><i class="bi bi-search"></i><input class="form-control" id="searchPrograms" placeholder="Buscar programa..."></div>
  <select class="form-select" style="max-width:180px" id="filterStatus">
    <option value="">Todos los estados</option>
    <option value="activo">Activo</option>
    <option value="inactivo">Inactivo</option>
    <option value="archivado">Archivado</option>
  </select>
</div>

<div class="row g-3">
  <?php foreach ($programas as $programa): ?>
  <div class="col-lg-6" data-status="<?= htmlspecialchars($programa['estado']) ?>" data-name="<?= htmlspecialchars($programa['nombre']) ?>">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5><?= htmlspecialchars($programa['nombre']) ?></h5>
            <p class="text-muted mb-2"><code><?= htmlspecialchars($programa['codigo']) ?></code></p>
            <p style="font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem;">
              <?= htmlspecialchars(substr($programa['descripcion'] ?? '', 0, 100)) ?>...
            </p>
            <div style="display: flex; gap: 1rem; font-size: 0.85rem;">
              <span><strong><?= $programa['duracion_horas'] ?? 0 ?></strong> horas</span>
              <span class="badge-soft <?= $estados_label[$programa['estado']][1] ?>"><?= $estados_label[$programa['estado']][0] ?></span>
            </div>
          </div>
          <div class="d-flex gap-1">
            <a href="<?= MODULES_PATH ?>/programas/editar.php?id=<?= $programa['id'] ?>" class="btn btn-sm btn-soft"><i class="bi bi-pencil"></i></a>
            <button type="button" class="btn btn-sm btn-soft text-danger" onclick="deleteProgram(<?= $programa['id'] ?>)"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<form id="deleteForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<script>
function deleteProgram(id) {
  if (confirm('¿Estás seguro de que deseas eliminar este programa?')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}

// Búsqueda
document.getElementById('searchPrograms').addEventListener('keyup', function(e) {
  filterPrograms();
});

document.getElementById('filterStatus').addEventListener('change', function(e) {
  filterPrograms();
});

function filterPrograms() {
  const searchFilter = document.getElementById('searchPrograms').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value;
  const cards = document.querySelectorAll('[data-status]');

  cards.forEach(card => {
    const name = card.dataset.name.toLowerCase();
    const status = card.dataset.status;
    
    const matchSearch = name.includes(searchFilter);
    const matchStatus = !statusFilter || status === statusFilter;
    
    card.style.display = (matchSearch && matchStatus) ? '' : 'none';
  });
}
</script>
