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
$successMessage = '';

$user_id  = (int)getCurrentUser()['id'];
$user_rol = getCurrentRole();

// ==========================================
// 1. PROCESAR ACCIONES (POST)
// ==========================================

// Registrar / actualizar evaluación por Resultado de Aprendizaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'registrar_evaluacion') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para registrar calificaciones.';
    } else {
        $ra_id         = (int)($_POST['resultado_aprendizaje_id'] ?? 0);
        $aprendiz_id_p = (int)($_POST['aprendiz_id'] ?? 0);
        $ficha_id_p    = (int)($_POST['ficha_id'] ?? 0);
        $concepto_form = $_POST['concepto'] ?? 'en_proceso';
        $concepto_map  = ['aprobado' => 'A', 'en_proceso' => 'D', 'no_aplica' => 'pendiente'];
        $concepto      = $concepto_map[$concepto_form] ?? 'pendiente';
        $comentario    = trim($_POST['comentario'] ?? '');

        if ($ra_id <= 0 || $aprendiz_id_p <= 0 || $ficha_id_p <= 0) {
            $errors[] = 'Datos de evaluación incompletos.';
        } else {
            try {
                $stmt = $db->prepare("
                    SELECT id FROM evaluaciones
                    WHERE resultado_aprendizaje_id = ? AND aprendiz_id = ? AND ficha_id = ?
                ");
                $stmt->execute([$ra_id, $aprendiz_id_p, $ficha_id_p]);
                $eval_id = $stmt->fetchColumn();

                if ($eval_id) {
                    $stmt = $db->prepare("
                        UPDATE evaluaciones
                        SET concepto = ?, comentario = ?, instructor_id = ?, fecha_evaluacion = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$concepto, $comentario, $user_id, date('Y-m-d'), $eval_id]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO evaluaciones
                            (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$ra_id, $aprendiz_id_p, $user_id, $ficha_id_p, $concepto, $comentario, date('Y-m-d')]);
                }

                $successMessage = 'Evaluación académica guardada correctamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al registrar la evaluación: ' . $e->getMessage();
            }
        }
    }
}

// Registrar anotación de retroalimentación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'agregar_retroalimentacion') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para agregar anotaciones de seguimiento.';
    } else {
        $aprendiz_id_r = (int)($_POST['aprendiz_id'] ?? 0);
        $tipo          = $_POST['tipo'] ?? 'recomendacion';
        $contenido     = trim($_POST['contenido'] ?? '');
        $privada       = isset($_POST['privada']) ? 1 : 0;

        if ($aprendiz_id_r <= 0) $errors[] = 'Seleccione un aprendiz válido.';
        if (empty($contenido))   $errors[] = 'El detalle del seguimiento es obligatorio.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO retroalimentacion (aprendiz_id, instructor_id, tipo, contenido, privada)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$aprendiz_id_r, $user_id, $tipo, $contenido, $privada]);

                $logStmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Crear', 'Seguimiento', 'retroalimentacion', ?, ?)
                ");
                $logStmt->execute([$user_id, (int)$db->lastInsertId(), "Registró anotación de seguimiento tipo $tipo para aprendiz id $aprendiz_id_r"]);

                $successMessage = 'Observación registrada exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al registrar observación: ' . $e->getMessage();
            }
        }
    }
}

// ==========================================
// 2. OBTENER DATOS SEGÚN EL ROL
// ==========================================

$fichas               = [];
$selected_ficha_id    = 0;
$selected_programa_id = 0;
$ficha_detalle        = null;
$aprendices_stats     = [];
$detalle_evaluaciones = [];
$detalle_retroalimentacion = [];

$mi_perfil            = null;
$mis_actividades      = []; // Resultados de Aprendizaje con sus evaluaciones
$mis_retroalimentaciones = [];

