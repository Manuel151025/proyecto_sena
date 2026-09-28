<?php
declare(strict_types=1);

// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Support\Semaforo;

$scriptsVista[] = 'modulos/graficos.js';
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$pct = static fn($v) => $v === null ? '—' : ((int)round((float)$v)) . '%';
$fecha = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach;
if (!empty($vacio)) { return; }
$r = $resumen;
$c = $carga;
?>
<section class="panel-hero p-3 p-md-4 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
  <div>
    <span class="etiqueta mb-2">INSTRUCTOR</span>
    <h1 class="h4 fw-bold mb-1 text-white">Hola, <?= e($nombreUsuario) ?></h1>
    <p class="mb-0 small">Tienes <?= (int)$r['fichas_activas'] ?> ficha(s) y <?= (int)$r['aprendices'] ?> aprendices en formación. Esto es lo que requiere tu atención hoy.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= $url('/seguimiento') ?>" class="btn btn-light btn-sm fw-bold"><i class="bi bi-person-lines-fill me-1"></i>Seguimiento</a>
    <a href="<?= $url('/evaluaciones/importar') ?>" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-upload me-1"></i>Importar juicios</a>
  </div>
</section>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['etiqueta' => 'Por calificar', 'valor' => number_format($c['por_calificar'], 0, ',', '.'), 'icono' => 'bi-hourglass-split', 'enlace' => '/evaluaciones?concepto=pendiente', 'nota' => 'RAP pendientes que calificas'],
      ['etiqueta' => 'RAP en D sin plan', 'valor' => $c['d_sin_plan'], 'icono' => 'bi-exclamation-diamond', 'enlace' => '/mejoramiento#sin-plan', 'clase' => $c['d_sin_plan'] > 0 ? 'text-warning-emphasis' : ''],
      ['etiqueta' => 'Planes vencidos', 'valor' => $c['planes_vencidos'], 'icono' => 'bi-alarm', 'enlace' => '/mejoramiento?estado=vencido', 'clase' => $c['planes_vencidos'] > 0 ? 'text-danger' : ''],
      ['etiqueta' => 'Evidencias por revisar', 'valor' => $c['evidencias_por_revisar'], 'icono' => 'bi-inbox', 'enlace' => '/evidencias?estado=enviada'],
      ['etiqueta' => 'Desempeño de tus fichas', 'valor' => $pct($r['desempeno']), 'icono' => 'bi-speedometer2', 'clase' => 'text-' . Semaforo::clase($r['semaforo'])],
      ['etiqueta' => 'Avance de RAP', 'valor' => $pct($r['avance']), 'icono' => 'bi-graph-up-arrow', 'nota' => 'Deserción ' . $pct($r['desercion'])],
  ] as $kpi): ?>
    <div class="col-6 col-md-4 col-xl-2"><?php require BASE_PATH . 'components/kpi.php'; ?></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-2">Semáforo de tus aprendices</h2>
      <div class="grafico">
        <canvas role="img" aria-label="Aprendices por semáforo" data-grafico="<?= datosJson(['tipo' => 'doughnut',
            'etiquetas' => array_map([Semaforo::class, 'etiqueta'], array_keys($semaforo)),
            'series' => [['nombre' => 'Aprendices', 'datos' => array_values($semaforo), 'colores' => ['#ef4444', '#f59e0b', '#22c55e', '#94a3b8']]]]) ?>"></canvas>
        <div class="grafico-vacio" hidden>Sin aprendices en formación.</div>
      </div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-2">Juicios emitidos por semana en tus fichas</h2>
      <div class="grafico">
        <canvas role="img" aria-label="Juicios A y D por semana" data-grafico="<?= datosJson(['tipo' => 'bar', 'apilado' => true, 'etiquetas' => $tendencia['etiquetas'],
            'series' => [['nombre' => 'A', 'datos' => $tendencia['a'], 'color' => '#22c55e'], ['nombre' => 'D', 'datos' => $tendencia['d'], 'color' => '#ef4444']]]) ?>"></canvas>
        <div class="grafico-vacio" hidden>No se emitieron juicios en las últimas 12 semanas.</div>
      </div>
    </div></div>
  </div>
