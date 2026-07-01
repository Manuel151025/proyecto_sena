<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\EvaluacionesModel;
use PDO;
use Exception;

class EvaluacionesController extends BaseController {
    private PDO $db;
    private EvaluacionesModel $evaluacionesModel;

    public function __construct(?PDO $db = null, ?EvaluacionesModel $evaluacionesModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->evaluacionesModel = $evaluacionesModel ?? new EvaluacionesModel($this->db);
    }

    public function index(): void {
        requireAuth();

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

                    $successMessage = 'Evaluación actualizada correctamente. Concepto: ' . $nuevo_concepto;
                } catch (Exception $e) {
                    $errors[] = 'Error al guardar evaluación: ' . $e->getMessage();
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
}
