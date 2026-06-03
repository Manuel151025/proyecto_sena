<?php
declare(strict_types=1);

/**
 * DASHBOARD INSTRUCTOR — modules/dashboard/instructor.php
 *
 * Vista de inicio para usuarios con rol 'instructor'.
 * Todos los KPIs y tablas se calculan desde BD; nada hard-coded.
 *
 * Datos mostrados:
 *   - KPI 1: evaluaciones pendientes (concepto = 'pendiente') del instructor
 *   - KPI 2: aprendices con D en sus fichas que requieren plan de mejoramiento
 *   - Mis fichas asignadas (de la tabla `fichas`)
 *   - Tabla "Aprendices con concepto D" — los más recientes
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_INSTRUCTOR);

$pageTitle = 'Dashboard Instructor · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$user           = getCurrentUser();
$nombreUsuario  = htmlspecialchars($user['nombre']);
$instructor_id  = (int)$user['id'];

// =====================================================================
// FUENTE DE DATOS
// =====================================================================

$db = Database::getConnection();

// KPI 1 — evaluaciones pendientes de calificar
$kpi_evaluaciones_pendientes = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM evaluaciones eval
        JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
        JOIN competencias c ON ra.competencia_id = c.id
        JOIN fichas f ON eval.ficha_id = f.id
        JOIN aprendices ap ON eval.aprendiz_id = ap.id
        WHERE eval.concepto = 'pendiente' AND (
            EXISTS (
                SELECT 1 FROM asignaciones asg 
                WHERE asg.ficha_id = eval.ficha_id 
                  AND asg.competencia_id = c.id 
                  AND asg.instructor_id = ?
            )
            OR
            (
                f.instructor_id = ? 
                AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                AND NOT EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = eval.ficha_id 
                      AND asg.competencia_id = c.id
                )
            )
            OR
            (
                (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                AND ap.instructor_seguimiento_id = ?
            )
        )
    ");
    $stmt->execute([$instructor_id, $instructor_id, $instructor_id]);
    $kpi_evaluaciones_pendientes = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // si falla, se mantiene en 0
}

// KPI 2 — aprendices con D en fichas del instructor (que requieren plan)
$kpi_planes_requeridos = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT eval.aprendiz_id)
        FROM evaluaciones eval
        JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
        JOIN competencias c ON ra.competencia_id = c.id
        JOIN fichas f ON eval.ficha_id = f.id
        JOIN aprendices ap ON eval.aprendiz_id = ap.id
        WHERE eval.concepto = 'D' AND (
            EXISTS (
                SELECT 1 FROM asignaciones asg 
                WHERE asg.ficha_id = eval.ficha_id 
                  AND asg.competencia_id = c.id 
                  AND asg.instructor_id = ?
            )
            OR
            (
                f.instructor_id = ? 
                AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                AND NOT EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = eval.ficha_id 
                      AND asg.competencia_id = c.id
                )
            )
            OR
            (
                (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                AND ap.instructor_seguimiento_id = ?
            )
        )
    ");
    $stmt->execute([$instructor_id, $instructor_id, $instructor_id]);
    $kpi_planes_requeridos = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // se mantiene en 0
}

// KPI 3 — aprendices en etapa práctica a cargo del instructor
$kpi_aprendices_seguimiento = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM aprendices
        WHERE instructor_seguimiento_id = ? AND estado = 'etapa_practica'
    ");
    $stmt->execute([$instructor_id]);
    $kpi_aprendices_seguimiento = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // se mantiene en 0
}

// Aprendices en Etapa Práctica a cargo del instructor
$aprendices_seguimiento_lista = [];
try {
    $stmt = $db->prepare("
        SELECT ap.id, u.nombre, f.numero_ficha, p.nombre as programa, ap.telefono, ap.ciudad, f.id as ficha_id
        FROM aprendices ap
        JOIN usuarios u ON ap.usuario_id = u.id
        LEFT JOIN fichas f ON ap.ficha_id = f.id
        LEFT JOIN programas p ON f.programa_id = p.id
        WHERE ap.instructor_seguimiento_id = ? AND ap.estado = 'etapa_practica'
        ORDER BY u.nombre
    ");
    $stmt->execute([$instructor_id]);
    $aprendices_seguimiento_lista = $stmt->fetchAll();
} catch (Exception $e) {
    // se mantiene vacía
}

// Fichas asignadas al instructor (incluyendo asignaciones específicas y de seguimiento)
$fichasInstructor = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT f.id, f.numero_ficha AS numero, p.nombre AS programa,
               f.cantidad_aprendices AS aprendices,
               f.cumplimiento_porcentaje AS cumplimiento, f.estado
        FROM fichas f
        JOIN programas p ON f.programa_id = p.id
        LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
        LEFT JOIN aprendices ap ON ap.ficha_id = f.id
        WHERE f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?
        ORDER BY f.cumplimiento_porcentaje ASC
    ");
    $stmt->execute([$instructor_id, $instructor_id, $instructor_id]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $f) {
        $badge = 'success';
        $estadoLabel = 'Al día';
        if ((float)$f['cumplimiento'] < 50) {
            $badge = 'danger';
            $estadoLabel = 'Crítico';
        } elseif ((float)$f['cumplimiento'] < 75) {
            $badge = 'warning';
            $estadoLabel = 'En riesgo';
        }
        $fichasInstructor[] = [
            'id'           => (int)$f['id'],
            'numero'       => $f['numero'],
            'programa'     => $f['programa'],
            'aprendices'   => (int)$f['aprendices'],
            'cumplimiento' => (float)$f['cumplimiento'],
            'estado'       => $estadoLabel,
            'badge'        => $badge,
        ];
    }
} catch (Exception $e) {
    // dejar lista vacía
}

// Aprendices con D más recientes (top 10)
$pendientesPlanes = [];
try {
    $stmt = $db->prepare("
        SELECT u.nombre        AS aprendiz,
               f.numero_ficha  AS ficha,
               ra.codigo       AS ra_codigo,
               ra.denominacion AS ra_nombre,
               eval.fecha_evaluacion AS fecha,
               eval.id         AS eval_id
        FROM evaluaciones eval
        JOIN fichas f                ON eval.ficha_id = f.id
        JOIN aprendices ap           ON eval.aprendiz_id = ap.id
        JOIN usuarios u              ON ap.usuario_id = u.id
        JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
        JOIN competencias c          ON ra.competencia_id = c.id
        WHERE eval.concepto = 'D' AND (
            EXISTS (
                SELECT 1 FROM asignaciones asg 
                WHERE asg.ficha_id = eval.ficha_id 
                  AND asg.competencia_id = c.id 
                  AND asg.instructor_id = ?
            )
            OR
            (
                f.instructor_id = ? 
                AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                AND NOT EXISTS (
                    SELECT 1 FROM asignaciones asg 
                    WHERE asg.ficha_id = eval.ficha_id 
                      AND asg.competencia_id = c.id
                )
            )
            OR
            (
                (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
                AND ap.instructor_seguimiento_id = ?
            )
        )
        ORDER BY eval.fecha_evaluacion DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id, $instructor_id, $instructor_id]);
    $pendientesPlanes = $stmt->fetchAll();
} catch (Exception $e) {
    // dejar lista vacía
}

// Distribución de conceptos para el gráfico de analítica
$eval_conceptos = ['A' => 0, 'D' => 0, 'pendiente' => 0];
try {
    $stmt = $db->prepare("
        SELECT eval.concepto, COUNT(*) as cantidad
        FROM evaluaciones eval
        JOIN fichas f ON eval.ficha_id = f.id
        JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
        JOIN competencias c ON ra.competencia_id = c.id
        JOIN aprendices ap ON eval.aprendiz_id = ap.id
        WHERE EXISTS (
            SELECT 1 FROM asignaciones asg 
            WHERE asg.ficha_id = eval.ficha_id 
              AND asg.competencia_id = c.id 
              AND asg.instructor_id = ?
        )
        OR
        (
            f.instructor_id = ? 
            AND NOT (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
            AND NOT EXISTS (
                SELECT 1 FROM asignaciones asg 
                WHERE asg.ficha_id = eval.ficha_id 
                  AND asg.competencia_id = c.id
            )
        )
        OR
        (
            (c.nombre LIKE '%ETAPA PRÁCTICA%' OR c.nombre LIKE '%ETAPA PRACTICA%')
            AND ap.instructor_seguimiento_id = ?
        )
        GROUP BY eval.concepto
    ");
    $stmt->execute([$instructor_id, $instructor_id, $instructor_id]);
    foreach ($stmt->fetchAll() as $row) {
        $concepto = $row['concepto'] ?: 'pendiente';
        $eval_conceptos[$concepto] = (int)$row['cantidad'];
    }
} catch (Exception $e) {}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Buen día, <?= $nombreUsuario ?> 👋</h1>
    <p class="text-muted mb-0">Estas son tus fichas y pendientes de hoy.</p>
  </div>
</div>

<!-- ===== KPIs ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <?php if ($kpi_evaluaciones_pendientes > 0): ?>
      <div class="alert-flat danger h-100">
        <i class="bi bi-clipboard-x"></i>
        <div>
          <strong><?= number_format($kpi_evaluaciones_pendientes) ?>
            <?= $kpi_evaluaciones_pendientes === 1 ? 'evaluación pendiente' : 'evaluaciones pendientes' ?></strong>
          requieren tu calificación.
          <a href="<?= MODULES_PATH ?>/evaluaciones/" class="ms-2 fw-semibold d-block text-decoration-underline"
             style="color:inherit">Ir a evaluar →</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success h-100">
        <i class="bi bi-check2-circle"></i>
        <div><strong>Sin evaluaciones pendientes.</strong> ¡Estás al día!</div>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-4">
    <?php if ($kpi_planes_requeridos > 0): ?>
      <div class="alert-flat warning h-100">
        <i class="bi bi-person-exclamation"></i>
        <div>
          <strong><?= number_format($kpi_planes_requeridos) ?>
            <?= $kpi_planes_requeridos === 1 ? 'aprendiz necesita' : 'aprendices necesitan' ?> plan de mejoramiento</strong>
          en tus fichas.
          <a href="<?= MODULES_PATH ?>/mejoramiento/" class="ms-2 fw-semibold d-block text-decoration-underline"
             style="color:inherit">Revisar →</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success h-100">
        <i class="bi bi-patch-check"></i>
        <div><strong>Ningún aprendiz</strong> requiere plan de mejoramiento.</div>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-4">
    <?php if ($kpi_aprendices_seguimiento > 0): ?>
      <div class="alert-flat info h-100" style="background: rgba(13, 110, 253, 0.05); border-left: 4px solid #0d6efd; color: #084298;">
        <i class="bi bi-person-video3" style="color: #0d6efd;"></i>
        <div>
          <strong><?= number_format($kpi_aprendices_seguimiento) ?>
            <?= $kpi_aprendices_seguimiento === 1 ? 'aprendiz' : 'aprendices' ?> en Etapa Práctica</strong>
          bajo tu seguimiento.
          <a href="#practica-seguimiento" class="ms-2 fw-semibold d-block text-decoration-underline"
             style="color:inherit">Ver listado ↓</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success h-100">
        <i class="bi bi-person-video3"></i>
        <div><strong>0 aprendices</strong> asignados en Etapa Práctica.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== Fichas asignadas ===== -->
<h2 class="mt-4 mb-2">Mis fichas asignadas</h2>

<?php if (empty($fichasInstructor)): ?>
  <div class="card"><div class="card-body text-center text-muted py-4">
    <i class="bi bi-inbox d-block mb-2" style="font-size:2rem"></i>
    No tienes fichas asignadas todavía. Cuando un coordinador te asigne una, aparecerá aquí.
  </div></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($fichasInstructor as $ficha): ?>
      <div class="col-md-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span class="badge-soft <?= $ficha['badge'] ?>"><?= htmlspecialchars($ficha['estado']) ?></span>
              <small class="text-muted">#<?= htmlspecialchars($ficha['numero']) ?></small>
            </div>
            <h3 class="mb-1"><?= htmlspecialchars($ficha['programa']) ?></h3>
            <small class="text-muted d-block mb-3">
              <i class="bi bi-people me-1"></i><?= $ficha['aprendices'] ?> aprendices
            </small>
            <div class="d-flex justify-content-between small mb-1">
              <span>Cumplimiento</span><strong><?= $ficha['cumplimiento'] ?>%</strong>
            </div>
            <div class="progress-flat <?= in_array($ficha['badge'], ['danger','warning']) ? $ficha['badge'] : '' ?>">
              <div style="width:<?= $ficha['cumplimiento'] ?>%"></div>
            </div>
            <a href="<?= MODULES_PATH ?>/fichas/ver.php?id=<?= $ficha['id'] ?>"
               class="btn btn-soft w-100 mt-3">Ver detalle</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ===== Sección de Analítica ===== -->
<div class="row g-4 mt-3 mb-4">
  <!-- Gráfico de Cumplimiento por Ficha -->
  <div class="col-md-8">
    <div class="card glass-card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-line text-primary me-2"></i>Avance de Cumplimiento por Ficha</h5>
        <small class="text-muted">Progreso integralizado de resultados de aprendizaje evaluados con 'A' por cada una de tus fichas.</small>
      </div>
      <div class="card-body p-4">
        <div style="height: 280px; position: relative;">
          <canvas id="chartFichasCumplimiento"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráfico de Distribución de Juicios -->
  <div class="col-md-4">
    <div class="card glass-card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart text-success me-2"></i>Distribución de Evaluaciones</h5>
        <small class="text-muted">Estado actual de todos los juicios de tu cohorte.</small>
      </div>
      <div class="card-body p-4 d-flex align-items-center justify-content-center">
        <div style="width: 100%; max-width: 240px; height: 240px; position: relative;">
          <canvas id="chartConceptosDistribucion"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Aprendices con concepto D ===== -->
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Aprendices con concepto D que requieren plan de mejoramiento</span>
    <?php if (count($pendientesPlanes) === 10): ?>
      <a href="<?= MODULES_PATH ?>/mejoramiento/" class="small text-muted">Ver todos →</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap" style="border:0;border-radius:0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Aprendiz</th>
          <th>Ficha</th>
          <th>RA</th>
          <th>Fecha D</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pendientesPlanes)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              <i class="bi bi-patch-check-fill text-success d-block mb-1" style="font-size:1.5rem"></i>
              No hay aprendices con concepto D pendiente.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($pendientesPlanes as $p): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar" style="width:32px;height:32px;font-size:.75rem">
                    <?= getInitials($p['aprendiz']) ?>
                  </div>
                  <?= htmlspecialchars($p['aprendiz']) ?>
                </div>
              </td>
              <td>#<?= htmlspecialchars($p['ficha']) ?></td>
              <td>
                <span class="badge-soft" title="<?= htmlspecialchars($p['ra_nombre']) ?>">
                  <?= htmlspecialchars($p['ra_codigo']) ?>
                </span>
              </td>
              <td><?= !empty($p['fecha']) ? date('d/m/Y', strtotime($p['fecha'])) : '—' ?></td>
              <td class="text-end">
                <a href="<?= MODULES_PATH ?>/mejoramiento/" class="btn btn-sm btn-primary">
                  <i class="bi bi-arrow-right"></i> Atender
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Aprendices en Etapa Práctica (Seguimiento) ===== -->
<div class="card mt-4 mb-4" id="practica-seguimiento">
  <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-video3 text-primary me-2"></i>Mis Aprendices en Etapa Práctica (Seguimiento)</h5>
    <small class="text-muted">Listado de aprendices asignados para seguimiento de etapa productiva.</small>
  </div>
  <div class="table-wrap mt-3" style="border:0;border-radius:0">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th class="ps-4">Aprendiz</th>
          <th>Ficha</th>
          <th>Programa</th>
          <th>Teléfono / Ciudad</th>
          <th class="pe-4 text-end">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($aprendices_seguimiento_lista)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              <i class="bi bi-person-badge-fill text-muted d-block mb-1" style="font-size:1.5rem; opacity:0.5;"></i>
              No tienes aprendices en etapa práctica asignados.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($aprendices_seguimiento_lista as $ap_seg): ?>
            <tr>
              <td class="ps-4">
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar bg-soft-primary text-primary" style="width:32px;height:32px;font-size:.75rem">
                    <?= getInitials($ap_seg['nombre']) ?>
                  </div>
                  <strong><?= htmlspecialchars($ap_seg['nombre']) ?></strong>
                </div>
              </td>
              <td>#<?= htmlspecialchars($ap_seg['numero_ficha']) ?></td>
              <td><small class="text-muted"><?= htmlspecialchars($ap_seg['programa']) ?></small></td>
              <td>
                <div><?= htmlspecialchars($ap_seg['telefono'] ?: '—') ?></div>
                <small class="text-muted"><?= htmlspecialchars($ap_seg['ciudad'] ?: '—') ?></small>
              </td>
              <td class="pe-4 text-end">
                <a href="<?= MODULES_PATH ?>/seguimiento/index.php?ficha_id=<?= $ap_seg['ficha_id'] ?>&ver_aprendiz_id=<?= $ap_seg['id'] ?>" class="btn btn-sm btn-soft">
                  <i class="bi bi-chat-dots me-1"></i> Seguimiento
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const labelsFichas = <?= json_encode(array_map(function($f) { return '#' . $f['numero']; }, $fichasInstructor)) ?>;
    const progFichas = <?= json_encode(array_column($fichasInstructor, 'cumplimiento')) ?>;
    
    const countA = <?= (int)$eval_conceptos['A'] ?>;
    const countD = <?= (int)$eval_conceptos['D'] ?>;
    const countPendiente = <?= (int)$eval_conceptos['pendiente'] ?>;

    // Chart 1: Bar Chart de Avance de Fichas
    if (document.getElementById('chartFichasCumplimiento') && labelsFichas.length > 0) {
        const ctxBar = document.getElementById('chartFichasCumplimiento').getContext('2d');
        
        // Generar gradiente para el color principal
        const gradient = ctxBar.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(57, 169, 0, 0.85)'); // Verde SENA
        gradient.addColorStop(1, 'rgba(0, 50, 77, 0.85)');  // Azul SENA
        
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: labelsFichas,
                datasets: [{
                    label: 'Cumplimiento (%)',
                    data: progFichas,
                    backgroundColor: gradient,
                    borderColor: '#00324D',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return ` ${context.parsed.y}%`; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { callback: value => value + '%' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Chart 2: Doughnut Chart de Distribución de Juicios
    if (document.getElementById('chartConceptosDistribucion')) {
        const ctxPie = document.getElementById('chartConceptosDistribucion').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Aprobado (A)', 'En Proceso (D)', 'Pendiente'],
                datasets: [{
                    data: [countA, countD, countPendiente],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.15)', // Verde suave
                        'rgba(239, 68, 68, 0.15)', // Rojo suave
                        'rgba(234, 179, 8, 0.15)'  // Amarillo suave
                    ],
                    borderColor: [
                        '#22c55e', // Verde
                        '#ef4444', // Rojo
                        '#eab308'  // Amarillo
                    ],
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = countA + countD + countPendiente;
                                const val = context.parsed;
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return ` ${context.label}: ${val} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>