<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ResultadosAprendizajeModel;
use Core\Models\CompetenciasModel;
use Core\XlsxParser;
use PDO;
use Exception;

class ResultadosAprendizajeController extends BaseController {
    private PDO $db;
    private ResultadosAprendizajeModel $resultadosModel;
    private CompetenciasModel $competenciasModel;

    public function __construct(
        ?PDO $db = null,
        ?ResultadosAprendizajeModel $resultadosModel = null,
        ?CompetenciasModel $competenciasModel = null
    ) {
        $this->db = $db ?? Database::getConnection();
        $this->resultadosModel = $resultadosModel ?? new ResultadosAprendizajeModel($this->db);
        $this->competenciasModel = $competenciasModel ?? new CompetenciasModel($this->db);
    }

    /**
     * Listado y gestión de Resultados de Aprendizaje (RAP).
     */
    public function index(): void {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $errors = [];
        $successMessage = '';

        $userId = (int)getCurrentUser()['id'];

        // Procesar formulario de creación de RAP manual
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_rap') {
            if (!hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)) {
                $errors[] = 'No tiene permisos para registrar Resultados de Aprendizaje.';
            } else {
                $competencia_id = (int)($_POST['competencia_id'] ?? 0);
                $codigo = trim($_POST['codigo'] ?? '');
                $denominacion = trim($_POST['denominacion'] ?? '');

                if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia válida.';
                if (empty($codigo)) $errors[] = 'El código del RAP es obligatorio.';
                if (empty($denominacion)) $errors[] = 'La denominación del RAP es obligatoria.';

                if (empty($errors)) {
                    try {
                        $this->resultadosModel->create([
                            'competencia_id' => $competencia_id,
                            'codigo' => $codigo,
                            'denominacion' => $denominacion
                        ], $userId);
                        $successMessage = 'Resultado de Aprendizaje (RAP) registrado exitosamente.';
                    } catch (Exception $e) {
                        $errors[] = 'Error al registrar RAP: ' . $e->getMessage();
                    }
                }
            }
        }

        // Procesar edición de RAP
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_rap') {
            if (!hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)) {
                $errors[] = 'No tiene permisos para editar Resultados de Aprendizaje.';
            } else {
                $id             = (int)($_POST['id'] ?? 0);
                $competencia_id = (int)($_POST['competencia_id'] ?? 0);
                $codigo         = trim($_POST['codigo'] ?? '');
                $denominacion   = trim($_POST['denominacion'] ?? '');

                if ($id <= 0)             $errors[] = 'RAP no válido.';
                if ($competencia_id <= 0) $errors[] = 'Debe seleccionar una competencia válida.';
                if (empty($codigo))       $errors[] = 'El código del RAP es obligatorio.';
                if (empty($denominacion)) $errors[] = 'La denominación del RAP es obligatoria.';

                if (empty($errors)) {
                    try {
                        $this->resultadosModel->update($id, [
                            'competencia_id' => $competencia_id,
                            'codigo' => $codigo,
                            'denominacion' => $denominacion
                        ], $userId);
                        $successMessage = 'Resultado de Aprendizaje actualizado exitosamente.';
                    } catch (Exception $e) {
                        $errors[] = 'Error al actualizar RAP: ' . $e->getMessage();
                    }
                }
            }
        }

        // Procesar eliminación de RAP
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_rap') {
            if (!hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR)) {
                $errors[] = 'No tiene permisos para eliminar Resultados de Aprendizaje.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $errors[] = 'RAP no válido.';
                } else {
                    try {
                        $this->resultadosModel->delete($id, $userId);
                        $successMessage = 'Resultado de Aprendizaje eliminado exitosamente.';
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Obtener competencias con RAPs de la BD usando el modelo
        $competencias = [];
        try {
            $competencias = $this->resultadosModel->getCompetenciasWithRaps();
        } catch (Exception $e) {
            $errors[] = 'Error al cargar las competencias o RAPs: ' . $e->getMessage();
        }

        $this->render(
            BASE_PATH . 'modules/resultados-aprendizaje/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'competencias' => $competencias
            ],
            'Resultados de Aprendizaje (RAP) · SENA'
        );
    }

    /**
     * Importación masiva de RAPs.
     */
    public function import(): void {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $errors = [];
        $successMessage = '';
        $resultados = [];

        $userId = (int)getCurrentUser()['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['archivo_raps']) && $_FILES['archivo_raps']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['archivo_raps']['tmp_name'];
                $fileName = $_FILES['archivo_raps']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExtension === 'xls') {
                    $errors[] = 'El formato .xls no está soportado. Por favor, guarde su archivo como .xlsx o expórtelo como .csv.';
                } elseif (!in_array($fileExtension, ['csv', 'xlsx'])) {
                    $errors[] = 'El archivo debe ser un archivo de Excel (.xlsx) o un archivo de texto separado por comas (.csv).';
                } else {
                    $rows = [];
                    if ($fileExtension === 'csv') {
                        $handle = fopen($fileTmpPath, 'r');
                        if ($handle !== false) {
                            $firstLine = fgets($handle);
                            $separator = (strpos($firstLine, ';') !== false) ? ';' : ',';
                            rewind($handle);

                            while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
                                $rows[] = $data;
                            }
                            fclose($handle);
                        } else {
                            $errors[] = 'No se pudo abrir el archivo CSV.';
                        }
                    } else {
                        try {
                            $rows = XlsxParser::parse($fileTmpPath);
                        } catch (Exception $e) {
                            $errors[] = 'Error al procesar el archivo Excel: ' . $e->getMessage();
                        }
                    }

                    if (empty($errors)) {
                        if (count($rows) <= 1) {
                            $errors[] = 'El archivo está vacío o solo contiene la cabecera.';
                        } else {
                            // Ignorar cabecera
                            array_shift($rows);

                            $linea = 2;
                            $rapsData = [];

                            // Cargar competencias existentes para buscar por código de forma rápida usando el modelo
                            $competenciasMap = [];
                            try {
                                $comp_list = $this->competenciasModel->getAll();
                                foreach ($comp_list as $c) {
                                    $competenciasMap[strtolower(trim($c['codigo']))] = (int)$c['id'];
                                }
                            } catch (Exception $e) {
                                $errors[] = 'Error al precargar códigos de competencias.';
                            }

                            foreach ($rows as $data) {
                                if (empty($data) || (empty($data[0]) && empty($data[1]) && empty($data[2]))) {
                                    $linea++;
                                    continue;
                                }

                                if (count($data) < 3) {
                                    $errors[] = "Línea $linea: Faltan columnas. Se requiere: Código Competencia, Código RAP, Nombre RAP.";
                                    $linea++;
                                    continue;
                                }

                                $comp_code = strtolower(trim((string)($data[0] ?? '')));
                                $rap_code = trim((string)($data[1] ?? ''));
                                $rap_name = mb_strtoupper(trim((string)($data[2] ?? '')), 'UTF-8');

                                $rowErrors = [];
                                if (!isset($competenciasMap[$comp_code])) {
                                    $rowErrors[] = "Línea $linea: El código de competencia '$comp_code' no existe en la base de datos.";
                                }
                                if (empty($rap_code)) {
                                    $rowErrors[] = "Línea $linea: El código del RAP está vacío.";
                                }
                                if (empty($rap_name)) {
                                    $rowErrors[] = "Línea $linea: La descripción/nombre del RAP está vacía.";
                                }

                                if (empty($rowErrors)) {
                                    $rapsData[] = [
                                        'competencia_id' => $competenciasMap[$comp_code],
                                        'codigo' => $rap_code,
                                        'denominacion' => $rap_name
                                    ];
                                } else {
                                    $errors = array_merge($errors, $rowErrors);
                                }
                                $linea++;
                            }

                            if (empty($errors)) {
                                if (count($rapsData) > 0) {
                                    try {
                                        $this->db->beginTransaction();

                                        $importedCount = 0;
                                        foreach ($rapsData as $rap) {
                                            $this->resultadosModel->create($rap, $userId);
                                            $importedCount++;
                                        }

                                        // Registrar log general de importación
                                        $logStmt = $this->db->prepare("
                                            INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, descripcion)
                                            VALUES (?, 'Importar', 'RAPs', 'resultados_aprendizaje', ?)
                                        ");
                                        $logStmt->execute([
                                            $userId,
                                            "Importó masivamente $importedCount Resultados de Aprendizaje (RAPs)"
                                        ]);

                                        $this->db->commit();
                                        $successMessage = "Se han importado exitosamente $importedCount Resultados de Aprendizaje.";
                                        $resultados = $rapsData;
                                    } catch (Exception $e) {
                                        if ($this->db->inTransaction()) {
                                            $this->db->rollBack();
                                        }
                                        $errors[] = 'Error al insertar RAPs en la BD: ' . $e->getMessage();
                                    }
                                } else {
                                    $errors[] = 'El archivo no contiene filas válidas.';
                                }
                            }
                        }
                    }
                }
            } else {
                $errors[] = 'Por favor, seleccione un archivo válido.';
            }
        }

        $this->render(
            BASE_PATH . 'modules/resultados-aprendizaje/views/importar.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'resultados' => $resultados
            ],
            'Importar RAPs · SENA'
        );
    }
}
