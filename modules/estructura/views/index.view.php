<?php
declare(strict_types=1);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Estructura Curricular y Proyectos</h1>
    <p class="text-muted mb-0">Gestión global de programas de formación, competencias, resultados de aprendizaje y proyectos formativos.</p>
  </div>
  <div>
    <a href="<?= APP_URL ?>/index.php/estructura/importar" class="btn btn-primary">
      <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar PDF
    </a>
  </div>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-flat <?= $tipo_mensaje ?> mb-4 alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-circle-fill me-2"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Tarjetas KPI Estadísticas -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <a href="<?= APP_URL ?>/index.php/programas" class="text-decoration-none d-block">
      <div class="kpi">
        <div class="kpi-content">
          <div class="icon-bg"><i class="bi bi-book"></i></div>
          <div class="label">Programas</div>
          <div class="value"><?= $numProgramas ?> <i class="bi bi-arrow-right-short text-muted fs-4"></i></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?= APP_URL ?>/index.php/competencias" class="text-decoration-none d-block">
      <div class="kpi">
        <div class="kpi-content">
          <div class="icon-bg"><i class="bi bi-diagram-3"></i></div>
          <div class="label">Competencias</div>
          <div class="value"><?= $numCompetencias ?> <i class="bi bi-arrow-right-short text-muted fs-4"></i></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?= APP_URL ?>/index.php/resultados-aprendizaje" class="text-decoration-none d-block">
      <div class="kpi">
        <div class="kpi-content">
          <div class="icon-bg"><i class="bi bi-clipboard-check"></i></div>
          <div class="label">Resultados (RA)</div>
          <div class="value"><?= $numResultados ?> <i class="bi bi-arrow-right-short text-muted fs-4"></i></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="icon-bg"><i class="bi bi-kanban"></i></div>
        <div class="label">Proyectos</div>
        <div class="value"><?= $numProyectos ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Programas de Formación Activos -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-collection-play me-2 text-primary"></i>Programas Cargados</span>
        <span class="badge bg-soft primary"><?= count($programas) ?> Registrados</span>
      </div>
      <div class="card-body p-0">
        <div class="table-wrap border-0 rounded-0">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Código / Nombre</th>
                <th class="text-center">Competencias</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($programas as $p): ?>
              <tr>
                <td>
                  <div class="fw-bold text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($p['nombre']) ?>"><?= htmlspecialchars($p['nombre']) ?></div>
                  <small class="text-muted">Código: <code><?= htmlspecialchars($p['codigo']) ?></code> · <?= $p['duracion_horas'] ?> hs</small>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-pill"><?= $p['total_competencias'] ?></span>
                </td>
                <td>
                  <span class="badge-soft <?= $p['estado'] === 'activo' ? 'success' : 'secondary' ?>">
                    <?= ucfirst($p['estado']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <div class="d-flex justify-content-end gap-1">
                    <a href="<?= APP_URL ?>/index.php/estructura/editar_programa?id=<?= $p['id'] ?>" class="btn btn-soft text-primary p-1 px-2 btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-soft text-danger p-1 px-2 btn-sm" onclick="eliminarPrograma(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>')" title="Eliminar"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($programas)): ?>
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">No hay programas registrados.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Proyectos Formativos Activos -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-kanban me-2 text-primary"></i>Proyectos Formativos</span>
        <span class="badge bg-soft primary"><?= count($proyectos) ?> Registrados</span>
      </div>
      <div class="card-body p-0">
        <div class="table-wrap border-0 rounded-0">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Código / Nombre</th>
                <th class="text-center">Fases</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($proyectos as $pj): ?>
              <tr>
                <td>
                  <div class="fw-bold text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($pj['nombre']) ?>"><?= htmlspecialchars($pj['nombre']) ?></div>
                  <small class="text-muted">Código: <code><?= htmlspecialchars($pj['codigo']) ?></code></small>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-pill"><?= $pj['total_fases'] ?></span>
                </td>
                <td>
                  <span class="badge-soft <?= $pj['estado'] === 'activo' ? 'success' : ($pj['estado'] === 'finalizado' ? 'primary' : 'secondary') ?>">
                    <?= ucfirst($pj['estado']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <div class="d-flex justify-content-end gap-1">
                    <a href="<?= APP_URL ?>/index.php/estructura/editar_proyecto?id=<?= $pj['id'] ?>" class="btn btn-soft text-primary p-1 px-2 btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-soft text-danger p-1 px-2 btn-sm" onclick="eliminarProyecto(<?= $pj['id'] ?>, '<?= htmlspecialchars(addslashes($pj['nombre'])) ?>')" title="Eliminar"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($proyectos)): ?>
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">No hay proyectos formativos registrados.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<form id="formEliminar" method="POST" style="display:none;">
  <?= csrfField() ?>
  <input type="hidden" name="action" id="actionEliminar">
  <input type="hidden" name="id" id="idEliminar">
</form>

<script>
function eliminarPrograma(id, nombre) {
  if (confirm('¿Estás seguro de que deseas eliminar el programa "' + nombre + '"? Se eliminarán todas sus competencias y resultados de aprendizaje. Esta acción no se puede deshacer.')) {
    document.getElementById('actionEliminar').value = 'eliminar_programa';
    document.getElementById('idEliminar').value = id;
    document.getElementById('formEliminar').submit();
  }
}

function eliminarProyecto(id, nombre) {
  if (confirm('¿Estás seguro de que deseas eliminar el proyecto "' + nombre + '"? Se eliminarán todas sus fases. Esta acción no se puede deshacer.')) {
    document.getElementById('actionEliminar').value = 'eliminar_proyecto';
    document.getElementById('idEliminar').value = id;
    document.getElementById('formEliminar').submit();
  }
}
</script>