</div>

<h2 class="h6 fw-bold mb-2">Tus fichas</h2>
<div class="row g-3 mb-4">
  <?php foreach ($fichas as $f): ?>
  <div class="col-md-6 col-xl-4">
    <a class="card border-0 shadow-sm h-100 text-reset text-decoration-none tarjeta-elevable" href="<?= $url('/seguimiento?ficha_id=' . (int)$f['id']) ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <div class="fw-bold">Ficha <?= e($f['numero_ficha']) ?></div>
          <span class="badge-soft <?= e(Semaforo::clase($f['semaforo'])) ?>"><?= e(Semaforo::etiqueta($f['semaforo'])) ?></span>
        </div>
        <div class="small text-muted text-uppercase-visual mb-2"><?= e($f['programa']) ?></div>
        <div class="d-flex justify-content-between small"><span>Desempeño</span><strong><?= e($pct($f['pct_a'])) ?></strong></div>
        <div class="d-flex justify-content-between small"><span>Avance de RAP</span><strong><?= e($pct($f['cumplimiento'])) ?></strong></div>
        <div class="progress barra-avance my-1" role="progressbar" aria-label="Avance de RAP" aria-valuenow="<?= (int)round((float)$f['cumplimiento']) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= (int)round((float)$f['cumplimiento']) ?>%"></div></div>
        <div class="d-flex justify-content-between small"><span>Proyecto formativo</span><strong><?= e($pct($f['avance_proyecto'])) ?></strong></div>
        <div class="small text-muted mt-1"><?= (int)$f['aprendices_activos'] ?> aprendices · <?= (int)$f['en_d'] ?> RAP en D</div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
  <?php if (empty($fichas)): ?><div class="col-12 estado-vacio"><i class="bi bi-folder2-open"></i>No tienes fichas asignadas.</div><?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-2">Aprendices en riesgo</h2>
      <div class="list-group list-group-flush">
        <?php foreach ($enRiesgo as $a): ?>
          <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 px-0" href="<?= $url('/seguimiento?ficha_id=' . (int)$a['ficha_id'] . '&aprendiz_id=' . (int)$a['id'] . '#expediente') ?>">
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?= e($a['nombre']) ?></div>
              <small class="text-muted">Ficha <?= e($a['numero_ficha']) ?> · <?= (int)$a['en_d'] ?> en D · <?= (int)$a['planes'] ?> plan(es)</small>
            </div>
            <span class="badge-soft <?= e(Semaforo::clase($a['semaforo'])) ?> text-nowrap"><?= e($pct($a['pct_a'])) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (empty($enRiesgo)): ?><div class="text-muted small py-3">Ningún aprendiz en riesgo.</div><?php endif; ?>
      </div>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-2">Competencias con más D</h2>
      <?php foreach ($competencias as $co): ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small"><span class="texto-recortado-2 me-2"><span class="font-monospace"><?= e($co['codigo']) ?></span> · <?= e($co['nombre']) ?></span><strong class="text-danger text-nowrap"><?= e((string)$co['pct_d']) ?>% D</strong></div>
          <div class="progress barra-avance" role="progressbar" aria-label="Proporción en D" aria-valuenow="<?= (int)round($co['pct_d']) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-danger" style="width: <?= (int)round($co['pct_d']) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($competencias)): ?><div class="text-muted small">Sin competencias con D.</div><?php endif; ?>
      <?php if (!empty($actividades)): ?>
        <h2 class="h6 fw-bold mt-4 mb-2">Actividades por vencer</h2>
        <?php foreach ($actividades as $act): $dias = (int)$act['dias']; ?>
          <div class="d-flex justify-content-between small mb-1 gap-2"><span class="text-truncate"><?= e($act['nombre']) ?> · Ficha <?= e($act['numero_ficha']) ?></span>
            <span class="badge-soft <?= $dias < 0 ? 'danger' : ($dias <= 3 ? 'warning' : 'secondary') ?> text-nowrap"><?= e($fecha($act['fecha_fin'])) ?></span></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div></div>
  </div>
</div>
