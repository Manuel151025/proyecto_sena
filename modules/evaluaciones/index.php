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
$success = '';

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
        $errors[] = 'Error al verificar perfil del aprendiz.';
    }
}

// Procesar cambio de concepto (solo instructor/coordinador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'evaluar') {
    if ($user_rol === ROL_INSTRUCTOR || $user_rol === ROL_COORDINADOR) {
        try {
            $eval_id = (int)($_POST['evaluacion_id'] ?? 0);
            $nuevo_concepto = trim($_POST['concepto'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');
            $motivo = trim($_POST['motivo'] ?? '');

            if ($eval_id <= 0) {
                throw new Exception('ID de evaluación inválido (recibido: ' . htmlspecialchars($_POST['evaluacion_id'] ?? 'vacío') . '). Recarga la página e intenta de nuevo.');
            }

            if (!in_array($nuevo_concepto, ['A', 'D', 'pendiente'])) {
                throw new Exception('Concepto no válido: "' . htmlspecialchars($nuevo_concepto) . '". Selecciona Aprobado (A) o No Aprobado (D).');
            }

            // Verificar que la evaluación existe y pertenece a este instructor
            if ($user_rol === ROL_INSTRUCTOR) {
                $stmtCurrent = $db->prepare("SELECT concepto FROM evaluaciones WHERE id = ? AND instructor_id = ?");
                $stmtCurrent->execute([$eval_id, $user_id]);
            } else {
                $stmtCurrent = $db->prepare("SELECT concepto FROM evaluaciones WHERE id = ?");
                $stmtCurrent->execute([$eval_id]);
            }
            $conceptoAnterior = $stmtCurrent->fetchColumn();

            if ($conceptoAnterior === false) {
                throw new Exception('Evaluación #' . $eval_id . ' no encontrada o sin permiso para editarla.');
            }

            // Actualizar evaluación
            $stmtUpdate = $db->prepare("UPDATE evaluaciones SET concepto = ?, comentario = ?, fecha_evaluacion = CURDATE(), fecha_actualizacion = NOW() WHERE id = ?");
            $stmtUpdate->execute([$nuevo_concepto, $comentario, $eval_id]);

            // Registrar en historial si hubo cambio
            if ($conceptoAnterior !== $nuevo_concepto) {
                // Exigir motivo si cambia de una calificación ya dada (A o D)
                if (in_array($conceptoAnterior, ['A', 'D']) && empty($motivo)) {
                    throw new Exception('El motivo del cambio de calificación es requerido.');
                }

                $stmtHist = $db->prepare("INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo) VALUES (?, ?, ?, ?, ?)");
                $stmtHist->execute([$eval_id, $user_id, $conceptoAnterior, $nuevo_concepto, $motivo ?: 'Calificación inicial']);
            }

            $success = 'Evaluación #' . $eval_id . ' actualizada correctamente. Concepto: ' . $nuevo_concepto;
        } catch (Exception $e) {
            $errors[] = 'Error al guardar evaluación: ' . $e->getMessage();
        }
    }
}

// Cargar fichas para filtros
$fichas = [];
if ($user_rol !== ROL_APRENDIZ) {
    try {
        if ($user_rol === ROL_INSTRUCTOR) {
            $stmtF = $db->prepare("SELECT id, numero_ficha FROM fichas WHERE instructor_id = ? ORDER BY numero_ficha");
            $stmtF->execute([$user_id]);
            $fichas = $stmtF->fetchAll();
        } else {
            $fichas = $db->query("SELECT id, numero_ficha FROM fichas ORDER BY numero_ficha")->fetchAll();
        }
    } catch (Exception $e) {
        $errors[] = 'Error al cargar fichas.';
    }
}

// Obtener filtros
$search = trim($_GET['search'] ?? '');
$filter_ficha = (int)($_GET['ficha_id'] ?? 0);
$filter_concepto = $_GET['concepto'] ?? '';

// Consulta de evaluaciones basada en Resultados de Aprendizaje
$sql = "
    SELECT eval.id, eval.concepto, eval.comentario, eval.fecha_evaluacion,
           ra.codigo as ra_codigo, ra.denominacion as ra_denominacion,
           c.nombre as competencia_nombre, c.codigo as competencia_codigo,
           f.numero_ficha, f.id as ficha_id,
           u_ap.nombre as aprendiz_nombre, u_ap.email as aprendiz_email,
           u_inst.nombre as instructor_nombre
    FROM evaluaciones eval
    JOIN resultados_aprendizaje ra ON eval.resultado_aprendizaje_id = ra.id
    JOIN competencias c ON ra.competencia_id = c.id
    JOIN fichas f ON eval.ficha_id = f.id
    JOIN aprendices ap ON eval.aprendiz_id = ap.id
    JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
    JOIN usuarios u_inst ON eval.instructor_id = u_inst.id
    WHERE 1=1
";
$params = [];

if ($user_rol === ROL_APRENDIZ) {
    $sql .= " AND eval.aprendiz_id = ?";
    $params[] = $aprendiz_id;
} elseif ($user_rol === ROL_INSTRUCTOR) {
    $sql .= " AND eval.instructor_id = ?";
    $params[] = $user_id;
    if ($filter_ficha > 0) {
        $sql .= " AND eval.ficha_id = ?";
        $params[] = $filter_ficha;
    }
} else {
    if ($filter_ficha > 0) {
        $sql .= " AND eval.ficha_id = ?";
        $params[] = $filter_ficha;
    }
}

if (!empty($search)) {
    $sql .= " AND (u_ap.nombre LIKE ? OR ra.codigo LIKE ? OR ra.denominacion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_concepto)) {
    $sql .= " AND eval.concepto = ?";
    $params[] = $filter_concepto;
}

$sql .= " ORDER BY eval.fecha_evaluacion DESC, eval.id DESC LIMIT 200";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $evaluaciones = $stmt->fetchAll();
} catch (Exception $e) {
    $evaluaciones = [];
    $errors[] = 'Error al cargar evaluaciones: ' . $e->getMessage();
}

