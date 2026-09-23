<?php
// Modal para emitir o cambiar un juicio (A/D). Lo usan /evaluaciones y
// /seguimiento; lo rellena data-valores con evaluacion_id, concepto,
// comentario, _anterior (concepto actual), ra y aprendiz. El motivo se pide
// al cambiar un juicio ya emitido (assets/js/modulos/evaluaciones.js).
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
?>
<div class="modal fade" id="modalEvaluar" tabindex="-1" aria-labelledby="tituloEvaluar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" data-form-juicio>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="evaluar">
        <input type="hidden" name="evaluacion_id">
        <input type="hidden" name="_anterior" data-anterior>
        <div class="modal-header">
          <h5 class="modal-title" id="tituloEvaluar"><i class="bi bi-clipboard-check"></i>Juicio evaluativo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-1" data-campo="ra"></p>
          <p class="small text-muted mb-3" data-campo="aprendiz"></p>
          <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Concepto <span class="text-danger">*</span></legend>
            <div class="d-flex gap-2">
              <input type="radio" class="btn-check" name="concepto" value="A" id="juicioA" required>
              <label class="btn btn-outline-success flex-grow-1" for="juicioA"><i class="bi bi-check-circle-fill me-1"></i>Aprobado (A)</label>
              <input type="radio" class="btn-check" name="concepto" value="D" id="juicioD">
              <label class="btn btn-outline-danger flex-grow-1" for="juicioD"><i class="bi bi-x-circle-fill me-1"></i>No aprobado (D)</label>
            </div>
          </fieldset>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="juicioComentario">Retroalimentación para el aprendiz</label>
            <textarea name="comentario" id="juicioComentario" class="form-control" rows="3" maxlength="1000" data-filtro="sin-html"
                      placeholder="Qué hizo bien o qué debe mejorar..."></textarea>
          </div>
          <div class="mb-0" data-bloque-motivo hidden>
            <label class="form-label fw-semibold" for="juicioMotivo">Motivo del cambio <span class="text-danger">*</span></label>
            <input type="text" name="motivo" id="juicioMotivo" class="form-control" maxlength="255" minlength="5" data-filtro="sin-html"
                   placeholder="Ej.: cumplió el plan de mejoramiento">
            <small class="text-muted">El juicio ya estaba emitido: el cambio y su motivo quedan en el historial.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
