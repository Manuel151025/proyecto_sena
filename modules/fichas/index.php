<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$pageTitle = 'Fichas de formación · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$db = Database::getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Eliminar ficha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id = (int) $_POST['id'];
        $stmt = $db->prepare("DELETE FROM fichas WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = 'Ficha eliminada correctamente';
        $tipo_mensaje = 'success';
    } catch (Exception $e) {
        $mensaje = 'Error al eliminar ficha';
        $tipo_mensaje = 'danger';
    }
}

// Obtener fichas con información de programa e instructor
try {
    $stmt = $db->prepare("
        SELECT 
            f.id, 
            f.numero_ficha, 
            f.estado, 
            f.cantidad_aprendices, 
            f.fecha_fin,
            f.cumplimiento_porcentaje,
            p.nombre as programa,
            p.codigo as codigo_programa,
            u.nombre as instructor,
            u.id as instructor_id
        FROM fichas f
        JOIN programas p ON f.programa_id = p.id
        JOIN usuarios u ON f.instructor_id = u.id
        ORDER BY f.fecha_fin DESC
    ");
    $stmt->execute();
    $fichas = $stmt->fetchAll();
} catch (Exception $e) {
    $fichas = [];
    $mensaje = 'Error al cargar fichas';
    $tipo_mensaje = 'danger';
}

$estados_label = [
    'planeacion' => ['Planeación', 'primary'],
    'induccion' => ['Inducción', 'info'],
    'ejecucion' => ['Ejecución', 'warning'],
    'cierre' => ['Cierre', 'success']
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Fichas de formación</h1>
    <p class="text-muted mb-0">Gestiona el ciclo completo de cada ficha.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= MODULES_PATH ?>/fichas/crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva Ficha</a>
  </div>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-3">
  <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
</div>
<?php endif; ?>

<div class="toolbar">
  <div class="search"><i class="bi bi-search"></i><input class="form-control" id="searchFichas" placeholder="Buscar ficha o programa..."></div>
  <select class="form-select" id="filterEstado" style="max-width:180px">
    <option value="">Todos los estados</option>
    <option value="planeacion">Planeación</option>
    <option value="induccion">Inducción</option>
    <option value="ejecucion">Ejecución</option>
    <option value="cierre">Cierre</option>
  </select>
  <select class="form-select" id="filterPrograma" style="max-width:200px">
    <option value="">Todos los programas</option>
    <?php
    $stmt = $db->prepare("SELECT DISTINCT codigo, nombre FROM programas ORDER BY nombre");
    $stmt->execute();
    $programas = $stmt->fetchAll();
    foreach ($programas as $prog):
    ?>
    <option value="<?= htmlspecialchars($prog['codigo']) ?>"><?= htmlspecialchars($prog['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Ficha</th>
        <th>Programa</th>
        <th>Instructor responsable</th>
        <th>Estado</th>
        <th>Aprendices</th>
        <th>Fecha fin</th>
        <th>Cumplimiento</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($fichas as $ficha): ?>
      <tr class="ficha-row" data-estado="<?= $ficha['estado'] ?>" data-programa="<?= htmlspecialchars($ficha['codigo_programa']) ?>">
        <td><strong>#<?= htmlspecialchars($ficha['numero_ficha']) ?></strong></td>
        <td><?= htmlspecialchars($ficha['programa']) ?></td>
        <td><?= htmlspecialchars($ficha['instructor']) ?></td>
        <td><span class="badge-soft <?= $estados_label[$ficha['estado']][1] ?>"><?= $estados_label[$ficha['estado']][0] ?></span></td>
        <td><?= $ficha['cantidad_aprendices'] ?></td>
        <td><?= $ficha['fecha_fin'] ? date('d/m/Y', strtotime($ficha['fecha_fin'])) : 'N/A' ?></td>
        <td>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 60px; height: 24px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
              <div style="height: 100%; width: <?= $ficha['cumplimiento_porcentaje'] ?>%; background: <?= $ficha['cumplimiento_porcentaje'] >= 75 ? '#22c55e' : ($ficha['cumplimiento_porcentaje'] >= 50 ? '#eab308' : '#ef4444') ?>; transition: width 0.3s;"></div>
            </div>
            <span style="font-size: 0.85rem; font-weight: 500;"><?= (int)$ficha['cumplimiento_porcentaje'] ?>%</span>
          </div>
        </td>
        <td class="text-end">
          <a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-sm btn-soft">Abrir</a>
          <a href="<?= MODULES_PATH ?>/fichas/editar.php?id=<?= $ficha['id'] ?>" class="btn btn-sm btn-soft">Editar</a>
          <button type="button" class="btn btn-sm btn-soft text-danger" onclick="deleteSheet(<?= $ficha['id'] ?>)">Eliminar</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form id="deleteForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<script>
function deleteSheet(id) {
  if (confirm('¿Estás seguro de que deseas eliminar esta ficha?')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}

// Buscar fichas
document.getElementById('searchFichas').addEventListener('keyup', filterFichas);
document.getElementById('filterEstado').addEventListener('change', filterFichas);
document.getElementById('filterPrograma').addEventListener('change', filterFichas);

function filterFichas() {
  const searchTerm = document.getElementById('searchFichas').value.toLowerCase();
  const estadoFilter = document.getElementById('filterEstado').value;
  const programaFilter = document.getElementById('filterPrograma').value;
  
  const rows = document.querySelectorAll('.ficha-row');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    const estado = row.dataset.estado;
    const programa = row.dataset.programa;
    
    const matchSearch = text.includes(searchTerm);
    const matchEstado = !estadoFilter || estado === estadoFilter;
    const matchPrograma = !programaFilter || programa === programaFilter;
    
    row.style.display = (matchSearch && matchEstado && matchPrograma) ? '' : 'none';
  });
}
</script>
