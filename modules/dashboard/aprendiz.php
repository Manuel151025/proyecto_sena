<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_APRENDIZ);

$pageTitle = 'Dashboard Aprendiz · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$user = getCurrentUser();
$nombreUsuario = htmlspecialchars($user['nombre']);
$db = Database::getConnection();

// Obtener datos del aprendiz
$aprendiz = null;
$fichaInfo = null;
$progreso = ['total_ra' => 0, 'aprobados' => 0, 'reprobados' => 0, 'pendientes' => 0];
$progresoCompetencias = [];
$fasesProyecto = [];
$evaluacionesRecientes = [];

try {
    // Datos del aprendiz
    $stmt = $db->prepare("
        SELECT a.id, a.ficha_id, f.numero_ficha, f.estado as ficha_estado,
               p.nombre as programa, p.codigo as programa_codigo,
               pr.nombre as proyecto_nombre, pr.codigo as proyecto_codigo,
               u_inst.nombre as instructor_nombre,
               f.proyecto_id
        FROM aprendices a
        JOIN fichas f ON a.ficha_id = f.id
        JOIN programas p ON f.programa_id = p.id
        LEFT JOIN proyectos pr ON f.proyecto_id = pr.id
        JOIN usuarios u_inst ON f.instructor_id = u_inst.id
        WHERE a.usuario_id = ?
    ");
    $stmt->execute([$user['id']]);
    $aprendiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aprendiz) {
        // Progreso global: Contar RAs evaluados
        $stmtProg = $db->prepare("
            SELECT 
                COUNT(*) as total_ra,
                SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
            FROM evaluaciones
            WHERE aprendiz_id = ?
        ");
        $stmtProg->execute([$aprendiz['id']]);
        $progreso = $stmtProg->fetch(PDO::FETCH_ASSOC);

        // Progreso por competencia
        $stmtComp = $db->prepare("
            SELECT 
                c.nombre as competencia,
                c.codigo as comp_codigo,
                COUNT(e.id) as total_ra,
                SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                u_inst.nombre as instructor_nombre
            FROM evaluaciones e
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            LEFT JOIN asignaciones asg ON asg.competencia_id = c.id AND asg.ficha_id = ?
            LEFT JOIN usuarios u_inst ON asg.instructor_id = u_inst.id
            WHERE e.aprendiz_id = ?
            GROUP BY c.id, c.nombre, c.codigo, u_inst.nombre
            ORDER BY c.codigo
        ");
        $stmtComp->execute([$aprendiz['ficha_id'], $aprendiz['id']]);
        $progresoCompetencias = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

        // Fases del proyecto
        if ($aprendiz['proyecto_id']) {
            $stmtFases = $db->prepare("
                SELECT nombre, cumplimiento_porcentaje, estado, numero_fase
                FROM fases_proyecto 
                WHERE proyecto_id = ?
                ORDER BY numero_fase
            ");
            $stmtFases->execute([$aprendiz['proyecto_id']]);
            $fasesProyecto = $stmtFases->fetchAll(PDO::FETCH_ASSOC);
        }

        // Últimas evaluaciones
        $stmtRecientes = $db->prepare("
            SELECT ra.codigo as ra_codigo, ra.denominacion, e.concepto, e.fecha_evaluacion, e.comentario, u.nombre as instructor_evaluador
            FROM evaluaciones e
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            LEFT JOIN usuarios u ON e.instructor_id = u.id
            WHERE e.aprendiz_id = ? AND e.concepto != 'pendiente'
            ORDER BY e.fecha_evaluacion DESC
            LIMIT 6
        ");
        $stmtRecientes->execute([$aprendiz['id']]);
        $evaluacionesRecientes = $stmtRecientes->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silently handle
}

$totalRA = max(1, (int)$progreso['total_ra']);
$pctAprobado = round(((int)$progreso['aprobados'] / $totalRA) * 100);
$pctReprobado = round(((int)$progreso['reprobados'] / $totalRA) * 100);
$pctPendiente = round(((int)$progreso['pendientes'] / $totalRA) * 100);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Hola, <?= $nombreUsuario ?> 👋</h1>
    <?php if ($aprendiz): ?>
    <p class="text-muted mb-0">
      Ficha #<?= htmlspecialchars($aprendiz['numero_ficha']) ?> · <?= htmlspecialchars($aprendiz['programa']) ?> · 
      Instructor: <?= htmlspecialchars($aprendiz['instructor_nombre']) ?>
    </p>
    <?php else: ?>
    <p class="text-muted mb-0">No estás matriculado en ninguna ficha actualmente.</p>
    <?php endif; ?>
  </div>
  <span class="badge-soft primary"><i class="bi bi-mortarboard me-1"></i>Aprendiz</span>
</div>

<?php if ($aprendiz): ?>

<!-- Progreso Global -->
<div class="card mb-4" style="border-top: 4px solid var(--sena-primary); border-radius: 12px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0 fw-bold">Progreso Formativo Global</h3>
        <small class="text-muted"><?= (int)$progreso['aprobados'] ?> de <?= $totalRA ?> Resultados de Aprendizaje aprobados</small>
      </div>
      <div class="text-end">
        <div class="fw-bold" style="font-size: 2.5rem; color: var(--sena-primary); line-height: 1;"><?= $pctAprobado ?>%</div>
        <small class="text-muted">Competente</small>
      </div>
    </div>
    <div class="progress" style="height: 14px; border-radius: 10px; background: #f0f0f0;">
      <div class="progress-bar bg-success" style="width: <?= $pctAprobado ?>%; border-radius: 10px 0 0 10px;" title="Aprobados (A)"></div>
      <div class="progress-bar bg-danger" style="width: <?= $pctReprobado ?>%;" title="No Aprobados (D)"></div>
      <div class="progress-bar" style="width: <?= $pctPendiente ?>%; background: #e5e7eb;" title="Pendientes"></div>
    </div>
    <div class="d-flex justify-content-between mt-2" style="font-size: 0.8rem;">
      <span><span class="d-inline-block rounded-circle me-1" style="width:10px; height:10px; background:#22c55e;"></span> Aprobados: <?= (int)$progreso['aprobados'] ?></span>
      <span><span class="d-inline-block rounded-circle me-1" style="width:10px; height:10px; background:#ef4444;"></span> No Aprobados: <?= (int)$progreso['reprobados'] ?></span>
      <span><span class="d-inline-block rounded-circle me-1" style="width:10px; height:10px; background:#e5e7eb;"></span> Pendientes: <?= (int)$progreso['pendientes'] ?></span>
    </div>
  </div>
</div>

<!-- Fases del Proyecto -->
<?php if (!empty($fasesProyecto)): ?>
<h2 class="mb-2">Fase actual del proyecto</h2>
<?php if ($aprendiz['proyecto_nombre']): ?>
<p class="text-muted mb-3"><i class="bi bi-kanban me-1"></i><?= htmlspecialchars($aprendiz['proyecto_nombre']) ?> (<?= htmlspecialchars($aprendiz['proyecto_codigo']) ?>)</p>
<?php endif; ?>
<div class="phases mb-4">
  <?php foreach ($fasesProyecto as $fase): ?>
  <div class="phase <?= $fase['estado'] === 'completada' ? 'done' : ($fase['estado'] === 'en_ejecucion' ? 'active' : '') ?>">
    <div class="ph-num"><?= $fase['estado'] === 'completada' ? '<i class="bi bi-check"></i>' : $fase['numero_fase'] ?></div>
    <div class="ph-name"><?= htmlspecialchars($fase['nombre']) ?></div>
    <div class="ph-meta"><?= $fase['estado'] === 'completada' ? 'Completada' : ($fase['estado'] === 'en_ejecucion' ? 'En curso' : 'Pendiente') ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Progreso por Competencia -->
<h2 class="mb-2">Progreso por Competencia</h2>
<div class="row g-3 mb-4">
  <?php foreach ($progresoCompetencias as $comp): ?>
  <?php 
    $compTotal = max(1, (int)$comp['total_ra']);
    $compPct = round(((int)$comp['aprobados'] / $compTotal) * 100);
    $compClass = $compPct >= 75 ? '' : ($compPct >= 50 ? 'warning' : 'danger');
  ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100" style="border-radius: 10px;">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
          <strong class="text-truncate" title="<?= htmlspecialchars($comp['competencia']) ?>" style="max-width: 200px;"><?= htmlspecialchars($comp['competencia']) ?></strong>
          <span class="fw-bold <?= $compClass === 'danger' ? 'text-danger' : ($compClass === 'warning' ? 'text-warning' : 'text-success') ?>"><?= $compPct ?>%</span>
        </div>
        <small class="text-muted d-block mb-2"><?= htmlspecialchars($comp['comp_codigo']) ?> · <?= (int)$comp['aprobados'] ?>/<?= $compTotal ?> RAs aprobados</small>
        <?php if (!empty($comp['instructor_nombre'])): ?>
        <div class="text-muted small mb-2" style="font-size: 0.75rem;">
          <i class="bi bi-person me-1"></i>Instructor: <?= htmlspecialchars($comp['instructor_nombre']) ?>
        </div>
        <?php else: ?>
        <div class="text-muted small mb-2" style="font-size: 0.75rem; font-style: italic;">
          <i class="bi bi-person me-1"></i>Instructor: Por asignar
        </div>
        <?php endif; ?>
        <div class="progress-flat <?= $compClass ?>"><div style="width:<?= $compPct ?>%"></div></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($progresoCompetencias)): ?>
  <div class="col-12 text-center py-4 text-muted">
    <i class="bi bi-clipboard-x d-block mb-2" style="font-size:2rem; opacity:0.4;"></i>
    Aún no tienes evaluaciones registradas por competencia.
  </div>
  <?php endif; ?>
</div>

<!-- Evaluaciones Recientes -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100" style="border-radius: 10px;">
      <div class="card-header fw-bold">Últimas Evaluaciones</div>
      <div class="card-body p-0">
        <?php if (!empty($evaluacionesRecientes)): ?>
        <ul class="list-unstyled m-0">
          <?php foreach ($evaluacionesRecientes as $ev): ?>
          <li class="d-flex justify-content-between align-items-center p-3 border-bottom" style="border-color:var(--border) !important">
            <div style="max-width: 65%;">
              <strong><?= htmlspecialchars($ev['ra_codigo']) ?></strong>
              <small class="text-muted d-block text-truncate"><?= htmlspecialchars($ev['denominacion']) ?></small>
              <?php if (!empty($ev['instructor_evaluador'])): ?>
              <small class="text-muted d-block text-truncate" style="font-size: 0.72rem; margin-top: 2px;" title="<?= htmlspecialchars($ev['instructor_evaluador']) ?>">
                <i class="bi bi-person me-1"></i>Evaluado por: <?= htmlspecialchars($ev['instructor_evaluador']) ?>
              </small>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <span class="badge-soft <?= $ev['concepto'] === 'A' ? 'success' : 'danger' ?> mb-1">
                <i class="bi <?= $ev['concepto'] === 'A' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                <?= $ev['concepto'] === 'A' ? 'Aprobado' : 'No Aprobado' ?>
              </span>
              <small class="text-muted d-block"><?= $ev['fecha_evaluacion'] ? date('d/m/Y', strtotime($ev['fecha_evaluacion'])) : '' ?></small>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="text-center py-4 text-muted">
          <i class="bi bi-clipboard d-block mb-2" style="font-size:2rem; opacity:0.4;"></i>
          Sin evaluaciones recientes.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="col-lg-5">
    <div class="card h-100" style="border-radius: 10px;">
      <div class="card-header fw-bold">Resumen por Concepto</div>
      <div class="card-body d-flex flex-column justify-content-center">
        <canvas id="chartConceptos" height="220"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('chartConceptos')) {
        const css = getComputedStyle(document.documentElement);
        new Chart(document.getElementById('chartConceptos'), {
            type: 'doughnut',
            data: {
                labels: ['Aprobados (A)', 'No Aprobados (D)', 'Pendientes'],
                datasets: [{
                    data: [<?= (int)$progreso['aprobados'] ?>, <?= (int)$progreso['reprobados'] ?>, <?= (int)$progreso['pendientes'] ?>],
                    backgroundColor: ['#22c55e', '#ef4444', '#e5e7eb'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12 } } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8 }
                }
            }
        });
    }
});
</script>

<?php else: ?>
<div class="card glass-card text-center py-5">
  <div class="card-body">
    <i class="bi bi-person-x d-block mb-3" style="font-size: 4rem; color: #d1d5db;"></i>
    <h3 class="fw-bold text-secondary">Sin matrícula activa</h3>
    <p class="text-muted">Tu cuenta no está asociada a ninguna ficha de formación. Contacta al coordinador o instructor.</p>
  </div>
</div>
<?php endif; ?>
