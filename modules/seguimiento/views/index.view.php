<div class="mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="mb-1 text-dark fw-bold"><i class="bi bi-graph-up text-primary me-2"></i>Seguimiento AcadÃ©mico</h1>
      <p class="text-muted mb-0">Control del avance formativo, cumplimiento de resultados de aprendizaje y nivelaciÃ³n de competencias.</p>
    </div>

    <?php if ($user_rol !== ROL_APRENDIZ && !empty($fichas)): ?>
      <form method="GET" class="d-flex align-items-center gap-2">
        <label class="text-muted small fw-semibold text-nowrap d-none d-sm-inline">Ficha:</label>
        <select name="ficha_id" class="form-select bg-white border border-light-subtle shadow-sm"
                onchange="this.form.submit()" style="min-width: 250px;">
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $f['id'] == $selected_ficha_id ? 'selected' : '' ?>>
              #<?= htmlspecialchars($f['numero_ficha']) ?> â€” <?= htmlspecialchars(substr($f['programa'], 0, 30)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($successMessage)): ?>
  <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-check-circle-fill me-2" style="font-size:1.2rem;"></i>
      <div><?= htmlspecialchars($successMessage) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-exclamation-triangle-fill me-2" style="font-size:1.2rem;"></i>
      <div>
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- VISTA APRENDIZ -->
<!-- ======================================================= -->
<?php if ($user_rol === ROL_APRENDIZ): ?>
  <?php if (!$mi_perfil): ?>
    <div class="text-center py-5 glass-card rounded">
      <i class="bi bi-person-x d-block mb-3 text-muted" style="font-size:3rem;"></i>
      <h4 class="fw-bold">Sin MatrÃ­cula Asignada</h4>
      <p class="text-muted">No apareces registrado en ninguna ficha de formaciÃ³n. Contacta con el coordinador de tu centro.</p>
    </div>
  <?php else: ?>
    <!-- Tarjeta de perfil -->
    <div class="card glass-card border-0 mb-4 shadow-sm">
      <div class="card-body p-4">
        <div class="row align-items-center g-3">
          <div class="col-auto">
            <div class="avatar bg-primary text-white rounded-circle shadow-sm"
                 style="width:64px;height:64px;font-size:1.5rem;display:flex;align-items:center;justify-content:center;">
              <?= strtoupper(substr(getCurrentUser()['nombre'] ?? '', 0, 2)) ?>
            </div>
          </div>
          <div class="col">
            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars(getCurrentUser()['nombre'] ?? '') ?></h4>
            <span class="badge bg-soft primary me-2">Ficha #<?= htmlspecialchars($mi_perfil['numero_ficha']) ?></span>
            <span class="text-muted small me-2"><?= htmlspecialchars($mi_perfil['programa_nombre']) ?></span>
            <?php if ($mi_perfil['aprendiz_estado'] === 'etapa_practica'): ?>
              <span class="badge bg-primary text-white">Etapa PrÃ¡ctica</span>
            <?php endif; ?>
          </div>
          <div class="col-12 col-md-4 text-md-end">
            <div class="text-muted small">Instructor LÃ­der:</div>
            <div class="fw-bold text-dark"><?= htmlspecialchars($mi_perfil['instructor_nombre'] ?: 'No asignado') ?></div>
            <?php if ($mi_perfil['instructor_seguimiento_nombre']): ?>
              <div class="text-muted small mt-1">Instructor de Seguimiento:</div>
              <div class="fw-bold text-info"><?= htmlspecialchars($mi_perfil['instructor_seguimiento_nombre']) ?></div>
            <?php endif; ?>
            <div class="text-muted small mt-1">Coordinador:</div>
            <div class="fw-semibold text-muted small"><?= htmlspecialchars($mi_perfil['coordinador_nombre'] ?: 'No asignado') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Progreso General -->
    <?php
      $total     = count($mis_actividades);
      $aprobadas = 0;
      $en_proceso = 0;
      foreach ($mis_actividades as $act) {
          if ($act['concepto'] === 'A')      $aprobadas++;
          elseif ($act['concepto'] === 'D')  $en_proceso++;
      }
      $progreso = $total > 0 ? round(($aprobadas / $total) * 100) : 0;
    ?>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card glass-card border-0 p-3 shadow-sm h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted fw-semibold d-block mb-1">Avance AcadÃ©mico</small>
              <h3 class="fw-bold mb-0 text-primary"><?= $progreso ?>%</h3>
            </div>
            <div class="rounded-circle p-3" style="background:rgba(var(--bs-primary-rgb),.1)">
              <i class="bi bi-trophy text-primary" style="font-size:1.5rem;"></i>
            </div>
          </div>
          <div class="progress mt-3" style="height:6px;">
            <div class="progress-bar bg-primary" style="width:<?= $progreso ?>%"></div>
          </div>
          <small class="text-muted d-block mt-2"><?= $aprobadas ?> de <?= $total ?> RAs aprobados</small>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card glass-card border-0 p-3 shadow-sm h-100 border-start border-danger border-4">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted fw-semibold d-block mb-1">Pendientes / NivelaciÃ³n</small>
              <h3 class="fw-bold mb-0 text-danger"><?= $en_proceso ?></h3>
            </div>
            <div class="rounded-circle p-3" style="background:rgba(239,68,68,.1)">
              <i class="bi bi-clock-history text-danger" style="font-size:1.5rem;"></i>
            </div>
          </div>
          <small class="text-muted d-block mt-3">RAs con concepto 'D' que requieren plan de mejoramiento</small>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card glass-card border-0 p-3 shadow-sm h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted fw-semibold d-block mb-1">Anotaciones de Seguimiento</small>
              <h3 class="fw-bold mb-0 text-info"><?= count($mis_retroalimentaciones) ?></h3>
            </div>
            <div class="rounded-circle p-3" style="background:rgba(13,202,240,.1)">
              <i class="bi bi-chat-text text-info" style="font-size:1.5rem;"></i>
            </div>
          </div>
          <small class="text-muted d-block mt-3">Consejos, fortalezas y sugerencias de tus instructores</small>
        </div>
      </div>
    </div>

    <!-- Tabs de detalle -->
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-header border-bottom-0 pb-0 bg-transparent">
        <ul class="nav nav-tabs border-bottom" id="aprendizTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#actividades" type="button">
              <i class="bi bi-card-checklist me-1"></i>Mis Resultados &amp; Calificaciones
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#feedback" type="button">
              <i class="bi bi-chat-dots me-1"></i>Historial de Seguimiento
            </button>
          </li>
        </ul>
      </div>
      <div class="card-body p-4">
        <div class="tab-content">

          <!-- TAB: Resultados de Aprendizaje -->
          <div class="tab-pane fade show active" id="actividades" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr class="table-light">
                    <th class="ps-4">Resultado de Aprendizaje</th>
                    <th>Competencia</th>
                    <th>Evaluado por</th>
                    <th>Fecha</th>
                    <th class="pe-4 text-center">Concepto</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($mis_actividades as $act): ?>
                    <?php $cl = $conceptos_labels[$act['concepto']] ?? ['Pendiente', 'secondary']; ?>
                    <tr>
                      <td class="ps-4">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($act['ra_nombre']) ?></div>
                        <small class="text-muted font-monospace"><?= htmlspecialchars($act['ra_codigo']) ?></small>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark font-monospace" style="max-width:250px;white-space:normal;">
                          <?= htmlspecialchars($act['competencia_codigo'] ?: 'N/A') ?> â€” <?= htmlspecialchars(substr($act['competencia_nombre'] ?: 'General', 0, 40)) ?>
                        </span>
                      </td>
                      <td>
                        <small class="fw-semibold text-muted"><?= htmlspecialchars($act['instructor_nombre'] ?: 'Pendiente') ?></small>
                      </td>
                      <td>
                        <small class="text-muted"><?= $act['fecha_evaluacion'] ? date('d/m/Y', strtotime($act['fecha_evaluacion'])) : 'â€”' ?></small>
                      </td>
                      <td class="pe-4 text-center">
                        <span class="badge-soft <?= $cl[1] ?>"
                              style="cursor:help;"
                              data-bs-toggle="tooltip"
                              title="<?= htmlspecialchars($act['comentario'] ?: 'Sin observaciones') ?>">
                          <?= $cl[0] ?>
                        </span>
                        <?php if (!empty($act['comentario'])): ?>
                          <div class="small text-muted mt-1" style="font-size:.75rem;max-width:200px;font-style:italic;">
                            "<?= htmlspecialchars($act['comentario']) ?>"
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($mis_actividades)): ?>
                    <tr>
                      <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                        AÃºn no se han registrado resultados de aprendizaje para tu programa.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB: Historial retroalimentaciÃ³n -->
          <div class="tab-pane fade" id="feedback" role="tabpanel">
            <?php foreach ($mis_retroalimentaciones as $retro): ?>
              <?php $fi = $feedback_iconos[$retro['tipo']] ?? ['bi bi-info-circle-fill text-info', 'ObservaciÃ³n', 'info']; ?>
              <div class="p-3 mb-3 border rounded shadow-sm bg-white" style="border-left:5px solid var(--bs-<?= $fi[2] ?>) !important;">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <i class="<?= $fi[0] ?>" style="font-size:1.2rem;"></i>
                    <strong class="text-dark"><?= $fi[1] ?></strong>
                  </div>
                  <small class="text-muted"><?= date('d/m/Y H:i', strtotime($retro['fecha_creacion'])) ?></small>
                </div>
                <p class="text-muted mb-2 font-monospace" style="font-size:.9rem;line-height:1.4;">
                  <?= nl2br(htmlspecialchars($retro['contenido'])) ?>
                </p>
                <div class="text-end">
                  <small class="text-muted">Por: <strong><?= htmlspecialchars($retro['instructor_nombre']) ?></strong></small>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($mis_retroalimentaciones)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-chat-square-text d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                No tienes anotaciones de seguimiento registradas.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<!-- ======================================================= -->
