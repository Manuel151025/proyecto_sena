
<h1 class="mb-1">Mi perfil</h1>
<p class="text-muted">Actualiza tus datos personales y credenciales de acceso.</p>

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

  <!-- ===== Datos personales ===== -->
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">Datos personales</div>
      <div class="card-body">

        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="avatar lg" style="background: <?= htmlspecialchars($user['avatar_color']) ?>">
            <?= getInitials($user['nombre']) ?>
          </div>
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($user['nombre']) ?></div>
            <div class="small text-muted">
              Miembro desde
              <?= !empty($user['fecha_creacion']) ? date('M Y', strtotime($user['fecha_creacion'])) : 'â€”' ?>
            </div>
          </div>
        </div>

        <form method="POST" action="">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombres y apellidos</label>
              <input type="text" name="nombre" class="form-control"
                     value="<?= htmlspecialchars($user['nombre']) ?>"
                     minlength="3" maxlength="150" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Correo institucional</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
              <div class="small text-muted mt-1">Para cambiar el correo contacta al coordinador.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Rol</label>
              <input type="text" class="form-control" value="<?= ucfirst(htmlspecialchars($user['rol'])) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label">Color de avatar</label>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($colores_validos as $color): ?>
                  <label class="d-inline-block" style="cursor:pointer">
                    <input type="radio" name="avatar_color" value="<?= $color ?>"
                           class="d-none"
                           <?= $user['avatar_color'] === $color ? 'checked' : '' ?>>
                    <span class="d-inline-block rounded-circle"
                          style="width:32px;height:32px;background:<?= $color ?>;
                                 border:3px solid <?= $user['avatar_color'] === $color ? '#1f2937' : 'transparent' ?>;
                                 transition:border-color .15s"></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2 me-1"></i>Guardar cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===== Cambiar contraseña ===== -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Cambiar contraseña</div>
      <div class="card-body">
        <form method="POST" action="">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="mb-3">
            <label class="form-label">Contraseña actual</label>
            <div class="position-relative">
              <input type="password" name="password_actual" id="pw-cur"
                     class="form-control pe-5" required>
              <button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted"
                      data-pw-toggle="#pw-cur" style="height:100%">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Nueva contraseÃ±a</label>
            <input type="password" name="password_nueva" class="form-control"
                   data-pw-strength required minlength="8">
            <div class="pw-strength mt-2"><span></span><span></span><span></span><span></span></div>
            <div class="mt-2">
              <div class="pw-req" data-req="len"><i class="bi bi-circle"></i> MÃ­nimo 8 caracteres</div>
              <div class="pw-req" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div>
              <div class="pw-req" data-req="num"><i class="bi bi-circle"></i> Contiene nÃºmeros</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirmar nueva contraseÃ±a</label>
            <input type="password" name="password_confirmar" class="form-control"
                   required minlength="8">
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-shield-check me-1"></i>Actualizar contraseÃ±a
          </button>
        </form>
      </div>
    </div>
  </div>

</div>
