
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Evidencias y Entregables</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        EnvÃ­a tus trabajos prÃ¡cticos y consulta las valoraciones de tu instructor.
      <?php else: ?>
        Revisa, retroalimenta y califica las evidencias enviadas por los aprendices.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($user_rol === ROL_APRENDIZ): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubir">
    <i class="bi bi-cloud-upload me-1"></i> Subir Evidencia
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 glass-card text-danger" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <ul class="mb-0 ps-3 d-inline-block">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12">
    <div class="card glass-card border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
              <tr>
                <th class="ps-4">TÃ­tulo / Entrega</th>
                <?php if ($user_rol !== ROL_APRENDIZ): ?>
                  <th>Aprendiz / Ficha</th>
                <?php endif; ?>
                <th>Resultado de Aprendizaje</th>
                <th>Fecha de EnvÃ­o</th>
                <th>Estado</th>
                <th class="pe-4 text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($evidencias as $ev): ?>
              <tr>
                <td class="ps-4">
                  <div class="fw-semibold text-dark"><?= htmlspecialchars($ev['titulo']) ?></div>
                  <small class="text-muted d-block text-truncate" style="max-width:300px;">
                    <?= htmlspecialchars($ev['descripcion'] ?: 'Sin descripciÃ³n') ?>
                  </small>
                  <?php if (!empty($ev['archivo_url'])): ?>
                    <a href="<?= APP_URL . '/' . htmlspecialchars($ev['archivo_url']) ?>" target="_blank" class="small text-primary text-decoration-none">
                      <i class="bi bi-paperclip me-1"></i>Descargar archivo (.<?= htmlspecialchars($ev['tipo_archivo'] ?? '') ?>)
                    </a>
                  <?php endif; ?>
                </td>
                <?php if ($user_rol !== ROL_APRENDIZ): ?>
                  <td>
                    <div class="fw-semibold text-dark"><?= htmlspecialchars($ev['aprendiz_nombre'] ?? '') ?></div>
                    <small class="badge bg-soft primary">Ficha #<?= htmlspecialchars($ev['numero_ficha']) ?></small>
                  </td>
                <?php endif; ?>
                <td>
                  <span class="text-muted small"><?= htmlspecialchars($ev['ra_denominacion'] ?? 'Sin resultado asociado') ?></span>
                </td>
                <td>
                  <div class="small"><?= date('d/m/Y h:i A', strtotime($ev['fecha_envio'])) ?></div>
                </td>
                <td>
                  <?php $badge = $estados_badge[$ev['estado']] ?? ['Desconocido', 'secondary']; ?>
                  <span class="badge-soft <?= $badge[1] ?>">
                    <?= $badge[0] ?>
                  </span>
                </td>
                <td class="pe-4 text-end">
                  <?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR]) && $ev['estado'] === 'enviada'): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCalificar"
                            onclick="prepararCalificacion(<?= $ev['id'] ?>, <?= json_encode($ev['titulo']) ?>)">
                      <i class="bi bi-pencil-square me-1"></i>Calificar
                    </button>
                  <?php elseif (!empty($ev['retroalimentacion'])): ?>
                    <button class="btn btn-sm btn-soft"
                            onclick="alert(<?= json_encode('RetroalimentaciÃ³n del Instructor:\n\n' . $ev['retroalimentacion']) ?>)">
                      <i class="bi bi-chat-left-text me-1"></i>Ver Retro
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">Sin revisiÃ³n</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($evidencias)): ?>
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="bi bi-file-earmark-arrow-up d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
                  No se han registrado entregas de evidencias todavÃ­a.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Subir Evidencia (Aprendiz) -->
<?php if ($user_rol === ROL_APRENDIZ): ?>
<div class="modal fade" id="modalSubir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Enviar Evidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="enviar_evidencia">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">TÃ­tulo de la Entrega</label>
            <input type="text" name="titulo" class="form-control" placeholder="Ej. SoluciÃ³n del taller de CSS Grid" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Comentarios / DescripciÃ³n</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="AÃ±ade detalles sobre tu entrega..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Archivo adjunto (PDF, ZIP, DOCX, etc.)</label>
            <input type="file" name="archivo" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Subir Evidencia</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Calificar Evidencia (Instructor / Coordinador) -->
<?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
<div class="modal fade" id="modalCalificar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Calificar Evidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="calificar_evidencia">
        <input type="hidden" name="evidencia_id" id="calificar_evidencia_id">
        <div class="modal-body">
          <div class="mb-3 p-3 rounded" style="background:rgba(0,0,0,0.02)">
            <span class="text-muted small">Evidencia seleccionada:</span>
            <div class="fw-bold" id="calificar_titulo">Ninguna</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Concepto Evaluativo</label>
            <select name="concepto" class="form-select" required>
              <option value="aprobado">Aprobado (A)</option>
              <option value="en_proceso">En Proceso (D)</option>
              <option value="no_aplica">No Aplica</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Comentarios de RetroalimentaciÃ³n</label>
            <textarea name="comentario" class="form-control" rows="4"
                      placeholder="Indica los logros o aspectos a mejorar de la entrega..." required></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar CalificaciÃ³n</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function prepararCalificacion(id, titulo) {
    document.getElementById('calificar_evidencia_id').value = id;
    document.getElementById('calificar_titulo').textContent = titulo;
}
</script>
<?php endif; ?>
