<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$pageTitle = 'Dashboard Coordinador · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$user = getCurrentUser();
$nombreUsuario = htmlspecialchars($user['nombre']);
$db = Database::getConnection();

// KPIs desde BD
try {
    // Fichas activas
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM fichas WHERE estado IN ('induccion', 'ejecucion')");
    $stmt->execute();
    $fichasActivas = $stmt->fetch()['count'] ?? 0;

    // Aprendices matriculados
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM aprendices WHERE estado = 'matriculado'");
    $stmt->execute();
    $aprendicesMatriculados = $stmt->fetch()['count'] ?? 0;

    // Instructores activos
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'instructor' AND estado = 'activo'");
    $stmt->execute();
    $instructoresActivos = $stmt->fetch()['count'] ?? 0;

    // Promedio retención (cumplimiento promedio)
    $stmt = $db->prepare("SELECT AVG(cumplimiento_porcentaje) as promedio FROM fichas WHERE cumplimiento_porcentaje > 0");
    $stmt->execute();
    $retencioPromedio = round($stmt->fetch()['promedio'] ?? 0, 1);

    // Fichas críticas (< 60%)
    $stmt = $db->prepare("SELECT f.id, f.numero_ficha, p.nombre as programa, u.nombre as instructor, f.cumplimiento_porcentaje, f.estado FROM fichas f JOIN programas p ON f.programa_id = p.id JOIN usuarios u ON f.instructor_id = u.id WHERE f.cumplimiento_porcentaje < 60 ORDER BY f.cumplimiento_porcentaje ASC LIMIT 5");
    $stmt->execute();
    $fichasCriticas = $stmt->fetchAll();

    // Cumplimiento por programa
    $stmt = $db->prepare("SELECT p.nombre, AVG(f.cumplimiento_porcentaje) as promedio FROM fichas f JOIN programas p ON f.programa_id = p.id GROUP BY p.id, p.nombre ORDER BY promedio DESC");
    $stmt->execute();
    $cumplimientoProgramas = $stmt->fetchAll();

} catch (Exception $e) {
    $fichasActivas = 0;
    $aprendicesMatriculados = 0;
    $instructoresActivos = 0;
    $retencioPromedio = 0;
    $fichasCriticas = [];
    $cumplimientoProgramas = [];
}

