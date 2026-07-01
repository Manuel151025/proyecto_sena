
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">RetroalimentaciÃ³n AcadÃ©mica</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Revisa los comentarios, recomendaciones y fortalezas indicadas por tus instructores.
      <?php else: ?>
        Gestiona y registra retroalimentaciÃ³n para los aprendices.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($user_rol !== ROL_APRENDIZ): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaRetro">
      <i class="bi bi-plus-lg me-1"></i> Nueva retroalimentaciÃ³n
    </button>
  <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
  <div class="alert-flat success mb-3">
    <i class="bi bi-check-circle"></i>
    <div><?= htmlspecialchars($success) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert-flat danger mb-3">
    <i class="bi bi-exclamation-circle"></i>
    <div>
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($feedbacks as $fb): ?>
    <?php $meta = $tipos_label[$fb['tipo']] ?? ['â€”','secondary','bi-chat']; ?>
    <div class="col-md-6 col-lg-4">
      <div class="card glass-card h-100 border-0 shadow-sm">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <span class="badge-soft <?= $meta[1] ?>">
              <i class="bi <?= $meta[2] ?> me-1"></i>
              <?= $meta[0] ?>
            </span>
            <small class="text-muted"><?= date('d/m/Y h:i A', strtotime($fb['fecha_creacion'])) ?></small>
          </div>

          <?php if (!empty($fb['privada'])): ?>
            <div class="small mb-2">
              <span class="badge-soft secondary"><i class="bi bi-lock-fill me-1"></i>Privada</span>
            </div>
          <?php endif; ?>

          <p class="card-text text-dark flex-grow-1" style="font-size:.95rem;font-style:italic">
            "<?= htmlspecialchars($fb['contenido']) ?>"
          </p>

          <div class="border-top pt-2 mt-3 d-flex align-items-center gap-2">
            <?php if ($user_rol === ROL_APRENDIZ): ?>
              <div class="avatar"
                   style="width:30px;height:30px;font-size:.75rem;background:<?= htmlspecialchars($fb['inst_color'] ?? '#3B82F6') ?>">
                <?= getInitials($fb['instructor_nombre']) ?>
              </div>
              <div class="small">
                <span class="text-muted d-block" style="font-size:.7rem">Instructor:</span>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($fb['instructor_nombre']) ?></span>
              </div>
            <?php else: ?>
              <div class="small">
                <span class="text-muted d-block" style="font-size:.7rem">Para aprendiz:</span>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($fb['aprendiz_nombre'] ?? 'Desconocido') ?></span>
                <small class="text-muted d-block" style="font-size:.65rem">
                  Por: <?= htmlspecialchars($fb['instructor_nombre']) ?>
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($feedbacks)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-chat-left-text d-block mb-2" style="font-size:3rem;opacity:.3"></i>
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        AÃºn no has recibido retroalimentaciones.
      <?php else: ?>
        No has registrado retroalimentaciones todavÃ­a. Usa el botÃ³n
        <strong>Nueva retroalimentaciÃ³n</strong> arriba para crear la primera.
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ===== Modal: nueva retroalimentaciÃ³n ===== -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="modal fade" id="modalNuevaRetro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="create_feedback">

        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-chat-left-quote me-2"></i>Nueva retroalimentaciÃ³n</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">

          <?php if (empty($aprendices_disponibles)): ?>
            <div class="alert-flat warning mb-0">
              <i class="bi bi-exclamation-circle"></i>
              <div>
                <?php if ($user_rol === ROL_INSTRUCTOR): ?>
                  No tienes aprendices matriculados en tus fichas. Cuando se te asignen, podrÃ¡s registrar retroalimentaciÃ³n.
                <?php else: ?>
                  No hay aprendices matriculados en el sistema.
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>

            <div class="mb-3">
              <label class="form-label">Aprendiz</label>
              <select name="aprendiz_id" class="form-select" required
                      data-picker
                      data-picker-label="Buscar aprendiz"
                      data-picker-placeholder="Escribe nombre, documento o ficha...">
                <option value="">Selecciona un aprendiz...</option>
                <?php foreach ($aprendices_disponibles as $ap): ?>
                  <option value="<?= (int)$ap['id'] ?>"
                          data-search="<?= htmlspecialchars(($ap['numero_documento'] ?? '') . ' ' . ($ap['numero_ficha'] ?? '')) ?>">
                    <?= htmlspecialchars($ap['nombre']) ?>
                    <?= !empty($ap['numero_documento']) ? ' â€” ' . htmlspecialchars($ap['tipo_documento'] ?? 'CC') . ' ' . htmlspecialchars($ap['numero_documento']) : '' ?>
                    <?= !empty($ap['numero_ficha']) ? ' Â· Ficha #' . htmlspecialchars($ap['numero_ficha']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Tipo</label>
              <div class="row g-2">
                <?php foreach ($tipos_label as $key => $meta): ?>
                  <div class="col-md-4">
                    <label class="d-block">
                      <input type="radio" name="tipo" value="<?= $key ?>" class="d-none" required
                             <?= $key === 'aspecto_mejorar' ? 'checked' : '' ?>>
                      <div class="card text-center p-2" style="cursor:pointer">
                        <i class="bi <?= $meta[2] ?>" style="font-size:1.5rem"></i>
                        <small class="mt-1"><?= $meta[0] ?></small>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Contenido</label>
              <textarea name="contenido" class="form-control" rows="5"
                        minlength="10" maxlength="2000"
                        placeholder="Describe la retroalimentaciÃ³n de forma constructiva..."
                        required></textarea>
              <div class="small text-muted mt-1">Entre 10 y 2000 caracteres.</div>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="privada" id="chkPrivada" value="1">
              <label class="form-check-label" for="chkPrivada">
                <i class="bi bi-lock-fill me-1"></i>
                Privada (el aprendiz no la verÃ¡; solo instructores y coordinadores)
              </label>
            </div>

          <?php endif; ?>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <?php if (!empty($aprendices_disponibles)): ?>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2 me-1"></i> Registrar
            </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
