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
?>
<section class="panel-hero p-3 p-md-4 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
  <div>
    <span class="etiqueta mb-2">COORDINACIÓN ACADÉMICA</span>
    <h1 class="h4 fw-bold mb-1 text-white">Hola, <?= e($nombreUsuario) ?></h1>
    <p class="mb-0 small">Situación del centro al <?= e(date('d/m/Y')) ?>: desempeño y avance de los aprendices, fichas y competencias que requieren atención y carga de los instructores.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= $url('/reportes') ?>" class="btn btn-light btn-sm fw-bold"><i class="bi bi-file-earmark-bar-graph me-1"></i>Reportes</a>
    <a href="<?= $url('/fichas') ?>" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-folder me-1"></i>Fichas</a>
  </div>
</section>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['etiqueta' => 'Fichas activas', 'valor' => $r['fichas_activas'], 'icono' => 'bi-journal-bookmark', 'enlace' => '/fichas', 'nota' => $r['instructores'] . ' instructores activos'],
      ['etiqueta' => 'Aprendices en formación', 'valor' => $r['aprendices'], 'icono' => 'bi-people', 'enlace' => '/matriculas', 'nota' => 'Deserción ' . $pct($r['desercion'])],
      ['etiqueta' => 'Desempeño', 'valor' => $pct($r['desempeno']), 'icono' => 'bi-speedometer2', 'clase' => 'text-' . Semaforo::clase($r['semaforo']), 'nota' => 'RAP en A sobre lo evaluado'],
      ['etiqueta' => 'Avance de RAP', 'valor' => $pct($r['avance']), 'icono' => 'bi-graph-up-arrow', 'nota' => number_format($r['pendientes'], 0, ',', '.') . ' juicios pendientes'],
      ['etiqueta' => 'Planes vigentes', 'valor' => $r['planes_vigentes'], 'icono' => 'bi-arrow-repeat', 'enlace' => '/mejoramiento?estado=vigente',
       'nota' => $r['planes_vencidos'] > 0 ? $r['planes_vencidos'] . ' vencidos' : $r['planes_cumplidos'] . ' cumplidos', 'clase' => $r['planes_vencidos'] > 0 ? 'text-danger' : ''],
      ['etiqueta' => 'Evidencias por revisar', 'valor' => $r['evidencias_por_revisar'], 'icono' => 'bi-inbox', 'enlace' => '/evidencias?estado=enviada'],
  ] as $kpi): ?>
    <div class="col-6 col-md-4 col-xl-2"><?php require BASE_PATH . 'components/kpi.php'; ?></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-1">Semáforo de aprendices</h2>
      <p class="small text-muted mb-2">Crítico: menos del 60 % en A o más de 2 RAP en D. Riesgo: menos del 80 % o algún D.</p>
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
      <h2 class="h6 fw-bold mb-1">Juicios emitidos por semana</h2>
      <p class="small text-muted mb-2">Según el historial de evaluaciones de las últimas 12 semanas.</p>
      <div class="grafico">
        <canvas role="img" aria-label="Juicios A y D por semana" data-grafico="<?= datosJson(['tipo' => 'bar', 'apilado' => true, 'etiquetas' => $tendencia['etiquetas'],
            'series' => [['nombre' => 'A', 'datos' => $tendencia['a'], 'color' => '#22c55e'], ['nombre' => 'D', 'datos' => $tendencia['d'], 'color' => '#ef4444']]]) ?>"></canvas>
        <div class="grafico-vacio" hidden>No se emitieron juicios en las últimas 12 semanas.</div>
      </div>
    </div></div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <h2 class="h6 fw-bold mb-3">Por programa de formación</h2>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="grafico">
        <canvas role="img" aria-label="Desempeño y avance por programa" data-grafico="<?= datosJson(['tipo' => 'bar', 'sufijo' => '%', 'maximo' => 100,
            'etiquetas' => array_column($programas, 'codigo'),
            'series' => [['nombre' => 'Desempeño', 'datos' => array_map(static fn($p) => $p['desempeno'] ?? 0, $programas), 'color' => '#39A900'],
                         ['nombre' => 'Avance', 'datos' => array_map(static fn($p) => $p['avance'] ?? 0, $programas), 'color' => '#0ea5e9']]]) ?>"></canvas>
        <div class="grafico-vacio" hidden>Sin juicios registrados.</div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="table-wrap">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Programa</th><th class="text-end">Fichas</th><th class="text-end">Aprendices</th><th class="text-end">Desempeño</th><th class="text-end">Avance</th><th class="text-end">Deserción</th></tr></thead>
          <tbody>
            <?php foreach ($programas as $p): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($p['codigo']) ?></span><small class="d-block text-muted texto-recortado-2"><?= e($p['nombre']) ?></small></td>
              <td class="text-end"><?= (int)$p['fichas'] ?></td>
              <td class="text-end"><?= (int)$p['aprendices'] ?></td>
              <td class="text-end"><span class="badge-soft <?= e(Semaforo::clase(Semaforo::porcentaje($p['desempeno']))) ?>"><?= e($pct($p['desempeno'])) ?></span></td>
              <td class="text-end"><?= e($pct($p['avance'])) ?></td>
              <td class="text-end"><?= e($pct($p['desercion'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div></div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0">Fichas con menor desempeño</h2><a class="small" href="<?= $url('/fichas') ?>">Todas</a></div>
      <div class="list-group list-group-flush">
        <?php foreach ($fichas as $f): ?>
          <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 px-0" href="<?= $url('/fichas/ver?id=' . (int)$f['id']) ?>">
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold">Ficha <?= e($f['numero_ficha']) ?> <small class="text-muted fw-normal"><?= e($f['codigo_programa']) ?></small></div>
              <small class="text-muted"><?= (int)$f['aprendices_activos'] ?> aprendices · <?= (int)$f['en_d'] ?> RAP en D · avance <?= e($pct($f['cumplimiento'])) ?></small>
            </div>
            <span class="badge-soft <?= e(Semaforo::clase($f['semaforo'])) ?>"><?= e($pct($f['pct_a'])) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (empty($fichas)): ?><div class="text-muted small py-3">No hay fichas.</div><?php endif; ?>
      </div>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0">Aprendices en riesgo</h2><a class="small" href="<?= $url('/reportes') ?>">Reporte completo</a></div>
      <div class="list-group list-group-flush">
        <?php foreach ($enRiesgo as $a): ?>
          <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 px-0" href="<?= $url('/seguimiento?ficha_id=' . (int)$a['ficha_id'] . '&aprendiz_id=' . (int)$a['id'] . '#expediente') ?>">
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?= e($a['nombre']) ?></div>
              <small class="text-muted">Ficha <?= e($a['numero_ficha']) ?> · <?= (int)$a['en_d'] ?> en D · <?= (int)$a['planes'] ?> plan(es)</small>
            </div>
            <span class="badge-soft <?= e(Semaforo::clase($a['semaforo'])) ?>"><?= e(Semaforo::etiqueta($a['semaforo'])) ?> · <?= e($pct($a['pct_a'])) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (empty($enRiesgo)): ?><div class="text-muted small py-3">Ningún aprendiz en riesgo.</div><?php endif; ?>
      </div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-1">Carga de los instructores</h2>
      <p class="small text-muted mb-2">Pendientes y D a su cargo, juicios emitidos en 30 días y planes que acompañan.</p>
      <div class="table-wrap">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Instructor</th><th class="text-end">Fichas</th><th class="text-end">Pendientes</th><th class="text-end">En D</th><th class="text-end">Juicios 30 d</th><th class="text-end">Planes</th></tr></thead>
          <tbody>
            <?php foreach ($instructores as $i): ?>
            <tr>
              <td class="fw-semibold"><?= e($i['nombre']) ?></td>
              <td class="text-end"><?= (int)$i['fichas_lider'] ?></td>
              <td class="text-end"><?= number_format((int)$i['pendientes'], 0, ',', '.') ?></td>
              <td class="text-end <?= (int)$i['en_d'] > 0 ? 'text-danger' : '' ?>"><?= (int)$i['en_d'] ?></td>
              <td class="text-end"><?= (int)$i['juicios_30d'] ?></td>
              <td class="text-end"><?= (int)$i['planes_vigentes'] ?><?= (int)$i['planes_vencidos'] > 0 ? ' <span class="badge-soft danger">' . (int)$i['planes_vencidos'] . ' venc.</span>' : '' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-1">Competencias críticas</h2>
      <p class="small text-muted mb-2">Mayor proporción de RAP en D entre lo evaluado.</p>
      <?php foreach ($competencias as $c): ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small"><span class="texto-recortado-2 me-2"><span class="font-monospace"><?= e($c['codigo']) ?></span> · <?= e($c['nombre']) ?></span><strong class="text-danger text-nowrap"><?= e((string)$c['pct_d']) ?>% D</strong></div>
          <div class="progress barra-avance" role="progressbar" aria-label="Proporción en D" aria-valuenow="<?= (int)round($c['pct_d']) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-danger" style="width: <?= (int)round($c['pct_d']) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($competencias)): ?><div class="text-muted small">Sin competencias con D.</div><?php endif; ?>
    </div></div>
  </div>
</div>

<?php if (!empty($actividades)): ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <h2 class="h6 fw-bold mb-2">Actividades del proyecto formativo por vencer</h2>
  <div class="list-group list-group-flush">
    <?php foreach ($actividades as $act): $dias = (int)$act['dias']; ?>
      <div class="list-group-item d-flex align-items-center gap-2 px-0">
        <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate"><?= e($act['nombre']) ?></div><small class="text-muted">Ficha <?= e($act['numero_ficha']) ?> · <?= (int)$act['cumplimiento_porcentaje'] ?>% de avance</small></div>
        <span class="badge-soft <?= $dias < 0 ? 'danger' : ($dias <= 3 ? 'warning' : 'secondary') ?> text-nowrap"><?= $dias < 0 ? 'Venció el ' . e($fecha($act['fecha_fin'])) : 'Vence el ' . e($fecha($act['fecha_fin'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div></div>
<?php endif; ?>
