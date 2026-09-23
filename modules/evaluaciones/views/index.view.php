<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
/** @var \Core\Support\Actor $actor */
$gestiona = $actor->gestiona();
$scriptsVista[] = 'modulos/evaluaciones.js';
$hayFiltros = $filtros['search'] !== '' || $filtros['ficha_id'] || $filtros['concepto'] !== '';
$qs = http_build_query(array_filter($filtros, static fn($x) => $x !== '' && $x !== 0));
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$fechaCorta = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$evaluados = $cifras['a'] + $cifras['d'];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1"><?= $actor->esAprendiz() ? 'Mis juicios de evaluación' : 'Juicios de evaluación' ?></h1>
    <p class="text-muted mb-0">
      <?= $actor->esAprendiz()
          ? 'El estado de cada resultado de aprendizaje de tu programa: A (aprobado), D (aún no aprobado) o pendiente.'
          : 'Juicios por resultado de aprendizaje (RAP). Los conceptos son A (aprobado) y D (aún no aprobado); todo cambio queda en el historial.' ?>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= $url('/evaluaciones/exportar' . ($qs ? '?' . $qs . '&' : '?') . 'formato=xlsx') ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
    <?php if ($gestiona): ?>
      <a href="<?= $url('/evaluaciones/importar') ?>" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar de Sofia Plus</a>
    <?php endif; ?>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['Resultados', $cifras['total'], 'bi-list-check', ''],
      ['Aprobados (A)', $cifras['a'], 'bi-check-circle', 'text-success'],
      ['No aprobados (D)', $cifras['d'], 'bi-x-circle', 'text-danger'],
      ['Pendientes', $cifras['pendientes'], 'bi-clock', 'text-warning-emphasis'],
  ] as [$etiqueta, $valor, $icono, $clase]): ?>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="kpi-content">
      <div class="icon-bg"><i class="bi <?= e($icono) ?>"></i></div>
      <div class="label"><?= e($etiqueta) ?></div>
      <div class="value <?= e($clase) ?>"><?= (int)$valor ?></div>
      <?php if ($etiqueta === 'Aprobados (A)' && $evaluados > 0): ?>
        <div class="small text-muted"><?= (int)round($cifras['a'] * 100 / $evaluados) ?>% de lo evaluado</div>
      <?php endif; ?>
    </div></div>
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
  <?php if ($gestiona): ?>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_ficha">Ficha</label>
    <select name="ficha_id" id="f_ficha" class="form-select" data-autoenvio data-picker data-picker-label="Ficha">
      <option value="0">Todas las fichas</option>
      <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $filtros['ficha_id'] === (int)$f['id'] ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_concepto">Concepto</label>
    <select name="concepto" id="f_concepto" class="form-select" data-autoenvio>
      <option value="">Todos los conceptos</option>
      <?php foreach ($conceptos as $valor => [$texto]): ?><option value="<?= e($valor) ?>" <?= $filtros['concepto'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option><?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-soft"><i class="bi bi-funnel me-1"></i>Filtrar</button>
  <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= $url('/evaluaciones') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
</form>

<div class="table-wrap">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th>Resultado de aprendizaje</th>
        <?php if ($gestiona): ?><th>Aprendiz</th><?php endif; ?>
        <th>Competencia</th>
        <th>Fecha</th>
        <th>Juicio</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($evaluaciones as $ev):
          [$cTexto, $cClase, $cIcono] = $conceptos[$ev['concepto']] ?? [$ev['concepto'], 'secondary', 'bi-question'];
          $hist = array_map(static fn($h) => [
              'fecha' => date('d/m/Y H:i', strtotime((string)$h['fecha_cambio'])), 'de' => $h['concepto_anterior'], 'a' => $h['concepto_nuevo'],
              'quien' => (string)($h['usuario'] ?? '—'), 'motivo' => (string)($h['motivo'] ?? ''),
          ], $historial[(int)$ev['id']] ?? []);
          $desertado = $ev['aprendiz_estado'] === 'desertado';
      ?>
      <tr>
        <td class="min-w-0">
          <div class="fw-semibold font-monospace small"><?= e($ev['ra_codigo']) ?></div>
          <div class="small text-muted texto-recortado-2" title="<?= e($ev['ra_denominacion']) ?>"><?= e($ev['ra_denominacion']) ?></div>
          <span class="badge bg-soft primary">Ficha <?= e($ev['numero_ficha']) ?></span>
          <?php if ((int)$ev['es_etapa_practica'] === 1): ?><span class="badge bg-soft info">Etapa práctica</span><?php endif; ?>
        </td>
        <?php if ($gestiona): ?>
        <td>
          <div class="fw-semibold"><?= e($ev['aprendiz_nombre']) ?></div>
          <small class="text-muted"><?= e($ev['numero_documento']) ?></small>
          <?php if ($desertado): ?><span class="badge-soft danger ms-1">Desertado</span><?php endif; ?>
        </td>
        <?php endif; ?>
        <td><small class="text-muted texto-recortado-2" title="<?= e($ev['competencia_nombre']) ?>"><?= e($ev['competencia_codigo']) ?> · <?= e($ev['competencia_nombre']) ?></small></td>
        <td class="small text-nowrap"><?= e($fechaCorta($ev['fecha_evaluacion'])) ?></td>
        <td><span class="badge-soft <?= e($cClase) ?> text-nowrap"><i class="bi <?= e($cIcono) ?> me-1"></i><?= e($cTexto) ?></span></td>
        <td class="text-end text-nowrap">
          <?php if ($gestiona && !$desertado): ?>
            <button type="button" class="btn btn-sm btn-primary" data-modal="#modalEvaluar"
                    data-valores="<?= datosJson(['_anterior' => $ev['concepto'], 'evaluacion_id' => (int)$ev['id'], 'concepto' => $ev['concepto'],
                        'comentario' => (string)($ev['comentario'] ?? ''), 'motivo' => '', 'ra' => $ev['ra_codigo'] . ' · ' . $ev['ra_denominacion'],
                        'aprendiz' => $ev['aprendiz_nombre']]) ?>"><i class="bi bi-pencil-square me-1"></i>Calificar</button>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-soft" data-modal="#modalDetalle" aria-label="Retroalimentación e historial"
                  data-historial="<?= datosJson($hist) ?>"
                  data-valores="<?= datosJson(['ra' => $ev['ra_codigo'] . ' · ' . $ev['ra_denominacion'], 'aprendiz' => $ev['aprendiz_nombre'],
                      'juicio' => $cTexto, 'instructor' => (string)($ev['instructor_nombre'] ?? '—'),
                      'comentario' => trim((string)($ev['comentario'] ?? '')) !== '' ? $ev['comentario'] : 'Sin comentarios.']) ?>">
            <i class="bi bi-chat-left-text"></i><?php if ($hist !== []): ?><span class="ms-1 small"><?= count($hist) ?></span><?php endif; ?>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($evaluaciones)): ?>
      <tr><td colspan="6" class="celda-vacia"><div><i class="bi bi-clipboard-check"></i><?= $hayFiltros ? 'Ningún juicio coincide con los filtros.' : 'No hay juicios para mostrar.' ?></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'juicios'; require BASE_PATH . 'components/paginacion.php'; ?>

