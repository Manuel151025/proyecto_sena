<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\EvidenciasModel;
use PDO;
use Exception;

class EvidenciasController extends BaseController {
    private PDO $db;
    private EvidenciasModel $evidenciasModel;

    public function __construct(?PDO $db = null, ?EvidenciasModel $evidenciasModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->evidenciasModel = $evidenciasModel ?? new EvidenciasModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];
        $successMessage = '';

        $user_id = (int)getCurrentUser()['id'];
        $user_rol = getCurrentRole();

        $aprendiz_id = 0;
        $ficha_id = 0;

        if ($user_rol === ROL_APRENDIZ) {
            try {
                $ap = $this->evidenciasModel->getAprendizPerfil($user_id);
                if ($ap) {
                    $aprendiz_id = (int)$ap['id'];
                    $ficha_id    = (int)$ap['ficha_id'];
                } else {
                    $errors[] = 'No se encontró perfil de aprendiz para este usuario.';
                }
            } catch (Exception $e) {
                $errors[] = 'Error al consultar perfil del aprendiz.';
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            requireCsrf();
            $action = $_POST['action'];

            if ($action === 'enviar_evidencia') {
                if ($user_rol !== ROL_APRENDIZ) {
                    $errors[] = 'Solo los aprendices pueden enviar evidencias.';
                } else {
                    $titulo      = trim($_POST['titulo'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');

                    if (empty($titulo)) {
                        $errors[] = 'El título de la evidencia es obligatorio.';
                    } elseif (mb_strlen($titulo, 'UTF-8') > 100) {
                        $errors[] = 'El título no puede exceder los 100 caracteres.';
                    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$/u', $titulo)) {
                        $errors[] = 'El título contiene caracteres no permitidos.';
                    }

                    if (mb_strlen($descripcion, 'UTF-8') > 1000) {
                        $errors[] = 'La descripción no puede exceder los 1000 caracteres.';
                    }
                    $descripcion = strip_tags($descripcion);

                    $archivo_url = null;
                    $tipo_archivo = null;
                    $tamanio_kb  = 0;

                    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['archivo']['tmp_name'];
                        $fileName    = $_FILES['archivo']['name'];
                        $tamanio_kb  = (int)round($_FILES['archivo']['size'] / 1024);
                        $tipo_archivo = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        $uploadDir = __DIR__ . '/../../uploads/evidencias/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $newFileName = md5(time() . $fileName) . '.' . $tipo_archivo;
                        if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                            $archivo_url = 'uploads/evidencias/' . $newFileName;
                        } else {
                            $errors[] = 'No se pudo guardar el archivo subido.';
                        }
                    }

                    if (empty($errors)) {
                        try {
                            $this->evidenciasModel->guardarEvidencia($aprendiz_id, $ficha_id, $titulo, $descripcion, $archivo_url, $tipo_archivo, $tamanio_kb, $user_id);
                            $successMessage = 'Evidencia enviada correctamente. Su instructor será notificado.';
                        } catch (Exception $e) {
                            $errors[] = 'Error al enviar la evidencia: ' . $e->getMessage();
                        }
                    }
                }
            } elseif ($action === 'calificar_evidencia') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para calificar evidencias.';
                } else {
                    $evidencia_id  = (int)($_POST['evidencia_id'] ?? 0);
                    $concepto_form = $_POST['concepto'] ?? 'en_proceso';

                    $concepto_map = ['aprobado' => 'A', 'en_proceso' => 'D', 'no_aplica' => 'pendiente'];
                    $concepto_db  = $concepto_map[$concepto_form] ?? 'pendiente';

                    $estado_evidencia = match($concepto_form) {
                        'aprobado'  => 'aprobada',
                        'en_proceso' => 'revisada',
                        default     => 'rechazada',
                    };
                    $tipo_retro = ($concepto_form === 'aprobado') ? 'fortaleza' : 'aspecto_mejorar';
                    $comentario = trim($_POST['comentario'] ?? '');

                    if (mb_strlen($comentario, 'UTF-8') > 1000) {
                        $errors[] = 'El comentario no puede exceder los 1000 caracteres.';
                    }
                    $comentario = strip_tags($comentario);

                    if ($evidencia_id <= 0) $errors[] = 'Evidencia no válida.';

                    if (empty($errors)) {
                        try {
                            $evidencia = $this->evidenciasModel->getEvidencia($evidencia_id);

                            if ($evidencia) {
                                if ($user_rol === ROL_INSTRUCTOR) {
                                    if (!$this->evidenciasModel->checkPermisoCalificar($evidencia, $user_id)) {
                                        throw new Exception('No tiene permisos para calificar esta evidencia.');
                                    }
                                }

                                $this->evidenciasModel->calificarEvidencia($evidencia, $estado_evidencia, $concepto_db, $comentario, $tipo_retro, $user_id);
                                $successMessage = 'Evidencia calificada y retroalimentación registrada con éxito.';
                            } else {
                                $errors[] = 'Evidencia no encontrada.';
                            }
                        } catch (Exception $e) {
                            $errors[] = 'Error al calificar la evidencia: ' . $e->getMessage();
                        }
                    }
                }
            }
        }

        $evidencias = [];
        try {
            $evidencias = $this->evidenciasModel->getEvidencias($user_rol, $user_id, $aprendiz_id);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar evidencias: ' . $e->getMessage();
        }

        $estados_badge = [
            'enviada'   => ['Recibido',  'info'],
            'revisada'  => ['Revisado',  'secondary'],
            'aprobada'  => ['Aprobado',  'success'],
            'rechazada' => ['Rechazado', 'danger'],
        ];

        $this->render(
            BASE_PATH . 'modules/evidencias/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'user_rol' => $user_rol,
                'evidencias' => $evidencias,
                'estados_badge' => $estados_badge
            ],
            'Evidencias Académicas · SENA'
        );
    }
}
