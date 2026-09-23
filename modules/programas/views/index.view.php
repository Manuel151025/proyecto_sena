<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$scriptsVista[] = 'modulos/filtro-tarjetas.js';
?>
<div class="page-header">
  <div>
    <h1>Programas de Formación</h1>
    <p class="text-muted mb-0">Programas del centro con sus competencias, resultados de aprendizaje y fichas.</p>
  </div>
  <?php if ($puedeEditar): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="bi bi-plus-lg me-1"></i>Nuevo programa
    </button>
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<!-- Filtro en el cliente: el listado de programas es corto y no se pagina,
     así que aquí sí alcanza a todas las filas. -->
<div class="toolbar mb-3" data-filtro-tarjetas="#listaProgramas">
  <div class="search">
    <i class="bi bi-search"></i>
    <input class="form-control" data-filtro-texto placeholder="Buscar por nombre o código..." aria-label="Buscar programa" maxlength="100">
  </div>
  <div class="toolbar-filter">
    <select class="form-select" data-filtro-estado data-picker data-picker-label="Filtrar por estado">
      <option value="">Todos los estados</option>
      <?php foreach ($estados_label as $valor => [$texto]): ?>
        <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="row g-3" id="listaProgramas">
  <?php foreach ($programas as $programa):
      [$estTxt, $estCls] = $estados_label[$programa['estado']] ?? [$programa['estado'], 'secondary'];
      $desc = (string)($programa['descripcion'] ?? '');
  ?>
  <div class="col-lg-6" data-estado="<?= e($programa['estado']) ?>"
       data-texto="<?= e(mb_strtolower($programa['nombre'] . ' ' . $programa['codigo'], 'UTF-8')) ?>">
    <article class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-w-0">
            <h2 class="h5 text-uppercase-visual"><?= e($programa['nombre']) ?></h2>
            <p class="text-muted mb-2"><code class="text-uppercase-visual"><?= e($programa['codigo']) ?></code></p>
            <?php if ($desc !== ''): ?>
              <p class="small texto-recortado-2 mb-2"><?= e($desc) ?></p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-3 small">
              <span><strong><?= (int)$programa['duracion_horas'] ?></strong> horas</span>
              <span><strong><?= (int)$programa['total_competencias'] ?></strong> competencias</span>
              <span><strong><?= (int)$programa['total_rap'] ?></strong> RAP</span>
              <span><strong><?= (int)$programa['total_fichas'] ?></strong> fichas</span>
              <span class="badge-soft <?= e($estCls) ?>"><?= e($estTxt) ?></span>
            </div>
          </div>
          <?php if ($puedeEditar): ?>
          <div class="d-flex gap-1 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-soft" aria-label="Editar programa"
                    data-modal="#modalEditar"
                    data-valores="<?= datosJson([
                        'id' => (int)$programa['id'], 'nombre' => $programa['nombre'], 'codigo' => $programa['codigo'],
                        'descripcion' => $desc, 'duracion_horas' => (int)$programa['duracion_horas'], 'estado' => $programa['estado'],
                    ]) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Eliminar el programa ' . $programa['nombre'] . '?') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$programa['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Eliminar programa"><i class="bi bi-trash"></i></button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </article>
  </div>
  <?php endforeach; ?>
  <div class="col-12 estado-vacio" data-sin-resultados <?= empty($programas) ? '' : 'hidden' ?>>
    <i class="bi bi-book"></i>
    <?= empty($programas) ? 'No hay programas registrados.' : 'Ningún programa coincide con el filtro.' ?>
  </div>
</div>

<?php if ($puedeEditar):
    foreach (['Crear' => 'crear', 'Editar' => 'editar'] as $sufijo => $accion): ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>">
            <i class="bi <?= $accion === 'crear' ? 'bi-book' : 'bi-pencil' ?>"></i>
            <?= $accion === 'crear' ? 'Nuevo programa de formación' : 'Editar programa de formación' ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_nombre">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="<?= $accion ?>_nombre" class="form-control" required
                   minlength="3" maxlength="<?= (int)$limites['nombre'] ?>" data-filtro="nombre"
                   placeholder="Ej.: Análisis y Desarrollo de Software">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="<?= $accion ?>_codigo">Código <span class="text-danger">*</span></label>
              <input type="text" name="codigo" id="<?= $accion ?>_codigo" class="form-control" required
                     minlength="2" maxlength="<?= (int)$limites['codigo'] ?>" data-filtro="codigo-punto" placeholder="Ej.: 228118">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="<?= $accion ?>_horas">Duración (horas) <span class="text-danger">*</span></label>
              <input type="number" name="duracion_horas" id="<?= $accion ?>_horas" class="form-control" required
                     min="1" max="<?= (int)$limites['horas'] ?>" step="1" inputmode="numeric">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="<?= $accion ?>_descripcion">Descripción</label>
            <textarea name="descripcion" id="<?= $accion ?>_descripcion" class="form-control" rows="3"
                      maxlength="<?= (int)$limites['texto'] ?>" data-filtro="sin-html"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold" for="<?= $accion ?>_estado">Estado</label>
            <select name="estado" id="<?= $accion ?>_estado" class="form-select" data-picker data-picker-label="Estado del programa">
              <?php foreach ($estados_label as $valor => [$texto]): ?>
                <option value="<?= e($valor) ?>"><?= e($texto) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
