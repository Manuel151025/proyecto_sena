<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$hayFiltros = $filtros['search'] !== '' || $filtros['rol'] !== '' || $filtros['estado'] !== '';
$exportar = APP_URL . '/index.php/usuarios/exportar?' . http_build_query(array_filter($filtros, static fn($x) => $x !== ''));
?>
<div class="page-header">
  <div>
    <h1>Gestión de Usuarios</h1>
    <p class="text-muted mb-0">Cuentas de coordinación, instructores y aprendices.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= e($exportar . '&formato=xlsx') ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
    <a href="<?= e(APP_URL . '/index.php/usuarios/importar') ?>" class="btn btn-soft"><i class="bi bi-upload me-1"></i>Importar</a>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"
            <?= $abrir_nuevo ? 'data-abrir-al-cargar' : '' ?>><i class="bi bi-plus-lg me-1"></i>Nuevo usuario</button>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<?php if ($credencial): ?>
<div class="card border-0 shadow-sm mb-3 credencial-temporal">
  <div class="card-body">
    <div class="d-flex gap-3 align-items-start">
      <i class="bi bi-shield-lock fs-3 text-warning"></i>
      <div class="min-w-0">
        <div class="fw-bold">Contraseña temporal <?= $credencial['motivo'] === 'creada' ? 'de la nueva cuenta' : 'restablecida' ?></div>
        <div class="small text-muted mb-2">Entrégala a <?= e($credencial['nombre']) ?> (<?= e($credencial['email']) ?>). No volverá a mostrarse; se le pedirá cambiarla al entrar.</div>
        <code class="fs-5 user-select-all"><?= e($credencial['password']) ?></code>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Búsqueda y filtros en el servidor: el listado está paginado. -->
<form method="GET" class="toolbar mb-3">
  <div class="search">
    <i class="bi bi-search"></i>
    <label class="visually-hidden" for="searchUsers">Buscar usuario</label>
    <input type="search" name="search" id="searchUsers" class="form-control" maxlength="100"
           placeholder="Buscar por nombre o correo..." value="<?= e($filtros['search']) ?>">
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="filtroRol">Rol</label>
    <select name="rol" id="filtroRol" class="form-select" data-autoenvio data-picker data-picker-label="Rol">
      <option value="">Todos los roles</option>
      <?php foreach ($roles_label as $valor => $etiqueta): ?>
        <option value="<?= e($valor) ?>" <?= $filtros['rol'] === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="filtroEstado">Estado</label>
    <select name="estado" id="filtroEstado" class="form-select" data-autoenvio data-picker data-picker-label="Estado">
      <option value="">Todos los estados</option>
      <?php foreach ($estados_label as $valor => [$texto]): ?>
        <option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-soft"><i class="bi bi-funnel me-1"></i>Filtrar</button>
  <?php if ($hayFiltros): ?><a href="<?= e(APP_URL . '/index.php/usuarios') ?>" class="btn btn-soft text-muted">Limpiar</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Creación</th><th class="text-end">Acciones</th></tr>
    </thead>
    <tbody>
      <?php if (empty($usuarios)): ?>
      <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-search d-block mb-2 fs-3 opacity-50"></i>No hay usuarios que coincidan.</td></tr>
      <?php endif; ?>
      <?php foreach ($usuarios as $u):
          $esYo = (int)$u['id'] === $actor_id;
          [$estTxt, $estCls] = $estados_label[$u['estado']] ?? [$u['estado'], 'secondary'];
      ?>
      <tr>
        <td>
          <strong><?= e($u['nombre']) ?></strong>
          <?php if ($esYo): ?><span class="badge-soft info ms-1">Tú</span><?php endif; ?>
          <?php if ((int)$u['debe_cambiar_password'] === 1): ?><span class="badge-soft warning ms-1" title="Aún no cambia su contraseña temporal">Clave temporal</span><?php endif; ?>
        </td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge-soft primary"><?= e($roles_label[$u['rol']] ?? $u['rol']) ?></span></td>
        <td><span class="badge-soft <?= e($estCls) ?>"><?= e($estTxt) ?></span></td>
        <td><?= e(date('d/m/Y', strtotime((string)$u['fecha_creacion']))) ?></td>
        <td class="text-end">
          <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-soft" data-modal="#modalEditar" aria-label="Editar <?= e($u['nombre']) ?>"
                    data-valores="<?= datosJson(['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'],
                        'rol' => $u['rol'], 'estado' => $u['estado'], 'avatar_color' => $u['avatar_color'] ?: $colores[0]]) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Generar una nueva contraseña temporal para ' . $u['nombre'] . '? La actual dejará de funcionar.') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="restablecer">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft" aria-label="Restablecer contraseña de <?= e($u['nombre']) ?>" title="Restablecer contraseña"><i class="bi bi-key"></i></button>
            </form>
            <?php if (!$esYo): ?>
            <form method="POST" class="d-inline"
                  data-confirmar="<?= e($u['estado'] === 'activo'
                      ? '¿Desactivar a ' . $u['nombre'] . '? No podrá iniciar sesión, pero sus registros se conservan.'
                      : '¿Activar de nuevo a ' . $u['nombre'] . '?') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="estado">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="estado" value="<?= $u['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
              <button type="submit" class="btn btn-sm btn-soft <?= $u['estado'] === 'activo' ? 'text-danger' : 'text-success' ?>"
                      aria-label="<?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> <?= e($u['nombre']) ?>">
                <i class="bi <?= $u['estado'] === 'activo' ? 'bi-person-x' : 'bi-person-check' ?>"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'usuarios'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>"><i class="bi <?= $accion === 'crear' ? 'bi-person-plus' : 'bi-pencil' ?>"></i>
            <?= $accion === 'crear' ? 'Nuevo usuario' : 'Editar usuario' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_nombre">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="<?= $accion ?>_nombre" class="form-control text-uppercase" required
                   minlength="3" maxlength="<?= (int)$limites['nombre'] ?>" data-filtro="persona" autocomplete="name">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_email">Correo institucional <span class="text-danger">*</span></label>
            <input type="email" name="email" id="<?= $accion ?>_email" class="form-control" required
                   maxlength="<?= (int)$limites['email'] ?>" autocomplete="off" inputmode="email">
          </div>
          <div class="row g-3 mb-3">
            <div class="<?= $accion === 'editar' ? 'col-sm-6' : 'col-12' ?>">
              <label class="form-label fw-semibold" for="<?= $accion ?>_rol">Rol <span class="text-danger">*</span></label>
              <select name="rol" id="<?= $accion ?>_rol" class="form-select" required data-picker data-picker-label="Rol">
                <?php foreach ($roles_label as $valor => $etiqueta): ?>
                  <option value="<?= e($valor) ?>" <?= $valor === 'instructor' ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($accion === 'editar'): ?>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="editar_estado">Estado</label>
              <select name="estado" id="editar_estado" class="form-select" data-picker data-picker-label="Estado">
                <?php foreach ($estados_label as $valor => [$texto]): ?>
                  <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <fieldset>
            <legend class="form-label fw-semibold fs-6">Color del avatar</legend>
            <div class="selector-colores">
              <?php foreach ($colores as $i => $c): ?>
                <label class="muestra-color" style="--color: <?= e($c) ?>">
                  <input type="radio" name="avatar_color" value="<?= e($c) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                  <span class="visually-hidden">Color <?= $i + 1 ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php if ($accion === 'crear'): ?>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-key me-1"></i>El sistema genera una contraseña temporal que verás una sola vez; la persona deberá cambiarla al entrar.</p>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
