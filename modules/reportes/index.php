<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$role = getCurrentRole();
if ($role !== 'coordinador' && $role !== 'instructor') {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$pageTitle = 'Reportes · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h1>Reportes</h1><p class="text-muted mb-0">Genera informes institucionales por programa, ficha o instructor.</p></div>
  <div class="d-flex gap-2"><button class="btn btn-soft"><i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF</button><button class="btn btn-primary"><i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel</button></div>
</div>
<div class="row g-3">
  <div class="col-lg-3"><div class="card sticky-top" style="top:80px"><div class="card-header">Filtros</div><div class="card-body">
    <label class="form-label">Tipo de reporte</label>
    <ul class="nav nav-tabs flex-column mb-3" style="border:0">
      <li><button class="nav-link active w-100 text-start">Cumplimiento por ficha</button></li>
      <li><button class="nav-link w-100 text-start">Conceptos por instructor</button></li>
      <li><button class="nav-link w-100 text-start">Retención por programa</button></li>
      <li><button class="nav-link w-100 text-start">Planes de mejoramiento</button></li>
    </ul>
    <div class="mb-2"><label class="form-label">Programa</label><select class="form-select"><option>Todos</option><option>ADSO</option><option>Multimedia</option></select></div>
    <div class="mb-2"><label class="form-label">Ficha</label><select class="form-select"><option>Todas</option><option>#2845671</option></select></div>
    <div class="mb-2"><label class="form-label">Instructor</label><select class="form-select"><option>Todos</option><option>Carlos Méndez</option></select></div>
    <div class="mb-2"><label class="form-label">Desde</label><input type="date" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Hasta</label><input type="date" class="form-control"></div>
    <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Generar</button>
  </div></div></div>
  <div class="col-lg-9">
    <div class="card mb-3"><div class="card-body"><div class="d-flex align-items-center gap-3 pb-3 mb-3" style="border-bottom:1px solid var(--border)"><div class="avatar lg" style="border-radius:8px">SENA</div><div><h2 class="mb-0">Cumplimiento por ficha</h2><small class="text-muted">Servicio Nacional de Aprendizaje · Generado por <?= htmlspecialchars(getCurrentUser()['nombre']) ?></small></div></div><canvas id="rChart" height="80"></canvas></div></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Ficha</th><th>Programa</th><th>Aprendices</th><th>Concepto</th><th>Cumplimiento</th><th>Última actualización</th></tr></thead><tbody>
      <tr><td><strong>#2845671</strong></td><td>ADSO</td><td>32</td><td class="row-cell-A"><span class="fw-semibold">A</span></td><td>78%</td><td>09/05/2026</td></tr>
      <tr><td><strong>#2867812</strong></td><td>Multimedia</td><td>28</td><td class="row-cell-D"><span class="fw-semibold">D</span></td><td>58%</td><td>09/05/2026</td></tr>
      <tr><td><strong>#2901234</strong></td><td>Contabilidad</td><td>30</td><td class="row-cell-A"><span class="fw-semibold">A</span></td><td>84%</td><td>08/05/2026</td></tr>
    </tbody></table></div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const css = getComputedStyle(document.documentElement);
    if(document.getElementById('rChart')){
        new Chart(document.getElementById('rChart'),{type:'bar',data:{labels:['#2845671','#2867812','#2901234','#2912345','#2823456'],datasets:[{label:'Cumplimiento %',data:[78,58,84,45,92],backgroundColor:css.getPropertyValue('--sena-primary').trim(),borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}});
    }
});
</script>
