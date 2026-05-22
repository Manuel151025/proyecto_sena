<?php
declare(strict_types=1);

/**
 * RETROALIMENTACIÓN — modules/retroalimentacion/index.php
 *
 * - Aprendiz: ve retroalimentaciones que le dejaron (excluye las privadas).
 * - Instructor: ve las suyas + puede crear nuevas para aprendices de SUS fichas.
 * - Coordinador: ve todas + puede crear nuevas para cualquier aprendiz.
 *
 * POST: action=create_feedback
 *   - aprendiz_id, tipo, contenido, privada (opcional)
 *   - Validaciones: contenido 10-2000 chars, tipo en ENUM, aprendiz autorizado
 *   - Si el rol es instructor, verifica que el aprendiz pertenezca a sus fichas
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireAuth();

$db       = Database::getConnection();
$errors   = [];
$success  = '';

$user_id  = (int)getCurrentUser()['id'];
$user_rol = getCurrentRole();

$tipos_validos = ['fortaleza', 'aspecto_mejorar', 'recomendacion'];

// =====================================================================
// POST: crear nueva retroalimentación (solo instructor/coordinador)
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'create_feedback'
    && $user_rol !== ROL_APRENDIZ) {

    $aprendiz_post = (int)($_POST['aprendiz_id'] ?? 0);
    $tipo          = $_POST['tipo'] ?? '';
    $contenido     = trim($_POST['contenido'] ?? '');
    $privada       = !empty($_POST['privada']) ? 1 : 0;

    if ($aprendiz_post <= 0) {
        $errors[] = 'Debes seleccionar un aprendiz.';
    }
    if (!in_array($tipo, $tipos_validos, true)) {
        $errors[] = 'Tipo de retroalimentación no válido.';
    }
    if (mb_strlen($contenido) < 10) {
        $errors[] = 'El contenido debe tener al menos 10 caracteres.';
    }
    if (mb_strlen($contenido) > 2000) {
        $errors[] = 'El contenido no puede exceder 2000 caracteres.';
    }

    // Si es instructor, verificar que el aprendiz pertenezca a una de sus fichas
    if (empty($errors) && $user_rol === ROL_INSTRUCTOR) {
        try {
            $stmt = $db->prepare("
                SELECT 1
                FROM aprendices ap
                JOIN fichas f ON ap.ficha_id = f.id
                WHERE ap.id = ? AND f.instructor_id = ?
                LIMIT 1
            ");
            $stmt->execute([$aprendiz_post, $user_id]);
            if (!$stmt->fetchColumn()) {
                $errors[] = 'No puedes dar retroalimentación a un aprendiz que no es de tus fichas.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al validar autorización.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO retroalimentacion
                    (aprendiz_id, instructor_id, tipo, contenido, privada)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$aprendiz_post, $user_id, $tipo, $contenido, $privada]);
            $success = 'Retroalimentación registrada correctamente.';
        } catch (Exception $e) {
            $errors[] = 'No se pudo guardar la retroalimentación.';
        }
    }
}

// =====================================================================
// Cargar perfil de aprendiz si corresponde
// =====================================================================

$aprendiz_id = 0;
if ($user_rol === ROL_APRENDIZ) {
    try {
        $stmt = $db->prepare("SELECT id FROM aprendices WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $aprendiz_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        $errors[] = 'Error al cargar perfil de aprendiz.';
    }
}

// =====================================================================
// Cargar retroalimentaciones (filtradas por rol)
// =====================================================================

$feedbacks = [];
try {
    if ($user_rol === ROL_APRENDIZ) {
        $stmt = $db->prepare("
            SELECT r.*, u_inst.nombre as instructor_nombre, u_inst.avatar_color as inst_color
            FROM retroalimentacion r
            JOIN usuarios u_inst ON r.instructor_id = u_inst.id
            WHERE r.aprendiz_id = ? AND r.privada = 0
            ORDER BY r.fecha_creacion DESC
        ");
        $stmt->execute([$aprendiz_id]);
        $feedbacks = $stmt->fetchAll();
    } else {
        // Instructor: solo las suyas. Coordinador: todas.
        $sql = "
            SELECT r.*, u_ap.nombre as aprendiz_nombre, u_ap.email as aprendiz_email,
                   u_inst.nombre as instructor_nombre
            FROM retroalimentacion r
            JOIN aprendices ap   ON r.aprendiz_id = ap.id
            JOIN usuarios u_ap   ON ap.usuario_id = u_ap.id
            JOIN usuarios u_inst ON r.instructor_id = u_inst.id
        ";
        if ($user_rol === ROL_INSTRUCTOR) {
            $sql .= " WHERE r.instructor_id = ? ";
        }
        $sql .= " ORDER BY r.fecha_creacion DESC";

        $stmt = $db->prepare($sql);
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmt->execute([$user_id]);
        } else {
            $stmt->execute();
        }
        $feedbacks = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar retroalimentaciones.';
}

// =====================================================================
// Cargar aprendices disponibles para el formulario (solo instr/coord)
// =====================================================================

$aprendices_disponibles = [];
if ($user_rol !== ROL_APRENDIZ) {
    try {
        if ($user_rol === ROL_INSTRUCTOR) {
            // Solo aprendices de fichas asignadas a este instructor
            $stmt = $db->prepare("
                SELECT ap.id, u.nombre, f.numero_ficha,
                       ap.numero_documento, ap.tipo_documento
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                JOIN fichas f   ON ap.ficha_id = f.id
                WHERE f.instructor_id = ? AND ap.estado = 'matriculado'
                ORDER BY f.numero_ficha, u.nombre
            ");
            $stmt->execute([$user_id]);
        } else {
            // Coordinador: todos los aprendices matriculados
            $stmt = $db->prepare("
                SELECT ap.id, u.nombre, f.numero_ficha,
                       ap.numero_documento, ap.tipo_documento
                FROM aprendices ap
                JOIN usuarios u ON ap.usuario_id = u.id
                LEFT JOIN fichas f ON ap.ficha_id = f.id
                WHERE ap.estado = 'matriculado'
                ORDER BY f.numero_ficha, u.nombre
            ");
            $stmt->execute();
        }
        $aprendices_disponibles = $stmt->fetchAll();
    } catch (Exception $e) {
        // dejar lista vacía
    }
}

$tipos_label = [
    'fortaleza'        => ['Fortaleza',          'success', 'bi-award'],
    'aspecto_mejorar'  => ['Aspecto a mejorar',  'warning', 'bi-graph-up-arrow'],
    'recomendacion'    => ['Recomendación',      'info',    'bi-info-circle'],
];

$pageTitle   = 'Retroalimentación Académica · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Retroalimentación Académica</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Revisa los comentarios, recomendaciones y fortalezas indicadas por tus instructores.
      <?php else: ?>
        Gestiona y registra retroalimentación para los aprendices.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($user_rol !== ROL_APRENDIZ): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaRetro">
      <i class="bi bi-plus-lg me-1"></i> Nueva retroalimentación
    </button>
  <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
  <div class="alert-flat success mb-3">
    <i class="bi bi-check-circle"></i>
    <div><?= htmlspecialchars($success) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert-flat danger mb-3">
    <i class="bi bi-exclamation-circle"></i>
    <div>
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($feedbacks as $fb): ?>
    <?php $meta = $tipos_label[$fb['tipo']] ?? ['—','secondary','bi-chat']; ?>
    <div class="col-md-6 col-lg-4">
      <div class="card glass-card h-100 border-0 shadow-sm">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <span class="badge-soft <?= $meta[1] ?>">
              <i class="bi <?= $meta[2] ?> me-1"></i>
              <?= $meta[0] ?>
            </span>
            <small class="text-muted"><?= date('d/m/Y h:i A', strtotime($fb['fecha_creacion'])) ?></small>
          </div>

          <?php if (!empty($fb['privada'])): ?>
            <div class="small mb-2">
              <span class="badge-soft secondary"><i class="bi bi-lock-fill me-1"></i>Privada</span>
            </div>
          <?php endif; ?>

          <p class="card-text text-dark flex-grow-1" style="font-size:.95rem;font-style:italic">
            "<?= htmlspecialchars($fb['contenido']) ?>"
          </p>

          <div class="border-top pt-2 mt-3 d-flex align-items-center gap-2">
            <?php if ($user_rol === ROL_APRENDIZ): ?>
              <div class="avatar"
                   style="width:30px;height:30px;font-size:.75rem;background:<?= htmlspecialchars($fb['inst_color'] ?? '#3B82F6') ?>">
                <?= getInitials($fb['instructor_nombre']) ?>
              </div>
              <div class="small">
                <span class="text-muted d-block" style="font-size:.7rem">Instructor:</span>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($fb['instructor_nombre']) ?></span>
              </div>
            <?php else: ?>
              <div class="small">
                <span class="text-muted d-block" style="font-size:.7rem">Para aprendiz:</span>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($fb['aprendiz_nombre'] ?? 'Desconocido') ?></span>
                <small class="text-muted d-block" style="font-size:.65rem">
                  Por: <?= htmlspecialchars($fb['instructor_nombre']) ?>
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($feedbacks)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-chat-left-text d-block mb-2" style="font-size:3rem;opacity:.3"></i>
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Aún no has recibido retroalimentaciones.
      <?php else: ?>
        No has registrado retroalimentaciones todavía. Usa el botón
        <strong>Nueva retroalimentación</strong> arriba para crear la primera.
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ===== Modal: nueva retroalimentación ===== -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="modal fade" id="modalNuevaRetro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="create_feedback">

        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-chat-left-quote me-2"></i>Nueva retroalimentación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">

          <?php if (empty($aprendices_disponibles)): ?>
            <div class="alert-flat warning mb-0">
              <i class="bi bi-exclamation-circle"></i>
              <div>
                <?php if ($user_rol === ROL_INSTRUCTOR): ?>
                  No tienes aprendices matriculados en tus fichas. Cuando se te asignen, podrás registrar retroalimentación.
                <?php else: ?>
                  No hay aprendices matriculados en el sistema.
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>

            <div class="mb-3">
              <label class="form-label">Aprendiz</label>
              <select name="aprendiz_id" class="form-select" required
                      data-picker
                      data-picker-label="Buscar aprendiz"
                      data-picker-placeholder="Escribe nombre, documento o ficha...">
                <option value="">Selecciona un aprendiz...</option>
                <?php foreach ($aprendices_disponibles as $ap): ?>
                  <option value="<?= (int)$ap['id'] ?>"
                          data-search="<?= htmlspecialchars(($ap['numero_documento'] ?? '') . ' ' . ($ap['numero_ficha'] ?? '')) ?>">
                    <?= htmlspecialchars($ap['nombre']) ?>
                    <?= !empty($ap['numero_documento']) ? ' — ' . htmlspecialchars($ap['tipo_documento'] ?? 'CC') . ' ' . htmlspecialchars($ap['numero_documento']) : '' ?>
                    <?= !empty($ap['numero_ficha']) ? ' · Ficha #' . htmlspecialchars($ap['numero_ficha']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Tipo</label>
              <div class="row g-2">
                <?php foreach ($tipos_label as $key => $meta): ?>
                  <div class="col-md-4">
                    <label class="d-block">
                      <input type="radio" name="tipo" value="<?= $key ?>" class="d-none" required
                             <?= $key === 'aspecto_mejorar' ? 'checked' : '' ?>>
                      <div class="card text-center p-2" style="cursor:pointer">
                        <i class="bi <?= $meta[2] ?>" style="font-size:1.5rem"></i>
                        <small class="mt-1"><?= $meta[0] ?></small>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Contenido</label>
              <textarea name="contenido" class="form-control" rows="5"
                        minlength="10" maxlength="2000"
                        placeholder="Describe la retroalimentación de forma constructiva..."
                        required></textarea>
              <div class="small text-muted mt-1">Entre 10 y 2000 caracteres.</div>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="privada" id="chkPrivada" value="1">
              <label class="form-check-label" for="chkPrivada">
                <i class="bi bi-lock-fill me-1"></i>
                Privada (el aprendiz no la verá; solo instructores y coordinadores)
              </label>
            </div>

          <?php endif; ?>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <?php if (!empty($aprendices_disponibles)): ?>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2 me-1"></i> Registrar
            </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
