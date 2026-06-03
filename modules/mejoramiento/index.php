<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireAuth();

$db = Database::getConnection();
$errors = [];

$user_id = (int)getCurrentUser()['id'];
$user_rol = getCurrentRole();

// Obtener datos si es aprendiz
$aprendiz_id = 0;
if ($user_rol === ROL_APRENDIZ) {
    try {
        $stmt = $db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $aprendiz_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        $errors[] = 'Error al cargar perfil.';
    }
}

// Cargar planes de mejoramiento con base en evaluaciones con concepto 'D' (Deficiente / En proceso).
// En SENA, una 'D' implica que el aprendiz debe nivelar el resultado de aprendizaje.
$planes = [];
try {
    if ($user_rol === ROL_APRENDIZ) {
        // El aprendiz ve sus propios planes pendientes
        $stmt = $db->prepare("
            SELECT eval.id,
                   ra.denominacion as actividad_nombre,
                   ra.codigo as ra_codigo,
                   u_inst.nombre as instructor_nombre,
                   eval.fecha_evaluacion,
                   eval.comentario
            FROM evaluaciones eval
            JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
            JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
            WHERE eval.aprendiz_id = ? AND eval.concepto = 'D'
            ORDER BY eval.fecha_evaluacion DESC
        ");
        $stmt->execute([$aprendiz_id]);
        $planes = $stmt->fetchAll();
    } elseif ($user_rol === ROL_INSTRUCTOR) {
        // El instructor solo ve planes de aprendices de SUS fichas y competencias asignadas
        $stmt = $db->prepare("
            SELECT eval.id,
                   ra.denominacion as actividad_nombre,
                   ra.codigo as ra_codigo,
                   u_inst.nombre as instructor_nombre,
                   u_ap.nombre as aprendiz_nombre,
                   f.numero_ficha,
                   eval.fecha_evaluacion,
                   eval.comentario
            FROM evaluaciones eval
            JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN fichas f ON eval.ficha_id = f.id
            JOIN aprendices ap ON eval.aprendiz_id = ap.id
            JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
            JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
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
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $planes = $stmt->fetchAll();
    } else {
        // El coordinador ve todos los planes del sistema
        $stmt = $db->prepare("
            SELECT eval.id,
                   ra.denominacion as actividad_nombre,
                   ra.codigo as ra_codigo,
                   u_inst.nombre as instructor_nombre,
                   u_ap.nombre as aprendiz_nombre,
                   f.numero_ficha,
                   eval.fecha_evaluacion,
                   eval.comentario
            FROM evaluaciones eval
            JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
            JOIN fichas f ON eval.ficha_id = f.id
            JOIN aprendices ap ON eval.aprendiz_id = ap.id
            JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
            JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
            WHERE eval.concepto = 'D'
            ORDER BY eval.fecha_evaluacion DESC
        ");
        $stmt->execute();
        $planes = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar planes de mejoramiento: ' . $e->getMessage();
}

$pageTitle = 'Planes de Mejoramiento · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="mb-4">
  <h1 class="mb-1">Planes de Mejoramiento</h1>
  <p class="text-muted mb-0">Cuando un aprendiz obtiene una evaluación 'En Proceso' (D), se genera automáticamente un plan de mejoramiento para nivelar las competencias pendientes.</p>
</div>

<div class="row g-3">
  <?php foreach ($planes as $plan): ?>
    <div class="col-md-6">
      <div class="card glass-card h-100 border-0 shadow-sm border-start border-danger border-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft danger">Plan Requerido</span>
            <small class="text-muted">Fecha: <?= date('d/m/Y', strtotime($plan['fecha_evaluacion'])) ?></small>
          </div>
          <h5 class="fw-bold text-dark mb-1">Plan de Nivelación: <?= htmlspecialchars($plan['actividad_nombre']) ?></h5>
          <?php if (!empty($plan['ra_codigo'])): ?>
            <div class="small text-muted mb-2">RA <code><?= htmlspecialchars($plan['ra_codigo']) ?></code></div>
          <?php endif; ?>
          
          <?php if ($user_rol !== ROL_APRENDIZ): ?>
            <div class="small text-muted mb-2">
              Aprendiz: <strong><?= htmlspecialchars($plan['aprendiz_nombre']) ?></strong> (Ficha #<?= htmlspecialchars($plan['numero_ficha']) ?>)
            </div>
          <?php endif; ?>

          <div class="p-3 bg-light-soft rounded mb-3" style="background: rgba(239, 68, 68, 0.03); font-size: 0.85rem;">
            <div class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-circle me-1"></i>Deficiencia reportada:</div>
            <p class="mb-0 text-muted">"<?= htmlspecialchars($plan['comentario']) ?>"</p>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Asignado por: <strong><?= htmlspecialchars($plan['instructor_nombre']) ?></strong></small>
            <button class="btn btn-sm btn-danger" onclick="alert('Instrucciones del plan:\n\n1. Repetir la entrega de la evidencia corrigiendo los puntos descritos.\n2. Solicitar cita de asesoría académica con el instructor asignado.')">
              Ver Guía Plan
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($planes)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-patch-check-fill d-block mb-2 text-success" style="font-size:3rem;"></i>
      <h4 class="fw-bold text-dark">¡Felicidades!</h4>
      <p class="text-muted">No se reportan planes de mejoramiento pendientes en el sistema formativo.</p>
    </div>
  <?php endif; ?>
</div>