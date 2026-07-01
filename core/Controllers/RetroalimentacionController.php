<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\RetroalimentacionModel;
use PDO;
use Exception;

class RetroalimentacionController extends BaseController {
    private PDO $db;
    private RetroalimentacionModel $retroalimentacionModel;

    public function __construct(?PDO $db = null, ?RetroalimentacionModel $retroalimentacionModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->retroalimentacionModel = $retroalimentacionModel ?? new RetroalimentacionModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];
        $success = '';

        $user_id = (int)getCurrentUser()['id'];
        $user_rol = getCurrentRole();

        $tipos_validos = ['fortaleza', 'aspecto_mejorar', 'recomendacion'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_feedback' && $user_rol !== ROL_APRENDIZ) {
            requireCsrf();
            $aprendiz_post = (int)($_POST['aprendiz_id'] ?? 0);
            $tipo          = $_POST['tipo'] ?? '';
            $contenido     = trim($_POST['contenido'] ?? '');
            $privada       = !empty($_POST['privada']) ? 1 : 0;

            if ($aprendiz_post <= 0) {
                $errors[] = 'Debes seleccionar un aprendiz.';
            }
            if (!in_array($tipo, $tipos_validos, true)) {
                $errors[] = 'Tipo de retroalimentación no válido.';
            }
            if (mb_strlen($contenido, 'UTF-8') < 10) {
                $errors[] = 'El contenido debe tener al menos 10 caracteres.';
            } elseif (mb_strlen($contenido, 'UTF-8') > 2000) {
                $errors[] = 'El contenido no puede exceder 2000 caracteres.';
            }
            $contenido = strip_tags($contenido);

            if (empty($errors) && $user_rol === ROL_INSTRUCTOR) {
                try {
                    if (!$this->retroalimentacionModel->checkPermisoInstructor($aprendiz_post, $user_id)) {
                        $errors[] = 'No puedes dar retroalimentación a un aprendiz que no es de tus fichas.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Error al validar autorización.';
                }
            }

            if (empty($errors)) {
                try {
                    $this->retroalimentacionModel->guardarRetroalimentacion($aprendiz_post, $user_id, $tipo, $contenido, $privada);
                    $success = 'Retroalimentación registrada correctamente.';
                } catch (Exception $e) {
                    $errors[] = 'No se pudo guardar la retroalimentación.';
                }
            }
        }

        $aprendiz_id = 0;
        if ($user_rol === ROL_APRENDIZ) {
            try {
                $aprendiz_id = $this->retroalimentacionModel->getAprendizId($user_id);
            } catch (Exception $e) {
                $errors[] = 'Error al cargar perfil de aprendiz.';
            }
        }

        $feedbacks = [];
        try {
            $feedbacks = $this->retroalimentacionModel->getFeedbacks($user_rol, $user_id, $aprendiz_id);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar retroalimentaciones.';
        }

        $aprendices_disponibles = [];
        if ($user_rol !== ROL_APRENDIZ) {
            try {
                $aprendices_disponibles = $this->retroalimentacionModel->getAprendicesDisponibles($user_rol, $user_id);
            } catch (Exception $e) {}
        }

        $tipos_label = [
            'fortaleza'        => ['Fortaleza',          'success', 'bi-award'],
            'aspecto_mejorar'  => ['Aspecto a mejorar',  'warning', 'bi-graph-up-arrow'],
            'recomendacion'    => ['Recomendación',      'info',    'bi-info-circle'],
        ];

        $this->render(
            BASE_PATH . 'modules/retroalimentacion/views/index.view.php',
            [
                'errors' => $errors,
                'success' => $success,
                'user_rol' => $user_rol,
                'feedbacks' => $feedbacks,
                'aprendices_disponibles' => $aprendices_disponibles,
                'tipos_label' => $tipos_label
            ],
            'Retroalimentación Académica · SENA'
        );
    }
}
