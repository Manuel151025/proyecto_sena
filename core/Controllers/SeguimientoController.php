<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\SeguimientoModel;
use PDO;
use Exception;

class SeguimientoController extends BaseController {
    private PDO $db;
    private SeguimientoModel $seguimientoModel;

    public function __construct(?PDO $db = null, ?SeguimientoModel $seguimientoModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->seguimientoModel = $seguimientoModel ?? new SeguimientoModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];
        $successMessage = '';

        $user_id  = (int)getCurrentUser()['id'];
        $user_rol = getCurrentRole();

        // 1. PROCESAR ACCIONES (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            requireCsrf();
            $action = $_POST['action'];

            if ($action === 'registrar_evaluacion') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para registrar calificaciones.';
                } else {
                    $ra_id         = (int)($_POST['resultado_aprendizaje_id'] ?? 0);
                    $aprendiz_id_p = (int)($_POST['aprendiz_id'] ?? 0);
                    $ficha_id_p    = (int)($_POST['ficha_id'] ?? 0);
                    $concepto_form = $_POST['concepto'] ?? 'en_proceso';
                    $concepto_map  = ['aprobado' => 'A', 'en_proceso' => 'D', 'no_aplica' => 'pendiente'];
                    $concepto      = $concepto_map[$concepto_form] ?? 'pendiente';
                    $comentario    = trim($_POST['comentario'] ?? '');
                    $motivo        = trim($_POST['motivo'] ?? '');

                    if (mb_strlen($comentario, 'UTF-8') > 1000) {
                        $errors[] = 'El comentario no puede exceder los 1000 caracteres.';
                    }
                    $comentario = strip_tags($comentario);

                    if (mb_strlen($motivo, 'UTF-8') > 255) {
                        $errors[] = 'El motivo no puede exceder los 255 caracteres.';
                    }
                    $motivo = strip_tags($motivo);

                    if ($ra_id <= 0 || $aprendiz_id_p <= 0 || $ficha_id_p <= 0) {
                        $errors[] = 'Datos de evaluación incompletos.';
                    } else {
                        try {
                            if ($user_rol === ROL_INSTRUCTOR) {
                                if (!$this->seguimientoModel->checkInstructorPermission($ra_id, $aprendiz_id_p, $ficha_id_p, $user_id)) {
                                    throw new Exception('No tiene permisos para calificar esta competencia en la ficha seleccionada.');
                                }
                            }
                            $this->seguimientoModel->registrarEvaluacion($ra_id, $aprendiz_id_p, $ficha_id_p, $concepto, $comentario, $motivo, $user_id);
                            $successMessage = 'Evaluación académica guardada correctamente.';
                        } catch (Exception $e) {
                            $errors[] = 'Error al registrar la evaluación: ' . $e->getMessage();
                        }
                    }
                }
            } elseif ($action === 'agregar_retroalimentacion') {
                if (!in_array($user_rol, [ROL_COORDINADOR, ROL_INSTRUCTOR])) {
                    $errors[] = 'No tiene permisos para agregar anotaciones de seguimiento.';
                } else {
                    $aprendiz_id_r = (int)($_POST['aprendiz_id'] ?? 0);
                    $tipo          = $_POST['tipo'] ?? 'recomendacion';
                    $contenido     = trim($_POST['contenido'] ?? '');
                    $privada       = isset($_POST['privada']) ? 1 : 0;

                    if ($aprendiz_id_r <= 0) $errors[] = 'Seleccione un aprendiz válido.';
                    if (empty($contenido)) {
                        $errors[] = 'El detalle del seguimiento es obligatorio.';
                    } elseif (mb_strlen($contenido, 'UTF-8') < 10) {
                        $errors[] = 'El contenido debe tener al menos 10 caracteres.';
                    } elseif (mb_strlen($contenido, 'UTF-8') > 2000) {
                        $errors[] = 'El contenido no puede exceder los 2000 caracteres.';
                    }
                    $contenido = strip_tags($contenido);

                    if (empty($errors)) {
                        try {
                            if ($user_rol === ROL_INSTRUCTOR) {
                                if (!$this->seguimientoModel->checkRetroalimentacionPermission($aprendiz_id_r, $user_id)) {
                                    throw new Exception('El aprendiz no pertenece a ninguna de sus fichas asignadas ni está asignado a su seguimiento.');
                                }
                            }
                            $this->seguimientoModel->agregarRetroalimentacion($aprendiz_id_r, $user_id, $tipo, $contenido, $privada);
                            $successMessage = 'Observación registrada exitosamente.';
                        } catch (Exception $e) {
                            $errors[] = 'Error al registrar observación: ' . $e->getMessage();
                        }
                    }
                }
            }
        }

        // 2. OBTENER DATOS SEGÚN EL ROL
        $fichas               = [];
        $selected_ficha_id    = 0;
        $selected_programa_id = 0;
        $ficha_detalle        = null;
        $aprendices_stats     = [];
        $detalle_evaluaciones = [];
        $detalle_retroalimentacion = [];
        $todas_actividades    = [];
        $todas_evaluaciones   = [];

        $mi_perfil            = null;
        $mis_actividades      = [];
        $mis_retroalimentaciones = [];

        if ($user_rol === ROL_APRENDIZ) {
            try {
                $mi_perfil = $this->seguimientoModel->getPerfilAprendiz($user_id);
                if ($mi_perfil) {
                    $ap_id    = (int)$mi_perfil['id'];
                    $ficha_id = (int)$mi_perfil['ficha_id'];
                    $mis_actividades = $this->seguimientoModel->getMisActividades($ap_id, $ficha_id, (int)$mi_perfil['programa_id']);
                    $mis_retroalimentaciones = $this->seguimientoModel->getMisRetroalimentaciones($ap_id);
                }
            } catch (Exception $e) {
                $errors[] = 'Error al cargar perfil de seguimiento: ' . $e->getMessage();
            }
        } else {
            try {
                $fichas = $this->seguimientoModel->getFichas($user_id, $user_rol);
                $selected_ficha_id = (int)($_GET['ficha_id'] ?? 0);
                
                if ($selected_ficha_id === 0 && !empty($fichas)) {
                    $selected_ficha_id = (int)$fichas[0]['id'];
                }

                if ($selected_ficha_id > 0 && $user_rol === ROL_INSTRUCTOR) {
                    $fichasIds = array_map('intval', array_column($fichas, 'id'));
                    if (!in_array($selected_ficha_id, $fichasIds, true)) {
                        $selected_ficha_id = !empty($fichas) ? (int)$fichas[0]['id'] : 0;
                    }
                }

                if ($selected_ficha_id > 0) {
                    $ficha_detalle = $this->seguimientoModel->getFichaDetalle($selected_ficha_id);
                    if ($ficha_detalle) {
                        $selected_programa_id = (int)$ficha_detalle['programa_id'];
                    }

                    $aprendices_stats = $this->seguimientoModel->getAprendicesStats($selected_ficha_id, $selected_programa_id, $user_rol, $user_id);
                    $todas_actividades = $this->seguimientoModel->getTodasActividades($selected_ficha_id, $selected_programa_id, $user_rol, $user_id);
                    $todas_evaluaciones = $this->seguimientoModel->getTodasEvaluaciones($selected_ficha_id);

                    // Agrupar evaluaciones por [aprendiz_id][ra_id]
                    $eval_map = [];
                    foreach ($todas_evaluaciones as $ev) {
                        $eval_map[(int)$ev['aprendiz_id']][(int)$ev['resultado_aprendizaje_id']] = $ev;
                    }

                    // Construir detalle completo por aprendiz
                    foreach ($aprendices_stats as $ap) {
                        $ap_id = (int)$ap['aprendiz_id'];
                        $detalle_evaluaciones[$ap_id] = [];
                        foreach ($todas_actividades as $ra) {
                            $ra_id     = (int)$ra['ra_id'];
                            $eval_info = $eval_map[$ap_id][$ra_id] ?? null;
                            $detalle_evaluaciones[$ap_id][] = [
                                'ra_id'              => $ra_id,
                                'ra_nombre'          => $ra['ra_nombre'],
                                'ra_codigo'          => $ra['ra_codigo'],
                                'competencia_codigo' => $ra['competencia_codigo'],
                                'competencia_nombre' => $ra['competencia_nombre'],
                                'concepto'           => $eval_info ? $eval_info['concepto'] : null,
                                'comentario'         => $eval_info ? $eval_info['comentario'] : null,
                                'fecha_evaluacion'   => $eval_info ? $eval_info['fecha_evaluacion'] : null,
                            ];
                        }
                    }

                    // Retroalimentaciones por aprendiz de esta ficha
                    $retroalimentaciones = $this->seguimientoModel->getRetroalimentacionesFicha($selected_ficha_id);
                    foreach ($retroalimentaciones as $row) {
                        $detalle_retroalimentacion[(int)$row['aprendiz_id']][] = $row;
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Error al cargar los datos de las fichas: ' . $e->getMessage();
            }
        }

        $conceptos_labels = [
            'A'        => ['Aprobado (A)',   'success'],
            'D'        => ['En Proceso (D)', 'danger'],
            'pendiente' => ['Pendiente',     'secondary'],
        ];

        $feedback_iconos = [
            'fortaleza'       => ['bi bi-check-circle-fill text-success',        'Fortaleza',         'success'],
            'aspecto_mejorar' => ['bi bi-exclamation-triangle-fill text-warning', 'Aspecto a mejorar', 'warning'],
            'recomendacion'   => ['bi bi-info-circle-fill text-info',            'Recomendación',     'info'],
        ];

        $this->render(
            BASE_PATH . 'modules/seguimiento/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'user_rol' => $user_rol,
                'fichas' => $fichas,
                'selected_ficha_id' => $selected_ficha_id,
                'selected_programa_id' => $selected_programa_id,
                'ficha_detalle' => $ficha_detalle,
                'aprendices_stats' => $aprendices_stats,
                'detalle_evaluaciones' => $detalle_evaluaciones,
                'detalle_retroalimentacion' => $detalle_retroalimentacion,
                'mi_perfil' => $mi_perfil,
                'mis_actividades' => $mis_actividades,
                'mis_retroalimentaciones' => $mis_retroalimentaciones,
                'conceptos_labels' => $conceptos_labels,
                'feedback_iconos' => $feedback_iconos
            ],
            'Seguimiento Académico · SENA'
        );
    }
}
