<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\CalendarioModel;
use Core\Models\FichaModel;
use PDO;
use Exception;

class CalendarioController extends BaseController {
    private PDO $db;
    private CalendarioModel $calendarioModel;
    private FichaModel $fichaModel;

    public function __construct(?PDO $db = null, ?CalendarioModel $calendarioModel = null, ?FichaModel $fichaModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->calendarioModel = $calendarioModel ?? new CalendarioModel($this->db);
        $this->fichaModel = $fichaModel ?? new FichaModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $rol     = getCurrentRole();
        $user    = getCurrentUser();
        $user_id = (int)$user['id'];
        $puedeCrearEvento = in_array($rol, [ROL_COORDINADOR, ROL_INSTRUCTOR], true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $puedeCrearEvento) {
            requireCsrf();

            if ($_POST['action'] === 'crear_evento') {
                $titulo = trim($_POST['titulo'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $fecha = $_POST['fecha'] ?? '';
                $ficha_id = (int)($_POST['ficha_id'] ?? 0);

                if ($titulo === '' || mb_strlen($titulo) > 150) {
                    setFlashMessage('El título del evento es obligatorio (máximo 150 caracteres).', 'danger');
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    setFlashMessage('Fecha inválida.', 'danger');
                } elseif ($ficha_id <= 0) {
                    setFlashMessage('Debe seleccionar una ficha.', 'danger');
                } else {
                    // Verificar que la ficha esté dentro del alcance del usuario
                    $fichasPermitidas = $rol === ROL_COORDINADOR
                        ? $this->fichaModel->getAll()
                        : $this->fichaModel->getByInstructor($user_id);
                    $idsPermitidos = array_column($fichasPermitidas, 'id');

                    if (!in_array($ficha_id, $idsPermitidos, true)) {
                        setFlashMessage('No tiene acceso a esa ficha.', 'danger');
                    } else {
                        try {
                            $this->calendarioModel->crearEvento($titulo, $descripcion ?: null, $fecha, $ficha_id, $user_id);
                            setFlashMessage('Evento agregado al calendario.', 'success');
                        } catch (Exception $e) {
                            error_log('CalendarioController::index crearEvento - ' . $e->getMessage());
                            setFlashMessage('Error al crear el evento.', 'danger');
                        }
                    }
                }
            } elseif ($_POST['action'] === 'eliminar_evento') {
                $evento_id = (int)($_POST['evento_id'] ?? 0);
                if ($evento_id <= 0) {
                    setFlashMessage('Evento no válido.', 'danger');
                } else {
                    $ok = $this->calendarioModel->eliminarEvento($evento_id, $user_id, $rol === ROL_COORDINADOR);
                    setFlashMessage($ok ? 'Evento eliminado.' : 'No se pudo eliminar el evento (no existe o no tiene permiso).', $ok ? 'success' : 'danger');
                }
            }

            $this->redirect(APP_URL . '/index.php/calendario');
        }

        $rolLabels = [
            ROL_COORDINADOR => 'Coordinador',
            ROL_INSTRUCTOR  => 'Instructor',
            ROL_APRENDIZ    => 'Aprendiz',
        ];

        $roleColors = [
            ROL_COORDINADOR => '#39A900',
            ROL_INSTRUCTOR  => '#3B82F6',
            ROL_APRENDIZ    => '#8B5CF6',
        ];

        $apiUrl = APP_URL . '/index.php/calendario/api';

        $fichasDisponibles = [];
        if ($puedeCrearEvento) {
            $fichasDisponibles = $rol === ROL_COORDINADOR
                ? $this->fichaModel->getAll()
                : $this->fichaModel->getByInstructor($user_id);
        }

        $this->render(
            BASE_PATH . 'modules/calendario/views/index.view.php',
            [
                'rol' => $rol,
                'user' => $user,
                'rolLabels' => $rolLabels,
                'roleColors' => $roleColors,
                'apiUrl' => $apiUrl,
                'puedeCrearEvento' => $puedeCrearEvento,
                'fichasDisponibles' => $fichasDisponibles,
            ],
            'Calendario · SENA'
        );
    }

    public function apiEvents(): void {
        if (!isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');

        $start = $_GET['start'] ?? date('Y-m-01');
        $end   = $_GET['end']   ?? date('Y-m-t');

        // Sanitizar fechas
        $start = preg_match('/^\d{4}-\d{2}-\d{2}/', $start) ? substr($start, 0, 10) : date('Y-m-01');
        $end   = preg_match('/^\d{4}-\d{2}-\d{2}/', $end)   ? substr($end,   0, 10) : date('Y-m-t');

        $user    = getCurrentUser();
        $user_id = (int)$user['id'];
        $rol     = getCurrentRole();
        $events  = [];

        if ($rol === ROL_COORDINADOR) {
            $events = $this->calendarioModel->getCoordinadorEvents($start, $end);
        } elseif ($rol === ROL_INSTRUCTOR) {
            $events = $this->calendarioModel->getInstructorEvents($user_id, $start, $end);
        } elseif ($rol === ROL_APRENDIZ) {
            $events = $this->calendarioModel->getAprendizEvents($user_id, $start, $end);
        }

        echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
