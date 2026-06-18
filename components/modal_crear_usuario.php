<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
$colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
?>
<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCrearUsuarioLabel"><i class="bi bi-person-plus me-2 text-primary"></i>Registrar Nuevo Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formCrearUsuario" novalidate>
        <div class="modal-body p-0">
          <div class="row g-0">
            <!-- Formulario (Izquierda) -->
            <div class="col-md-7 p-4 border-end-custom" style="background: var(--surface);">
              <!-- Alertas dentro del modal -->
              <div id="modalCrearUsuarioAlert" class="alert-flat mb-3" style="display: none;"></div>

              <div class="mb-3">
                <label for="modal-nombre" class="form-label fw-semibold">Nombre completo</label>
                <input type="text" id="modal-nombre" name="nombre" class="form-control form-control-custom" placeholder="Ej: Carlos Andrés Martínez" required>
              </div>

              <div class="mb-3">
                <label for="modal-email" class="form-label fw-semibold">Email institucional</label>
                <input type="email" id="modal-email" name="email" class="form-control form-control-custom" placeholder="usuario@sena.edu.co" required>
              </div>

              <div class="mb-3">
                <label for="modal-password" class="form-label fw-semibold">Contraseña</label>
                <input type="password" id="modal-password" name="password" class="form-control form-control-custom" placeholder="Mínimo 6 caracteres" required>
              </div>

              <div class="mb-3">
                <label for="modal-rol" class="form-label fw-semibold">Rol del Sistema</label>
                <select id="modal-rol" name="rol" class="form-select form-select-custom" required>
                  <option value="aprendiz" selected>Aprendiz</option>
                  <option value="instructor">Instructor</option>
                  <option value="coordinador">Coordinador</option>
                </select>
              </div>

              <div class="mb-0">
                <label class="form-label fw-semibold">Color de Avatar</label>
                <div class="d-flex gap-2 flex-wrap mt-1">
                  <?php foreach ($colors as $idx => $color): ?>
                    <input type="radio" name="avatar_color" id="mcolor-<?= $idx ?>" value="<?= htmlspecialchars($color) ?>" <?= $idx === 0 ? 'checked' : '' ?> style="display: none;">
                    <label for="mcolor-<?= $idx ?>" class="modal-color-lbl" style="width: 34px; height: 34px; background: <?= htmlspecialchars($color) ?>; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;" onclick="selectModalColor(this)"></label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Panel de Información (Derecha) -->
            <div class="col-md-5 p-4 bg-light-premium" style="background: var(--surface-2);">
              <h5 class="fw-bold text-primary mb-4" style="font-size: 0.95rem; letter-spacing: -0.01em;">
                <i class="bi bi-shield-check me-2"></i>Políticas & Permisos
              </h5>
              
              <div class="info-section-item mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-envelope-check text-success" style="font-size: 1.1rem;"></i>
                  <strong class="small" style="color: var(--text); font-weight: 600;">Email Único</strong>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.8rem; padding-left: 24px; line-height: 1.4;">
                  Cada usuario debe contar con un correo electrónico institucional único en el sistema.
                </p>
              </div>

              <div class="info-section-item mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-lock-fill text-warning" style="font-size: 1.1rem;"></i>
                  <strong class="small" style="color: var(--text); font-weight: 600;">Contraseña Segura</strong>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.8rem; padding-left: 24px; line-height: 1.4;">
                  Se almacena mediante hash criptográfico irreversible (mínimo 6 caracteres).
                </p>
              </div>

              <div class="info-section-item">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-person-badge-fill text-info" style="font-size: 1.1rem;"></i>
                  <strong class="small" style="color: var(--text); font-weight: 600;">Permisos por Rol</strong>
                </div>
                <div class="d-flex flex-column gap-3" style="padding-left: 24px;">
                  <div class="role-desc">
                    <span class="badge-soft primary" style="font-size: 0.65rem; border-radius: 4px;">Coordinador</span>
                    <p class="text-muted mb-0 mt-1" style="font-size: 0.76rem; line-height: 1.3;">Gestión de estructura curricular, fichas y usuarios.</p>
                  </div>
                  <div class="role-desc">
                    <span class="badge-soft info" style="font-size: 0.65rem; border-radius: 4px;">Instructor</span>
                    <p class="text-muted mb-0 mt-1" style="font-size: 0.76rem; line-height: 1.3;">Gestión de fichas asignadas y evaluaciones de aprendices.</p>
                  </div>
                  <div class="role-desc">
                    <span class="badge-soft success" style="font-size: 0.65rem; border-radius: 4px;">Aprendiz</span>
                    <p class="text-muted mb-0 mt-1" style="font-size: 0.76rem; line-height: 1.3;">Visualización de su proyecto, evidencias y juicios.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitCrearUsuario">Crear Usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
