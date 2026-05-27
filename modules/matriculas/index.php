<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$db = Database::getConnection();
$errors = [];
$successMessage = '';

// Procesar formulario de Matrícula (Creación de aprendiz)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'matricular') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden realizar matrículas de aprendices.';
    } else {
        $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');
        $email = trim($_POST['email'] ?? '');
        $numero_documento = trim($_POST['numero_documento'] ?? '');
        $tipo_documento = $_POST['tipo_documento'] ?? 'CC';
        $ficha_id = (int)($_POST['ficha_id'] ?? 0);
        $genero = $_POST['genero'] ?? 'O';
        $telefono = trim($_POST['telefono'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;

        // Validaciones básicas
        if (empty($nombre)) $errors[] = 'El nombre completo es obligatorio.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (empty($numero_documento)) $errors[] = 'El número de documento es obligatorio.';
        if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha de formación.';

        // Verificar si el email ya existe en la tabla usuarios
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'El correo electrónico ya se encuentra registrado.';
            }
        }

        // Verificar si el documento ya existe en la tabla aprendices
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM aprendices WHERE numero_documento = ?");
            $stmt->execute([$numero_documento]);
            if ($stmt->fetch()) {
                $errors[] = 'El número de documento ya se encuentra matriculado.';
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // 1. Crear el usuario (Rol aprendiz, contraseña por defecto Sena2026)
                $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                $avatar_color = $colors[array_rand($colors)];
                $password_hash = password_hash('Sena2026', PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado)
                    VALUES (?, ?, ?, 'aprendiz', ?, 'activo')
                ");
                $stmt->execute([$nombre, $email, $password_hash, $avatar_color]);
                $usuario_id = (int)$db->lastInsertId();

                // 2. Crear registro de aprendiz
                $stmt = $db->prepare("
                    INSERT INTO aprendices (usuario_id, ficha_id, numero_documento, tipo_documento, genero, fecha_nacimiento, telefono, ciudad, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'matriculado')
                ");
                $stmt->execute([$usuario_id, $ficha_id, $numero_documento, $tipo_documento, $genero, $fecha_nacimiento, $telefono, $ciudad]);
                $new_aprendiz_id = (int)$db->lastInsertId();

                // 2.5. Inicializar evaluaciones como 'pendiente' para todos los RAs
                inicializarEvaluacionesAprendiz($db, $new_aprendiz_id, $ficha_id);

                // 3. Incrementar el contador en la ficha
                $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$ficha_id]);

                // Registrar en logs del sistema
                $stmt = $db->prepare("
                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                    VALUES (?, 'Crear', 'Matriculas', 'aprendices', ?, ?)
                ");
                $stmt->execute([(int)getCurrentUser()['id'], $new_aprendiz_id, "Matriculó al aprendiz $nombre en ficha id $ficha_id"]);

                $db->commit();
                $successMessage = 'Aprendiz matriculado exitosamente.';
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al matricular aprendiz: ' . $e->getMessage();
            }
        }
    }
}

