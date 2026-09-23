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
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$hayFiltros = $filtros['search'] !== '' || $filtros['tipo'] !== '' || $filtros['ficha_id'];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1"><?= $actor->esAprendiz() ? 'Mi retroalimentación' : 'Retroalimentación' ?></h1>
    <p class="text-muted mb-0"><?= $actor->esAprendiz()
        ? 'Lo que tus instructores destacan de tu trabajo y lo que te recomiendan mejorar.'
        : 'Fortalezas, aspectos a mejorar y recomendaciones para tus aprendices; las privadas solo las ve el equipo de formación.' ?></p>
  </div>
  <?php if ($gestiona): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRetro"><i class="bi bi-plus-lg me-1"></i>Nueva retroalimentación</button>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<form method="GET" class="toolbar mb-3">
  <div class="search">
    <i class="bi bi-search"></i>
    <label class="visually-hidden" for="f_search">Buscar</label>
    <input type="search" name="search" id="f_search" class="form-control" maxlength="100"
           placeholder="<?= $gestiona ? 'Aprendiz, documento o texto...' : 'Buscar en el texto...' ?>" value="<?= e($filtros['search']) ?>">
  </div>
  <div class="toolbar-filter">
    <label class="visually-hidden" for="f_tipo">Tipo</label>
    <select name="tipo" id="f_tipo" class="form-select" data-autoenvio>
      <option value="">Todos los tipos</option>
      <?php foreach ($tipos as $valor => [$texto]): ?><option value="<?= e($valor) ?>" <?= $filtros['tipo'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option><?php endforeach; ?>
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
  <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= $url('/retroalimentacion') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
</form>

<div class="row g-3">
  <?php foreach ($retros as $r): [$tTexto, $tClase, $tIcono] = $tipos[$r['tipo']] ?? [$r['tipo'], 'secondary', 'bi-chat']; ?>
  <div class="col-md-6 col-xl-4">
    <article class="card h-100 tarjeta-lateral lateral-<?= e($tClase) ?>">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <span class="badge-soft <?= e($tClase) ?>"><i class="bi <?= e($tIcono) ?> me-1"></i><?= e($tTexto) ?></span>
          <?php if ((int)$r['privada'] === 1): ?><span class="badge-soft secondary"><i class="bi bi-lock me-1"></i>Privada</span><?php endif; ?>
        </div>
        <?php if ($gestiona): ?>
          <div class="fw-semibold"><?= e($r['aprendiz_nombre']) ?> <small class="text-muted fw-normal">· Ficha <?= e($r['numero_ficha']) ?></small></div>
        <?php endif; ?>
        <?php if ($r['ra_codigo']): ?><div class="small text-muted font-monospace"><?= e($r['ra_codigo']) ?></div><?php endif; ?>
        <p class="mt-2 mb-3 text-break texto-multilinea"><?= e($r['contenido']) ?></p>
        <div class="d-flex align-items-center gap-2 mt-auto small text-muted">
          <div class="avatar sm" style="background: <?= e($r['avatar_color'] ?: '#39A900') ?>"><?= e(getInitials($r['instructor_nombre'])) ?></div>
          <span><?= e($r['instructor_nombre']) ?> · <?= e(date('d/m/Y', strtotime((string)$r['fecha_creacion']))) ?></span>
        </div>
      </div>
    </article>
  </div>
  <?php endforeach; ?>
  <?php if (empty($retros)): ?>
    <div class="col-12 estado-vacio"><i class="bi bi-chat-square-text"></i><?= $hayFiltros ? 'Nada coincide con los filtros.' : ($actor->esAprendiz() ? 'Aún no tienes retroalimentación.' : 'No hay retroalimentación registrada.') ?></div>
  <?php endif; ?>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'registros'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($gestiona) { $aprendicesModal = $aprendices; require BASE_PATH . 'components/modal_retroalimentacion.php'; } ?>
