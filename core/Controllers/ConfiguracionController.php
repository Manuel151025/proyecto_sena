<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ConfiguracionModel;
use PDO;
use Exception;

class ConfiguracionController extends BaseController {
    private PDO $db;
    private ConfiguracionModel $configModel;

    public function __construct(?PDO $db = null, ?ConfiguracionModel $configModel = null) {
        requireRole(ROL_COORDINADOR);
        $this->db = $db ?? Database::getConnection();
        $this->configModel = $configModel ?? new ConfiguracionModel($this->db);
    }

    public function index(): void {
        $errors = [];
        $successMessage = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();

            $system_title = trim($_POST['system_title'] ?? '');
            $regional = trim($_POST['regional'] ?? '');
            $pass_score = trim($_POST['pass_score'] ?? '');
            $smtp_server = trim($_POST['smtp_server'] ?? '');

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
            $pass_score = strip_tags($pass_score);

            if (empty($errors)) {
                try {
                    $this->configModel->save('system_title', $system_title);
                    $this->configModel->save('regional', $regional);
                    $this->configModel->save('pass_score', $pass_score);
                    $this->configModel->save('smtp_server', $smtp_server);

                    setFlashMessage('Configuración guardada exitosamente en el sistema.', 'success');
                    $this->redirect(APP_URL . '/index.php/configuracion');
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } else {
            // Cargar desde la base de datos
            $configs = $this->configModel->getAll();
            $system_title = $configs['system_title'] ?? 'SENA - Seguimiento de Fichas';
            $regional = $configs['regional'] ?? 'Regional Antioquia - Centro de Servicios y Gestión';
            $pass_score = $configs['pass_score'] ?? '70%';
            $smtp_server = $configs['smtp_server'] ?? 'smtp.soy.sena.edu.co';
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