// Procesar formulario de Carga Masiva (CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cargar_csv') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden realizar esta acción.';
    } else {
        $ficha_id = (int)($_POST['ficha_id'] ?? 0);
        if ($ficha_id <= 0) {
            $errors[] = 'Debe seleccionar una ficha de destino válida.';
        } elseif (!isset($_FILES['file_csv']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error al subir el archivo CSV o no se seleccionó ninguno.';
        } else {
            $file = $_FILES['file_csv']['tmp_name'];
            $handle = fopen($file, 'r');
            if ($handle === false) {
                $errors[] = 'No se pudo abrir el archivo CSV.';
            } else {
                try {
                    $db->beginTransaction();

                    // Detectar delimitador (coma o punto y coma)
                    $firstLine = fgets($handle);
                    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                    rewind($handle);

                    $successCount = 0;
                    $warnings = [];
                    $rowNum = 0;
                    $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                    $password_hash = password_hash('Sena2026', PASSWORD_DEFAULT);

                    while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                        $rowNum++;
                        // Si es la primera fila y tiene texto de cabecera, la saltamos
                        if ($rowNum === 1 && (
                            stripos($data[0], 'nombre') !== false ||
                            stripos($data[0], 'nombre_completo') !== false ||
                            stripos($data[1], 'email') !== false ||
                            stripos($data[3], 'documento') !== false
                        )) {
                            continue;
                        }

                        // Validar número de columnas mínimo (al menos nombre, email, documento)
                        if (count($data) < 4) {
                            $warnings[] = "Fila $rowNum: Columnas insuficientes (se requieren al menos Nombre, Email, Tipo Doc y Num Doc).";
                            continue;
                        }

                        $nombre = mb_strtoupper(trim($data[0] ?? ''), 'UTF-8');
                        $email = trim($data[1] ?? '');
                        $tipo_doc = strtoupper(trim($data[2] ?? 'CC'));
                        $num_doc = trim($data[3] ?? '');
                        $genero = strtoupper(trim($data[4] ?? 'O'));
                        $telefono = trim($data[5] ?? '');
                        $ciudad = trim($data[6] ?? '');

                        // Si está vacío el nombre o documento, saltar
                        if (empty($nombre) || empty($email) || empty($num_doc)) {
                            $warnings[] = "Fila $rowNum: Nombre, Email o Documento vacío. Fila omitida.";
                            continue;
                        }

                        // Limpiar género
                        if (!in_array($genero, ['M', 'F', 'O'])) {
                            $genero = 'O';
                        }
                        // Limpiar tipo doc
                        if (!in_array($tipo_doc, ['CC', 'TI', 'CE', 'PEP', 'PA'])) {
                            $tipo_doc = 'CC';
                        }

                        // Verificar si el email ya existe en la tabla usuarios
                        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $warnings[] = "Fila $rowNum: El correo electrónico '$email' ya está registrado. Omitido.";
                            continue;
                        }

                        // Verificar si el documento ya existe en la tabla aprendices
                        $stmt = $db->prepare("SELECT id FROM aprendices WHERE numero_documento = ?");
                        $stmt->execute([$num_doc]);
                        if ($stmt->fetch()) {
                            $warnings[] = "Fila $rowNum: El documento '$num_doc' ya está matriculado. Omitido.";
                            continue;
                        }

                        // 1. Crear el usuario
                        $avatar_color = $colors[array_rand($colors)];
                        $stmt = $db->prepare("
                            INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado)
                            VALUES (?, ?, ?, 'aprendiz', ?, 'activo')
                        ");
                        $stmt->execute([$nombre, $email, $password_hash, $avatar_color]);
                        $usuario_id = (int)$db->lastInsertId();

                        // 2. Crear aprendiz
                        $stmt = $db->prepare("
                            INSERT INTO aprendices (usuario_id, ficha_id, numero_documento, tipo_documento, genero, estado, telefono, ciudad)
                            VALUES (?, ?, ?, ?, ?, 'matriculado', ?, ?)
                        ");
                        $stmt->execute([$usuario_id, $ficha_id, $num_doc, $tipo_doc, $genero, $telefono, $ciudad]);
                        $new_ap_id = (int)$db->lastInsertId();

                        // 3. Inicializar evaluaciones como 'pendiente'
                        inicializarEvaluacionesAprendiz($db, $new_ap_id, $ficha_id);

                        // 4. Incrementar contador en la ficha
                        $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$ficha_id]);

                        $successCount++;
                    }

                    fclose($handle);

                    if ($successCount > 0) {
                        $db->commit();
                        $successMessage = "Se matricularon exitosamente $successCount aprendices y se inicializaron sus evaluaciones.";
                        if (!empty($warnings)) {
                            $successMessage .= "<br><strong>Nota:</strong> Se omitieron algunas filas:<br>" . implode("<br>", array_slice($warnings, 0, 10));
                            if (count($warnings) > 10) {
                                $successMessage .= "<br>... y " . (count($warnings) - 10) . " advertencias más.";
                            }
                        }
                    } else {
                        $db->rollBack();
                        $errors[] = "No se matriculó ningún aprendiz. Revise los errores:<br>" . implode("<br>", $warnings);
                    }

                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $errors[] = 'Error durante la carga masiva: ' . $e->getMessage();
                }
            }
        }
    }
}

