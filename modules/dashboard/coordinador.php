<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$pageTitle = 'Dashboard Coordinador · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$user = getCurrentUser();
$nombreUsuario = htmlspecialchars($user['nombre']);
$db = Database::getConnection();

// KPIs desde BD
try {
    // Fichas activas
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM fichas WHERE estado IN ('induccion', 'ejecucion')");
    $stmt->execute();
    $fichasActivas = $stmt->fetch()['count'] ?? 0;

    // Aprendices matriculados
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM aprendices WHERE estado = 'matriculado'");
    $stmt->execute();
    $aprendicesMatriculados = $stmt->fetch()['count'] ?? 0;

    // Instructores activos
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'instructor' AND estado = 'activo'");
    $stmt->execute();
    $instructoresActivos = $stmt->fetch()['count'] ?? 0;

    // Promedio retención (cumplimiento promedio)
    $stmt = $db->prepare("SELECT AVG(cumplimiento_porcentaje) as promedio FROM fichas WHERE cumplimiento_porcentaje > 0");
    $stmt->execute();
    $retencioPromedio = round((float)($stmt->fetch()['promedio'] ?? 0), 1);

    // Fichas críticas (< 60%)
    $stmt = $db->prepare("SELECT f.id, f.numero_ficha, p.nombre as programa, u.nombre as instructor, f.cumplimiento_porcentaje, f.estado FROM fichas f JOIN programas p ON f.programa_id = p.id JOIN usuarios u ON f.instructor_id = u.id WHERE f.cumplimiento_porcentaje < 60 ORDER BY f.cumplimiento_porcentaje ASC LIMIT 5");
    $stmt->execute();
    $fichasCriticas = $stmt->fetchAll();

    // Cumplimiento por programa (Analítica Avanzada: Promedio, Min, Max, Volumen)
    $stmt = $db->prepare("
        SELECT 
            p.nombre, 
            AVG(f.cumplimiento_porcentaje) as promedio,
            COUNT(DISTINCT a.id) as total_aprendices,
            MAX(f.cumplimiento_porcentaje) as max_cumplimiento,
            MIN(f.cumplimiento_porcentaje) as min_cumplimiento
        FROM programas p
        LEFT JOIN fichas f ON p.id = f.programa_id
        LEFT JOIN aprendices a ON f.id = a.ficha_id AND a.estado = 'matriculado'
        GROUP BY p.id, p.nombre 
        ORDER BY promedio DESC
    ");
    $stmt->execute();
    $cumplimientoProgramas = $stmt->fetchAll();

    // NUEVO: Radar & Retención (Por Programa)
    $stmt = $db->prepare("
        SELECT 
            p.nombre as programa,
            AVG(f.cumplimiento_porcentaje) as cumplimiento_avg,
            COUNT(DISTINCT f.id) as fichas_count,
            SUM(CASE WHEN a.estado = 'matriculado' THEN 1 ELSE 0 END) as matriculados,
            SUM(CASE WHEN a.estado = 'desertado' THEN 1 ELSE 0 END) as desertados
        FROM programas p
        LEFT JOIN fichas f ON p.id = f.programa_id
        LEFT JOIN aprendices a ON f.id = a.ficha_id
        GROUP BY p.id, p.nombre
        ORDER BY matriculados DESC
        LIMIT 5
    ");
    $stmt->execute();
    $statsProgramas = $stmt->fetchAll();

    // NUEVO: Top 5 Instructores por cumplimiento
    $stmt = $db->prepare("
        SELECT 
            u.nombre, 
            u.avatar_color,
            AVG(f.cumplimiento_porcentaje) as promedio,
            COUNT(f.id) as fichas_asignadas
        FROM usuarios u 
        JOIN fichas f ON u.id = f.instructor_id 
        WHERE u.rol = 'instructor' 
        GROUP BY u.id, u.nombre, u.avatar_color
        ORDER BY promedio DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $topInstructores = $stmt->fetchAll();

    // Fichas por estado para sparkline
    $stmt = $db->query("
        SELECT estado, COUNT(*) as count 
        FROM fichas 
        GROUP BY estado 
        ORDER BY FIELD(estado, 'planeacion', 'induccion', 'ejecucion', 'cierre')
    ");
    $fichasPorEstadoData = $stmt->fetchAll();
    $fichasEstadosMap = ['planeacion' => 0, 'induccion' => 0, 'ejecucion' => 0, 'cierre' => 0];
    foreach ($fichasPorEstadoData as $f) {
        if ($f['estado'] !== null) {
            $fichasEstadosMap[$f['estado']] = (int)$f['count'];
        }
    }

    // Aprendices por estado para sparkline
    $stmt = $db->query("
        SELECT estado, COUNT(*) as count 
        FROM aprendices 
        GROUP BY estado 
        ORDER BY FIELD(estado, 'matriculado', 'suspendido', 'desertado', 'egresado')
    ");
    $aprendicesPorEstadoData = $stmt->fetchAll();
    $aprendicesEstadosMap = ['matriculado' => 0, 'suspendido' => 0, 'desertado' => 0, 'egresado' => 0];
    foreach ($aprendicesPorEstadoData as $a) {
        if ($a['estado'] !== null) {
            $aprendicesEstadosMap[$a['estado']] = (int)$a['count'];
        }
    }

    // Instructores por estado para sparkline
    $stmt = $db->query("
        SELECT estado, COUNT(*) as count 
        FROM usuarios 
        WHERE rol = 'instructor' 
        GROUP BY estado 
        ORDER BY FIELD(estado, 'activo', 'inactivo', 'bloqueado')
    ");
    $instructoresPorEstadoData = $stmt->fetchAll();
    $instructoresEstadosMap = ['activo' => 0, 'inactivo' => 0, 'bloqueado' => 0];
    foreach ($instructoresPorEstadoData as $inst) {
        if ($inst['estado'] !== null) {
            $instructoresEstadosMap[$inst['estado']] = (int)$inst['count'];
        }
    }

    // Fichas cumplimiento para sparkline
    $stmt = $db->query("
        SELECT numero_ficha, cumplimiento_porcentaje 
        FROM fichas 
        ORDER BY numero_ficha ASC 
        LIMIT 6
    ");
    $fichasCumplimientoData = $stmt->fetchAll();

} catch (Exception $e) {
    $fichasActivas = 0;
    $aprendicesMatriculados = 0;
    $instructoresActivos = 0;
    $retencioPromedio = 0;
    $fichasCriticas = [];
    $cumplimientoProgramas = [];
    $statsProgramas = [];
    $topInstructores = [];
    $fichasEstadosMap = ['planeacion' => 0, 'induccion' => 0, 'ejecucion' => 0, 'cierre' => 0];
    $aprendicesEstadosMap = ['matriculado' => 0, 'suspendido' => 0, 'desertado' => 0, 'egresado' => 0];
    $instructoresEstadosMap = ['activo' => 0, 'inactivo' => 0, 'bloqueado' => 0];
    $fichasCumplimientoData = [];
}

?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
  <div>
    <h1 class="mb-1">Hola, <?= $nombreUsuario ?> 👋</h1>
    <p class="text-muted mb-0">Resumen institucional de seguimiento académico.</p>
  </div>
  <div class="w-100 w-sm-auto">
    <a href="#" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Nuevo Usuario</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="icon-bg"><i class="bi bi-journal-bookmark"></i></div>
        <div class="label">Fichas Activas</div>
        <div class="value"><?= $fichasActivas ?> <span class="trend up"><i class="bi bi-arrow-up-right"></i> +5%</span></div>
      </div>
      <div class="sparkline-container"><canvas id="sparkFichas"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="icon-bg"><i class="bi bi-people"></i></div>
        <div class="label">Aprendices</div>
        <div class="value"><?= $aprendicesMatriculados ?> <span class="trend up"><i class="bi bi-arrow-up-right"></i> +12%</span></div>
      </div>
      <div class="sparkline-container"><canvas id="sparkAprendices"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="icon-bg"><i class="bi bi-person-workspace"></i></div>
        <div class="label">Instructores</div>
        <div class="value"><?= $instructoresActivos ?> <span class="trend up"><i class="bi bi-dash"></i> 0%</span></div>
      </div>
      <div class="sparkline-container"><canvas id="sparkInstructores"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="kpi-content">
        <div class="icon-bg"><i class="bi bi-graph-up"></i></div>
        <div class="label">Retención Prom.</div>
        <div class="value"><?= $retencioPromedio ?>% <span class="trend <?= $retencioPromedio >= 80 ? 'up' : 'down' ?>"><i class="bi <?= $retencioPromedio >= 80 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' ?>"></i></span></div>
      </div>
      <div class="sparkline-container"><canvas id="sparkRetencion"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        Analítica de Cumplimiento vs Volumen
        <span class="badge-soft primary">Innovador</span>
      </div>
      <div class="card-body">
        <canvas id="chartProg" height="280"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        Estado de fichas
      </div>
      <div class="card-body">
        <canvas id="chartPie" height="180"></canvas>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($fichasCriticas)): ?>
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100 glass-card">
      <div class="card-header">Tasa de Deserción por Programa</div>
      <div class="card-body">
        <canvas id="chartDesercionRate" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100 glass-card">
      <div class="card-header">Retención vs Deserción</div>
      <div class="card-body">
        <canvas id="chartRetencion" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100 glass-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-trophy text-warning me-2"></i>Top Instructores</span>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush" style="background:transparent;">
          <?php foreach ($topInstructores as $idx => $inst): ?>
          <li class="list-group-item d-flex align-items-center gap-3 p-3" style="background:transparent; border-color:rgba(255,255,255,0.05);">
            <div class="avatar" style="background:<?= htmlspecialchars($inst['avatar_color']) ?>; width:40px; height:40px; font-size:1.1rem;">
              <?= strtoupper(substr($inst['nombre'], 0, 1)) ?>
            </div>
            <div class="flex-grow-1">
              <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($inst['nombre']) ?></h6>
              <small class="text-muted"><?= $inst['fichas_asignadas'] ?> fichas asignadas</small>
            </div>
            <div class="text-end">
              <div class="fw-bold text-success"><?= round((float)$inst['promedio'], 1) ?>%</div>
              <small class="text-muted">Cumplimiento</small>
            </div>
          </li>
          <?php endforeach; ?>
          <?php if(empty($topInstructores)): ?>
            <li class="list-group-item text-center text-muted p-4" style="background:transparent;">No hay instructores registrados.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <?php if (!empty($fichasCriticas)): ?>
      <div class="card glass-card h-100 border-0">
        <div class="card-header d-flex justify-content-between align-items-center" style="border-bottom:1px solid rgba(255,255,255,0.1)">
          <span><i class="bi bi-exclamation-triangle text-danger me-2"></i>Alertas críticas — Fichas con cumplimiento menor al 60%</span>
          <a href="<?= MODULES_PATH ?>/fichas/" class="small text-danger fw-semibold">Ver todas</a>
        </div>
        <div class="table-responsive" style="border:0;border-radius:0;background:transparent; -webkit-overflow-scrolling: touch;">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Ficha</th>
                <th class="d-none d-sm-table-cell">Programa</th>
                <th class="d-none d-md-table-cell">Instructor</th>
                <th>Cumplimiento</th>
                <th class="d-none d-sm-table-cell">Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fichasCriticas as $ficha): ?>
              <tr style="background:var(--danger-bg)">
                <td><strong>#<?= htmlspecialchars($ficha['numero_ficha']) ?></strong></td>
                <td class="d-none d-sm-table-cell"><?= htmlspecialchars($ficha['programa']) ?></td>
                <td class="d-none d-md-table-cell"><?= htmlspecialchars($ficha['instructor']) ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress-flat danger" style="width:120px">
                      <div style="width:<?= $ficha['cumplimiento_porcentaje'] ?>%"></div>
                    </div>
                    <span class="text-danger fw-semibold"><?= round((float)$ficha['cumplimiento_porcentaje'], 1) ?>%</span>
                  </div>
                </td>
                <td class="d-none d-sm-table-cell"><span class="badge-soft danger"><?= htmlspecialchars($ficha['estado']) ?></span></td>
                <td><a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-sm btn-soft">Ver</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <!-- Tarjeta premium de "Institución al Día" -->
      <div class="card border-0 h-100 shadow-sm" style="border-left: 5px solid var(--success) !important; border-radius: 12px; background: var(--success-bg);">
        <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center h-100">
          <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px; background-color: var(--sena-primary-50); color: var(--success); border: 1.5px solid var(--sena-primary-50);">
            <i class="bi bi-shield-fill-check" style="font-size: 1.8rem; color: var(--success);"></i>
          </div>
          <h4 class="mb-2 fw-bold text-success">¡Fichas al Día!</h4>
          <p class="text-muted mb-0" style="max-width: 400px; font-size: 0.85rem;">
            Todas las fichas de formación superan el 60% de cumplimiento académico. No se reportan alertas de rendimiento crítico en este momento.
          </p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="card h-100" style="border-radius: 10px;">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>Próximos Eventos</span>
        <a href="<?= APP_URL ?>/modules/calendario/" class="text-primary small fw-semibold" style="font-size: 0.75rem;"><i class="bi bi-calendar3 me-1"></i>Ver todo</a>
      </div>
      <div class="card-body" id="dashboard-events-list" style="max-height: 380px; overflow-y: auto;">
        <div class="text-center py-4 text-muted" id="events-loader">
          <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
          <div class="small">Cargando eventos...</div>
        </div>
      </div>
    </div>
  </div>
</div>


<div class="row g-3">
  <div class="col-12 col-md-4"><a href="<?= MODULES_PATH ?>/fichas/" class="btn btn-soft w-100 py-3"><i class="bi bi-journal-bookmark me-2"></i>Gestionar Fichas</a></div>
  <div class="col-12 col-md-4"><a href="<?= APP_URL ?>/index.php/programas" class="btn btn-soft w-100 py-3"><i class="bi bi-briefcase me-2"></i>Ver Programas</a></div>
  <div class="col-12 col-md-4"><a href="#" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario" class="btn btn-primary w-100 py-3"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</a></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const css = getComputedStyle(document.documentElement);
    const primaryColor = css.getPropertyValue('--sena-primary').trim();
    
    // Función reutilizable para crear Sparklines premium e interactivos
    function createSparkline(id, labels, data, color, fillGradStart, isPercentage = false) {
        if (!document.getElementById(id)) return;
        const ctx = document.getElementById(id).getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 45);
        grad.addColorStop(0, fillGradStart);
        grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.length > 0 ? labels : ['Sin datos'],
                datasets: [{
                    data: data.length > 0 ? data : [0],
                    borderColor: color,
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: color,
                    pointBorderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { size: 10, family: "'Inter', sans-serif", weight: '600' },
                        bodyFont: { size: 10, family: "'Inter', sans-serif" },
                        padding: 6,
                        cornerRadius: 6,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed.y;
                                return isPercentage ? ` Progreso: ${val}%` : ` Cantidad: ${val}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // Inicializar sparklines con datos reales consultados de BD
    createSparkline(
        'sparkFichas', 
        ['Planeación', 'Inducción', 'Ejecución', 'Cierre'],
        <?= json_encode([
            $fichasEstadosMap['planeacion'],
            $fichasEstadosMap['induccion'],
            $fichasEstadosMap['ejecucion'],
            $fichasEstadosMap['cierre']
        ]) ?>,
        primaryColor,
        'rgba(57, 169, 0, 0.25)'
    );

    createSparkline(
        'sparkAprendices', 
        ['Matriculados', 'Suspendidos', 'Desertados', 'Egresados'],
        <?= json_encode([
            $aprendicesEstadosMap['matriculado'],
            $aprendicesEstadosMap['suspendido'],
            $aprendicesEstadosMap['desertado'],
            $aprendicesEstadosMap['egresado']
        ]) ?>,
        '#3B82F6',
        'rgba(59, 130, 246, 0.25)'
    );

    createSparkline(
        'sparkInstructores', 
        ['Activos', 'Inactivos', 'Bloqueados'],
        <?= json_encode([
            $instructoresEstadosMap['activo'],
            $instructoresEstadosMap['inactivo'],
            $instructoresEstadosMap['bloqueado']
        ]) ?>,
        '#8B5CF6',
        'rgba(139, 92, 246, 0.25)'
    );

    createSparkline(
        'sparkRetencion', 
        <?= json_encode(array_map(fn($f) => "Ficha #" . $f['numero_ficha'], $fichasCumplimientoData)) ?>,
        <?= json_encode(array_map(fn($f) => round((float)$f['cumplimiento_porcentaje'], 1), $fichasCumplimientoData)) ?>,
        '#F59E0B',
        'rgba(245, 158, 11, 0.25)',
        true
    );

    // Plugin personalizado para efecto "Glow" (Neón) en líneas
    const neonGlowPlugin = {
        id: 'neonGlow',
        beforeDatasetsDraw: (chart) => {
            const ctx = chart.ctx;
            chart.data.datasets.forEach((dataset, i) => {
                if (dataset.type === 'line' && chart.getDatasetMeta(i).hidden === false) {
                    ctx.save();
                    ctx.shadowColor = dataset.borderColor;
                    ctx.shadowBlur = 15;
                    ctx.shadowOffsetX = 0;
                    ctx.shadowOffsetY = 4;
                }
            });
        },
        afterDatasetsDraw: (chart) => {
            chart.ctx.restore();
        }
    };
    Chart.register(neonGlowPlugin);

    // Chart Principal: Analítica Avanzada (Mixed Chart Innovador)
    if (document.getElementById('chartProg')) {
        const ctxProg = document.getElementById('chartProg').getContext('2d');
        
        // Gradientes para Volumen (Barras)
        const gradBar = ctxProg.createLinearGradient(0, 0, 0, 400);
        gradBar.addColorStop(0, 'rgba(59, 130, 246, 0.4)'); // Azul suave
        gradBar.addColorStop(1, 'rgba(59, 130, 246, 0)');
        
        // Gradientes para Cumplimiento (Línea)
        const gradLine = ctxProg.createLinearGradient(0, 0, 0, 400);
        gradLine.addColorStop(0, 'rgba(57, 169, 0, 0.5)'); // Verde SENA
        gradLine.addColorStop(1, 'rgba(57, 169, 0, 0)');

        const programasData = <?php echo json_encode(array_map(fn($p) => [
            'nombre' => $p['nombre'], 
            'promedio' => round((float)$p['promedio'], 1),
            'volumen' => (int)$p['total_aprendices'],
            'min' => round((float)$p['min_cumplimiento'], 1),
            'max' => round((float)$p['max_cumplimiento'], 1)
        ], $cumplimientoProgramas)); ?>;
        
        const labels = programasData.map(p => p.nombre.length > 20 ? p.nombre.substring(0, 20) + '...' : p.nombre);
        const dataPromedio = programasData.map(p => p.promedio);
        const dataVolumen = programasData.map(p => p.volumen);
        
        // Simulación de "Meta Institucional" para análisis comparativo
        const dataMeta = Array(labels.length).fill(80);

        new Chart(ctxProg, {
            type: 'line',
            data: {
                labels: labels.length > 0 ? labels : ['Sin datos'],
                datasets: [
                    {
                        type: 'line',
                        label: 'Meta Institucional',
                        data: dataMeta,
                        borderColor: 'rgba(239, 68, 68, 0.6)', // Rojo suave
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0,
                        tension: 0,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Cumplimiento Promedio (%)',
                        data: dataPromedio.length > 0 ? dataPromedio : [0],
                        backgroundColor: gradLine,
                        borderColor: primaryColor,
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: primaryColor,
                        pointBorderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 4,
                        yAxisID: 'y'
                    },
                    {
                        type: 'bar',
                        label: 'Volumen de Aprendices',
                        data: dataVolumen.length > 0 ? dataVolumen : [0],
                        backgroundColor: gradBar,
                        borderColor: 'rgba(59, 130, 246, 0.8)',
                        borderWidth: { top: 2, right: 0, bottom: 0, left: 0 },
                        borderRadius: { topLeft: 6, topRight: 6 },
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        display: true, 
                        position: 'top', 
                        labels: { usePointStyle: true, boxWidth: 8, font: { weight: '600' } } 
                    },
                    tooltip: { 
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', 
                        titleFont: { size: 14, family: "'Inter', sans-serif" }, 
                        bodyFont: { size: 13, family: "'Inter', sans-serif" }, 
                        padding: 16, 
                        cornerRadius: 12,
                        usePointStyle: true,
                        boxPadding: 6,
                        callbacks: {
                            afterBody: function(context) {
                                // Agregar min y max al tooltip para mayor analítica
                                const index = context[0].dataIndex;
                                const pData = programasData[index];
                                if (pData) {
                                    return `\nDispersión:\nMax: ${pData.max}%\nMin: ${pData.min}%`;
                                }
                            }
                        }
                    }
                },
                scales: { 
                    y: { 
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true, 
                        max: 100, 
                        title: { display: true, text: 'Cumplimiento (%)', font: { weight: 'bold', size: window.innerWidth < 576 ? 10 : 12 } },
                        ticks: { font: { size: window.innerWidth < 576 ? 9 : 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } 
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: { display: true, text: 'N° Aprendices', font: { weight: 'bold', size: window.innerWidth < 576 ? 10 : 12 }, color: '#3B82F6' },
                        grid: { drawOnChartArea: false, drawBorder: false },
                        ticks: { color: '#3B82F6', font: { size: window.innerWidth < 576 ? 9 : 11 } }
                    },
                    x: { 
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            font: { 
                                weight: '500',
                                size: window.innerWidth < 576 ? 9 : 11
                            },
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 10,
                            maxRotation: 45,
                            minRotation: window.innerWidth < 576 ? 45 : 0
                        }
                    }
                },
                interaction: { intersect: false, mode: 'index' },
                animation: {
                    tension: {
                        duration: 1000,
                        easing: 'linear',
                        from: 1,
                        to: 0.4,
                        loop: false
                    }
                }
            }
        });
    }

    // Chart de estados de fichas (Doughnut Premium)
    if (document.getElementById('chartPie')) {
        new Chart(document.getElementById('chartPie'), {
            type: 'doughnut',
            data: {
                labels: ['Ejecución', 'Inducción', 'Planeación', 'Cierre'],
                datasets: [{
                    data: [
                        <?php 
                        try { echo $db->query("SELECT COUNT(*) FROM fichas WHERE estado = 'ejecucion'")->fetchColumn() ?: '0'; } catch (Exception $e) { echo '0'; }
                        ?>,
                        <?php 
                        try { echo $db->query("SELECT COUNT(*) FROM fichas WHERE estado = 'induccion'")->fetchColumn() ?: '0'; } catch (Exception $e) { echo '0'; }
                        ?>,
                        <?php 
                        try { echo $db->query("SELECT COUNT(*) FROM fichas WHERE estado = 'planeacion'")->fetchColumn() ?: '0'; } catch (Exception $e) { echo '0'; }
                        ?>,
                        <?php 
                        try { echo $db->query("SELECT COUNT(*) FROM fichas WHERE estado = 'cierre'")->fetchColumn() ?: '0'; } catch (Exception $e) { echo '0'; }
                        ?>
                    ],
                    backgroundColor: [primaryColor, '#3B82F6', '#F59E0B', '#8B5CF6'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: { 
                cutout: '75%', 
                plugins: { 
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            padding: window.innerWidth < 576 ? 10 : 20, 
                            usePointStyle: true, 
                            pointStyle: 'circle',
                            font: { size: window.innerWidth < 576 ? 10 : 12 }
                        } 
                    },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8 }
                } 
            }
        });
    }

    // Datos comparativos PHP a JS
    const statsProgramas = <?php echo json_encode($statsProgramas); ?>;
    const radarLabels = statsProgramas.map(p => p.programa.substring(0, 15) + '...');
    
    // Chart de Tasa de Deserción por Programa (Barra Horizontal)
    if (document.getElementById('chartDesercionRate') && statsProgramas.length > 0) {
        const desercionRates = statsProgramas.map(p => {
            const matriculados = parseInt(p.matriculados || 0);
            const desertados = parseInt(p.desertados || 0);
            const total = matriculados + desertados;
            return total > 0 ? Math.round((desertados / total) * 100) : 0;
        });

        new Chart(document.getElementById('chartDesercionRate'), {
            type: 'bar',
            data: {
                labels: radarLabels,
                datasets: [{
                    label: 'Tasa de Deserción (%)',
                    data: desercionRates,
                    backgroundColor: 'rgba(239, 68, 68, 0.75)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barThickness: 16
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) { return value + '%'; },
                            font: { family: "'Inter', sans-serif", size: window.innerWidth < 576 ? 9 : 11 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Inter', sans-serif", weight: '500', size: window.innerWidth < 576 ? 9 : 11 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { family: "'Inter', sans-serif" },
                        bodyFont: { family: "'Inter', sans-serif" },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                return ` Tasa de Deserción: ${context.parsed.x}%`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Stacked Bar (Retención vs Deserción)
    if (document.getElementById('chartRetencion') && statsProgramas.length > 0) {
        const dataMatriculados = statsProgramas.map(p => parseInt(p.matriculados || 0));
        const dataDesertados = statsProgramas.map(p => parseInt(p.desertados || 0));

        new Chart(document.getElementById('chartRetencion'), {
            type: 'bar',
            data: {
                labels: radarLabels,
                datasets: [
                    {
                        label: 'Matriculados',
                        data: dataMatriculados,
                        backgroundColor: primaryColor,
                        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 4, bottomRight: 4 },
                        borderSkipped: false
                    },
                    {
                        label: 'Desertados',
                        data: dataDesertados,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)', // red-500
                        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 4, bottomRight: 4 },
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: { 
                        stacked: true, 
                        grid: { display: false },
                        ticks: { 
                            font: { family: "'Inter', sans-serif", size: window.innerWidth < 576 ? 9 : 11 },
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 10,
                            maxRotation: 45,
                            minRotation: window.innerWidth < 576 ? 45 : 0
                        }
                    },
                    y: { 
                        stacked: true, 
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { 
                            font: { family: "'Inter', sans-serif", size: window.innerWidth < 576 ? 9 : 11 }
                        }
                    }
                },
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            boxWidth: 12, 
                            font: { size: window.innerWidth < 576 ? 10 : 12 } 
                        } 
                    },
                    tooltip: { mode: 'index', backgroundColor: 'rgba(0,0,0,0.8)' }
                }
            }
        });
    }
    // Cargar eventos del calendario para el dashboard
    const eventsList = document.getElementById('dashboard-events-list');
    if (eventsList) {
        const today = new Date();
        const formatDate = (d) => d.toISOString().split('T')[0];
        
        const start = formatDate(today);
        const next30Days = new Date(today);
        next30Days.setDate(today.getDate() + 30);
        const end = formatDate(next30Days);
        
        const url = `<?= APP_URL ?>/modules/calendario/api_events.php?start=${start}&end=${end}`;
        
        fetch(url)
            .then(res => res.json())
            .then(events => {
                const loader = document.getElementById('events-loader');
                if (loader) loader.remove();
                
                if (!events || events.length === 0) {
                    eventsList.innerHTML = `
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem; opacity:0.4;"></i>
                            Sin eventos programados para los próximos 30 días.
                        </div>
                    `;
                    return;
                }
                
                // Ordenar eventos por fecha ascendente
                events.sort((a, b) => new Date(a.start) - new Date(b.start));
                
                // Mostrar un máximo de 5 eventos
                const upcoming = events.slice(0, 5);
                
                let html = '';
                const formatLabel = (dateStr) => {
                    const parts = dateStr.split('-');
                    if (parts.length < 3) return dateStr;
                    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                    const t = new Date();
                    t.setHours(0,0,0,0);
                    d.setHours(0,0,0,0);
                    
                    const diffTime = d.getTime() - t.getTime();
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays === 0) return 'Hoy';
                    if (diffDays === 1) return 'Mañ.';
                    
                    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                    return `${d.getDate()} ${months[d.getMonth()]}`;
                };

                upcoming.forEach(ev => {
                    const color = ev.color || '#39A900';
                    const targetUrl = ev.url || '#';
                    const title = ev.title || 'Evento';
                    const tipo = (ev.extendedProps && ev.extendedProps.tipo) || 'Académico';
                    const extra = (ev.extendedProps && (ev.extendedProps.ficha || ev.extendedProps.programa || ev.extendedProps.instructor)) || '';
                    
                    html += `
                        <a href="${targetUrl}" class="event-item">
                            <span class="event-badge-dot" style="background-color: ${color};"></span>
                            <div class="event-body">
                                <div class="event-title">${title}</div>
                                <div class="event-desc">${tipo}${extra ? ' · ' + extra : ''}</div>
                            </div>
                            <span class="event-date-badge">${formatLabel(ev.start)}</span>
                        </a>
                    `;
                });
                eventsList.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                const loader = document.getElementById('events-loader');
                if (loader) loader.remove();
                eventsList.innerHTML = `
                    <div class="text-center py-5 text-danger">
                        <i class="bi bi-exclamation-octagon d-block mb-2" style="font-size:2rem;"></i>
                        Error al cargar los eventos.
                    </div>
                `;
            });
    }

});
</script>
