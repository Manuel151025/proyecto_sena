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

$user_id = (int)getCurrentUser()['id'];
$user_rol = getCurrentRole();

// Obtener datos de perfil si es aprendiz
$aprendiz_id = 0;
$ficha_id = 0;
if ($user_rol === ROL_APRENDIZ) {
    try {
        $stmt = $db->prepare("SELECT id, ficha_id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $ap = $stmt->fetch();
        if ($ap) {
            $aprendiz_id = (int)$ap['id'];
            $ficha_id    = (int)$ap['ficha_id'];
        } else {
            $errors[] = 'No se encontró perfil de aprendiz para este usuario.';
        }
    } catch (Exception $e) {
        $errors[] = 'Error al consultar perfil del aprendiz.';
    }
}

// =========================================================
// POST: Enviar evidencia (Aprendiz)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enviar_evidencia') {
    if ($user_rol !== ROL_APRENDIZ) {
        $errors[] = 'Solo los aprendices pueden enviar evidencias.';
    } else {
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (empty($titulo)) $errors[] = 'El título de la evidencia es obligatorio.';

        $archivo_url = null;
        $tipo_archivo = null;
        $tamanio_kb  = 0;

        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['archivo']['tmp_name'];
            $fileName    = $_FILES['archivo']['name'];
            $tamanio_kb  = (int)round($_FILES['archivo']['size'] / 1024);
            $tipo_archivo = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $uploadDir = __DIR__ . '/../../uploads/evidencias/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = md5(time() . $fileName) . '.' . $tipo_archivo;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $archivo_url = 'uploads/evidencias/' . $newFileName;
            } else {
                $errors[] = 'No se pudo guardar el archivo subido.';
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO evidencias (aprendiz_id, ficha_id, titulo, descripcion, archivo_url, tipo_archivo, tamaño_kb, estado, fecha_envio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'enviada', CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$aprendiz_id, $ficha_id, $titulo, $descripcion, $archivo_url, $tipo_archivo, $tamanio_kb]);
                $evidencia_id = (int)$db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Crear', 'Evidencias', 'evidencias', ?, ?)
                ");
                $stmt->execute([$user_id, $evidencia_id, "Subió evidencia: $titulo"]);

                $db->commit();
                $successMessage = 'Evidencia enviada correctamente. Su instructor será notificado.';
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al enviar la evidencia: ' . $e->getMessage();
            }
        }
    }
}

