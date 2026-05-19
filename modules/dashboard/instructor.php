<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROL_INSTRUCTOR);

$pageTitle = 'Dashboard Instructor · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$user = getCurrentUser();
$nombreUsuario = htmlspecialchars($user['nombre']);

// Datos mock para las fichas
$fichasInstructor = [
    ['id' => 1, 'numero' => '2845671', 'programa' => 'ADSO', 'aprendices' => 32, 'cumplimiento' => 42, 'estado' => 'Crítico', 'badge' => 'danger'],
    ['id' => 2, 'numero' => '2867812', 'programa' => 'Multimedia', 'aprendices' => 28, 'cumplimiento' => 68, 'estado' => 'En riesgo', 'badge' => 'warning'],
    ['id' => 3, 'numero' => '2901234', 'programa' => 'ADSO', 'aprendices' => 30, 'cumplimiento' => 84, 'estado' => 'Al día', 'badge' => 'success'],
    ['id' => 4, 'numero' => '2912345', 'programa' => 'Contabilidad', 'aprendices' => 25, 'cumplimiento' => 91, 'estado' => 'Al día', 'badge' => 'success']
];

$pendientesPlanes = [
    ['aprendiz' => 'Andrés Gómez', 'ficha' => '2845671', 'ra' => 'RA-12', 'fecha' => '12/03/2026'],
    ['aprendiz' => 'María López', 'ficha' => '2867812', 'ra' => 'RA-08', 'fecha' => '10/03/2026'],
    ['aprendiz' => 'Juan Castro', 'ficha' => '2845671', 'ra' => 'RA-15', 'fecha' => '08/03/2026']
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h1>Buen día, <?= $nombreUsuario ?> 👋</h1><p class="text-muted mb-0">Estas son tus fichas y pendientes de hoy.</p></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="alert-flat danger"><i class="bi bi-clipboard-x"></i><div><strong>5 evaluaciones pendientes</strong> requieren tu calificación. <a href="<?= MODULES_PATH ?>/evaluaciones/" class="ms-2 fw-semibold" style="color:inherit;text-decoration:underline">Ir a evaluar</a></div></div></div>
  <div class="col-md-6"><div class="alert-flat warning"><i class="bi bi-clock-history"></i><div><strong>3 actividades vencen</strong> en los próximos 7 días.</div></div></div>
</div>
<h2 class="mt-4 mb-2">Mis fichas asignadas</h2>
<div class="row g-3">
  <?php foreach ($fichasInstructor as $ficha): ?>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between mb-2"><span class="badge-soft <?= $ficha['badge'] ?>"><?= htmlspecialchars($ficha['estado']) ?></span><small class="text-muted">#<?= htmlspecialchars($ficha['numero']) ?></small></div><h3 class="mb-1"><?= htmlspecialchars($ficha['programa']) ?></h3><small class="text-muted d-block mb-3"><i class="bi bi-people me-1"></i><?= $ficha['aprendices'] ?> aprendices</small><div class="d-flex justify-content-between small mb-1"><span>Cumplimiento</span><strong><?= $ficha['cumplimiento'] ?>%</strong></div><div class="progress-flat <?= $ficha['badge'] === 'danger' || $ficha['badge'] === 'warning' ? $ficha['badge'] : '' ?>"><div style="width:<?= $ficha['cumplimiento'] ?>%"></div></div><a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-soft w-100 mt-3">Ver detalle</a></div></div></div>
  <?php endforeach; ?>
</div>
<div class="card mt-4">
  <div class="card-header">Aprendices con concepto D sin plan de mejoramiento</div>
  <div class="table-wrap" style="border:0;border-radius:0"><table class="table mb-0"><thead><tr><th>Aprendiz</th><th>Ficha</th><th>RA</th><th>Fecha D</th><th></th></tr></thead><tbody>
  <?php foreach ($pendientesPlanes as $p): ?>
  <tr><td><div class="d-flex align-items-center gap-2"><div class="avatar" style="width:32px;height:32px;font-size:.75rem"><?= getInitials($p['aprendiz']) ?></div><?= htmlspecialchars($p['aprendiz']) ?></div></td><td>#<?= htmlspecialchars($p['ficha']) ?></td><td><span class="badge-soft"><?= htmlspecialchars($p['ra']) ?></span></td><td><?= htmlspecialchars($p['fecha']) ?></td><td class="text-end"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Crear plan</button></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
