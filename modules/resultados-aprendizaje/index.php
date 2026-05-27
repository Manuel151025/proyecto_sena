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

// Procesar formulario de creación de RAP manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_rap') {
    if (!hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)) {
        $errors[] = 'No tiene permisos para registrar Resultados de Aprendizaje.';
    } else {
        $competencia_id = (int)($_POST['competencia_id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $denominacion = trim($_POST['denominacion'] ?? '');

        if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia válida.';
        if (empty($codigo)) $errors[] = 'El código del RAP es obligatorio.';
        if (empty($denominacion)) $errors[] = 'La denominación del RAP es obligatoria.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$competencia_id, $codigo, $denominacion]);

                // Registrar log
                $logStmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Crear', 'RAPs', 'resultados_aprendizaje', ?, ?)
                ");
                $logStmt->execute([(int)getCurrentUser()['id'], (int)$db->lastInsertId(), "Creó el RAP $codigo para competencia id $competencia_id"]);

                $successMessage = 'Resultado de Aprendizaje (RAP) registrado exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al registrar RAP: ' . $e->getMessage();
            }
        }
    }
}

// Procesar edición de RAP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_rap') {
    if (!hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)) {
        $errors[] = 'No tiene permisos para editar Resultados de Aprendizaje.';
    } else {
        $id             = (int)($_POST['id'] ?? 0);
        $competencia_id = (int)($_POST['competencia_id'] ?? 0);
        $codigo         = trim($_POST['codigo'] ?? '');
        $denominacion   = trim($_POST['denominacion'] ?? '');

        if ($id <= 0)             $errors[] = 'RAP no válido.';
        if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia válida.';
        if (empty($codigo))       $errors[] = 'El código del RAP es obligatorio.';
        if (empty($denominacion)) $errors[] = 'La denominación del RAP es obligatoria.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE resultados_aprendizaje
                    SET competencia_id=?, codigo=?, denominacion=?
                    WHERE id=?
                ");
                $stmt->execute([$competencia_id, $codigo, $denominacion, $id]);

                $logStmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Editar', 'RAPs', 'resultados_aprendizaje', ?, ?)
                ");
                $logStmt->execute([(int)getCurrentUser()['id'], $id, "Editó el RAP $codigo"]);

                $successMessage = 'Resultado de Aprendizaje actualizado exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'Error al actualizar RAP: ' . $e->getMessage();
            }
        }
    }
}

// Procesar eliminación de RAP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_rap') {
    if (!hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)) {
        $errors[] = 'No tiene permisos para eliminar Resultados de Aprendizaje.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'RAP no válido.';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM resultados_aprendizaje WHERE id = ?");
                $stmt->execute([$id]);

                $logStmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Eliminar', 'RAPs', 'resultados_aprendizaje', ?, 'Eliminó el RAP')
                ");
                $logStmt->execute([(int)getCurrentUser()['id'], $id]);

                $successMessage = 'Resultado de Aprendizaje eliminado exitosamente.';
            } catch (Exception $e) {
                $errors[] = 'No se puede eliminar: el RAP tiene evaluaciones asociadas.';
            }
        }
    }
}

