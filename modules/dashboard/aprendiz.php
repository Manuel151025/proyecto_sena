<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

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
$rolCased = ucfirst($user['rol']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h1>Hola, <?= $nombreUsuario ?> 👋</h1><p class="text-muted mb-0">Ficha #2845671 · ADSO · Trimestre 4</p></div>
  <span class="badge-soft primary"><i class="bi bi-mortarboard me-1"></i><?= $rolCased ?></span>
</div>
<div class="card mb-4"><div class="card-body"><div class="d-flex justify-content-between mb-2"><h3 class="mb-0">Progreso global del proyecto</h3><strong style="font-size:1.5rem;color:var(--sena-primary-600)">72%</strong></div><div class="progress-xl"><div style="width:72%">72%</div></div></div></div>
<h2 class="mb-2">Fase actual del proyecto</h2>
<div class="phases mb-4">
  <div class="phase done"><div class="ph-num"><i class="bi bi-check"></i></div><div class="ph-name">Análisis</div><div class="ph-meta">Completada</div></div>
  <div class="phase done"><div class="ph-num"><i class="bi bi-check"></i></div><div class="ph-name">Planeación</div><div class="ph-meta">Completada</div></div>
  <div class="phase active"><div class="ph-num">3</div><div class="ph-name">Ejecución</div><div class="ph-meta">En curso</div></div>
  <div class="phase"><div class="ph-num">4</div><div class="ph-name">Evaluación</div><div class="ph-meta">Pendiente</div></div>
</div>
<h2 class="mb-2">Progreso por competencia</h2>
<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-4"><div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Análisis y diseño</strong><span>85%</span></div><div class="progress-flat"><div style="width:85%"></div></div></div></div></div>
  <div class="col-md-6 col-xl-4"><div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Programación back-end</strong><span>74%</span></div><div class="progress-flat"><div style="width:74%"></div></div></div></div></div>
  <div class="col-md-6 col-xl-4"><div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Bases de datos</strong><span>62%</span></div><div class="progress-flat warning"><div style="width:62%"></div></div></div></div></div>
  <div class="col-md-6 col-xl-4"><div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Pruebas y QA</strong><span>48%</span></div><div class="progress-flat danger"><div style="width:48%"></div></div></div></div></div>
  <div class="col-md-6 col-xl-4"><div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Trabajo en equipo</strong><span>92%</span></div><div class="progress-flat"><div style="width:92%"></div></div></div></div></div>
  <div class="col-md-6 col-xl-4"><div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Inglés técnico</strong><span>55%</span></div><div class="progress-flat warning"><div style="width:55%"></div></div></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-header">Actividades próximas a vencer</div><div class="card-body p-0"><ul class="list-unstyled m-0">
    <li class="d-flex justify-content-between p-3 border-bottom" style="border-color:var(--border) !important"><div><strong>Entrega prototipo módulo de login</strong><br><small class="text-muted">Fecha: Hoy</small></div><span class="badge-soft danger"><i class="bi bi-clock me-1"></i>En 6 horas</span></li>
    <li class="d-flex justify-content-between p-3 border-bottom" style="border-color:var(--border) !important"><div><strong>Documentación técnica del API</strong><br><small class="text-muted">Fecha: Mañana</small></div><span class="badge-soft warning"><i class="bi bi-clock me-1"></i>En 1 día</span></li>
    <li class="d-flex justify-content-between p-3 border-bottom" style="border-color:var(--border) !important"><div><strong>Pruebas unitarias módulo usuarios</strong><br><small class="text-muted">Fecha: 15 mar</small></div><span class="badge-soft warning"><i class="bi bi-clock me-1"></i>En 5 días</span></li>
    <li class="d-flex justify-content-between p-3" style="border-color:var(--border) !important"><div><strong>Sustentación parcial</strong><br><small class="text-muted">Fecha: 22 mar</small></div><span class="badge-soft info"><i class="bi bi-clock me-1"></i>En 12 días</span></li>
  </ul></div></div></div>
  <div class="col-lg-5"><div class="card h-100"><div class="card-header">Planes de mejoramiento activos</div><div class="card-body">
    <div class="alert-flat warning mb-2"><i class="bi bi-clipboard-pulse"></i><div><strong>RA-08 — Bases de datos</strong><br><small>Vence 18/03/2026 · <span class="badge-soft warning">En proceso</span></small></div></div>
    <div class="alert-flat info"><i class="bi bi-clipboard-check"></i><div><strong>RA-15 — Pruebas QA</strong><br><small>Vence 25/03/2026 · <span class="badge-soft info">Asignado</span></small></div></div>
  </div></div></div>
</div>
