<?php
declare(strict_types=1);
?>
<!-- Hero Banner de Bienvenida Premium Compacto -->
<div class="card border-0 mb-4 shadow-sm text-white overflow-hidden" style="background: linear-gradient(135deg, var(--sena-primary) 0%, #0f172a 100%); position: relative; border-radius: 12px;">
  <!-- Figuras orgánicas de fondo -->
  <div class="position-absolute" style="width: 180px; height: 180px; background: rgba(255, 255, 255, 0.04); border-radius: 50%; top: -85px; right: -40px;"></div>
  <div class="position-absolute" style="width: 120px; height: 120px; background: rgba(255, 255, 255, 0.02); border-radius: 50%; bottom: -45px; right: 90px;"></div>
  
  <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 2;">
    <div>
      <span class="badge bg-white text-success fw-bold px-2.5 py-1.5 mb-2" style="font-size: 0.65rem; border-radius: 30px; letter-spacing: 0.05em;">PORTAL DE INSTRUCTORES</span>
      <h3 class="fw-bold mb-1 text-white" style="letter-spacing: -0.01em; font-size: 1.5rem;">Buen día, <?= $nombreUsuario ?> 👋</h3>
      <p class="mb-0 text-white-50" style="max-width: 580px; font-size: 0.88rem; line-height: 1.5;">
        Monitorea el avance de tus fichas asignadas, califica evaluaciones pendientes y realiza el seguimiento a tus aprendices.
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= MODULES_PATH ?>/evaluaciones/" class="btn btn-light text-dark fw-bold px-3 py-2 btn-sm" style="border-radius: 8px; font-size: 0.82rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
        <i class="bi bi-pencil-square me-1"></i> Calificar
      </a>
      <a href="<?= MODULES_PATH ?>/mejoramiento/" class="btn btn-outline-light fw-bold px-3 py-2 btn-sm" style="border-radius: 8px; font-size: 0.82rem; border-width: 1.5px;">
        <i class="bi bi-person-exclamation me-1"></i> Planes Mejora
      </a>
    </div>
  </div>
</div>

<!-- ===== KPIs ===== -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <?php if ($kpis['evaluaciones_pendientes'] > 0): ?>
      <div class="alert-flat danger h-100">
        <i class="bi bi-clipboard-x"></i>
        <div>
          <strong><?= number_format($kpis['evaluaciones_pendientes']) ?>
            <?= $kpis['evaluaciones_pendientes'] === 1 ? 'evaluación pendiente' : 'evaluaciones pendientes' ?></strong>
          requieren tu calificación.
          <a href="<?= MODULES_PATH ?>/evaluaciones/" class="ms-2 fw-semibold d-block text-decoration-underline" style="color:inherit">Ir a evaluar →</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success h-100">
        <i class="bi bi-check2-circle"></i>
        <div><strong>Sin evaluaciones pendientes.</strong> ¡Estás al día!</div>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-4">
    <?php if ($kpis['planes_requeridos'] > 0): ?>
      <div class="alert-flat warning h-100">
        <i class="bi bi-person-exclamation"></i>
        <div>
          <strong><?= number_format($kpis['planes_requeridos']) ?>
            <?= $kpis['planes_requeridos'] === 1 ? 'aprendiz necesita' : 'aprendices necesitan' ?> plan de mejoramiento</strong>
          en tus fichas.
          <a href="<?= MODULES_PATH ?>/mejoramiento/" class="ms-2 fw-semibold d-block text-decoration-underline" style="color:inherit">Revisar →</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success h-100">
        <i class="bi bi-patch-check"></i>
        <div><strong>Ningún aprendiz</strong> requiere plan de mejoramiento.</div>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-4">
    <?php if ($kpis['aprendices_seguimiento'] > 0): ?>
      <div class="alert-flat info h-100" style="background: rgba(31, 111, 235, 0.05); border-left: 4px solid var(--info); color: var(--info);">
        <i class="bi bi-person-video3" style="color: var(--info);"></i>
        <div>
          <strong><?= number_format($kpis['aprendices_seguimiento']) ?>
            <?= $kpis['aprendices_seguimiento'] === 1 ? 'aprendiz' : 'aprendices' ?> en Etapa Práctica</strong>
          bajo tu seguimiento.
          <a href="#practica-seguimiento" class="ms-2 fw-semibold d-block text-decoration-underline" style="color:inherit">Ver listado ↓</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success h-100">
        <i class="bi bi-person-video3"></i>
        <div><strong>0 aprendices</strong> asignados en Etapa Práctica.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== Fichas asignadas ===== -->
