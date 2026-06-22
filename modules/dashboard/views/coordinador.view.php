<?php
declare(strict_types=1);
?>
<!-- Encabezado del Dashboard con saludo y acciones rápidas -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
  <div>
    <h1 class="mb-1 text-gradient fw-bold">Hola, <?= $nombreUsuario ?> 👋</h1>
    <p class="text-muted mb-0">Resumen institucional y analítica de seguimiento académico regional.</p>
  </div>
  <a href="#" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario" class="btn btn-primary btn-sm w-100 w-sm-auto">
    <i class="bi bi-plus-lg me-1"></i> Nuevo Usuario
  </a>
</div>

<!-- Grid de Tarjetas KPI con Minigráficos (Sparklines) interactivos -->
<div class="row g-3 mb-4">
  <!-- Fichas Activas -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="label">Fichas Activas</div>
            <div class="value"><?= $fichasActivas ?></div>
          </div>
          <div class="icon-bg"><i class="bi bi-journal-bookmark"></i></div>
        </div>
        <div class="mt-2">
          <span class="trend up"><i class="bi bi-arrow-up-right me-1"></i>+4.2%</span>
          <span class="text-muted ms-2 small">vs mes anterior</span>
        </div>
      </div>
      <div class="sparkline-container">
        <canvas id="sparkFichas"></canvas>
      </div>
    </div>
  </div>

  <!-- Aprendices Matriculados -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="label">Aprendices</div>
            <div class="value"><?= $aprendicesMatriculados ?></div>
          </div>
          <div class="icon-bg" style="color: #3B82F6;"><i class="bi bi-people"></i></div>
        </div>
        <div class="mt-2">
          <span class="trend up" style="background: var(--info-bg); color: var(--info);"><i class="bi bi-arrow-up-right me-1"></i>+1.8%</span>
          <span class="text-muted ms-2 small">vs mes anterior</span>
        </div>
      </div>
      <div class="sparkline-container">
        <canvas id="sparkAprendices"></canvas>
      </div>
    </div>
  </div>

  <!-- Instructores Activos -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="label">Instructores</div>
            <div class="value"><?= $instructoresActivos ?></div>
          </div>
          <div class="icon-bg" style="color: #8B5CF6;"><i class="bi bi-person-workspace"></i></div>
        </div>
        <div class="mt-2">
          <span class="trend"><i class="bi bi-dash me-1"></i>0.0%</span>
          <span class="text-muted ms-2 small">vs mes anterior</span>
        </div>
      </div>
      <div class="sparkline-container">
        <canvas id="sparkInstructores"></canvas>
      </div>
    </div>
  </div>

  <!-- Promedio de Retención Académica -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="label">Retención Prom.</div>
            <div class="value"><?= $retencioPromedio ?>%</div>
          </div>
          <div class="icon-bg" style="color: #F59E0B;"><i class="bi bi-graph-up-arrow"></i></div>
        </div>
        <div class="mt-2">
          <?php $esAlto = ($retencioPromedio >= 80); ?>
          <span class="trend <?= $esAlto ? 'up' : 'down' ?>">
            <i class="bi <?= $esAlto ? 'bi-arrow-up-right' : 'bi-arrow-down-right' ?> me-1"></i>
            <?= $esAlto ? 'Estable' : 'Bajo Meta (80%)' ?>
          </span>
        </div>
      </div>
      <div class="sparkline-container">
        <canvas id="sparkRetencion"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Sección Principal: Gráficos y Analítica de Cumplimiento -->
<div class="row g-3 mb-4">
  <!-- Gráfico de Dispersión y Volumen por Programa -->
  <div class="col-lg-7">
    <div class="card h-100 shadow-sm border-0 bg-elev">
      <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-0 pt-3 px-4">
        <h5 class="mb-0 fw-semibold text-gradient">Analítica de Cumplimiento vs Volumen</h5>
        <span class="badge-soft primary">Filtro Avanzado</span>
      </div>
      <div class="card-body px-4 pb-4">
        <div style="position: relative; height: 280px;">
          <canvas id="chartProg"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Distribución de Fichas por Estado -->
  <div class="col-lg-5">
    <div class="card h-100 shadow-sm border-0 bg-elev">
      <div class="card-header bg-transparent border-0 pt-3 px-4">
        <h5 class="mb-0 fw-semibold text-gradient">Estado de Fichas</h5>
      </div>
      <div class="card-body px-4 pb-4">
        <div style="position: relative; height: 200px;">
          <canvas id="chartPie"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Paneles de Analíticas Secundarias y Top Instructores (Solo si hay datos críticos) -->
