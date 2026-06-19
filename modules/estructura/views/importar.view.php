<?php
declare(strict_types=1);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Importar Datos Académicos</h1>
    <p class="text-muted mb-0">Carga los documentos PDF para estructurar automáticamente tu programa de formación.</p>
  </div>
  <div>
    <a href="<?= APP_URL ?>/index.php/estructura" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver al Módulo
    </a>
  </div>
</div>

<?php if ($error): ?>
<div class="alert alert-flat danger mb-4 alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <div><?= htmlspecialchars($error) ?></div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-flat success mb-4 alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i>
  <div><?= htmlspecialchars($success) ?></div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<div class="card p-4 text-center border-0 shadow-sm mb-4">
  <i class="bi bi-clipboard2-check text-success mb-3" style="font-size: 3.5rem;"></i>
  <h4 class="fw-bold text-dark">Importación Completada</h4>
  <p class="text-muted mb-3">La estructura curricular y el proyecto se cargaron en el sistema de manera exitosa.</p>
  <div>
    <a href="<?= APP_URL ?>/index.php/estructura" class="btn btn-primary px-4"><i class="bi bi-house-door me-2"></i>Ir al Dashboard</a>
  </div>
</div>
<?php endif; ?>

<?php if (!$success && !$preview_mode): ?>
<!-- FORMULARIO DE CARGA DE ARCHIVOS -->
<div class="row">
  <div class="col-xl-8 mx-auto">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-pdf text-primary me-2"></i>Sube tus Documentos PDF</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <?= csrfField() ?>
          
          <!-- Estructura Curricular -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark d-flex justify-content-between">
              <span>Estructura Curricular (PDF)</span>
              <span class="text-muted small fw-normal">Opcional</span>
            </label>
            <p class="text-muted small mb-2">Este archivo contiene todas las competencias del programa, su duración y la denominación de los resultados de aprendizaje.</p>
            <div class="upload-dropzone" id="dropzoneEstructura" onclick="document.getElementById('pdf_estructura').click()">
              <i class="bi bi-file-earmark-arrow-up icon"></i>
              <span class="text">Arrastra aquí el archivo o haz clic para buscar</span>
              <span class="filename" id="file_estructura_name">No se ha seleccionado ningún archivo</span>
              <input type="file" name="pdf_estructura" id="pdf_estructura" class="d-none" accept=".pdf" onchange="updateFilename('pdf_estructura', 'file_estructura_name')">
            </div>
          </div>

          <!-- Proyecto Formativo -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark d-flex justify-content-between">
              <span>Reporte Proyecto Formativo (PDF)</span>
              <span class="text-muted small fw-normal">Opcional</span>
            </label>
            <p class="text-muted small mb-2">Este archivo contiene la información del proyecto formativo, las fases asociadas (Análisis, Planeación, Ejecución, Evaluación) y los RAs vinculados a cada fase.</p>
            <div class="upload-dropzone" id="dropzoneProyecto" onclick="document.getElementById('pdf_proyecto').click()">
              <i class="bi bi-kanban-fill icon"></i>
              <span class="text">Arrastra aquí el archivo o haz clic para buscar</span>
              <span class="filename" id="file_proyecto_name">No se ha seleccionado ningún archivo</span>
              <input type="file" name="pdf_proyecto" id="pdf_proyecto" class="d-none" accept=".pdf" onchange="updateFilename('pdf_proyecto', 'file_proyecto_name')">
            </div>
          </div>

          <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary py-2.5 fw-bold"><i class="bi bi-cpu me-2"></i>Analizar y Previsualizar Documentos</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.upload-dropzone {
  border: 2px dashed var(--border);
  border-radius: var(--radius-lg);
  padding: 2.5rem 1.5rem;
  text-align: center;
  background: var(--bg);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
.upload-dropzone:hover {
  border-color: var(--sena-primary);
  background: var(--sena-primary-50);
}
.upload-dropzone .icon {
  font-size: 2.5rem;
  color: var(--text-soft);
  transition: color 0.2s ease;
}
.upload-dropzone:hover .icon {
  color: var(--sena-primary);
}
.upload-dropzone .text {
  font-weight: 500;
  color: var(--text-muted);
}
.upload-dropzone .filename {
  font-size: 0.8rem;
  color: var(--text-soft);
  font-style: italic;
}
</style>

<script>
function updateFilename(inputId, nameId) {
  const input = document.getElementById(inputId);
  const display = document.getElementById(nameId);
  if (input.files && input.files[0]) {
    display.textContent = input.files[0].name;
    display.classList.add('text-success', 'fw-bold');
  } else {
    display.textContent = 'No se ha seleccionado ningún archivo';
    display.classList.remove('text-success', 'fw-bold');
  }
}

