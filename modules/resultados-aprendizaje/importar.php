<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/XlsxParser.php';

use Core\Database;
use Core\XlsxParser;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$db = Database::getConnection();
$errors = [];
$successMessage = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['archivo_raps']) && $_FILES['archivo_raps']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['archivo_raps']['tmp_name'];
        $fileName = $_FILES['archivo_raps']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'xls') {
            $errors[] = 'El formato .xls no está soportado. Por favor, guarde su archivo como .xlsx o expórtelo como .csv.';
        } elseif (!in_array($fileExtension, ['csv', 'xlsx'])) {
            $errors[] = 'El archivo debe ser un archivo de Excel (.xlsx) o un archivo de texto separado por comas (.csv).';
        } else {
            $rows = [];
            if ($fileExtension === 'csv') {
                $handle = fopen($fileTmpPath, 'r');
                if ($handle !== false) {
                    $firstLine = fgets($handle);
                    $separator = (strpos($firstLine, ';') !== false) ? ';' : ',';
                    rewind($handle);

                    while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                } else {
                    $errors[] = 'No se pudo abrir el archivo CSV.';
                }
            } else {
                try {
                    $rows = XlsxParser::parse($fileTmpPath);
                } catch (Exception $e) {
                    $errors[] = 'Error al procesar el archivo Excel: ' . $e->getMessage();
                }
            }

            if (empty($errors)) {
                if (count($rows) <= 1) {
                    $errors[] = 'El archivo está vacío o solo contiene la cabecera.';
                } else {
                    // Ignorar cabecera
                    array_shift($rows);
                    
                    $linea = 2;
                    $rapsData = [];
                    
                    // Cargar competencias existentes para buscar por código de forma rápida
                    $competenciasMap = [];
                    try {
                        $comp_list = $db->query("SELECT id, codigo FROM competencias")->fetchAll();
                        foreach ($comp_list as $c) {
                            $competenciasMap[strtolower(trim($c['codigo']))] = (int)$c['id'];
                        }
                    } catch (Exception $e) {
                        $errors[] = 'Error al precargar códigos de competencias.';
                    }

                    foreach ($rows as $data) {
                        if (empty($data) || (empty($data[0]) && empty($data[1]) && empty($data[2]))) {
                            $linea++;
                            continue;
                        }

                        if (count($data) < 3) {
                            $errors[] = "Línea $linea: Faltan columnas. Se requiere: Código Competencia, Código RAP, Nombre RAP.";
                            $linea++;
                            continue;
                        }

                        $comp_code = strtolower(trim((string)($data[0] ?? '')));
                        $rap_code = trim((string)($data[1] ?? ''));
                        $rap_name = mb_strtoupper(trim((string)($data[2] ?? '')), 'UTF-8');

                        $rowErrors = [];
                        if (!isset($competenciasMap[$comp_code])) {
                            $rowErrors[] = "Línea $linea: El código de competencia '$comp_code' no existe en la base de datos.";
                        }
                        if (empty($rap_code)) {
                            $rowErrors[] = "Línea $linea: El código del RAP está vacío.";
                        }
                        if (empty($rap_name)) {
                            $rowErrors[] = "Línea $linea: La descripción/nombre del RAP está vacía.";
                        }

                        if (empty($rowErrors)) {
                            $rapsData[] = [
                                'competencia_id' => $competenciasMap[$comp_code],
                                'codigo' => $rap_code,
                                'denominacion' => $rap_name
                            ];
                        } else {
                            $errors = array_merge($errors, $rowErrors);
                        }
                        $linea++;
                    }

                    if (empty($errors)) {
                        if (count($rapsData) > 0) {
                            try {
                                $db->beginTransaction();
                                $stmt = $db->prepare("
                                    INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion)
                                    VALUES (?, ?, ?)
                                ");

                                $importedCount = 0;
                                foreach ($rapsData as $rap) {
                                    $stmt->execute([
                                        $rap['competencia_id'],
                                        $rap['codigo'],
                                        $rap['denominacion']
                                    ]);
                                    $importedCount++;
                                }

                                // Registrar log
                                $logStmt = $db->prepare("
                                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, descripcion)
                                    VALUES (?, 'Importar', 'RAPs', 'resultados_aprendizaje', ?)
                                ");
                                $logStmt->execute([(int)getCurrentUser()['id'], "Importó masivamente $importedCount Resultados de Aprendizaje (RAPs)"]);

                                $db->commit();
                                $successMessage = "Se han importado exitosamente $importedCount Resultados de Aprendizaje.";
                                $resultados = $rapsData;
                            } catch (Exception $e) {
                                $db->rollBack();
                                $errors[] = 'Error al insertar RAPs en la BD: ' . $e->getMessage();
                            }
                        } else {
                            $errors[] = 'El archivo no contiene filas válidas.';
                        }
                    }
                }
            }
        }
    } else {
        $errors[] = 'Por favor, seleccione un archivo válido.';
    }
}

