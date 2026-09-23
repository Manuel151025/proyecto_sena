<?php
// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$hayFiltros = $filtros['search'] !== '' || $filtros['ficha_id'] || $filtros['estado'] !== '';
$qs = http_build_query(array_filter($filtros, static fn($x) => $x !== '' && $x !== 0));
$nombresDoc = ['CC' => 'Cédula de ciudadanía', 'TI' => 'Tarjeta de identidad', 'CE' => 'Cédula de extranjería', 'PEP' => 'Permiso especial (PEP)', 'PA' => 'Pasaporte'];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Matrículas</h1>
    <p class="text-muted mb-0"><?= $esCoordinador ? 'Aprendices matriculados en las fichas del centro.' : 'Aprendices de tus fichas y de tu seguimiento.' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= e(APP_URL . '/index.php/matriculas/exportar' . ($qs ? '?' . $qs : '')) ?>" class="btn btn-soft"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
    <?php if ($esCoordinador): ?>
      <a href="<?= e(APP_URL . '/index.php/matriculas/importar') ?>" class="btn btn-soft"><i class="bi bi-upload me-1"></i>Matrícula masiva</a>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-person-plus me-1"></i>Matricular</button>
    <?php endif; ?>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<?php if ($credencial): ?>
<div class="card border-0 shadow-sm mb-3 credencial-temporal">
  <div class="card-body d-flex gap-3 align-items-start">
    <i class="bi bi-shield-lock fs-3 text-warning"></i>
    <div class="min-w-0">
      <div class="fw-bold">Contraseña temporal de <?= e($credencial['nombre']) ?></div>
      <div class="small text-muted mb-2"><?= e($credencial['email']) ?> · No volverá a mostrarse; se le pedirá cambiarla al entrar.</div>
      <code class="fs-5 user-select-all"><?= e($credencial['password']) ?></code>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small" for="f_search">Buscar</label>
        <input type="search" name="search" id="f_search" class="form-control" maxlength="100" placeholder="Nombre, documento o correo..." value="<?= e($filtros['search']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small" for="f_ficha">Ficha</label>
        <select name="ficha_id" id="f_ficha" class="form-select" data-autoenvio data-picker data-picker-label="Ficha">
          <option value="0">Todas</option>
          <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $filtros['ficha_id'] === (int)$f['id'] ? 'selected' : '' ?>>Ficha <?= e($f['numero_ficha']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small" for="f_estado">Estado</label>
        <select name="estado" id="f_estado" class="form-select" data-autoenvio data-picker data-picker-label="Estado">
          <option value="">Todos</option>
          <?php foreach ($estados_label as $v => [$t]): ?><option value="<?= e($v) ?>" <?= $filtros['estado'] === $v ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-soft flex-grow-1">Filtrar</button>
        <?php if ($hayFiltros): ?><a class="btn btn-soft" href="<?= e(APP_URL . '/index.php/matriculas') ?>" aria-label="Quitar filtros"><i class="bi bi-x-lg"></i></a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Documento</th><th>Aprendiz</th><th>Ficha</th><th>Contacto</th><th>Estado</th><?php if ($esCoordinador): ?><th class="text-end">Acciones</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($aprendices as $ap):
          [$estTxt, $estCls] = $estados_label[$ap['estado']] ?? [$ap['estado'], 'secondary']; ?>
      <tr>
        <td class="font-monospace"><?= e($ap['tipo_documento'] . ' ' . $ap['numero_documento']) ?></td>
        <td>
          <strong><?= e($ap['nombre']) ?></strong>
          <small class="d-block text-muted"><?= e($ap['email']) ?></small>
          <?php if ($ap['instructor_seguimiento_nombre']): ?><small class="d-block text-muted">Seguimiento: <?= e($ap['instructor_seguimiento_nombre']) ?></small><?php endif; ?>
        </td>
        <td><?php if ($ap['numero_ficha']): ?><a href="<?= e(APP_URL . '/index.php/fichas/ver?id=' . (int)$ap['ficha_id']) ?>">Ficha <?= e($ap['numero_ficha']) ?></a>
          <small class="d-block text-muted"><?= e($ap['programa_nombre']) ?></small><?php else: ?>—<?php endif; ?></td>
        <td class="small"><?= e($ap['telefono'] ?: '—') ?><br><span class="text-muted"><?= e($ap['ciudad'] ?: '') ?></span></td>
        <td><span class="badge-soft <?= e($estCls) ?>"><?= e($estTxt) ?></span></td>
        <?php if ($esCoordinador): ?>
        <td class="text-end">
          <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-soft" aria-label="Editar matrícula" data-modal="#modalEditar"
                    data-valores="<?= datosJson(['id' => (int)$ap['id'], 'nombre' => $ap['nombre'], 'email' => $ap['email'],
                        'tipo_documento' => $ap['tipo_documento'], 'numero_documento' => $ap['numero_documento'], 'ficha_id' => (int)$ap['ficha_id'],
                        'estado' => $ap['estado'], 'genero' => $ap['genero'], 'fecha_nacimiento' => $ap['fecha_nacimiento'] ?? '',
                        'telefono' => $ap['telefono'] ?? '', 'ciudad' => $ap['ciudad'] ?? '',
                        'instructor_seguimiento_id' => (int)($ap['instructor_seguimiento_id'] ?? 0) ?: '']) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <?php if ($ap['estado'] !== 'desertado'): ?>
            <form method="POST" class="d-inline" data-confirmar="<?= e('¿Retirar la matrícula de ' . $ap['nombre'] . '? Quedará como desertado y sin acceso; su historial se conserva.') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="retirar">
              <input type="hidden" name="id" value="<?= (int)$ap['id'] ?>">
              <button type="submit" class="btn btn-sm btn-soft text-danger" aria-label="Retirar matrícula"><i class="bi bi-person-dash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($aprendices)): ?>
      <tr><td colspan="6" class="celda-vacia"><div><?= $hayFiltros ? 'Ningún aprendiz coincide con los filtros.' : 'No hay aprendices matriculados.' ?></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $paginador = $paginacion; $paginacionEtiqueta = 'aprendices'; require BASE_PATH . 'components/paginacion.php'; ?>

