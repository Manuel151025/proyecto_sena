<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\CalendarioModel;
use PDO;

class CalendarioController extends BaseController {
    private PDO $db;
    private CalendarioModel $calendarioModel;

    public function __construct(?PDO $db = null, ?CalendarioModel $calendarioModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->calendarioModel = $calendarioModel ?? new CalendarioModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $rol   = getCurrentRole();
        $user  = getCurrentUser();

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

        $this->render(
            BASE_PATH . 'modules/calendario/views/index.view.php',
            [
                'rol' => $rol,
                'user' => $user,
                'rolLabels' => $rolLabels,
                'roleColors' => $roleColors,
                'apiUrl' => $apiUrl
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
