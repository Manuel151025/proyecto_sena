<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$db = Database::getConnection();
$errors = [];

// Obtener filtros
$search = trim($_GET['search'] ?? '');
$filter_accion = $_GET['accion'] ?? '';

// Consulta de logs
$sql = "
    SELECT logs.*, u.nombre as usuario_nombre, u.email as usuario_email, u.rol as usuario_rol
    FROM logs_sistema logs
    LEFT JOIN usuarios u ON logs.usuario_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nombre LIKE ? OR logs.descripcion LIKE ? OR logs.modulo LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_accion)) {
    $sql .= " AND logs.accion = ?";
    $params[] = $filter_accion;
}

$sql .= " ORDER BY logs.fecha DESC LIMIT 100";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $logs = [];
    $errors[] = 'Error al cargar los registros de auditoría: ' . $e->getMessage();
}

$acciones_badge = [
    'Crear' => 'success',
    'Calificar' => 'primary',
    'Modificar' => 'warning',
    'Eliminar' => 'danger',
    'Login' => 'info',
    'Logout' => 'secondary'
];

$pageTitle = 'Bitácora de Auditoría · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="mb-3">
  <h1 class="mb-1">Auditoría del Sistema</h1>
  <p class="text-muted mb-0">Revisa la bitácora de acciones y modificaciones del sistema para control de calidad y trazabilidad.</p>
</div>

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
      <div class="col-md-6">
        <label class="form-label text-muted small">Buscar por Usuario, Descripción o Módulo</label>
        <div class="input-group">
          <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Buscar..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label text-muted small">Acción realizada</label>
        <select name="accion" class="form-select">
          <option value="">Todas las acciones</option>
          <option value="Crear" <?= $filter_accion === 'Crear' ? 'selected' : '' ?>>Crear</option>
          <option value="Calificar" <?= $filter_accion === 'Calificar' ? 'selected' : '' ?>>Calificar</option>
          <option value="Modificar" <?= $filter_accion === 'Modificar' ? 'selected' : '' ?>>Modificar</option>
          <option value="Eliminar" <?= $filter_accion === 'Eliminar' ? 'selected' : '' ?>>Eliminar</option>
          <option value="Login" <?= $filter_accion === 'Login' ? 'selected' : '' ?>>Login</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Bitácora de Auditoría -->
<div class="card glass-card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle table-hover">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Fecha / Hora</th>
            <th>Usuario</th>
            <th>Acción</th>
            <th>Módulo</th>
            <th>Descripción</th>
            <th class="pe-4">IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td class="ps-4 text-muted small">
              <?= date('d/m/Y h:i:s A', strtotime($log['fecha'])) ?>
            </td>
            <td>
              <div class="fw-semibold text-dark"><?= htmlspecialchars($log['usuario_nombre'] ?: 'Sistema / Anon') ?></div>
              <small class="text-muted"><?= htmlspecialchars($log['usuario_email'] ?: '') ?></small>
            </td>
            <td>
              <span class="badge bg-<?= $acciones_badge[$log['accion']] ?? 'secondary' ?>">
                <?= htmlspecialchars($log['accion']) ?>
              </span>
            </td>
            <td>
              <span class="badge bg-soft info"><?= htmlspecialchars($log['modulo'] ?: 'General') ?></span>
            </td>
            <td>
              <div class="text-wrap small text-dark" style="max-width: 400px;">
                <?= htmlspecialchars($log['descripcion']) ?>
              </div>
              <small class="text-muted">ID Registro: <?= $log['id_registro'] ?: 'N/A' ?></small>
            </td>
            <td class="pe-4 font-monospace small text-muted">
              <?= htmlspecialchars($log['ip_address'] ?: '127.0.0.1') ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-shield-check d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
              No hay logs registrados en la bitácora todavía.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
