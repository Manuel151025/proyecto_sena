<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
use Core\Formularios\EvidenciaFormulario;

/** @var \Core\Support\Actor $actor */
$gestiona = $actor->gestiona();
$esAprendiz = $actor->esAprendiz();
if ($esAprendiz) {
    $scriptsVista[] = 'modulos/zona-archivos.js';
}
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$hayFiltros = $filtros['search'] !== '' || $filtros['estado'] !== '' || $filtros['ficha_id'];
$conceptoTexto = ['A' => 'A', 'D' => 'D', 'pendiente' => 'Pendiente'];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1"><?= $esAprendiz ? 'Mis evidencias' : 'Evidencias' ?></h1>
    <p class="text-muted mb-0"><?= $esAprendiz
        ? 'Envía tus productos y consulta la retroalimentación de tus instructores.'
        : 'Revisa y retroalimenta las evidencias de tus aprendices; si están ligadas a un RAP, puedes registrar el juicio.' ?></p>
  </div>
  <?php if ($esAprendiz): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEnviar"><i class="bi bi-cloud-upload me-1"></i>Enviar evidencia</button>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<?php if ($gestiona && $porRevisar > 0 && $filtros['estado'] !== 'enviada'): ?>
<div class="alert-flat info mb-3"><i class="bi bi-inbox"></i>
  <div>Tienes <strong><?= (int)$porRevisar ?></strong> evidencia(s) por revisar. <a href="<?= $url('/evidencias?estado=enviada') ?>">Verlas</a></div>
</div>
<?php endif; ?>

<form method="GET" class="toolbar mb-3">
  <div class="search">
    <i class="bi bi-search"></i>
    <label class="visually-hidden" for="f_search">Buscar</label>
    <input type="search" name="search" id="f_search" class="form-control" maxlength="100"
           placeholder="<?= $gestiona ? 'Título, aprendiz, documento o RAP...' : 'Título o RAP...' ?>" value="<?= e($filtros['search']) ?>">
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_estado">Estado</label>
    <select name="estado" id="f_estado" class="form-select" data-autoenvio>
      <option value="">Todos los estados</option>
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
  <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= $url('/evidencias') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
</form>

<div class="row g-3">
  <?php foreach ($evidencias as $ev):
      [$eTexto, $eClase] = $estados[$ev['estado']] ?? [$ev['estado'], 'secondary'];
      $puedeRetirar = ($esAprendiz && $ev['estado'] === 'enviada') || $actor->esCoordinador();
  ?>
  <div class="col-md-6 col-xl-4">
    <article class="card h-100 tarjeta-elevable">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
          <h2 class="h6 fw-bold mb-0 text-break"><?= e($ev['titulo']) ?></h2>
          <span class="badge-soft <?= e($eClase) ?> text-nowrap"><?= e($eTexto) ?></span>
        </div>
        <?php if ($gestiona): ?>
          <div class="small"><i class="bi bi-person me-1"></i><?= e($ev['aprendiz_nombre']) ?> · Ficha <?= e($ev['numero_ficha']) ?></div>
        <?php endif; ?>
        <div class="small text-muted mb-2"><i class="bi bi-calendar3 me-1"></i><?= e(date('d/m/Y H:i', strtotime((string)$ev['fecha_envio']))) ?>
          <?php if ($ev['ra_codigo']): ?> · <span class="font-monospace"><?= e($ev['ra_codigo']) ?></span>
            <span class="badge bg-soft primary">Juicio: <?= e($conceptoTexto[$ev['concepto']] ?? '—') ?></span><?php endif; ?></div>
        <?php if ($ev['descripcion']): ?><p class="small texto-recortado-2 mb-2"><?= e($ev['descripcion']) ?></p><?php endif; ?>
        <?php if ($ev['retroalimentacion']): ?>
          <div class="panel-cifras small mb-2"><strong class="d-block mb-1"><i class="bi bi-chat-left-quote me-1"></i>Retroalimentación</strong><span class="text-break"><?= e($ev['retroalimentacion']) ?></span></div>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-auto flex-wrap">
          <?php if ($ev['archivo_url']): ?>
            <a class="btn btn-sm btn-soft" href="<?= $url('/evidencias/archivo?id=' . (int)$ev['id']) ?>" target="_blank" rel="noopener">
              <i class="bi bi-paperclip me-1"></i><?= e(strtoupper((string)$ev['tipo_archivo'])) ?> · <?= (int)$ev['tamano_kb'] ?> KB</a>
          <?php endif; ?>
          <?php if ($gestiona): ?>
            <button type="button" class="btn btn-sm btn-primary" data-modal="#modalRevisar"
                    data-valores="<?= datosJson(['id' => (int)$ev['id'], 'estado' => $ev['estado'] === 'enviada' ? '' : $ev['estado'],
                        'retroalimentacion' => (string)($ev['retroalimentacion'] ?? ''), 'juicio' => '',
                        'titulo' => $ev['titulo'], 'aprendiz' => $ev['aprendiz_nombre'],
                        'rap' => $ev['ra_codigo'] ? $ev['ra_codigo'] . ' · juicio actual: ' . ($conceptoTexto[$ev['concepto']] ?? '—') : 'Sin RAP ligado: no se registra juicio.']) ?>">
              <i class="bi bi-check2-square me-1"></i><?= $ev['estado'] === 'enviada' ? 'Revisar' : 'Cambiar revisión' ?></button>
          <?php endif; ?>
          <?php if ($puedeRetirar): ?>
            <form method="POST" class="d-inline ms-auto" data-confirmar="<?= e('¿Eliminar la evidencia «' . $ev['titulo'] . '»?') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Eliminar evidencia"><i class="bi bi-trash"></i></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </article>
  </div>
  <?php endforeach; ?>
  <?php if (empty($evidencias)): ?>
    <div class="col-12 estado-vacio"><i class="bi bi-inbox"></i><?= $hayFiltros ? 'Ninguna evidencia coincide con los filtros.' : ($esAprendiz ? 'Aún no has enviado evidencias.' : 'No hay evidencias para mostrar.') ?></div>
  <?php endif; ?>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'evidencias'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($esAprendiz): ?>