// =========================================================
// POST: Calificar evidencia (Instructor / Coordinador)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'calificar_evidencia') {
    if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
        $errors[] = 'No tiene permisos para calificar evidencias.';
    } else {
        $evidencia_id  = (int)($_POST['evidencia_id'] ?? 0);
        $concepto_form = $_POST['concepto'] ?? 'en_proceso'; // valor del formulario

        // Mapear a ENUM de evaluaciones: A, D, pendiente
        $concepto_map = ['aprobado' => 'A', 'en_proceso' => 'D', 'no_aplica' => 'pendiente'];
        $concepto_db  = $concepto_map[$concepto_form] ?? 'pendiente';

        $estado_evidencia = match($concepto_form) {
            'aprobado'  => 'aprobada',
            'en_proceso' => 'revisada',
            default     => 'rechazada',
        };
        $tipo_retro = ($concepto_form === 'aprobado') ? 'fortaleza' : 'aspecto_mejorar';
        $comentario = trim($_POST['comentario'] ?? '');

        if ($evidencia_id <= 0) $errors[] = 'Evidencia no válida.';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("SELECT evaluacion_id, aprendiz_id, ficha_id, titulo FROM evidencias WHERE id = ?");
                $stmt->execute([$evidencia_id]);
                $evidencia = $stmt->fetch();

                if ($evidencia) {
                    $eval_id = $evidencia['evaluacion_id'];

                    // 1. Actualizar estado de la evidencia
                    $stmt = $db->prepare("
                        UPDATE evidencias
                        SET estado = ?, retroalimentacion = ?, fecha_revision = CURRENT_DATE
                        WHERE id = ?
                    ");
                    $stmt->execute([$estado_evidencia, $comentario, $evidencia_id]);

                    // 2. Si tiene evaluación vinculada, actualizarla
                    if ($eval_id) {
                        $stmt = $db->prepare("
                            UPDATE evaluaciones
                            SET concepto = ?, comentario = ?, instructor_id = ?, fecha_evaluacion = CURRENT_DATE
                            WHERE id = ?
                        ");
                        $stmt->execute([$concepto_db, $comentario, $user_id, $eval_id]);
                    }

                    // 3. Registrar retroalimentación (evaluacion_id puede ser NULL)
                    $stmt = $db->prepare("
                        INSERT INTO retroalimentacion (evaluacion_id, aprendiz_id, instructor_id, tipo, contenido, fecha_creacion)
                        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([$eval_id ?: null, $evidencia['aprendiz_id'], $user_id, $tipo_retro, $comentario]);

                    // 4. Recalcular cumplimiento de la ficha
                    $ficha_id_ev = $evidencia['ficha_id'];
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as total, SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas
                        FROM evidencias WHERE ficha_id = ?
                    ");
                    $stmt->execute([$ficha_id_ev]);
                    $stats = $stmt->fetch();
                    if ($stats && (int)$stats['total'] > 0) {
                        $cump = ((float)$stats['aprobadas'] / (float)$stats['total']) * 100;
                        $db->prepare("UPDATE fichas SET cumplimiento_porcentaje = ? WHERE id = ?")->execute([$cump, $ficha_id_ev]);
                    }

                    // 5. Log
                    $stmt = $db->prepare("
                        INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                        VALUES (?, 'Calificar', 'Evidencias', 'evidencias', ?, ?)
                    ");
                    $stmt->execute([$user_id, $evidencia_id, "Calificó evidencia: " . $evidencia['titulo'] . " como $concepto_form"]);

                    $db->commit();
                    $successMessage = 'Evidencia calificada y retroalimentación registrada con éxito.';
                } else {
                    $db->rollBack();
                    $errors[] = 'Evidencia no encontrada.';
                }
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al calificar la evidencia: ' . $e->getMessage();
            }
        }
    }
}

// =========================================================
// Cargar listado de evidencias
// =========================================================
$evidencias = [];
try {
    if ($user_rol === ROL_APRENDIZ) {
        $stmt = $db->prepare("
            SELECT ev.*, ra.denominacion AS ra_denominacion, u.nombre AS instructor_revisor
            FROM evidencias ev
            LEFT JOIN evaluaciones eval ON ev.evaluacion_id = eval.id
            LEFT JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
            LEFT JOIN usuarios u ON eval.instructor_id = u.id
            WHERE ev.aprendiz_id = ?
            ORDER BY ev.fecha_envio DESC
        ");
        $stmt->execute([$aprendiz_id]);
        $evidencias = $stmt->fetchAll();
    } else {
        $evidencias = $db->query("
            SELECT ev.*, ra.denominacion AS ra_denominacion,
                   f.numero_ficha, u_ap.nombre AS aprendiz_nombre, u_ap.email AS aprendiz_email
            FROM evidencias ev
            LEFT JOIN evaluaciones eval ON ev.evaluacion_id = eval.id
            LEFT JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
            JOIN fichas f    ON ev.ficha_id = f.id
            JOIN aprendices ap ON ev.aprendiz_id = ap.id
            JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
            ORDER BY ev.estado = 'enviada' DESC, ev.fecha_envio DESC
        ")->fetchAll();
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar evidencias: ' . $e->getMessage();
}

$estados_badge = [
    'enviada'   => ['Recibido',  'info'],
    'revisada'  => ['Revisado',  'secondary'],
    'aprobada'  => ['Aprobado',  'success'],
    'rechazada' => ['Rechazado', 'danger'],
];

$pageTitle   = 'Evidencias Académicas · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Evidencias y Entregables</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Envía tus trabajos prácticos y consulta las valoraciones de tu instructor.
      <?php else: ?>
        Revisa, retroalimenta y califica las evidencias enviadas por los aprendices.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($user_rol === ROL_APRENDIZ): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubir">
    <i class="bi bi-cloud-upload me-1"></i> Subir Evidencia
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 glass-card text-danger" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <ul class="mb-0 ps-3 d-inline-block">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12">
    <div class="card glass-card border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
              <tr>
                <th class="ps-4">Título / Entrega</th>
                <?php if ($user_rol !== ROL_APRENDIZ): ?>
                  <th>Aprendiz / Ficha</th>
                <?php endif; ?>
                <th>Resultado de Aprendizaje</th>
                <th>Fecha de Envío</th>
                <th>Estado</th>
                <th class="pe-4 text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($evidencias as $ev): ?>
              <tr>
                <td class="ps-4">
                  <div class="fw-semibold text-dark"><?= htmlspecialchars($ev['titulo']) ?></div>
                  <small class="text-muted d-block text-truncate" style="max-width:300px;">
                    <?= htmlspecialchars($ev['descripcion'] ?: 'Sin descripción') ?>
                  </small>
                  <?php if (!empty($ev['archivo_url'])): ?>
                    <a href="<?= APP_URL . '/' . htmlspecialchars($ev['archivo_url']) ?>" target="_blank" class="small text-primary text-decoration-none">
                      <i class="bi bi-paperclip me-1"></i>Descargar archivo (.<?= htmlspecialchars($ev['tipo_archivo'] ?? '') ?>)
                    </a>
                  <?php endif; ?>
                </td>
                <?php if ($user_rol !== ROL_APRENDIZ): ?>
                  <td>
                    <div class="fw-semibold text-dark"><?= htmlspecialchars($ev['aprendiz_nombre'] ?? '') ?></div>
                    <small class="badge bg-soft primary">Ficha #<?= htmlspecialchars($ev['numero_ficha']) ?></small>
                  </td>
                <?php endif; ?>
                <td>
                  <span class="text-muted small"><?= htmlspecialchars($ev['ra_denominacion'] ?? 'Sin resultado asociado') ?></span>
                </td>
                <td>
                  <div class="small"><?= date('d/m/Y h:i A', strtotime($ev['fecha_envio'])) ?></div>
                </td>
                <td>
                  <?php $badge = $estados_badge[$ev['estado']] ?? ['Desconocido', 'secondary']; ?>
                  <span class="badge-soft <?= $badge[1] ?>">
                    <?= $badge[0] ?>
                  </span>
                </td>
                <td class="pe-4 text-end">
                  <?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR]) && $ev['estado'] === 'enviada'): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCalificar"
                            onclick="prepararCalificacion(<?= $ev['id'] ?>, <?= json_encode($ev['titulo']) ?>)">
                      <i class="bi bi-pencil-square me-1"></i>Calificar
                    </button>
                  <?php elseif (!empty($ev['retroalimentacion'])): ?>
                    <button class="btn btn-sm btn-soft"
                            onclick="alert(<?= json_encode('Retroalimentación del Instructor:\n\n' . $ev['retroalimentacion']) ?>)">
                      <i class="bi bi-chat-left-text me-1"></i>Ver Retro
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">Sin revisión</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($evidencias)): ?>
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="bi bi-file-earmark-arrow-up d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
                  No se han registrado entregas de evidencias todavía.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Subir Evidencia (Aprendiz) -->
<?php if ($user_rol === ROL_APRENDIZ): ?>
<div class="modal fade" id="modalSubir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Enviar Evidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="enviar_evidencia">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Título de la Entrega</label>
            <input type="text" name="titulo" class="form-control" placeholder="Ej. Solución del taller de CSS Grid" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Comentarios / Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="Añade detalles sobre tu entrega..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Archivo adjunto (PDF, ZIP, DOCX, etc.)</label>
            <input type="file" name="archivo" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Subir Evidencia</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Calificar Evidencia (Instructor / Coordinador) -->
<?php if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])): ?>
<div class="modal fade" id="modalCalificar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Calificar Evidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="calificar_evidencia">
        <input type="hidden" name="evidencia_id" id="calificar_evidencia_id">
        <div class="modal-body">
          <div class="mb-3 p-3 rounded" style="background:rgba(0,0,0,0.02)">
            <span class="text-muted small">Evidencia seleccionada:</span>
            <div class="fw-bold" id="calificar_titulo">Ninguna</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Concepto Evaluativo</label>
            <select name="concepto" class="form-select" required>
              <option value="aprobado">Aprobado (A)</option>
              <option value="en_proceso">En Proceso (D)</option>
              <option value="no_aplica">No Aplica</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Comentarios de Retroalimentación</label>
            <textarea name="comentario" class="form-control" rows="4"
                      placeholder="Indica los logros o aspectos a mejorar de la entrega..." required></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar Calificación</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function prepararCalificacion(id, titulo) {
    document.getElementById('calificar_evidencia_id').value = id;
    document.getElementById('calificar_titulo').textContent = titulo;
}
</script>
<?php endif; ?>
