<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ActividadesModel;
use PDO;
use Exception;

class ActividadesController extends BaseController {
    private PDO $db;
    private ActividadesModel $actividadesModel;

    public function __construct(?PDO $db = null, ?ActividadesModel $actividadesModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->actividadesModel = $actividadesModel ?? new ActividadesModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];
        $successMessage = '';

        $user_id = (int)getCurrentUser()['id'];
        $user_rol = getCurrentRole();

        $aprendiz_ficha_id = 0;
        if ($user_rol === ROL_APRENDIZ) {
            $aprendiz_ficha_id = $this->actividadesModel->getAprendizFichaId($user_id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            requireCsrf();
            $action = $_POST['action'];

            if ($action === 'crear') {
                if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $ficha_id = (int)($_POST['ficha_id'] ?? 0);
                    $competencia_id = (int)($_POST['competencia_id'] ?? 0);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
                    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
                    $responsable_id = (int)($_POST['responsable_id'] ?? 0);
                    $estado = $_POST['estado'] ?? 'pendiente';

                    if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha.';
                    if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia.';
                    if (empty($nombre)) $errors[] = 'El nombre de la actividad es obligatorio.';
                    if ($responsable_id <= 0) $errors[] = 'Debe asignar un responsable.';

                    if (empty($errors)) {
                        try {
                            $this->actividadesModel->create([
                                'ficha_id' => $ficha_id,
                                'competencia_id' => $competencia_id,
                                'nombre' => $nombre,
                                'descripcion' => $descripcion,
                                'fecha_inicio' => $fecha_inicio,
                                'fecha_fin' => $fecha_fin,
                                'estado' => $estado
                            ], $user_id);
                            setFlashMessage('Actividad académica registrada exitosamente.', 'success');
                            $this->redirect($_SERVER['REQUEST_URI']);
                        } catch (Exception $e) {
                            setFlashMessage($e->getMessage(), 'danger');
                        }
                    }
                } else {
                    $errors[] = 'No tiene permisos para crear actividades.';
                }
            } elseif ($action === 'editar') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para editar actividades.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $ficha_id = (int)($_POST['ficha_id'] ?? 0);
                    $competencia_id = (int)($_POST['competencia_id'] ?? 0);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
                    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
                    $responsable_id = (int)($_POST['responsable_id'] ?? 0);
                    $estado = $_POST['estado'] ?? 'pendiente';
                    $cumplimiento = (float)($_POST['cumplimiento_porcentaje'] ?? 0);

                    if ($id <= 0) $errors[] = 'Actividad no válida.';
                    if ($ficha_id <= 0) $errors[] = 'Debe seleccionar una ficha.';
                    if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia.';
                    if (empty($nombre)) $errors[] = 'El nombre de la actividad es obligatorio.';
                    if ($responsable_id <= 0) $errors[] = 'Debe asignar un responsable.';

                    if (empty($errors)) {
                        try {
                            $this->actividadesModel->update($id, [
                                'ficha_id' => $ficha_id,
                                'competencia_id' => $competencia_id,
                                'nombre' => $nombre,
                                'descripcion' => $descripcion,
                                'fecha_inicio' => $fecha_inicio,
                                'fecha_fin' => $fecha_fin,
                                'responsable_id' => $responsable_id,
                                'cumplimiento_porcentaje' => $cumplimiento
                            ], $user_id);
                            setFlashMessage('Actividad actualizada exitosamente.', 'success');
                            $this->redirect($_SERVER['REQUEST_URI']);
                        } catch (Exception $e) {
                            setFlashMessage($e->getMessage(), 'danger');
                        }
                    }
                }
            } elseif ($action === 'eliminar') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para eliminar actividades.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Actividad no válida.';
                    } else {
                        try {
                            $this->actividadesModel->delete($id, $user_id);
                            setFlashMessage('Actividad eliminada exitosamente.', 'success');
                            $this->redirect($_SERVER['REQUEST_URI']);
                        } catch (Exception $e) {
                            setFlashMessage($e->getMessage(), 'danger');
                        }
                    }
                }
            }
        }

        $fichas = [];
        $competencias = [];
        $instructores = [];

        if (in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
            $fichas = $this->actividadesModel->getFichas($user_rol, $user_id);
            $competencias = $this->actividadesModel->getCompetencias();
            $instructores = $this->actividadesModel->getInstructores();
        }

        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'ficha_id' => (int)($_GET['ficha_id'] ?? 0),
            'estado' => $_GET['estado'] ?? ''
        ];

        $actividades = $this->actividadesModel->getActividadesList($user_rol, $user_id, $aprendiz_ficha_id, $filters);

        $estados_label = [
            'pendiente' => ['Pendiente', 'secondary'],
            'en_progreso' => ['En Progreso', 'warning'],
            'completada' => ['Completada', 'success'],
            'cancelada' => ['Cancelada', 'danger']
        ];

        $this->render(
            BASE_PATH . 'modules/actividades/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'actividades' => $actividades,
                'fichas' => $fichas,
                'competencias' => $competencias,
                'instructores' => $instructores,
                'user_rol' => $user_rol,
                'user_id' => $user_id,
                'estados_label' => $estados_label,
                'search' => $filters['search'],
                'filter_ficha' => $filters['ficha_id'],
                'filter_estado' => $filters['estado']
            ],
            'Actividades Académicas · SENA'
        );
    }
}
