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
$user_rol = getCurrentRole();
$user_id = (int)getCurrentUser()['id'];

// Procesar exportaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $type = $_POST['export'];
    $format = $_POST['format'] ?? 'csv';
    
    try {
        $data = [];
        $headers = [];
        $filename = '';

        switch ($type) {
            case 'evaluaciones_ficha':
                $ficha_id = (int)($_POST['ficha_id'] ?? 0);
                
                // Validación de scoping para instructores
                if ($user_rol === ROL_INSTRUCTOR) {
                    $stmtFichaCheck = $db->prepare("SELECT COUNT(*) FROM fichas WHERE id = ? AND instructor_id = ?");
                    $stmtFichaCheck->execute([$ficha_id, $user_id]);
                    if ((int)$stmtFichaCheck->fetchColumn() === 0) {
                        throw new Exception("No tiene permisos para descargar los reportes de esta ficha.");
                    }
                }

                $headers = ['Aprendiz', 'Documento', 'RA Código', 'RA Denominación', 'Competencia', 'Concepto', 'Fecha Evaluación', 'Instructor'];
                $sql = "
                    SELECT u.nombre as aprendiz, ap.numero_documento, ra.codigo as ra_codigo, ra.denominacion,
                           c.nombre as competencia, e.concepto, e.fecha_evaluacion, ui.nombre as instructor
                    FROM evaluaciones e
                    JOIN aprendices ap ON e.aprendiz_id = ap.id
                    JOIN usuarios u ON ap.usuario_id = u.id
                    JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                    JOIN competencias c ON ra.competencia_id = c.id
                    JOIN usuarios ui ON e.instructor_id = ui.id
                    WHERE e.ficha_id = ?
                    ORDER BY u.nombre, ra.codigo
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute([$ficha_id]);
                $data = $stmt->fetchAll(PDO::FETCH_NUM);
                $filename = "evaluaciones_ficha_{$ficha_id}_" . date('Ymd');
                break;

            case 'cumplimiento_instructor':
                $headers = ['Instructor', 'Ficha', 'Programa', 'Total RAs', 'Aprobados (A)', 'No Aprobados (D)', 'Pendientes', '% Cumplimiento'];
                $sql = "
                    SELECT ui.nombre as instructor, f.numero_ficha, p.nombre as programa,
                           COUNT(e.id) as total_ra,
                           SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                           SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                           SUM(CASE WHEN e.concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                           ROUND(SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(e.id), 0), 1) as pct
                    FROM evaluaciones e
                    JOIN fichas f ON e.ficha_id = f.id
                    JOIN programas p ON f.programa_id = p.id
                    JOIN usuarios ui ON f.instructor_id = ui.id
                ";
                $params = [];
                if ($user_rol === ROL_INSTRUCTOR) {
                    $sql .= " WHERE f.instructor_id = ?";
                    $params[] = $user_id;
                }
                $sql .= " GROUP BY ui.id, f.id ORDER BY ui.nombre, f.numero_ficha";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_NUM);
                $filename = "cumplimiento_instructor_" . date('Ymd');
                break;

            case 'cumplimiento_competencia':
                $headers = ['Programa', 'Competencia', 'Código Comp.', 'Total RAs Evaluados', 'Aprobados (A)', 'No Aprobados (D)', '% Aprobación'];
                $sql = "
                    SELECT p.nombre as programa, c.nombre as competencia, c.codigo,
                           COUNT(e.id) as total,
                           SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                           SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                           ROUND(SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(e.id), 0), 1) as pct
                    FROM evaluaciones e
                    JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                    JOIN competencias c ON ra.competencia_id = c.id
                    JOIN programas p ON c.programa_id = p.id
                    JOIN fichas f ON e.ficha_id = f.id
                    WHERE e.concepto != 'pendiente'
                ";
                $params = [];
                if ($user_rol === ROL_INSTRUCTOR) {
                    $sql .= " AND f.instructor_id = ?";
                    $params[] = $user_id;
                }
                $sql .= " GROUP BY c.id ORDER BY p.nombre, c.codigo";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_NUM);
                $filename = "cumplimiento_competencia_" . date('Ymd');
                break;

            case 'historial_cambios':
                $headers = ['Evaluación ID', 'Aprendiz', 'RA Código', 'Concepto Anterior', 'Concepto Nuevo', 'Motivo', 'Modificado Por', 'Fecha Cambio'];
                $sql = "
                    SELECT he.evaluacion_id, u_ap.nombre as aprendiz, ra.codigo,
                           he.concepto_anterior, he.concepto_nuevo, he.motivo,
                           u_mod.nombre as modificado_por, he.fecha_cambio
                    FROM historial_evaluaciones he
                    JOIN evaluaciones e ON he.evaluacion_id = e.id
                    JOIN fichas f ON e.ficha_id = f.id
                    JOIN aprendices ap ON e.aprendiz_id = ap.id
                    JOIN usuarios u_ap ON ap.usuario_id = u_ap.id
                    JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                    JOIN usuarios u_mod ON he.usuario_id = u_mod.id
                ";
                $params = [];
                if ($user_rol === ROL_INSTRUCTOR) {
                    $sql .= " WHERE f.instructor_id = ?";
                    $params[] = $user_id;
                }
                $sql .= " ORDER BY he.fecha_cambio DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_NUM);
                $filename = "historial_evaluaciones_" . date('Ymd');
                break;
        }

        if ($format === 'excel') {
            // Export as Excel-compatible HTML table
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename . '.xls');
            
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
            echo '<head>';
            echo '<meta charset="UTF-8">';
            echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($type) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
            echo '<style>';
            echo 'table { border-collapse: collapse; font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; }';
            echo 'th { background-color: #00324D; color: white; font-weight: bold; border: 1px solid #cccccc; padding: 8px; text-align: center; }';
            echo 'td { border: 1px solid #e2e8f0; padding: 6px; }';
            echo '.alert-critico { background-color: #fce8e6; color: #a51d24; font-weight: bold; text-align: center; }';
            echo '.alert-riesgo { background-color: #fef7e0; color: #b06000; font-weight: bold; text-align: center; }';
            echo '.alert-dia { background-color: #e6f4ea; color: #137333; font-weight: bold; text-align: center; }';
            echo '.text-center { text-align: center; }';
            echo '.fw-bold { font-weight: bold; }';
            echo '</style>';
            echo '</head>';
            echo '<body>';
            echo '<h2 style="color: #00324D;">REPORTE: ' . htmlspecialchars(str_replace('_', ' ', strtoupper($type))) . '</h2>';
            echo '<table>';
            echo '<thead>';
            echo '<tr>';
            foreach ($headers as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                foreach ($row as $colIdx => $cell) {
                    $style = '';
                    $class = '';
                    
                    // Formatear celdas basadas en el tipo de reporte y columna
                    if ($type === 'evaluaciones_ficha' && $colIdx === 5) {
                        if ($cell === 'A') {
                            $class = ' class="alert-dia"';
                        } elseif ($cell === 'D') {
                            $class = ' class="alert-critico"';
                        } else {
                            $class = ' class="alert-riesgo"';
                        }
                    } elseif (($type === 'cumplimiento_instructor' && $colIdx === 7) || ($type === 'cumplimiento_competencia' && $colIdx === 6)) {
                        $val = (float)$cell;
                        if ($val >= 80) {
                            $class = ' class="alert-dia"';
                        } elseif ($val >= 60) {
                            $class = ' class="alert-riesgo"';
                        } else {
                            $class = ' class="alert-critico"';
                        }
                    } elseif ($type === 'historial_cambios' && ($colIdx === 3 || $colIdx === 4)) {
                        if ($cell === 'A') {
                            $class = ' class="alert-dia"';
                        } elseif ($cell === 'D') {
                            $class = ' class="alert-critico"';
                        } else {
                            $class = ' class="alert-riesgo"';
                        }
                    }
                    
                    if (is_numeric($cell) && $class === '') {
                        $style = ' style="text-align: center;"';
                    }
                    
                    echo "<td{$class}{$style}>" . htmlspecialchars((string)($cell ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table></body></html>';
            exit;
        } else {
            // CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel
            fwrite($output, "sep=;\n"); // Directiva separador
            fputcsv($output, $headers, ';');
            foreach ($data as $row) fputcsv($output, $row, ';');
            fclose($output);
            exit;
        }
    } catch (Exception $e) {
        $errors[] = 'Error al exportar reporte: ' . $e->getMessage();
    }
}

// Cargar estadísticas
$stats = [];
try {
    if ($user_rol === ROL_INSTRUCTOR) {
        // Evaluaciones de las fichas del instructor
        $stmtStats = $db->prepare("
            SELECT 
                COUNT(*) as total_evaluaciones,
                SUM(CASE WHEN e.concepto = 'A' THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN e.concepto = 'D' THEN 1 ELSE 0 END) as reprobados,
                SUM(CASE WHEN e.concepto = 'pendiente' THEN 1 ELSE 0 END) as pendientes
            FROM evaluaciones e
            JOIN fichas f ON e.ficha_id = f.id
            WHERE f.instructor_id = ?
        ");
        $stmtStats->execute([$user_id]);
        $rowStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        $stats['total_evaluaciones'] = (int)($rowStats['total_evaluaciones'] ?? 0);
        $stats['aprobados']          = (int)($rowStats['aprobados'] ?? 0);
        $stats['reprobados']         = (int)($rowStats['reprobados'] ?? 0);
        $stats['pendientes']         = (int)($rowStats['pendientes'] ?? 0);
        
        $stmtFichas = $db->prepare("SELECT COUNT(*) FROM fichas WHERE instructor_id = ?");
        $stmtFichas->execute([$user_id]);
        $stats['total_fichas'] = (int)$stmtFichas->fetchColumn();
        
        // Historial de cambios de las fichas del instructor
        $stmtHist = $db->prepare("
            SELECT COUNT(*) 
            FROM historial_evaluaciones he
            JOIN evaluaciones e ON he.evaluacion_id = e.id
            JOIN fichas f ON e.ficha_id = f.id
            WHERE f.instructor_id = ?
        ");
        $stmtHist->execute([$user_id]);
        $stats['cambios_historial'] = (int)$stmtHist->fetchColumn();
    } else {
        // Coordinador: Estadísticas globales
        $stats['total_evaluaciones'] = $db->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
        $stats['aprobados'] = $db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto = 'A'")->fetchColumn();
        $stats['reprobados'] = $db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto = 'D'")->fetchColumn();
        $stats['pendientes'] = $db->query("SELECT COUNT(*) FROM evaluaciones WHERE concepto = 'pendiente'")->fetchColumn();
        $stats['total_fichas'] = $db->query("SELECT COUNT(*) FROM fichas")->fetchColumn();
        $stats['cambios_historial'] = $db->query("SELECT COUNT(*) FROM historial_evaluaciones")->fetchColumn();
    }
} catch (Exception $e) {
    $stats = array_fill_keys(['total_evaluaciones','aprobados','reprobados','pendientes','total_fichas','cambios_historial'], 0);
}

// Fichas para selector
$fichas = [];
try {
    if ($user_rol === ROL_INSTRUCTOR) {
        $stmtF = $db->prepare("SELECT f.id, f.numero_ficha, p.nombre as programa FROM fichas f JOIN programas p ON f.programa_id = p.id WHERE f.instructor_id = ? ORDER BY f.numero_ficha");
        $stmtF->execute([$user_id]);
        $fichas = $stmtF->fetchAll();
    } else {
        $fichas = $db->query("SELECT f.id, f.numero_ficha, p.nombre as programa FROM fichas f JOIN programas p ON f.programa_id = p.id ORDER BY f.numero_ficha")->fetchAll();
    }
} catch (Exception $e) {}

$pageTitle = 'Centro de Reportes · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="mb-4">
  <h1 class="mb-1">Centro de Reportes</h1>
  <p class="text-muted mb-0">Genera reportes de cumplimiento por instructor, ficha y competencia. Exporta en CSV y Excel.</p>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div></div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid var(--sena-primary);">
      <div class="kpi-content"><div class="label">Evaluaciones</div><div class="value"><?= (int)$stats['total_evaluaciones'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #22c55e;">
      <div class="kpi-content"><div class="label">Aprobados</div><div class="value" style="color:#22c55e;"><?= (int)$stats['aprobados'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #ef4444;">
      <div class="kpi-content"><div class="label">No Aprobados</div><div class="value" style="color:#ef4444;"><?= (int)$stats['reprobados'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #eab308;">
      <div class="kpi-content"><div class="label">Pendientes</div><div class="value" style="color:#eab308;"><?= (int)$stats['pendientes'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #3b82f6;">
      <div class="kpi-content"><div class="label">Fichas</div><div class="value" style="color:#3b82f6;"><?= (int)$stats['total_fichas'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="kpi text-center" style="border-top: 3px solid #8b5cf6;">
      <div class="kpi-content"><div class="label">Cambios</div><div class="value" style="color:#8b5cf6;"><?= (int)$stats['cambios_historial'] ?></div></div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Reporte 1: Evaluaciones por Ficha -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid var(--sena-primary); border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-folder2-open text-primary" style="font-size: 2.5rem;"></i></div>
        <h5 class="fw-bold text-dark">Evaluaciones por Ficha</h5>
        <p class="text-muted small">Detalle de todos los juicios evaluativos (A/D) para cada aprendiz de una ficha específica.</p>
        <form method="POST">
          <input type="hidden" name="export" value="evaluaciones_ficha">
          <div class="mb-3">
            <select name="ficha_id" class="form-select form-select-sm" required
                    data-picker
                    data-picker-label="Seleccionar ficha"
                    data-picker-placeholder="Número de ficha o programa...">
              <option value="">Seleccionar ficha...</option>
              <?php foreach ($fichas as $f): ?>
              <option value="<?= $f['id'] ?>"
                      data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 2: Cumplimiento por Instructor -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid #3b82f6; border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-person-workspace" style="font-size: 2.5rem; color: #3b82f6;"></i></div>
        <h5 class="fw-bold text-dark">Cumplimiento por Instructor</h5>
        <p class="text-muted small">Cantidad de RAs evaluados vs faltantes agrupados por instructor y ficha asignada.</p>
        <form method="POST">
          <input type="hidden" name="export" value="cumplimiento_instructor">
          <div class="d-flex gap-2 mt-4">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 3: Cumplimiento por Competencia -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid #22c55e; border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-diagram-3" style="font-size: 2.5rem; color: #22c55e;"></i></div>
        <h5 class="fw-bold text-dark">Cumplimiento por Competencia</h5>
        <p class="text-muted small">Porcentaje de aprobación por cada competencia y programa formativo a nivel institucional.</p>
        <form method="POST">
          <input type="hidden" name="export" value="cumplimiento_competencia">
          <div class="d-flex gap-2 mt-4">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 4: Historial / Trazabilidad -->
  <div class="col-md-6">
    <div class="card glass-card h-100 border-0 shadow-sm" style="border-top: 4px solid #8b5cf6; border-radius: 12px;">
      <div class="card-body p-4">
        <div class="mb-3"><i class="bi bi-clock-history" style="font-size: 2.5rem; color: #8b5cf6;"></i></div>
        <h5 class="fw-bold text-dark">Historial de Cambios (Trazabilidad)</h5>
        <p class="text-muted small">Registro de todos los cambios de concepto evaluativo con fecha, responsable y motivo (RNF02).</p>
        <form method="POST">
          <input type="hidden" name="export" value="historial_cambios">
          <div class="d-flex gap-2 mt-4">
            <button type="submit" name="format" value="csv" class="btn btn-primary flex-grow-1"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button type="submit" name="format" value="excel" class="btn btn-success flex-grow-1"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Botón imprimir para PDF -->
<div class="card glass-card border-0 mt-4 p-4 text-center" style="border-radius: 12px;">
  <h5 class="fw-bold mb-2"><i class="bi bi-printer me-2"></i>Exportar a PDF</h5>
  <p class="text-muted small mb-3">Utiliza la función de impresión del navegador (Ctrl+P) y selecciona "Guardar como PDF" para generar reportes en formato PDF directamente desde cualquier vista del sistema.</p>
  <button onclick="window.print()" class="btn btn-outline-dark px-5"><i class="bi bi-file-earmark-pdf me-2"></i>Imprimir / Guardar como PDF</button>
</div>
