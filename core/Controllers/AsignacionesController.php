<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\AsignacionesModel;
use PDO;
use Exception;

class AsignacionesController extends BaseController {
    private PDO $db;
    private AsignacionesModel $asignacionesModel;

    public function __construct(?PDO $db = null, ?AsignacionesModel $asignacionesModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->asignacionesModel = $asignacionesModel ?? new AsignacionesModel($this->db);
    }

    public function index(): void {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $errors = [];
        $successMessage = '';
        $user_id = (int)getCurrentUser()['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            requireCsrf();
            if (!hasRole(ROL_COORDINADOR)) {
                $errors[] = 'Solo los coordinadores pueden gestionar asignaciones.';
            } else {
                if ($_POST['action'] === 'asignar') {
                    $ficha_id = (int)($_POST['ficha_id'] ?? 0);
                    $competencia_id = (int)($_POST['competencia_id'] ?? 0);
                    $instructor_id = (int)($_POST['instructor_id'] ?? 0);

                    if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha válida.';
                    if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia válida.';
                    if ($instructor_id <= 0) $errors[] = 'Debe seleccionar un instructor válido.';

                    if (empty($errors)) {
                        try {
                            if ($this->asignacionesModel->checkAsignacionExiste($ficha_id, $competencia_id)) {
                                $errors[] = 'Ya existe un instructor asignado a esta competencia en esta ficha. Elimine la asignación previa primero.';
                            } else {
                                $this->asignacionesModel->crearAsignacion($ficha_id, $competencia_id, $instructor_id, $user_id);
                                $successMessage = 'Instructor asignado exitosamente a la competencia.';
                            }
                        } catch (Exception $e) {
                            $errors[] = 'Error al realizar la asignación: ' . $e->getMessage();
                        }
                    }
                } elseif ($_POST['action'] === 'eliminar') {
                    $asignacion_id = (int)($_POST['asignacion_id'] ?? 0);
                    if ($asignacion_id <= 0) {
                        $errors[] = 'ID de asignación no válido.';
                    } else {
                        try {
                            $this->asignacionesModel->eliminarAsignacion($asignacion_id, $user_id);
                            $successMessage = 'Asignación eliminada exitosamente.';
                        } catch (Exception $e) {
                            $errors[] = 'Error al eliminar la asignación: ' . $e->getMessage();
                        }
                    }
                }
            }
        }

        $search = trim($_GET['search'] ?? '');
        $filter_ficha = (int)($_GET['ficha_id'] ?? 0);
        $filter_instructor = (int)($_GET['instructor_id'] ?? 0);

        $asignaciones = [];
        try {
            $asignaciones = $this->asignacionesModel->getAsignaciones($search, $filter_ficha, $filter_instructor);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar asignaciones.';
        }

        $fichas = [];
        $competencias = [];
        $instructores = [];
        try {
            $fichas = $this->asignacionesModel->getFichas();
            $competencias = $this->asignacionesModel->getCompetencias();
            $instructores = $this->asignacionesModel->getInstructores();
        } catch (Exception $e) {
            $errors[] = 'Error al cargar datos auxiliares.';
        }

        $this->render(
            BASE_PATH . 'modules/asignaciones/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'search' => $search,
                'filter_ficha' => $filter_ficha,
                'filter_instructor' => $filter_instructor,
                'asignaciones' => $asignaciones,
                'fichas' => $fichas,
                'competencias' => $competencias,
                'instructores' => $instructores
            ],
            'Asignaciones de Instructores · SENA'
        );
    }
}
