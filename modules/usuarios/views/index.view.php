<div class="d-flex justify-content-between align-items-center mb-3">
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

<div class="toolbar mb-3">
  <div class="search"><i class="bi bi-search"></i><input class="form-control" id="searchUsers" placeholder="Buscar por nombre o email..."></div>
</div>

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
      <?php foreach ($usuarios as $usuario): ?>
      <tr>
        <td><strong><?= htmlspecialchars($usuario['nombre']) ?></strong></td>
        <td><?= htmlspecialchars($usuario['email']) ?></td>
        <td><span class="badge-soft primary"><?= htmlspecialchars($roles_label[$usuario['rol']] ?? $usuario['rol']) ?></span></td>
        <td><span class="badge-soft <?= htmlspecialchars($estados_label[$usuario['estado']][1] ?? 'secondary') ?>"><?= htmlspecialchars($estados_label[$usuario['estado']][0] ?? $usuario['estado']) ?></span></td>
        <td><?= date('d/m/Y', strtotime($usuario['fecha_creacion'])) ?></td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-soft" onclick="openEditUserModal(<?= $usuario['id'] ?>)">Editar</button>
          <button type="button" class="btn btn-sm btn-soft text-danger" onclick="deleteUser(<?= $usuario['id'] ?>)">Eliminar</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form id="deleteForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<!-- Incluir el script de JS específico para usuarios -->
<script src="<?= ASSETS_PATH ?>/js/modules/usuarios.js"></script>
