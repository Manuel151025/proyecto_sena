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
        FROM evaluaciones
        WHERE instructor_id = ? AND concepto = 'pendiente'
    ");
    $stmt->execute([$instructor_id]);
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
        JOIN fichas f ON eval.ficha_id = f.id
        WHERE f.instructor_id = ? AND eval.concepto = 'D'
    ");
    $stmt->execute([$instructor_id]);
    $kpi_planes_requeridos = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // se mantiene en 0
}

// Fichas asignadas al instructor (mantengo la query original, ya estaba bien)
$fichasInstructor = [];
try {
    $stmt = $db->prepare("
        SELECT f.id, f.numero_ficha AS numero, p.nombre AS programa,
               f.cantidad_aprendices AS aprendices,
               f.cumplimiento_porcentaje AS cumplimiento, f.estado
        FROM fichas f
        JOIN programas p ON f.programa_id = p.id
        WHERE f.instructor_id = ?
        ORDER BY f.cumplimiento_porcentaje ASC
    ");
    $stmt->execute([$instructor_id]);
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

// Aprendices con D más recientes (top 10) — esto reemplaza la tabla TODO
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
        WHERE f.instructor_id = ? AND eval.concepto = 'D'
        ORDER BY eval.fecha_evaluacion DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id]);
    $pendientesPlanes = $stmt->fetchAll();
} catch (Exception $e) {
    // dejar lista vacía
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Buen día, <?= $nombreUsuario ?> 👋</h1>
    <p class="text-muted mb-0">Estas son tus fichas y pendientes de hoy.</p>
  </div>
</div>

<!-- ===== KPIs ===== -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <?php if ($kpi_evaluaciones_pendientes > 0): ?>
      <div class="alert-flat danger">
        <i class="bi bi-clipboard-x"></i>
        <div>
          <strong><?= number_format($kpi_evaluaciones_pendientes) ?>
            <?= $kpi_evaluaciones_pendientes === 1 ? 'evaluación pendiente' : 'evaluaciones pendientes' ?></strong>
          requieren tu calificación.
          <a href="<?= MODULES_PATH ?>/evaluaciones/" class="ms-2 fw-semibold"
             style="color:inherit;text-decoration:underline">Ir a evaluar</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success">
        <i class="bi bi-check2-circle"></i>
        <div><strong>Sin evaluaciones pendientes.</strong> ¡Estás al día!</div>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-6">
    <?php if ($kpi_planes_requeridos > 0): ?>
      <div class="alert-flat warning">
        <i class="bi bi-person-exclamation"></i>
        <div>
          <strong><?= number_format($kpi_planes_requeridos) ?>
            <?= $kpi_planes_requeridos === 1 ? 'aprendiz necesita' : 'aprendices necesitan' ?> plan de mejoramiento</strong>
          en tus fichas.
          <a href="<?= MODULES_PATH ?>/mejoramiento/" class="ms-2 fw-semibold"
             style="color:inherit;text-decoration:underline">Revisar</a>
        </div>
      </div>
    <?php else: ?>
      <div class="alert-flat success">
        <i class="bi bi-patch-check"></i>
        <div><strong>Ningún aprendiz</strong> requiere plan de mejoramiento.</div>
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