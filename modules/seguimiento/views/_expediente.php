<?php
// Expediente de un aprendiz. Se incluye desde index.view.php; espera
// $resumen, $competencias, $retros, $conceptos, $tipos, $gestiona, $fecha, $pct, $url.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Support\Semaforo;

$r = $resumen;
$desertado = $r['estado'] === 'desertado';
?>
<article class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start gap-3">
      <div class="avatar lg" style="background: <?= e($r['avatar_color'] ?: '#39A900') ?>"><?= e(getInitials($r['nombre'])) ?></div>
      <div class="flex-grow-1 min-w-0">
        <h2 class="h5 fw-bold mb-0 text-break"><?= e($r['nombre']) ?></h2>
        <div class="small text-muted"><?= e($r['tipo_documento'] . ' ' . $r['numero_documento']) ?> · Ficha <?= e($r['numero_ficha']) ?> · <?= e(ucfirst(str_replace('_', ' ', $r['estado']))) ?></div>
        <div class="small text-muted">Líder: <?= e($r['lider'] ?? '—') ?><?= $r['instructor_seguimiento'] ? ' · Seguimiento: ' . e($r['instructor_seguimiento']) : '' ?></div>
      </div>
      <span class="badge-soft <?= e(Semaforo::clase($r['semaforo'])) ?> fs-6"><?= e(Semaforo::etiqueta($r['semaforo'])) ?></span>
    </div>
    <div class="row g-2 mt-2 text-center">
      <div class="col-6 col-sm-3"><div class="panel-cifras"><div class="fw-bold fs-5 text-success"><?= (int)$r['aprobados'] ?></div><div class="small text-muted">A</div></div></div>
      <div class="col-6 col-sm-3"><div class="panel-cifras"><div class="fw-bold fs-5 text-danger"><?= (int)$r['en_d'] ?></div><div class="small text-muted">D</div></div></div>
      <div class="col-6 col-sm-3"><div class="panel-cifras"><div class="fw-bold fs-5"><?= (int)$r['pendientes'] ?></div><div class="small text-muted">Pend.</div></div></div>
      <div class="col-6 col-sm-3"><div class="panel-cifras"><div class="fw-bold fs-5"><?= e($pct($r['pct_a'])) ?></div><div class="small text-muted">Desempeño</div></div></div>
    </div>
    <div class="progress barra-avance mt-3" role="progressbar" aria-label="Avance de RAP" aria-valuenow="<?= (int)round($r['avance']) ?>" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-bar bg-success" style="width: <?= (int)round($r['avance']) ?>%"></div>
    </div>
    <div class="small text-muted mt-1">Avance: <?= e($pct($r['avance'])) ?> de los RAP del programa aprobados<?= (int)$r['planes_vigentes'] > 0 ? ' · ' . (int)$r['planes_vigentes'] . ' plan(es) de mejoramiento vigente(s)' : '' ?></div>
    <div class="d-flex flex-wrap gap-2 mt-3">
      <?php if ($gestiona && !$desertado): ?>
        <button type="button" class="btn btn-sm btn-primary" data-modal="#modalRetro"
                data-valores="<?= datosJson(['aprendiz_id' => (int)$r['id'], 'aprendiz' => $r['nombre'], 'contenido' => '', 'evaluacion_id' => 0]) ?>">
          <i class="bi bi-chat-left-text me-1"></i>Observación</button>
      <?php endif; ?>
      <a class="btn btn-sm btn-soft" href="<?= $url('/mejoramiento' . ($gestiona ? '?search=' . rawurlencode($r['numero_documento']) : '')) ?>"><i class="bi bi-arrow-repeat me-1"></i>Planes</a>
      <a class="btn btn-sm btn-soft" href="<?= $url('/evidencias' . ($gestiona ? '?search=' . rawurlencode($r['numero_documento']) : '')) ?>"><i class="bi bi-folder2-open me-1"></i>Evidencias</a>
    </div>
  </div>
</article>

