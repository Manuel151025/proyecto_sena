<div class="page-header">
  <div>
    <h1>Gestión de Usuarios</h1>
    <p class="text-muted mb-0">Administra usuarios del sistema (Coordinadores, Instructores y Aprendices).</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/index.php/usuarios/importar" class="btn btn-soft"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar CSV</a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo Usuario</a>
  </div>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= htmlspecialchars($tipo_mensaje) ?> mb-3">
  <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
</div>
<?php endif; ?>

<!-- La búsqueda y los filtros se resuelven en el servidor (ver
     UsuarioModel::getFilteredList): el listado está paginado, así que
     filtrar en el cliente solo alcanzaría a las filas de esta página. -->
<form method="GET" class="toolbar mb-3">
  <div class="search">
    <i class="bi bi-search"></i>
    <label class="visually-hidden" for="searchUsers">Buscar usuario</label>
    <input type="text" name="search" id="searchUsers" class="form-control"
           placeholder="Buscar por nombre o email..."
           value="<?= htmlspecialchars($filtros['search'] ?? '') ?>">
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="filtroRol">Rol</label>
    <select name="rol" id="filtroRol" class="form-select" onchange="this.form.submit()"
            data-picker data-picker-label="Rol" data-picker-placeholder="Todos los roles">
      <option value="">Todos los roles</option>
      <?php foreach ($roles_label as $valor => $etiqueta): ?>
        <option value="<?= htmlspecialchars($valor) ?>" <?= ($filtros['rol'] ?? '') === $valor ? 'selected' : '' ?>>
          <?= htmlspecialchars($etiqueta) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="filtroEstado">Estado</label>
    <select name="estado" id="filtroEstado" class="form-select" onchange="this.form.submit()"
            data-picker data-picker-label="Estado" data-picker-placeholder="Todos los estados">
      <option value="">Todos los estados</option>
      <option value="activo" <?= ($filtros['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
      <option value="inactivo" <?= ($filtros['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
    </select>
  </div>
  <button type="submit" class="btn btn-soft"><i class="bi bi-funnel me-1"></i>Filtrar</button>
  <?php if (($filtros['search'] ?? '') !== '' || ($filtros['rol'] ?? '') !== '' || ($filtros['estado'] ?? '') !== ''): ?>
    <a href="<?= APP_URL ?>/index.php/usuarios" class="btn btn-soft text-muted">Limpiar</a>
  <?php endif; ?>
</form>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Email</th>
        <th>Rol</th>
        <th>Estado</th>
        <th>Fecha de Creación</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($usuarios)): ?>
      <tr>
        <td colspan="6" class="text-center text-muted py-5">
          <i class="bi bi-search d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
          No hay usuarios que coincidan con la búsqueda.
        </td>
      </tr>
      <?php endif; ?>
      <?php foreach ($usuarios as $usuario): ?>
      <tr>
        <td><strong><?= htmlspecialchars($usuario['nombre']) ?></strong></td>
        <td><?= htmlspecialchars($usuario['email']) ?></td>
        <td><span class="badge-soft primary"><?= htmlspecialchars($roles_label[$usuario['rol']] ?? $usuario['rol']) ?></span></td>
        <td><span class="badge-soft <?= htmlspecialchars($estados_label[$usuario['estado']][1] ?? 'secondary') ?>"><?= htmlspecialchars($estados_label[$usuario['estado']][0] ?? $usuario['estado']) ?></span></td>
        <td><?= date('d/m/Y', strtotime($usuario['fecha_creacion'])) ?></td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-soft" onclick="openEditUserModal(<?= $usuario['id'] ?>)">Editar</button>
          <button type="button" class="btn btn-sm btn-soft text-danger" onclick="deleteUser(<?= $usuario['id'] ?>)">Desactivar</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'usuarios'; require BASE_PATH . 'components/paginacion.php'; ?>

<form id="deleteForm" method="POST" style="display:none;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<!-- Incluir el script de JS específico para usuarios -->
<script src="<?= ASSETS_PATH ?>/js/modules/usuarios.js"></script>
