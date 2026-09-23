<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Formularios\PlanFormulario;

/** @var \Core\Support\Actor $actor */
$gestiona = $actor->gestiona();
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$fecha = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$hayFiltros = $filtros['search'] !== '' || $filtros['estado'] !== '' || $filtros['ficha_id'];
$qs = http_build_query(array_filter($filtros, static fn($x) => $x !== '' && $x !== 0));
$hoy = date('Y-m-d');
$sugerida = date('Y-m-d', strtotime('+15 days'));
$maxLimite = date('Y-m-d', strtotime('+' . PlanFormulario::MAX_DIAS . ' days'));
?>
<div class="page-header">
  <div>
    <h1 class="mb-1"><?= $actor->esAprendiz() ? 'Mis planes de mejoramiento' : 'Planes de mejoramiento' ?></h1>
    <p class="text-muted mb-0"><?= $actor->esAprendiz()
        ? 'Lo que debes hacer para nivelar los resultados de aprendizaje que están en D, y hasta cuándo.'
        : 'Nivelación de los RAP en D: se abre un plan, el aprendiz entrega evidencias y al cerrarlo como cumplido el RAP pasa a A.' ?></p>
  </div>
  <a href="<?= $url('/mejoramiento/exportar' . ($qs ? '?' . $qs . '&' : '?') . 'formato=xlsx') ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
  <?php foreach (array_filter([
      ['Vigentes', $cifras['vigentes'], 'bi-hourglass-split', '', 'vigente'],
      ['Vencidos', $cifras['vencidos'], 'bi-alarm', $cifras['vencidos'] > 0 ? 'text-danger' : '', 'vencido'],
      ['Cumplidos', $cifras['cumplidos'], 'bi-patch-check', 'text-success', 'cumplido'],
      $gestiona ? ['RAP en D sin plan', $cifras['sin_plan'], 'bi-exclamation-diamond', $cifras['sin_plan'] > 0 ? 'text-warning-emphasis' : '', ''] : ['No cumplidos', $cifras['no_cumplidos'], 'bi-x-octagon', '', 'no_cumplido'],
  ]) as [$etiqueta, $valor, $icono, $clase, $filtro]): ?>
  <div class="col-6 col-lg-3">
    <a class="text-reset text-decoration-none" href="<?= $filtro !== '' ? $url('/mejoramiento?estado=' . $filtro) : '#sin-plan' ?>">
      <div class="kpi"><div class="kpi-content">
        <div class="icon-bg"><i class="bi <?= e($icono) ?>"></i></div>
        <div class="label"><?= e($etiqueta) ?></div>
        <div class="value <?= e($clase) ?>"><?= (int)$valor ?></div>
      </div></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<form method="GET" class="toolbar mb-3">
  <div class="search">
    <i class="bi bi-search"></i>
    <label class="visually-hidden" for="f_search">Buscar</label>
    <input type="search" name="search" id="f_search" class="form-control" maxlength="100"
           placeholder="<?= $gestiona ? 'Aprendiz, documento o RAP...' : 'Código o nombre del RAP...' ?>" value="<?= e($filtros['search']) ?>">
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_estado">Estado</label>
    <select name="estado" id="f_estado" class="form-select" data-autoenvio>
      <option value="">Todos los planes</option>
      <option value="vigente" <?= $filtros['estado'] === 'vigente' ? 'selected' : '' ?>>Vigentes</option>
      <option value="vencido" <?= $filtros['estado'] === 'vencido' ? 'selected' : '' ?>>Vencidos</option>
      <?php foreach ($estados as $valor => [$texto]): ?><option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php if ($gestiona): ?>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_ficha">Ficha</label>
    <select name="ficha_id" id="f_ficha" class="form-select" data-autoenvio data-picker data-picker-label="Ficha">
      <option value="0">Todas las fichas</option>
      <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $filtros['ficha_id'] === (int)$f['id'] ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <button type="submit" class="btn btn-soft"><i class="bi bi-funnel me-1"></i>Filtrar</button>
  <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= $url('/mejoramiento') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
</form>