// Procesar edición de Matrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_matricula') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden modificar matrículas.';
    } else {
        $aprendiz_id = (int)($_POST['aprendiz_id'] ?? 0);
        $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');
        $email = trim($_POST['email'] ?? '');
        $tipo_documento = $_POST['tipo_documento'] ?? 'CC';
        $numero_documento = trim($_POST['numero_documento'] ?? '');
        $ficha_id = (int)($_POST['ficha_id'] ?? 0);
        $estado = $_POST['estado'] ?? 'matriculado';
        $genero = $_POST['genero'] ?? 'O';
        $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
        $telefono = trim($_POST['telefono'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');

        if ($aprendiz_id <= 0) $errors[] = 'Aprendiz no válido.';
        if (empty($nombre)) $errors[] = 'El nombre completo es obligatorio.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (empty($numero_documento)) $errors[] = 'El número de documento es obligatorio.';
        if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha de formación.';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Obtener datos actuales del aprendiz y su usuario_id
                $stmt = $db->prepare("SELECT ficha_id, usuario_id FROM aprendices WHERE id = ?");
                $stmt->execute([$aprendiz_id]);
                $old_ap = $stmt->fetch();

                if ($old_ap) {
                    $old_ficha_id = (int)$old_ap['ficha_id'];
                    $usuario_id = (int)$old_ap['usuario_id'];

                    // Verificar email duplicado (excluyendo el usuario actual)
                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $usuario_id]);
                    if ($stmt->fetch()) {
                        throw new Exception('El correo electrónico ya se encuentra registrado por otro usuario.');
                    }

                    // Verificar documento duplicado (excluyendo el aprendiz actual)
                    $stmt = $db->prepare("SELECT id FROM aprendices WHERE numero_documento = ? AND id != ?");
                    $stmt->execute([$numero_documento, $aprendiz_id]);
                    if ($stmt->fetch()) {
                        throw new Exception('El número de documento ya se encuentra registrado por otro aprendiz.');
                    }

                    // 1. Actualizar tabla usuarios
                    $stmt = $db->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, email = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $email, $usuario_id]);

                    // 2. Actualizar tabla aprendices
                    $stmt = $db->prepare("
                        UPDATE aprendices 
                        SET ficha_id = ?, estado = ?, tipo_documento = ?, numero_documento = ?, 
                            genero = ?, fecha_nacimiento = ?, telefono = ?, ciudad = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $ficha_id, $estado, $tipo_documento, $numero_documento,
                        $genero, $fecha_nacimiento, $telefono, $ciudad, $aprendiz_id
                    ]);

                    // 3. Si cambió de ficha, actualizar los contadores
                    if ($old_ficha_id !== $ficha_id) {
                        $db->prepare("UPDATE fichas SET cantidad_aprendices = GREATEST(0, cantidad_aprendices - 1) WHERE id = ?")->execute([$old_ficha_id]);
                        $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$ficha_id]);
                    }

                    // Registrar log
                    $logStmt = $db->prepare("
                        INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                        VALUES (?, 'Editar', 'Matriculas', 'aprendices', ?, ?)
                    ");
                    $logStmt->execute([(int)getCurrentUser()['id'], $aprendiz_id, "Actualizó información de matrícula y datos personales del aprendiz: $nombre"]);

                    $db->commit();
                    $successMessage = 'Matrícula y datos del aprendiz actualizados exitosamente.';
                } else {
                    $db->rollBack();
                    $errors[] = 'No se encontró el registro del aprendiz.';
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Error al actualizar aprendiz: ' . $e->getMessage();
            }
        }
    }
}


