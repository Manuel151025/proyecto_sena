<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <?php if (getCurrentRole() !== ROL_APRENDIZ): ?>
      <a href="<?= MODULES_PATH ?>/fichas/" class="small"><i class="bi bi-arrow-left"></i> Volver a fichas</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/index.php/dashboard" class="small"><i class="bi bi-arrow-left"></i> Volver al Inicio</a>
    <?php endif; ?>
    <h1 class="mt-2 mb-1">
      Ficha #<?= htmlspecialchars($ficha['numero_ficha']) ?> 
      <span class="badge-soft <?= $estados_label[$ficha['estado']][1] ?> ms-2"><?= $estados_label[$ficha['estado']][0] ?></span>
    </h1>
    <p class="text-muted mb-0">
      <?= htmlspecialchars($ficha['programa']) ?> Â· 
      Instructor LÃ­der: <?= htmlspecialchars($ficha['instructor']) ?> Â· 
      <?= $ficha['cantidad_aprendices'] ?> aprendices
    </p>
  </div>
  <div class="d-flex gap-2">
    <?php if (getCurrentRole() === ROL_COORDINADOR || getCurrentRole() === ROL_INSTRUCTOR): ?>
      <a href="<?= MODULES_PATH ?>/evaluaciones/index.php?ficha_id=<?= $id ?>" class="btn btn-success">
        <i class="bi bi-clipboard-check me-1"></i>Evaluar Ficha (Juicios)
      </a>
    <?php endif; ?>
    <?php if (getCurrentRole() === ROL_COORDINADOR): ?>
      <a href="<?= MODULES_PATH ?>/fichas/editar.php?id=<?= $id ?>" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i>Editar ficha
      </a>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tInfo">
      <i class="bi bi-info-circle me-1"></i>InformaciÃ³n general
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tApr">
      <i class="bi bi-people me-1"></i>Aprendices matriculados (<?= count($aprendices) ?>)
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tInfo">
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <small class="text-muted">Programa</small>
            <div class="fw-semibold"><?= htmlspecialchars($ficha['programa']) ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Instructor LÃ­der</small>
            <div class="fw-semibold"><?= htmlspecialchars($ficha['instructor']) ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Estado</small>
            <div class="fw-semibold"><span class="badge-soft <?= $estados_label[$ficha['estado']][1] ?>"><?= $estados_label[$ficha['estado']][0] ?></span></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Fecha de inicio</small>
            <div class="fw-semibold"><?= $ficha['fecha_inicio'] ? date('d/m/Y', strtotime($ficha['fecha_inicio'])) : 'N/A' ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Fecha de fin</small>
            <div class="fw-semibold"><?= $ficha['fecha_fin'] ? date('d/m/Y', strtotime($ficha['fecha_fin'])) : 'N/A' ?></div>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Cumplimiento</small>
            <div class="fw-semibold">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 100px; height: 24px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                  <div style="height: 100%; width: <?= $ficha['cumplimiento_porcentaje'] ?>%; background: <?= $ficha['cumplimiento_porcentaje'] >= 75 ? '#22c55e' : ($ficha['cumplimiento_porcentaje'] >= 50 ? '#eab308' : '#ef4444') ?>; transition: width 0.3s;"></div>
                </div>
                <span><?= (int)$ficha['cumplimiento_porcentaje'] ?>%</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tApr">
    <div class="toolbar mb-3">
      <div class="search"><i class="bi bi-search"></i><input class="form-control" id="searchAprendices" placeholder="Buscar aprendiz..."></div>
    </div>
    <?php if (count($aprendices) > 0): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Aprendiz</th>
            <th>Tipo de documento</th>
            <th>Estado</th>
            <th>Instructor de Seguimiento</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendices as $aprendiz): ?>
          <tr class="aprendiz-row" data-search="<?= htmlspecialchars(strtolower($aprendiz['nombre'] . ' ' . $aprendiz['numero_documento'])) ?>">
            <td><code><?= htmlspecialchars($aprendiz['numero_documento']) ?></code></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar" style="width:30px;height:30px;font-size:.72rem;background:<?= htmlspecialchars($aprendiz['avatar_color']) ?>">
                  <?= htmlspecialchars(substr($aprendiz['nombre'], 0, 2)) ?>
                </div>
                <?= htmlspecialchars($aprendiz['nombre']) ?>
              </div>
            </td>
            <td><?= htmlspecialchars($aprendiz['tipo_documento']) ?></td>
            <td>
              <span class="badge-soft <?= $estados_aprendiz[$aprendiz['estado']][1] ?? 'secondary' ?>">
                <?= $estados_aprendiz[$aprendiz['estado']][0] ?? 'N/A' ?>
              </span>
            </td>
            <td>
              <?php if ($aprendiz['instructor_seguimiento_nombre']): ?>
                <span class="small text-muted"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($aprendiz['instructor_seguimiento_nombre']) ?></span>
              <?php else: ?>
                <span class="small text-muted italic">â€”</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if (getCurrentRole() !== ROL_APRENDIZ): ?>
              <a href="<?= MODULES_PATH ?>/seguimiento/index.php?ficha_id=<?= $id ?>&ver_aprendiz_id=<?= $aprendiz['id'] ?>" class="btn btn-sm btn-soft">Ver</a>
              <?php else: ?>
              <span class="text-muted">â€”</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script>
    document.getElementById('searchAprendices').addEventListener('keyup', function(e) {
      const filter = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('.aprendiz-row');
      rows.forEach(row => {
        const text = row.dataset.search;
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    });
    </script>
    <?php else: ?>
    <div class="alert-flat info">
      <i class="bi bi-info-circle"></i>
      <div>No hay aprendices matriculados en esta ficha aÃºn.</div>
    </div>
    <?php endif; ?>
