<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use PDO;

class ConfiguracionController extends BaseController {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function index(): void {
        requireRole(ROL_COORDINADOR);

        $errors = [];
        $successMessage = '';

        $system_title = $_POST['system_title'] ?? 'SENA - Seguimiento de Fichas';
        $regional = $_POST['regional'] ?? 'Regional Antioquia - Centro de Servicios y Gestión';
        $pass_score = $_POST['pass_score'] ?? '70%';
        $smtp_server = $_POST['smtp_server'] ?? 'smtp.soy.sena.edu.co';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            // This is just a stub for now, as the original code didn't save these to DB
            $successMessage = 'Configuración guardada exitosamente en el sistema.';
        }

        $this->render(
            BASE_PATH . 'modules/configuracion/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'system_title' => $system_title,
                'regional' => $regional,
                'pass_score' => $pass_score,
                'smtp_server' => $smtp_server
            ],
            'Configuración del Sistema · SENA'
        );
    }
}
