<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$activos = array_filter($filtros, static fn($x) => $x !== '' && $x !== 0 && $x !== null);
$qs = http_build_query($activos);
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Bitácora de auditoría</h1>
    <p class="text-muted mb-0">Quién hizo qué, cuándo y desde dónde. Los registros no se editan ni se borran desde la aplicación.</p>
  </div>
  <a href="<?= $url('/logs/exportar' . ($qs ? '?' . $qs . '&' : '?') . 'formato=xlsx') ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<form method="GET" class="card border-0 shadow-sm mb-3"><div class="card-body row g-2 align-items-end">
  <div class="col-md-4">
    <label class="form-label small text-muted" for="f_search">Buscar</label>
    <input type="search" name="search" id="f_search" class="form-control" maxlength="100" placeholder="Usuario, correo, descripción o IP..." value="<?= e($filtros['search']) ?>">
  </div>
  <div class="col-6 col-md-2">
    <label class="form-label small text-muted" for="f_accion">Acción</label>
    <select name="accion" id="f_accion" class="form-select" data-autoenvio>
      <option value="">Todas</option>
      <?php foreach ($acciones as $a): ?><option value="<?= e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <label class="form-label small text-muted" for="f_modulo">Módulo</label>
    <select name="modulo" id="f_modulo" class="form-select" data-autoenvio>
      <option value="">Todos</option>
      <?php foreach ($modulos as $m): ?><option value="<?= e($m) ?>" <?= $filtros['modulo'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label small text-muted" for="f_usuario">Usuario</label>
    <select name="usuario_id" id="f_usuario" class="form-select" data-autoenvio data-picker data-picker-label="Usuario">
      <option value="0">Todos</option>
      <?php foreach ($usuarios as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $filtros['usuario_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label small text-muted" for="f_desde">Desde</label>
    <input type="date" name="desde" id="f_desde" class="form-control" value="<?= e((string)$filtros['desde']) ?>">
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label small text-muted" for="f_hasta">Hasta</label>
    <input type="date" name="hasta" id="f_hasta" class="form-control" value="<?= e((string)$filtros['hasta']) ?>">
  </div>
  <div class="col-md-6 d-flex gap-2">
    <button type="submit" class="btn btn-soft flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
    <?php if ($activos): ?><a class="btn btn-soft" href="<?= $url('/logs') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
  </div>
</div></form>

<div class="table-wrap">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Descripción</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td class="small text-nowrap"><?= e(date('d/m/Y H:i', strtotime((string)$l['fecha']))) ?></td>
        <td class="small"><?php if ($l['usuario_nombre']): ?><div class="fw-semibold"><?= e($l['usuario_nombre']) ?></div><span class="text-muted"><?= e((string)$l['usuario_rol']) ?></span><?php else: ?><span class="text-muted">Sistema / anónimo</span><?php endif; ?></td>
        <td><span class="badge-soft <?= e($colores[$l['accion']] ?? 'secondary') ?> text-nowrap"><?= e($l['accion']) ?></span></td>
        <td class="small"><?= e((string)$l['modulo']) ?><?= $l['id_registro'] ? '<span class="text-muted"> #' . (int)$l['id_registro'] . '</span>' : '' ?></td>
        <td class="small text-break"><?= e((string)$l['descripcion']) ?></td>
        <td class="small font-monospace text-nowrap"><?= e((string)$l['ip_address']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
      <tr><td colspan="6" class="celda-vacia"><div><i class="bi bi-journal-x"></i>No hay registros con esos filtros.</div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'registros'; require BASE_PATH . 'components/paginacion.php'; ?>
