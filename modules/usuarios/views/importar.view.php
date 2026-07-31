<div class="mb-3">
  <h1>Importar Usuarios</h1>
  <p class="text-muted mb-0">Sube un archivo Excel (.xlsx) o CSV para registrar aprendices, instructores o coordinadores de forma masiva.</p>
</div>

<?php if (!empty($mensaje)): ?>
<?php
// El icono acompaña al tipo de mensaje: la importación puede terminar en
// 'warning' cuando todas las filas se omitieron por ya existir.
$iconoMensaje = 'bi-check-circle';
if ($tipo_mensaje === 'warning') $iconoMensaje = 'bi-exclamation-triangle';
if ($tipo_mensaje === 'danger') $iconoMensaje = 'bi-exclamation-circle';
?>
<div class="alert-flat <?= htmlspecialchars($tipo_mensaje) ?> mb-3">
  <i class="bi <?= $iconoMensaje ?>"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
</div>
<?php endif; ?>

<?php if (!empty($resultados)): ?>
<div class="card mb-3">
  <div class="card-header">Contraseñas temporales generadas</div>
  <div class="card-body">
    <p class="small text-muted">Cada usuario debe cambiar esta contraseña en su primer inicio de sesión. Este listado no se volverá a mostrar.</p>
    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0">
        <thead class="bg-light">
          <tr><th>Nombre</th><th>Email</th><th>Contraseña temporal</th></tr>
        </thead>
        <tbody>
          <?php foreach ($resultados as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['nombre']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><code><?= htmlspecialchars($r['password']) ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer">
    <a href="<?= APP_URL ?>/index.php/usuarios" class="btn btn-primary btn-sm">Volver a la lista →</a>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3">
  <i class="bi bi-exclamation-circle"></i>
  <div>
    <strong>Se encontraron los siguientes errores:</strong>
    <ul class="mb-0 mt-2 pl-3">
      <?php foreach ($errors as $error): ?>
      <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (empty($mensaje)): ?>
    <div class="mt-2 text-muted small">Ningún usuario fue importado. Por favor, corrige los errores y vuelve a intentarlo.</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <div class="mb-4">
            <label class="form-label d-block">Archivo Excel / CSV</label>
            <input type="file" name="archivo_csv" class="form-control" accept=".csv, .xlsx" required
                   data-import-preview data-import-preview-target="#previewUsuarios">
            <div class="text-muted small mt-2">
              Formatos aceptados: <strong>.xlsx</strong> (Excel moderno) y <strong>.csv</strong>.
              El formato antiguo <strong>.xls</strong> no se admite: ábrelo en Excel y usa
              <em>Guardar como</em> &rarr; <strong>.xlsx</strong>.
            </div>
            <!-- Vista previa del archivo: debajo de la ayuda de formatos -->
            <div id="previewUsuarios"></div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Subir e Importar</button>
            <a href="<?= APP_URL ?>/index.php/usuarios" class="btn btn-soft">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card bg-light border-0">
      <div class="card-body">
        <h5><i class="bi bi-info-circle text-primary me-2"></i>Instrucciones</h5>
        <p class="small text-muted mb-3">Para asegurar una importación exitosa, el archivo debe contener exactamente estas tres columnas en la primera fila (cabecera):</p>
        
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered bg-white mb-0 text-center small">
            <thead class="bg-light">
              <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Juan Pérez</td>
                <td>juan@sena.edu.co</td>
                <td>aprendiz</td>
              </tr>
              <tr>
                <td>Ana Gómez</td>
                <td>ana@sena.edu.co</td>
                <td>instructor</td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul class="small text-muted mb-0">
          <li><strong>Formato:</strong> Se recomienda usar <code>.xlsx</code> para evitar problemas de codificación de caracteres.</li>
          <li><strong>Rol:</strong> Solo se admiten los valores <code>aprendiz</code>, <code>instructor</code> o <code>coordinador</code>.</li>
          <li><strong>Contraseña:</strong> Cada usuario importado recibe una contraseña temporal aleatoria (se muestra una sola vez al finalizar) y debe cambiarla en su primer inicio de sesión.</li>
          <li><strong>Colores:</strong> El avatar se asignará de forma aleatoria automáticamente.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Vista previa del archivo antes de enviar (solo frontend) -->
<script src="<?= APP_URL ?>/assets/js/import-preview.js?v=<?= filemtime(BASE_PATH . 'assets/js/import-preview.js') ?>"></script>
