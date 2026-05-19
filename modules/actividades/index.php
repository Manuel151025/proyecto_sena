<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROL_APRENDIZ);

$pageTitle = 'Mis Actividades · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h1>Mis actividades</h1><p class="text-muted mb-0">Tus entregas organizadas por fase del proyecto.</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mEvi"><i class="bi bi-cloud-arrow-up me-1"></i>Subir Evidencia</button>
</div>
<div class="acc-item"><div class="acc-header"><span><i class="bi bi-folder2-open me-2 text-success"></i>Análisis <span class="badge-soft ms-2">2 actividades</span></span><i class="bi bi-chevron-up" data-chev></i></div>
  <div class="acc-body" style="display:block">
    <div class="d-flex justify-content-between align-items-start py-3" style="border-bottom:1px solid var(--border)"><div><div class="d-flex gap-2 align-items-center mb-1"><strong>Investigación de mercado</strong><span class="badge-soft success">Entregado</span></div><div class="text-muted small">Investigar tendencias del sector</div><div class="small mt-1"><i class="bi bi-calendar3 me-1"></i>Entrega: 01/02/2026 · <span class="badge-soft">RA-01</span></div></div><button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#mEvi"><i class="bi bi-cloud-arrow-up me-1"></i>Evidencia</button></div>
    <div class="d-flex justify-content-between align-items-start py-3" style="border-bottom:1px solid var(--border)"><div><div class="d-flex gap-2 align-items-center mb-1"><strong>Levantamiento de requisitos</strong><span class="badge-soft success">Entregado</span></div><div class="text-muted small">Documento funcional</div><div class="small mt-1"><i class="bi bi-calendar3 me-1"></i>Entrega: 08/02/2026 · <span class="badge-soft">RA-01</span> <span class="badge-soft">RA-02</span></div></div><button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#mEvi"><i class="bi bi-cloud-arrow-up me-1"></i>Evidencia</button></div>
  </div>
</div>
<div class="acc-item"><div class="acc-header"><span><i class="bi bi-folder2-open me-2 text-success"></i>Ejecución <span class="badge-soft ms-2">3 actividades</span></span><i class="bi bi-chevron-down" data-chev></i></div>
  <div class="acc-body" style="display:none">
    <div class="d-flex justify-content-between align-items-start py-3" style="border-bottom:1px solid var(--border)"><div><div class="d-flex gap-2 align-items-center mb-1"><strong>Prototipo módulo de login</strong><span class="badge-soft info">En Proceso</span></div><div class="text-muted small">Implementar autenticación segura</div><div class="small mt-1"><i class="bi bi-calendar3 me-1"></i>Entrega: Hoy · <span class="badge-soft">RA-08</span> <span class="badge-soft">RA-12</span></div></div><button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#mEvi"><i class="bi bi-cloud-arrow-up me-1"></i>Evidencia</button></div>
    <div class="d-flex justify-content-between align-items-start py-3" style="border-bottom:1px solid var(--border)"><div><div class="d-flex gap-2 align-items-center mb-1"><strong>Pruebas unitarias módulo usuarios</strong><span class="badge-soft">Pendiente</span></div><div class="text-muted small">Cobertura mínima 80%</div><div class="small mt-1"><i class="bi bi-calendar3 me-1"></i>Entrega: 15/03/2026 · <span class="badge-soft">RA-15</span></div></div><button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#mEvi"><i class="bi bi-cloud-arrow-up me-1"></i>Evidencia</button></div>
  </div>
</div>
<!-- Modal evidencia -->
<div class="modal fade" id="mEvi" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Subir evidencia</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="dropzone mb-3"><i class="bi bi-cloud-arrow-up"></i><div class="mt-2"><strong>Arrastra tu archivo aquí</strong> o haz clic para seleccionar</div><div class="small mt-1">Formatos: PDF, JPG, PNG, DOCX, URL, ZIP, RAR · Máx 50MB</div></div>
    <div class="mb-3"><label class="form-label">Título de la evidencia</label><input class="form-control" placeholder="Ej: Prototipo módulo login v2"></div>
    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" rows="3" placeholder="Describe brevemente el contenido y cumplimiento..."></textarea></div>
  </div>
  <div class="modal-footer"><button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Subir</button></div>
</div></div></div>