?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Hola, <?= $nombreUsuario ?> 👋</h1>
    <p class="text-muted mb-0">Resumen institucional de seguimiento académico.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= MODULES_PATH ?>/usuarios/crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo Usuario</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="icon-bg"><i class="bi bi-journal-bookmark"></i></div>
      <div class="label">Fichas Activas</div>
      <div class="value"><?= $fichasActivas ?></div>
      <div class="trend up"><i class="bi bi-info-circle"></i> En ejecución e inducción</div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="icon-bg"><i class="bi bi-people"></i></div>
      <div class="label">Aprendices Matriculados</div>
      <div class="value"><?= $aprendicesMatriculados ?></div>
      <div class="trend up"><i class="bi bi-info-circle"></i> Estado activo</div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="icon-bg"><i class="bi bi-person-workspace"></i></div>
      <div class="label">Instructores Activos</div>
      <div class="value"><?= $instructoresActivos ?></div>
      <div class="trend up"><i class="bi bi-info-circle"></i> Habilitados en sistema</div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="icon-bg"><i class="bi bi-graph-up"></i></div>
      <div class="label">Retención Promedio</div>
      <div class="value"><?= $retencioPromedio ?>%</div>
      <div class="trend up"><i class="bi bi-info-circle"></i> Cumplimiento general</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        Cumplimiento por programa de formación
        <span class="badge-soft">Datos actuales</span>
      </div>
      <div class="card-body">
        <canvas id="chartProg" height="120"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        Estado de fichas
      </div>
      <div class="card-body">
        <canvas id="chartPie" height="180"></canvas>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($fichasCriticas)): ?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-exclamation-triangle text-danger me-2"></i>Alertas críticas — Fichas con cumplimiento menor al 60%</span>
    <a href="<?= MODULES_PATH ?>/fichas/" class="small">Ver todas</a>
  </div>
  <div class="table-wrap" style="border:0;border-radius:0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Ficha</th>
          <th>Programa</th>
          <th>Instructor</th>
          <th>Cumplimiento</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fichasCriticas as $ficha): ?>
        <tr style="background:var(--danger-bg)">
          <td><strong>#<?= htmlspecialchars($ficha['numero_ficha']) ?></strong></td>
          <td><?= htmlspecialchars($ficha['programa']) ?></td>
          <td><?= htmlspecialchars($ficha['instructor']) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress-flat danger" style="width:120px">
                <div style="width:<?= $ficha['cumplimiento_porcentaje'] ?>%"></div>
              </div>
              <span class="text-danger fw-semibold"><?= round($ficha['cumplimiento_porcentaje'], 1) ?>%</span>
            </div>
          </td>
          <td><span class="badge-soft danger"><?= htmlspecialchars($ficha['estado']) ?></span></td>
          <td><a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-sm btn-soft">Ver</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-4"><a href="<?= MODULES_PATH ?>/fichas/" class="btn btn-soft w-100 py-3"><i class="bi bi-journal-bookmark me-2"></i>Gestionar Fichas</a></div>
  <div class="col-md-4"><a href="<?= MODULES_PATH ?>/programas/" class="btn btn-soft w-100 py-3"><i class="bi bi-briefcase me-2"></i>Ver Programas</a></div>
  <div class="col-md-4"><a href="<?= MODULES_PATH ?>/usuarios/crear.php" class="btn btn-primary w-100 py-3"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</a></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const css = getComputedStyle(document.documentElement);
    const green = css.getPropertyValue('--sena-primary').trim();

    // Chart de cumplimiento por programa
    if (document.getElementById('chartProg')) {
        const programasData = <?php echo json_encode(array_map(fn($p) => ['nombre' => $p['nombre'], 'promedio' => round($p['promedio'], 1)], $cumplimientoProgramas)); ?>;
        const labels = programasData.map(p => p.nombre);
        const data = programasData.map(p => p.promedio);
        
        new Chart(document.getElementById('chartProg'), {
            type: 'bar',
            data: {
                labels: labels.length > 0 ? labels : ['Sin datos'],
                datasets: [{
                    label: 'Cumplimiento %',
                    data: data.length > 0 ? data : [0],
                    backgroundColor: green,
                    borderRadius: 6
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    }

    // Chart de estados de fichas
    if (document.getElementById('chartPie')) {
        new Chart(document.getElementById('chartPie'), {
            type: 'doughnut',
            data: {
                labels: ['Ejecución', 'Inducción', 'Planeación', 'Cierre'],
                datasets: [{
                    data: [
                        <?php 
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM fichas WHERE estado = 'ejecucion'");
                            $stmt->execute();
                            echo $stmt->fetch()[0];
                        } catch (Exception $e) { echo '0'; }
                        ?>,
                        <?php 
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM fichas WHERE estado = 'induccion'");
                            $stmt->execute();
                            echo $stmt->fetch()[0];
                        } catch (Exception $e) { echo '0'; }
                        ?>,
                        <?php 
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM fichas WHERE estado = 'planeacion'");
                            $stmt->execute();
                            echo $stmt->fetch()[0];
                        } catch (Exception $e) { echo '0'; }
                        ?>,
                        <?php 
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM fichas WHERE estado = 'cierre'");
                            $stmt->execute();
                            echo $stmt->fetch()[0];
                        } catch (Exception $e) { echo '0'; }
                        ?>
                    ],
                    backgroundColor: [green, '#3B82F6', '#F59E0B', '#8B5CF6'],
                    borderWidth: 0
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }
});
</script>
