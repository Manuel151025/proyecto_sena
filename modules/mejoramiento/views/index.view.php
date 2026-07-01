
<div class="mb-4">
  <h1 class="mb-1">Planes de Mejoramiento</h1>
  <p class="text-muted mb-0">Cuando un aprendiz obtiene una evaluaciÃ³n 'En Proceso' (D), se genera automÃ¡ticamente un plan de mejoramiento para nivelar las competencias pendientes.</p>
</div>

<div class="row g-3">
  <?php foreach ($planes as $plan): ?>
    <div class="col-md-6">
      <div class="card glass-card h-100 border-0 shadow-sm border-start border-danger border-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft danger">Plan Requerido</span>
            <small class="text-muted">Fecha: <?= date('d/m/Y', strtotime($plan['fecha_evaluacion'])) ?></small>
          </div>
          <h5 class="fw-bold text-dark mb-1">Plan de NivelaciÃ³n: <?= htmlspecialchars($plan['actividad_nombre']) ?></h5>
          <?php if (!empty($plan['ra_codigo'])): ?>
            <div class="small text-muted mb-2">RA <code><?= htmlspecialchars($plan['ra_codigo']) ?></code></div>
          <?php endif; ?>
          
          <?php if ($user_rol !== ROL_APRENDIZ): ?>
            <div class="small text-muted mb-2">
              Aprendiz: <strong><?= htmlspecialchars($plan['aprendiz_nombre']) ?></strong> (Ficha #<?= htmlspecialchars($plan['numero_ficha']) ?>)
            </div>
          <?php endif; ?>

          <div class="p-3 bg-light-soft rounded mb-3" style="background: rgba(239, 68, 68, 0.03); font-size: 0.85rem;">
            <div class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-circle me-1"></i>Deficiencia reportada:</div>
            <p class="mb-0 text-muted">"<?= htmlspecialchars($plan['comentario']) ?>"</p>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Asignado por: <strong><?= htmlspecialchars($plan['instructor_nombre']) ?></strong></small>
            <button class="btn btn-sm btn-danger" onclick="alert('Instrucciones del plan:\n\n1. Repetir la entrega de la evidencia corrigiendo los puntos descritos.\n2. Solicitar cita de asesorÃ­a acadÃ©mica con el instructor asignado.')">
              Ver GuÃ­a Plan
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($planes)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-patch-check-fill d-block mb-2 text-success" style="font-size:3rem;"></i>
      <h4 class="fw-bold text-dark">Â¡Felicidades!</h4>
      <p class="text-muted">No se reportan planes de mejoramiento pendientes en el sistema formativo.</p>
    </div>
  <?php endif; ?>
</div>
