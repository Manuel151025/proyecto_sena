<div class="mb-3">
  <h1>Importar Usuarios</h1>
  <p class="text-muted mb-0">Sube un archivo Excel (.xlsx) o CSV para registrar aprendices, instructores o coordinadores de forma masiva.</p>
</div>

<?php if (!empty($mensaje)): ?>
<div class="alert-flat <?= htmlspecialchars($tipo_mensaje) ?> mb-3">
  <i class="bi bi-check-circle"></i>
  <div><?= htmlspecialchars($mensaje) ?></div>
  <br><a href="<?= APP_URL ?>/index.php/usuarios">Volver a la lista →</a>
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
          <div class="mb-4">
            <label class="form-label d-block">Archivo Excel / CSV</label>
            <input type="file" name="archivo_csv" class="form-control" accept=".csv, .xlsx, .xls" required>
            <div class="text-muted small mt-2">Formatos aceptados: <strong>.xlsx</strong> (Excel moderno) y <strong>.csv</strong>. Si tiene un archivo antiguo (.xls), por favor guardelo como .xlsx.</div>
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
          <li><strong>Contraseña:</strong> Todos los usuarios importados masivamente tendrán la contraseña por defecto: <code>Sena2026</code></li>
          <li><strong>Colores:</strong> El avatar se asignará de forma aleatoria automáticamente.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
