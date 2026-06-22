<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Services\JuiciosImportService;
use Exception;

class EvaluacionController extends BaseController {
    private JuiciosImportService $importService;

    public function __construct(?JuiciosImportService $importService = null) {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $this->importService = $importService ?? new JuiciosImportService();
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
                $tempPath = '';
                $originalName = '';
                
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

                        $tempPath = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('ajax_', true) . '.xls';
                        if (file_put_contents($tempPath, $fileData) === false) {
                            throw new Exception('Error al guardar el archivo decodificado en el servidor.');
                        }
                    } else {
                        if (isset($_FILES['excel_file'])) {
                            if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                                throw new Exception('Error al subir el archivo. Código: ' . $_FILES['excel_file']['error']);
                            }
                            $tempPath = $_FILES['excel_file']['tmp_name'];
                            $originalName = $_FILES['excel_file']['name'];
                        } else {
                            throw new Exception('No se ha subido ningún archivo o hubo un error en la subida.');
                        }
                    }

                    // Ejecutar el servicio
                    $stats = $this->importService->import($tempPath, $originalName, (int)$user['id'], $role);

                    $successMessage = "¡Carga masiva finalizada con éxito! Todos los registros fueron procesados.";
                    $import_summary = $stats;

                    if ($is_ajax) {
                        // Almacenar en sesión para que persista al recargar/redirigir
                        $_SESSION['tabs'][$tabId]['import_success'] = $successMessage;
                        $_SESSION['tabs'][$tabId]['import_summary'] = $import_summary;
                        $_SESSION['import_success'] = $successMessage;
                        $_SESSION['import_summary'] = $import_summary;

                        $this->json(['success' => true, 'message' => $successMessage]);
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    if ($is_ajax) {
                        $this->json(['success' => false, 'errors' => $errors]);
                    }
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