// Estadísticas rápidas
$statsEval = ['total' => 0, 'aprobados' => 0, 'reprobados' => 0, 'pendientes' => 0];
try {
    $sqlStats = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
        SUM(CASE WHEN concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
        SUM(CASE WHEN concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
        FROM evaluaciones";
    if ($user_rol === ROL_APRENDIZ) {
        $sqlStats .= " WHERE aprendiz_id = " . (int)$aprendiz_id;
    } elseif ($user_rol === ROL_INSTRUCTOR) {
        $sqlStats .= " WHERE instructor_id = " . (int)$user_id;
    }
    $statsEval = $db->query($sqlStats)->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$conceptos_label = [
    'A' => ['Aprobado (A)', 'success', 'bi-check-circle-fill'],
    'D' => ['No Aprobado (D)', 'danger', 'bi-x-circle-fill'],
    'pendiente' => ['Pendiente', 'warning', 'bi-clock-fill']
];

$pageTitle = 'Juicios de Evaluación · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Juicios de Evaluación</h1>
    <p class="text-muted mb-0">
      <?php if ($user_rol === ROL_APRENDIZ): ?>
        Consulta el estado de tus Resultados de Aprendizaje (RA) evaluados con conceptos A (Aprobado) y D (Aún no competente).
      <?php else: ?>
        Gestiona los juicios evaluativos por Resultado de Aprendizaje. Los conceptos válidos son <strong>A</strong> (Aprobado) y <strong>D</strong> (Aún no competente).
      <?php endif; ?>
    </p>
  </div>
  <?php if ($user_rol !== ROL_APRENDIZ): ?>
  <div>
    <a href="<?= MODULES_PATH ?>/evaluaciones/importar_juicios.php" class="btn btn-primary">
      <i class="bi bi-file-earmark-excel me-1"></i> Importar Excel (Sofia Plus)
    </a>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>
    <?php foreach ($errors as $err): ?>
      <div><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-flat success mb-3">
  <i class="bi bi-check-circle-fill"></i>
  <div><?= htmlspecialchars($success) ?></div>
</div>
<?php endif; ?>

<!-- KPIs de Evaluación -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid var(--sena-primary);">
      <div class="kpi-content">
        <div class="label">Total Evaluaciones</div>
        <div class="value"><?= (int)$statsEval['total'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid #22c55e;">
      <div class="kpi-content">
        <div class="label">Aprobados (A)</div>
        <div class="value" style="color: #22c55e;"><?= (int)$statsEval['aprobados'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid #ef4444;">
      <div class="kpi-content">
        <div class="label">No Aprobados (D)</div>
        <div class="value" style="color: #ef4444;"><?= (int)$statsEval['reprobados'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi" style="border-left: 4px solid #eab308;">
      <div class="kpi-content">
        <div class="label">Pendientes</div>
        <div class="value" style="color: #eab308;"><?= (int)$statsEval['pendientes'] ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Barra de filtros -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar Aprendiz / RA</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0" style="border-color:rgba(255,255,255,0.15)"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Nombre o código RA..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>>
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Concepto Evaluativo</label>
        <select name="concepto" class="form-select">
          <option value="">Todos</option>
          <option value="A" <?= $filter_concepto === 'A' ? 'selected' : '' ?>>Aprobado (A)</option>
          <option value="D" <?= $filter_concepto === 'D' ? 'selected' : '' ?>>No Aprobado (D)</option>
          <option value="pendiente" <?= $filter_concepto === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Listado de Evaluaciones -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Resultado de Aprendizaje</th>
            <?php if ($user_rol !== ROL_APRENDIZ): ?>
              <th>Aprendiz</th>
            <?php endif; ?>
            <th>Competencia</th>
            <th>Fecha</th>
            <th>Concepto</th>
            <th class="pe-4 text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($evaluaciones as $eval): ?>
          <tr>
            <td class="ps-4">
              <div class="fw-semibold text-dark"><?= htmlspecialchars($eval['ra_codigo']) ?></div>
              <small class="text-muted" style="display:block; max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($eval['ra_denominacion']) ?>">
                <?= htmlspecialchars($eval['ra_denominacion']) ?>
              </small>
              <small class="badge bg-soft primary">Ficha #<?= htmlspecialchars($eval['numero_ficha']) ?></small>
            </td>
            <?php if ($user_rol !== ROL_APRENDIZ): ?>
              <td>
                <div class="fw-semibold text-dark"><?= htmlspecialchars($eval['aprendiz_nombre']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($eval['aprendiz_email']) ?></small>
              </td>
            <?php endif; ?>
            <td>
              <small class="fw-medium text-muted"><?= htmlspecialchars($eval['competencia_nombre']) ?></small>
            </td>
            <td>
              <span class="small"><?= $eval['fecha_evaluacion'] ? date('d/m/Y', strtotime($eval['fecha_evaluacion'])) : '—' ?></span>
            </td>
            <td>
              <span class="badge-soft <?= $conceptos_label[$eval['concepto']][1] ?>">
                <i class="bi <?= $conceptos_label[$eval['concepto']][2] ?> me-1"></i>
                <?= $conceptos_label[$eval['concepto']][0] ?>
              </span>
            </td>
            <td class="pe-4 text-end">
              <?php if ($user_rol !== ROL_APRENDIZ): ?>
              <button class="btn btn-sm btn-primary"
                data-bs-toggle="modal" data-bs-target="#modalEvaluar"
                data-eval-id="<?= $eval['id'] ?>"
                data-ra="<?= htmlspecialchars($eval['ra_codigo'], ENT_QUOTES) ?>"
                data-aprendiz="<?= htmlspecialchars($eval['aprendiz_nombre'], ENT_QUOTES) ?>"
                data-concepto="<?= htmlspecialchars($eval['concepto'], ENT_QUOTES) ?>"
                data-comentario="<?= htmlspecialchars($eval['comentario'] ?? '', ENT_QUOTES) ?>">
                <i class="bi bi-pencil-square me-1"></i>Evaluar
              </button>
              <?php endif; ?>
              <button class="btn btn-sm btn-soft" onclick="alert(<?= json_encode('Retroalimentación:\n\n' . ($eval['comentario'] ?: 'Sin comentarios.')) ?>)">
                <i class="bi bi-chat-left-dots"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($evaluaciones)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-pencil-square d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
              No hay evaluaciones registradas con los filtros seleccionados.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal para Evaluar -->
<?php if ($user_rol !== ROL_APRENDIZ): ?>
<div class="modal fade" id="modalEvaluar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0; border-radius: 16px; overflow: hidden;">
      <form method="POST">
        <input type="hidden" name="action" value="evaluar">
        <input type="hidden" name="evaluacion_id" id="evalId">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--sena-primary), #2d8000); color: white; border: 0;">
          <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Juicio Evaluativo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <div class="text-muted small text-uppercase">Resultado de Aprendizaje</div>
            <div class="fw-bold" id="evalRA">—</div>
          </div>
          <div class="mb-3">
            <div class="text-muted small text-uppercase">Aprendiz</div>
            <div class="fw-semibold" id="evalAprendiz">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Concepto Evaluativo <span class="text-danger">*</span></label>
            <div class="d-flex gap-2">
              <label class="btn btn-outline-success flex-grow-1 concepto-radio" style="border-radius: 10px;">
                <input type="radio" name="concepto" value="A" class="d-none" required>
                <i class="bi bi-check-circle-fill me-1"></i> Aprobado (A)
              </label>
              <label class="btn btn-outline-danger flex-grow-1 concepto-radio" style="border-radius: 10px;">
                <input type="radio" name="concepto" value="D" class="d-none">
                <i class="bi bi-x-circle-fill me-1"></i> No Aprobado (D)
              </label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Comentario / Retroalimentación</label>
            <textarea name="comentario" id="evalComentario" class="form-control" rows="3" placeholder="Escriba su observación sobre el desempeño del aprendiz..."></textarea>
          </div>
          <div class="mb-0" id="div_eval_motivo" style="display:none;">
            <label class="form-label fw-semibold text-danger">Motivo del cambio *</label>
            <input type="text" name="motivo" id="eval_motivo" class="form-control" placeholder="Ej: Plan de mejoramiento completado">
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar Evaluación</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Poblar modal usando el evento de Bootstrap (método fiable)
const modalEvaluar = document.getElementById('modalEvaluar');
let originalConcepto = '';

if (modalEvaluar) {
  modalEvaluar.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget; // botón que disparó el modal
    if (!btn) return;

    const evalId    = btn.dataset.evalId;
    const ra        = btn.dataset.ra;
    const aprendiz  = btn.dataset.aprendiz;
    const concepto  = btn.dataset.concepto;
    const comentario = btn.dataset.comentario || '';

    document.getElementById('evalId').value           = evalId;
    document.getElementById('evalRA').textContent     = ra;
    document.getElementById('evalAprendiz').textContent = aprendiz;
    document.getElementById('evalComentario').value   = comentario;

    originalConcepto = concepto; // Guardar el concepto original

    // Resetear motivo
    const divMotivo = document.getElementById('div_eval_motivo');
    const inputMotivo = document.getElementById('eval_motivo');
    if (divMotivo && inputMotivo) {
      divMotivo.style.display = 'none';
      inputMotivo.value = '';
      inputMotivo.required = false;
    }

    // Marcar el radio del concepto actual
    document.querySelectorAll('.concepto-radio').forEach(label => {
      label.classList.remove('active');
      const radio = label.querySelector('input[type="radio"]');
      if (radio.value === concepto) {
        radio.checked = true;
        label.classList.add('active');
      } else {
        radio.checked = false;
      }
    });

    console.log('[Eval] Modal abierto para evaluación ID:', evalId, '| concepto actual:', concepto);
  });

  // Toggle visual del campo motivo según el concepto seleccionado
  document.querySelectorAll('.concepto-radio').forEach(label => {
    label.addEventListener('click', function() {
      // Toggle de active class se maneja más abajo, aquí detectamos el radio de este label
      setTimeout(() => {
        const radio = this.querySelector('input[type="radio"]');
        if (!radio) return;
        const nuevoConcepto = radio.value;
        const divMotivo = document.getElementById('div_eval_motivo');
        const inputMotivo = document.getElementById('eval_motivo');

        if (originalConcepto && originalConcepto !== 'pendiente' && originalConcepto !== nuevoConcepto) {
          if (divMotivo && inputMotivo) {
            divMotivo.style.display = 'block';
            inputMotivo.required = true;
          }
        } else {
          if (divMotivo && inputMotivo) {
            divMotivo.style.display = 'none';
            inputMotivo.required = false;
          }
        }
      }, 50);
    });
  });

  // Guardia antes de enviar
  modalEvaluar.querySelector('form')?.addEventListener('submit', function(e) {
    const id = parseInt(document.getElementById('evalId').value, 10);
    const concepto = this.querySelector('input[name="concepto"]:checked');
    const motivo = document.getElementById('eval_motivo').value.trim();

    if (!id || id <= 0) {
      e.preventDefault();
      alert('Error: ID de evaluación no cargado. Cierra el modal y haz clic en Evaluar nuevamente.');
      return;
    }
    if (!concepto) {
      e.preventDefault();
      alert('Debes seleccionar un concepto: Aprobado (A) o No Aprobado (D).');
      return;
    }

    if (originalConcepto && originalConcepto !== 'pendiente' && originalConcepto !== concepto.value && !motivo) {
      e.preventDefault();
      alert('Debes ingresar el motivo del cambio de calificación (ej. Plan de mejoramiento completado).');
      return;
    }

    console.log('[Eval] Enviando evaluación ID:', id, '| nuevo concepto:', concepto.value);
  });
}

// Toggle visual de radio buttons
document.querySelectorAll('.concepto-radio').forEach(label => {
  label.addEventListener('click', function() {
    document.querySelectorAll('.concepto-radio').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
  });
});
</script>

<style>
.concepto-radio.active {
    font-weight: 600;
}
.concepto-radio:has(input[value="A"]).active {
    background-color: #22c55e !important;
    color: white !important;
    border-color: #22c55e !important;
}
.concepto-radio:has(input[value="D"]).active {
    background-color: #ef4444 !important;
    color: white !important;
    border-color: #ef4444 !important;
}
</style>
