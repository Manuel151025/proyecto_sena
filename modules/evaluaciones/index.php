<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROL_INSTRUCTOR);

$pageTitle = 'Evaluaciones · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>
<h1 class="mb-1">Registrar evaluación</h1>
<p class="text-muted">Sigue los pasos para calificar las evidencias del aprendiz.</p>
<div class="steps">
  <div class="step done"><div class="num"><i class="bi bi-check"></i></div>Ficha</div>
  <span class="step-sep"><i class="bi bi-chevron-right"></i></span>
  <div class="step done"><div class="num"><i class="bi bi-check"></i></div>Aprendiz</div>
  <span class="step-sep"><i class="bi bi-chevron-right"></i></span>
  <div class="step done"><div class="num"><i class="bi bi-check"></i></div>RA</div>
  <span class="step-sep"><i class="bi bi-chevron-right"></i></span>
  <div class="step active"><div class="num">4</div>Registrar evaluación</div>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-paperclip me-1"></i>Evidencias del aprendiz</span><span class="badge-soft">3 archivos</span></div>
    <div class="card-body">
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="btn btn-soft btn-sm active"><i class="bi bi-file-earmark-pdf text-danger"></i> entrega-final.pdf</button>
        <button class="btn btn-soft btn-sm"><i class="bi bi-file-earmark-image text-info"></i> screenshot.png</button>
        <button class="btn btn-soft btn-sm"><i class="bi bi-file-earmark-zip text-warning"></i> codigo.zip</button>
      </div>
      <div style="background:var(--surface-2);border:1px dashed var(--border);border-radius:8px;height:340px;display:grid;place-items:center;color:var(--text-muted)">
        <div class="text-center"><i class="bi bi-file-earmark-pdf" style="font-size:3rem;color:var(--danger)"></i><div class="mt-2"><strong>Vista previa: entrega-final.pdf</strong></div><small>Página 1 de 12</small></div>
      </div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-body"><small class="text-muted d-block">Aprendiz</small><div class="d-flex align-items-center gap-2 mb-2"><div class="avatar">AG</div><strong>Andrés Gómez</strong></div><div class="row g-2 small"><div class="col-6"><span class="text-muted">Ficha:</span> #2845671</div><div class="col-6"><span class="text-muted">RA:</span> RA-12 Pruebas QA</div></div></div></div>
    <div class="card mb-3"><div class="card-body"><h3 class="mb-3">Concepto</h3>
      <div class="concept-toggle">
        <div class="concept-btn A"><i class="bi bi-check-circle ico"></i><div class="lbl">A</div><div class="desc">Aprobado</div></div>
        <div class="concept-btn D"><i class="bi bi-arrow-clockwise ico"></i><div class="lbl">D</div><div class="desc">Por mejorar</div></div>
        <div class="concept-btn P active"><i class="bi bi-clock ico"></i><div class="lbl">Pendiente</div><div class="desc">Sin definir</div></div>
      </div>
    </div></div>
    <div class="card mb-3"><div class="card-body"><label class="form-label d-flex justify-content-between"><span>Retroalimentación al aprendiz</span><small class="text-muted" id="cnt">0 / 2000</small></label><textarea class="form-control" rows="5" maxlength="2000" data-counter="#cnt" placeholder="Describe fortalezas, oportunidades de mejora y observaciones..."></textarea></div></div>
    <div class="card mb-3" id="plan-mejora" style="display:none"><div class="card-header"><i class="bi bi-clipboard-pulse me-1 text-danger"></i>Plan de mejoramiento</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label">Causas</label><textarea class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label class="form-label">Actividades adicionales</label><textarea class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label class="form-label">Criterios de aprobación</label><textarea class="form-control" rows="2"></textarea></div>
        <div><label class="form-label">Fecha límite</label><input type="date" class="form-control"></div>
      </div>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-soft flex-fill">Guardar borrador</button><button class="btn btn-primary flex-fill"><i class="bi bi-check2 me-1"></i>Registrar evaluación</button></div>
  </div>
</div>
