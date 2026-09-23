<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\Support\ErrorDeNegocio;
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
                    setFlashMessage(ErrorDeNegocio::mensajeSeguro($e, 'Error de base de datos al eliminar la ficha'), 'danger');
                }
            } catch (Exception $e) {
                setFlashMessage(ErrorDeNegocio::mensajeSeguro($e, 'Error al eliminar la ficha'), 'danger');
            }
            $this->redirect(APP_URL . '/index.php/fichas');
        }

        // Obtener fichas con información de programa e instructor
        try {
            $instructorId = ($role === ROL_INSTRUCTOR) ? (int)$user['id'] : null;
            $fichas = $this->fichaModel->getDetailedList($instructorId);
        } catch (Exception $e) {
            $fichas = [];
            $mensaje = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar fichas');
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
            } elseif ($role === ROL_INSTRUCTOR && (int)$ficha['instructor_id'] !== (int)$user['id']) {
                denyAccess('No tienes permiso para ver una ficha que no tienes asignada.');
            }
        } catch (Exception $e) {
            $errors[] = 'Error al cargar ficha';
        }

        if (!empty($errors)) {
            setFlashMessage($errors[0], 'danger');
            $this->redirect(APP_URL . '/index.php/fichas');
            exit;
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
            $v = new \Core\Support\Validador($_POST);

            $numero_ficha  = $v->texto('numero_ficha', 'El número de ficha', 1, 20);
            $proyecto_id   = $v->id('proyecto_id', 'El proyecto', false) ?: null;
            $programa_id   = $v->id('programa_id', 'El programa');
            $instructor_id = $v->id('instructor_id', 'El instructor');
            $estado        = $v->enum('estado', 'El estado', ['planeacion', 'induccion', 'ejecucion', 'cierre'], 'planeacion');
            $fecha_inicio  = $v->fecha('fecha_inicio', 'La fecha de inicio', false);
            $fecha_fin     = $v->fecha('fecha_fin', 'La fecha de fin', false);
            $v->rangoFechas($fecha_inicio, $fecha_fin);

            if ($numero_ficha !== '' && !preg_match('/^[a-zA-Z0-9\-]+$/', $numero_ficha)) {
                $v->agregarError('El número de ficha contiene caracteres no permitidos.');
            }

            // `cantidad_aprendices` ya no se acepta del formulario: es el
            // número de aprendices matriculados, un dato derivado de la tabla
            // `aprendices`. Poder teclearlo a mano es lo que lo desincronizó
            // en 3 de las 7 fichas. Los listados lo calculan al leer.
            $cantidad_aprendices = $this->fichaModel->contarAprendices($id);

            // `cumplimiento_porcentaje` tampoco: lo recalcula el sistema al
            // calificar evidencias. Aceptarlo por formulario significaba que
            // el valor mostrado dependía de quién hubiera escrito el último.
            $cumplimiento_porcentaje = $id > 0
                ? (float)($this->fichaModel->getFichaById($id)['cumplimiento_porcentaje'] ?? 0)
                : 0.0;

            $errors = array_merge($errors, $v->errores());

            if (empty($errors)) {
                try {
                    if ($id > 0) {
                        $this->fichaModel->updateFicha($id, $numero_ficha, $proyecto_id, $programa_id, $instructor_id, $estado, $cantidad_aprendices, $fecha_inicio, $fecha_fin, $cumplimiento_porcentaje);

                        // Editar una ficha puede cambiarle el programa —y con
                        // él, el juego de RAP que deben evaluarse— o asignarle
                        // por primera vez un instructor líder, que es
                        // obligatorio para poder crear evaluaciones. En ambos
                        // casos sus aprendices necesitan las filas del nuevo
                        // conjunto. No se borra nada de lo ya evaluado: los RAP
                        // del programa anterior conservan su historial.
                        $sync = (new \Core\Services\EvaluacionesSyncService(Database::getConnection()))
                            ->sincronizar(['ficha_id' => $id]);

                        $mensaje = 'Ficha actualizada correctamente';
                        if ($sync['creadas'] > 0) {
                            $mensaje .= ". Se habilitaron {$sync['creadas']} evaluaciones pendientes para sus aprendices.";
                        }
                        setFlashMessage($mensaje, 'success');
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
