<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Proyectos Formativos</h1>
    <p class="text-muted mb-0">Cada proyecto integra las competencias y resultados de aprendizaje de un programa de formaciÃ³n.</p>
  </div>
  <?php if ($user_rol === ROL_COORDINADOR): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
    <i class="bi bi-plus-lg me-1"></i>Nuevo Proyecto
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-flat success mb-3"><i class="bi bi-check-circle-fill"></i><div><?= htmlspecialchars($success) ?></div></div>
<?php endif; ?>

<div class="row g-4">
  <?php foreach ($proyectos as $proj): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid var(--sena-primary); border-radius: 12px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 30px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.05)';">
        <div class="card-body d-flex flex-column p-4">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft primary fw-semibold" style="letter-spacing: 0.5px;"><?= htmlspecialchars($proj['codigo']) ?></span>
            <span class="badge-soft <?= $proj['estado'] === 'activo' ? 'success' : ($proj['estado'] === 'finalizado' ? 'info' : 'secondary') ?>"><?= ucfirst($proj['estado']) ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($proj['nombre']) ?></h5>
          <small class="text-muted d-block mb-3" style="max-height: 40px; overflow: hidden;"><?= htmlspecialchars($proj['objetivo'] ?? 'Sin objetivo definido') ?></small>
          
          <div class="p-3 rounded-3 mb-3 flex-grow-1" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.04); font-size: 0.85rem;">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-folder2-open me-1"></i>Fichas vinculadas</span>
              <span class="fw-bold"><?= (int)$proj['total_fichas'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted"><i class="bi bi-people me-1"></i>Aprendices</span>
              <span class="fw-bold"><?= (int)$proj['total_aprendices'] ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted"><i class="bi bi-list-task me-1"></i>Fases</span>
              <span class="fw-bold text-success"><?= (int)$proj['fases_completadas'] ?> / <?= (int)$proj['total_fases'] ?></span>
            </div>
            
            <div class="text-muted small mb-1">Avance del proyecto:</div>
            <div class="progress" style="height: 8px; border-radius: 10px;">
              <?php $avance = (int)($proj['avance_promedio'] ?? 0); ?>
              <div class="progress-bar" role="progressbar" style="width: <?= $avance ?>%; background: <?= $avance >= 75 ? 'var(--sena-primary)' : ($avance >= 40 ? '#eab308' : '#ef4444') ?>; border-radius: 10px;"></div>
            </div>
            <div class="text-end fw-bold mt-1" style="font-size: 0.8rem;"><?= $avance ?>%</div>
          </div>

          <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/index.php/fases?proyecto_id=<?= $proj['id'] ?>" class="btn btn-primary flex-grow-1" style="border-radius: 8px;">
              <i class="bi bi-list-task me-1"></i>Ver Fases
            </a>
            <?php if ($user_rol === ROL_COORDINADOR): ?>
             <button class="btn btn-soft px-3" style="border-radius: 8px;"
              onclick="abrirModalEditarProyecto(
                <?= $proj['id'] ?>, <?= htmlspecialchars(json_encode($proj['nombre']), ENT_QUOTES, 'UTF-8') ?>,
                <?= htmlspecialchars(json_encode($proj['codigo']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($proj['objetivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                <?= htmlspecialchars(json_encode($proj['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($proj['estado']), ENT_QUOTES, 'UTF-8') ?>)">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $proj['id'] ?>">
              <button type="submit" class="btn btn-soft text-danger px-3" style="border-radius: 8px;"
                onclick="return confirm('Â¿Eliminar el proyecto <?= htmlspecialchars(addslashes($proj['nombre'])) ?>?')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($proyectos)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-kanban d-block mb-2" style="font-size:3rem; opacity:0.3;"></i>
      No hay proyectos formativos creados.
    </div>
  <?php endif; ?>
</div>

<!-- Modal Editar Proyecto -->
<?php if ($user_rol === ROL_COORDINADOR): ?>
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Proyecto Formativo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre del Proyecto <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" maxlength="100" minlength="3" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]/g, '')" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
            <input type="text" name="codigo" id="edit_codigo" class="form-control" maxlength="20" minlength="2" pattern="^[a-zA-Z0-9\-]+$" oninput="this.value = this.value.replace(/[^a-zA-Z0-9\-]/g, '').toUpperCase()" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Objetivo</label>
            <textarea name="objetivo" id="edit_objetivo" class="form-control" rows="2" maxlength="1000" oninput="this.value = this.value.replace(/[<>]/g, '')"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Descripción</label>
            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2" maxlength="1000" oninput="this.value = this.value.replace(/[<>]/g, '')"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Estado</label>
            <select name="estado" id="edit_estado" class="form-select">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
              <option value="finalizado">Finalizado</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalEditarProyecto(id, nombre, codigo, objetivo, descripcion, estado) {
    document.getElementById('edit_id').value          = id;
    document.getElementById('edit_nombre').value      = nombre;
    document.getElementById('edit_codigo').value      = codigo;
    document.getElementById('edit_objetivo').value    = objetivo;
    document.getElementById('edit_descripcion').value = descripcion;
    document.getElementById('edit_estado').value      = estado;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
<?php endif; ?>

<!-- Modal Crear Proyecto -->
<?php if ($user_rol === ROL_COORDINADOR): ?>
<div class="modal fade" id="modalCrear" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="crear">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
          <h5 class="modal-title"><i class="bi bi-kanban me-2"></i>Nuevo Proyecto Formativo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre del Proyecto <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Sistema de Gestión de Inventarios Web" maxlength="100" minlength="3" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]/g, '')">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
            <input type="text" name="codigo" class="form-control" required placeholder="Ej: PF-ADSO-02" maxlength="20" minlength="2" pattern="^[a-zA-Z0-9\-]+$" oninput="this.value = this.value.replace(/[^a-zA-Z0-9\-]/g, '').toUpperCase()">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Objetivo</label>
            <textarea name="objetivo" class="form-control" rows="2" placeholder="Objetivo general del proyecto formativo" maxlength="1000" oninput="this.value = this.value.replace(/[<>]/g, '')"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción ampliada del proyecto" maxlength="1000" oninput="this.value = this.value.replace(/[<>]/g, '')"></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Crear Proyecto</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