<div class="row g-3 mb-4">
  <?php foreach ($planes as $p):
      [$eTexto, $eClase] = $estados[$p['estado']] ?? [$p['estado'], 'secondary'];
      $vigente = in_array($p['estado'], ['abierto', 'en_curso'], true);
      $dias = (int)$p['dias_restantes'];
  ?>
  <div class="col-md-6 col-xl-4">
    <article class="card h-100 tarjeta-elevable <?= (int)$p['vencido'] ? 'border-danger' : '' ?>">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
          <div class="min-w-0">
            <div class="fw-bold font-monospace"><?= e($p['ra_codigo']) ?></div>
            <div class="small text-muted texto-recortado-2" title="<?= e($p['ra_denominacion']) ?>"><?= e($p['ra_denominacion']) ?></div>
          </div>
          <span class="badge-soft <?= e($eClase) ?> text-nowrap"><?= e($eTexto) ?></span>
        </div>
        <?php if ($gestiona): ?>
          <div class="small mb-1"><i class="bi bi-person me-1"></i><?= e($p['aprendiz_nombre']) ?> · Ficha <?= e($p['numero_ficha']) ?></div>
        <?php endif; ?>
        <div class="small mb-2">
          <i class="bi bi-calendar-event me-1"></i><?= e($fecha($p['fecha_inicio'])) ?> → <strong><?= e($fecha($p['fecha_limite'])) ?></strong>
          <?php if ($vigente): ?>
            <span class="badge-soft <?= (int)$p['vencido'] ? 'danger' : ($dias <= 3 ? 'warning' : 'secondary') ?> ms-1">
              <?= (int)$p['vencido'] ? 'Vencido hace ' . abs($dias) . ' d' : ($dias === 0 ? 'Vence hoy' : "Faltan $dias d") ?></span>
          <?php endif; ?>
        </div>
        <div class="panel-cifras small mb-2"><strong class="d-block mb-1">Actividades</strong><span class="text-break texto-multilinea"><?= e($p['actividades']) ?></span></div>
        <?php if (!$vigente && $p['observaciones_cierre']): ?>
          <div class="small text-muted mb-2"><strong>Cierre (<?= e($fecha($p['fecha_cierre'])) ?>):</strong> <?= e($p['observaciones_cierre']) ?></div>
        <?php endif; ?>
        <div class="small text-muted mt-auto mb-2">Responsable: <?= e($p['instructor_nombre']) ?></div>
        <?php if ($gestiona && $vigente): ?>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-primary flex-grow-1" data-modal="#modalCerrar"
                  data-valores="<?= datosJson(['id' => (int)$p['id'], 'resultado' => '', 'observaciones' => '', 'resumen' => $p['ra_codigo'] . ' · ' . ($p['aprendiz_nombre'] ?? '')]) ?>">
            <i class="bi bi-flag me-1"></i>Cerrar plan</button>
          <button type="button" class="btn btn-sm btn-soft" data-modal="#modalEditar" aria-label="Editar plan"
                  data-valores="<?= datosJson(['id' => (int)$p['id'], 'actividades' => $p['actividades'], 'fecha_limite' => $p['fecha_limite'],
                      'resumen' => $p['ra_codigo'] . ' · ' . ($p['aprendiz_nombre'] ?? '')]) ?>"><i class="bi bi-pencil"></i></button>
        </div>
        <?php elseif ($actor->esAprendiz() && $vigente): ?>
          <a class="btn btn-sm btn-soft" href="<?= $url('/evidencias') ?>"><i class="bi bi-cloud-upload me-1"></i>Entregar evidencia de este RAP</a>
        <?php endif; ?>
      </div>
    </article>
  </div>
  <?php endforeach; ?>
  <?php if (empty($planes)): ?>
    <div class="col-12 estado-vacio"><i class="bi bi-patch-check"></i><?= $hayFiltros ? 'Ningún plan coincide con los filtros.' : ($actor->esAprendiz() ? 'No tienes planes de mejoramiento.' : 'No hay planes de mejoramiento registrados.') ?></div>
  <?php endif; ?>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'planes'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($gestiona): ?>
