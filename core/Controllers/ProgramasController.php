<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ProgramasModel;
use PDO;
use Exception;

class ProgramasController extends BaseController {
    private PDO $db;
    private ProgramasModel $programasModel;

    public function __construct(?PDO $db = null, ?ProgramasModel $programasModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->programasModel = $programasModel ?? new ProgramasModel($this->db);
    }

    /**
     * Listado de programas.
     */
    public function index(): void {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $mensaje = '';
        $tipo_mensaje = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
            try {
                $id = (int)$_POST['id'];
                $this->programasModel->delete($id);
                setFlashMessage('Programa eliminado correctamente', 'success');
            } catch (Exception $e) {
                setFlashMessage($e->getMessage(), 'danger');
            }
            $this->redirect(APP_URL . '/index.php/programas');
        }

        try {
            $programas = $this->programasModel->getAll();
        } catch (Exception $e) {
            $programas = [];
            $mensaje = 'Error al cargar programas: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }

        $estados_label = [
            'activo' => ['Activo', 'success'],
            'inactivo' => ['Inactivo', 'warning'],
            'archivado' => ['Archivado', 'info']
        ];

        $this->render(
            BASE_PATH . 'modules/programas/views/index.view.php',
            [
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'programas' => $programas,
                'estados_label' => $estados_label
            ],
            'Programas de Formación · SENA'
        );
    }

    /**
     * Creación de un nuevo programa.
     */
    public function create(): void {
        requireRole(ROL_COORDINADOR);

        $errores = [];
        $mensaje = '';
        $tipo_mensaje = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $duracion_horas = (int)($_POST['duracion_horas'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';

            if (empty($nombre)) {
                $errores[] = 'El nombre es requerido';
            } elseif (mb_strlen($nombre, 'UTF-8') > 100) {
                $errores[] = 'El nombre no puede exceder los 100 caracteres';
            } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$/u', $nombre)) {
                $errores[] = 'El nombre contiene caracteres no permitidos';
            }

            if (empty($codigo)) {
                $errores[] = 'El código es requerido';
            } elseif (mb_strlen($codigo, 'UTF-8') > 20) {
                $errores[] = 'El código no puede exceder los 20 caracteres';
            } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $codigo)) {
                $errores[] = 'El código solo puede contener letras, números y guiones';
            }

            if ($duracion_horas <= 0 || $duracion_horas > 99999) {
                $errores[] = 'La duración debe estar entre 1 y 99999 horas';
            }

            if (mb_strlen($descripcion, 'UTF-8') > 1000) {
                $errores[] = 'La descripción no puede exceder los 1000 caracteres';
            }
            $descripcion = strip_tags($descripcion);

            if (!in_array($estado, ['activo', 'inactivo', 'archivado'])) {
                $errores[] = 'Estado inválido';
            }

            if (empty($errores)) {
                try {
                    $this->programasModel->create([
                        'nombre' => $nombre,
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'duracion_horas' => $duracion_horas,
                        'estado' => $estado
                    ]);
                    setFlashMessage('Programa creado correctamente', 'success');
                    $this->redirect(APP_URL . '/index.php/programas');
                } catch (Exception $e) {
                    $errores[] = $e->getMessage();
                }
            }
        }

        $this->render(
            BASE_PATH . 'modules/programas/views/crear.view.php',
            [
                'esEdicion' => false,
                'errores' => $errores,
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'valores' => $_POST
            ],
            'Crear Programa · SENA'
        );
    }

    /**
     * Edición de un programa.
     */
    public function edit(): void {
        requireRole(ROL_COORDINADOR);

        $id = (int)($_GET['id'] ?? 0);
        $errores = [];
        $mensaje = '';
        $tipo_mensaje = '';
        $programa = null;

        if ($id > 0) {
            try {
                $programa = $this->programasModel->findById($id);
                if (!$programa) {
                    die('Programa no encontrado');
                }
            } catch (Exception $e) {
                die('Error al cargar programa');
            }
        } else {
            die('ID de programa no válido');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $duracion_horas = (int)($_POST['duracion_horas'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';

            if (empty($nombre)) {
                $errores[] = 'El nombre es requerido';
            } elseif (mb_strlen($nombre, 'UTF-8') > 100) {
                $errores[] = 'El nombre no puede exceder los 100 caracteres';
            } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_.,()]+$/u', $nombre)) {
                $errores[] = 'El nombre contiene caracteres no permitidos';
            }

            if (empty($codigo)) {
                $errores[] = 'El código es requerido';
            } elseif (mb_strlen($codigo, 'UTF-8') > 20) {
                $errores[] = 'El código no puede exceder los 20 caracteres';
            } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $codigo)) {
                $errores[] = 'El código solo puede contener letras, números y guiones';
            }

            if ($duracion_horas <= 0 || $duracion_horas > 99999) {
                $errores[] = 'La duración debe estar entre 1 y 99999 horas';
            }

            if (mb_strlen($descripcion, 'UTF-8') > 1000) {
                $errores[] = 'La descripción no puede exceder los 1000 caracteres';
            }
            $descripcion = strip_tags($descripcion);

            if (!in_array($estado, ['activo', 'inactivo', 'archivado'])) {
                $errores[] = 'Estado inválido';
            }

            if (empty($errores)) {
                try {
                    $data = [
                        'nombre' => $nombre,
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'duracion_horas' => $duracion_horas,
                        'estado' => $estado
                    ];
                    $this->programasModel->update($id, $data);
                    setFlashMessage('Programa actualizado correctamente', 'success');
                    $this->redirect(APP_URL . '/index.php/programas');
                } catch (Exception $e) {
                    $errores[] = $e->getMessage();
                }
            }
        }

        $this->render(
            BASE_PATH . 'modules/programas/views/crear.view.php',
            [
                'esEdicion' => true,
                'errores' => $errores,
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'valores' => $programa
            ],
            'Editar Programa · SENA'
        );
    }
}
