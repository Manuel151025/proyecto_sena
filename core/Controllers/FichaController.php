<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\FichaModel;
use Core\Database;
use Exception;
use PDOException;

class FichaController extends BaseController {
    private FichaModel $fichaModel;

    public function __construct(?FichaModel $fichaModel = null) {
        // Exigir roles
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ);
        $this->fichaModel = $fichaModel ?? new FichaModel();
    }

    public function index(): void {
        $user = getCurrentUser();
        $role = getCurrentRole();

        // Si es aprendiz, redirigir directamente al panel de su ficha
        if ($role === ROL_APRENDIZ) {
            $ficha_id = $this->fichaModel->getFichaIdByUsuarioId((int)$user['id']);
            if ($ficha_id !== null && $ficha_id > 0) {
                $this->redirect(MODULES_PATH . '/fichas/ver.php?id=' . $ficha_id);
            }
            denyAccess();
        }

        $mensaje = '';
        $tipo_mensaje = '';

        // Eliminar ficha (solo coordinador)
        if ($role === ROL_COORDINADOR && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
            try {
                $id = (int) $_POST['id'];
                if ($this->fichaModel->delete($id)) {
                    $mensaje = 'Ficha eliminada correctamente';
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $mensaje = 'No se puede eliminar la ficha porque tiene aprendices matriculados, actividades, o evaluaciones registradas.';
                } else {
                    $mensaje = 'Error de base de datos al eliminar la ficha: ' . $e->getMessage();
                }
                $tipo_mensaje = 'danger';
            } catch (Exception $e) {
                $mensaje = 'Error al eliminar la ficha: ' . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
        }

        // Obtener fichas con información de programa e instructor
        try {
            $instructorId = ($role === ROL_INSTRUCTOR) ? (int)$user['id'] : null;
            $fichas = $this->fichaModel->getDetailedList($instructorId);
        } catch (Exception $e) {
            $fichas = [];
            $mensaje = 'Error al cargar fichas: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }

        $estados_label = [
            'planeacion' => ['Planeación', 'primary'],
            'induccion' => ['Inducción', 'info'],
            'ejecucion' => ['Ejecución', 'warning'],
            'cierre' => ['Cierre', 'success']
        ];

        // Obtener programas para el filtro de la vista
        $db = Database::getConnection();
        $programas = [];
        try {
            $stmtProg = $db->prepare("SELECT DISTINCT codigo, nombre FROM programas ORDER BY nombre");
            $stmtProg->execute();
            $programas = $stmtProg->fetchAll();
        } catch (Exception $e) {
            // Ignorar o registrar error
        }

        $this->render(
            BASE_PATH . 'modules/fichas/views/index.view.php',
            [
                'role' => $role,
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'fichas' => $fichas,
                'estados_label' => $estados_label,
                'programas' => $programas
            ],
            'Fichas de formación · SENA'
        );
    }
}
