<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\LogsModel;
use PDO;
use Exception;

class LogsController extends BaseController {
    private PDO $db;
    private LogsModel $logsModel;

    public function __construct(?PDO $db = null, ?LogsModel $logsModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->logsModel = $logsModel ?? new LogsModel($this->db);
    }

    public function index(): void {
        requireRole(ROL_COORDINADOR);

        $errors = [];
        $search = trim($_GET['search'] ?? '');
        $filter_accion = $_GET['accion'] ?? '';

        $logs = [];
        try {
            $logs = $this->logsModel->getLogs($search, $filter_accion);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar los registros de auditoría: ' . $e->getMessage();
        }

        $acciones_badge = [
            'Crear' => 'success',
            'Calificar' => 'primary',
            'Modificar' => 'warning',
            'Eliminar' => 'danger',
            'Login' => 'info',
            'Logout' => 'secondary'
        ];

        $this->render(
            BASE_PATH . 'modules/logs/views/index.view.php',
            [
                'errors' => $errors,
                'logs' => $logs,
                'search' => $search,
                'filter_accion' => $filter_accion,
                'acciones_badge' => $acciones_badge
            ],
            'Bitácora de Auditoría · SENA'
        );
    }
}
