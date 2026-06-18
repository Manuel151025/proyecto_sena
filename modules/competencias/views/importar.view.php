<?php
declare(strict_types=1);
?>
<div class="mb-4">
  <a href="<?= APP_URL ?>/index.php/competencias" class="btn btn-soft btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver al Listado</a>
  <h1 class="mb-1">Importación Masiva de Competencias</h1>
  <p class="text-muted mb-0">Registra de forma masiva las competencias de formación usando plantillas Excel o archivos CSV.</p>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 glass-card text-danger" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <strong class="d-block mb-1">Se encontraron los siguientes errores en la validación:</strong>
  <ul class="mb-0 ps-3">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-upload text-success me-2"></i>Cargar Plantilla</h5>
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-4">
            <label class="form-label text-muted small fw-semibold">Selecciona el archivo (.xlsx o .csv)</label>
            <input type="file" name="archivo_competencias" class="form-control" accept=".xlsx, .csv" required>
            <small class="text-muted d-block mt-1">El archivo no debe exceder los 10MB de tamaño.</small>
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-circle-fill me-1"></i>Procesar e Importar</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Instrucciones de Formato</h5>
        <p class="text-muted small">Para que la importación sea exitosa, el archivo debe tener exactamente las siguientes columnas en su cabecera:</p>
        
        <div class="table-responsive">
          <table class="table table-bordered table-sm small text-center mb-0">
            <thead class="table-light">
              <tr>
                <th>Columna A</th>
                <th>Columna B</th>
                <th>Columna C</th>
                <th>Columna D</th>
                <th>Columna E</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Código Programa</strong></td>
                <td><strong>Código Competencia</strong></td>
                <td><strong>Nombre Competencia</strong></td>
                <td><strong>Horas</strong></td>
                <td><strong>Descripción</strong></td>
              </tr>
              <tr class="text-muted">
                <td>ADSO</td>
                <td>220501096</td>
                <td>DESARROLLAR LA ESTRUCTURA DEL SISTEMA</td>
                <td>240</td>
                <td>Diseño lógico y físico de BD...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul class="text-muted small ps-3 mt-3 mb-0">
          <li class="mb-1"><strong>Código Programa:</strong> Debe coincidir exactamente con el código de un programa activo en el sistema (ej: <code>ADSO</code>, <code>MM</code>).</li>
          <li class="mb-1"><strong>Código Competencia:</strong> Identificador único numérico o alfanumérico.</li>
          <li class="mb-1"><strong>Nombre Competencia:</strong> Se convertirá automáticamente a MAYÚSCULAS para mantener consistencia.</li>
          <li class="mb-1"><strong>Horas:</strong> Duración de la competencia, debe ser un número entero positivo.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($resultados)): ?>
<div class="card glass-card border-0 shadow-sm mt-4">
  <div class="card-body">
    <h5 class="fw-bold text-success mb-3"><i class="bi bi-check-all me-1"></i>Competencias Importadas en esta sesión:</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Programa ID</th>
            <th>Código</th>
            <th>Nombre Competencia</th>
            <th>Horas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resultados as $c): ?>
            <tr>
              <td><?= htmlspecialchars((string)$c['programa_id']) ?></td>
              <td class="font-monospace fw-bold"><?= htmlspecialchars($c['codigo']) ?></td>
              <td><?= htmlspecialchars($c['nombre']) ?></td>
              <td><?= htmlspecialchars((string)$c['horas']) ?> hs</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
