<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Configuración</h1>
    <p class="text-muted mb-0">Datos institucionales que aparecen en los reportes, y los parámetros con los que funciona el sistema.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-building me-2 text-success"></i>Datos institucionales</h2>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="guardar">
        <?php foreach ($claves as $clave => [$etiqueta]): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="cfg_<?= e($clave) ?>"><?= e($etiqueta) ?></label>
            <input type="text" name="<?= e($clave) ?>" id="cfg_<?= e($clave) ?>" class="form-control" required minlength="3" maxlength="120" data-filtro="nombre" value="<?= e($valores[$clave]) ?>">
          </div>
        <?php endforeach; ?>
        <small class="d-block text-muted mb-3">Se imprimen en el encabezado de los reportes PDF.</small>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-sliders me-2 text-success"></i>Parámetros del sistema</h2>
      <dl class="row small mb-0">
        <?php foreach ($tecnica as $etiqueta => $valor): ?>
          <dt class="col-sm-4 text-muted"><?= e($etiqueta) ?></dt>
          <dd class="col-sm-8 text-break"><?= e($valor) ?></dd>
        <?php endforeach; ?>
      </dl>
      <p class="small text-muted mt-3 mb-0">Estos valores se definen en el código y en el entorno del servidor (.env), no desde esta pantalla: ver docs/DESPLIEGUE.md.</p>
    </div></div>
  </div>
</div>
