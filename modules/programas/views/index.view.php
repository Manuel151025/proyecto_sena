<?php
declare(strict_types=1);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Programas de Formación</h1>
    <p class="text-muted mb-0">Administra todos los programas de formación disponibles.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR)): ?>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/index.php/programas/crear" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo Programa</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-flat <?= $tipo_mensaje ?> mb-3 alert-dismissible fade show" role="alert">
  <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="toolbar mb-3">
  <div class="search"><i class="bi bi-search"></i><input class="form-control" id="searchPrograms" placeholder="Buscar programa..."></div>
  <select class="form-select" style="max-width:180px" id="filterStatus">
    <option value="">Todos los estados</option>
    <option value="activo">Activo</option>
    <option value="inactivo">Inactivo</option>
    <option value="archivado">Archivado</option>
  </select>
</div>

<div class="row g-3">
  <?php foreach ($programas as $programa): ?>
  <div class="col-lg-6" data-status="<?= htmlspecialchars($programa['estado']) ?>" data-name="<?= htmlspecialchars($programa['nombre']) ?>">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5><?= htmlspecialchars($programa['nombre']) ?></h5>
            <p class="text-muted mb-2"><code><?= htmlspecialchars($programa['codigo']) ?></code></p>
            <p style="font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem;">
              <?= htmlspecialchars(substr($programa['descripcion'] ?? '', 0, 100)) ?><?= strlen($programa['descripcion'] ?? '') > 100 ? '...' : '' ?>
            </p>
            <div style="display: flex; gap: 1rem; font-size: 0.85rem;">
              <span><strong><?= $programa['duracion_horas'] ?? 0 ?></strong> horas</span>
              <span class="badge-soft <?= $estados_label[$programa['estado']][1] ?>"><?= $estados_label[$programa['estado']][0] ?></span>
            </div>
          </div>
          <?php if (hasRole(ROL_COORDINADOR)): ?>
          <div class="d-flex gap-1">
            <a href="<?= APP_URL ?>/index.php/programas/editar?id=<?= $programa['id'] ?>" class="btn btn-sm btn-soft"><i class="bi bi-pencil"></i></a>
            <button type="button" class="btn btn-sm btn-soft text-danger" onclick="deleteProgram(<?= $programa['id'] ?>)"><i class="bi bi-trash"></i></button>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($programas)): ?>
  <div class="col-12 text-center py-5 text-muted">
    <i class="bi bi-book d-block mb-2" style="font-size: 3rem; opacity: 0.3;"></i>
    No hay programas registrados en el sistema.
  </div>
  <?php endif; ?>
</div>

<form id="deleteForm" method="POST" style="display:none;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<script>
function deleteProgram(id) {
  if (confirm('¿Estás seguro de que deseas eliminar este programa?')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}

// Búsqueda
document.getElementById('searchPrograms').addEventListener('keyup', function(e) {
  filterPrograms();
});

document.getElementById('filterStatus').addEventListener('change', function(e) {
  filterPrograms();
});

function filterPrograms() {
  const searchFilter = document.getElementById('searchPrograms').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value;
  const cards = document.querySelectorAll('[data-status]');

  cards.forEach(card => {
    const name = card.dataset.name.toLowerCase();
    const status = card.dataset.status;
    
    const matchSearch = name.includes(searchFilter);
    const matchStatus = !statusFilter || status === statusFilter;
    
    card.style.display = (matchSearch && matchStatus) ? '' : 'none';
  });
}
</script>