<!-- VISTA COORDINADOR / INSTRUCTOR -->
<!-- ======================================================= -->
<?php else: ?>
  <?php if (!$ficha_detalle): ?>
    <div class="text-center py-5 glass-card rounded">
      <i class="bi bi-folder-x d-block mb-3 text-muted" style="font-size:3rem;"></i>
      <h4 class="fw-bold">No hay Fichas Disponibles</h4>
      <p class="text-muted">No tienes fichas de formaciÃ³n asignadas o creadas para realizar seguimiento acadÃ©mico.</p>
    </div>
  <?php else: ?>
    <!-- KPI de la ficha -->
    <div class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card glass-card border-0 h-100 shadow-sm p-4 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <span class="badge bg-soft primary mb-2">Ficha AcadÃ©mica</span>
                <h3 class="fw-bold text-dark mb-1">Ficha #<?= htmlspecialchars($ficha_detalle['numero_ficha']) ?></h3>
                <h5 class="text-muted"><?= htmlspecialchars($ficha_detalle['programa_nombre']) ?></h5>
              </div>
              <span class="badge bg-soft warning">Etapa de <?= htmlspecialchars(ucfirst($ficha_detalle['estado'])) ?></span>
            </div>
            <div class="row g-3 mt-3">
              <div class="col-sm-6">
                <small class="text-muted d-block">Instructor LÃ­der:</small>
                <strong><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($ficha_detalle['instructor_nombre'] ?: 'Sin asignar') ?></strong>
              </div>
              <div class="col-sm-6">
                <small class="text-muted d-block">Coordinador:</small>
                <strong><i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($ficha_detalle['coordinador_nombre'] ?: 'Sin asignar') ?></strong>
              </div>
            </div>
          </div>
          <div class="mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small fw-semibold">Avance Integral del Proyecto Ficha:</span>
              <span class="fw-bold text-dark"><?= (int)$ficha_detalle['cumplimiento_porcentaje'] ?>%</span>
            </div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-success" style="width:<?= (int)$ficha_detalle['cumplimiento_porcentaje'] ?>%"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="row g-3 h-100">
          <div class="col-6 col-lg-12">
            <div class="card glass-card border-0 h-100 p-3 shadow-sm d-flex flex-row align-items-center justify-content-between">
              <div>
                <small class="text-muted d-block fw-semibold">Aprendices</small>
                <h2 class="fw-bold text-primary mb-0"><?= count($aprendices_stats) ?></h2>
              </div>
              <div class="rounded-circle p-3" style="background:rgba(var(--bs-primary-rgb),.1)">
                <i class="bi bi-people text-primary" style="font-size:1.5rem;"></i>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-12">
            <div class="card glass-card border-0 h-100 p-3 shadow-sm d-flex flex-row align-items-center justify-content-between border-start border-danger border-4">
              <?php
                $criticos = 0;
                foreach ($aprendices_stats as $ap_s) {
                    $t = (int)$ap_s['total_actividades'];
                    $a = (int)$ap_s['aprobadas'];
                    $p = $t > 0 ? ($a / $t) * 100 : 0;
                    if ($p < 60 || (int)$ap_s['en_proceso'] > 2) $criticos++;
                }
              ?>
              <div>
                <small class="text-muted d-block fw-semibold">Casos CrÃ­ticos</small>
                <h2 class="fw-bold text-danger mb-0"><?= $criticos ?></h2>
              </div>
              <div class="rounded-circle p-3" style="background:rgba(239,68,68,.1)">
                <i class="bi bi-exclamation-octagon text-danger" style="font-size:1.5rem;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla de aprendices con sus estadÃ­sticas -->
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-header border-bottom-0 bg-transparent p-4 pb-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <h4 class="fw-bold text-dark mb-1">Rendimiento AcadÃ©mico por Aprendiz</h4>
            <p class="text-muted small mb-0">Monitorea el avance por resultados de aprendizaje e interviene oportunamente.</p>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group" style="width: auto; min-width: 250px;">
              <span class="input-group-text bg-transparent border-end-0" style="border-color:rgba(0,0,0,0.15)">
                <i class="bi bi-search text-muted"></i>
              </span>
              <input type="text" id="buscar_aprendiz" class="form-control border-start-0 ps-0" placeholder="Buscar aprendiz...">
            </div>
            
            <div class="btn-group shadow-sm" role="group">
              <button type="button" class="btn btn-sm btn-outline-secondary active btn-alerta-filtro" data-filtro="todos">Todos</button>
              <button type="button" class="btn btn-sm btn-outline-danger btn-alerta-filtro" data-filtro="danger">CrÃ­ticos</button>
              <button type="button" class="btn btn-sm btn-outline-warning btn-alerta-filtro" data-filtro="warning">Riesgo</button>
              <button type="button" class="btn btn-sm btn-outline-success btn-alerta-filtro" data-filtro="success">Al DÃ­a</button>
            </div>

            <button type="button" id="btn_exportar_excel" class="btn btn-sm btn-soft text-primary">
              <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
            </button>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr class="table-light">
                <th class="ps-4">Documento / Aprendiz</th>
                <th class="text-center">Total RAs</th>
                <th class="text-center">Aprobados (A)</th>
                <th class="text-center">En Proceso (D)</th>
                <th class="text-center">Progreso</th>
                <th class="text-center">Nivel Alerta</th>
                <th class="pe-4 text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aprendices_stats as $ap): ?>
                <?php
                  $total_act    = (int)$ap['total_actividades'];
                  $aprobadas_ap = (int)$ap['aprobadas'];
                  $en_proc      = (int)$ap['en_proceso'];
                  $prog         = $total_act > 0 ? round(($aprobadas_ap / $total_act) * 100) : 0;
                  if ($prog < 60 || $en_proc > 2)       { $alerta_label = 'CrÃ­tico'; $alerta_class = 'danger'; }
                  elseif ($prog < 80 || $en_proc > 0)   { $alerta_label = 'Riesgo';  $alerta_class = 'warning'; }
                  else                                   { $alerta_label = 'Al DÃ­a';  $alerta_class = 'success'; }
                ?>
                <tr class="aprendiz-fila" 
                    data-search="<?= htmlspecialchars(strtolower($ap['aprendiz_nombre'] . ' ' . $ap['numero_documento']), ENT_QUOTES, 'UTF-8') ?>"
                    data-alerta="<?= $alerta_class ?>">
                  <td class="ps-4">
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar bg-light text-dark fw-bold rounded-circle border"
                           style="width:32px;height:32px;font-size:.8rem;display:flex;align-items:center;justify-content:center;">
                        <?= strtoupper(substr($ap['aprendiz_nombre'], 0, 2)) ?>
                      </div>
                      <div>
                        <div class="d-flex align-items-center gap-2">
                          <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($ap['aprendiz_nombre']) ?></h6>
                          <?php if ($ap['aprendiz_estado'] === 'etapa_practica'): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary small py-0 px-2 rounded">Etapa PrÃ¡ctica</span>
                          <?php endif; ?>
                        </div>
                        <small class="text-muted font-monospace"><?= htmlspecialchars($ap['tipo_documento']) ?> <?= htmlspecialchars($ap['numero_documento']) ?></small>
                        <?php if ($ap['instructor_seguimiento_nombre']): ?>
                          <div class="small text-info mt-1"><i class="bi bi-person-badge me-1"></i>Seguimiento: <?= htmlspecialchars($ap['instructor_seguimiento_nombre']) ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="text-center fw-semibold text-muted"><?= $total_act ?></td>
                  <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1 rounded"><?= $aprobadas_ap ?></span></td>
                  <td class="text-center"><span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-2 py-1 rounded"><?= $en_proc ?></span></td>
                  <td class="text-center" style="width:180px;">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <div class="progress flex-grow-1" style="height:6px;min-width:80px;">
                        <div class="progress-bar bg-<?= $alerta_class ?>" style="width:<?= $prog ?>%"></div>
                      </div>
                      <small class="fw-bold text-dark"><?= $prog ?>%</small>
                    </div>
                  </td>
                  <td class="text-center">
                    <span class="badge-soft <?= $alerta_class ?>"><?= $alerta_label ?></span>
                  </td>
                  <td class="pe-4 text-end">
                    <button class="btn btn-sm btn-soft me-1"
                            onclick="abrirModalDetalle(<?= $ap['aprendiz_id'] ?>, <?= htmlspecialchars(json_encode($ap['aprendiz_nombre']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ap['aprendiz_email']), ENT_QUOTES, 'UTF-8') ?>)"
                            title="Ver Detalle AcadÃ©mico">
                      <i class="bi bi-eye"></i> Detalle
                    </button>
                    <button class="btn btn-sm btn-soft btn-outline-info"
                            onclick="abrirModalRetroalimentacion(<?= $ap['aprendiz_id'] ?>, <?= htmlspecialchars(json_encode($ap['aprendiz_nombre']), ENT_QUOTES, 'UTF-8') ?>)"
                            title="Registrar Nota de Seguimiento">
                      <i class="bi bi-chat-dots"></i> ObservaciÃ³n
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($aprendices_stats)): ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                    No hay aprendices matriculados en esta ficha.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL: Detalle acadÃ©mico del aprendiz -->
    <div class="modal fade" id="modalDetalleAprendiz" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content glass-card border-0" style="background:rgba(255,255,255,.99);backdrop-filter:blur(25px);">
          <div class="modal-header border-bottom-0 pb-0">
            <div>
              <h5 class="modal-title fw-bold text-dark">
                <i class="bi bi-person-check text-primary me-2"></i>Seguimiento AcadÃ©mico Individual
              </h5>
              <small class="text-muted d-block" id="detalle_aprendiz_subtitulo"></small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <ul class="nav nav-pills bg-light p-1 rounded mb-4" id="pills-tab" role="tablist">
              <li class="nav-item flex-fill text-center">
                <button class="nav-link active w-100 py-2 fw-semibold" data-bs-toggle="pill" data-bs-target="#pills-evals" type="button">
                  <i class="bi bi-check2-circle me-1"></i>Resultados de Aprendizaje
                </button>
              </li>
              <li class="nav-item flex-fill text-center">
                <button class="nav-link w-100 py-2 fw-semibold" data-bs-toggle="pill" data-bs-target="#pills-feedback" type="button">
                  <i class="bi bi-chat-right-text me-1"></i>Anotaciones &amp; BitÃ¡cora
                </button>
              </li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="pills-evals">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="switch_ver_pendientes">
                    <label class="form-check-label fw-semibold text-muted small" for="switch_ver_pendientes">
                      Ver solo pendientes y calificados con D (Deficiente)
                    </label>
                  </div>
                  <button type="button" id="btn_generar_plan_mejoramiento" class="btn btn-sm btn-soft text-danger" style="display:none;">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Borrador Plan de Mejoramiento
                  </button>
                </div>
                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr class="table-light">
                        <th>Resultado de Aprendizaje</th>
                        <th>Competencia</th>
                        <th>Estado Actual</th>
                        <th class="text-end">CalificaciÃ³n</th>
                      </tr>
                    </thead>
                    <tbody id="lista_actividades_detalle"></tbody>
                  </table>
                </div>
              </div>
              <div class="tab-pane fade" id="pills-feedback">
                <div id="lista_feedback_detalle"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cerrar Detalle</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: Registrar evaluaciÃ³n por RA -->
    <div class="modal fade" id="modalCalificarActividad" tabindex="-1" aria-hidden="true" style="z-index:1060;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 bg-white">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-clipboard-check text-primary me-2"></i>Registrar EvaluaciÃ³n
            </h5>
            <button type="button" class="btn-close" onclick="cerrarModalCalificar()"></button>
          </div>
          <form method="POST">
            <input type="hidden" name="action"                  value="registrar_evaluacion">
            <input type="hidden" name="aprendiz_id"             id="calif_aprendiz_id">
            <input type="hidden" name="resultado_aprendizaje_id" id="calif_ra_id">
            <input type="hidden" name="ficha_id"                id="calif_ficha_id">
            <div class="modal-body">
              <div class="mb-3 bg-light p-3 rounded">
                <small class="text-muted d-block">Resultado de aprendizaje:</small>
                <strong class="text-dark" id="calif_actividad_nombre"></strong>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Concepto Evaluativo (SENA)</label>
                <select name="concepto" id="calif_concepto" class="form-select" required>
                  <option value="aprobado">Aprobado (A)</option>
                  <option value="en_proceso">En Proceso (D)</option>
                  <option value="no_aplica">No Aplica</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Comentarios / Observaciones</label>
                <textarea name="comentario" id="calif_comentario" class="form-control" rows="3"
                          placeholder="Describe los puntos a mejorar o felicita al aprendiz..."></textarea>
              </div>
              <div class="mb-3" id="div_calif_motivo" style="display:none;">
                <label class="form-label text-muted small fw-semibold text-danger">Motivo del cambio *</label>
                <input type="text" name="motivo" id="calif_motivo" class="form-control" 
                       placeholder="Ej. Plan de mejoramiento completado">
              </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
              <button type="button" class="btn btn-soft" onclick="cerrarModalCalificar()">Cancelar</button>
              <button type="submit" class="btn btn-primary">Guardar EvaluaciÃ³n</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: AnotaciÃ³n de retroalimentaciÃ³n -->
    <div class="modal fade" id="modalRetroalimentacion" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0" style="background:rgba(255,255,255,.99);backdrop-filter:blur(25px);">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark">
              <i class="bi bi-chat-text text-primary me-2"></i>AnotaciÃ³n de Seguimiento
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST">
            <input type="hidden" name="action"      value="agregar_retroalimentacion">
            <input type="hidden" name="aprendiz_id" id="retro_aprendiz_id">
            <div class="modal-body">
              <div class="mb-3 bg-light p-3 rounded">
                <small class="text-muted d-block">Registrar observaciÃ³n para:</small>
                <strong class="text-dark" id="retro_aprendiz_nombre"></strong>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Tipo de Nota</label>
                <select name="tipo" class="form-select" required>
                  <option value="recomendacion">ðŸ’¡ RecomendaciÃ³n / Sugerencia</option>
                  <option value="fortaleza">â­ Fortaleza / FelicitaciÃ³n</option>
                  <option value="aspecto_mejorar">âš ï¸ Aspecto a Mejorar (Alerta)</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Detalle de la ObservaciÃ³n</label>
                <textarea name="contenido" class="form-control" rows="4"
                          placeholder="Escribe el comentario acadÃ©mico que quedarÃ¡ en el historial del estudiante..." required></textarea>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="privada" id="retro_privada" value="1">
                <label class="form-check-label text-muted small" for="retro_privada">
                  Nota privada (solo visible para instructores y coordinadores)
                </label>
              </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
              <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Registrar Nota</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    const detalleEvaluaciones      = <?= json_encode($detalle_evaluaciones) ?>;
    const detalleRetroalimentacion = <?= json_encode($detalle_retroalimentacion) ?>;
    const conceptosLabels          = <?= json_encode($conceptos_labels) ?>;
    const feedbackIconos           = <?= json_encode($feedback_iconos) ?>;
    const currentFichaId           = <?= $selected_ficha_id ?>;
    const aprendicesStats          = <?= json_encode($aprendices_stats) ?>;
    const fichaDetalle             = <?= json_encode($ficha_detalle) ?>;

    // Map para pasar datos al modal de calificaciÃ³n sin inyecciÃ³n HTML
    const calificarData = {};

    let modalDetalle, modalCalificar, modalRetro;

    document.addEventListener('DOMContentLoaded', function () {
        modalDetalle   = new bootstrap.Modal(document.getElementById('modalDetalleAprendiz'));
        modalCalificar = new bootstrap.Modal(document.getElementById('modalCalificarActividad'));
        modalRetro     = new bootstrap.Modal(document.getElementById('modalRetroalimentacion'));

        // Auto-open apprentice detail modal if ver_aprendiz_id is in URL query parameters
        const urlParams = new URLSearchParams(window.location.search);
        const verAprendizId = parseInt(urlParams.get('ver_aprendiz_id'));
        if (verAprendizId) {
            const ap = aprendicesStats.find(x => parseInt(x.aprendiz_id) === verAprendizId);
            if (ap) {
                abrirModalDetalle(ap.aprendiz_id, ap.aprendiz_nombre, ap.aprendiz_email);
            }
        }

        // Event listener for switch_ver_pendientes
        document.getElementById('switch_ver_pendientes')?.addEventListener('change', function() {
            const verSoloPendientes = this.checked;
            document.querySelectorAll('.ra-fila-modal').forEach(tr => {
                const concepto = tr.dataset.concepto;
                if (verSoloPendientes) {
                    if (concepto === 'D' || concepto === 'pendiente') {
                        tr.style.display = '';
                    } else {
                        tr.style.display = 'none';
                    }
                } else {
                    tr.style.display = '';
                }
            });
        });

        // Event listener for btn_generar_plan_mejoramiento
        document.getElementById('btn_generar_plan_mejoramiento')?.addEventListener('click', function() {
            const aprendizId = parseInt(this.dataset.aprendizId);
            if (!aprendizId) return;
            
            const ap = aprendicesStats.find(x => parseInt(x.aprendiz_id) === aprendizId);
            if (!ap) return;
            
            const evals = detalleEvaluaciones[aprendizId] || [];
            const reprobados = evals.filter(ev => ev.concepto === 'D');
            
            if (reprobados.length === 0) {
                alert('El aprendiz no tiene Resultados de Aprendizaje calificados con D (Deficiente).');
                return;
            }
            
            const printWindow = window.open('', '_blank', 'width=900,height=800');
            if (!printWindow) {
                alert('El navegador bloqueÃ³ la ventana emergente. Por favor, permita las ventanas emergentes para este sitio.');
                return;
            }
            
            const hoy = new Date();
            const fechaFormateada = hoy.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
            
            let raRowsHtml = '';
            reprobados.forEach((ev, idx) => {
                raRowsHtml += `
                    <tr>
                        <td style="text-align: center; font-weight: bold; width: 5%;">${idx + 1}</td>
                        <td style="width: 25%; font-family: monospace; font-size: 0.85rem;">${esc(ev.ra_codigo)}</td>
                        <td style="width: 45%;">${esc(ev.ra_nombre)}</td>
                        <td style="width: 25%; font-size: 0.85rem; font-style: italic; color: #555;">${esc(ev.comentario || 'Pendiente de nivelaciÃ³n')}</td>
                    </tr>
                `;
            });
            
            const fichaNum = fichaDetalle ? esc(fichaDetalle.numero_ficha) : 'N/A';
            const programaNombre = fichaDetalle ? esc(fichaDetalle.programa_nombre) : 'N/A';
            const instructorNombre = fichaDetalle ? esc(fichaDetalle.instructor_nombre || 'No asignado') : 'No asignado';
            const coordinadorNombre = fichaDetalle ? esc(fichaDetalle.coordinador_nombre || 'No asignado') : 'No asignado';
            
            const html = `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plan de Mejoramiento AcadÃ©mico - Ficha #${fichaNum}</title>
    <style>
        @page {
            size: letter;
            margin: 1.5cm;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
            line-height: 1.4;
            font-size: 0.9rem;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            border: 1px solid #00324D;
            padding: 10px;
            vertical-align: middle;
        }
        .logo-container {
            width: 15%;
            text-align: center;
        }
        .logo-sena {
            width: 55px;
            height: 55px;
            display: inline-block;
            background-color: #39A900;
            border-radius: 50%;
            position: relative;
        }
        .logo-sena::before {
            content: "SENA";
            color: white;
            font-family: 'Arial Black', Impact, sans-serif;
            font-size: 15px;
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            letter-spacing: 0.5px;
        }
        .header-title {
            width: 60%;
            text-align: center;
            font-weight: bold;
            font-size: 1.05rem;
            color: #00324D;
            text-transform: uppercase;
        }
        .header-meta {
            width: 25%;
            font-size: 0.75rem;
            color: #555;
        }
        
        .section-title {
            background-color: #00324D;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 10px;
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 3px;
        }
        
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-grid td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 0.85rem;
        }
        .info-grid td.label {
            font-weight: bold;
            background-color: #f7f9fa;
            color: #00324D;
            width: 20%;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #f0f4f7;
            color: #00324D;
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 0.85rem;
            text-align: left;
            font-weight: bold;
        }
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 0.85rem;
            vertical-align: top;
        }
        
        .text-box {
            border: 1px solid #ccc;
            min-height: 80px;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #fafafa;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .text-box-fill {
            min-height: 50px;
            font-family: inherit;
            color: #555;
        }
        
        .signatures-container {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signatures-container td {
            width: 33%;
            text-align: center;
            vertical-align: bottom;
            padding-bottom: 10px;
            border: none;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 80%;
            margin: 0 auto 5px auto;
        }
        .signature-title {
            font-size: 0.8rem;
            font-weight: bold;
            color: #00324D;
        }
        .signature-subtitle {
            font-size: 0.75rem;
            color: #666;
        }
        
        .footer-note {
            text-align: center;
            font-size: 0.7rem;
            color: #888;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .btn-print-action {
            background-color: #39A900;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: background-color 0.2s;
        }
        .btn-print-action:hover {
            background-color: #2e8a00;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 0.85rem;
            }
            .text-box {
                background-color: #fff;
                border: 1px solid #999;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="padding: 10px; background-color: #f0f4f7; border-bottom: 1px solid #ddd; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <span style="color: #00324D; font-weight: bold;">Vista Previa de ImpresiÃ³n - Formato Plan de Mejoramiento</span>
        <button class="btn-print-action" onclick="window.print()">Imprimir Plan de Mejoramiento</button>
    </div>

    <table class="header-table">
        <tr>
            <td class="logo-container">
                <div class="logo-sena"></div>
            </td>
            <td class="header-title">
                Proceso de GestiÃ³n de FormaciÃ³n Profesional Integral<br>
                <span style="font-size: 0.85rem; font-weight: normal;">Plan de Mejoramiento AcadÃ©mico</span>
            </td>
            <td class="header-meta">
                <strong>CÃ³digo:</strong> F-GFPI-19<br>
                <strong>VersiÃ³n:</strong> 1<br>
                <strong>Fecha:</strong> ${fechaFormateada}
            </td>
        </tr>
    </table>

    <div class="section-title">1. InformaciÃ³n General del Aprendiz y del Programa</div>
    <table class="info-grid">
        <tr>
            <td class="label">Nombre del Aprendiz</td>
            <td>${esc(ap.aprendiz_nombre)}</td>
            <td class="label">Documento</td>
            <td>${esc(ap.tipo_documento)} ${esc(ap.numero_documento)}</td>
        </tr>
        <tr>
            <td class="label">Programa de FormaciÃ³n</td>
            <td>${programaNombre}</td>
            <td class="label">NÃºmero de Ficha</td>
            <td><strong>#${fichaNum}</strong></td>
        </tr>
        <tr>
            <td class="label">Instructor LÃ­der</td>
            <td>${instructorNombre}</td>
            <td class="label">Coordinador AcadÃ©mico</td>
            <td>${coordinadorNombre}</td>
        </tr>
        <tr>
            <td class="label">Correo ElectrÃ³nico</td>
            <td>${esc(ap.aprendiz_email)}</td>
            <td class="label">TelÃ©fono / Celular</td>
            <td>${esc(ap.telefono || 'No registrado')}</td>
        </tr>
    </table>

    <div class="section-title">2. Resultados de Aprendizaje por Nivelar (Concepto D)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">Item</th>
                <th style="width: 25%;">CÃ³digo RA</th>
                <th style="width: 45%;">DescripciÃ³n del Resultado de Aprendizaje</th>
                <th style="width: 25%;">Observaciones del Instructor</th>
            </tr>
        </thead>
        <tbody>
            ${raRowsHtml}
        </tbody>
    </table>

    <div class="section-title">3. DiagnÃ³stico AcadÃ©mico y JustificaciÃ³n del Plan</div>
    <div class="text-box text-box-fill">
        El aprendiz presenta rezago o no ha alcanzado las evidencias correspondientes a los resultados de aprendizaje descritos en la secciÃ³n anterior. Es necesario implementar actividades de nivelaciÃ³n para asegurar el cumplimiento del perfil de egreso del programa.
    </div>

    <div class="section-title">4. Actividades de Aprendizaje a Desarrollar (A concertar con el Instructor)</div>
    <div class="text-box" style="min-height: 100px;">
        1. PresentaciÃ³n de las evidencias de producto y desempeÃ±o pendientes detalladas por el instructor.<br>
        2. SustentaciÃ³n presencial o virtual del componente tÃ©cnico asociado a los resultados evaluados con D.<br>
        3. Desarrollo de talleres prÃ¡cticos complementarios.<br>
        <br>
        <strong>Fecha LÃ­mite de Entrega:</strong> ____________________________
    </div>

    <div class="section-title">5. Compromisos del Aprendiz</div>
    <div class="text-box" style="min-height: 80px; font-style: italic;">
        Yo, <strong>${esc(ap.aprendiz_nombre)}</strong>, identificado con documento nÃºmero <strong>${esc(ap.numero_documento)}</strong>, me comprometo a desarrollar y entregar en las fechas y condiciones establecidas en este documento las actividades de nivelaciÃ³n propuestas, entendiendo que el incumplimiento del presente plan de mejoramiento darÃ¡ lugar a los trÃ¡mites disciplinarios y acadÃ©micos establecidos en el Reglamento del Aprendiz SENA.
    </div>

    <table class="signatures-container">
        <tr>
            <td>
                <div class="signature-line"></div>
                <div class="signature-title">${esc(ap.aprendiz_nombre)}</div>
                <div class="signature-subtitle">Firma del Aprendiz</div>
                <div class="signature-subtitle">CC. ${esc(ap.numero_documento)}</div>
            </td>
            <td>
                <div class="signature-line"></div>
                <div class="signature-title">${instructorNombre}</div>
                <div class="signature-subtitle">Firma del Instructor</div>
                <div class="signature-subtitle">Gestor AcadÃ©mico</div>
            </td>
            <td>
                <div class="signature-line"></div>
                <div class="signature-title">${coordinadorNombre}</div>
                <div class="signature-subtitle">Firma Coordinador</div>
                <div class="signature-subtitle">Centro de FormaciÃ³n</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Servicio Nacional de Aprendizaje SENA - DirecciÃ³n de FormaciÃ³n Profesional - Formato Plan de Mejoramiento AcadÃ©mico<br>
        Copia controlada - Proceso de GestiÃ³n de FormaciÃ³n
    </div>
</body>
</html>
            `;
            printWindow.document.write(html);
            printWindow.document.close();
        });

        // Event listener for btn_exportar_excel
        document.getElementById('btn_exportar_excel')?.addEventListener('click', function() {
            if (!aprendicesStats || aprendicesStats.length === 0) {
                alert('No hay datos para exportar.');
                return;
            }
            
            let tbodyHtml = '';
            
            aprendicesStats.forEach(ap => {
                const total = parseInt(ap.total_actividades) || 0;
                const aprobadas = parseInt(ap.aprobadas) || 0;
                const en_proceso = parseInt(ap.en_proceso) || 0;
                const prog = total > 0 ? Math.round((aprobadas / total) * 100) : 0;
                
                let alertaClass = 'alert-dia';
                let alertaText = 'Al DÃ­a';
                if (prog < 60 || en_proceso > 2) {
                    alertaClass = 'alert-critico';
                    alertaText = 'CrÃ­tico';
                } else if (prog < 80 || en_proceso > 0) {
                    alertaClass = 'alert-riesgo';
                    alertaText = 'Riesgo';
                }
                
                tbodyHtml += `
                    <tr>
                        <td style="border: 1px solid #cccccc; padding: 8px;">${esc(ap.tipo_documento)}</td>
                        <td style="border: 1px solid #cccccc; padding: 8px; font-family: 'Consolas', monospace; text-align: center;">${esc(ap.numero_documento)}</td>
                        <td style="border: 1px solid #cccccc; padding: 8px; font-weight: bold;">${esc(ap.aprendiz_nombre)}</td>
                        <td style="border: 1px solid #cccccc; padding: 8px; text-align: center;">${total}</td>
                        <td style="border: 1px solid #cccccc; padding: 8px; text-align: center; color: #137333; font-weight: bold;">${aprobadas}</td>
                        <td style="border: 1px solid #cccccc; padding: 8px; text-align: center; color: #a51d24; font-weight: bold;">${en_proceso}</td>
                        <td style="border: 1px solid #cccccc; padding: 8px; text-align: center; font-weight: bold;">${prog}%</td>
                        <td class="${alertaClass}" style="border: 1px solid #cccccc; padding: 8px;">${alertaText}</td>
                    </tr>
                `;
            });
            
            const fichaNum = fichaDetalle ? esc(fichaDetalle.numero_ficha) : 'N/A';
            const programaNombre = fichaDetalle ? esc(fichaDetalle.programa_nombre) : 'N/A';
            
            const excelHtml = `
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Seguimiento Ficha #${fichaNum}</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        table { border-collapse: collapse; font-family: 'Segoe UI', Arial, sans-serif; }
        th { background-color: #00324D; color: #ffffff; font-weight: bold; border: 1px solid #cccccc; padding: 10px; text-align: center; }
        .alert-critico { background-color: #fce8e6; color: #a51d24; font-weight: bold; text-align: center; }
        .alert-riesgo { background-color: #fef7e0; color: #b06000; font-weight: bold; text-align: center; }
        .alert-dia { background-color: #e6f4ea; color: #137333; font-weight: bold; text-align: center; }
        .title-header { font-size: 1.3rem; font-weight: bold; color: #00324D; }
        .sub-header { font-size: 1rem; color: #555555; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="8" class="title-header" style="border: none; padding: 10px 0;">REPORTE DE SEGUIMIENTO ACADÃ‰MICO - SENA</td>
        </tr>
        <tr>
            <td colspan="8" class="sub-header" style="border: none; padding-bottom: 20px;">
                <strong>Programa:</strong> ${programaNombre} | <strong>Ficha:</strong> #${fichaNum}
            </td>
        </tr>
        <thead>
            <tr>
                <th>Tipo Documento</th>
                <th>NÃºmero Documento</th>
                <th>Nombre Aprendiz</th>
                <th>Total RAs</th>
                <th>Aprobados (A)</th>
                <th>En Proceso (D)</th>
                <th>Progreso (%)</th>
                <th>Nivel Alerta</th>
            </tr>
        </thead>
        <tbody>
            ${tbodyHtml}
        </tbody>
    </table>
</body>
</html>
            `;
            
            const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            
            const numeroFicha = fichaDetalle ? fichaDetalle.numero_ficha : 'Seguimiento';
            link.setAttribute("download", `Seguimiento_Ficha_${numeroFicha}.xls`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    });

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function abrirModalDetalle(aprendizId, nombre, email) {
        document.getElementById('detalle_aprendiz_subtitulo').innerText = nombre + ' (' + email + ')';

        // Reset the switch on open
        const switchPendientes = document.getElementById('switch_ver_pendientes');
        if (switchPendientes) {
            switchPendientes.checked = false;
        }

        const tbody = document.getElementById('lista_actividades_detalle');
        tbody.innerHTML = '';
        const evals = detalleEvaluaciones[aprendizId] || [];

        if (evals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay RAs registrados para esta ficha.</td></tr>';
        } else {
            evals.forEach(ev => {
                const cl          = conceptosLabels[ev.concepto] || ['Pendiente', 'secondary'];
                const commentHtml = ev.comentario ? `<div class="small text-muted mt-1 fst-italic">"${esc(ev.comentario)}"</div>` : '';
                const dateHtml    = ev.fecha_evaluacion ? `<small class="text-muted d-block">Fecha: ${ev.fecha_evaluacion}</small>` : '';

                const key = `${aprendizId}_${ev.ra_id}`;
                calificarData[key] = {
                    aprendizId,
                    raId:      ev.ra_id,
                    raNombre:  ev.ra_nombre || '',
                    fichaId:   currentFichaId,
                    concepto:  ev.concepto || '',
                    comentario: ev.comentario || ''
                };

                tbody.innerHTML += `
                    <tr class="ra-fila-modal" data-concepto="${esc(ev.concepto || 'pendiente')}">
                      <td>
                        <div class="fw-semibold text-dark">${esc(ev.ra_nombre)}</div>
                        <small class="text-muted font-monospace">${esc(ev.ra_codigo)}</small>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark font-monospace" style="max-width:250px;white-space:normal;">
                          ${esc(ev.competencia_codigo || 'N/A')} â€” ${esc((ev.competencia_nombre || 'General').substring(0,40))}
                        </span>
                      </td>
                      <td>
                        <span class="badge-soft ${cl[1]}">${cl[0]}</span>
                        ${commentHtml}
                        ${dateHtml}
                      </td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-soft" onclick="abrirCalificarDesdeKey('${key}')">
                          <i class="bi bi-pencil-square"></i> Calificar
                        </button>
                      </td>
                    </tr>`;
            });
        }

        // Show/hide plan de mejoramiento button
        const tieneReprobados = evals.some(ev => ev.concepto === 'D');
        const btnPlan = document.getElementById('btn_generar_plan_mejoramiento');
        if (btnPlan) {
            if (tieneReprobados) {
                btnPlan.style.display = 'inline-block';
                btnPlan.dataset.aprendizId = aprendizId;
            } else {
                btnPlan.style.display = 'none';
            }
        }

        const container = document.getElementById('lista_feedback_detalle');
        container.innerHTML = '';
        const retros = detalleRetroalimentacion[aprendizId] || [];

        if (retros.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-muted">Sin notas de seguimiento registradas.</div>';
        } else {
            retros.forEach(r => {
                const fi = feedbackIconos[r.tipo] || ['bi bi-info-circle-fill text-info', 'ObservaciÃ³n', 'info'];
                const privBadge = r.privada == 1
                    ? '<span class="badge bg-danger ms-2"><i class="bi bi-eye-slash-fill me-1"></i>Privado</span>'
                    : '';
                container.innerHTML += `
                    <div class="p-3 mb-3 border rounded shadow-sm bg-white" style="border-left:5px solid var(--bs-${fi[2]}) !important;">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                          <i class="${fi[0]}" style="font-size:1.1rem;"></i>
                          <strong class="text-dark">${fi[1]}</strong>
                          ${privBadge}
                        </div>
                        <small class="text-muted">${r.fecha_creacion}</small>
                      </div>
                      <p class="text-muted mb-1 font-monospace" style="font-size:.85rem;">
                        ${esc(r.contenido || '').replace(/\n/g,'<br>')}
                      </p>
                      <div class="text-end">
                        <small class="text-muted">Instructor: <strong>${esc(r.instructor_nombre)}</strong></small>
                      </div>
                    </div>`;
            });
        }

        modalDetalle.show();
    }

    let originalConceptoSeguimiento = '';

    function abrirCalificarDesdeKey(key) {
        const d = calificarData[key];
        if (!d) return;
        document.getElementById('calif_aprendiz_id').value       = d.aprendizId;
        document.getElementById('calif_ra_id').value              = d.raId;
        document.getElementById('calif_ficha_id').value           = d.fichaId;
        document.getElementById('calif_actividad_nombre').innerText = d.raNombre;
        
        // Map DB concepto to form option value
        const map = { 'A': 'aprobado', 'D': 'en_proceso', 'pendiente': 'no_aplica' };
        const mappedConcepto = map[d.concepto] || 'no_aplica';
        document.getElementById('calif_concepto').value  = mappedConcepto;
        document.getElementById('calif_comentario').value = d.comentario || '';
        
        originalConceptoSeguimiento = d.concepto || 'pendiente';
        
        // Ocultar div de motivo al abrir
        const divMotivo = document.getElementById('div_calif_motivo');
        const inputMotivo = document.getElementById('calif_motivo');
        if (divMotivo && inputMotivo) {
            divMotivo.style.display = 'none';
            inputMotivo.value = '';
            inputMotivo.required = false;
        }

        modalCalificar.show();
    }

    // Toggle visual del campo motivo segÃºn el cambio de concepto
    document.getElementById('calif_concepto')?.addEventListener('change', function() {
        const divMotivo = document.getElementById('div_calif_motivo');
        const inputMotivo = document.getElementById('calif_motivo');
        const mapRev = { 'aprobado': 'A', 'en_proceso': 'D', 'no_aplica': 'pendiente' };
        const nuevoConcepto = mapRev[this.value] || 'pendiente';
        
        if (originalConceptoSeguimiento !== 'pendiente' && originalConceptoSeguimiento !== nuevoConcepto) {
            if (divMotivo && inputMotivo) {
                divMotivo.style.display = 'block';
                inputMotivo.required = true;
            }
        } else {
            if (divMotivo && inputMotivo) {
                divMotivo.style.display = 'none';
                inputMotivo.required = false;
            }
        }
    });

    // Validar motivo en el envÃ­o
    document.getElementById('modalCalificarActividad')?.querySelector('form')?.addEventListener('submit', function(e) {
        const mapRev = { 'aprobado': 'A', 'en_proceso': 'D', 'no_aplica': 'pendiente' };
        const nuevoConcepto = mapRev[document.getElementById('calif_concepto').value] || 'pendiente';
        const motivo = document.getElementById('calif_motivo').value.trim();
        
        if (originalConceptoSeguimiento !== 'pendiente' && originalConceptoSeguimiento !== nuevoConcepto && !motivo) {
            e.preventDefault();
            alert('Debes ingresar el motivo del cambio de calificaciÃ³n (ej. Plan de mejoramiento completado).');
        }
    });

    function cerrarModalCalificar() { modalCalificar.hide(); }

    function abrirModalRetroalimentacion(aprendizId, nombre) {
        document.getElementById('retro_aprendiz_id').value        = aprendizId;
        document.getElementById('retro_aprendiz_nombre').innerText = nombre;
        modalRetro.show();
    }

    let filtroAlertaActivo = 'todos';

    function filtrarAprendices() {
        const query = document.getElementById('buscar_aprendiz')?.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim() || '';
        document.querySelectorAll('.aprendiz-fila').forEach(tr => {
            const searchVal = (tr.dataset.search || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const alertaVal = tr.dataset.alerta || 'success';
            
            const matchSearch = searchVal.includes(query);
            const matchAlerta = (filtroAlertaActivo === 'todos' || alertaVal === filtroAlertaActivo);
            
            if (matchSearch && matchAlerta) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
        });
    }

    // Buscador en tiempo real de aprendices (con soporte de acentos/tildes)
    document.getElementById('buscar_aprendiz')?.addEventListener('input', filtrarAprendices);

    // Filtros de nivel de alerta
    document.querySelectorAll('.btn-alerta-filtro').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-alerta-filtro').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filtroAlertaActivo = this.dataset.filtro;
            filtrarAprendices();
        });
    });
    </script>
  <?php endif; ?>
<?php endif; ?>
