<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Support\Semaforo;

$scriptsVista[] = 'modulos/filtro-tarjetas.js';
$gestiona = in_array($rol, [ROL_COORDINADOR, ROL_INSTRUCTOR], true);
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$fechaCorta = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
[$estTxt, $estCls] = $estados_label[$ficha['estado']] ?? [$ficha['estado'], 'secondary'];
$semFicha = $ficha['semaforo'];
$fechas = $ficha['fecha_inicio'] || $ficha['fecha_fin'] ? $fechaCorta($ficha['fecha_inicio']) . ' – ' . $fechaCorta($ficha['fecha_fin']) : '';
$id = (int)$ficha['id'];
?>
<div class="page-header">
  <div>
    <a href="<?= $url($rol === ROL_APRENDIZ ? '/dashboard' : '/fichas') ?>" class="small"><i class="bi bi-arrow-left"></i> <?= $rol === ROL_APRENDIZ ? 'Volver al inicio' : 'Volver a fichas' ?></a>
    <h1 class="mt-2 mb-1">Ficha <?= e($ficha['numero_ficha']) ?> <span class="badge-soft <?= e($estCls) ?> ms-1 align-middle fs-6"><?= e($estTxt) ?></span></h1>
    <p class="text-muted mb-0"><span class="text-uppercase-visual"><?= e($ficha['programa']) ?></span> · Líder: <?= e($ficha['instructor']) ?>
      <?php if ($fechas !== ''): ?>· <?= e($fechas) ?><?php endif; ?></p>
  </div>
  <?php if ($gestiona): ?>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= $url('/seguimiento?ficha_id=' . $id) ?>" class="btn btn-primary"><i class="bi bi-graph-up me-1"></i>Seguimiento</a>
    <a href="<?= $url('/evaluaciones?ficha_id=' . $id) ?>" class="btn btn-soft"><i class="bi bi-clipboard-check me-1"></i>Juicios</a>
    <a href="<?= $url('/fichas/exportar?id=' . $id) ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['Aprendices activos', (int)$ficha['aprendices_activos'], 'bi-people', ''],
      ['Desempeño (A sobre evaluados)', $ficha['pct_a'] === null ? '—' : (int)round($ficha['pct_a']) . '%', 'bi-speedometer2', Semaforo::etiqueta($semFicha)],
      ['Avance de RAP (A sobre el total)', $ficha['cumplimiento'] === null ? '—' : (int)round($ficha['cumplimiento']) . '%', 'bi-clipboard-check', ''],
      ['RAP en D', (int)$ficha['en_d'], 'bi-exclamation-triangle', ''],
      ['Planes de mejoramiento abiertos', (int)$ficha['planes_abiertos'], 'bi-arrow-repeat', ''],
      ['Avance del proyecto', $ficha['avance_proyecto'] === null ? '—' : (int)round($ficha['avance_proyecto']) . '%', 'bi-kanban', ''],
  ] as [$et, $val, $ico, $nota]): ?>
  <div class="col-6 col-lg">
    <div class="kpi"><div class="kpi-content">
      <div class="icon-bg"><i class="bi <?= e($ico) ?>"></i></div>
      <div class="label"><?= e($et) ?></div>
      <div class="value"><?= e((string)$val) ?></div>
      <?php if ($nota !== ''): ?><div class="small"><span class="badge-soft <?= e(Semaforo::clase($semFicha)) ?>"><?= e($nota) ?></span></div><?php endif; ?>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-kanban me-2 text-primary"></i>Proyecto formativo</div>
      <div class="card-body">
        <?php if (!$ficha['proyecto_id']): ?>
          <p class="text-muted mb-0">Esta ficha no tiene proyecto formativo asignado.</p>
        <?php else: ?>
          <div class="fw-bold text-uppercase-visual"><?= e($ficha['proyecto_nombre']) ?></div>
          <div class="small text-muted mb-3"><?= e($ficha['proyecto_codigo']) ?> · <?= e($ficha['proyecto_objetivo'] ?: 'Sin objetivo definido.') ?></div>
          <?php foreach ($fases as $fase):
              $tot = (int)$fase['total_actividades'];
              $av = $tot > 0 ? (float)$fase['avance'] : null; ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span><strong>Fase <?= (int)$fase['numero_fase'] ?></strong> · <?= e($fase['nombre']) ?></span>
                <span class="text-muted"><?= $tot > 0 ? ((int)$fase['actividades_completadas'] . '/' . $tot . ' act. · ' . (int)round($av) . '%') : 'sin actividades' ?></span>
              </div>
              <div class="progress barra-avance" role="progressbar" aria-label="Avance de la fase <?= e($fase['nombre']) ?>" aria-valuenow="<?= (int)round((float)$av) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-<?= claseAvance($av) ?>" style="width: <?= (int)round((float)$av) ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
          <a href="<?= $url('/actividades?ficha_id=' . $id) ?>" class="btn btn-sm btn-soft"><i class="bi bi-check2-square me-1"></i>Ver actividades</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><i class="bi bi-people me-2 text-primary"></i><?= $rol === ROL_APRENDIZ ? 'Mi situación' : 'Aprendices (' . count($aprendices) . ')' ?></span>
        <?php if ($rol !== ROL_APRENDIZ): ?>
        <span class="small">
          <span class="badge-soft danger"><?= (int)($conteo[Semaforo::CRITICO] ?? 0) ?> críticos</span>
          <span class="badge-soft warning"><?= (int)($conteo[Semaforo::RIESGO] ?? 0) ?> en riesgo</span>
          <span class="badge-soft success"><?= (int)($conteo[Semaforo::AL_DIA] ?? 0) ?> al día</span>
        </span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($rol !== ROL_APRENDIZ): ?>
        <div class="toolbar mb-2" data-filtro-tarjetas="#tablaAprendices">
          <div class="search"><i class="bi bi-search"></i><input class="form-control" data-filtro-texto placeholder="Nombre o documento..." aria-label="Buscar aprendiz" maxlength="100"></div>
          <div class="toolbar-filter">
            <select class="form-select" data-filtro-estado data-picker data-picker-label="Semáforo">
              <option value="">Todos</option>
              <?php foreach ([Semaforo::CRITICO, Semaforo::RIESGO, Semaforo::AL_DIA, Semaforo::SIN_DATOS] as $s): ?>
                <option value="<?= e($s) ?>"><?= e(Semaforo::etiqueta($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
        <div class="table-wrap">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Aprendiz</th><th>Estado</th><th class="text-center">A</th><th class="text-center">D</th><th class="text-center">Pend.</th><th>Semáforo</th></tr></thead>
            <tbody id="tablaAprendices">
              <?php foreach ($aprendices as $a):
                  [$eaTxt, $eaCls] = $estados_aprendiz[$a['estado']] ?? [$a['estado'], 'secondary']; ?>
              <tr data-texto="<?= e(mb_strtolower($a['nombre'] . ' ' . $a['numero_documento'], 'UTF-8')) ?>" data-estado="<?= e($a['semaforo']) ?>">
                <td>
                  <strong class="d-block"><?= e($a['nombre']) ?></strong>
                  <small class="text-muted"><?= e($a['tipo_documento'] . ' ' . $a['numero_documento']) ?>
                    <?php if ($a['instructor_seguimiento_nombre']): ?> · seguimiento: <?= e($a['instructor_seguimiento_nombre']) ?><?php endif; ?></small>
                </td>
                <td><span class="badge-soft <?= e($eaCls) ?>"><?= e($eaTxt) ?></span></td>
                <td class="text-center"><?= (int)$a['aprobados'] ?></td>
                <td class="text-center <?= (int)$a['en_d'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= (int)$a['en_d'] ?></td>
                <td class="text-center"><?= (int)$a['pendientes'] ?></td>
                <td><span class="badge-soft <?= e(Semaforo::clase($a['semaforo'])) ?>"><?= e(Semaforo::etiqueta($a['semaforo'])) ?></span>
                  <?php if ((int)$a['planes_abiertos'] > 0): ?><span class="badge-soft info" title="Planes de mejoramiento abiertos"><?= (int)$a['planes_abiertos'] ?> plan</span><?php endif; ?></td>
              </tr>
              <?php endforeach; ?>
              <tr data-sin-resultados <?= empty($aprendices) ? '' : 'hidden' ?>><td colspan="6" class="celda-vacia"><div><?= empty($aprendices) ? 'La ficha no tiene aprendices matriculados.' : 'Ningún aprendiz coincide con el filtro.' ?></div></td></tr>
            </tbody>
          </table>
        </div>
        <p class="small text-muted mt-2 mb-0">Semáforo: crítico con menos del 60 % de los RAP evaluados en A o más de 2 en D; riesgo con menos del 80 % o algún RAP en D.</p>
      </div>
    </div>
  </div>
</div>