<?php if (!empty($fichasCriticas)): ?>
<div class="row g-3 mb-4">
  <!-- Tasa de Deserción por Programa -->
  <div class="col-lg-4">
    <div class="card h-100 shadow-sm border-0 glass-card">
      <div class="card-header bg-transparent border-0 pt-3 px-3">
        <h6 class="mb-0 fw-semibold text-gradient">Tasa de Deserción por Programa</h6>
      </div>
      <div class="card-body px-3 pb-3">
        <div style="position: relative; height: 220px;">
          <canvas id="chartDesercionRate"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Relación Retención vs Deserción -->
  <div class="col-lg-4">
    <div class="card h-100 shadow-sm border-0 glass-card">
      <div class="card-header bg-transparent border-0 pt-3 px-3">
        <h6 class="mb-0 fw-semibold text-gradient">Retención vs Deserción</h6>
      </div>
      <div class="card-body px-3 pb-3">
        <div style="position: relative; height: 220px;">
          <canvas id="chartRetencion"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Instructores con Excelente Nivel de Cumplimiento -->
  <div class="col-lg-4">
    <div class="card h-100 shadow-sm border-0 glass-card">
      <div class="card-header bg-transparent border-0 pt-3 px-3">
        <h6 class="mb-0 fw-semibold text-gradient"><i class="bi bi-trophy text-warning me-2"></i>Top Instructores</h6>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush bg-transparent">
          <?php foreach ($topInstructores as $inst): ?>
          <li class="list-group-item d-flex align-items-center gap-3 p-3 bg-transparent" style="border-color: rgba(0,0,0,0.05);">
            <div class="avatar" style="background: <?= htmlspecialchars($inst['avatar_color']) ?>; width: 38px; height: 38px; font-size: 1rem; border-radius: 50%; display: grid; place-items: center; color: white;">
              <?= strtoupper(substr($inst['nombre'], 0, 1)) ?>
            </div>
            <div class="flex-grow-1 min-w-0">
              <h6 class="mb-0 fw-semibold text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($inst['nombre']) ?></h6>
              <small class="text-muted"><?= $inst['fichas_asignadas'] ?> fichas asignadas</small>
            </div>
            <div class="text-end">
              <div class="fw-bold text-success" style="font-size: 0.95rem;"><?= round((float)$inst['promedio'], 1) ?>%</div>
              <small class="text-muted" style="font-size: 0.72rem;">Cumplimiento</small>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Sección Inferior: Alertas Críticas y Calendario de Eventos -->
<div class="row g-4 mb-4">
  <!-- Fichas Críticas con cumplimiento menor al 60% -->
  <div class="col-lg-8">
    <?php if (!empty($fichasCriticas)): ?>
      <div class="card h-100 border-0 shadow-sm bg-elev">
        <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3 px-4">
          <h5 class="mb-0 fw-semibold text-danger">
            <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Alertas críticas (Cumplimiento < 60%)
          </h5>
          <a href="<?= MODULES_PATH ?>/fichas/" class="small text-danger fw-semibold">Ver todas</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th class="ps-4">Ficha</th>
                  <th class="d-none d-sm-table-cell">Programa</th>
                  <th class="d-none d-md-table-cell">Instructor</th>
                  <th>Cumplimiento</th>
                  <th class="d-none d-sm-table-cell">Estado</th>
                  <th class="pe-4"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($fichasCriticas as $ficha): ?>
                <tr style="background: var(--danger-bg);">
                  <td class="ps-4"><strong>#<?= htmlspecialchars($ficha['numero_ficha']) ?></strong></td>
                  <td class="d-none d-sm-table-cell text-truncate" style="max-width: 180px;"><?= htmlspecialchars($ficha['programa']) ?></td>
                  <td class="d-none d-md-table-cell"><?= htmlspecialchars($ficha['instructor']) ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="progress-flat danger" style="width: 100px;">
                        <div style="width: <?= $ficha['cumplimiento_porcentaje'] ?>%;"></div>
                      </div>
                      <span class="text-danger fw-semibold small"><?= round((float)$ficha['cumplimiento_porcentaje'], 1) ?>%</span>
                    </div>
                  </td>
                  <td class="d-none d-sm-table-cell">
                    <span class="badge-soft danger"><?= htmlspecialchars($ficha['estado']) ?></span>
                  </td>
                  <td class="pe-4 text-end">
                    <a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-soft py-1 px-2 btn-sm">Ver</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php else: ?>
      <!-- Tarjeta premium de "Institución al Día" -->
      <div class="card border-0 h-100 shadow-sm" style="border-left: 5px solid var(--success) !important; border-radius: 12px; background: var(--success-bg);">
        <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center h-100">
          <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 54px; height: 54px; background-color: rgba(46, 139, 31, 0.1); color: var(--success); border: 2px solid rgba(46, 139, 31, 0.2);">
            <i class="bi bi-shield-fill-check" style="font-size: 1.8rem;"></i>
          </div>
          <h4 class="mb-2 fw-bold text-success">¡Institución al Día!</h4>
          <p class="text-muted mb-0" style="max-width: 420px; font-size: 0.88rem;">
            Todas las fichas de formación académica superan el umbral del 60% de cumplimiento. No se reportan alertas ni anomalías en este momento.
          </p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Próximos Eventos de Calendario -->
  <div class="col-lg-4">
    <div class="card h-100 shadow-sm border-0 bg-elev">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center bg-transparent border-0 pt-3 px-3">
        <h5 class="mb-0 fw-semibold text-gradient"><i class="bi bi-calendar3 text-primary me-2"></i>Eventos Previstos</h5>
        <a href="<?= APP_URL ?>/modules/calendario/" class="text-primary small fw-semibold" style="font-size: 0.75rem;">Ver todo</a>
      </div>
      <div class="card-body px-3 pb-3 d-flex flex-column">
        <div id="dashboard-events-list" class="flex-grow-1 overflow-y-auto" style="max-height: 280px;">
          <div class="text-center py-5 text-muted" id="events-loader">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div class="small">Sincronizando eventos...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Accesos directos inferiores de administración -->