// Agregar efectos de drag-and-drop
['dropzoneEstructura', 'dropzoneProyecto'].forEach(zoneId => {
  const zone = document.getElementById(zoneId);
  const inputId = zoneId === 'dropzoneEstructura' ? 'pdf_estructura' : 'pdf_proyecto';
  const nameId = zoneId === 'dropzoneEstructura' ? 'file_estructura_name' : 'file_proyecto_name';
  const input = document.getElementById(inputId);

  zone.addEventListener('dragover', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--sena-primary)';
    zone.style.background = 'var(--sena-primary-50)';
  });

  zone.addEventListener('dragleave', () => {
    zone.style.borderColor = 'var(--border)';
    zone.style.background = 'var(--bg)';
  });

  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--border)';
    zone.style.background = 'var(--bg)';
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      input.files = e.dataTransfer.files;
      updateFilename(inputId, nameId);
    }
  });
});
</script>
<?php endif; ?>

<?php if ($preview_mode && !empty($_SESSION['pending_import'])): ?>
<!-- PREVISUALIZACIÓN DE DATOS ANTES DE IMPORTAR -->
<div class="row">
  <div class="col-12">
    <div class="alert alert-flat warning mb-4 border-0 d-flex gap-3 align-items-center">
      <i class="bi bi-info-circle-fill text-warning" style="font-size: 1.5rem;"></i>
      <div>
        <h6 class="fw-bold mb-1">Previsualización de Importación</h6>
        Revisa los datos extraídos de los documentos PDF. Si estás conforme con la información estructurada, haz clic en **Confirmar e Importar** para registrar los datos en la base de datos de manera definitiva.
      </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="card p-3 mb-4 border-0 shadow-sm d-flex flex-row justify-content-between align-items-center bg-light">
      <div class="fw-semibold text-muted"><i class="bi bi-layers me-2"></i>Estado: Esperando confirmación de escritura</div>
      <div class="d-flex gap-2">
        <form method="POST" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="cancelar">
          <button type="submit" class="btn btn-soft px-4"><i class="bi bi-trash me-2"></i>Cancelar</button>
        </form>
        <form method="POST" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="confirmar">
          <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-check-all me-2"></i>Confirmar e Importar</button>
        </form>
      </div>
    </div>

    <!-- Estructura Curricular Preview -->
    <?php if (!empty($parsed_estructura)): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold text-success"><i class="bi bi-collection me-2"></i>Estructura Curricular: <?= htmlspecialchars($parsed_estructura['programa_nombre']) ?></h5>
      </div>
      <div class="card-body">
        <div class="row mb-3 g-2">
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Código del Programa</small>
              <strong style="font-size: 1.1rem;"><code><?= htmlspecialchars($parsed_estructura['programa_codigo']) ?></code></strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Total Competencias</small>
              <strong style="font-size: 1.1rem;"><?= count($parsed_estructura['competencias']) ?></strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Duración Estimada</small>
              <strong style="font-size: 1.1rem;"><?= $parsed_estructura['programa_duracion'] ?> horas</strong>
            </div>
          </div>
        </div>

        <div class="accordion" id="accordionEstructura">
          <?php foreach ($parsed_estructura['competencias'] as $index => $comp): ?>
          <div class="accordion-item" style="border-radius: var(--radius-lg); margin-bottom: 0.5rem; overflow: hidden; border: 1px solid var(--border);">
            <h2 class="accordion-header" id="headingEst<?= $index ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEst<?= $index ?>" aria-expanded="false" style="font-size: 0.95rem; font-weight: 600; padding: 1rem 1.25rem;">
                <span class="badge bg-soft primary me-2">Código: <?= htmlspecialchars($comp['codigo']) ?></span>
                <?= htmlspecialchars(substr($comp['nombre'], 0, 110)) ?><?= strlen($comp['nombre']) > 110 ? '...' : '' ?>
                <span class="badge bg-secondary ms-auto text-white ms-2" style="font-size: 0.72rem;"><?= count($comp['resultados']) ?> RAs</span>
              </button>
            </h2>
            <div id="collapseEst<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#accordionEstructura">
              <div class="accordion-body bg-white p-3">
                <p class="mb-3 text-muted"><i class="bi bi-clock me-1"></i> Duración de la Competencia: <strong><?= htmlspecialchars($comp['duracion']) ?></strong></p>
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-2">Resultados de Aprendizaje (RAs):</h6>
                <ul class="list-group list-group-flush">
                  <?php foreach ($comp['resultados'] as $ra): ?>
                  <li class="list-group-item px-0 py-2 d-flex align-items-start gap-2">
                    <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem; font-family: monospace;">RA-<?= str_pad((string)$ra['numero'], 2, '0', STR_PAD_LEFT) ?></span>
                    <span style="font-size: 0.9rem;"><?= htmlspecialchars($ra['denominacion']) ?></span>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Proyecto Formativo Preview -->
    <?php if (!empty($parsed_proyecto)): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-kanban me-2"></i>Proyecto Formativo: <?= htmlspecialchars($parsed_proyecto['proyecto_nombre']) ?></h5>
      </div>
      <div class="card-body">
        <div class="row mb-4 g-2">
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Código del Proyecto</small>
              <strong style="font-size: 1.1rem;"><code><?= htmlspecialchars($parsed_proyecto['proyecto_codigo']) ?></code></strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Fases del Proyecto</small>
              <strong style="font-size: 1.1rem; text-transform: capitalize;"><?= count($parsed_proyecto['fases']) ?> fases</strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Programa Asociado</small>
              <strong style="font-size: 1.05rem;" class="text-truncate d-block" title="<?= htmlspecialchars($parsed_proyecto['programa_nombre']) ?>"><?= htmlspecialchars($parsed_proyecto['programa_nombre'] ?: 'Desconocido') ?></strong>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <h6 class="fw-bold text-dark mb-1">Objetivo General del Proyecto:</h6>
          <p class="p-3 border rounded-3 bg-light text-muted mb-0" style="font-size: 0.92rem; line-height: 1.6;"><?= htmlspecialchars($parsed_proyecto['proyecto_objetivo']) ?></p>
        </div>

        <h6 class="fw-bold text-dark mt-4 mb-3"><i class="bi bi-diagram-2 me-2"></i>Fases y Resultados del Proyecto Formativo</h6>
        <div class="accordion" id="accordionProyecto">
          <?php 
          // Agrupar resultados por fase para la vista
          $raPorFase = [];
          foreach ($parsed_proyecto['fases'] as $f) {
              $raPorFase[$f] = [];
          }
          foreach ($parsed_proyecto['resultados'] as $r) {
              if (isset($raPorFase[$r['fase']])) {
                  $raPorFase[$r['fase']][] = $r;
              }
          }
          ?>
          <?php foreach ($parsed_proyecto['fases'] as $index => $fase): ?>
          <div class="accordion-item" style="border-radius: var(--radius-lg); margin-bottom: 0.5rem; overflow: hidden; border: 1px solid var(--border);">
            <h2 class="accordion-header" id="headingProj<?= $index ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProj<?= $index ?>" aria-expanded="false" style="font-size: 0.95rem; font-weight: 600; padding: 1rem 1.25rem;">
                <span class="badge bg-success me-2">Fase</span>
                <?= htmlspecialchars($fase) ?>
                <span class="badge bg-secondary ms-auto text-white ms-2" style="font-size: 0.72rem;"><?= count($raPorFase[$fase]) ?> RAs en esta fase</span>
              </button>
            </h2>
            <div id="collapseProj<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#accordionProyecto">
              <div class="accordion-body bg-white p-3">
                <div class="table-wrap border-0 rounded-0">
                  <table class="table mb-0" style="font-size: 0.85rem;">
                    <thead>
                      <tr>
                        <th>Código RA</th>
                        <th>Resultado de Aprendizaje (RA)</th>
                        <th>Competencia Asociada</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($raPorFase[$fase] as $ra): ?>
                      <tr>
                        <td style="white-space: nowrap;"><strong><?= htmlspecialchars($ra['ra_code']) ?>-<?= str_pad((string)$ra['ra_num'], 2, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= htmlspecialchars($ra['denominacion']) ?></td>
                        <td>
                          <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($ra['competencia_code'] . ' - ' . ($parsed_proyecto['competencias'][$ra['competencia_code']] ?? '')) ?>">
                            <small class="badge bg-light text-dark border"><?= htmlspecialchars($ra['competencia_code']) ?></small>
                            <?= htmlspecialchars($parsed_proyecto['competencias'][$ra['competencia_code']] ?? 'Ver competencia') ?>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($raPorFase[$fase])): ?>
                      <tr>
                        <td colspan="3" class="text-center py-3 text-muted">No se encontraron resultados de aprendizaje asociados a esta fase en el PDF.</td>
                      </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>