#modalCrearUsuario .modal-content {
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: var(--shadow-md);
  background: var(--surface);
}
#modalCrearUsuario .modal-header {
  background: linear-gradient(135deg, var(--bg-elev) 0%, var(--surface-2) 100%);
  border-bottom: 1px solid var(--border);
  padding: 1.25rem 1.5rem;
}
#modalCrearUsuario .modal-footer {
  border-top: 1px solid var(--border);
  background: var(--surface);
  padding: 1rem 1.5rem;
}
#modalCrearUsuario .modal-title {
  font-weight: 700;
  letter-spacing: -0.019em;
}
.form-control-custom, .form-select-custom {
  border-radius: 10px !important;
  padding: 0.65rem 0.85rem !important;
  font-size: 0.9rem !important;
  background: var(--surface) !important;
  border: 1px solid var(--border) !important;
  color: var(--text) !important;
}
.form-control-custom:focus, .form-select-custom:focus {
  border-color: var(--sena-primary) !important;
  box-shadow: 0 0 0 3px rgba(57, 169, 0, 0.15) !important;
}
.border-end-custom {
  border-right: 1px solid var(--border) !important;
}
@media (max-width: 768px) {
  .border-end-custom {
    border-right: none !important;
    border-bottom: 1px solid var(--border) !important;
  }
}
.modal-color-lbl {
  border: 2px solid transparent;
  transition: all 0.2s ease-in-out;
}
.modal-color-lbl:hover {
  transform: scale(1.1);
}
.modal-color-lbl.selected {
  border-color: var(--text) !important;
  transform: scale(1.15);
  box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
[data-theme="dark"] .modal-color-lbl.selected {
  border-color: #fff !important;
  box-shadow: 0 4px 10px rgba(255,255,255,0.25);
}
</style>

<script>
function selectModalColor(labelEl) {
  document.querySelectorAll('.modal-color-lbl').forEach(lbl => {
    lbl.classList.remove('selected');
    lbl.style.borderColor = 'transparent';
  });
  labelEl.classList.add('selected');
  labelEl.style.borderColor = 'var(--text)';
}

document.addEventListener('DOMContentLoaded', () => {
  // Inicializar selección visual del primer color de avatar
  const firstColor = document.querySelector('input[name="avatar_color"]:checked');
  if (firstColor) {
    const label = document.querySelector('label[for="' + firstColor.id + '"]');
    if (label) selectModalColor(label);
  }

  // Lógica de submit por AJAX
  const form = document.getElementById('formCrearUsuario');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(form);
      const submitBtn = document.getElementById('btnSubmitCrearUsuario');
      const alertContainer = document.getElementById('modalCrearUsuarioAlert');
      
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Creando...';
      alertContainer.style.display = 'none';
      
      fetch('<?= APP_URL ?>/index.php/usuarios/crear', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Crear Usuario';
        
        if (data.status === 'success') {
          alertContainer.className = 'alert-flat success mb-3';
          alertContainer.innerHTML = '<i class="bi bi-check-circle-fill text-success me-2" style="font-size:1.15rem;"></i><div>' + data.message + '</div>';
          alertContainer.style.display = 'flex';
          form.reset();
          
          // Re-marcar color por defecto
          const defaultColorInput = document.getElementById('mcolor-0');
          if (defaultColorInput) {
            defaultColorInput.checked = true;
            selectModalColor(document.querySelector('label[for="mcolor-0"]'));
          }

          // Si estamos en la lista de usuarios, recargar para mostrar el nuevo elemento
          if (window.location.pathname.includes('/usuarios') || window.location.href.includes('/usuarios')) {
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            // Si estamos en el dashboard, cerrar el modal tras 1.2 segundos
            setTimeout(() => {
              const modalEl = document.getElementById('modalCrearUsuario');
              const modal = bootstrap.Modal.getInstance(modalEl);
              if (modal) modal.hide();
              alertContainer.style.display = 'none';
            }, 1200);
          }
        } else {
          let errHtml = '<i class="bi bi-exclamation-triangle-fill text-danger me-2" style="font-size:1.15rem;"></i><div>';
          if (data.errors && Array.isArray(data.errors)) {
            data.errors.forEach(err => {
              errHtml += '<div>' + err + '</div>';
            });
          } else {
            errHtml += '<div>Hubo un error al crear el usuario.</div>';
          }
          errHtml += '</div>';
          alertContainer.className = 'alert-flat danger mb-3';
          alertContainer.innerHTML = errHtml;
          alertContainer.style.display = 'flex';
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Crear Usuario';
        alertContainer.className = 'alert-flat danger mb-3';
        alertContainer.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger me-2" style="font-size:1.15rem;"></i><div>Error en la conexión con el servidor.</div>';
        alertContainer.style.display = 'flex';
        console.error(err);
      });
    });
  }
});
</script>
