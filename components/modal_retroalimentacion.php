<?php
// Modal para registrar retroalimentación u observaciones de seguimiento.
// Lo usan /retroalimentacion y /seguimiento. Espera:
//   $aprendicesModal  lista [id, nombre, numero_ficha] para el selector; si
//                     es null, el aprendiz llega fijo en data-valores.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Formularios\RetroalimentacionFormulario;
?>
<div class="modal fade" id="modalRetro" tabindex="-1" aria-labelledby="tituloRetro" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= e($accionRetro ?? 'registrar') ?>">
        <input type="hidden" name="evaluacion_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloRetro"><i class="bi bi-chat-left-text"></i>Retroalimentación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <?php if ($aprendicesModal !== null): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="retro_ap">Aprendiz <span class="text-danger">*</span></label>
            <select name="aprendiz_id" id="retro_ap" class="form-select" required data-picker data-picker-label="Aprendiz" data-picker-placeholder="Nombre o ficha...">
              <option value="" disabled selected>Seleccione…</option>
              <?php foreach ($aprendicesModal as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?> · Ficha <?= e($a['numero_ficha']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
          <input type="hidden" name="aprendiz_id">
          <p class="fw-semibold mb-3" data-campo="aprendiz"></p>
          <?php endif; ?>
          <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Tipo</legend>
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach (RetroalimentacionFormulario::TIPOS as $valor => [$texto, $clase]): ?>
                <input type="radio" class="btn-check" name="tipo" value="<?= e($valor) ?>" id="retro_<?= e($valor) ?>" <?= $valor === 'recomendacion' ? 'checked' : '' ?>>
                <label class="btn btn-outline-<?= e($clase) ?> btn-sm flex-grow-1" for="retro_<?= e($valor) ?>"><?= e($texto) ?></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="retro_txt">Contenido <span class="text-danger">*</span></label>
            <textarea name="contenido" id="retro_txt" class="form-control" rows="4" required minlength="10" maxlength="<?= RetroalimentacionFormulario::MAX_CONTENIDO ?>" data-filtro="sin-html"
                      placeholder="Observación concreta y accionable..."></textarea>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="privada" value="1" id="retro_priv">
            <label class="form-check-label" for="retro_priv">Privada: solo la ve el equipo de formación, no el aprendiz</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>
