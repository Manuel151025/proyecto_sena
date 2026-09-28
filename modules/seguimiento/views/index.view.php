<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Support\Semaforo;

/** @var \Core\Support\Actor $actor */
$gestiona = $actor->gestiona();
if ($gestiona) {
    $scriptsVista[] = 'modulos/evaluaciones.js';
}
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$fecha = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$pct = static fn($v) => $v === null ? '—' : ((int)round((float)$v)) . '%';
$enlaceAprendiz = static fn(int $id) => $url('/seguimiento?ficha_id=' . $fichaId . '&aprendiz_id=' . $id . ($semaforo !== '' ? '&semaforo=' . $semaforo : '') . '#expediente');
?>
<div class="page-header">
  <div>
    <h1 class="mb-1"><?= $actor->esAprendiz() ? 'Mi seguimiento' : 'Seguimiento académico' ?></h1>
    <p class="text-muted mb-0"><?= $actor->esAprendiz()
        ? 'Tus resultados de aprendizaje por competencia, tus planes de mejoramiento y lo que te dicen tus instructores.'
        : 'Elige una ficha y un aprendiz para ver su expediente: juicios por competencia, planes y observaciones.' ?></p>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<?php if ($gestiona): ?>
  <form method="GET" class="toolbar mb-3">
    <div class="toolbar-filter flex-grow-1">
      <label class="visually-hidden" for="f_ficha">Ficha</label>
      <select name="ficha_id" id="f_ficha" class="form-select" data-autoenvio data-picker data-picker-label="Ficha">
        <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $fichaId === (int)$f['id'] ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="toolbar-filter">
      <label class="visually-hidden" for="f_sem">Semáforo</label>
      <select name="semaforo" id="f_sem" class="form-select" data-autoenvio>
        <option value="">Todos los aprendices</option>
        <?php foreach ([Semaforo::CRITICO, Semaforo::RIESGO, Semaforo::AL_DIA, Semaforo::SIN_DATOS] as $s): ?>
          <option value="<?= e($s) ?>" <?= $semaforo === $s ? 'selected' : '' ?>><?= e(Semaforo::etiqueta($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-soft"><i class="bi bi-funnel me-1"></i>Ver</button>
  </form>

  <?php if ($fichaId === 0): ?>
    <div class="estado-vacio"><i class="bi bi-folder2-open"></i>No tienes fichas a cargo.</div>
  <?php else: ?>
    <?php if ($ficha): ?>
    <div class="d-flex flex-wrap gap-2 mb-3 small">
      <span class="badge-soft primary">Ficha <?= e($ficha['numero_ficha']) ?> · <?= e($ficha['programa']) ?></span>
      <span class="badge-soft secondary"><?= (int)$ficha['aprendices_activos'] ?> aprendices</span>
      <span class="badge-soft <?= e(Semaforo::clase($ficha['semaforo'])) ?>">Desempeño <?= e($pct($ficha['pct_a'])) ?> · <?= e(Semaforo::etiqueta($ficha['semaforo'])) ?></span>
      <span class="badge-soft secondary">Avance de RAP <?= e($pct($ficha['cumplimiento'])) ?></span>
      <a class="ms-auto" href="<?= $url('/fichas/ver?id=' . $fichaId) ?>">Ver ficha</a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-4" id="lista-aprendices">
        <div class="list-group lista-aprendices">
          <?php foreach ($aprendices as $a): $activo = (int)$a['id'] === $aprendizId; ?>
            <a href="<?= $enlaceAprendiz((int)$a['id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= $activo ? 'active' : '' ?>" <?= $activo ? 'aria-current="true"' : '' ?>>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?= e($a['nombre']) ?></div>
                <small class="<?= $activo ? '' : 'text-muted' ?>">A <?= (int)$a['aprobados'] ?> · D <?= (int)$a['en_d'] ?> · Pend. <?= (int)$a['pendientes'] ?><?= $a['estado'] === 'desertado' ? ' · Desertado' : '' ?></small>
              </div>
              <span class="badge-soft <?= e(Semaforo::clase($a['semaforo'])) ?> text-nowrap"><?= e($pct($a['pct_a'])) ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (empty($aprendices)): ?>
            <div class="list-group-item estado-vacio"><i class="bi bi-people"></i><?= $semaforo !== '' ? 'Ningún aprendiz en ese semáforo.' : 'La ficha no tiene aprendices.' ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php // En el móvil, con un aprendiz elegido, su expediente va primero y la lista después. ?>
      <div class="col-lg-8 <?= $resumen !== null ? 'order-first order-lg-last' : '' ?>" id="expediente">
        <?php if ($resumen !== null): ?>
          <a class="d-lg-none small d-inline-block mb-2" href="#lista-aprendices"><i class="bi bi-people me-1"></i>Cambiar de aprendiz</a>
        <?php endif; ?>
        <?php if ($resumen === null): ?>
          <div class="estado-vacio"><i class="bi bi-person-lines-fill"></i>Elige un aprendiz para ver su expediente.</div>
        <?php else: require __DIR__ . '/_expediente.php'; endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php
    require BASE_PATH . 'components/modal_juicio.php';
    $aprendicesModal = null;
    $accionRetro = 'observar';
    require BASE_PATH . 'components/modal_retroalimentacion.php';
  ?>
<?php elseif ($resumen !== null): ?>
  <?php require __DIR__ . '/_expediente.php'; ?>
<?php endif; ?>