// Procesar eliminación de Matrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_matricula') {
    if (!hasRole(ROL_COORDINADOR)) {
        $errors[] = 'Solo los coordinadores pueden eliminar matrículas.';
    } else {
        $aprendiz_id = (int)($_POST['aprendiz_id'] ?? 0);

        if ($aprendiz_id <= 0) $errors[] = 'Aprendiz no válido.';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Obtener datos del aprendiz antes de eliminar
                $stmt = $db->prepare("SELECT ficha_id, usuario_id FROM aprendices WHERE id = ?");
                $stmt->execute([$aprendiz_id]);
                $ap = $stmt->fetch();

                if ($ap) {
                    $ficha_id = (int)$ap['ficha_id'];
                    $usuario_id = (int)$ap['usuario_id'];

                    // Eliminar de aprendices
                    $db->prepare("DELETE FROM aprendices WHERE id = ?")->execute([$aprendiz_id]);

                    // Desactivar el usuario o eliminarlo (desactivar es más seguro para evitar romper logs)
                    $db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?")->execute([$usuario_id]);

                    // Decrementar el contador en la ficha
                    $db->prepare("UPDATE fichas SET cantidad_aprendices = GREATEST(0, cantidad_aprendices - 1) WHERE id = ?")->execute([$ficha_id]);

                    // Registrar log
                    $logStmt = $db->prepare("
                        INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                        VALUES (?, 'Eliminar', 'Matriculas', 'aprendices', ?, ?)
                    ");
                    $logStmt->execute([(int)getCurrentUser()['id'], $aprendiz_id, "Eliminó la matrícula del aprendiz id $aprendiz_id y desactivó su usuario"]);

                    $db->commit();
                    $successMessage = 'Matrícula eliminada exitosamente.';
                } else {
                    $db->rollBack();
                    $errors[] = 'No se encontró el registro del aprendiz.';
                }
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al eliminar matrícula: ' . $e->getMessage();
            }
        }
    }
}


// Obtener fichas para formulario y filtros
$fichas = [];
try {
    $fichas = $db->query("
        SELECT f.id, f.numero_ficha, p.nombre as programa 
        FROM fichas f
        JOIN programas p ON f.programa_id = p.id 
        ORDER BY f.numero_ficha
    ")->fetchAll();
} catch (Exception $e) {
    $errors[] = 'Error al cargar fichas.';
}

// Obtener filtros de búsqueda
$search = trim($_GET['search'] ?? '');
$filter_ficha = (int)($_GET['ficha_id'] ?? 0);
$filter_estado = $_GET['estado'] ?? '';

// Construir consulta de aprendices
$sql = "
    SELECT a.*, u.nombre, u.email, u.avatar_color, f.numero_ficha, p.nombre as programa_nombre
    FROM aprendices a
    JOIN usuarios u ON a.usuario_id = u.id
    LEFT JOIN fichas f ON a.ficha_id = f.id
    LEFT JOIN programas p ON f.programa_id = p.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nombre LIKE ? OR a.numero_documento LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_ficha > 0) {
    $sql .= " AND a.ficha_id = ?";
    $params[] = $filter_ficha;
}
if (!empty($filter_estado)) {
    $sql .= " AND a.estado = ?";
    $params[] = $filter_estado;
}

$sql .= " ORDER BY u.nombre";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $aprendices = $stmt->fetchAll();
} catch (Exception $e) {
    $aprendices = [];
    $errors[] = 'Error al cargar los aprendices.';
}

$estados_label = [
    'matriculado' => ['Matriculado', 'success'],
    'suspendido' => ['Suspendido', 'warning'],
    'desertado' => ['Desertado', 'danger'],
    'egresado' => ['Egresado', 'info']
];

$pageTitle = 'Gestión de Matrículas · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Matrículas de Aprendices</h1>
    <p class="text-muted mb-0">Gestiona las admisiones de estudiantes y su asignación a fichas técnicas.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR)): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-soft text-primary" data-bs-toggle="modal" data-bs-target="#modalCargarCSV">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Carga Masiva (CSV)
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMatricular">
      <i class="bi bi-person-plus me-1"></i> Matricular Aprendiz
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