// Obtener competencias de la BD
$competencias = [];
try {
    $competencias = $db->query("
        SELECT c.id, c.nombre, c.codigo, p.nombre as programa 
        FROM competencias c
        JOIN programas p ON c.programa_id = p.id 
        WHERE c.estado = 'activo'
        ORDER BY p.nombre, c.codigo
    ")->fetchAll();
    
    // Cargar RAPs para cada competencia
    foreach ($competencias as &$comp) {
        $stmt = $db->prepare("SELECT id, codigo, denominacion FROM resultados_aprendizaje WHERE competencia_id = ? ORDER BY codigo");
        $stmt->execute([$comp['id']]);
        $comp['raps'] = $stmt->fetchAll();
    }
    unset($comp);
} catch (Exception $e) {
    $errors[] = 'Error al cargar las competencias o RAPs.';
}

$pageTitle = 'Resultados de Aprendizaje (RAP) · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Resultados de Aprendizaje (RAP)</h1>
    <p class="text-muted mb-0">Listado y gestión de RAPs asociados a las competencias de cada programa formativo.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
  <div class="d-flex gap-2">
    <a href="importar.php" class="btn btn-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar Masivo
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearRAP">
      <i class="bi bi-plus-lg me-1"></i> Nuevo RAP
    </button>
  </div>
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
  <?php foreach ($competencias as $comp): ?>
    <div class="col-md-6">
      <div class="card glass-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-soft info font-monospace"><?= htmlspecialchars($comp['codigo']) ?></span>
            <small class="text-muted"><?= htmlspecialchars($comp['programa']) ?></small>
          </div>
          <h5 class="fw-bold text-dark mb-3"><?= htmlspecialchars($comp['nombre']) ?></h5>
          
          <h6 class="text-muted small fw-bold mb-2">Resultados de Aprendizaje (RAP) Vinculados:</h6>
          <ul class="list-group list-group-flush small" style="background:transparent;">
            <?php foreach ($comp['raps'] as $rap): ?>
              <li class="list-group-item d-flex gap-2 align-items-start ps-0 border-0" style="background:transparent;">
                <span class="badge bg-success flex-shrink-0"><?= htmlspecialchars($rap['codigo']) ?></span>
                <span class="text-dark flex-grow-1"><?= htmlspecialchars($rap['denominacion']) ?></span>
                <?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
                <div class="d-flex gap-1 flex-shrink-0">
                  <button class="btn btn-sm btn-soft py-0 px-1" style="font-size:.75rem;"
                    onclick="abrirModalEditarRAP(
                      <?= $rap['id'] ?>, <?= $comp['id'] ?>,
                      <?= json_encode($rap['codigo']) ?>,
                      <?= json_encode($rap['denominacion']) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('¿Eliminar este RAP? Esta acción no se puede deshacer.')">
                    <input type="hidden" name="action" value="eliminar_rap">
                    <input type="hidden" name="id" value="<?= $rap['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-soft py-0 px-1 text-danger" style="font-size:.75rem;">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
            <?php if (empty($comp['raps'])): ?>
              <li class="list-group-item ps-0 border-0 text-muted small" style="background:transparent;">
                <i class="bi bi-info-circle me-1"></i>No hay RAPs vinculados a esta competencia todavía.
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($competencias)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-clipboard-check d-block mb-2" style="font-size:3rem; opacity:0.3;"></i>
      No hay competencias registradas en el sistema.
    </div>
  <?php endif; ?>
</div>

<!-- Modal Editar RAP -->
<?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
<div class="modal fade" id="modalEditarRAP" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Editar Resultado de Aprendizaje (RAP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="editar_rap">
        <input type="hidden" name="id" id="edit_rap_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Competencia Asociada</label>
            <select name="competencia_id" id="edit_rap_competencia_id" class="form-select" required>
              <?php foreach ($competencias as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Código del RAP</label>
            <input type="text" name="codigo" id="edit_rap_codigo" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Denominación del Resultado de Aprendizaje</label>
            <textarea name="denominacion" id="edit_rap_denominacion" class="form-control" rows="4" required></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalEditarRAP(id, competenciaId, codigo, denominacion) {
    document.getElementById('edit_rap_id').value             = id;
    document.getElementById('edit_rap_competencia_id').value = competenciaId;
    document.getElementById('edit_rap_codigo').value         = codigo;
    document.getElementById('edit_rap_denominacion').value   = denominacion;
    new bootstrap.Modal(document.getElementById('modalEditarRAP')).show();
}
</script>
<?php endif; ?>

<!-- Modal Registrar RAP -->
<?php if (hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)): ?>
<div class="modal fade" id="modalCrearRAP" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Nuevo Resultado de Aprendizaje (RAP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="crear_rap">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Competencia Asociada</label>
            <select name="competencia_id" class="form-select" required
                    data-picker
                    data-picker-label="Seleccionar competencia"
                    data-picker-placeholder="Código o nombre de la competencia...">
              <option value="" disabled selected>Seleccione Competencia...</option>
              <?php foreach ($competencias as $c): ?>
                <option value="<?= $c['id'] ?>"
                        data-search="<?= htmlspecialchars($c['codigo'] . ' ' . $c['nombre']) ?>">
                  <?= htmlspecialchars($c['codigo']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Código del RAP (ej: RAP 1, RAP-02)</label>
            <input type="text" name="codigo" class="form-control" placeholder="Ej. RAP 1" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Denominación del Resultado de Aprendizaje</label>
            <textarea name="denominacion" class="form-control" rows="4" placeholder="Describa el resultado de aprendizaje..." required></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar RAP</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
