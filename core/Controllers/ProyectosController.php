<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ProyectosModel;
use PDO;
use Exception;

class ProyectosController extends BaseController {
    private PDO $db;
    private ProyectosModel $proyectosModel;

    public function __construct(?PDO $db = null, ?ProyectosModel $proyectosModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->proyectosModel = $proyectosModel ?? new ProyectosModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $errors = [];
        $success = '';
        $user_rol = getCurrentRole();
        $user_id = (int)getCurrentUser()['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            requireCsrf();
            if ($_POST['action'] === 'crear' && $user_rol === ROL_COORDINADOR) {
                try {
                    $nombre = trim($_POST['nombre'] ?? '');
                    $codigo = trim($_POST['codigo'] ?? '');
                    $objetivo = trim($_POST['objetivo'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');

                    if (empty($nombre) || empty($codigo)) {
                        throw new Exception('El nombre y código son obligatorios.');
                    }
                    if (mb_strlen($nombre, 'UTF-8') > 100) {
                        throw new Exception('El nombre no puede exceder los 100 caracteres.');
                    }
                    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$/u', $nombre)) {
                        throw new Exception('El nombre contiene caracteres no permitidos.');
                    }
                    if (mb_strlen($codigo, 'UTF-8') > 20) {
                        throw new Exception('El código no puede exceder los 20 caracteres.');
                    }
                    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $codigo)) {
                        throw new Exception('El código solo puede contener letras, números y guiones.');
                    }
                    if (mb_strlen($objetivo, 'UTF-8') > 1000 || mb_strlen($descripcion, 'UTF-8') > 1000) {
                        throw new Exception('El objetivo y la descripción no pueden exceder los 1000 caracteres.');
                    }
                    $objetivo = strip_tags($objetivo);
                    $descripcion = strip_tags($descripcion);

                    $this->proyectosModel->crearProyecto($nombre, $codigo, $objetivo, $descripcion);
                    $success = 'Proyecto formativo creado exitosamente.';
                } catch (Exception $e) {
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
            if ($_POST['action'] === 'delete' && $user_rol === ROL_COORDINADOR) {
                try {
                    $id = (int)$_POST['id'];
                    $this->proyectosModel->eliminarProyecto($id);
                    $success = 'Proyecto eliminado correctamente.';
                } catch (Exception $e) {
                    $errors[] = 'No se puede eliminar: el proyecto tiene fichas o fases asociadas.';
                }
            }
            if ($_POST['action'] === 'editar' && $user_rol === ROL_COORDINADOR) {
                try {
                    $id          = (int)($_POST['id'] ?? 0);
                    $nombre      = trim($_POST['nombre'] ?? '');
                    $codigo      = trim($_POST['codigo'] ?? '');
                    $objetivo    = trim($_POST['objetivo'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $estado      = $_POST['estado'] ?? 'activo';

                    if (empty($nombre) || empty($codigo)) {
                        throw new Exception('El nombre y código son obligatorios.');
                    }
                    if (mb_strlen($nombre, 'UTF-8') > 100) {
                        throw new Exception('El nombre no puede exceder los 100 caracteres.');
                    }
                    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$/u', $nombre)) {
                        throw new Exception('El nombre contiene caracteres no permitidos.');
                    }
                    if (mb_strlen($codigo, 'UTF-8') > 20) {
                        throw new Exception('El código no puede exceder los 20 caracteres.');
                    }
                    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $codigo)) {
                        throw new Exception('El código solo puede contener letras, números y guiones.');
                    }
                    if (mb_strlen($objetivo, 'UTF-8') > 1000 || mb_strlen($descripcion, 'UTF-8') > 1000) {
                        throw new Exception('El objetivo y la descripción no pueden exceder los 1000 caracteres.');
                    }
                    $objetivo = strip_tags($objetivo);
                    $descripcion = strip_tags($descripcion);
                    if (!in_array($estado, ['activo', 'inactivo', 'finalizado'])) {
                        throw new Exception('Estado inválido.');
                    }

                    $this->proyectosModel->editarProyecto($id, $nombre, $codigo, $objetivo, $descripcion, $estado);
                    $success = 'Proyecto actualizado correctamente.';
                } catch (Exception $e) {
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
        }

        $proyectos = [];
        try {
            $proyectos = $this->proyectosModel->getProyectos($user_rol, $user_id);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar los proyectos: ' . $e->getMessage();
        }

        $this->render(
            BASE_PATH . 'modules/proyectos/views/index.view.php',
            [
                'errors' => $errors,
                'success' => $success,
                'user_rol' => $user_rol,
                'proyectos' => $proyectos
            ],
            'Proyectos Formativos · SENA'
        );
    }
}
