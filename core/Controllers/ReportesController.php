<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ReportesModel;
use PDO;
use Exception;

class ReportesController extends BaseController {
    private PDO $db;
    private ReportesModel $reportesModel;

    public function __construct(?PDO $db = null, ?ReportesModel $reportesModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->reportesModel = $reportesModel ?? new ReportesModel($this->db);
    }

    public function index(): void {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $errors = [];
        $user_rol = getCurrentRole();
        $user_id = (int)getCurrentUser()['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
            requireCsrf();
            $type = $_POST['export'];
            $format = $_POST['format'] ?? 'csv';
            
            try {
                $data = [];
                $headers = [];
                $filename = '';

                switch ($type) {
                    case 'evaluaciones_ficha':
                        $ficha_id = (int)($_POST['ficha_id'] ?? 0);
                        if ($user_rol === ROL_INSTRUCTOR) {
                            if (!$this->reportesModel->checkFichaInstructorAccess($ficha_id, $user_id)) {
                                throw new Exception("No tiene permisos para descargar los reportes de esta ficha.");
                            }
                        }
                        $headers = ['Aprendiz', 'Documento', 'RA Código', 'RA Denominación', 'Competencia', 'Concepto', 'Fecha Evaluación', 'Instructor'];
                        $data = $this->reportesModel->getReportEvaluacionesFicha($ficha_id, $user_id, $user_rol);
                        $filename = "evaluaciones_ficha_{$ficha_id}_" . date('Ymd');
                        break;
                    case 'cumplimiento_instructor':
                        $headers = ['Instructor Líder', 'Ficha', 'Programa', 'Total RAs', 'Aprobados (A)', 'No Aprobados (D)', 'Pendientes', '% Cumplimiento'];
                        $data = $this->reportesModel->getReportCumplimientoInstructor($user_id, $user_rol);
                        $filename = "cumplimiento_instructor_" . date('Ymd');
                        break;
                    case 'cumplimiento_competencia':
                        $headers = ['Programa', 'Competencia', 'Código Comp.', 'Total RAs Evaluados', 'Aprobados (A)', 'No Aprobados (D)', '% Aprobación'];
                        $data = $this->reportesModel->getReportCumplimientoCompetencia($user_id, $user_rol);
                        $filename = "cumplimiento_competencia_" . date('Ymd');
                        break;
                    case 'historial_cambios':
                        $headers = ['Evaluación ID', 'Aprendiz', 'RA Código', 'Concepto Anterior', 'Concepto Nuevo', 'Motivo', 'Modificado Por', 'Fecha Cambio'];
                        $data = $this->reportesModel->getReportHistorialCambios($user_id, $user_rol);
                        $filename = "historial_evaluaciones_" . date('Ymd');
                        break;
                }

                if ($format === 'excel') {
                    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                    header('Content-Disposition: attachment; filename=' . $filename . '.xls');
                    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
                    echo '<head><meta charset="UTF-8">';
                    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($type) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
                    echo '<style>';
                    echo 'table { border-collapse: collapse; font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; }';
                    echo 'th { background-color: #00324D; color: white; font-weight: bold; border: 1px solid #cccccc; padding: 8px; text-align: center; }';
                    echo 'td { border: 1px solid #e2e8f0; padding: 6px; }';
                    echo '.alert-critico { background-color: #fce8e6; color: #a51d24; font-weight: bold; text-align: center; }';
                    echo '.alert-riesgo { background-color: #fef7e0; color: #b06000; font-weight: bold; text-align: center; }';
                    echo '.alert-dia { background-color: #e6f4ea; color: #137333; font-weight: bold; text-align: center; }';
                    echo '</style></head><body>';
                    echo '<h2 style="color: #00324D;">REPORTE: ' . htmlspecialchars(str_replace('_', ' ', strtoupper($type))) . '</h2>';
                    echo '<table><thead><tr>';
                    foreach ($headers as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
                    echo '</tr></thead><tbody>';
                    foreach ($data as $row) {
                        echo '<tr>';
                        foreach ($row as $colIdx => $cell) {
                            $style = ''; $class = '';
                            if ($type === 'evaluaciones_ficha' && $colIdx === 5) {
                                if ($cell === 'A') $class = ' class="alert-dia"';
                                elseif ($cell === 'D') $class = ' class="alert-critico"';
                                else $class = ' class="alert-riesgo"';
                            } elseif (($type === 'cumplimiento_instructor' && $colIdx === 7) || ($type === 'cumplimiento_competencia' && $colIdx === 6)) {
                                $val = (float)$cell;
                                if ($val >= 80) $class = ' class="alert-dia"';
                                elseif ($val >= 60) $class = ' class="alert-riesgo"';
                                else $class = ' class="alert-critico"';
                            } elseif ($type === 'historial_cambios' && ($colIdx === 3 || $colIdx === 4)) {
                                if ($cell === 'A') $class = ' class="alert-dia"';
                                elseif ($cell === 'D') $class = ' class="alert-critico"';
                                else $class = ' class="alert-riesgo"';
                            }
                            if (is_numeric($cell) && $class === '') $style = ' style="text-align: center;"';
                            echo "<td{$class}{$style}>" . htmlspecialchars((string)($cell ?? '')) . '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody></table></body></html>';
                    exit;
                } else {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=' . $filename . '.csv');
                    $output = fopen('php://output', 'w');
                    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                    fwrite($output, "sep=;\n");
                    fputcsv($output, $headers, ';');
                    foreach ($data as $row) fputcsv($output, $row, ';');
                    fclose($output);
                    exit;
                }
            } catch (Exception $e) {
                $errors[] = 'Error al exportar reporte: ' . $e->getMessage();
            }
        }

        $stats = [];
        try {
            if ($user_rol === ROL_INSTRUCTOR) {
                $stats = $this->reportesModel->getInstructorStats($user_id);
            } else {
                $stats = $this->reportesModel->getGlobalStats();
            }
        } catch (Exception $e) {
            $stats = array_fill_keys(['total_evaluaciones','aprobados','reprobados','pendientes','total_fichas','cambios_historial'], 0);
        }

        $fichas = [];
        try {
            if ($user_rol === ROL_INSTRUCTOR) {
                $fichas = $this->reportesModel->getFichasForInstructor($user_id);
            } else {
                $fichas = $this->reportesModel->getAllFichas();
            }
        } catch (Exception $e) {}

        $this->render(
            BASE_PATH . 'modules/reportes/views/index.view.php',
            [
                'errors' => $errors,
                'stats' => $stats,
                'fichas' => $fichas
            ],
            'Centro de Reportes · SENA'
        );
    }
}