<!-- Barra de filtros -->
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar aprendiz</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0" style="border-color:rgba(255,255,255,0.15)"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Nombre, correo o documento..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select" onchange="this.form.submit()"
                data-picker
                data-picker-label="Filtrar por ficha"
                data-picker-placeholder="Buscar ficha por número o programa...">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>
                    data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="matriculado" <?= $filter_estado === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
          <option value="suspendido" <?= $filter_estado === 'suspendido' ? 'selected' : '' ?>>Suspendido</option>
          <option value="desertado" <?= $filter_estado === 'desertado' ? 'selected' : '' ?>>Desertado</option>
          <option value="egresado" <?= $filter_estado === 'egresado' ? 'selected' : '' ?>>Egresado</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de matriculados -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Documento</th>
            <th>Aprendiz</th>
            <th>Ficha Asignada</th>
            <th>Teléfono / Ciudad</th>
            <th>Estado</th>
            <th class="pe-4 text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendices as $ap): ?>
          <tr>
            <td class="ps-4 font-monospace fw-bold text-muted">
              <span class="badge bg-light text-dark border"><?= htmlspecialchars($ap['tipo_documento']) ?></span> 
              <?= htmlspecialchars($ap['numero_documento']) ?>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar" style="width:36px; height:36px; font-size:0.9rem; background:<?= htmlspecialchars($ap['avatar_color']) ?>">
                  <?= strtoupper(substr($ap['nombre'], 0, 2)) ?>
                </div>
                <div>
                  <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($ap['nombre']) ?></h6>
                  <small class="text-muted"><?= htmlspecialchars($ap['email']) ?></small>
                </div>
              </div>
            </td>
            <td>
              <?php if ($ap['numero_ficha']): ?>
                <div class="fw-bold text-dark">Ficha #<?= htmlspecialchars($ap['numero_ficha']) ?></div>
                <small class="text-muted text-wrap d-inline-block" style="max-width:200px;"><?= htmlspecialchars($ap['programa_nombre']) ?></small>
              <?php else: ?>
                <span class="text-danger small"><i class="bi bi-x-circle me-1"></i>Sin Ficha</span>
              <?php endif; ?>
            </td>
            <td>
              <div><?= htmlspecialchars($ap['telefono'] ?: 'N/A') ?></div>
              <small class="text-muted"><?= htmlspecialchars($ap['ciudad'] ?: 'No asignado') ?></small>
            </td>
            <td>
              <span class="badge-soft <?= $estados_label[$ap['estado']][1] ?? 'secondary' ?>">
                <?= $estados_label[$ap['estado']][0] ?? 'N/A' ?>
              </span>
            </td>
            <td class="pe-4 text-end">
              <?php if (hasRole(ROL_COORDINADOR)): ?>
                <button class="btn btn-sm btn-soft me-1" 
                        onclick="mostrarEditarMatricula(this)"
                        data-id="<?= $ap['id'] ?>"
                        data-nombre="<?= htmlspecialchars($ap['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                        data-email="<?= htmlspecialchars($ap['email'], ENT_QUOTES, 'UTF-8') ?>"
                        data-tipo-documento="<?= htmlspecialchars($ap['tipo_documento'], ENT_QUOTES, 'UTF-8') ?>"
                        data-numero-documento="<?= htmlspecialchars($ap['numero_documento'], ENT_QUOTES, 'UTF-8') ?>"
                        data-genero="<?= htmlspecialchars($ap['genero'], ENT_QUOTES, 'UTF-8') ?>"
                        data-fecha-nacimiento="<?= htmlspecialchars($ap['fecha_nacimiento'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                        data-telefono="<?= htmlspecialchars($ap['telefono'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                        data-ciudad="<?= htmlspecialchars($ap['ciudad'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                        data-ficha-id="<?= $ap['ficha_id'] ?>"
                        data-estado="<?= htmlspecialchars($ap['estado'], ENT_QUOTES, 'UTF-8') ?>"
                        data-bs-toggle="modal" 
                        data-bs-target="#modalEditarMatricula"
                        title="Gestionar Matrícula">
                  <i class="bi bi-pencil"></i>
                </button>


                 <button class="btn btn-sm btn-soft text-danger"
                        onclick="eliminarMatricula(<?= $ap['id'] ?>, <?= htmlspecialchars(json_encode($ap['nombre']), ENT_QUOTES, 'UTF-8') ?>)"
                        title="Eliminar Matrícula">
                  <i class="bi bi-trash"></i>
                </button>

              <?php else: ?>
                <span class="text-muted small">No acciones</span>
              <?php endif; ?>
            </td>

          </tr>
          <?php endforeach; ?>
          <?php if (empty($aprendices)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-people d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
              No se encontraron aprendices registrados.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Registrar Matrícula -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalMatricular" tabindex="-1" aria-labelledby="modalMatricularLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalMatricularLabel"><i class="bi bi-person-plus text-primary me-2"></i>Nueva Matrícula</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="matricular">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Nombre Completo</label>
              <input type="text" name="nombre" class="form-control" placeholder="Ej. Carlos Mario Restrepo" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Correo Electrónico Institucional</label>
              <input type="email" name="email" class="form-control" placeholder="Ej. carlos@soy.sena.edu.co" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Tipo Documento</label>
              <select name="tipo_documento" class="form-select">
                <option value="CC">Cédula de Ciudadanía (CC)</option>
                <option value="TI">Tarjeta de Identidad (TI)</option>
                <option value="CE">Cédula de Extranjería (CE)</option>
                <option value="PEP">PEP</option>
                <option value="PA">Pasaporte</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Número Documento</label>
              <input type="text" name="numero_documento" class="form-control" placeholder="Ej. 1045612378" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Ficha de Destino</label>
              <select name="ficha_id" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar ficha"
                      data-picker-placeholder="Número de ficha o nombre del programa...">
                <option value="" disabled selected>Seleccionar Ficha...</option>
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= $f['id'] ?>"
                          data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                    Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Género</label>
              <select name="genero" class="form-select">
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
                <option value="O">Otro</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Fecha Nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Teléfono</label>
              <input type="text" name="telefono" class="form-control" placeholder="Ej. 3127894512">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Ciudad</label>
              <input type="text" name="ciudad" class="form-control" placeholder="Ej. Medellín">
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Matricular Aprendiz</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Carga Masiva CSV -->
<div class="modal fade" id="modalCargarCSV" tabindex="-1" aria-labelledby="modalCargarCSVLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalCargarCSVLabel"><i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>Carga Masiva de Aprendices</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="cargar_csv">
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Sube un archivo delimitado por comas (<strong>.csv</strong>) con la información de los aprendices. El sistema registrará a los usuarios e inicializará automáticamente todas sus evaluaciones para la ficha seleccionada.
          </p>
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Ficha de Destino</label>
            <select name="ficha_id" class="form-select" required>
              <option value="" disabled selected>Seleccionar Ficha...</option>
              <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id'] ?>">
                  Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Seleccionar Archivo CSV</label>
            <input type="file" name="file_csv" class="form-control" accept=".csv" required>
          </div>

          <div class="p-3 bg-light rounded-3 text-muted" style="font-size:0.8rem;">
            <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i> Formato de Columnas del CSV:</div>
            <code>nombre, email, tipo_documento, numero_documento, genero, telefono, ciudad</code>
            <div class="mt-2">
              * El tipo de documento debe ser uno de: <strong>CC, TI, CE, PEP, PA</strong>.<br>
              * La contraseña por defecto de los nuevos aprendices será <strong>Sena2026</strong>.
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Importar Aprendices</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- Modal Editar Matrícula -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalEditarMatricula" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Datos del Aprendiz</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="editar_matricula">
        <input type="hidden" name="aprendiz_id" id="edit_aprendiz_id">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Nombre Completo</label>
              <input type="text" name="nombre" id="edit_nombre" class="form-control" placeholder="Ej. Carlos Mario Restrepo" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Correo Electrónico Institucional</label>
              <input type="email" name="email" id="edit_email" class="form-control" placeholder="Ej. carlos@soy.sena.edu.co" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Tipo Documento</label>
              <select name="tipo_documento" id="edit_tipo_documento" class="form-select">
                <option value="CC">Cédula de Ciudadanía (CC)</option>
                <option value="TI">Tarjeta de Identidad (TI)</option>
                <option value="CE">Cédula de Extranjería (CE)</option>
                <option value="PEP">PEP</option>
                <option value="PA">Pasaporte</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Número Documento</label>
              <input type="text" name="numero_documento" id="edit_numero_documento" class="form-control" placeholder="Ej. 1045612378" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Ficha de Formación</label>
              <select name="ficha_id" id="edit_ficha_id" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar ficha"
                      data-picker-placeholder="Número de ficha o nombre del programa...">
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= $f['id'] ?>"
                          data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                    Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Estado de Matrícula</label>
              <select name="estado" id="edit_estado" class="form-select" required>
                <option value="matriculado">Matriculado</option>
                <option value="suspendido">Suspendido</option>
                <option value="desertado">Desertado</option>
                <option value="egresado">Egresado</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Género</label>
              <select name="genero" id="edit_genero" class="form-select">
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
                <option value="O">Otro</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Fecha Nacimiento</label>
              <input type="date" name="fecha_nacimiento" id="edit_fecha_nacimiento" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Teléfono</label>
              <input type="text" name="telefono" id="edit_telefono" class="form-control" placeholder="Ej. 3127894512">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Ciudad</label>
            <input type="text" name="ciudad" id="edit_ciudad" class="form-control" placeholder="Ej. Medellín">
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
<?php endif; ?>

<form id="formEliminarMatricula" method="POST" style="display:none;">
  <input type="hidden" name="action" value="eliminar_matricula">
  <input type="hidden" name="aprendiz_id" id="eliminar_aprendiz_id">
</form>

<script>
function eliminarMatricula(id, nombre) {
    if (confirm('¿Estás seguro de que deseas desvincular y eliminar la matrícula del aprendiz ' + nombre + '? Su usuario también será desactivado.')) {
        document.getElementById('eliminar_aprendiz_id').value = id;
        document.getElementById('formEliminarMatricula').submit();
    }
}

function mostrarEditarMatricula(button) {
    const elId = document.getElementById('edit_aprendiz_id');
    const elNombre = document.getElementById('edit_nombre');
    const elEmail = document.getElementById('edit_email');
    const elTipoDoc = document.getElementById('edit_tipo_documento');
    const elNumDoc = document.getElementById('edit_numero_documento');
    const elFicha = document.getElementById('edit_ficha_id');
    const elEstado = document.getElementById('edit_estado');
    const elGenero = document.getElementById('edit_genero');
    const elFechaNac = document.getElementById('edit_fecha_nacimiento');
    const elTelefono = document.getElementById('edit_telefono');
    const elCiudad = document.getElementById('edit_ciudad');

    if (elId) elId.value = button.getAttribute('data-id') || '';
    if (elNombre) elNombre.value = button.getAttribute('data-nombre') || '';
    if (elEmail) elEmail.value = button.getAttribute('data-email') || '';
    if (elTipoDoc) elTipoDoc.value = button.getAttribute('data-tipo-documento') || 'CC';
    if (elNumDoc) elNumDoc.value = button.getAttribute('data-numero-documento') || '';
    if (elFicha) elFicha.value = button.getAttribute('data-ficha-id') || '';
    if (elEstado) elEstado.value = button.getAttribute('data-estado') || 'matriculado';
    if (elGenero) elGenero.value = button.getAttribute('data-genero') || 'O';
    if (elFechaNac) elFechaNac.value = button.getAttribute('data-fecha-nacimiento') || '';
    if (elTelefono) elTelefono.value = button.getAttribute('data-telefono') || '';
    if (elCiudad) elCiudad.value = button.getAttribute('data-ciudad') || '';
}
</script>