<!-- Detalle: retroalimentación e historial (RNF02) -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="tituloDetalle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloDetalle"><i class="bi bi-chat-left-text"></i>Detalle del juicio</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="fw-semibold mb-1" data-campo="ra"></p>
        <p class="small text-muted mb-3"><span data-campo="aprendiz"></span> · <span data-campo="juicio"></span> · <span data-campo="instructor"></span></p>
        <h6 class="small text-uppercase text-muted fw-bold">Retroalimentación</h6>
        <p class="text-break" data-campo="comentario"></p>
        <h6 class="small text-uppercase text-muted fw-bold mt-3">Historial de cambios</h6>
        <ol class="list-unstyled small mb-0" data-lista-historial></ol>
      </div>
    </div>
  </div>
</div>

<?php if ($gestiona): ?>
<div class="modal fade" id="modalEvaluar" tabindex="-1" aria-labelledby="tituloEvaluar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" data-form-juicio>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="evaluar">
        <input type="hidden" name="evaluacion_id">
        <input type="hidden" name="_anterior" data-anterior>
        <div class="modal-header">
          <h5 class="modal-title" id="tituloEvaluar"><i class="bi bi-clipboard-check"></i>Juicio evaluativo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-1" data-campo="ra"></p>
          <p class="small text-muted mb-3" data-campo="aprendiz"></p>
          <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Concepto <span class="text-danger">*</span></legend>
            <div class="d-flex gap-2">
              <input type="radio" class="btn-check" name="concepto" value="A" id="juicioA" required>
              <label class="btn btn-outline-success flex-grow-1" for="juicioA"><i class="bi bi-check-circle-fill me-1"></i>Aprobado (A)</label>
              <input type="radio" class="btn-check" name="concepto" value="D" id="juicioD">
              <label class="btn btn-outline-danger flex-grow-1" for="juicioD"><i class="bi bi-x-circle-fill me-1"></i>No aprobado (D)</label>
            </div>
          </fieldset>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="juicioComentario">Retroalimentación para el aprendiz</label>
            <textarea name="comentario" id="juicioComentario" class="form-control" rows="3" maxlength="1000" data-filtro="sin-html"
                      placeholder="Qué hizo bien o qué debe mejorar..."></textarea>
          </div>
          <div class="mb-0" data-bloque-motivo hidden>
            <label class="form-label fw-semibold" for="juicioMotivo">Motivo del cambio <span class="text-danger">*</span></label>
            <input type="text" name="motivo" id="juicioMotivo" class="form-control" maxlength="255" minlength="5" data-filtro="sin-html"
                   placeholder="Ej.: cumplió el plan de mejoramiento">
            <small class="text-muted">El juicio ya estaba emitido: el cambio y su motivo quedan en el historial.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
