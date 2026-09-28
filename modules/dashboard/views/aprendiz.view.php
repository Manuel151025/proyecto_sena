<?php
declare(strict_types=1);

// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Formularios\RetroalimentacionFormulario;
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
$tipos = RetroalimentacionFormulario::TIPOS;
$comparacion = $promedio['avance'] === null ? '' : ($r['avance'] >= $promedio['avance']
    ? 'Vas por encima del promedio de tu ficha (' . $pct($promedio['avance']) . ').'
    : 'El promedio de tu ficha va en ' . $pct($promedio['avance']) . '.');
?>
<section class="panel-hero p-3 p-md-4 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
  <div>
    <span class="etiqueta mb-2">APRENDIZ · FICHA <?= e($r['numero_ficha']) ?></span>
    <h1 class="h4 fw-bold mb-1 text-white">Hola, <?= e($nombreUsuario) ?></h1>
    <p class="mb-0 small"><?= e($r['programa']) ?>. Llevas <?= (int)$r['aprobados'] ?> de <?= (int)$r['total'] ?> resultados de aprendizaje aprobados. <?= e($comparacion) ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= $url('/seguimiento') ?>" class="btn btn-light btn-sm fw-bold"><i class="bi bi-person-lines-fill me-1"></i>Mi seguimiento</a>
    <a href="<?= $url('/evidencias') ?>" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-cloud-upload me-1"></i>Enviar evidencia</a>
  </div>
</section>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['etiqueta' => 'Mi avance', 'valor' => $pct($r['avance']), 'icono' => 'bi-graph-up-arrow', 'nota' => 'Promedio de la ficha: ' . $pct($promedio['avance'])],
      ['etiqueta' => 'Mi desempeño', 'valor' => $pct($r['pct_a']), 'icono' => 'bi-speedometer2', 'clase' => 'text-' . Semaforo::clase($r['semaforo']), 'nota' => Semaforo::etiqueta($r['semaforo'])],
      ['etiqueta' => 'Aprobados (A)', 'valor' => $r['aprobados'], 'icono' => 'bi-check-circle', 'clase' => 'text-success', 'enlace' => '/evaluaciones?concepto=A'],
      ['etiqueta' => 'Por aprobar (D)', 'valor' => $r['en_d'], 'icono' => 'bi-x-circle', 'clase' => (int)$r['en_d'] > 0 ? 'text-danger' : '', 'enlace' => '/evaluaciones?concepto=D'],
      ['etiqueta' => 'Pendientes', 'valor' => $r['pendientes'], 'icono' => 'bi-clock', 'enlace' => '/evaluaciones?concepto=pendiente'],
      ['etiqueta' => 'Planes vigentes', 'valor' => $r['planes_vigentes'], 'icono' => 'bi-arrow-repeat', 'enlace' => '/mejoramiento', 'clase' => (int)$r['planes_vigentes'] > 0 ? 'text-warning-emphasis' : ''],
  ] as $kpi): ?>
    <div class="col-6 col-md-4 col-xl-2"><?php require BASE_PATH . 'components/kpi.php'; ?></div>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <h2 class="h6 fw-bold mb-1">Mi progreso por competencia</h2>
  <p class="small text-muted mb-2">Resultados de aprendizaje aprobados, por aprobar y pendientes en cada competencia.</p>
  <div class="grafico alto">
    <canvas role="img" aria-label="Progreso por competencia" data-grafico="<?= datosJson(['tipo' => 'bar', 'horizontal' => true, 'apilado' => true,
        'etiquetas' => array_column($competencias, 'codigo'),
        'series' => [['nombre' => 'A', 'datos' => array_column($competencias, 'a'), 'color' => '#22c55e'],
                     ['nombre' => 'D', 'datos' => array_column($competencias, 'd'), 'color' => '#ef4444'],
                     ['nombre' => 'Pendiente', 'datos' => array_column($competencias, 'pendientes'), 'color' => '#94a3b8']]]) ?>"></canvas>
    <div class="grafico-vacio" hidden>Tu programa aún no tiene resultados de aprendizaje.</div>
  </div>
</div></div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0">Mis planes de mejoramiento</h2><a class="small" href="<?= $url('/mejoramiento') ?>">Ver todos</a></div>
      <?php foreach ($planes as $p): $dias = (int)$p['dias_restantes']; ?>
        <div class="border-start border-3 ps-2 mb-2 <?= (int)$p['vencido'] ? 'border-danger' : 'border-warning' ?>">
          <div class="small fw-semibold"><span class="font-monospace"><?= e($p['ra_codigo']) ?></span> · <?= e(mb_substr($p['ra_denominacion'], 0, 70)) ?></div>
          <div class="small text-muted"><?= (int)$p['vencido'] ? 'Venció el ' : 'Vence el ' ?><?= e($fecha($p['fecha_limite'])) ?><?= !(int)$p['vencido'] ? " (faltan $dias d)" : '' ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($planes)): ?><div class="text-muted small"><i class="bi bi-patch-check text-success me-1"></i>No tienes planes de mejoramiento vigentes.</div><?php endif; ?>
      <?php if (!empty($actividades)): ?>
        <h2 class="h6 fw-bold mt-4 mb-2">Próximas actividades del proyecto</h2>
        <?php foreach ($actividades as $act): $dias = (int)$act['dias']; ?>
          <div class="d-flex justify-content-between small mb-1 gap-2"><span class="text-truncate"><?= e($act['nombre']) ?></span>
            <span class="badge-soft <?= $dias < 0 ? 'danger' : ($dias <= 3 ? 'warning' : 'secondary') ?> text-nowrap"><?= e($fecha($act['fecha_fin'])) ?></span></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0">Última retroalimentación</h2><a class="small" href="<?= $url('/retroalimentacion') ?>">Ver toda</a></div>
      <?php foreach ($retros as $rt): [$tTexto, $tClase] = $tipos[$rt['tipo']] ?? [$rt['tipo'], 'secondary']; ?>
        <div class="mb-3">
          <span class="badge-soft <?= e($tClase) ?>"><?= e($tTexto) ?></span>
          <span class="small text-muted ms-1"><?= e($rt['instructor_nombre']) ?> · <?= e($fecha($rt['fecha_creacion'])) ?></span>
          <div class="small text-break texto-recortado-2 mt-1"><?= e($rt['contenido']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($retros)): ?><div class="text-muted small">Aún no tienes retroalimentación.</div><?php endif; ?>
    </div></div>
  </div>
</div>