<h4 class="mt-4 mb-3 fw-bold text-gradient"><i class="bi bi-folder2-open me-2 text-primary"></i>Mis fichas asignadas</h4>

<?php if (empty($fichasInstructor)): ?>
  <div class="card border-0 bg-elev shadow-sm" style="border-radius:12px;">
    <div class="card-body text-center text-muted py-4">
      <i class="bi bi-inbox d-block mb-2" style="font-size:2rem"></i>
      No tienes fichas asignadas todavía. Cuando un coordinador te asigne una, aparecerá aquí.
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($fichasInstructor as $ficha): ?>
      <div class="col-md-6 col-xl-3">
        <div class="card h-100 hover-scale-sm border-0 shadow-sm bg-elev" style="border-radius:12px; transition: transform 0.2s ease-in-out;">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between mb-2">
                <span class="badge-soft <?= $ficha['badge'] ?>"><?= htmlspecialchars($ficha['estado']) ?></span>
                <small class="text-muted">#<?= htmlspecialchars($ficha['numero']) ?></small>
              </div>
              <h5 class="mb-1 fw-bold text-truncate" title="<?= htmlspecialchars($ficha['programa']) ?>" style="font-size: 0.95rem;"><?= htmlspecialchars($ficha['programa']) ?></h5>
              <small class="text-muted d-block mb-3">
                <i class="bi bi-people me-1"></i><?= $ficha['aprendices'] ?> aprendices
              </small>
            </div>
            <div>
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Cumplimiento</span><strong><?= $ficha['cumplimiento'] ?>%</strong>
              </div>
              <div class="progress-flat <?= in_array($ficha['badge'], ['danger','warning']) ? $ficha['badge'] : '' ?>">
                <div style="width:<?= $ficha['cumplimiento'] ?>%"></div>
              </div>
              <a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-soft w-100 mt-3 btn-sm">Ver detalle</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ===== Sección de Analítica ===== -->
<div class="row g-3 mt-3 mb-4">
  <!-- Gráfico de Cumplimiento por Ficha -->
  <div class="col-md-8">
    <div class="card h-100 shadow-sm border-0 bg-elev" style="border-radius:12px;">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-0 text-gradient"><i class="bi bi-bar-chart-line text-primary me-2"></i>Avance de Cumplimiento por Ficha</h5>
        <small class="text-muted">Progreso integralizado de resultados de aprendizaje evaluados con 'A' por cada una de tus fichas.</small>
      </div>
      <div class="card-body p-4">
        <div style="height: 280px; position: relative;">
          <canvas id="chartFichasCumplimiento"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráfico de Distribución de Juicios -->
  <div class="col-md-4">
    <div class="card h-100 shadow-sm border-0 bg-elev" style="border-radius:12px;">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-0 text-gradient"><i class="bi bi-pie-chart text-success me-2"></i>Distribución de Evaluaciones</h5>
        <small class="text-muted">Estado actual de todos los juicios de tu cohorte.</small>
      </div>
      <div class="card-body p-4 d-flex align-items-center justify-content-center">
        <div style="width: 100%; max-width: 240px; height: 240px; position: relative;">
          <canvas id="chartConceptosDistribucion"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Aprendices con concepto D ===== -->
