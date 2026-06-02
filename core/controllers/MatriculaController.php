<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\AprendizModel;
use Core\Models\FichaModel;
use Core\Database;
use Exception;

class MatriculaController extends BaseController {
    public function index(): void {
        // Exigir sesión y rol
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $db = Database::getConnection();
        $errors = [];
        $successMessage = '';

        // 1. PROCESAR ACCIONES (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];

            try {
                if ($action === 'matricular') {
                    if (!hasRole(ROL_COORDINADOR)) {
                        throw new Exception('Solo los coordinadores pueden realizar matrículas.');
                    }
                    
                    $data = [
                        'nombre' => mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8'),
                        'email' => trim($_POST['email'] ?? ''),
                        'numero_documento' => trim($_POST['numero_documento'] ?? ''),
                        'tipo_documento' => $_POST['tipo_documento'] ?? 'CC',
                        'ficha_id' => (int)($_POST['ficha_id'] ?? 0),
                        'genero' => $_POST['genero'] ?? 'O',
                        'telefono' => trim($_POST['telefono'] ?? ''),
                        'ciudad' => trim($_POST['ciudad'] ?? ''),
                        'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
                        'instructor_seguimiento_id' => !empty($_POST['instructor_seguimiento_id']) ? (int)$_POST['instructor_seguimiento_id'] : null
                    ];

                    if (empty($data['nombre'])) throw new Exception('El nombre completo es obligatorio.');
                    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido.');
                    if (empty($data['numero_documento'])) throw new Exception('El número de documento es obligatorio.');
                    if ($data['ficha_id'] <= 0) throw new Exception('Debe seleccionar una ficha de formación.');

                    AprendizModel::matricular($data, (int)getCurrentUser()['id']);
                    $successMessage = 'Aprendiz matriculado exitosamente.';

                } elseif ($action === 'editar_matricula') {
                    if (!hasRole(ROL_COORDINADOR)) {
                        throw new Exception('Solo los coordinadores pueden modificar matrículas.');
                    }

                    $aprendiz_id = (int)($_POST['aprendiz_id'] ?? 0);
                    $data = [
                        'nombre' => mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8'),
                        'email' => trim($_POST['email'] ?? ''),
                        'tipo_documento' => $_POST['tipo_documento'] ?? 'CC',
                        'numero_documento' => trim($_POST['numero_documento'] ?? ''),
                        'ficha_id' => (int)($_POST['ficha_id'] ?? 0),
                        'estado' => $_POST['estado'] ?? 'matriculado',
                        'genero' => $_POST['genero'] ?? 'O',
                        'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
                        'telefono' => trim($_POST['telefono'] ?? ''),
                        'ciudad' => trim($_POST['ciudad'] ?? ''),
                        'instructor_seguimiento_id' => !empty($_POST['instructor_seguimiento_id']) ? (int)$_POST['instructor_seguimiento_id'] : null
                    ];

                    if ($aprendiz_id <= 0) throw new Exception('Aprendiz no válido.');
                    if (empty($data['nombre'])) throw new Exception('El nombre completo es obligatorio.');
                    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido.');
                    if (empty($data['numero_documento'])) throw new Exception('El número de documento es obligatorio.');
                    if ($data['ficha_id'] <= 0) throw new Exception('Debe seleccionar una ficha de formación.');

                    AprendizModel::editarMatricula($aprendiz_id, $data, (int)getCurrentUser()['id']);
                    $successMessage = 'Matrícula y datos del aprendiz actualizados exitosamente.';

                } elseif ($action === 'eliminar_matricula') {
                    if (!hasRole(ROL_COORDINADOR)) {
                        throw new Exception('Solo los coordinadores pueden eliminar matrículas.');
                    }

                    $aprendiz_id = (int)($_POST['aprendiz_id'] ?? 0);
                    if ($aprendiz_id <= 0) throw new Exception('Aprendiz no válido.');

                    AprendizModel::eliminar($aprendiz_id, (int)getCurrentUser()['id']);
                    $successMessage = 'Matrícula eliminada exitosamente.';

                } elseif ($action === 'cargar_csv') {
                    if (!hasRole(ROL_COORDINADOR)) {
                        throw new Exception('Solo los coordinadores pueden realizar esta acción.');
                    }

                    $ficha_id = (int)($_POST['ficha_id'] ?? 0);
                    if ($ficha_id <= 0) {
                        throw new Exception('Debe seleccionar una ficha de destino válida.');
                    } elseif (!isset($_FILES['file_csv']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Error al subir el archivo CSV o no se seleccionó ninguno.');
                    }

                    $file = $_FILES['file_csv']['tmp_name'];
                    $handle = fopen($file, 'r');
                    if ($handle === false) {
                        throw new Exception('No se pudo abrir el archivo CSV.');
                    }

                    // Detectar delimitador (coma o punto y coma)
                    $firstLine = fgets($handle);
                    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                    rewind($handle);

                    $successCount = 0;
                    $warnings = [];
                    $rowNum = 0;
                    $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                    $password_hash = password_hash('Sena2026', PASSWORD_DEFAULT);

                    try {
                        $db->beginTransaction();

                        while (($csvData = fgetcsv($handle, 1000, $delimiter)) !== false) {
                            $rowNum++;
                            // Saltar cabecera
                            if ($rowNum === 1 && (
                                stripos($csvData[0], 'nombre') !== false ||
                                stripos($csvData[0], 'nombre_completo') !== false ||
                                stripos($csvData[1], 'email') !== false ||
                                stripos($csvData[3], 'documento') !== false
                            )) {
                                continue;
                            }

                            if (count($csvData) < 4) {
                                $warnings[] = "Fila $rowNum: Columnas insuficientes. Fila omitida.";
                                continue;
                            }

                            $nombre = mb_strtoupper(trim($csvData[0] ?? ''), 'UTF-8');
                            $email = trim($csvData[1] ?? '');
                            $tipo_doc = strtoupper(trim($csvData[2] ?? 'CC'));
                            $num_doc = trim($csvData[3] ?? '');
                            $genero = strtoupper(trim($csvData[4] ?? 'O'));
                            $telefono = trim($csvData[5] ?? '');
                            $ciudad = trim($csvData[6] ?? '');

                            if (empty($nombre) || empty($email) || empty($num_doc)) {
                                $warnings[] = "Fila $rowNum: Campos obligatorios vacíos. Fila omitida.";
                                continue;
                            }

                            // Limpiar tipo_doc y genero
                            if (!in_array($tipo_doc, ['CC', 'TI', 'CE', 'PEP', 'PA'])) $tipo_doc = 'CC';
                            if (!in_array($genero, ['M', 'F', 'O'])) $genero = 'O';

                            // Verificar duplicados
                            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                            $stmt->execute([$email]);
                            if ($stmt->fetch()) {
                                $warnings[] = "Fila $rowNum: Correo '$email' ya registrado. Omitido.";
                                continue;
                            }

                            $stmt = $db->prepare("SELECT id FROM aprendices WHERE numero_documento = ?");
                            $stmt->execute([$num_doc]);
                            if ($stmt->fetch()) {
                                $warnings[] = "Fila $rowNum: Documento '$num_doc' ya registrado. Omitido.";
                                continue;
                            }

                            // 1. Crear el usuario
                            $avatar_color = $colors[array_rand($colors)];
                            $stmt = $db->prepare("
                                INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado)
                                VALUES (?, ?, ?, 'aprendiz', ?, 'activo')
                            ");
                            $stmt->execute([$nombre, $email, $password_hash, $avatar_color]);
                            $usuario_id = (int)$db->lastInsertId();

                            // 2. Crear aprendiz
                            $stmt = $db->prepare("
                                INSERT INTO aprendices (usuario_id, ficha_id, numero_documento, tipo_documento, genero, estado, telefono, ciudad)
                                VALUES (?, ?, ?, ?, ?, 'matriculado', ?, ?)
                            ");
                            $stmt->execute([$usuario_id, $ficha_id, $num_doc, $tipo_doc, $genero, $telefono, $ciudad]);
                            $new_ap_id = (int)$db->lastInsertId();

                            // 3. Inicializar evaluaciones
                            if (function_exists('inicializarEvaluacionesAprendiz')) {
                                inicializarEvaluacionesAprendiz($db, $new_ap_id, $ficha_id);
                            }

                            // 4. Incrementar contador en la ficha
                            $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$ficha_id]);

                            $successCount++;
                        }

                        fclose($handle);

                        if ($successCount > 0) {
                            $db->commit();
                            $successMessage = "Se matricularon exitosamente $successCount aprendices y se inicializaron sus evaluaciones.";
                            if (!empty($warnings)) {
                                $successMessage .= "<br><strong>Nota:</strong> Se omitieron algunas filas:<br>" . implode("<br>", array_slice($warnings, 0, 10));
                            }
                        } else {
                            $db->rollBack();
                            throw new Exception("No se matriculó ningún aprendiz. Revise los errores:<br>" . implode("<br>", $warnings));
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $e;
                    }
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // 2. OBTENER DATOS PARA LA VISTA
        $fichas = [];
        $instructores = [];
        $aprendices = [];

        try {
            if (getCurrentRole() === ROL_INSTRUCTOR) {
                $fichas = FichaModel::getByInstructor((int)getCurrentUser()['id']);
            } else {
                $fichas = FichaModel::getAll();
            }

            // Instructores activos
            $stmtInst = $db->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY nombre");
            $stmtInst->execute();
            $instructores = $stmtInst->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar fichas o instructores.';
        }

        // Filtros
        $search = trim($_GET['search'] ?? '');
        $filter_ficha = (int)($_GET['ficha_id'] ?? 0);
        $filter_estado = $_GET['estado'] ?? '';

        $filters = [
            'search' => $search,
            'ficha_id' => $filter_ficha,
            'estado' => $filter_estado
        ];

        try {
            $instructorIdScope = (getCurrentRole() === ROL_INSTRUCTOR) ? (int)getCurrentUser()['id'] : null;
            $aprendices = AprendizModel::getFilteredList($filters, $instructorIdScope);
        } catch (Exception $e) {
            $errors[] = 'Error al cargar los aprendices: ' . $e->getMessage();
        }

        // Etiquetas de estado
        $estados_label = [
            'matriculado' => ['Matriculado', 'success'],
            'suspendido' => ['Suspendido', 'warning'],
            'desertado' => ['Desertado', 'danger'],
            'egresado' => ['Egresado', 'info'],
            'etapa_practica' => ['Etapa Práctica', 'primary']
        ];

        // Renderizar la vista
        $this->render(
            BASE_PATH . 'modules/matriculas/views/index.view.php',
            [
                'fichas' => $fichas,
                'instructores' => $instructores,
                'aprendices' => $aprendices,
                'search' => $search,
                'filter_ficha' => $filter_ficha,
                'filter_estado' => $filter_estado,
                'estados_label' => $estados_label,
                'successMessage' => $successMessage,
                'errors' => $errors
            ],
            'Gestión de Matrículas · SENA'
        );
    }
}
