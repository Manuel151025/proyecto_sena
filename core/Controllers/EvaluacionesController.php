<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\EvaluacionesModel;
use Core\Services\JuiciosImportService;
use PDO;
use Exception;

class EvaluacionesController extends BaseController {
    private PDO $db;
    private EvaluacionesModel $evaluacionesModel;
    private JuiciosImportService $importService;

    public function __construct(?PDO $db = null, ?EvaluacionesModel $evaluacionesModel = null, ?JuiciosImportService $importService = null) {
        requireAuth();
        $this->db = $db ?? Database::getConnection();
        $this->evaluacionesModel = $evaluacionesModel ?? new EvaluacionesModel($this->db);
        $this->importService = $importService ?? new JuiciosImportService();
    }

    public function index(): void {
        $errors = [];
        $successMessage = '';

        $user_id = (int)getCurrentUser()['id'];
        $user_rol = getCurrentRole();

        $aprendiz_id = 0;
        if ($user_rol === ROL_APRENDIZ) {
            try {
                $aprendiz_id = $this->evaluacionesModel->getAprendizId($user_id);
            } catch (Exception $e) {
                $errors[] = 'Error al verificar perfil del aprendiz.';
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'evaluar') {
            requireCsrf();
            if ($user_rol === ROL_INSTRUCTOR || $user_rol === ROL_COORDINADOR) {
                try {
                    $eval_id = (int)($_POST['evaluacion_id'] ?? 0);
                    $nuevo_concepto = trim($_POST['concepto'] ?? '');
                    $comentario = trim($_POST['comentario'] ?? '');
                    $motivo = trim($_POST['motivo'] ?? '');

                    if (mb_strlen($comentario, 'UTF-8') > 1000) {
                        throw new Exception('El comentario no puede exceder los 1000 caracteres.');
                    }
                    $comentario = strip_tags($comentario);

                    if (mb_strlen($motivo, 'UTF-8') > 255) {
                        throw new Exception('El motivo no puede exceder los 255 caracteres.');
                    }
                    $motivo = strip_tags($motivo);

                    if ($eval_id <= 0) {
                        throw new Exception('ID de evaluación inválido.');
                    }

                    if (!in_array($nuevo_concepto, ['A', 'D', 'pendiente'])) {
                        throw new Exception('Concepto no válido.');
                    }

                    $conceptoAnterior = $this->evaluacionesModel->getEvaluacionAnterior($eval_id, $user_rol, $user_id);

                    if ($conceptoAnterior === false) {
                        throw new Exception('Evaluación no encontrada o sin permiso para editarla.');
                    }

                    if ($conceptoAnterior !== $nuevo_concepto && in_array($conceptoAnterior, ['A', 'D']) && empty($motivo)) {
                        throw new Exception('El motivo del cambio de calificación es requerido.');
                    }

                    $this->evaluacionesModel->actualizarEvaluacion($eval_id, $nuevo_concepto, $comentario, $motivo, $user_id, $conceptoAnterior);

                    setFlashMessage('Evaluación actualizada correctamente. Concepto: ' . $nuevo_concepto, 'success');
                    $this->redirect($_SERVER['REQUEST_URI']);
                } catch (Exception $e) {
                    setFlashMessage('Error al guardar evaluación: ' . $e->getMessage(), 'danger');
                }
            }
        }

        $fichas = [];
        if ($user_rol !== ROL_APRENDIZ) {
            try {
                $fichas = $this->evaluacionesModel->getFichas($user_rol, $user_id);
            } catch (Exception $e) {
                $errors[] = 'Error al cargar fichas.';
            }
        }

        $search = trim($_GET['search'] ?? '');
        $filter_ficha = (int)($_GET['ficha_id'] ?? 0);
        $filter_concepto = $_GET['concepto'] ?? '';

        $evaluaciones = [];
        try {
            $evaluaciones = $this->evaluacionesModel->getEvaluaciones($user_rol, $user_id, $aprendiz_id, $filter_ficha, $filter_concepto, $search);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar evaluaciones: ' . $e->getMessage();
        }

        $statsEval = ['total' => 0, 'aprobados' => 0, 'reprobados' => 0, 'pendientes' => 0];
        try {
            $statsEvalResult = $this->evaluacionesModel->getStatsEval($user_rol, $user_id, $aprendiz_id);
            if ($statsEvalResult) {
                $statsEval = $statsEvalResult;
            }
        } catch (Exception $e) {}

        $conceptos_label = [
            'A' => ['Aprobado (A)', 'success', 'bi-check-circle-fill'],
            'D' => ['No Aprobado (D)', 'danger', 'bi-x-circle-fill'],
            'pendiente' => ['Pendiente', 'warning', 'bi-clock-fill']
        ];

        $this->render(
            BASE_PATH . 'modules/evaluaciones/views/index.view.php',
            [
                'errors' => $errors,
                'success' => $successMessage,
                'user_rol' => $user_rol,
                'fichas' => $fichas,
                'evaluaciones' => $evaluaciones,
                'statsEval' => $statsEval,
                'conceptos_label' => $conceptos_label,
                'search' => $search,
                'filter_ficha' => $filter_ficha,
                'filter_concepto' => $filter_concepto
            ],
            'Juicios de Evaluación · SENA'
        );
    }

    public function import(): void {
        // Aumentar límites para uploads base64 y archivos grandes
        @ini_set('post_max_size', '64M');
        @ini_set('upload_max_filesize', '64M');
        @ini_set('memory_limit', '256M');

        $errors = [];
        $successMessage = '';
        $import_summary = null;

        // Comprobar si hay resultados de importación almacenados en sesión
        $tabId = getTabId();
        if (isset($_SESSION['tabs'][$tabId]['import_success'])) {
            $successMessage = $_SESSION['tabs'][$tabId]['import_success'];
            $import_summary = $_SESSION['tabs'][$tabId]['import_summary'] ?? null;
            unset($_SESSION['tabs'][$tabId]['import_success'], $_SESSION['tabs'][$tabId]['import_summary']);
        } elseif (isset($_SESSION['import_success'])) {
            $successMessage = $_SESSION['import_success'];
            $import_summary = $_SESSION['import_summary'] ?? null;
            unset($_SESSION['import_success'], $_SESSION['import_summary']);
        }

        $user = getCurrentUser();
        $role = getCurrentRole();

        $is_ajax = (!empty($_POST['file_data']) && !empty($_POST['file_name']));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (empty($_POST) && empty($_FILES)) {
                $errors[] = 'El tamaño del archivo supera el límite permitido por la configuración de PHP del servidor (post_max_size / upload_max_filesize). Intente con un archivo más pequeño.';
                @file_put_contents(BASE_PATH . 'logs/import_errors.log', date('[Y-m-d H:i:s] ') . "POST vacío recibido. Posible exceso de post_max_size en php.ini.\n", FILE_APPEND);
            } else {
                $rutaTemporal = null;
                $originalName = '';
                $jsonResponse = null;
                
                try {
                    if ($is_ajax) {
                        $originalName = $_POST['file_name'];
                        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        if ($ext !== 'xls') {
                            throw new Exception('El archivo debe tener extensión .xls (Reporte binario de Sofia Plus).');
                        }

                        $fileData = base64_decode($_POST['file_data'], true);
                        if ($fileData === false || strlen($fileData) === 0) {
                            throw new Exception('Error: No se pudo decodificar el contenido del archivo. Intenta de nuevo.');
                        }

                        $uploadsDir = realpath(BASE_PATH . 'uploads');
                        if ($uploadsDir === false) {
                            $uploadsDir = BASE_PATH . 'uploads';
                            if (!is_dir($uploadsDir)) {
                                @mkdir($uploadsDir, 0777, true);
                            }
                        }

                        $rutaTemporal = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('ajax_', true) . '.xls';
                        if (file_put_contents($rutaTemporal, $fileData) === false) {
                            throw new Exception('Error al guardar el archivo decodificado en el servidor.');
                        }
                    } else {
                        if (isset($_FILES['excel_file'])) {
                            if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                                throw new Exception('Error al subir el archivo. Código: ' . $_FILES['excel_file']['error']);
                            }
                            $rutaTemporal = $_FILES['excel_file']['tmp_name'];
                            $originalName = $_FILES['excel_file']['name'];
                        } else {
                            throw new Exception('No se ha subido ningún archivo o hubo un error en la subida.');
                        }
                    }

                    // Ejecutar el servicio
                    $stats = $this->importService->import($rutaTemporal, $originalName, (int)$user['id'], $role);

                    $successMessage = "¡Carga masiva finalizada con éxito! Todos los registros fueron procesados.";
                    $import_summary = $stats;

                    if ($is_ajax) {
                        // Almacenar en sesión para que persista al recargar/redirigir
                        $_SESSION['tabs'][$tabId]['import_success'] = $successMessage;
                        $_SESSION['tabs'][$tabId]['import_summary'] = $import_summary;
                        $_SESSION['import_success'] = $successMessage;
                        $_SESSION['import_summary'] = $import_summary;

                        // Diferir la respuesta JSON para que finally se ejecute
                        $jsonResponse = ['success' => true, 'message' => $successMessage];
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    if ($is_ajax) {
                        $jsonResponse = ['success' => false, 'errors' => $errors];
                    }
                } finally {
                    // Asegurar la limpieza del archivo temporal creado en la petición AJAX
                    if ($is_ajax && $rutaTemporal !== null && file_exists($rutaTemporal)) {
                        @unlink($rutaTemporal);
                    }
                }

                // Enviar respuesta JSON y terminar ejecución (exit) si es AJAX
                if ($jsonResponse !== null) {
                    $this->json($jsonResponse);
                }
            }
        }

        $this->render(
            BASE_PATH . 'modules/evaluaciones/views/importar.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'import_summary' => $import_summary
            ],
            'Importar Juicios Evaluativos · SENA'
        );
    }
}
