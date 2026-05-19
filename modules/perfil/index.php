<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pageTitle = 'Mi Perfil · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>
<h1 class="mb-1">Mi perfil</h1>
<p class="text-muted">Actualiza tus datos personales y credenciales de acceso.</p>
<div class="row g-3">
  <div class="col-lg-7"><div class="card mb-3"><div class="card-header">Datos personales</div><div class="card-body">
    <div class="d-flex align-items-center gap-3 mb-4"><div class="avatar lg"><?= getInitials(getCurrentUser()['nombre']) ?></div><div><button class="btn btn-soft btn-sm"><i class="bi bi-camera me-1"></i>Cambiar foto</button><button class="btn btn-link btn-sm text-danger">Eliminar</button><div class="small text-muted mt-1">JPG o PNG · máx 2MB</div></div></div>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Nombres</label><input class="form-control" value="<?= htmlspecialchars(getCurrentUser()['nombre']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Correo institucional</label><input class="form-control" value="<?= htmlspecialchars(getCurrentUser()['email']) ?>" disabled></div>
      <div class="col-md-6"><label class="form-label">Rol</label><input class="form-control" value="<?= ucfirst(getCurrentUser()['rol']) ?>" disabled></div>
    </div>
    <div class="mt-3 text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar cambios</button></div>
  </div></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header">Cambiar contraseña</div><div class="card-body">
    <div class="mb-3"><label class="form-label">Contraseña actual</label><div class="position-relative"><input type="password" id="pw-cur" class="form-control pe-5"><button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted" data-pw-toggle="#pw-cur" style="height:100%"><i class="bi bi-eye"></i></button></div></div>
    <div class="mb-3"><label class="form-label">Nueva contraseña</label><input type="password" class="form-control" data-pw-strength><div class="pw-strength mt-2"><span></span><span></span><span></span><span></span></div><div class="mt-2"><div class="pw-req" data-req="len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</div><div class="pw-req" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div><div class="pw-req" data-req="num"><i class="bi bi-circle"></i> Contiene números</div></div></div>
    <div class="mb-3"><label class="form-label">Confirmar nueva contraseña</label><input type="password" class="form-control"></div>
    <button class="btn btn-primary w-100"><i class="bi bi-shield-check me-1"></i>Actualizar contraseña</button>
  </div></div></div>
</div>
