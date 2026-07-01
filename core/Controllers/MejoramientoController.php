<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\MejoramientoModel;
use PDO;
use Exception;

class MejoramientoController extends BaseController {
    private PDO $db;
    private MejoramientoModel $mejoramientoModel;

    public function __construct(?PDO $db = null, ?MejoramientoModel $mejoramientoModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->mejoramientoModel = $mejoramientoModel ?? new MejoramientoModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];

        $user_id = (int)getCurrentUser()['id'];
        $user_rol = getCurrentRole();

        $aprendiz_id = 0;
        if ($user_rol === ROL_APRENDIZ) {
            try {
                $aprendiz_id = $this->mejoramientoModel->getAprendizId($user_id);
            } catch (Exception $e) {
                $errors[] = 'Error al cargar perfil.';
            }
        }

        $planes = [];
        try {
            $planes = $this->mejoramientoModel->getPlanesMejoramiento($user_rol, $user_id, $aprendiz_id);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar planes de mejoramiento: ' . $e->getMessage();
        }

        $this->render(
            BASE_PATH . 'modules/mejoramiento/views/index.view.php',
            [
                'errors' => $errors,
                'user_rol' => $user_rol,
                'planes' => $planes
            ],
            'Planes de Mejoramiento · SENA'
        );
    }
}
