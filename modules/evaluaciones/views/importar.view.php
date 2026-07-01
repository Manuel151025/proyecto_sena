<?php
declare(strict_types=1);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Importar Juicios Evaluativos</h1>
    <p class="text-muted mb-0">Carga el reporte de juicios evaluativos desde Excel para registrar notas, fichas y aprendices automáticamente.</p>
  </div>
  <div>
    <a href="<?= APP_URL ?>/index.php/evaluaciones" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver a Evaluaciones
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-4">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>
    <?php foreach ($errors as $e) echo $e . '<br>'; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($successMessage): ?>
<div class="alert-flat success mb-4">
  <i class="bi bi-check-circle-fill"></i>
  <div><?= htmlspecialchars($successMessage) ?></div>
</div>
<?php endif; ?>

<!-- RESUMEN DE LA IMPORTACIÓN -->
<?php if ($import_summary): ?>
<div class="card border-0 shadow-sm mb-4" style="border-top: 4px solid var(--sena-primary); border-radius: 12px;">
  <div class="card-body p-4">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Resumen de Datos Cargados</h5>
    
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="p-3 border rounded bg-light">
          <small class="text-muted d-block">Ficha de Caracterización</small>
          <strong style="font-size: 1.1rem;">#<?= htmlspecialchars($import_summary['ficha_num']) ?></strong>
          <span class="badge bg-soft primary d-block mt-1"><?= htmlspecialchars($import_summary['ficha_estado']) ?></span>
        </div>
      </div>
      <div class="col-sm-6 col-lg-5">
        <div class="p-3 border rounded bg-light">
          <small class="text-muted d-block">Programa de Formación</small>
          <strong style="font-size: 1.1rem;"><?= htmlspecialchars($import_summary['programa']) ?></strong>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle" style="font-size:0.9rem;">
        <thead class="table-light">
          <tr>
            <th>Concepto / Entidad</th>
            <th class="text-center" style="width: 150px;">Registros Creados</th>
            <th class="text-center" style="width: 150px;">Registros Actualizados</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Aprendices</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['aprendices_creados'] ?></td>
            <td class="text-center text-primary"><?= $import_summary['aprendices_actualizados'] ?></td>
          </tr>
          <tr>
            <td><strong>Competencias</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['competencias_creadas'] ?></td>
            <td class="text-center text-muted">0</td>
          </tr>
          <tr>
            <td><strong>Resultados de Aprendizaje (RAs)</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['ras_creados'] ?></td>
            <td class="text-center text-muted">0</td>
          </tr>
          <tr>
            <td><strong>Juicios de Evaluación (A/D)</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['evaluaciones_creadas'] ?></td>
            <td class="text-center text-primary"><?= $import_summary['evaluaciones_actualizadas'] ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <?php if (!empty($import_summary['detalles'])): ?>
    <div class="mt-4 border-top pt-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Listado Detallado de Juicios Importados</h6>
        <div class="d-flex align-items-center gap-2">
          <input type="text" id="detailSearchInput" class="form-control form-control-sm" placeholder="🔍 Buscar aprendiz o documento..." style="max-width: 250px; border-radius: 8px;">
        </div>
      </div>

      <div class="table-responsive" style="max-height: 450px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
        <table class="table table-hover align-middle mb-0" id="detailsTable" style="font-size:0.85rem;">
          <thead class="table-light sticky-top" style="z-index: 1;">
            <tr>
              <th style="width: 180px;">Documento</th>
              <th>Aprendiz</th>
              <th class="text-center" style="width: 150px;">Juicios Procesados</th>
              <th class="text-center" style="width: 250px;">Resumen de Acciones</th>
              <th class="text-center" style="width: 100px;">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $apIndex = 0;
            foreach ($import_summary['detalles'] as $ap): 
              $apIndex++;
              $detailsId = "details-" . $apIndex;
              $iconId = "icon-" . $detailsId;
              
              // Contar estados
              $creados = 0; 
              $actualizados = 0; 
              $sin_cambios = 0;
              foreach ($ap['juicios'] as $j) {
                  if ($j['eval_accion'] === 'Creado') $creados++;
                  elseif ($j['eval_accion'] === 'Actualizado') $actualizados++;
                  else $sin_cambios++;
              }
              
              $resumen_acciones = [];
              if ($creados > 0) $resumen_acciones[] = "<span class='badge bg-soft success text-success'>$creados Nuevos</span>";
              if ($actualizados > 0) $resumen_acciones[] = "<span class='badge bg-soft primary text-primary'>$actualizados Act.</span>";
              if ($sin_cambios > 0) $resumen_acciones[] = "<span class='badge bg-soft secondary text-secondary'>$sin_cambios S/C</span>";
              
              $resumenHtml = implode(' ', $resumen_acciones);
            ?>
            <tr class="cursor-pointer" onclick="toggleDetails('<?= $detailsId ?>')" data-details-id="<?= $detailsId ?>" style="transition: background-color 0.2s;">
              <td>
                <i class="bi bi-chevron-right me-2 text-muted" id="<?= $iconId ?>" style="transition: transform 0.2s; display: inline-block;"></i>
                <?= htmlspecialchars($ap['documento']) ?>
              </td>
              <td><strong><?= htmlspecialchars($ap['nombre']) ?></strong></td>
              <td class="text-center fw-semibold text-dark"><?= count($ap['juicios']) ?> Juicios</td>
              <td class="text-center"><?= $resumenHtml ?></td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-primary py-0.5 px-2" style="font-size: 0.75rem; border-radius: 6px;">
                  Ver
                </button>
              </td>
            </tr>
            <tr id="<?= $detailsId ?>" style="display: none; background-color: #f8fafc;">
              <td colspan="5" class="p-3">
                <div class="px-4 py-3 border rounded bg-white shadow-sm" style="border-radius: 8px;">
                  <h6 class="fw-bold mb-3 text-muted" style="font-size: 0.8rem;"><i class="bi bi-journal-check me-2"></i>Detalle de Evaluaciones</h6>
                  <table class="table table-sm table-bordered mb-0 align-middle" style="font-size: 0.8rem;">
                    <thead class="table-light">
                      <tr>
                        <th>Competencia</th>
                        <th style="width: 120px;">Código RA</th>
                        <th>Resultado de Aprendizaje (RA)</th>
                        <th class="text-center" style="width: 120px;">Juicio</th>
                        <th class="text-center" style="width: 120px;">Acción de BD</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($ap['juicios'] as $j): 
                        $badgeConcepto = 'bg-secondary text-dark';
                        $textoConcepto = 'Pendiente';
                        if ($j['concepto'] === 'A') {
                            $badgeConcepto = 'bg-soft success text-success';
                            $textoConcepto = 'Aprobado';
                        } elseif ($j['concepto'] === 'D') {
                            $badgeConcepto = 'bg-soft danger text-danger';
                            $textoConcepto = 'Deficiente';
                        }
                        
                        $badgeAccion = 'bg-light text-dark';
                        if ($j['eval_accion'] === 'Creado') {
                            $badgeAccion = 'bg-soft success text-success';
                        } elseif ($j['eval_accion'] === 'Actualizado') {
                            $badgeAccion = 'bg-soft primary text-primary';
                        } else {
                            $badgeAccion = 'bg-soft secondary text-secondary';
                        }
                      ?>
                      <tr>
                        <td style="font-size: 0.75rem; color: #555;"><i class="bi bi-award me-1"></i><?= htmlspecialchars($j['competencia'] ?? '') ?></td>
                        <td><code class="text-dark bg-light px-1.5 py-0.5 rounded" style="font-size: 0.8rem;"><?= htmlspecialchars($j['ra_codigo']) ?></code></td>
                        <td style="font-size: 0.75rem;"><?= htmlspecialchars($j['ra_denom'] ?? '') ?></td>
                        <td class="text-center"><span class="badge <?= $badgeConcepto ?>" style="padding: 4px 8px; font-weight:600;"><?= $textoConcepto ?></span></td>
                        <td class="text-center"><span class="badge <?= $badgeAccion ?>" style="padding: 4px 8px; font-weight:600;"><?= htmlspecialchars($j['eval_accion']) ?></span></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- FORMULARIO DE CARGA -->
