<?php
declare(strict_types=1);

$totalRA = max(1, (int)$progreso['total_ra']);
$pctAprobado = round(((int)$progreso['aprobados'] / $totalRA) * 100);
$pctReprobado = round(((int)$progreso['reprobados'] / $totalRA) * 100);
$pctPendiente = round(((int)$progreso['pendientes'] / $totalRA) * 100);

// Lógica de Gamificación (Niveles y Rangos)
$totalAprobados = (int)$progreso['aprobados'];
$totalRA_count = (int)$progreso['total_ra'];

if ($pctAprobado >= 100) {
    $nivel = 5;
    $rangoTitulo = 'Tecnólogo Master';
    $rangoDesc = '¡Etapa lectiva completada! Listo para tu etapa práctica.';
    $rangoIcono = 'bi-trophy-fill';
    $rangoColor = '#eab308'; // Oro (Yellow-500)
    $rasParaSiguiente = 0;
} elseif ($pctAprobado >= 76) {
    $nivel = 4;
    $rangoTitulo = 'Experto en Desarrollo';
    $rangoDesc = 'En la recta final de tu etapa lectiva. ¡Casi en la meta!';
    $rangoIcono = 'bi-rocket-takeoff';
    $rangoColor = '#8b5cf6'; // Violeta (Purple-500)
    $siguientePorcentaje = 100;
    $limiteSiguiente = (int)ceil($totalRA_count * ($siguientePorcentaje / 100));
    $rasParaSiguiente = max(1, $limiteSiguiente - $totalAprobados);
} elseif ($pctAprobado >= 51) {
    $nivel = 3;
    $rangoTitulo = 'Tecnólogo Avanzado';
    $rangoDesc = 'Dominando las competencias clave de tu programa.';
    $rangoIcono = 'bi-cpu';
    $rangoColor = '#f59e0b'; // Ámbar (Amber-500)
    $siguientePorcentaje = 76;
    $limiteSiguiente = (int)ceil($totalRA_count * ($siguientePorcentaje / 100));
    $rasParaSiguiente = max(1, $limiteSiguiente - $totalAprobados);
} elseif ($pctAprobado >= 26) {
    $nivel = 2;
    $rangoTitulo = 'Iniciado de Proyectos';
    $rangoDesc = 'Construyendo las bases firmes de tu formación.';
    $rangoIcono = 'bi-shield-check';
    $rangoColor = '#10b981'; // Esmeralda (Emerald-500)
    $siguientePorcentaje = 51;
    $limiteSiguiente = (int)ceil($totalRA_count * ($siguientePorcentaje / 100));
    $rasParaSiguiente = max(1, $limiteSiguiente - $totalAprobados);
} else {
    $nivel = 1;
    $rangoTitulo = 'Aspirante Formativo';
    $rangoDesc = 'Iniciando tu camino en el SENA. ¡Mucho éxito!';
    $rangoIcono = 'bi-award';
    $rangoColor = '#3b82f6'; // Azul (Blue-500)
    $siguientePorcentaje = 26;
    $limiteSiguiente = (int)ceil($totalRA_count * ($siguientePorcentaje / 100));
    $rasParaSiguiente = max(1, $limiteSiguiente - $totalAprobados);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Hola, <?= $nombreUsuario ?> 👋</h1>
    <?php if ($aprendiz): ?>
      <p class="text-muted mb-0">
        Ficha #<?= htmlspecialchars($aprendiz['numero_ficha']) ?> · <?= htmlspecialchars($aprendiz['programa']) ?> · 
        Instructor: <?= htmlspecialchars($aprendiz['instructor_nombre']) ?>
      </p>
    <?php else: ?>
      <p class="text-muted mb-0">No estás matriculado en ninguna ficha actualmente.</p>
    <?php endif; ?>
  </div>
  <span class="badge-soft primary"><i class="bi bi-mortarboard me-1"></i>Aprendiz</span>
</div>

<?php if ($aprendiz): ?>

<!-- Panel de Alertas Académicas / Nivelación -->
<?php if (!empty($alertasD)): ?>
  <div class="card border-0 mb-4 shadow-sm" style="border-left: 5px solid var(--danger) !important; border-radius: 12px; background: var(--danger-bg);">
    <div class="card-body p-3">
      <div class="d-flex align-items-center mb-2 text-danger">
        <i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.25rem;"></i>
        <h3 class="mb-0 text-danger" style="font-size: 0.95rem; font-weight: 700;">Alerta de Rendimiento: Tienes <?= count($alertasD) ?> Plan(es) de Mejoramiento pendientes</h3>
      </div>
      <p class="text-muted small mb-3">
        Has obtenido una evaluación de "En Proceso" (D) en los siguientes resultados de aprendizaje. Corrige tus evidencias y solicita asesoría con el instructor correspondiente.
      </p>
      <div class="row g-2 mb-3">
        <?php foreach ($alertasD as $ad): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="p-2 border rounded shadow-xs bg-elev" style="font-size: 0.8rem; border-color: var(--border) !important;">
              <div class="d-flex justify-content-between mb-1">
                <span class="badge-soft danger px-2 py-0.5" style="font-size: 0.65rem;">Pendiente</span>
                <small class="text-muted" style="font-size: 0.72rem;"><?= date('d/m/Y', strtotime($ad['fecha_evaluacion'])) ?></small>
              </div>
              <div class="text-truncate fw-bold text-dark" style="font-size: 0.82rem;" title="<?= htmlspecialchars($ad['ra_denominacion']) ?>"><?= htmlspecialchars($ad['ra_denominacion']) ?></div>
              <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 1px;"><i class="bi bi-person me-1"></i>Instructor: <?= htmlspecialchars($ad['instructor_nombre']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <a href="<?= MODULES_PATH ?>/mejoramiento/" class="btn btn-sm btn-danger text-white px-3" style="border-radius: 8px; font-size: 0.82rem; font-weight: 600;">
        <i class="bi bi-arrow-right-circle me-1"></i> Ver mis planes
      </a>
    </div>
  </div>
<?php else: ?>
  <div class="card border-0 mb-4 shadow-sm" style="border-left: 5px solid var(--success) !important; border-radius: 12px; background: var(--success-bg);">
    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center">
        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 38px; height: 38px; background-color: rgba(46, 139, 31, 0.1); color: var(--success); border: 1.5px solid rgba(46, 139, 31, 0.15);">
          <i class="bi bi-shield-fill-check" style="font-size: 1.2rem; color: var(--success);"></i>
        </div>
        <div>
          <h4 class="mb-0 fw-bold" style="font-size: 0.95rem; color: var(--success);">¡Formación al Día!</h4>
          <small class="text-muted" style="font-size: 0.78rem;">No registras planes de mejoramiento activos ni deficiencias. ¡Buen trabajo!</small>
        </div>
      </div>
      <span class="badge-soft success px-3 py-1.5" style="font-size: 0.72rem;">
        <i class="bi bi-patch-check-fill me-1"></i> Sin Novedades
      </span>
    </div>
  </div>
<?php endif; ?>

<!-- Fila de Progreso Global & Gamificación -->
<div class="row g-3 mb-4">
  
  <!-- Progreso Global -->
  <div class="col-lg-7 col-xl-8">
    <div class="card h-100 border-0 bg-elev shadow-sm" style="border-top: 4px solid var(--sena-primary) !important; border-radius: 12px;">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h3 class="mb-0 fw-bold" style="font-size: 1.1rem;">Progreso Formativo Global</h3>
              <small class="text-muted"><?= (int)$progreso['aprobados'] ?> de <?= $totalRA ?> Resultados de Aprendizaje aprobados</small>
            </div>
            <div class="text-end">
              <div class="fw-bold text-success" style="font-size: 2.2rem; line-height: 1;"><?= $pctAprobado ?>%</div>
              <small class="text-muted" style="font-size: 0.75rem;">Competente</small>
            </div>
          </div>
          <div class="progress" style="height: 14px; border-radius: 10px; background: var(--surface-2);">
            <div class="progress-bar bg-success" style="width: <?= $pctAprobado ?>%; border-radius: 10px 0 0 10px;" title="Aprobados (A)"></div>
            <div class="progress-bar bg-danger" style="width: <?= $pctReprobado ?>%;" title="No Aprobados (D)"></div>
            <div class="progress-bar" style="width: <?= $pctPendiente ?>%; background: var(--border);" title="Pendientes"></div>
          </div>
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mt-3" style="font-size: 0.78rem;">
          <span><span class="d-inline-block rounded-circle me-1" style="width:10px; height:10px; background:#22c55e;"></span> Aprobados: <?= (int)$progreso['aprobados'] ?></span>
          <span><span class="d-inline-block rounded-circle me-1" style="width:10px; height:10px; background:#ef4444;"></span> No Aprobados: <?= (int)$progreso['reprobados'] ?></span>
          <span><span class="d-inline-block rounded-circle me-1" style="width:10px; height:10px; background:var(--border);"></span> Pendientes: <?= (int)$progreso['pendientes'] ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Nivel de Gamificación -->
  <div class="col-lg-5 col-xl-4">
    <div class="card h-100 hover-scale-sm border-0 bg-elev shadow-sm" style="border-top: 4px solid <?= $rangoColor ?> !important; border-radius: 12px; transition: transform 0.2s ease-out;">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="badge" style="background-color: <?= $rangoColor ?>18; color: <?= $rangoColor ?>; font-weight: 700; border-radius: 30px; padding: 0.35rem 0.75rem; font-size: 0.72rem; border: 1px solid <?= $rangoColor ?>30;">
              <i class="bi bi-arrow-up-circle-fill me-1"></i> NIVEL <?= $nivel ?>
            </span>
            <small class="text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.06em;">SISTEMA DE RANGOS</small>
          </div>
          <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: <?= $rangoColor ?>15; color: <?= $rangoColor ?>; font-size: 1.5rem; border: 1.5px dashed <?= $rangoColor ?>50;">
              <i class="bi <?= $rangoIcono ?>"></i>
            </div>
            <div style="min-width: 0;">
              <h4 class="mb-0 fw-bold text-truncate" style="font-size: 1.05rem;"><?= $rangoTitulo ?></h4>
              <p class="text-muted small mb-0 text-truncate-2" style="font-size: 0.76rem; line-height: 1.3;"><?= $rangoDesc ?></p>
            </div>
          </div>
        </div>
        
        <div class="pt-2 mt-3 border-top" style="border-color: var(--border) !important;">
          <?php if ($nivel < 5): ?>
            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.75rem;">
              <span class="text-muted">Siguiente rango:</span>
              <strong class="text-dark">Nivel <?= $nivel + 1 ?></strong>
            </div>
            <div class="text-muted small" style="font-size: 0.72rem; line-height: 1.3;">
              Para subir de rango, te hacen falta <strong><?= $rasParaSiguiente ?></strong> RA(s) aprobados.
            </div>
          <?php else: ?>
            <div class="text-success small fw-bold d-flex align-items-center gap-1" style="font-size: 0.75rem;">
              <i class="bi bi-patch-check-fill text-success" style="font-size: 0.9rem;"></i> ¡Nivel formativo máximo alcanzado!
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Fases del Proyecto -->
<?php if (!empty($fasesProyecto)): ?>
  <h4 class="mb-2 fw-bold text-gradient"><i class="bi bi-kanban me-2 text-primary"></i>Fase actual del proyecto</h4>
  <?php if ($aprendiz['proyecto_nombre']): ?>
    <p class="text-muted mb-3 small"><i class="bi bi-projector me-1"></i><?= htmlspecialchars($aprendiz['proyecto_nombre']) ?> (<?= htmlspecialchars($aprendiz['proyecto_codigo']) ?>)</p>
  <?php endif; ?>
  <div class="phases mb-4">
    <?php foreach ($fasesProyecto as $fase): ?>
      <div class="phase <?= $fase['estado'] === 'completada' ? 'done' : ($fase['estado'] === 'en_ejecucion' ? 'active' : '') ?>">
        <div class="ph-num"><?= $fase['estado'] === 'completada' ? '<i class="bi bi-check"></i>' : $fase['numero_fase'] ?></div>
        <div class="ph-name"><?= htmlspecialchars($fase['nombre']) ?></div>
        <div class="ph-meta"><?= $fase['estado'] === 'completada' ? 'Completada' : ($fase['estado'] === 'en_ejecucion' ? 'En curso' : 'Pendiente') ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Progreso por Competencia -->
<h4 class="mb-3 fw-bold text-gradient"><i class="bi bi-folder2-open me-2 text-primary"></i>Progreso por Competencia</h4>
<div class="row g-3 mb-4">
  <?php foreach ($progresoCompetencias as $comp): ?>
    <?php 
      $compTotal = max(1, (int)$comp['total_ra']);
      $compPct = round(((int)$comp['aprobados'] / $compTotal) * 100);
      $compClass = $compPct >= 75 ? '' : ($compPct >= 50 ? 'warning' : 'danger');
    ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 border-0 bg-elev shadow-sm" style="border-radius: 12px;">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between mb-1">
              <strong class="text-truncate d-block" title="<?= htmlspecialchars($comp['competencia']) ?>" style="max-width: 80%;"><?= htmlspecialchars($comp['competencia']) ?></strong>
              <span class="fw-bold <?= $compClass === 'danger' ? 'text-danger' : ($compClass === 'warning' ? 'text-warning' : 'text-success') ?>"><?= $compPct ?>%</span>
            </div>
            <small class="text-muted d-block mb-2"><?= htmlspecialchars($comp['comp_codigo']) ?> · <?= (int)$comp['aprobados'] ?>/<?= $compTotal ?> RAs aprobados</small>
            <?php if (!empty($comp['instructor_nombre'])): ?>
              <div class="text-muted small mb-2" style="font-size: 0.75rem;">
                <i class="bi bi-person me-1"></i>Instructor: <?= htmlspecialchars($comp['instructor_nombre']) ?>
              </div>
            <?php else: ?>
              <div class="text-muted small mb-2" style="font-size: 0.75rem; font-style: italic;">
                <i class="bi bi-person me-1"></i>Instructor: Por asignar
              </div>
            <?php endif; ?>
          </div>
          <div class="progress-flat <?= $compClass ?>"><div style="width:<?= $compPct ?>%"></div></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($progresoCompetencias)): ?>
    <div class="col-12 text-center py-4 text-muted bg-elev rounded shadow-sm border-0">
      <i class="bi bi-clipboard-x d-block mb-2" style="font-size:2rem; opacity:0.4;"></i>
      Aún no tienes evaluaciones registradas por competencia.
    </div>
  <?php endif; ?>