<div class="modal fade" id="modalEnviar" tabindex="-1" aria-labelledby="tituloEnviar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="enviar">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloEnviar"><i class="bi bi-cloud-upload"></i>Enviar evidencia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ev_titulo">Título <span class="text-danger">*</span></label>
            <input type="text" name="titulo" id="ev_titulo" class="form-control" required minlength="3" maxlength="<?= EvidenciaFormulario::MAX_TITULO ?>" data-filtro="nombre"
                   placeholder="Ej.: Diagrama de casos de uso">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ev_rap">Resultado de aprendizaje</label>
            <select name="evaluacion_id" id="ev_rap" class="form-select" data-picker data-picker-label="Resultado de aprendizaje" data-picker-placeholder="Código o nombre...">
              <option value="0">Sin ligar a un RAP</option>
              <?php foreach ($raps as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['codigo'] . ' — ' . mb_substr($r['denominacion'], 0, 90)) ?> (<?= e($conceptoTexto[$r['concepto']] ?? '') ?>)</option><?php endforeach; ?>
            </select>
            <small class="text-muted">Si la evidencia demuestra un RAP, elígelo: tu instructor podrá registrar el juicio al revisarla.</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ev_desc">Descripción</label>
            <textarea name="descripcion" id="ev_desc" class="form-control" rows="3" maxlength="<?= EvidenciaFormulario::MAX_DESCRIPCION ?>" data-filtro="sin-html"
                      placeholder="Qué entregas y cómo se relaciona con la actividad..."></textarea>
          </div>
          <div class="upload-dropzone" data-pulsar="#ev_archivo" data-zona-archivo="#ev_archivo" role="button" tabindex="0" aria-label="Elegir archivo">
            <i class="bi bi-file-earmark-arrow-up icon"></i>
            <span class="text">Arrastra aquí el archivo o haz clic para buscarlo</span>
            <span class="filename" id="ev_nombreArchivo">PDF, Office, JPG, PNG o TXT · hasta <?= EvidenciaFormulario::MAX_MB ?> MB</span>
            <input type="file" name="archivo" id="ev_archivo" class="d-none"
                   accept="<?= e(implode(',', array_map(static fn($x) => '.' . $x, EvidenciaFormulario::EXTENSIONES))) ?>"
                   data-nombre-en="#ev_nombreArchivo" data-max-mb="<?= EvidenciaFormulario::MAX_MB ?>">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($gestiona): ?>
<div class="modal fade" id="modalRevisar" tabindex="-1" aria-labelledby="tituloRevisar" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="revisar">
        <input type="hidden" name="id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloRevisar"><i class="bi bi-check2-square"></i>Revisar evidencia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="fw-semibold mb-0 text-break" data-campo="titulo"></p>
          <p class="small text-muted mb-3"><span data-campo="aprendiz"></span> · <span data-campo="rap"></span></p>
          <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Decisión <span class="text-danger">*</span></legend>
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach (EvidenciaFormulario::REVISION as $valor => $texto): ?>
                <input type="radio" class="btn-check" name="estado" value="<?= e($valor) ?>" id="rev_<?= e($valor) ?>" required>
                <label class="btn btn-outline-<?= e(\Core\Controllers\EvidenciasController::ESTADOS[$valor][1]) ?> flex-grow-1" for="rev_<?= e($valor) ?>"><?= e($texto) ?></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="rev_retro">Retroalimentación <span class="text-danger">*</span></label>
            <textarea name="retroalimentacion" id="rev_retro" class="form-control" rows="3" required minlength="5" maxlength="<?= EvidenciaFormulario::MAX_RETRO ?>" data-filtro="sin-html"
                      placeholder="Qué está bien y qué debe mejorar..."></textarea>
          </div>
          <div>
            <label class="form-label fw-semibold" for="rev_juicio">Juicio del RAP</label>
            <select name="juicio" id="rev_juicio" class="form-select">
              <option value="">No cambiar el juicio</option>
              <option value="A">Registrar A (aprobado)</option>
              <option value="D">Registrar D (aún no aprobado)</option>
            </select>
            <small class="text-muted">Solo si la evidencia está ligada a un RAP. El cambio queda en el historial con esta retroalimentación como motivo.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar revisión</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