$pageTitle = 'Importar RAPs · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="mb-4">
  <a href="index.php" class="btn btn-soft btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver al Listado</a>
  <h1 class="mb-1">Importación Masiva de RAPs</h1>
  <p class="text-muted mb-0">Registra de forma masiva los Resultados de Aprendizaje (RAP) usando plantillas Excel o archivos CSV.</p>
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
  <strong class="d-block mb-1">Se encontraron los siguientes errores en la validación:</strong>
  <ul class="mb-0 ps-3">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-upload text-success me-2"></i>Cargar Plantilla</h5>
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-4">
            <label class="form-label text-muted small fw-semibold">Selecciona el archivo (.xlsx o .csv)</label>
            <input type="file" name="archivo_raps" class="form-control" accept=".xlsx, .csv" required>
            <small class="text-muted d-block mt-1">El archivo no debe exceder los 10MB de tamaño.</small>
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-circle-fill me-1"></i>Procesar e Importar</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card glass-card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Instrucciones de Formato</h5>
        <p class="text-muted small">Para que la importación sea exitosa, el archivo debe tener exactamente las siguientes columnas en su cabecera:</p>
        
        <div class="table-responsive">
          <table class="table table-bordered table-sm small text-center mb-0">
            <thead class="table-light">
              <tr>
                <th>Columna A</th>
                <th>Columna B</th>
                <th>Columna C</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Código Competencia</strong></td>
                <td><strong>Código RAP</strong></td>
                <td><strong>Nombre RAP</strong></td>
              </tr>
              <tr class="text-muted">
                <td>220501096</td>
                <td>RAP 1</td>
                <td>IDENTIFICAR LOS REQUISITOS DE SOFTWARE DEL CLIENTE SEGÚN LA METODOLOGÍA.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul class="text-muted small ps-3 mt-3 mb-0">
          <li class="mb-1"><strong>Código Competencia:</strong> Debe coincidir exactamente con el código de una competencia activa en el sistema (ej: <code>220501096</code>).</li>
          <li class="mb-1"><strong>Código RAP:</strong> Identificador del RAP (ej: <code>RAP 1</code>, <code>RAP-02</code>).</li>
          <li class="mb-1"><strong>Nombre RAP:</strong> Se convertirá automáticamente a MAYÚSCULAS para mantener consistencia.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($resultados)): ?>
<div class="card glass-card border-0 shadow-sm mt-4">
  <div class="card-body">
    <h5 class="fw-bold text-success mb-3"><i class="bi bi-check-all me-1"></i>Resultados de Aprendizaje Importados en esta sesión:</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Competencia ID</th>
            <th>Código RAP</th>
            <th>Nombre RAP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resultados as $rap): ?>
            <tr>
              <td><?= htmlspecialchars((string)$rap['competencia_id']) ?></td>
              <td class="font-monospace fw-bold text-success"><?= htmlspecialchars($rap['codigo']) ?></td>
              <td><?= htmlspecialchars($rap['denominacion']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
