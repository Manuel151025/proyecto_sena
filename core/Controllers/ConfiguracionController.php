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

            if (mb_strlen($system_title, 'UTF-8') < 3 || mb_strlen($system_title, 'UTF-8') > 100) {
                $errors[] = 'El nombre del sistema debe tener entre 3 y 100 caracteres.';
            }
            if (mb_strlen($regional, 'UTF-8') < 3 || mb_strlen($regional, 'UTF-8') > 100) {
                $errors[] = 'La regional debe tener entre 3 y 100 caracteres.';
            }
            if (mb_strlen($smtp_server, 'UTF-8') < 3 || mb_strlen($smtp_server, 'UTF-8') > 100) {
                $errors[] = 'El servidor SMTP debe tener entre 3 y 100 caracteres.';
            }

            $system_title = strip_tags($system_title);
            $regional = strip_tags($regional);
            $smtp_server = strip_tags($smtp_server);

            if (empty($errors)) {
                // This is just a stub for now, as the original code didn't save these to DB
                $successMessage = 'Configuración guardada exitosamente en el sistema.';
            }
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