<div class="row g-3">
  <div class="col-12 col-md-4">
    <a href="<?= MODULES_PATH ?>/fichas/" class="btn btn-soft w-100 py-3 d-flex align-items-center justify-content-center gap-2">
      <i class="bi bi-journal-bookmark" style="font-size: 1.2rem;"></i> Gestionar Fichas
    </a>
  </div>
  <div class="col-12 col-md-4">
    <a href="<?= APP_URL ?>/index.php/programas" class="btn btn-soft w-100 py-3 d-flex align-items-center justify-content-center gap-2">
      <i class="bi bi-briefcase" style="font-size: 1.2rem;"></i> Ver Programas
    </a>
  </div>
  <div class="col-12 col-md-4">
    <a href="#" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario" class="btn btn-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2">
      <i class="bi bi-person-plus" style="font-size: 1.2rem;"></i> Crear Usuario
    </a>
  </div>
</div>

<!-- Bloque de datos JSON transferido del servidor al script del cliente -->
<script id="dashboard-data" type="application/json">
{
  "appUrl": <?= json_encode(APP_URL) ?>,
  "fichasEstados": <?= json_encode([
      $fichasEstadosMap['planeacion'] ?? 0,
      $fichasEstadosMap['induccion'] ?? 0,
      $fichasEstadosMap['ejecucion'] ?? 0,
      $fichasEstadosMap['cierre'] ?? 0
  ]) ?>,
  "aprendicesEstados": <?= json_encode([
      $aprendicesEstadosMap['matriculado'] ?? 0,
      $aprendicesEstadosMap['suspendido'] ?? 0,
      $aprendicesEstadosMap['desertado'] ?? 0,
      $aprendicesEstadosMap['egresado'] ?? 0
  ]) ?>,
  "instructoresEstados": <?= json_encode([
      $instructoresEstadosMap['activo'] ?? 0,
      $instructoresEstadosMap['inactivo'] ?? 0,
      $instructoresEstadosMap['bloqueado'] ?? 0
  ]) ?>,
  "fichasCumplimientoLabels": <?= json_encode(array_map(fn($f) => "Ficha #" . $f['numero_ficha'], $fichasCumplimientoData)) ?>,
  "fichasCumplimientoData": <?= json_encode(array_map(fn($f) => round((float)$f['cumplimiento_porcentaje'], 1), $fichasCumplimientoData)) ?>,
  "programasLabels": <?= json_encode(array_map(fn($p) => strlen($p['nombre']) > 20 ? substr($p['nombre'], 0, 20) . '...' : $p['nombre'], $cumplimientoProgramas)) ?>,
  "programasPromedio": <?= json_encode(array_map(fn($p) => round((float)$p['promedio'], 1), $cumplimientoProgramas)) ?>,
  "programasVolumen": <?= json_encode(array_map(fn($p) => (int)$p['total_aprendices'], $cumplimientoProgramas)) ?>,
  "programasMin": <?= json_encode(array_map(fn($p) => round((float)$p['min_cumplimiento'], 1), $cumplimientoProgramas)) ?>,
  "programasMax": <?= json_encode(array_map(fn($p) => round((float)$p['max_cumplimiento'], 1), $cumplimientoProgramas)) ?>,
  "radarLabels": <?= json_encode(array_map(fn($p) => strlen($p['programa']) > 15 ? substr($p['programa'], 0, 15) . '...' : $p['programa'], $statsProgramas)) ?>,
  "statsProgramas": <?= json_encode($statsProgramas) ?>
}
</script>

<!-- Carga del Script del Dashboard del Cliente -->
<script src="<?= APP_URL ?>/assets/js/dashboard/coordinador.js"></script>