<h3 class="h6 fw-bold text-uppercase text-muted mb-2">Resultados por competencia</h3>
<?php foreach ($competencias as $i => $c): $total = count($c['raps']); ?>
<details class="card border-0 shadow-sm mb-2 competencia-expediente" <?= $c['d'] > 0 || ($i === 0 && count($competencias) <= 3) ? 'open' : '' ?>>
  <summary class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
    <span class="font-monospace small"><?= e($c['codigo']) ?></span>
    <span class="flex-grow-1 min-w-0 texto-recortado-2 small fw-semibold"><?= e($c['nombre']) ?></span>
    <?php if ($c['etapa_practica']): ?><span class="badge bg-soft info">Etapa práctica</span><?php endif; ?>
    <span class="badge-soft success">A <?= (int)$c['a'] ?>/<?= $total ?></span>
    <?php if ($c['d'] > 0): ?><span class="badge-soft danger">D <?= (int)$c['d'] ?></span><?php endif; ?>
  </summary>
  <ul class="list-group list-group-flush">
    <?php foreach ($c['raps'] as $rap): [$cTexto, $cClase] = $conceptos[$rap['concepto']] ?? [$rap['concepto'], 'secondary']; ?>
    <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1 min-w-0">
        <div class="small"><span class="font-monospace"><?= e($rap['ra_codigo']) ?></span> · <?= e($rap['ra_denominacion']) ?></div>
        <div class="small text-muted">
          <?= $rap['fecha_evaluacion'] ? e($fecha($rap['fecha_evaluacion'])) . ' · ' : '' ?>Califica: <?= e($rap['responsable'] ?? '—') ?>
          <?php if ($rap['plan_id']): ?> · <span class="text-warning-emphasis"><i class="bi bi-arrow-repeat"></i> Plan hasta <?= e($fecha($rap['plan_limite'])) ?></span><?php endif; ?>
        </div>
        <?php if (trim((string)$rap['comentario']) !== ''): ?><div class="small fst-italic text-break">«<?= e($rap['comentario']) ?>»</div><?php endif; ?>
      </div>
      <span class="badge-soft <?= e($cClase) ?> text-nowrap"><?= e($cTexto) ?></span>
      <?php if ($gestiona && $rap['puede_calificar'] && !$desertado): ?>
        <button type="button" class="btn btn-sm btn-soft" data-modal="#modalEvaluar" aria-label="Calificar <?= e($rap['ra_codigo']) ?>"
                data-valores="<?= datosJson(['_anterior' => $rap['concepto'], 'evaluacion_id' => (int)$rap['evaluacion_id'], 'concepto' => $rap['concepto'],
                    'comentario' => (string)($rap['comentario'] ?? ''), 'motivo' => '', 'ra' => $rap['ra_codigo'] . ' · ' . $rap['ra_denominacion'],
                    'aprendiz' => $r['nombre']]) ?>"><i class="bi bi-pencil-square"></i></button>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</details>
<?php endforeach; ?>
<?php if (empty($competencias)): ?>
  <div class="estado-vacio"><i class="bi bi-list-check"></i>El programa de la ficha aún no tiene resultados de aprendizaje.</div>
<?php endif; ?>

<h3 class="h6 fw-bold text-uppercase text-muted mt-4 mb-2">Retroalimentación</h3>
<?php foreach ($retros as $rt): [$tTexto, $tClase, $tIcono] = $tipos[$rt['tipo']] ?? [$rt['tipo'], 'secondary', 'bi-chat']; ?>
  <div class="card border-0 shadow-sm mb-2 tarjeta-lateral lateral-<?= e($tClase) ?>">
    <div class="card-body py-2">
      <div class="d-flex flex-wrap gap-2 align-items-center small mb-1">
        <span class="badge-soft <?= e($tClase) ?>"><i class="bi <?= e($tIcono) ?> me-1"></i><?= e($tTexto) ?></span>
        <?php if ((int)$rt['privada'] === 1): ?><span class="badge-soft secondary"><i class="bi bi-lock me-1"></i>Privada</span><?php endif; ?>
        <?php if ($rt['ra_codigo']): ?><span class="font-monospace text-muted"><?= e($rt['ra_codigo']) ?></span><?php endif; ?>
        <span class="text-muted ms-auto"><?= e($rt['instructor_nombre']) ?> · <?= e($fecha($rt['fecha_creacion'])) ?></span>
      </div>
      <div class="small text-break texto-multilinea"><?= e($rt['contenido']) ?></div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (empty($retros)): ?>
  <p class="text-muted small">Sin retroalimentación todavía.</p>
<?php endif; ?>
