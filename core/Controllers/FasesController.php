<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\FasesModel;
use PDO;
use Exception;

class FasesController extends BaseController {
    private PDO $db;
    private FasesModel $fasesModel;

    public function __construct(?PDO $db = null, ?FasesModel $fasesModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->fasesModel = $fasesModel ?? new FasesModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];
        $successMessage = '';
        $user_rol = getCurrentRole();
        $user_id = (int)getCurrentUser()['id'];

        $selected_proyecto_id = (int)($_GET['proyecto_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            requireCsrf();
            if ($_POST['action'] === 'crear') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para administrar fases.';
                } else {
                    $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
                    $numero_fase = (int)($_POST['numero_fase'] ?? 0);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
                    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
                    $cumplimiento = (float)($_POST['cumplimiento_porcentaje'] ?? 0);
                    $estado = $_POST['estado'] ?? 'planeada';

                    if ($proyecto_id <= 0) $errors[] = 'Debe seleccionar un proyecto.';
                    if ($numero_fase <= 0) $errors[] = 'El número de fase debe ser mayor a 0.';
                    if (empty($nombre)) $errors[] = 'El nombre de la fase es obligatorio.';

                    if (empty($errors)) {
                        try {
                            $this->fasesModel->crearFase($proyecto_id, $numero_fase, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $cumplimiento, $estado);
                            $successMessage = 'Fase de proyecto registrada exitosamente.';
                            $selected_proyecto_id = $proyecto_id;
                        } catch (Exception $e) {
                            $errors[] = 'Error al registrar la fase: ' . $e->getMessage();
                        }
                    }
                }
            }
            if ($_POST['action'] === 'editar') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para editar fases.';
                } else {
                    $id          = (int)($_POST['id'] ?? 0);
                    $numero_fase = (int)($_POST['numero_fase'] ?? 0);
                    $nombre      = trim($_POST['nombre'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
                    $fecha_fin    = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
                    $cumplimiento = (float)($_POST['cumplimiento_porcentaje'] ?? 0);
                    $estado       = $_POST['estado'] ?? 'planeada';

                    if ($id <= 0)          $errors[] = 'Fase no válida.';
                    if ($numero_fase <= 0) $errors[] = 'El número de fase debe ser mayor a 0.';
                    if (empty($nombre))    $errors[] = 'El nombre de la fase es obligatorio.';

                    if (empty($errors)) {
                        try {
                            $this->fasesModel->editarFase($id, $numero_fase, $nombre, $descripcion, $fecha_inicio, $fecha_fin, $cumplimiento, $estado);
                            $successMessage = 'Fase actualizada exitosamente.';
                        } catch (Exception $e) {
                            $errors[] = 'Error al actualizar la fase: ' . $e->getMessage();
                        }
                    }
                }
            }
            if ($_POST['action'] === 'eliminar') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para eliminar fases.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Fase no válida.';
                    } else {
                        try {
                            $this->fasesModel->eliminarFase($id);
                            $successMessage = 'Fase eliminada exitosamente.';
                        } catch (Exception $e) {
                            $errors[] = 'No se puede eliminar: la fase tiene registros asociados.';
                        }
                    }
                }
            }
        }

        $proyectos = [];
        $aprendiz_proyecto_id = 0;

        if ($user_rol === ROL_APRENDIZ) {
            try {
                $aprendiz_proyecto_id = $this->fasesModel->getAprendizProyectoId($user_id);
            } catch (Exception $e) {
                $errors[] = 'Error al obtener el proyecto del aprendiz.';
            }
        }

        try {
            if ($user_rol === ROL_APRENDIZ) {
                if ($aprendiz_proyecto_id > 0) {
                    $proyectos = $this->fasesModel->getProyecto($aprendiz_proyecto_id) ?? [];
                } else {
                    $proyectos = [];
                }
                $selected_proyecto_id = $aprendiz_proyecto_id;
            } else {
                $proyectos = $this->fasesModel->getTodosProyectos();
                if ($selected_proyecto_id === 0 && !empty($proyectos)) {
                    $selected_proyecto_id = (int)$proyectos[0]['id'];
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Error al cargar proyectos.';
        }

        $proyectoActual = null;
        if ($selected_proyecto_id > 0) {
            try {
                $proyectoActual = $this->fasesModel->getProyectoActual($selected_proyecto_id);
            } catch (Exception $e) {}
        }

        $fases = [];
        if ($selected_proyecto_id > 0) {
            try {
                $fases = $this->fasesModel->getFases($selected_proyecto_id);
            } catch (Exception $e) {
                $errors[] = 'Error al cargar fases del proyecto.';
            }
        }

        $estados_label = [
            'planeada' => ['Planeada', 'secondary'],
            'en_ejecucion' => ['En Ejecución', 'warning'],
            'completada' => ['Completada', 'success']
        ];

        $this->render(
            BASE_PATH . 'modules/fases/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'user_rol' => $user_rol,
                'selected_proyecto_id' => $selected_proyecto_id,
                'proyectos' => $proyectos,
                'aprendiz_proyecto_id' => $aprendiz_proyecto_id,
                'proyectoActual' => $proyectoActual,
                'fases' => $fases,
                'estados_label' => $estados_label
            ],
            'Fases de Proyecto · SENA'
        );
    }
}
