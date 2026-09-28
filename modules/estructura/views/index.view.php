<?php
declare(strict_types=1);

// Esta vista solo debe renderizarse desde un controlador, a traves del
// layout. Abierta directamente por URL, se ejecutaria sin las variables
// que espera y sin ninguna comprobacion de permisos.
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}

$kpis = [
    ['Programas',    'bi-book',            (int)($totales['programas'] ?? 0),    '/programas'],
    ['Competencias', 'bi-diagram-3',       (int)($totales['competencias'] ?? 0), '/competencias'],
    ['Resultados (RAP)', 'bi-clipboard-check', (int)($totales['resultados'] ?? 0), '/resultados-aprendizaje'],
    ['Proyectos',    'bi-kanban',          (int)($totales['proyectos'] ?? 0),    '/proyectos'],
];
?>
<div class="page-header">
  <div>
    <h1 class="mb-1">Estructura Curricular</h1>
    <p class="text-muted mb-0">Resumen de programas, competencias, resultados de aprendizaje y proyectos formativos. Cada catálogo se administra en su propia sección.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(APP_URL . '/index.php/estructura/importar') ?>" class="btn btn-primary">
      <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar PDF
    </a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?= e($err) ?></div></div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
  <?php foreach ($kpis as [$etiqueta, $icono, $valor, $ruta]): ?>
  <div class="col-6 col-lg-3">
    <a href="<?= e(APP_URL . '/index.php' . $ruta) ?>" class="text-decoration-none d-block">
      <div class="kpi">
        <div class="kpi-content">
          <div class="icon-bg"><i class="bi <?= e($icono) ?>"></i></div>
          <div class="label"><?= e($etiqueta) ?></div>
          <div class="value"><?= $valor ?> <i class="bi bi-arrow-right-short text-muted fs-4"></i></div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-collection-play me-2 text-primary"></i>Programas</span>
        <a href="<?= e(APP_URL . '/index.php/programas') ?>" class="btn btn-sm btn-soft">Administrar</a>
      </div>
      <div class="card-body p-0">
        <div class="table-wrap border-0 rounded-0">
          <table class="table mb-0">
            <thead><tr><th>Programa</th><th class="text-center">Competencias</th><th class="text-center">RAP</th><th>Estado</th></tr></thead>
            <tbody>
              <?php foreach ($programas as $p): ?>
              <tr>
                <td>
                  <strong class="d-block text-uppercase-visual"><?= e($p['nombre']) ?></strong>
                  <small class="text-muted"><code class="text-uppercase-visual"><?= e($p['codigo']) ?></code> · <?= (int)$p['duracion_horas'] ?> h</small>
                </td>
                <td class="text-center"><?= (int)$p['total_competencias'] ?></td>
                <td class="text-center"><?= (int)$p['total_rap'] ?></td>
                <td><span class="badge-soft <?= $p['estado'] === 'activo' ? 'success' : 'secondary' ?>"><?= e(ucfirst($p['estado'])) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($programas)): ?>
              <tr><td colspan="4" class="text-center py-4 text-muted">No hay programas registrados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-kanban me-2 text-primary"></i>Proyectos formativos</span>
        <a href="<?= e(APP_URL . '/index.php/proyectos') ?>" class="btn btn-sm btn-soft">Administrar</a>
      </div>
      <div class="card-body p-0">
        <div class="table-wrap border-0 rounded-0">
          <table class="table mb-0">
            <thead><tr><th>Proyecto</th><th class="text-center">Fases</th><th class="text-center">Fichas</th><th>Estado</th></tr></thead>
            <tbody>
              <?php foreach ($proyectos as $pj): ?>
              <tr>
                <td>
                  <strong class="d-block text-uppercase-visual"><?= e($pj['nombre']) ?></strong>
                  <small class="text-muted"><code class="text-uppercase-visual"><?= e($pj['codigo']) ?></code></small>
                </td>
                <td class="text-center"><?= (int)$pj['total_fases'] ?></td>
                <td class="text-center"><?= (int)$pj['total_fichas'] ?></td>
                <td><span class="badge-soft <?= $pj['estado'] === 'activo' ? 'success' : ($pj['estado'] === 'finalizado' ? 'info' : 'secondary') ?>"><?= e(ucfirst($pj['estado'])) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($proyectos)): ?>
              <tr><td colspan="4" class="text-center py-4 text-muted">No hay proyectos formativos registrados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
