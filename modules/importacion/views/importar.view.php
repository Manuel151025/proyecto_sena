<?php
declare(strict_types=1);

// Vista común de las importaciones tabulares. Solo desde un controlador.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}

/** @var \Core\Importacion\Importador $importador */
$scriptsVista[] = 'modulos/zona-archivos.js';
$columnas = $importador->columnas();
[$rutaVolver, $textoVolver] = $volver;
$url = static fn(string $r) => e(APP_URL . '/index.php' . $r);
$nombreSeparador = [';' => 'punto y coma', ',' => 'coma', "\t" => 'tabulador', '|' => 'barra vertical'];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Importar <?= e($importador->titulo()) ?></h1>
    <p class="text-muted mb-0">Sube un archivo CSV o Excel, revisa el resultado fila por fila y confirma. Nada se guarda antes de confirmar.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= $url($ruta . '/plantilla') ?>" class="btn btn-soft"><i class="bi bi-download me-1"></i>Plantilla CSV</a>
    <a href="<?= $url($rutaVolver) ?>" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i><?= e($textoVolver) ?></a>
  </div>
</div>

<?php if ($resultado !== null): ?>
  <!-- RESULTADO (se muestra una sola vez) -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <h2 class="h5 fw-bold mb-3"><i class="bi bi-clipboard2-check text-success me-2"></i>Importación terminada</h2>
      <div class="row g-3 mb-3">
        <div class="col-4"><div class="panel-cifras text-center"><div class="fs-3 fw-bold text-success"><?= (int)$resultado['creados'] ?></div><div class="small text-muted">creados</div></div></div>
        <div class="col-4"><div class="panel-cifras text-center"><div class="fs-3 fw-bold"><?= (int)$resultado['omitidos'] ?></div><div class="small text-muted">omitidos (ya existían)</div></div></div>
        <div class="col-4"><div class="panel-cifras text-center"><div class="fs-3 fw-bold text-danger"><?= (int)($resultado['con_error'] ?? 0) ?></div><div class="small text-muted">con error (no importados)</div></div></div>
      </div>
      <?php foreach (($resultado['detalle'] ?? []) as $d): ?>
        <div class="small text-muted">· <?= e($d) ?></div>
      <?php endforeach; ?>

      <?php if (!empty($resultado['credenciales'])): ?>
        <div class="alert-flat warning my-3">
          <i class="bi bi-shield-lock"></i>
          <div><strong>Copia estas contraseñas temporales ahora: no volverán a mostrarse.</strong>
            Cada persona deberá cambiarla en su primer acceso.</div>
        </div>
        <div class="table-wrap">
          <table class="table table-sm mb-0">
            <thead><tr><th>Nombre</th><th>Correo</th><th>Contraseña temporal</th></tr></thead>
            <tbody>
              <?php foreach ($resultado['credenciales'] as $c): ?>
                <tr><td><strong><?= e($c['nombre']) ?></strong></td><td><?= e($c['email']) ?></td><td><code class="user-select-all"><?= e($c['password']) ?></code></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="mt-2"><button type="button" class="btn btn-sm btn-soft" data-accion="imprimir"><i class="bi bi-printer me-1"></i>Imprimir credenciales</button></div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($previa === null): ?>
  <!-- PASO 1: SUBIR -->
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <?php foreach (($extras['campos'] ?? []) as $campo): ?>
              <div class="mb-3">
                <label class="form-label fw-semibold" for="imp_<?= e($campo['nombre']) ?>"><?= e($campo['etiqueta']) ?> <span class="text-danger">*</span></label>
                <select name="<?= e($campo['nombre']) ?>" id="imp_<?= e($campo['nombre']) ?>" class="form-select" required
                        data-picker data-picker-label="<?= e($campo['etiqueta']) ?>">
                  <option value="" disabled selected>Seleccione…</option>
                  <?php foreach ($campo['opciones'] as $valor => $texto): ?>
                    <option value="<?= e((string)$valor) ?>"><?= e($texto) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>

            <div class="upload-dropzone mb-3" data-pulsar="#archivo" data-zona-archivo="#archivo" role="button" tabindex="0" aria-label="Elegir archivo">
              <i class="bi bi-file-earmark-spreadsheet icon"></i>
              <span class="text">Arrastra aquí el archivo o haz clic para buscarlo</span>
              <span class="filename" id="nombreArchivo">CSV, XLSX o XLS · hasta <?= (int)$max_mb ?> MB · <?= (int)$importador->maxFilas() ?> filas</span>
              <input type="file" name="archivo" id="archivo" class="d-none" required
                     accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                     data-nombre-en="#nombreArchivo" data-max-mb="<?= (int)$max_mb ?>">
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-search me-2"></i>Analizar archivo</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 fw-bold mb-3"><i class="bi bi-list-columns me-2"></i>Columnas del archivo</h2>
          <div class="table-wrap">
            <table class="table table-sm mb-2">
              <thead><tr><th>Encabezado</th><th>Contenido</th></tr></thead>
              <tbody>
                <?php foreach ($columnas as $col => $def): ?>
                  <tr>
                    <td><code><?= e($col) ?></code><?= !empty($def['obligatorio']) ? ' <span class="text-danger">*</span>' : '' ?></td>
                    <td class="small"><?= e($def['etiqueta']) ?><?= isset($def['ayuda']) ? '<br><span class="text-muted">' . e($def['ayuda']) . '</span>' : '' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <ul class="small text-muted ps-3 mb-0">
            <li>La primera fila debe llevar los encabezados (se aceptan en cualquier orden, con o sin tildes).</li>
            <li>El separador del CSV (coma o punto y coma) y la codificación (UTF-8 o la de Excel en Windows) se detectan solos.</li>
            <li>Reimportar el mismo archivo no duplica: lo que ya existe se omite.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- PASO 2: REVISAR Y CONFIRMAR -->
  <?php $conError = (int)$previa['total'] - (int)$previa['validas']; ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
      <div class="min-w-0">
        <div class="fw-bold text-break"><i class="bi bi-file-earmark-check me-1"></i><?= e($previa['archivo']) ?></div>
        <div class="small text-muted">
          <?= e($previa['formato']) ?>
          <?php if ($previa['separador'] !== null): ?> · separador: <?= e($nombreSeparador[$previa['separador']] ?? $previa['separador']) ?><?php endif; ?>
          · <?= e($previa['codificacion']) ?>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <span class="badge-soft success"><?= (int)$previa['validas'] ?> válidas</span>
        <?php if ($conError > 0): ?><span class="badge-soft danger"><?= $conError ?> con error</span><?php endif; ?>
      </div>
      <div class="d-flex gap-2">
        <form method="POST" class="d-inline">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="cancelar">
          <button type="submit" class="btn btn-soft">Cancelar</button>
        </form>
        <?php if ((int)$previa['validas'] > 0): ?>
        <form method="POST" class="d-inline"
              data-confirmar="<?= e('¿Importar las ' . (int)$previa['validas'] . ' filas válidas?' . ($conError > 0 ? " Las $conError con error se descartarán." : '')) ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="confirmar">
          <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check2-all me-1"></i>Importar <?= (int)$previa['validas'] ?> filas</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!$previa['encabezado']): ?>
    <div class="alert-flat warning mb-3"><i class="bi bi-exclamation-triangle"></i>
      <div>No se reconoció la fila de encabezados, así que se usó el orden de columnas de la plantilla. Si los datos no coinciden, descarga la plantilla y vuelve a subir el archivo.</div></div>
  <?php endif; ?>
  <?php if ($previa['truncado']): ?>
    <div class="alert-flat warning mb-3"><i class="bi bi-scissors"></i>
      <div>El archivo supera el máximo de <?= (int)$importador->maxFilas() ?> filas: solo se analizaron las primeras. Divide el archivo para importar el resto.</div></div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="table table-sm align-middle tabla-previa">
      <thead>
        <tr>
          <th>Fila</th>
          <?php foreach ($columnas as $col => $def): ?><th><?= e($def['etiqueta']) ?></th><?php endforeach; ?>
          <th>Resultado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($previa['filas'] as $f): $ok = $f['errores'] === []; ?>
          <tr class="<?= $ok ? '' : 'fila-con-error' ?>">
            <td class="text-muted"><?= (int)$f['linea'] ?></td>
            <?php foreach ($columnas as $col => $_): ?>
              <td><?= e(mb_substr((string)($f['valores'][$col] ?? ''), 0, 120)) ?></td>
            <?php endforeach; ?>
            <td class="small">
              <?php if ($ok): ?>
                <span class="text-success-emphasis"><i class="bi bi-check-circle-fill text-success me-1"></i>Válida</span>
              <?php else: ?>
                <?php foreach ($f['errores'] as $err): ?><div class="text-danger"><i class="bi bi-x-circle me-1"></i><?= e($err) ?></div><?php endforeach; ?>
              <?php endif; ?>
              <?php foreach ($f['avisos'] as $av): ?><div class="text-warning-emphasis"><i class="bi bi-info-circle me-1"></i><?= e($av) ?></div><?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
