?>

<div class="mb-4">
  <h1 class="mb-1">Centro de Reportes</h1>
  <p class="text-muted mb-0">Genera reportes de cumplimiento por instructor, ficha y competencia. Exporta en CSV y Excel.</p>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div></div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid var(--sena-primary);">
      <div class="kpi-content"><div class="label">Evaluaciones</div><div class="value"><?= (int)$stats['total_evaluaciones'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #22c55e;">
      <div class="kpi-content"><div class="label">Aprobados</div><div class="value" style="color:#22c55e;"><?= (int)$stats['aprobados'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #ef4444;">
      <div class="kpi-content"><div class="label">No Aprobados</div><div class="value" style="color:#ef4444;"><?= (int)$stats['reprobados'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #eab308;">
      <div class="kpi-content"><div class="label">Pendientes</div><div class="value" style="color:#eab308;"><?= (int)$stats['pendientes'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #3b82f6;">
      <div class="kpi-content"><div class="label">Fichas</div><div class="value" style="color:#3b82f6;"><?= (int)$stats['total_fichas'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #8b5cf6;">
      <div class="kpi-content"><div class="label">Cambios</div><div class="value" style="color:#8b5cf6;"><?= (int)$stats['cambios_historial'] ?></div></div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Reporte 1: Evaluaciones por Ficha -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid var(--sena-primary); border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-folder2-open text-primary" style="font-size: 2.5rem;"></i></div>
        <h5 class="fw-bold text-dark">Evaluaciones por Ficha</h5>
        <p class="text-muted small">Detalle de todos los juicios evaluativos (A/D) para cada aprendiz de una ficha especÃ­fica.</p>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="export" value="evaluaciones_ficha">
          <div class="mb-3">
            <select name="ficha_id" class="form-select form-select-sm" required
                    data-picker
                    data-picker-label="Seleccionar ficha"
                    data-picker-placeholder="NÃºmero de ficha o programa...">
              <option value="">Seleccionar ficha...</option>
              <?php foreach ($fichas as $f): ?>
              <option value="<?= $f['id'] ?>"
                      data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> â€” <?= htmlspecialchars($f['programa']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 2: Cumplimiento por Instructor -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid #3b82f6; border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-person-workspace" style="font-size: 2.5rem; color: #3b82f6;"></i></div>
        <h5 class="fw-bold text-dark">Cumplimiento por Instructor LÃ­der</h5>
        <p class="text-muted small">Cantidad de RAs evaluados vs faltantes agrupados por instructor lÃ­der y ficha asignada.</p>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="export" value="cumplimiento_instructor">
          <div class="d-flex gap-2 mt-4">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 3: Cumplimiento por Competencia -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid #22c55e; border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-diagram-3" style="font-size: 2.5rem; color: #22c55e;"></i></div>
        <h5 class="fw-bold text-dark">Cumplimiento por Competencia</h5>
        <p class="text-muted small">Porcentaje de aprobaciÃ³n por cada competencia y programa formativo a nivel institucional.</p>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="export" value="cumplimiento_competencia">
          <div class="d-flex gap-2 mt-4">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 4: Historial / Trazabilidad -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid #8b5cf6; border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-clock-history" style="font-size: 2.5rem; color: #8b5cf6;"></i></div>
        <h5 class="fw-bold text-dark">Historial de Cambios (Trazabilidad)</h5>
        <p class="text-muted small">Registro de todos los cambios de concepto evaluativo con fecha, responsable y motivo (RNF02).</p>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="export" value="historial_cambios">
          <div class="d-flex gap-2 mt-4">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- BotÃ³n imprimir para PDF -->
<div class="card glass-card border-0 mt-4 p-4 text-center" style="border-radius: 12px;">
  <h5 class="fw-bold mb-2"><i class="bi bi-printer me-2"></i>Exportar a PDF</h5>
  <p class="text-muted small mb-3">Utiliza la funciÃ³n de impresiÃ³n del navegador (Ctrl+P) y selecciona "Guardar como PDF" para generar reportes en formato PDF directamente desde cualquier vista del sistema.</p>
  <button onclick="window.print()" class="btn btn-outline-dark px-5"><i class="bi bi-file-earmark-pdf me-2"></i>Imprimir / Guardar como PDF</button>
</div>