<div class="row g-4 mt-4 mb-4">
  <div class="col-lg-8">
    <div class="card h-100 border-0 shadow-sm bg-elev" style="border-radius: 12px;">
      <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-0 pt-3 px-4">
        <h5 class="mb-0 fw-semibold text-gradient"><i class="bi bi-person-exclamation text-warning me-2"></i>Requieren Plan de Mejoramiento (Concepto D)</h5>
        <?php if (count($pendientesPlanes) === 10): ?>
          <a href="<?= MODULES_PATH ?>/mejoramiento/" class="small text-muted">Ver todos →</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead>
              <tr>
                <th class="ps-4">Aprendiz</th>
                <th>Ficha</th>
                <th>RAP</th>
                <th>Fecha D</th>
                <th class="pe-4 text-end"></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pendientesPlanes)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-patch-check-fill text-success d-block mb-2" style="font-size:2rem"></i>
                    No hay aprendices con concepto D pendiente.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($pendientesPlanes as $p): ?>
                  <tr>
                    <td class="ps-4">
                      <div class="d-flex align-items-center gap-2">
                        <div class="avatar" style="width:32px;height:32px;font-size:.75rem">
                          <?= getInitials($p['aprendiz']) ?>
                        </div>
                        <span class="fw-bold"><?= htmlspecialchars($p['aprendiz']) ?></span>
                      </div>
                    </td>
                    <td>#<?= htmlspecialchars($p['ficha']) ?></td>
                    <td>
                      <span class="badge-soft primary" title="<?= htmlspecialchars($p['ra_nombre']) ?>">
                        <?= htmlspecialchars($p['ra_codigo']) ?>
                      </span>
                    </td>
                    <td class="text-muted small"><?= !empty($p['fecha']) ? date('d/m/Y', strtotime($p['fecha'])) : '—' ?></td>
                    <td class="pe-4 text-end">
                      <a href="<?= MODULES_PATH ?>/mejoramiento/" class="btn btn-sm btn-primary py-1 px-2.5" style="border-radius: 6px;">
                        <i class="bi bi-arrow-right"></i> Atender
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Próximos Eventos -->
  <div class="col-lg-4">
    <div class="card h-100 border-0 shadow-sm bg-elev" style="border-radius: 12px;">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center bg-transparent border-0 pt-3 px-3">
        <h5 class="mb-0 fw-semibold text-gradient"><i class="bi bi-calendar3 text-primary me-2"></i>Eventos Previstos</h5>
        <a href="<?= APP_URL ?>/modules/calendario/" class="text-primary small fw-semibold" style="font-size: 0.75rem;"><i class="bi bi-calendar3 me-1"></i>Ver todo</a>
      </div>
      <div class="card-body px-3 pb-3 d-flex flex-column">
        <div class="flex-grow-1 overflow-y-auto" id="dashboard-events-list" style="max-height: 380px;">
          <div class="text-center py-4 text-muted" id="events-loader">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div class="small">Sincronizando eventos...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Aprendices en Etapa Práctica (Seguimiento) ===== -->
<div class="card border-0 shadow-sm bg-elev mb-4" id="practica-seguimiento" style="border-radius: 12px;">
  <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
    <h5 class="fw-bold text-gradient mb-0"><i class="bi bi-person-video3 text-primary me-2"></i>Mis Aprendices en Etapa Práctica (Seguimiento)</h5>
    <small class="text-muted">Listado de aprendices asignados para seguimiento de etapa productiva.</small>
  </div>
  <div class="card-body p-0 mt-3">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead>
          <tr>
            <th class="ps-4">Aprendiz</th>
            <th>Ficha</th>
            <th>Programa</th>
            <th>Teléfono / Ciudad</th>
            <th class="pe-4 text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($aprendicesSeguimientoLista)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-5">
                <i class="bi bi-person-badge-fill text-muted d-block mb-2" style="font-size:2rem; opacity:0.3;"></i>
                No tienes aprendices en etapa práctica asignados.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($aprendicesSeguimientoLista as $ap_seg): ?>
              <tr>
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar bg-soft-primary text-primary" style="width:32px;height:32px;font-size:.75rem">
                      <?= getInitials($ap_seg['nombre']) ?>
                    </div>
                    <strong><?= htmlspecialchars($ap_seg['nombre']) ?></strong>
                  </div>
                </td>
                <td>#<?= htmlspecialchars($ap_seg['numero_ficha']) ?></td>
                <td><small class="text-muted"><?= htmlspecialchars($ap_seg['programa']) ?></small></td>
                <td>
                  <div><?= htmlspecialchars($ap_seg['telefono'] ?: '—') ?></div>
                  <small class="text-muted"><?= htmlspecialchars($ap_seg['ciudad'] ?: '—') ?></small>
                </td>
                <td class="pe-4 text-end">
                  <a href="<?= MODULES_PATH ?>/seguimiento/index.php?ficha_id=<?= $ap_seg['ficha_id'] ?>&ver_aprendiz_id=<?= $ap_seg['id'] ?>" class="btn btn-sm btn-soft">
                    <i class="bi bi-chat-dots me-1"></i> Seguimiento
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bloque de datos JSON transferido del servidor al script del cliente -->
<script id="dashboard-data" type="application/json">
{
  "appUrl": <?= json_encode(APP_URL) ?>,
  "fichasLabels": <?= json_encode(array_map(fn($f) => '#' . $f['numero'], $fichasInstructor)) ?>,
  "fichasCumplimiento": <?= json_encode(array_column($fichasInstructor, 'cumplimiento')) ?>,
  "countA": <?= (int)$evalConceptos['A'] ?>,
  "countD": <?= (int)$evalConceptos['D'] ?>,
  "countPendiente": <?= (int)$evalConceptos['pendiente'] ?>
}
</script>

<!-- Carga del Script del Dashboard del Cliente -->
<script src="<?= APP_URL ?>/assets/js/dashboard/instructor.js?v=<?= filemtime(BASE_PATH . 'assets/js/dashboard/instructor.js') ?>"></script>