<?php if (!$import_summary): ?>
<div class="row">
  <div class="col-xl-6 mx-auto">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
      <div class="card-header bg-transparent py-3 border-bottom-0">
        <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-excel text-primary me-2"></i>Sube tu archivo .xls</h5>
      </div>
      <div class="card-body p-4 pt-0">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
          <p class="text-muted small mb-4">
            Selecciona el archivo binario de Excel (<strong>.xls</strong>) que contiene los juicios evaluativos. El sistema lo convertirá y procesará para crear la ficha, los aprendices, las competencias y todos los juicios de evaluación.
          </p>
          
          <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Archivo Excel (.xls)</label>
            <input type="file" id="excelFileInput" name="excel_file" class="form-control" accept=".xls" required>
            <div class="form-text text-muted" style="font-size:0.75rem;">
              * Asegúrate de no alterar el archivo original de juicios evaluativos.
            </div>
          </div>
          
          <!-- Barra de progreso (oculta inicialmente) -->
          <div id="uploadProgress" class="mb-3" style="display:none;">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
              <span id="uploadStatusText" class="small text-muted">Leyendo archivo...</span>
            </div>
            <div class="progress" style="height: 6px;">
              <div id="uploadProgressBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width: 0%"></div>
            </div>
          </div>

          <!-- Contenedor de errores AJAX -->
          <div id="ajaxErrors" class="alert-flat danger mb-3" style="display:none;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div id="ajaxErrorContent"></div>
          </div>
          
          <div class="d-grid">
            <button type="submit" id="submitBtn" class="btn btn-primary py-2.5 fw-bold">
              <i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Cargar Script del Cliente Desacoplado -->
<script src="<?= APP_URL ?>/assets/js/evaluaciones/importar.js"></script>
