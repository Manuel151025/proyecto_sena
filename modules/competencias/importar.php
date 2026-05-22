<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/XlsxParser.php';

use Core\Database;
use Core\XlsxParser;

requireRole(ROL_COORDINADOR);

$db = Database::getConnection();
$errors = [];
$successMessage = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['archivo_competencias']) && $_FILES['archivo_competencias']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['archivo_competencias']['tmp_name'];
        $fileName = $_FILES['archivo_competencias']['name'];
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
                    $competenciasData = [];
                    
                    // Cargar programas existentes para buscar por código de forma rápida
                    $programasMap = [];
                    try {
                        $p_list = $db->query("SELECT id, codigo FROM programas")->fetchAll();
                        foreach ($p_list as $p) {
                            $programasMap[strtolower(trim($p['codigo']))] = (int)$p['id'];
                        }
                    } catch (Exception $e) {
                        $errors[] = 'Error al precargar códigos de programas.';
                    }

                    foreach ($rows as $data) {
                        if (empty($data) || (empty($data[0]) && empty($data[1]) && empty($data[2]))) {
                            $linea++;
                            continue;
                        }

                        if (count($data) < 4) {
                            $errors[] = "Línea $linea: Faltan columnas. Se requiere: Código Programa, Código Competencia, Nombre Competencia, Horas.";
                            $linea++;
                            continue;
                        }

                        $prog_code = strtolower(trim((string)($data[0] ?? '')));
                        $comp_code = trim((string)($data[1] ?? ''));
                        $comp_name = mb_strtoupper(trim((string)($data[2] ?? '')), 'UTF-8');
                        $horas = (int)($data[3] ?? 0);
                        $descripcion = trim((string)($data[4] ?? ''));

                        $rowErrors = [];
                        if (!isset($programasMap[$prog_code])) {
                            $rowErrors[] = "Línea $linea: El código de programa '$prog_code' no existe en la base de datos.";
                        }
                        if (empty($comp_code)) {
                            $rowErrors[] = "Línea $linea: El código de competencia está vacío.";
                        }
                        if (empty($comp_name)) {
                            $rowErrors[] = "Línea $linea: El nombre de la competencia está vacío.";
                        }
                        if ($horas <= 0) {
                            $rowErrors[] = "Línea $linea: Las horas deben ser un número positivo mayor que cero.";
                        }

                        if (empty($rowErrors)) {
                            $competenciasData[] = [
                                'programa_id' => $programasMap[$prog_code],
                                'codigo' => $comp_code,
                                'nombre' => $comp_name,
                                'horas' => $horas,
                                'descripcion' => $descripcion
                            ];
                        } else {
                            $errors = array_merge($errors, $rowErrors);
                        }
                        $linea++;
                    }

                    if (empty($errors)) {
                        if (count($competenciasData) > 0) {
                            try {
                                $db->beginTransaction();
                                $stmt = $db->prepare("
                                    INSERT INTO competencias (programa_id, codigo, nombre, horas, descripcion, estado)
                                    VALUES (?, ?, ?, ?, ?, 'activo')
                                ");
                                
                                $importedCount = 0;
                                foreach ($competenciasData as $c) {
                                    $stmt->execute([
                                        $c['programa_id'],
                                        $c['codigo'],
                                        $c['nombre'],
                                        $c['horas'],
                                        $c['descripcion']
                                    ]);
                                    $importedCount++;
                                }

                                // Registrar log
                                $logStmt = $db->prepare("
                                    INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, descripcion)
                                    VALUES (?, 'Importar', 'Competencias', 'competencias', ?)
                                ");
                                $logStmt->execute([(int)getCurrentUser()['id'], "Importó masivamente $importedCount competencias académicas"]);

                                $db->commit();
                                $successMessage = "Se han importado exitosamente $importedCount competencias académicas.";
                                $resultados = $competenciasData;
                            } catch (Exception $e) {
                                $db->rollBack();
                                $errors[] = 'Error al insertar competencias en la BD: ' . $e->getMessage();
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

$pageTitle = 'Importar Competencias · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="mb-4">
  <a href="index.php" class="btn btn-soft btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i>Volver al Listado</a>
  <h1 class="mb-1">Importación Masiva de Competencias</h1>
  <p class="text-muted mb-0">Registra de forma masiva las competencias de formación usando plantillas Excel o archivos CSV.</p>
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
            <input type="file" name="archivo_competencias" class="form-control" accept=".xlsx, .csv" required>
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
                <th>Columna D</th>
                <th>Columna E</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Código Programa</strong></td>
                <td><strong>Código Competencia</strong></td>
                <td><strong>Nombre Competencia</strong></td>
                <td><strong>Horas</strong></td>
                <td><strong>Descripción</strong></td>
              </tr>
              <tr class="text-muted">
                <td>ADSO</td>
                <td>220501096</td>
                <td>DESARROLLAR LA ESTRUCTURA DEL SISTEMA</td>
                <td>240</td>
                <td>Diseño lógico y físico de BD...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul class="text-muted small ps-3 mt-3 mb-0">
          <li class="mb-1"><strong>Código Programa:</strong> Debe coincidir exactamente con el código de un programa activo en el sistema (ej: <code>ADSO</code>, <code>MM</code>).</li>
          <li class="mb-1"><strong>Código Competencia:</strong> Identificador único numérico o alfanumérico.</li>
          <li class="mb-1"><strong>Nombre Competencia:</strong> Se convertirá automáticamente a MAYÚSCULAS para mantener consistencia.</li>
          <li class="mb-1"><strong>Horas:</strong> Duración de la competencia, debe ser un número entero positivo.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($resultados)): ?>
<div class="card glass-card border-0 shadow-sm mt-4">
  <div class="card-body">
    <h5 class="fw-bold text-success mb-3"><i class="bi bi-check-all me-1"></i>Competencias Importadas en esta sesión:</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Programa ID</th>
            <th>Código</th>
            <th>Nombre Competencia</th>
            <th>Horas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resultados as $c): ?>
            <tr>
              <td><?= htmlspecialchars((string)$c['programa_id']) ?></td>
              <td class="font-monospace fw-bold"><?= htmlspecialchars($c['codigo']) ?></td>
              <td><?= htmlspecialchars($c['nombre']) ?></td>
              <td><?= htmlspecialchars((string)$c['horas']) ?> hs</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
