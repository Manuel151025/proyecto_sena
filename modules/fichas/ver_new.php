<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$id = (int) ($_GET['id'] ?? 0);
$db = Database::getConnection();
$errors = [];

// Obtener ficha con información completa
try {
    $stmt = $db->prepare("
        SELECT 
            f.id,
            f.numero_ficha,
            f.estado,
            f.cantidad_aprendices,
            f.fecha_inicio,
            f.fecha_fin,
            f.cumplimiento_porcentaje,
            p.nombre as programa,
            p.id as programa_id,
            u.nombre as instructor,
            u.id as instructor_id
        FROM fichas f
        JOIN programas p ON f.programa_id = p.id
        JOIN usuarios u ON f.instructor_id = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id]);
    $ficha = $stmt->fetch();
    
    if (!$ficha) {
        $errors[] = 'Ficha no encontrada';
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar ficha';
    $ficha = null;
}

// Obtener aprendices matriculados en esta ficha
$aprendices = [];
if ($ficha) {
    try {
        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.numero_documento,
                a.tipo_documento,
                u.nombre,
                u.avatar_color,
                a.estado
            FROM aprendices a
            JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.ficha_id = ?
            ORDER BY u.nombre
        ");
        $stmt->execute([$id]);
        $aprendices = $stmt->fetchAll();
    } catch (Exception $e) {
        $aprendices = [];
    }
}

$pageTitle = $ficha ? 'Ficha Detalle · SENA' : 'Ficha no encontrada · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$estados_label = [
    'planeacion' => ['Planeación', 'primary'],
    'induccion' => ['Inducción', 'info'],
    'ejecucion' => ['Ejecución', 'warning'],
    'cierre' => ['Cierre', 'success']
];

$estados_aprendiz = [
    'matriculado' => ['Matriculado', 'success'],
    'suspendido' => ['Suspendido', 'warning'],
    'desertado' => ['Desertado', 'danger'],
    'egresado' => ['Egresado', 'info']
];
?>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3">
  <i class="bi bi-exclamation-circle"></i>
  <div><?= htmlspecialchars($errors[0]) ?></div>
  <br><a href="<?= MODULES_PATH ?>/fichas/">Volver a fichas →</a>
</div>
<?php else: ?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= MODULES_PATH ?>/fichas/" class="small"><i class="bi bi-arrow-left"></i> Volver a fichas</a>
    <h1 class="mt-2 mb-1">
      Ficha #<?= htmlspecialchars($ficha['numero_ficha']) ?> 
      <span class="badge-soft <?= $estados_label[$ficha['estado']][1] ?> ms-2"><?= $estados_label[$ficha['estado']][0] ?></span>
    </h1>
    <p class="text-muted mb-0">
      <?= htmlspecialchars($ficha['programa']) ?> · 
      Instructor: <?= htmlspecialchars($ficha['instructor']) ?> · 
      <?= $ficha['cantidad_aprendices'] ?> aprendices
    </p>
  </div>
  <a href="<?= MODULES_PATH ?>/fichas/editar.php?id=<?= $id ?>" class="btn btn-primary">
    <i class="bi bi-pencil me-1"></i>Editar ficha
  </a>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tInfo">
      <i class="bi bi-info-circle me-1"></i>Información general
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tApr">
      <i class="bi bi-people me-1"></i>Aprendices matriculados (<?= count($aprendices) ?>)
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tInfo">
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <small class="text-muted">Programa</small>
            <div class="fw-semibold"><?= htmlspecialchars($ficha['programa']) ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Instructor responsable</small>
            <div class="fw-semibold"><?= htmlspecialchars($ficha['instructor']) ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Estado</small>
            <div class="fw-semibold"><span class="badge-soft <?= $estados_label[$ficha['estado']][1] ?>"><?= $estados_label[$ficha['estado']][0] ?></span></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Fecha de inicio</small>
            <div class="fw-semibold"><?= $ficha['fecha_inicio'] ? date('d/m/Y', strtotime($ficha['fecha_inicio'])) : 'N/A' ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Fecha de fin</small>
            <div class="fw-semibold"><?= $ficha['fecha_fin'] ? date('d/m/Y', strtotime($ficha['fecha_fin'])) : 'N/A' ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Cumplimiento</small>
            <div class="fw-semibold">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 100px; height: 24px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                  <div style="height: 100%; width: <?= $ficha['cumplimiento_porcentaje'] ?>%; background: <?= $ficha['cumplimiento_porcentaje'] >= 75 ? '#22c55e' : ($ficha['cumplimiento_porcentaje'] >= 50 ? '#eab308' : '#ef4444') ?>; transition: width 0.3s;"></div>
                </div>
                <span><?= (int)$ficha['cumplimiento_porcentaje'] ?>%</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tApr">
    <div class="toolbar mb-3">
      <div class="search"><i class="bi bi-search"></i><input class="form-control" id="searchAprendices" placeholder="Buscar aprendiz..."></div>
    </div>
    <?php if (count($aprendices) > 0): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Aprendiz</th>
            <th>Tipo de documento</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendices as $aprendiz): ?>
          <tr class="aprendiz-row" data-search="<?= htmlspecialchars(strtolower($aprendiz['nombre'] . ' ' . $aprendiz['numero_documento'])) ?>">
            <td><code><?= htmlspecialchars($aprendiz['numero_documento']) ?></code></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar" style="width:30px;height:30px;font-size:.72rem;background:<?= htmlspecialchars($aprendiz['avatar_color']) ?>">
                  <?= htmlspecialchars(substr($aprendiz['nombre'], 0, 2)) ?>
                </div>
                <?= htmlspecialchars($aprendiz['nombre']) ?>
              </div>
            </td>
            <td><?= htmlspecialchars($aprendiz['tipo_documento']) ?></td>
            <td>
              <span class="badge-soft <?= $estados_aprendiz[$aprendiz['estado']][1] ?>">
                <?= $estados_aprendiz[$aprendiz['estado']][0] ?>
              </span>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-soft" onclick="alert('Función de detalle de aprendiz próximamente')">Ver</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script>
    document.getElementById('searchAprendices').addEventListener('keyup', function(e) {
      const filter = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('.aprendiz-row');
      rows.forEach(row => {
        const text = row.dataset.search;
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    });
    </script>
    <?php else: ?>
    <div class="alert-flat info">
      <i class="bi bi-info-circle"></i>
      <div>No hay aprendices matriculados en esta ficha aún.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
