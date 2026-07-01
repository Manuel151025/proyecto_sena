<?php
declare(strict_types=1);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1><?= $role === ROL_INSTRUCTOR ? 'Mis fichas asignadas' : 'Directorio de Fichas' ?></h1>
    <p class="text-muted mb-0">Gestiona y haz seguimiento a las fichas de formación.</p>
  </div>
  <?php if($role === ROL_COORDINADOR): ?>
  <div class="d-flex gap-2">
    <a href="<?= MODULES_PATH ?>/fichas/crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva Ficha</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($mensaje): ?>
<div class="alert-flat <?= $tipo_mensaje ?> mb-4">
  <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
</div>
<?php endif; ?>

<div class="toolbar mb-4" style="background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); padding: 1rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
  <div class="search" style="max-width: 360px; flex-grow: 1;"><i class="bi bi-search"></i><input class="form-control border-0 bg-transparent" id="searchFichas" placeholder="Buscar ficha o programa..."></div>
  <select class="form-select border-0 bg-light" id="filterEstado" style="max-width:180px">
    <option value="">Todos los estados</option>
    <option value="planeacion">Planeación</option>
    <option value="induccion">Inducción</option>
    <option value="ejecucion">Ejecución</option>
    <option value="cierre">Cierre</option>
  </select>
  <select class="form-select border-0 bg-light" id="filterPrograma" style="max-width:200px">
    <option value="">Todos los programas</option>
    <?php foreach ($programas as $prog): ?>
    <option value="<?= htmlspecialchars($prog['codigo']) ?>"><?= htmlspecialchars($prog['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="row g-4" id="fichasGrid">
  <?php foreach ($fichas as $ficha): ?>
  <div class="col-12 col-md-6 col-xl-4 ficha-card" data-estado="<?= $ficha['estado'] ?>" data-programa="<?= htmlspecialchars($ficha['codigo_programa']) ?>">
    <div class="card h-100 glass-card" style="transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid var(--sena-primary); border-radius: 12px; overflow: hidden;" onmouseover="this.style.transform='translateY(-5px)';this.style.boxShadow='0 12px 30px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.05)';">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="badge-soft <?= $estados_label[$ficha['estado']][1] ?> px-3 py-2 rounded-pill fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
            <?= strtoupper($estados_label[$ficha['estado']][0]) ?>
          </span>
          <span class="text-muted small fw-bold" style="font-size: 0.9rem; letter-spacing: 1px;">#<?= htmlspecialchars($ficha['numero_ficha']) ?></span>
        </div>
        
        <h4 class="card-title fw-bold mb-3 text-truncate" title="<?= htmlspecialchars($ficha['programa']) ?>" style="font-size: 1.15rem; color: #1f2937;">
          <?= htmlspecialchars($ficha['programa']) ?>
        </h4>
        
        <div class="mb-4">
          <?php if($role === ROL_COORDINADOR): ?>
          <div class="d-flex align-items-center mb-2">
            <div class="icon-bg bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;font-size:0.8rem;">
              <i class="bi bi-person-video3"></i>
            </div>
            <span class="text-muted small fw-medium text-truncate"><?= htmlspecialchars($ficha['instructor']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($ficha['proyecto_nombre'])): ?>
          <div class="d-flex align-items-center mb-2">
            <div class="icon-bg bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;font-size:0.8rem;">
              <i class="bi bi-kanban"></i>
            </div>
            <span class="text-muted small fw-medium text-truncate" title="<?= htmlspecialchars($ficha['proyecto_nombre']) ?>">
              <?= htmlspecialchars($ficha['proyecto_codigo'] . ' — ' . substr($ficha['proyecto_nombre'], 0, 28) . (strlen($ficha['proyecto_nombre']) > 28 ? '…' : '')) ?>
            </span>
          </div>
          <?php else: ?>
          <div class="d-flex align-items-center mb-2">
            <div class="icon-bg bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;font-size:0.8rem;">
              <i class="bi bi-kanban"></i>
            </div>
            <span class="text-muted small fst-italic">Sin proyecto asignado</span>
          </div>
          <?php endif; ?>
          <div class="d-flex align-items-center">
            <div class="icon-bg bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width:28px;height:28px;font-size:0.8rem;">
              <i class="bi bi-calendar-event"></i>
            </div>
            <span class="text-muted small fw-medium">Fin: <?= $ficha['fecha_fin'] ? date('d/m/Y', strtotime($ficha['fecha_fin'])) : 'N/A' ?></span>
          </div>
        </div>

        <div class="d-flex align-items-center gap-4 p-3 rounded-3" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.04);">
          <div class="d-flex align-items-center gap-2">
            <div class="text-primary fs-4"><i class="bi bi-people-fill"></i></div>
            <div>
              <div class="fw-bold fs-5 lh-1 text-dark"><?= $ficha['cantidad_aprendices'] ?></div>
              <div class="text-muted" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px;">Aprendices</div>
            </div>
          </div>
          
          <div class="flex-grow-1 border-start ps-4">
            <div class="d-flex justify-content-between align-items-end mb-1">
              <span class="text-muted" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px;">Cumplimiento</span>
              <span class="fw-bold text-dark" style="font-size:0.9rem;"><?= (int)$ficha['cumplimiento_porcentaje'] ?>%</span>
            </div>
            <div class="progress" style="height:6px; background:rgba(0,0,0,0.06); border-radius: 10px;">
              <div class="progress-bar" style="width: <?= $ficha['cumplimiento_porcentaje'] ?>%; background: <?= $ficha['cumplimiento_porcentaje'] >= 75 ? 'var(--sena-primary)' : ($ficha['cumplimiento_porcentaje'] >= 50 ? '#eab308' : '#ef4444') ?>; border-radius: 10px;"></div>
            </div>
          </div>
        </div>
        
      </div>
      <div class="card-footer bg-transparent border-0 px-4 pb-4 pt-0">
        <div class="d-flex gap-2">
          <a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-primary flex-grow-1 fw-medium" style="border-radius: 8px;">Ir al Panel</a>
          <?php if($role === ROL_COORDINADOR): ?>
          <a href="<?= MODULES_PATH ?>/fichas/editar.php?id=<?= $ficha['id'] ?>" class="btn btn-soft text-primary px-3" style="border-radius: 8px;" title="Editar"><i class="bi bi-pencil-square"></i></a>
          <button type="button" class="btn btn-soft text-danger px-3" style="border-radius: 8px;" onclick="deleteSheet(<?= $ficha['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($fichas)): ?>
  <div class="col-12 text-center py-5 text-muted">
    <div style="font-size: 4rem; color: #d1d5db; margin-bottom: 1rem;"><i class="bi bi-folder-x"></i></div>
    <h4 class="fw-bold text-secondary">No hay fichas disponibles</h4>
    <p>Aún no tienes fichas de formación asignadas o creadas.</p>
  </div>
  <?php endif; ?>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<!-- Cargar Script del Cliente Desacoplado -->
<script src="<?= APP_URL ?>/assets/js/fichas/fichas.js"></script>