<?php if ($esCoordinador):
    foreach (['Crear' => 'matricular', 'Editar' => 'editar'] as $sufijo => $accion): $p = strtolower($sufijo); ?>
<div class="modal fade" id="modal<?= $sufijo ?>" tabindex="-1" aria-labelledby="titulo<?= $sufijo ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $accion ?>">
        <?php if ($accion === 'editar'): ?><input type="hidden" name="id"><?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title" id="titulo<?= $sufijo ?>"><i class="bi <?= $accion === 'editar' ? 'bi-pencil-square' : 'bi-person-plus' ?>"></i><?= $accion === 'editar' ? 'Editar matrícula' : 'Nueva matrícula' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="<?= $p ?>_nombre">Nombre completo <span class="text-danger">*</span></label>
              <input type="text" name="nombre" id="<?= $p ?>_nombre" class="form-control text-uppercase" required minlength="3" maxlength="150" data-filtro="persona" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="<?= $p ?>_email">Correo <span class="text-danger">*</span></label>
              <input type="email" name="email" id="<?= $p ?>_email" class="form-control" required maxlength="120" inputmode="email" autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $p ?>_tipo">Tipo de documento</label>
              <select name="tipo_documento" id="<?= $p ?>_tipo" class="form-select" data-picker data-picker-label="Tipo de documento">
                <?php foreach ($tipos_doc as $t): ?><option value="<?= e($t) ?>"><?= e($nombresDoc[$t] ?? $t) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $p ?>_doc">Número de documento <span class="text-danger">*</span></label>
              <input type="text" name="numero_documento" id="<?= $p ?>_doc" class="form-control" required minlength="5" maxlength="20" data-filtro="codigo" inputmode="numeric">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="<?= $p ?>_ficha">Ficha <span class="text-danger">*</span></label>
              <select name="ficha_id" id="<?= $p ?>_ficha" class="form-select" required data-picker data-picker-label="Ficha">
                <option value="" disabled selected>Seleccione…</option>
                <?php foreach ($fichas as $f): ?><option value="<?= (int)$f['id'] ?>" data-search="<?= e($f['numero_ficha'] . ' ' . $f['programa']) ?>">Ficha <?= e($f['numero_ficha']) ?> — <?= e($f['programa']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="<?= $p ?>_genero">Género</label>
              <select name="genero" id="<?= $p ?>_genero" class="form-select" data-picker data-picker-label="Género">
                <option value="F">Femenino</option><option value="M">Masculino</option><option value="O" selected>Otro / no informa</option>
              </select>
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="<?= $p ?>_nacimiento">Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" id="<?= $p ?>_nacimiento" class="form-control" min="1936-01-01" max="<?= e(date('Y-m-d', strtotime('-14 years'))) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="<?= $p ?>_telefono">Teléfono</label>
              <input type="tel" name="telefono" id="<?= $p ?>_telefono" class="form-control" maxlength="16" inputmode="tel" pattern="\+?[0-9 ]{7,15}">
            </div>
            <div class="col-sm-6 col-md-3">
              <label class="form-label small fw-semibold" for="<?= $p ?>_ciudad">Ciudad</label>
              <input type="text" name="ciudad" id="<?= $p ?>_ciudad" class="form-control" maxlength="100" data-filtro="nombre">
            </div>
            <div class="<?= $accion === 'editar' ? 'col-md-6' : 'col-12' ?>">
              <label class="form-label small fw-semibold" for="<?= $p ?>_seguimiento">Instructor de seguimiento (etapa práctica)</label>
              <select name="instructor_seguimiento_id" id="<?= $p ?>_seguimiento" class="form-select" data-picker data-picker-label="Instructor de seguimiento">
                <option value="">— Sin asignar —</option>
                <?php foreach ($instructores as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['nombre']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <?php if ($accion === 'editar'): ?>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="editar_estado">Estado</label>
              <select name="estado" id="editar_estado" class="form-select" data-picker data-picker-label="Estado">
                <?php foreach ($estados_label as $v => [$t]): ?><option value="<?= e($v) ?>"><?= e($t) ?></option><?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>
            <?= $accion === 'editar'
                ? 'Desertado o egresado deja al aprendiz sin acceso; un aprendiz con juicios solo se traslada a otra ficha del mismo programa.'
                : 'Se crea la cuenta con una contraseña temporal (la verás una sola vez) y sus evaluaciones pendientes de todos los RAP del programa.' ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><?= $accion === 'editar' ? 'Guardar' : 'Matricular' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
