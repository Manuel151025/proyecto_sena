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
                    setFlashMessage('Ficha eliminada correctamente', 'success');
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    setFlashMessage('No se puede eliminar la ficha porque tiene aprendices matriculados, actividades, o evaluaciones registradas.', 'danger');
                } else {
                    setFlashMessage('Error de base de datos al eliminar la ficha: ' . $e->getMessage(), 'danger');
                }
            } catch (Exception $e) {
                setFlashMessage('Error al eliminar la ficha: ' . $e->getMessage(), 'danger');
            }
            $this->redirect(APP_URL . '/index.php/fichas');
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

    public function view(): void {
        $id = (int) ($_GET['id'] ?? 0);
        $errors = [];
        
        $user = getCurrentUser();
        $role = getCurrentRole();

        if ($role === ROL_APRENDIZ) {
            try {
                $user_ficha_id = $this->fichaModel->getFichaIdByUsuarioId((int)$user['id']);
                if ($user_ficha_id <= 0 || $id !== $user_ficha_id) {
                    if ($user_ficha_id > 0) {
                        $this->redirect(APP_URL . '/index.php/fichas/ver?id=' . $user_ficha_id);
                    } else {
                        denyAccess();
                    }
                }
            } catch (Exception $e) {
                denyAccess();
            }
        }

        $ficha = null;
        try {
            $ficha = $this->fichaModel->getFichaCompleta($id);
            if (!$ficha) {
                $errors[] = 'Ficha no encontrada';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al cargar ficha';
        }

        $aprendices = [];
        if ($ficha) {
            try {
                $aprendices = $this->fichaModel->getAprendicesFicha($id);
            } catch (Exception $e) {
                $aprendices = [];
            }
        }

        $estados_label = [
            'planeacion' => ['Planeación', 'primary'],
            'induccion' => ['Inducción', 'info'],
            'ejecucion' => ['Ejecución', 'warning'],
            'cierre' => ['Cierre', 'success']
        ];

        $estados_aprendiz = [
            'matriculado' => ['Matriculado', 'success'],
            'suspendido' => ['Suspendido', 'warning'],
            'desertado' => ['Desertado', 'danger'],
            'egresado' => ['Egresado', 'info'],
            'etapa_practica' => ['Etapa Práctica', 'primary']
        ];

        $this->render(
            BASE_PATH . 'modules/fichas/views/ver.view.php',
            [
                'id' => $id,
                'errors' => $errors,
                'ficha' => $ficha,
                'aprendices' => $aprendices,
                'estados_label' => $estados_label,
                'estados_aprendiz' => $estados_aprendiz
            ],
            $ficha ? 'Ficha Detalle · SENA' : 'Ficha no encontrada · SENA'
        );
    }

    public function edit(): void {
        requireRole(ROL_COORDINADOR);

        $id = (int) ($_GET['id'] ?? 0);
        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];
        $ficha = null;

        if ($id > 0) {
            try {
                $ficha = $this->fichaModel->getFichaParaEditar($id);
                if (!$ficha) {
                    $errors[] = 'Ficha no encontrada';
                }
            } catch (Exception $e) {
                $errors[] = 'Error al cargar ficha';
            }
        }

        $programas = [];
        $instructores = [];
        $proyectos = [];
        try {
            $programas = $this->fichaModel->getProgramasActivos();
            $instructores = $this->fichaModel->getInstructoresActivos();
            $proyectos = $this->fichaModel->getProyectosActivos();
        } catch (Exception $e) {
            // log error
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            $numero_ficha = trim($_POST['numero_ficha'] ?? '');
            $proyecto_id  = (int) ($_POST['proyecto_id'] ?? 0) ?: null;
            $programa_id  = (int) ($_POST['programa_id'] ?? 0);
            $instructor_id = (int) ($_POST['instructor_id'] ?? 0);
            $estado = $_POST['estado'] ?? 'planeacion';
            $cantidad_aprendices = (int) ($_POST['cantidad_aprendices'] ?? 0);
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $cumplimiento_porcentaje = (float) ($_POST['cumplimiento_porcentaje'] ?? 0);

            if (empty($numero_ficha)) {
                $errors[] = 'El número de ficha es requerido';
            } elseif (mb_strlen($numero_ficha, 'UTF-8') > 20) {
                $errors[] = 'El número de ficha no puede exceder los 20 caracteres';
            } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $numero_ficha)) {
                $errors[] = 'El número de ficha contiene caracteres no permitidos';
            }

            if ($programa_id <= 0) $errors[] = 'Debe seleccionar un programa';
            if ($instructor_id <= 0) $errors[] = 'Debe seleccionar un instructor';
            if (!in_array($estado, ['planeacion', 'induccion', 'ejecucion', 'cierre'])) $errors[] = 'Estado inválido';
            
            if ($cantidad_aprendices < 0 || $cantidad_aprendices > 999) {
                $errors[] = 'La cantidad de aprendices debe estar entre 0 y 999';
            }
            if ($cumplimiento_porcentaje < 0 || $cumplimiento_porcentaje > 100) {
                $errors[] = 'El cumplimiento debe estar entre 0 y 100%';
            }

            if ($fecha_inicio && !strtotime($fecha_inicio)) {
                $errors[] = 'La fecha de inicio no es válida';
            }
            if ($fecha_fin && !strtotime($fecha_fin)) {
                $errors[] = 'La fecha de fin no es válida';
            }
            if ($fecha_inicio && $fecha_fin && strtotime($fecha_inicio) > strtotime($fecha_fin)) {
                $errors[] = 'La fecha de inicio no puede ser posterior a la fecha de fin';
            }

            if (empty($errors)) {
                try {
                    if ($id > 0) {
                        $this->fichaModel->updateFicha($id, $numero_ficha, $proyecto_id, $programa_id, $instructor_id, $estado, $cantidad_aprendices, $fecha_inicio, $fecha_fin, $cumplimiento_porcentaje);
                        setFlashMessage('Ficha actualizada correctamente', 'success');
                    } else {
                        $coordinador_id = getCurrentUser()['id'];
                        $this->fichaModel->createFicha($numero_ficha, $proyecto_id, $programa_id, $instructor_id, $coordinador_id, $estado, $cantidad_aprendices, $fecha_inicio, $fecha_fin, $cumplimiento_porcentaje);
                        setFlashMessage('Ficha creada correctamente', 'success');
                    }
                    $this->redirect(APP_URL . '/index.php/fichas');
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), '1062') !== false) {
                        $errors[] = 'Este número de ficha ya existe';
                    } else {
                        $errors[] = $id > 0 ? 'Error al actualizar ficha' : 'Error al crear ficha';
                    }
                }
            }
        }

        $this->render(
            BASE_PATH . 'modules/fichas/views/editar.view.php',
            [
                'id' => $id,
                'ficha' => $ficha,
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'errors' => $errors,
                'programas' => $programas,
                'instructores' => $instructores,
                'proyectos' => $proyectos
            ],
            ($id && $ficha) ? 'Editar Ficha · SENA' : 'Crear Ficha · SENA'
        );
    }
}