</div>

<!-- Evaluaciones Recientes, Gráfico y Eventos -->
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100 border-0 bg-elev shadow-sm" style="border-radius: 12px;">
      <div class="card-header fw-bold bg-transparent border-0 pt-3 px-3">
        <h5 class="mb-0 fw-semibold text-gradient"><i class="bi bi-clock-history me-1 text-primary"></i>Últimas Calificaciones</h5>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($evaluacionesRecientes)): ?>
          <ul class="list-unstyled m-0">
            <?php foreach ($evaluacionesRecientes as $ev): ?>
              <li class="d-flex justify-content-between align-items-center p-3 border-bottom" style="border-color:var(--border) !important">
                <div style="max-width: 65%;">
                  <strong><?= htmlspecialchars($ev['ra_codigo']) ?></strong>
                  <small class="text-muted d-block text-truncate"><?= htmlspecialchars($ev['denominacion']) ?></small>
                  <?php if (!empty($ev['instructor_evaluador'])): ?>
                    <small class="text-muted d-block text-truncate" style="font-size: 0.72rem; margin-top: 2px;" title="<?= htmlspecialchars($ev['instructor_evaluador']) ?>">
                      <i class="bi bi-person me-1"></i>Evaluado por: <?= htmlspecialchars($ev['instructor_evaluador']) ?>
                    </small>
                  <?php endif; ?>
                </div>
                <div class="text-end">
                  <span class="badge-soft <?= $ev['concepto'] === 'A' ? 'success' : 'danger' ?> mb-1">
                    <i class="bi <?= $ev['concepto'] === 'A' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                    <?= $ev['concepto'] === 'A' ? 'Aprobado' : 'No Aprobado' ?>
                  </span>
                  <small class="text-muted d-block" style="font-size:0.75rem"><?= $ev['fecha_evaluacion'] ? date('d/m/Y', strtotime($ev['fecha_evaluacion'])) : '' ?></small>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-clipboard d-block mb-2" style="font-size:2rem; opacity:0.4;"></i>
            Sin evaluaciones recientes.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="col-lg-4">
    <div class="card h-100 border-0 bg-elev shadow-sm" style="border-radius: 12px;">
      <div class="card-header fw-bold bg-transparent border-0 pt-3 px-3">
        <h5 class="mb-0 fw-semibold text-gradient"><i class="bi bi-pie-chart me-1 text-success"></i>Resumen por Concepto</h5>
      </div>
      <div class="card-body d-flex flex-column justify-content-center align-items-center">
        <div style="width: 100%; max-width: 200px; height: 200px; position: relative;">
          <canvas id="chartConceptos"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100 border-0 bg-elev shadow-sm" style="border-radius: 12px;">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center bg-transparent border-0 pt-3 px-3">
        <h5 class="mb-0 fw-semibold text-gradient"><i class="bi bi-calendar3 me-1 text-primary"></i>Eventos Previstos</h5>
        <a href="<?= APP_URL ?>/modules/calendario/" class="text-primary small fw-semibold" style="font-size: 0.75rem;"><i class="bi bi-calendar3 me-1"></i>Ver todo</a>
      </div>
      <div class="card-body px-3 pb-3 d-flex flex-column">
        <div class="flex-grow-1 overflow-y-auto" id="dashboard-events-list" style="max-height: 350px;">
          <div class="text-center py-4 text-muted" id="events-loader">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div class="small">Cargando eventos...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
  <div class="card border-0 bg-elev shadow-sm text-center py-5" style="border-radius: 12px;">
    <div class="card-body">
      <i class="bi bi-person-x d-block mb-3" style="font-size: 4rem; color: #d1d5db;"></i>
      <h3 class="fw-bold text-secondary">Sin matrícula activa</h3>
      <p class="text-muted">Tu cuenta no está asociada a ninguna ficha de formación. Contacta al coordinador o instructor.</p>
    </div>
  </div>
<?php endif; ?>

<!-- Bloque de datos JSON transferido del servidor al script del cliente -->
<script id="dashboard-data" type="application/json">
{
  "appUrl": <?= json_encode(APP_URL) ?>,
  "aprobados": <?= (int)$progreso['aprobados'] ?>,
  "reprobados": <?= (int)$progreso['reprobados'] ?>,
  "pendientes":  <?= (int)$progreso['pendientes'] ?>
}
</script>

<!-- Carga del Script del Dashboard del Cliente -->
<script src="<?= APP_URL ?>/assets/js/dashboard/aprendiz.js?v=<?= filemtime(BASE_PATH . 'assets/js/dashboard/aprendiz.js') ?>"></script>
