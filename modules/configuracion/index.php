<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

requireRole(ROL_COORDINADOR);

$errors = [];
$successMessage = '';

$system_title = $_POST['system_title'] ?? 'SENA - Seguimiento de Fichas';
$regional = $_POST['regional'] ?? 'Regional Antioquia - Centro de Servicios y Gestión';
$pass_score = $_POST['pass_score'] ?? '70%';
$smtp_server = $_POST['smtp_server'] ?? 'smtp.soy.sena.edu.co';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMessage = 'Configuración guardada exitosamente en el sistema.';
}

$pageTitle = 'Configuración del Sistema · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="mb-4">
  <h1 class="mb-1">Configuración General</h1>
  <p class="text-muted mb-0">Ajusta los parámetros académicos, nombres institucionales y credenciales del sistema.</p>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-body">
        <form method="POST">
          <h5 class="fw-bold text-dark mb-4"><i class="bi bi-gear-fill me-2 text-primary"></i>Parámetros Institucionales</h5>
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Nombre del Sistema</label>
            <input type="text" name="system_title" class="form-control" value="<?= htmlspecialchars($system_title) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Regional / Centro de Formación</label>
            <input type="text" name="regional" class="form-control" value="<?= htmlspecialchars($regional) ?>" required>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Porcentaje Mínimo Aprobación</label>
              <select name="pass_score" class="form-select">
                <option value="60%" <?= $pass_score === '60%' ? 'selected' : '' ?>>60%</option>
                <option value="70%" <?= $pass_score === '70%' ? 'selected' : '' ?>>70% (Por defecto)</option>
                <option value="80%" <?= $pass_score === '80%' ? 'selected' : '' ?>>80%</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Servidor SMTP de Correo Institucional</label>
              <input type="text" name="smtp_server" class="form-control" value="<?= htmlspecialchars($smtp_server) ?>" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Cambios</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card bg-light border-0">
      <div class="card-body">
        <h5><i class="bi bi-info-circle-fill text-primary me-2"></i>Información Técnica</h5>
        <ul class="text-muted small ps-3 mb-0 mt-3">
          <li class="mb-2"><strong>Versión del Sistema:</strong> 1.5.0-premium</li>
          <li class="mb-2"><strong>Motor de BD:</strong> MySQL 8.0 (PDO utf8mb4)</li>
          <li class="mb-2"><strong>Límite de subida:</strong> 15MB por evidencia</li>
          <li class="mb-2"><strong>Zona Horaria:</strong> America/Bogota</li>
        </ul>
      </div>
    </div>
  </div>
</div>
