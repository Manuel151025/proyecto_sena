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

} catch (Exception $e) {
    $fichasActivas = 0;
    $aprendicesMatriculados = 0;
    $instructoresActivos = 0;
    $retencioPromedio = 0;
    $fichasCriticas = [];
    $cumplimientoProgramas = [];
    $statsProgramas = [];
    $topInstructores = [];
}

?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Hola, <?= $nombreUsuario ?> 👋</h1>
    <p class="text-muted mb-0">Resumen institucional de seguimiento académico.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= MODULES_PATH ?>/usuarios/crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo Usuario</a>
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
      <div class="card-header">Desempeño Integral</div>
      <div class="card-body">
        <canvas id="chartRadar" height="220"></canvas>
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

<div class="card glass-card mb-4 border-0">
  <div class="card-header d-flex justify-content-between align-items-center" style="border-bottom:1px solid rgba(255,255,255,0.1)">
    <span><i class="bi bi-exclamation-triangle text-danger me-2"></i>Alertas críticas — Fichas con cumplimiento menor al 60%</span>
    <a href="<?= MODULES_PATH ?>/fichas/" class="small text-danger fw-semibold">Ver todas</a>
  </div>
  <div class="table-wrap" style="border:0;border-radius:0;background:transparent;">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Ficha</th>
          <th>Programa</th>
          <th>Instructor</th>
          <th>Cumplimiento</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fichasCriticas as $ficha): ?>
        <tr style="background:var(--danger-bg)">
          <td><strong>#<?= htmlspecialchars($ficha['numero_ficha']) ?></strong></td>
          <td><?= htmlspecialchars($ficha['programa']) ?></td>
          <td><?= htmlspecialchars($ficha['instructor']) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress-flat danger" style="width:120px">
                <div style="width:<?= $ficha['cumplimiento_porcentaje'] ?>%"></div>
              </div>
              <span class="text-danger fw-semibold"><?= round((float)$ficha['cumplimiento_porcentaje'], 1) ?>%</span>
            </div>
          </td>
          <td><span class="badge-soft danger"><?= htmlspecialchars($ficha['estado']) ?></span></td>
          <td><a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>" class="btn btn-sm btn-soft">Ver</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-4"><a href="<?= MODULES_PATH ?>/fichas/" class="btn btn-soft w-100 py-3"><i class="bi bi-journal-bookmark me-2"></i>Gestionar Fichas</a></div>
  <div class="col-md-4"><a href="<?= MODULES_PATH ?>/programas/" class="btn btn-soft w-100 py-3"><i class="bi bi-briefcase me-2"></i>Ver Programas</a></div>
  <div class="col-md-4"><a href="<?= MODULES_PATH ?>/usuarios/crear.php" class="btn btn-primary w-100 py-3"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</a></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const css = getComputedStyle(document.documentElement);
    const primaryColor = css.getPropertyValue('--sena-primary').trim();
    
    // Configuración común para Sparklines
    const sparklineOptions = {
        type: 'line',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } },
            layout: { padding: 0 },
            elements: { point: { radius: 0 }, line: { tension: 0.4, borderWidth: 2 } }
        }
    };

    // Renderizar Sparklines aleatorios simulando tendencia
    ['sparkFichas', 'sparkAprendices', 'sparkInstructores', 'sparkRetencion'].forEach(id => {
        if (!document.getElementById(id)) return;
        const ctx = document.getElementById(id).getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 45);
        grad.addColorStop(0, 'rgba(57, 169, 0, 0.4)');
        grad.addColorStop(1, 'rgba(57, 169, 0, 0)');
        
        const data = Array.from({length: 10}, () => Math.floor(Math.random() * 40) + 60);
        new Chart(ctx, {
            ...sparklineOptions,
            data: {
                labels: data.map((_, i) => i),
                datasets: [{ data: data, borderColor: primaryColor, backgroundColor: grad, fill: true }]
            }
        });
    });

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
                        title: { display: true, text: 'Cumplimiento (%)', font: { weight: 'bold' } },
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false } 
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: { display: true, text: 'N° Aprendices', font: { weight: 'bold' }, color: '#3B82F6' },
                        grid: { drawOnChartArea: false, drawBorder: false },
                        ticks: { color: '#3B82F6' }
                    },
                    x: { 
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { weight: '500' } }
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
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8 }
                } 
            }
        });
    }

    // Datos comparativos PHP a JS
    const statsProgramas = <?php echo json_encode($statsProgramas); ?>;
    const radarLabels = statsProgramas.map(p => p.programa.substring(0, 15) + '...');
    
    // Chart de Radar (Desempeño Integral)
    if (document.getElementById('chartRadar') && statsProgramas.length > 0) {
        const dataCumplimiento = statsProgramas.map(p => parseFloat(p.cumplimiento_avg || 0));
        
        // Simulamos un factor de retención normalizando matriculados vs desertados a %
        const dataRetencion = statsProgramas.map(p => {
            let total = parseInt(p.matriculados) + parseInt(p.desertados);
            return total > 0 ? (parseInt(p.matriculados) / total) * 100 : 0;
        });

        new Chart(document.getElementById('chartRadar'), {
            type: 'radar',
            data: {
                labels: radarLabels,
                datasets: [
                    {
                        label: 'Cumplimiento Promedio',
                        data: dataCumplimiento,
                        backgroundColor: 'rgba(57, 169, 0, 0.2)',
                        borderColor: primaryColor,
                        pointBackgroundColor: primaryColor,
                        borderWidth: 2
                    },
                    {
                        label: 'Retención Estimada',
                        data: dataRetencion,
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: '#3B82F6',
                        pointBackgroundColor: '#3B82F6',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                scales: {
                    r: {
                        angleLines: { color: 'rgba(0,0,0,0.1)' },
                        grid: { color: 'rgba(0,0,0,0.1)' },
                        pointLabels: { font: { size: 10 } },
                        ticks: { display: false, max: 100, min: 0 }
                    }
                },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
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
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: { color: 'rgba(0,0,0,0.05)' } }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    tooltip: { mode: 'index', backgroundColor: 'rgba(0,0,0,0.8)' }
                }
            }
        });
    }

});
</script>
