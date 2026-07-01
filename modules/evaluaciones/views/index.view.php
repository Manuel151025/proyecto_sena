
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Juicios de EvaluaciÃ³n</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Consulta el estado de tus Resultados de Aprendizaje (RA) evaluados con conceptos A (Aprobado) y D (AÃºn no competente).
      <?php else: ?>
        Gestiona los juicios evaluativos por Resultado de Aprendizaje. Los conceptos vÃ¡lidos son <strong>A</strong> (Aprobado) y <strong>D</strong> (AÃºn no competente).
      <?php endif; ?>
    </p>
  </div>
  <?php if ($user_rol !== ROL_APRENDIZ): ?>
  <div>
    <a href="<?= MODULES_PATH ?>/evaluaciones/importar_juicios.php" class="btn btn-primary">
      <i class="bi bi-file-earmark-excel me-1"></i> Importar Juicios Evaluativos
    </a>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>
    <?php foreach ($errors as $err): ?>
      <div><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-flat success mb-3">
  <i class="bi bi-check-circle-fill"></i>
  <div><?= htmlspecialchars($success) ?></div>
</div>
<?php endif; ?>

<!-- KPIs de EvaluaciÃ³n -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid var(--sena-primary);">
      <div class="kpi-content">
        <div class="label">Total Evaluaciones</div>
        <div class="value"><?= (int)$statsEval['total'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid #22c55e;">
      <div class="kpi-content">
        <div class="label">Aprobados (A)</div>
        <div class="value" style="color: #22c55e;"><?= (int)$statsEval['aprobados'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid #ef4444;">
      <div class="kpi-content">
        <div class="label">No Aprobados (D)</div>
        <div class="value" style="color: #ef4444;"><?= (int)$statsEval['reprobados'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid #eab308;">
      <div class="kpi-content">
        <div class="label">Pendientes</div>
        <div class="value" style="color: #eab308;"><?= (int)$statsEval['pendientes'] ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Barra de filtros -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar Aprendiz / RA</label>
        <div class="input-group">
          <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Nombre o cÃ³digo RA..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>>
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Concepto Evaluativo</label>
        <select name="concepto" class="form-select">
          <option value="">Todos</option>
          <option value="A" <?= $filter_concepto === 'A' ? 'selected' : '' ?>>Aprobado (A)</option>
          <option value="D" <?= $filter_concepto === 'D' ? 'selected' : '' ?>>No Aprobado (D)</option>
          <option value="pendiente" <?= $filter_concepto === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Listado de Evaluaciones -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Resultado de Aprendizaje</th>
            <?php if ($user_rol !== ROL_APRENDIZ): ?>
              <th>Aprendiz</th>
            <?php endif; ?>
            <th>Competencia</th>
            <th>Fecha</th>
            <th>Concepto</th>
            <th class="pe-4 text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($evaluaciones as $eval): ?>
          <tr>
            <td class="ps-4">
              <div class="fw-semibold text-dark"><?= htmlspecialchars($eval['ra_codigo']) ?></div>
              <small class="text-muted" style="display:block; max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($eval['ra_denominacion']) ?>">
                <?= htmlspecialchars($eval['ra_denominacion']) ?>
              </small>
              <small class="badge bg-soft primary">Ficha #<?= htmlspecialchars($eval['numero_ficha']) ?></small>
            </td>
            <?php if ($user_rol !== ROL_APRENDIZ): ?>
              <td>
                <div class="fw-semibold text-dark"><?= htmlspecialchars($eval['aprendiz_nombre']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($eval['aprendiz_email']) ?></small>
              </td>
            <?php endif; ?>
            <td>
              <small class="fw-medium text-muted"><?= htmlspecialchars($eval['competencia_nombre']) ?></small>
            </td>
            <td>
              <span class="small"><?= $eval['fecha_evaluacion'] ? date('d/m/Y', strtotime($eval['fecha_evaluacion'])) : 'â€”' ?></span>
            </td>
            <td>
              <span class="badge-soft <?= $conceptos_label[$eval['concepto']][1] ?>">
                <i class="bi <?= $conceptos_label[$eval['concepto']][2] ?> me-1"></i>
                <?= $conceptos_label[$eval['concepto']][0] ?>
              </span>
            </td>
            <td class="pe-4 text-end">
              <?php if ($user_rol !== ROL_APRENDIZ): ?>
              <button class="btn btn-sm btn-primary"
                data-bs-toggle="modal" data-bs-target="#modalEvaluar"
                data-eval-id="<?= $eval['id'] ?>"
                data-ra="<?= htmlspecialchars($eval['ra_codigo'], ENT_QUOTES) ?>"
                data-aprendiz="<?= htmlspecialchars($eval['aprendiz_nombre'], ENT_QUOTES) ?>"
                data-concepto="<?= htmlspecialchars($eval['concepto'], ENT_QUOTES) ?>"
                data-comentario="<?= htmlspecialchars($eval['comentario'] ?? '', ENT_QUOTES) ?>">
                <i class="bi bi-pencil-square me-1"></i>Evaluar
              </button>
              <?php endif; ?>
              <button class="btn btn-sm btn-soft" onclick="alert(<?= json_encode('RetroalimentaciÃ³n:\n\n' . ($eval['comentario'] ?: 'Sin comentarios.')) ?>)">
                <i class="bi bi-chat-left-dots"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($evaluaciones)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-pencil-square d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
              No hay evaluaciones registradas con los filtros seleccionados.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal para Evaluar -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="modal fade" id="modalEvaluar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <form method="POST">
        <input type="hidden" name="action" value="evaluar">
        <input type="hidden" name="evaluacion_id" id="evalId">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
          <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Juicio Evaluativo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <div class="text-muted small text-uppercase">Resultado de Aprendizaje</div>
            <div class="fw-bold" id="evalRA">â€”</div>
          </div>
          <div class="mb-3">
            <div class="text-muted small text-uppercase">Aprendiz</div>
            <div class="fw-semibold" id="evalAprendiz">â€”</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Concepto Evaluativo <span class="text-danger">*</span></label>
            <div class="d-flex gap-2">
              <label class="btn btn-outline-success flex-grow-1 concepto-radio" style="border-radius: 10px;">
                <input type="radio" name="concepto" value="A" class="d-none" required>
                <i class="bi bi-check-circle-fill me-1"></i> Aprobado (A)
              </label>
              <label class="btn btn-outline-danger flex-grow-1 concepto-radio" style="border-radius: 10px;">
                <input type="radio" name="concepto" value="D" class="d-none">
                <i class="bi bi-x-circle-fill me-1"></i> No Aprobado (D)
              </label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Comentario / RetroalimentaciÃ³n</label>
            <textarea name="comentario" id="evalComentario" class="form-control" rows="3" placeholder="Escriba su observaciÃ³n sobre el desempeÃ±o del aprendiz..."></textarea>
          </div>
          <div class="mb-0" id="div_eval_motivo" style="display:none;">
            <label class="form-label fw-semibold text-danger">Motivo del cambio *</label>
            <input type="text" name="motivo" id="eval_motivo" class="form-control" placeholder="Ej: Plan de mejoramiento completado">
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar EvaluaciÃ³n</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Poblar modal usando el evento de Bootstrap (mÃ©todo fiable)
const modalEvaluar = document.getElementById('modalEvaluar');
let originalConcepto = '';

if (modalEvaluar) {
  modalEvaluar.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget; // botÃ³n que disparÃ³ el modal
    if (!btn) return;

    const evalId    = btn.dataset.evalId;
    const ra        = btn.dataset.ra;
    const aprendiz  = btn.dataset.aprendiz;
    const concepto  = btn.dataset.concepto;
    const comentario = btn.dataset.comentario || '';

    document.getElementById('evalId').value           = evalId;
    document.getElementById('evalRA').textContent     = ra;
    document.getElementById('evalAprendiz').textContent = aprendiz;
    document.getElementById('evalComentario').value   = comentario;

    originalConcepto = concepto; // Guardar el concepto original

    // Resetear motivo
    const divMotivo = document.getElementById('div_eval_motivo');
    const inputMotivo = document.getElementById('eval_motivo');
    if (divMotivo && inputMotivo) {
      divMotivo.style.display = 'none';
      inputMotivo.value = '';
      inputMotivo.required = false;
    }

    // Marcar el radio del concepto actual
    document.querySelectorAll('.concepto-radio').forEach(label => {
      label.classList.remove('active');
      const radio = label.querySelector('input[type="radio"]');
      if (radio.value === concepto) {
        radio.checked = true;
        label.classList.add('active');
      } else {
        radio.checked = false;
      }
    });

    console.log('[Eval] Modal abierto para evaluaciÃ³n ID:', evalId, '| concepto actual:', concepto);
  });

  // Toggle visual del campo motivo segÃºn el concepto seleccionado
  document.querySelectorAll('.concepto-radio').forEach(label => {
    label.addEventListener('click', function() {
      // Toggle de active class se maneja mÃ¡s abajo, aquÃ­ detectamos el radio de este label
      setTimeout(() => {
        const radio = this.querySelector('input[type="radio"]');
        if (!radio) return;
        const nuevoConcepto = radio.value;
        const divMotivo = document.getElementById('div_eval_motivo');
        const inputMotivo = document.getElementById('eval_motivo');

        if (originalConcepto && originalConcepto !== 'pendiente' && originalConcepto !== nuevoConcepto) {
          if (divMotivo && inputMotivo) {
            divMotivo.style.display = 'block';
            inputMotivo.required = true;
          }
        } else {
          if (divMotivo && inputMotivo) {
            divMotivo.style.display = 'none';
            inputMotivo.required = false;
          }
        }
      }, 50);
    });
  });

  // Guardia antes de enviar
  modalEvaluar.querySelector('form')?.addEventListener('submit', function(e) {
    const id = parseInt(document.getElementById('evalId').value, 10);
    const concepto = this.querySelector('input[name="concepto"]:checked');
    const motivo = document.getElementById('eval_motivo').value.trim();

    if (!id || id <= 0) {
      e.preventDefault();
      alert('Error: ID de evaluaciÃ³n no cargado. Cierra el modal y haz clic en Evaluar nuevamente.');
      return;
    }
    if (!concepto) {
      e.preventDefault();
      alert('Debes seleccionar un concepto: Aprobado (A) o No Aprobado (D).');
      return;
    }

    if (originalConcepto && originalConcepto !== 'pendiente' && originalConcepto !== concepto.value && !motivo) {
      e.preventDefault();
      alert('Debes ingresar el motivo del cambio de calificaciÃ³n (ej. Plan de mejoramiento completado).');
      return;
    }

    console.log('[Eval] Enviando evaluaciÃ³n ID:', id, '| nuevo concepto:', concepto.value);
  });
}

// Toggle visual de radio buttons
document.querySelectorAll('.concepto-radio').forEach(label => {
  label.addEventListener('click', function() {
    document.querySelectorAll('.concepto-radio').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
  });
});
</script>

<style>
.concepto-radio.active {
    font-weight: 600;
}
.concepto-radio:has(input[value="A"]).active {
    background-color: #22c55e !important;
    color: white !important;
    border-color: #22c55e !important;
}
.concepto-radio:has(input[value="D"]).active {
    background-color: #ef4444 !important;
    color: white !important;
    border-color: #ef4444 !important;
}
</style>
