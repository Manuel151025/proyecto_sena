<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
$colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
?>
<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditarUsuarioLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formEditarUsuario" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="id" id="edit-id">
        <div class="modal-body p-0">
          <div class="row g-0">
            <!-- Formulario (Izquierda) -->
            <div class="col-md-7 p-4 border-end-custom" style="background: var(--surface);">
              <!-- Alertas dentro del modal -->
              <div id="modalEditarUsuarioAlert" class="alert-flat mb-3" style="display: none;"></div>

              <div class="mb-3">
                <label for="edit-nombre" class="form-label fw-semibold">Nombre completo</label>
                <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-custom" placeholder="Ej: Carlos Andrés Martínez" maxlength="60" minlength="3" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')" required>
              </div>

              <div class="mb-3">
                <label for="edit-email" class="form-label fw-semibold">Email institucional</label>
                <input type="email" id="edit-email" name="email" class="form-control form-control-custom" placeholder="usuario@sena.edu.co" maxlength="100" required>
              </div>

              <div class="mb-3">
                <label for="edit-password" class="form-label fw-semibold">Contraseña (Opcional)</label>
                <input type="password" id="edit-password" name="password" class="form-control form-control-custom" placeholder="Dejar en blanco para mantener la actual" maxlength="60" minlength="6">
                <small class="text-muted" style="font-size: 0.75rem;">Mínimo 6 caracteres si deseas cambiarla.</small>
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="edit-rol" class="form-label fw-semibold">Rol</label>
                  <select id="edit-rol" name="rol" class="form-select form-select-custom" required>
                    <option value="aprendiz">Aprendiz</option>
                    <option value="instructor">Instructor</option>
                    <option value="coordinador">Coordinador</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="edit-estado" class="form-label fw-semibold">Estado</label>
                  <select id="edit-estado" name="estado" class="form-select form-select-custom" required>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                    <option value="bloqueado">Bloqueado</option>
                  </select>
                </div>
              </div>

              <div class="mb-0">
                <label class="form-label fw-semibold">Color de Avatar</label>
                <div class="d-flex gap-2 flex-wrap mt-1">
                  <?php foreach ($colors as $idx => $color): ?>
                    <input type="radio" name="avatar_color" id="ecolor-<?= $idx ?>" value="<?= htmlspecialchars($color) ?>" style="display: none;">
                    <label for="ecolor-<?= $idx ?>" class="modal-edit-color-lbl" style="width: 34px; height: 34px; background: <?= htmlspecialchars($color) ?>; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;" onclick="selectEditModalColor(this)"></label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Panel de Información (Derecha) -->
            <div class="col-md-5 p-4 bg-light-premium" style="background: var(--surface-2);">
              <h5 class="fw-bold text-primary mb-4" style="font-size: 0.95rem; letter-spacing: -0.01em;">
                <i class="bi bi-info-circle me-2"></i>Información de Edición
              </h5>
              
              <div class="info-section-item mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-shield-exclamation text-warning" style="font-size: 1.1rem;"></i>
                  <strong class="small" style="color: var(--text); font-weight: 600;">Estado de Cuenta</strong>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.8rem; padding-left: 24px; line-height: 1.4;">
                  Cambiar el estado a <strong>Inactivo</strong> o <strong>Bloqueado</strong> impedirá el ingreso del usuario al sistema de manera inmediata.
                </p>
              </div>

              <div class="info-section-item mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-key text-info" style="font-size: 1.1rem;"></i>
                  <strong class="small" style="color: var(--text); font-weight: 600;">Actualización de Clave</strong>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.8rem; padding-left: 24px; line-height: 1.4;">
                  Si deja el campo de contraseña vacío, el usuario mantendrá su contraseña actual intacta.
                </p>
              </div>

              <div class="info-section-item">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-gear-fill text-secondary" style="font-size: 1.1rem;"></i>
                  <strong class="small" style="color: var(--text); font-weight: 600;">Cambio de Rol</strong>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.8rem; padding-left: 24px; line-height: 1.4;">
                  Modificar el rol cambiará sus permisos de forma instantánea. Asegúrese de que el usuario pertenezca a la ficha o programa adecuado si es aprendiz/instructor.
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitEditarUsuario">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
#modalEditarUsuario .modal-content {
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: var(--shadow-md);
  background: var(--surface);
}
#modalEditarUsuario .modal-header {
  background: linear-gradient(135deg, var(--bg-elev) 0%, var(--surface-2) 100%);
  border-bottom: 1px solid var(--border);
  padding: 1.25rem 1.5rem;
}
#modalEditarUsuario .modal-footer {
  border-top: 1px solid var(--border);
  background: var(--surface);
  padding: 1rem 1.5rem;
}
#modalEditarUsuario .modal-title {
  font-weight: 700;
  letter-spacing: -0.019em;
}
.modal-edit-color-lbl {
  border: 2px solid transparent;
  transition: all 0.2s ease-in-out;
}
.modal-edit-color-lbl:hover {
  transform: scale(1.1);
}
.modal-edit-color-lbl.selected {
  border-color: var(--text) !important;
  transform: scale(1.15);
  box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
[data-theme="dark"] .modal-edit-color-lbl.selected {
  border-color: #fff !important;
  box-shadow: 0 4px 10px rgba(255,255,255,0.25);
}
</style>

<script>
function selectEditModalColor(labelEl) {
  document.querySelectorAll('.modal-edit-color-lbl').forEach(lbl => {
    lbl.classList.remove('selected');
    lbl.style.borderColor = 'transparent';
  });
  labelEl.classList.add('selected');
  labelEl.style.borderColor = 'var(--text)';
}

function openEditUserModal(userId) {
  const alertContainer = document.getElementById('modalEditarUsuarioAlert');
  if (alertContainer) alertContainer.style.display = 'none';

  const form = document.getElementById('formEditarUsuario');
  if (form) form.reset();

  // Desmarcar todos los colores primero
  document.querySelectorAll('.modal-edit-color-lbl').forEach(lbl => {
    lbl.classList.remove('selected');
    lbl.style.borderColor = 'transparent';
  });

  // Mostrar el modal
  const modalEl = document.getElementById('modalEditarUsuario');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  // Obtener datos por AJAX (GET)
  fetch('<?= APP_URL ?>/index.php/usuarios/editar?id=' + userId, {
    method: 'GET',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(res => {
    if (res.status === 'success') {
      const u = res.data;
      document.getElementById('edit-id').value = u.id;
      document.getElementById('edit-nombre').value = u.nombre;
      document.getElementById('edit-email').value = u.email;
      document.getElementById('edit-rol').value = u.rol;
      document.getElementById('edit-estado').value = u.estado;

      // Marcar el color del avatar correspondiente
      const colorRadio = document.querySelector('input[name="avatar_color"][value="' + u.avatar_color + '"]');
      if (colorRadio) {
        colorRadio.checked = true;
        const label = document.querySelector('label[for="' + colorRadio.id + '"]');
        if (label) selectEditModalColor(label);
      }
    } else {
      alert('Error al cargar datos del usuario: ' + (res.message || 'Desconocido'));
      modal.hide();
    }
  })
  .catch(err => {
    console.error(err);
    alert('Error de red al cargar el usuario');
    modal.hide();
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formEditarUsuario');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const userId = document.getElementById('edit-id').value;
      const formData = new FormData(form);
      const submitBtn = document.getElementById('btnSubmitEditarUsuario');
      const alertContainer = document.getElementById('modalEditarUsuarioAlert');
      
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
      alertContainer.style.display = 'none';
      
      fetch('<?= APP_URL ?>/index.php/usuarios/editar?id=' + userId, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar Cambios';
        
        if (data.status === 'success') {
          alertContainer.className = 'alert-flat success mb-3';
          alertContainer.innerHTML = '<i class="bi bi-check-circle-fill text-success me-2" style="font-size:1.15rem;"></i><div>' + data.message + '</div>';
          alertContainer.style.display = 'flex';
          
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          let errHtml = '<i class="bi bi-exclamation-triangle-fill text-danger me-2" style="font-size:1.15rem;"></i><div>';
          if (data.errors && Array.isArray(data.errors)) {
            data.errors.forEach(err => {
              errHtml += '<div>' + err + '</div>';
            });
          } else {
            errHtml += '<div>Hubo un error al actualizar el usuario.</div>';
          }
          errHtml += '</div>';
          alertContainer.className = 'alert-flat danger mb-3';
          alertContainer.innerHTML = errHtml;
          alertContainer.style.display = 'flex';
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar Cambios';
        alertContainer.className = 'alert-flat danger mb-3';
        alertContainer.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger me-2" style="font-size:1.15rem;"></i><div>Error en la conexión con el servidor.</div>';
        alertContainer.style.display = 'flex';
        console.error(err);
      });
    });
  }
});
</script>