<section id="sin-plan" class="mt-4">
  <h2 class="h5 fw-bold mb-1">RAP en D sin plan</h2>
  <p class="text-muted small mb-3">Resultados no aprobados que todavía no tienen un plan vigente<?php $totalSin = (int)($cifras['sin_plan_filtrados'] ?? $cifras['sin_plan']); ?><?= $totalSin > count($sinPlan) ? ' (se muestran los ' . count($sinPlan) . ' más antiguos de ' . $totalSin . '; usa el buscador o el filtro de ficha para acotar)' : '' ?>.</p>
  <div class="list-group">
    <?php foreach ($sinPlan as $s): ?>
    <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold"><?= e($s['aprendiz_nombre']) ?> <small class="text-muted fw-normal">· Ficha <?= e($s['numero_ficha']) ?></small></div>
        <div class="small text-truncate"><span class="font-monospace"><?= e($s['ra_codigo']) ?></span> · <span class="text-muted" title="<?= e($s['ra_denominacion']) ?>"><?= e($s['ra_denominacion']) ?></span></div>
        <div class="small text-muted">En D desde <?= e($fecha($s['fecha_evaluacion'])) ?></div>
      </div>
      <button type="button" class="btn btn-sm btn-primary text-nowrap" data-modal="#modalCrear"
              data-valores="<?= datosJson(['evaluacion_id' => (int)$s['evaluacion_id'], 'actividades' => '', 'fecha_inicio' => $hoy, 'fecha_limite' => $sugerida,
                  'resumen' => $s['ra_codigo'] . ' · ' . $s['aprendiz_nombre'], 'observacion' => (string)($s['comentario'] ?? '')]) ?>">
        <i class="bi bi-plus-lg me-1"></i>Crear plan</button>
    </div>
    <?php endforeach; ?>
    <?php if (empty($sinPlan)): ?>
      <div class="list-group-item estado-vacio"><i class="bi bi-check2-circle"></i><?= $filtros['search'] !== '' || $filtros['ficha_id'] ? 'Ningún RAP en D sin plan coincide con los filtros.' : 'Todos los RAP en D tienen un plan vigente.' ?></div>
    <?php endif; ?>
  </div>
</section>

<div class="modal fade" id="modalCrear" tabindex="-1" aria-labelledby="tituloCrear" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="crear">
        <input type="hidden" name="evaluacion_id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloCrear"><i class="bi bi-clipboard-plus"></i>Nuevo plan de mejoramiento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-1" data-campo="resumen"></p>
          <p class="small text-muted mb-3" data-campo="observacion"></p>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="plan_act">Actividades <span class="text-danger">*</span></label>
            <textarea name="actividades" id="plan_act" class="form-control" rows="4" required minlength="10" maxlength="<?= PlanFormulario::MAX_ACTIVIDADES ?>" data-filtro="sin-html"
                      placeholder="Qué debe hacer y entregar el aprendiz para nivelar el RAP..."></textarea>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label fw-semibold" for="plan_ini">Inicio</label>
              <input type="date" name="fecha_inicio" id="plan_ini" class="form-control" value="<?= e($hoy) ?>">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold" for="plan_lim">Fecha límite <span class="text-danger">*</span></label>
              <input type="date" name="fecha_limite" id="plan_lim" class="form-control" required min="<?= e($hoy) ?>" max="<?= e($maxLimite) ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear plan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="tituloEditar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloEditar"><i class="bi bi-pencil"></i>Editar plan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-3" data-campo="resumen"></p>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ed_act">Actividades <span class="text-danger">*</span></label>
            <textarea name="actividades" id="ed_act" class="form-control" rows="4" required minlength="10" maxlength="<?= PlanFormulario::MAX_ACTIVIDADES ?>" data-filtro="sin-html"></textarea>
          </div>
          <label class="form-label fw-semibold" for="ed_lim">Fecha límite <span class="text-danger">*</span></label>
          <input type="date" name="fecha_limite" id="ed_lim" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCerrar" tabindex="-1" aria-labelledby="tituloCerrar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cerrar">
        <input type="hidden" name="id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloCerrar"><i class="bi bi-flag"></i>Cerrar plan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-3" data-campo="resumen"></p>
          <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Resultado <span class="text-danger">*</span></legend>
            <div class="d-flex gap-2">
              <input type="radio" class="btn-check" name="resultado" value="cumplido" id="cierre_ok" required>
              <label class="btn btn-outline-success flex-grow-1" for="cierre_ok"><i class="bi bi-check-circle me-1"></i>Cumplido (RAP a A)</label>
              <input type="radio" class="btn-check" name="resultado" value="no_cumplido" id="cierre_no">
              <label class="btn btn-outline-danger flex-grow-1" for="cierre_no"><i class="bi bi-x-circle me-1"></i>No cumplido</label>
            </div>
          </fieldset>
          <label class="form-label fw-semibold" for="cierre_obs">Observaciones <span class="text-danger">*</span></label>
          <textarea name="observaciones" id="cierre_obs" class="form-control" rows="3" required minlength="5" maxlength="<?= PlanFormulario::MAX_OBSERVACIONES ?>" data-filtro="sin-html"
                    placeholder="Qué evidenció el aprendiz; quedará en el historial del juicio."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Cerrar plan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
