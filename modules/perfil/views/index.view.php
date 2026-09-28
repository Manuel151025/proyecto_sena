<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos: el resultado eran
// avisos de PHP con rutas del servidor, y fragmentos de la pagina.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
?>

<div class="page-header">
  <div>
    <h1 class="mb-1">Mi perfil</h1>
    <p class="text-muted mb-0">Actualiza tus datos personales y credenciales de acceso.</p>
  </div>
</div>

<?php if (!empty($debeCambiarPassword)): ?>
  <div class="alert-flat warning mb-3">
    <i class="bi bi-shield-exclamation"></i>
    <div>Tu cuenta tiene una contraseña temporal. Debes establecer una nueva contraseña antes de continuar usando el sistema.</div>
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
          <div class="avatar lg" style="background: <?= e($user['avatar_color'] ?: '#39A900') ?>">
            <?= e(getInitials($user['nombre'])) ?>
          </div>
          <div>
            <div class="fw-semibold"><?= e($user['nombre']) ?></div>
            <div class="small text-muted">
              Miembro desde
              <?= !empty($user['fecha_creacion']) ? date('M Y', strtotime($user['fecha_creacion'])) : '—' ?>
            </div>
          </div>
        </div>

        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="datos">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="perfil-nombre">Nombres y apellidos</label>
              <input type="text" id="perfil-nombre" name="nombre" class="form-control"
                     value="<?= e($user['nombre']) ?>"
                     minlength="3" maxlength="<?= \Core\Formularios\UsuarioFormulario::MAX_NOMBRE ?>" data-filtro="persona" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="perfil-email">Correo institucional</label>
              <input type="email" id="perfil-email" class="form-control" value="<?= e($user['email']) ?>" disabled>
              <div class="small text-muted mt-1">Para cambiar el correo contacta al coordinador.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="perfil-rol">Rol</label>
              <input type="text" id="perfil-rol" class="form-control" value="<?= e(ucfirst((string)$user['rol'])) ?>" disabled>
            </div>
            <div class="col-md-6">
              <fieldset>
                <legend class="form-label fs-6">Color de avatar</legend>
                <div class="selector-colores">
                  <?php foreach ($colores as $i => $c): ?>
                    <label class="muestra-color" style="--color: <?= e($c) ?>">
                      <input type="radio" name="avatar_color" value="<?= e($c) ?>" <?= strcasecmp((string)$user['avatar_color'], $c) === 0 ? 'checked' : '' ?> required>
                      <span class="visually-hidden">Color <?= $i + 1 ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
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
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="contrasena">

          <div class="mb-3">
            <label class="form-label" for="pw-cur">Contraseña actual</label>
            <div class="position-relative">
              <input type="password" name="password_actual" id="pw-cur"
                     class="form-control pe-5" required autocomplete="current-password">
              <button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted"
                      data-pw-toggle="#pw-cur" style="height:100%">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="pw-nueva">Nueva contraseña</label>
            <input type="password" id="pw-nueva" name="password_nueva" class="form-control"
                   data-pw-strength required minlength="<?= (int)$minimo ?>" maxlength="72" autocomplete="new-password">
            <div class="pw-strength mt-2"><span></span><span></span><span></span><span></span></div>
            <div class="mt-2">
              <div class="pw-req" data-req="len"><i class="bi bi-circle"></i> Mínimo <?= (int)$minimo ?> caracteres</div>
              <div class="pw-req" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div>
              <div class="pw-req" data-req="num"><i class="bi bi-circle"></i> Contiene números</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="pw-confirmar">Confirmar nueva contraseña</label>
            <input type="password" id="pw-confirmar" name="password_confirmar" class="form-control"
                   required minlength="<?= (int)$minimo ?>" maxlength="72" autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-shield-check me-1"></i>Actualizar contraseña
          </button>
        </form>
      </div>
    </div>
  </div>

</div>