if ($user_rol === ROL_APRENDIZ) {
    // ---- FLUJO APRENDIZ ----
    try {
        $stmt = $db->prepare("
            SELECT ap.id, ap.ficha_id,
                   f.numero_ficha, p.nombre as programa_nombre, p.id as programa_id,
                   u_inst.nombre as instructor_nombre, u_coor.nombre as coordinador_nombre
            FROM aprendices ap
            JOIN fichas f    ON ap.ficha_id = f.id
            JOIN programas p ON f.programa_id = p.id
            LEFT JOIN usuarios u_inst ON f.instructor_id = u_inst.id
            LEFT JOIN usuarios u_coor ON f.coordinador_id = u_coor.id
            WHERE ap.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        $mi_perfil = $stmt->fetch();

        if ($mi_perfil) {
            $ap_id    = (int)$mi_perfil['id'];
            $ficha_id = (int)$mi_perfil['ficha_id'];

            // RAs del programa con sus evaluaciones para este aprendiz
            $stmt = $db->prepare("
                SELECT
                    ra.id            AS ra_id,
                    ra.denominacion  AS ra_nombre,
                    ra.codigo        AS ra_codigo,
                    c.codigo         AS competencia_codigo,
                    c.nombre         AS competencia_nombre,
                    eval.concepto,
                    eval.comentario,
                    eval.fecha_evaluacion,
                    u_inst.nombre    AS instructor_nombre
                FROM resultados_aprendizaje ra
                JOIN competencias c ON ra.competencia_id = c.id
                LEFT JOIN evaluaciones eval
                    ON eval.resultado_aprendizaje_id = ra.id
                    AND eval.aprendiz_id = ? AND eval.ficha_id = ?
                LEFT JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
                WHERE c.programa_id = ?
                ORDER BY c.codigo, ra.codigo
            ");
            $stmt->execute([$ap_id, $ficha_id, (int)$mi_perfil['programa_id']]);
            $mis_actividades = $stmt->fetchAll();

            // Retroalimentaciones públicas
            $stmt = $db->prepare("
                SELECT r.*, u.nombre AS instructor_nombre
                FROM retroalimentacion r
                JOIN usuarios u ON r.instructor_id = u.id
                WHERE r.aprendiz_id = ? AND r.privada = 0
                ORDER BY r.fecha_creacion DESC
            ");
            $stmt->execute([$ap_id]);
            $mis_retroalimentaciones = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $errors[] = 'Error al cargar perfil de seguimiento: ' . $e->getMessage();
    }
} else {
    // ---- FLUJO COORDINADOR / INSTRUCTOR ----
    try {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmt = $db->prepare("
                SELECT f.id, f.numero_ficha, p.nombre AS programa
                FROM fichas f
                JOIN programas p ON f.programa_id = p.id
                WHERE f.instructor_id = ?
                ORDER BY f.numero_ficha
            ");
            $stmt->execute([$user_id]);
            $fichas = $stmt->fetchAll();
        } else {
            $fichas = $db->query("
                SELECT f.id, f.numero_ficha, p.nombre AS programa
                FROM fichas f
                JOIN programas p ON f.programa_id = p.id
                ORDER BY f.numero_ficha
            ")->fetchAll();
        }

        $selected_ficha_id = (int)($_GET['ficha_id'] ?? 0);
        if ($selected_ficha_id === 0 && !empty($fichas)) {
            $selected_ficha_id = (int)$fichas[0]['id'];
        }

        if ($selected_ficha_id > 0) {
            // Detalle de la ficha
            $stmt = $db->prepare("
                SELECT f.*, p.nombre AS programa_nombre,
                       u_inst.nombre AS instructor_nombre, u_coor.nombre AS coordinador_nombre
                FROM fichas f
                JOIN programas p ON f.programa_id = p.id
                LEFT JOIN usuarios u_inst ON f.instructor_id = u_inst.id
                LEFT JOIN usuarios u_coor ON f.coordinador_id = u_coor.id
                WHERE f.id = ?
            ");
            $stmt->execute([$selected_ficha_id]);
            $ficha_detalle = $stmt->fetch();

            if ($ficha_detalle) {
                $selected_programa_id = (int)$ficha_detalle['programa_id'];
            }

            // Estadísticas por aprendiz (subqueries sin actividad_id)
            $stmt = $db->prepare("
                SELECT
                    ap.id            AS aprendiz_id,
                    u.nombre         AS aprendiz_nombre,
                    u.email          AS aprendiz_email,
                    ap.numero_documento,
                    ap.tipo_documento,
                    ap.genero,
                    ap.telefono,
                    ap.ciudad,
                    (SELECT COUNT(DISTINCT ra.id)
                     FROM resultados_aprendizaje ra
                     JOIN competencias c ON ra.competencia_id = c.id
                     WHERE c.programa_id = ?) AS total_actividades,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'A') AS aprobadas,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'D') AS en_proceso,
                    (SELECT COUNT(*) FROM evaluaciones eval
                     WHERE eval.aprendiz_id = ap.id AND eval.ficha_id = ? AND eval.concepto = 'pendiente') AS no_aplica
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                WHERE ap.ficha_id = ?
                ORDER BY u.nombre
            ");
            $stmt->execute([$selected_programa_id, $selected_ficha_id, $selected_ficha_id, $selected_ficha_id, $selected_ficha_id]);
            $aprendices_stats = $stmt->fetchAll();

            // Todos los RAs del programa de esta ficha
            $stmt = $db->prepare("
                SELECT ra.id AS ra_id, ra.denominacion AS ra_nombre, ra.codigo AS ra_codigo,
                       c.codigo AS competencia_codigo, c.nombre AS competencia_nombre
                FROM resultados_aprendizaje ra
                JOIN competencias c ON ra.competencia_id = c.id
                WHERE c.programa_id = ?
                ORDER BY c.codigo, ra.codigo
            ");
            $stmt->execute([$selected_programa_id]);
            $todas_actividades = $stmt->fetchAll();

            // Todas las evaluaciones de los aprendices de esta ficha
            $stmt = $db->prepare("
                SELECT eval.concepto, eval.comentario, eval.fecha_evaluacion,
                       eval.resultado_aprendizaje_id, eval.aprendiz_id
                FROM evaluaciones eval
                WHERE eval.ficha_id = ?
            ");
            $stmt->execute([$selected_ficha_id]);
            $todas_evaluaciones = $stmt->fetchAll();

            // Agrupar evaluaciones por [aprendiz_id][ra_id]
            $eval_map = [];
            foreach ($todas_evaluaciones as $ev) {
                $eval_map[(int)$ev['aprendiz_id']][(int)$ev['resultado_aprendizaje_id']] = $ev;
            }

            // Construir detalle completo por aprendiz
            foreach ($aprendices_stats as $ap) {
                $ap_id = (int)$ap['aprendiz_id'];
                $detalle_evaluaciones[$ap_id] = [];
                foreach ($todas_actividades as $ra) {
                    $ra_id     = (int)$ra['ra_id'];
                    $eval_info = $eval_map[$ap_id][$ra_id] ?? null;
                    $detalle_evaluaciones[$ap_id][] = [
                        'ra_id'              => $ra_id,
                        'ra_nombre'          => $ra['ra_nombre'],
                        'ra_codigo'          => $ra['ra_codigo'],
                        'competencia_codigo' => $ra['competencia_codigo'],
                        'competencia_nombre' => $ra['competencia_nombre'],
                        'concepto'           => $eval_info ? $eval_info['concepto'] : null,
                        'comentario'         => $eval_info ? $eval_info['comentario'] : null,
                        'fecha_evaluacion'   => $eval_info ? $eval_info['fecha_evaluacion'] : null,
                    ];
                }
            }

            // Retroalimentaciones por aprendiz de esta ficha
            $stmt = $db->prepare("
                SELECT r.*, u.nombre AS instructor_nombre
                FROM retroalimentacion r
                JOIN usuarios u ON r.instructor_id = u.id
                WHERE r.aprendiz_id IN (SELECT id FROM aprendices WHERE ficha_id = ?)
                ORDER BY r.fecha_creacion DESC
            ");
            $stmt->execute([$selected_ficha_id]);
            foreach ($stmt->fetchAll() as $row) {
                $detalle_retroalimentacion[(int)$row['aprendiz_id']][] = $row;
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Error al cargar los datos de las fichas: ' . $e->getMessage();
    }
}

$pageTitle   = 'Seguimiento Académico · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

// Claves = valores ENUM de la BD
$conceptos_labels = [
    'A'        => ['Aprobado (A)',   'success'],
    'D'        => ['En Proceso (D)', 'danger'],
    'pendiente' => ['Pendiente',     'secondary'],
];

$feedback_iconos = [
    'fortaleza'       => ['bi bi-check-circle-fill text-success',        'Fortaleza',         'success'],
    'aspecto_mejorar' => ['bi bi-exclamation-triangle-fill text-warning', 'Aspecto a mejorar', 'warning'],
    'recomendacion'   => ['bi bi-info-circle-fill text-info',            'Recomendación',     'info'],
];
?>

<div class="mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="mb-1 text-dark fw-bold"><i class="bi bi-graph-up text-primary me-2"></i>Seguimiento Académico</h1>
      <p class="text-muted mb-0">Control del avance formativo, cumplimiento de resultados de aprendizaje y nivelación de competencias.</p>
    </div>

    <?php if ($user_rol !== ROL_APRENDIZ && !empty($fichas)): ?>
      <form method="GET" class="d-flex align-items-center gap-2">
        <label class="text-muted small fw-semibold text-nowrap d-none d-sm-inline">Ficha:</label>
        <select name="ficha_id" class="form-select bg-white border border-light-subtle shadow-sm"
                onchange="this.form.submit()" style="min-width: 250px;">
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $f['id'] == $selected_ficha_id ? 'selected' : '' ?>>
              #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars(substr($f['programa'], 0, 30)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($successMessage)): ?>
  <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-check-circle-fill me-2" style="font-size:1.2rem;"></i>
      <div><?= htmlspecialchars($successMessage) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-exclamation-triangle-fill me-2" style="font-size:1.2rem;"></i>
      <div>
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- VISTA APRENDIZ -->
<!-- ======================================================= -->
<?php if ($user_rol === ROL_APRENDIZ): ?>
  <?php if (!$mi_perfil): ?>
    <div class="text-center py-5 glass-card rounded">
      <i class="bi bi-person-x d-block mb-3 text-muted" style="font-size:3rem;"></i>
      <h4 class="fw-bold">Sin Matrícula Asignada</h4>
      <p class="text-muted">No apareces registrado en ninguna ficha de formación. Contacta con el coordinador de tu centro.</p>
    </div>
  <?php else: ?>
    <!-- Tarjeta de perfil -->
    <div class="card glass-card border-0 mb-4 shadow-sm">
      <div class="card-body p-4">
        <div class="row align-items-center g-3">
          <div class="col-auto">
            <div class="avatar bg-primary text-white rounded-circle shadow-sm"
                 style="width:64px;height:64px;font-size:1.5rem;display:flex;align-items:center;justify-content:center;">
              <?= strtoupper(substr(getCurrentUser()['nombre'] ?? '', 0, 2)) ?>
            </div>
          </div>
          <div class="col">
            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars(getCurrentUser()['nombre'] ?? '') ?></h4>
            <span class="badge bg-soft primary me-2">Ficha #<?= htmlspecialchars($mi_perfil['numero_ficha']) ?></span>
            <span class="text-muted small"><?= htmlspecialchars($mi_perfil['programa_nombre']) ?></span>
          </div>
          <div class="col-12 col-md-4 text-md-end">
            <div class="text-muted small">Instructor Asignado:</div>
            <div class="fw-bold text-dark"><?= htmlspecialchars($mi_perfil['instructor_nombre'] ?: 'No asignado') ?></div>
            <div class="text-muted small mt-1">Coordinador:</div>
            <div class="fw-semibold text-muted small"><?= htmlspecialchars($mi_perfil['coordinador_nombre'] ?: 'No asignado') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Progreso General -->
    <?php
      $total     = count($mis_actividades);
      $aprobadas = 0;
      $en_proceso = 0;
      foreach ($mis_actividades as $act) {
          if ($act['concepto'] === 'A')      $aprobadas++;
          elseif ($act['concepto'] === 'D')  $en_proceso++;
      }
      $progreso = $total > 0 ? round(($aprobadas / $total) * 100) : 0;
    ?>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card glass-card border-0 p-3 shadow-sm h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted fw-semibold d-block mb-1">Avance Académico</small>
              <h3 class="fw-bold mb-0 text-primary"><?= $progreso ?>%</h3>
            </div>
            <div class="rounded-circle p-3" style="background:rgba(var(--bs-primary-rgb),.1)">
              <i class="bi bi-trophy text-primary" style="font-size:1.5rem;"></i>
            </div>
          </div>
          <div class="progress mt-3" style="height:6px;">
            <div class="progress-bar bg-primary" style="width:<?= $progreso ?>%"></div>
          </div>
          <small class="text-muted d-block mt-2"><?= $aprobadas ?> de <?= $total ?> RAs aprobados</small>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card glass-card border-0 p-3 shadow-sm h-100 border-start border-danger border-4">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted fw-semibold d-block mb-1">Pendientes / Nivelación</small>
              <h3 class="fw-bold mb-0 text-danger"><?= $en_proceso ?></h3>
            </div>
            <div class="rounded-circle p-3" style="background:rgba(239,68,68,.1)">
              <i class="bi bi-clock-history text-danger" style="font-size:1.5rem;"></i>
            </div>
          </div>
          <small class="text-muted d-block mt-3">RAs con concepto 'D' que requieren plan de mejoramiento</small>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card glass-card border-0 p-3 shadow-sm h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted fw-semibold d-block mb-1">Anotaciones de Seguimiento</small>
              <h3 class="fw-bold mb-0 text-info"><?= count($mis_retroalimentaciones) ?></h3>
            </div>
            <div class="rounded-circle p-3" style="background:rgba(13,202,240,.1)">
              <i class="bi bi-chat-text text-info" style="font-size:1.5rem;"></i>
            </div>
          </div>
          <small class="text-muted d-block mt-3">Consejos, fortalezas y sugerencias de tus instructores</small>
        </div>
      </div>
    </div>

    <!-- Tabs de detalle -->
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-header border-bottom-0 pb-0 bg-transparent">
        <ul class="nav nav-tabs border-bottom" id="aprendizTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#actividades" type="button">
              <i class="bi bi-card-checklist me-1"></i>Mis Resultados &amp; Calificaciones
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#feedback" type="button">
              <i class="bi bi-chat-dots me-1"></i>Historial de Seguimiento
            </button>
          </li>
        </ul>
      </div>
      <div class="card-body p-4">
        <div class="tab-content">

          <!-- TAB: Resultados de Aprendizaje -->
          <div class="tab-pane fade show active" id="actividades" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr class="table-light">
                    <th class="ps-4">Resultado de Aprendizaje</th>
                    <th>Competencia</th>
                    <th>Evaluado por</th>
                    <th>Fecha</th>
                    <th class="pe-4 text-center">Concepto</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($mis_actividades as $act): ?>
                    <?php $cl = $conceptos_labels[$act['concepto']] ?? ['Pendiente', 'secondary']; ?>
                    <tr>
                      <td class="ps-4">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($act['ra_nombre']) ?></div>
                        <small class="text-muted font-monospace"><?= htmlspecialchars($act['ra_codigo']) ?></small>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark font-monospace" style="max-width:250px;white-space:normal;">
                          <?= htmlspecialchars($act['competencia_codigo'] ?: 'N/A') ?> — <?= htmlspecialchars(substr($act['competencia_nombre'] ?: 'General', 0, 40)) ?>
                        </span>
                      </td>
                      <td>
                        <small class="fw-semibold text-muted"><?= htmlspecialchars($act['instructor_nombre'] ?: 'Pendiente') ?></small>
                      </td>
                      <td>
                        <small class="text-muted"><?= $act['fecha_evaluacion'] ? date('d/m/Y', strtotime($act['fecha_evaluacion'])) : '—' ?></small>
                      </td>
                      <td class="pe-4 text-center">
                        <span class="badge-soft <?= $cl[1] ?>"
                              style="cursor:help;"
                              data-bs-toggle="tooltip"
                              title="<?= htmlspecialchars($act['comentario'] ?: 'Sin observaciones') ?>">
                          <?= $cl[0] ?>
                        </span>
                        <?php if (!empty($act['comentario'])): ?>
                          <div class="small text-muted mt-1" style="font-size:.75rem;max-width:200px;font-style:italic;">
                            "<?= htmlspecialchars($act['comentario']) ?>"
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($mis_actividades)): ?>
                    <tr>
                      <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                        Aún no se han registrado resultados de aprendizaje para tu programa.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB: Historial retroalimentación -->
          <div class="tab-pane fade" id="feedback" role="tabpanel">
            <?php foreach ($mis_retroalimentaciones as $retro): ?>
              <?php $fi = $feedback_iconos[$retro['tipo']] ?? ['bi bi-info-circle-fill text-info', 'Observación', 'info']; ?>
              <div class="p-3 mb-3 border rounded shadow-sm bg-white" style="border-left:5px solid var(--bs-<?= $fi[2] ?>) !important;">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <i class="<?= $fi[0] ?>" style="font-size:1.2rem;"></i>
                    <strong class="text-dark"><?= $fi[1] ?></strong>
                  </div>
                  <small class="text-muted"><?= date('d/m/Y H:i', strtotime($retro['fecha_creacion'])) ?></small>
                </div>
                <p class="text-muted mb-2 font-monospace" style="font-size:.9rem;line-height:1.4;">
                  <?= nl2br(htmlspecialchars($retro['contenido'])) ?>
                </p>
                <div class="text-end">
                  <small class="text-muted">Por: <strong><?= htmlspecialchars($retro['instructor_nombre']) ?></strong></small>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($mis_retroalimentaciones)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-chat-square-text d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                No tienes anotaciones de seguimiento registradas.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<!-- ======================================================= -->
<!-- VISTA COORDINADOR / INSTRUCTOR -->
<!-- ======================================================= -->
<?php else: ?>
  <?php if (!$ficha_detalle): ?>
    <div class="text-center py-5 glass-card rounded">
      <i class="bi bi-folder-x d-block mb-3 text-muted" style="font-size:3rem;"></i>
      <h4 class="fw-bold">No hay Fichas Disponibles</h4>
      <p class="text-muted">No tienes fichas de formación asignadas o creadas para realizar seguimiento académico.</p>
    </div>
  <?php else: ?>
    <!-- KPI de la ficha -->
    <div class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card glass-card border-0 h-100 shadow-sm p-4 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <span class="badge bg-soft primary mb-2">Ficha Académica</span>
                <h3 class="fw-bold text-dark mb-1">Ficha #<?= htmlspecialchars($ficha_detalle['numero_ficha']) ?></h3>
                <h5 class="text-muted"><?= htmlspecialchars($ficha_detalle['programa_nombre']) ?></h5>
              </div>
              <span class="badge bg-soft warning">Etapa de <?= htmlspecialchars(ucfirst($ficha_detalle['estado'])) ?></span>
            </div>
            <div class="row g-3 mt-3">
              <div class="col-sm-6">
                <small class="text-muted d-block">Instructor Asignado:</small>
                <strong><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($ficha_detalle['instructor_nombre'] ?: 'Sin asignar') ?></strong>
              </div>
              <div class="col-sm-6">
                <small class="text-muted d-block">Coordinador:</small>
                <strong><i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($ficha_detalle['coordinador_nombre'] ?: 'Sin asignar') ?></strong>
              </div>
            </div>
          </div>
          <div class="mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small fw-semibold">Avance Integral del Proyecto Ficha:</span>
              <span class="fw-bold text-dark"><?= (int)$ficha_detalle['cumplimiento_porcentaje'] ?>%</span>
            </div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-success" style="width:<?= (int)$ficha_detalle['cumplimiento_porcentaje'] ?>%"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="row g-3 h-100">
          <div class="col-6 col-lg-12">
            <div class="card glass-card border-0 h-100 p-3 shadow-sm d-flex flex-row align-items-center justify-content-between">
              <div>
                <small class="text-muted d-block fw-semibold">Aprendices</small>
                <h2 class="fw-bold text-primary mb-0"><?= count($aprendices_stats) ?></h2>
              </div>
              <div class="rounded-circle p-3" style="background:rgba(var(--bs-primary-rgb),.1)">
                <i class="bi bi-people text-primary" style="font-size:1.5rem;"></i>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-12">
            <div class="card glass-card border-0 h-100 p-3 shadow-sm d-flex flex-row align-items-center justify-content-between border-start border-danger border-4">
              <?php
                $criticos = 0;
                foreach ($aprendices_stats as $ap_s) {
                    $t = (int)$ap_s['total_actividades'];
                    $a = (int)$ap_s['aprobadas'];
                    $p = $t > 0 ? ($a / $t) * 100 : 0;
                    if ($p < 60 || (int)$ap_s['en_proceso'] > 2) $criticos++;
                }
              ?>
              <div>
                <small class="text-muted d-block fw-semibold">Casos Críticos</small>
                <h2 class="fw-bold text-danger mb-0"><?= $criticos ?></h2>
              </div>
              <div class="rounded-circle p-3" style="background:rgba(239,68,68,.1)">
                <i class="bi bi-exclamation-octagon text-danger" style="font-size:1.5rem;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla de aprendices con sus estadísticas -->
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-header border-bottom-0 pb-0 bg-transparent p-4">
        <h4 class="fw-bold text-dark mb-1">Rendimiento Académico por Aprendiz</h4>
        <p class="text-muted small mb-0">Monitorea el avance por resultados de aprendizaje e interviene oportunamente.</p>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr class="table-light">
                <th class="ps-4">Documento / Aprendiz</th>
                <th class="text-center">Total RAs</th>
                <th class="text-center">Aprobados (A)</th>
                <th class="text-center">En Proceso (D)</th>
                <th class="text-center">Progreso</th>
                <th class="text-center">Nivel Alerta</th>
                <th class="pe-4 text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aprendices_stats as $ap): ?>
                <?php
                  $total_act    = (int)$ap['total_actividades'];
                  $aprobadas_ap = (int)$ap['aprobadas'];
                  $en_proc      = (int)$ap['en_proceso'];
                  $prog         = $total_act > 0 ? round(($aprobadas_ap / $total_act) * 100) : 0;
                  if ($prog < 60 || $en_proc > 2)       { $alerta_label = 'Crítico'; $alerta_class = 'danger'; }
                  elseif ($prog < 80 || $en_proc > 0)   { $alerta_label = 'Riesgo';  $alerta_class = 'warning'; }
                  else                                   { $alerta_label = 'Al Día';  $alerta_class = 'success'; }
                ?>
                <tr>
                  <td class="ps-4">
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar bg-light text-dark fw-bold rounded-circle border"
                           style="width:32px;height:32px;font-size:.8rem;display:flex;align-items:center;justify-content:center;">
                        <?= strtoupper(substr($ap['aprendiz_nombre'], 0, 2)) ?>
                      </div>
                      <div>
                        <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($ap['aprendiz_nombre']) ?></h6>
                        <small class="text-muted font-monospace"><?= htmlspecialchars($ap['tipo_documento']) ?> <?= htmlspecialchars($ap['numero_documento']) ?></small>
                      </div>
                    </div>
                  </td>
                  <td class="text-center fw-semibold text-muted"><?= $total_act ?></td>
                  <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1 rounded"><?= $aprobadas_ap ?></span></td>
                  <td class="text-center"><span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-2 py-1 rounded"><?= $en_proc ?></span></td>
                  <td class="text-center" style="width:180px;">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <div class="progress flex-grow-1" style="height:6px;min-width:80px;">
                        <div class="progress-bar bg-<?= $alerta_class ?>" style="width:<?= $prog ?>%"></div>
                      </div>
                      <small class="fw-bold text-dark"><?= $prog ?>%</small>
                    </div>
                  </td>
                  <td class="text-center">
                    <span class="badge-soft <?= $alerta_class ?>"><?= $alerta_label ?></span>
                  </td>
                  <td class="pe-4 text-end">
                    <button class="btn btn-sm btn-soft me-1"
                            onclick="abrirModalDetalle(<?= $ap['aprendiz_id'] ?>, <?= json_encode($ap['aprendiz_nombre']) ?>, <?= json_encode($ap['aprendiz_email']) ?>)"
                            title="Ver Detalle Académico">
                      <i class="bi bi-eye"></i> Detalle
                    </button>
                    <button class="btn btn-sm btn-soft btn-outline-info"
                            onclick="abrirModalRetroalimentacion(<?= $ap['aprendiz_id'] ?>, <?= json_encode($ap['aprendiz_nombre']) ?>)"
                            title="Registrar Nota de Seguimiento">
                      <i class="bi bi-chat-dots"></i> Observación
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($aprendices_stats)): ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:.5;"></i>
                    No hay aprendices matriculados en esta ficha.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL: Detalle académico del aprendiz -->
    <div class="modal fade" id="modalDetalleAprendiz" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content glass-card border-0" style="background:rgba(255,255,255,.99);backdrop-filter:blur(25px);">
          <div class="modal-header border-bottom-0 pb-0">
            <div>
              <h5 class="modal-title fw-bold text-dark">
                <i class="bi bi-person-check text-primary me-2"></i>Seguimiento Académico Individual
              </h5>
              <small class="text-muted d-block" id="detalle_aprendiz_subtitulo"></small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <ul class="nav nav-pills bg-light p-1 rounded mb-4" id="pills-tab" role="tablist">
              <li class="nav-item flex-fill text-center">
                <button class="nav-link active w-100 py-2 fw-semibold" data-bs-toggle="pill" data-bs-target="#pills-evals" type="button">
                  <i class="bi bi-check2-circle me-1"></i>Resultados de Aprendizaje
                </button>
              </li>
              <li class="nav-item flex-fill text-center">
                <button class="nav-link w-100 py-2 fw-semibold" data-bs-toggle="pill" data-bs-target="#pills-feedback" type="button">
                  <i class="bi bi-chat-right-text me-1"></i>Anotaciones &amp; Bitácora
                </button>
              </li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="pills-evals">
                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr class="table-light">
                        <th>Resultado de Aprendizaje</th>
                        <th>Competencia</th>
                        <th>Estado Actual</th>
                        <th class="text-end">Calificación</th>
                      </tr>
                    </thead>
                    <tbody id="lista_actividades_detalle"></tbody>
                  </table>
                </div>
              </div>
              <div class="tab-pane fade" id="pills-feedback">
                <div id="lista_feedback_detalle"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cerrar Detalle</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: Registrar evaluación por RA -->
    <div class="modal fade" id="modalCalificarActividad" tabindex="-1" aria-hidden="true" style="z-index:1060;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 bg-white">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-clipboard-check text-primary me-2"></i>Registrar Evaluación
            </h5>
            <button type="button" class="btn-close" onclick="cerrarModalCalificar()"></button>
          </div>
          <form method="POST">
            <input type="hidden" name="action"                  value="registrar_evaluacion">
            <input type="hidden" name="aprendiz_id"             id="calif_aprendiz_id">
            <input type="hidden" name="resultado_aprendizaje_id" id="calif_ra_id">
            <input type="hidden" name="ficha_id"                id="calif_ficha_id">
            <div class="modal-body">
              <div class="mb-3 bg-light p-3 rounded">
                <small class="text-muted d-block">Resultado de aprendizaje:</small>
                <strong class="text-dark" id="calif_actividad_nombre"></strong>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Concepto Evaluativo (SENA)</label>
                <select name="concepto" id="calif_concepto" class="form-select" required>
                  <option value="aprobado">Aprobado (A)</option>
                  <option value="en_proceso">En Proceso (D)</option>
                  <option value="no_aplica">No Aplica</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Comentarios / Observaciones</label>
                <textarea name="comentario" id="calif_comentario" class="form-control" rows="3"
                          placeholder="Describe los puntos a mejorar o felicita al aprendiz..."></textarea>
              </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
              <button type="button" class="btn btn-soft" onclick="cerrarModalCalificar()">Cancelar</button>
              <button type="submit" class="btn btn-primary">Guardar Evaluación</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: Anotación de retroalimentación -->
    <div class="modal fade" id="modalRetroalimentacion" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0" style="background:rgba(255,255,255,.99);backdrop-filter:blur(25px);">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark">
              <i class="bi bi-chat-text text-primary me-2"></i>Anotación de Seguimiento
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST">
            <input type="hidden" name="action"      value="agregar_retroalimentacion">
            <input type="hidden" name="aprendiz_id" id="retro_aprendiz_id">
            <div class="modal-body">
              <div class="mb-3 bg-light p-3 rounded">
                <small class="text-muted d-block">Registrar observación para:</small>
                <strong class="text-dark" id="retro_aprendiz_nombre"></strong>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Tipo de Nota</label>
                <select name="tipo" class="form-select" required>
                  <option value="recomendacion">💡 Recomendación / Sugerencia</option>
                  <option value="fortaleza">⭐ Fortaleza / Felicitación</option>
                  <option value="aspecto_mejorar">⚠️ Aspecto a Mejorar (Alerta)</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Detalle de la Observación</label>
                <textarea name="contenido" class="form-control" rows="4"
                          placeholder="Escribe el comentario académico que quedará en el historial del estudiante..." required></textarea>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="privada" id="retro_privada" value="1">
                <label class="form-check-label text-muted small" for="retro_privada">
                  Nota privada (solo visible para instructores y coordinadores)
                </label>
              </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
              <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Registrar Nota</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    const detalleEvaluaciones      = <?= json_encode($detalle_evaluaciones) ?>;
    const detalleRetroalimentacion = <?= json_encode($detalle_retroalimentacion) ?>;
    const conceptosLabels          = <?= json_encode($conceptos_labels) ?>;
    const feedbackIconos           = <?= json_encode($feedback_iconos) ?>;
    const currentFichaId           = <?= $selected_ficha_id ?>;

    // Map para pasar datos al modal de calificación sin inyección HTML
    const calificarData = {};

    let modalDetalle, modalCalificar, modalRetro;

    document.addEventListener('DOMContentLoaded', function () {
        modalDetalle   = new bootstrap.Modal(document.getElementById('modalDetalleAprendiz'));
        modalCalificar = new bootstrap.Modal(document.getElementById('modalCalificarActividad'));
        modalRetro     = new bootstrap.Modal(document.getElementById('modalRetroalimentacion'));
    });

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function abrirModalDetalle(aprendizId, nombre, email) {
        document.getElementById('detalle_aprendiz_subtitulo').innerText = nombre + ' (' + email + ')';

        const tbody = document.getElementById('lista_actividades_detalle');
        tbody.innerHTML = '';
        const evals = detalleEvaluaciones[aprendizId] || [];

        if (evals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay RAs registrados para esta ficha.</td></tr>';
        } else {
            evals.forEach(ev => {
                const cl          = conceptosLabels[ev.concepto] || ['Pendiente', 'secondary'];
                const commentHtml = ev.comentario ? `<div class="small text-muted mt-1 fst-italic">"${esc(ev.comentario)}"</div>` : '';
                const dateHtml    = ev.fecha_evaluacion ? `<small class="text-muted d-block">Fecha: ${ev.fecha_evaluacion}</small>` : '';

                const key = `${aprendizId}_${ev.ra_id}`;
                calificarData[key] = {
                    aprendizId,
                    raId:      ev.ra_id,
                    raNombre:  ev.ra_nombre || '',
                    fichaId:   currentFichaId,
                    concepto:  ev.concepto || '',
                    comentario: ev.comentario || ''
                };

                tbody.innerHTML += `
                    <tr>
                      <td>
                        <div class="fw-semibold text-dark">${esc(ev.ra_nombre)}</div>
                        <small class="text-muted font-monospace">${esc(ev.ra_codigo)}</small>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark font-monospace" style="max-width:250px;white-space:normal;">
                          ${esc(ev.competencia_codigo || 'N/A')} — ${esc((ev.competencia_nombre || 'General').substring(0,40))}
                        </span>
                      </td>
                      <td>
                        <span class="badge-soft ${cl[1]}">${cl[0]}</span>
                        ${commentHtml}
                        ${dateHtml}
                      </td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-soft" onclick="abrirCalificarDesdeKey('${key}')">
                          <i class="bi bi-pencil-square"></i> Calificar
                        </button>
                      </td>
                    </tr>`;
            });
        }

        const container = document.getElementById('lista_feedback_detalle');
        container.innerHTML = '';
        const retros = detalleRetroalimentacion[aprendizId] || [];

        if (retros.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-muted">Sin notas de seguimiento registradas.</div>';
        } else {
            retros.forEach(r => {
                const fi = feedbackIconos[r.tipo] || ['bi bi-info-circle-fill text-info', 'Observación', 'info'];
                const privBadge = r.privada == 1
                    ? '<span class="badge bg-danger ms-2"><i class="bi bi-eye-slash-fill me-1"></i>Privado</span>'
                    : '';
                container.innerHTML += `
                    <div class="p-3 mb-3 border rounded shadow-sm bg-white" style="border-left:5px solid var(--bs-${fi[2]}) !important;">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                          <i class="${fi[0]}" style="font-size:1.1rem;"></i>
                          <strong class="text-dark">${fi[1]}</strong>
                          ${privBadge}
                        </div>
                        <small class="text-muted">${r.fecha_creacion}</small>
                      </div>
                      <p class="text-muted mb-1 font-monospace" style="font-size:.85rem;">
                        ${esc(r.contenido || '').replace(/\n/g,'<br>')}
                      </p>
                      <div class="text-end">
                        <small class="text-muted">Instructor: <strong>${esc(r.instructor_nombre)}</strong></small>
                      </div>
                    </div>`;
            });
        }

        modalDetalle.show();
    }

    function abrirCalificarDesdeKey(key) {
        const d = calificarData[key];
        if (!d) return;
        document.getElementById('calif_aprendiz_id').value       = d.aprendizId;
        document.getElementById('calif_ra_id').value              = d.raId;
        document.getElementById('calif_ficha_id').value           = d.fichaId;
        document.getElementById('calif_actividad_nombre').innerText = d.raNombre;
        // Map DB concepto to form option value
        const map = { 'A': 'aprobado', 'D': 'en_proceso', 'pendiente': 'no_aplica' };
        document.getElementById('calif_concepto').value  = map[d.concepto] || 'aprobado';
        document.getElementById('calif_comentario').value = d.comentario;
        modalCalificar.show();
    }

    function cerrarModalCalificar() { modalCalificar.hide(); }

    function abrirModalRetroalimentacion(aprendizId, nombre) {
        document.getElementById('retro_aprendiz_id').value        = aprendizId;
        document.getElementById('retro_aprendiz_nombre').innerText = nombre;
        modalRetro.show();
    }
    </script>
  <?php endif; ?>
<?php endif; ?>
