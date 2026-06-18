<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\CompetenciasModel;
use Core\Models\ProgramasModel;
use Core\XlsxParser;
use PDO;
use Exception;

class CompetenciasController extends BaseController {
    private PDO $db;
    private CompetenciasModel $competenciasModel;
    private ProgramasModel $programasModel;

    public function __construct(?PDO $db = null, ?CompetenciasModel $competenciasModel = null, ?ProgramasModel $programasModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->competenciasModel = $competenciasModel ?? new CompetenciasModel($this->db);
        $this->programasModel = $programasModel ?? new ProgramasModel($this->db);
    }

    /**
     * Gestión y listado de competencias.
     */
    public function index(): void {
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

        $errors = [];
        $successMessage = '';

        // Procesar formulario de creación de competencia
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
            if (!hasRole(ROL_COORDINADOR)) {
                $errors[] = 'Solo los coordinadores pueden registrar competencias.';
            } else {
                $programa_id = (int)($_POST['programa_id'] ?? 0);
                $nombre = trim($_POST['nombre'] ?? '');
                $codigo = trim($_POST['codigo'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $horas = (int)($_POST['horas'] ?? 0);
                $estado = $_POST['estado'] ?? 'activo';

                if ($programa_id <= 0) $errors[] = 'Debe seleccionar un programa válido.';
                if (empty($nombre)) $errors[] = 'El nombre de la competencia es obligatorio.';
                if (empty($codigo)) $errors[] = 'El código de la competencia es obligatorio.';
                if ($horas <= 0) $errors[] = 'La duración en horas debe ser mayor a 0.';

                if (empty($errors)) {
                    try {
                        $this->competenciasModel->create([
                            'programa_id' => $programa_id,
                            'nombre' => $nombre,
                            'codigo' => $codigo,
                            'descripcion' => $descripcion,
                            'horas' => $horas,
                            'estado' => $estado
                        ]);
                        $successMessage = 'Competencia registrada exitosamente.';
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Procesar edición de competencia
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
            if (!hasRole(ROL_COORDINADOR)) {
                $errors[] = 'Solo los coordinadores pueden editar competencias.';
            } else {
                $id          = (int)($_POST['id'] ?? 0);
                $programa_id = (int)($_POST['programa_id'] ?? 0);
                $nombre      = trim($_POST['nombre'] ?? '');
                $codigo      = trim($_POST['codigo'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $horas       = (int)($_POST['horas'] ?? 0);
                $estado      = $_POST['estado'] ?? 'activo';

                if ($id <= 0)          $errors[] = 'Competencia no válida.';
                if ($programa_id <= 0) $errors[] = 'Debe seleccionar un programa válido.';
                if (empty($nombre))    $errors[] = 'El nombre de la competencia es obligatorio.';
                if (empty($codigo))    $errors[] = 'El código de la competencia es obligatorio.';
                if ($horas <= 0)       $errors[] = 'La duración en horas debe ser mayor a 0.';

                if (empty($errors)) {
                    try {
                        $this->competenciasModel->update($id, [
                            'programa_id' => $programa_id,
                            'nombre' => $nombre,
                            'codigo' => $codigo,
                            'descripcion' => $descripcion,
                            'horas' => $horas,
                            'estado' => $estado
                        ]);
                        $successMessage = 'Competencia actualizada exitosamente.';
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Procesar eliminación de competencia
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
            if (!hasRole(ROL_COORDINADOR)) {
                $errors[] = 'Solo los coordinadores pueden eliminar competencias.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $errors[] = 'Competencia no válida.';
                } else {
                    try {
                        $this->competenciasModel->delete($id);
                        $successMessage = 'Competencia eliminada exitosamente.';
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Obtener programas para los filtros y el formulario
        $programas = [];
        try {
            $programas = $this->programasModel->getAll();
            // Filtrar programas para que solo se muestren los activos en el select
            $programas = array_filter($programas, fn($p) => $p['estado'] === 'activo');
        } catch (Exception $e) {
            $errors[] = 'Error al cargar programas.';
        }

        // Obtener filtros de búsqueda
        $search = trim($_GET['search'] ?? '');
        $filter_programa = (int)($_GET['programa_id'] ?? 0);
        $filter_estado = $_GET['estado'] ?? '';

        $filters = [
            'search' => $search,
            'programa_id' => $filter_programa,
            'estado' => $filter_estado
        ];

        try {
            $competencias = $this->competenciasModel->getFilteredList($filters);
        } catch (Exception $e) {
            $competencias = [];
            $errors[] = $e->getMessage();
        }

        $this->render(
            BASE_PATH . 'modules/competencias/views/index.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'programas' => $programas,
                'search' => $search,
                'filter_programa' => $filter_programa,
                'filter_estado' => $filter_estado,
                'competencias' => $competencias
            ],
            'Gestión de Competencias · SENA'
        );
    }

    /**
     * Importación masiva de competencias.
     */
    public function import(): void {
        requireRole(ROL_COORDINADOR);

        $errors = [];
        $successMessage = '';
        $resultados = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['archivo_competencias']) && $_FILES['archivo_competencias']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['archivo_competencias']['tmp_name'];
                $fileName = $_FILES['archivo_competencias']['name'];
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
                    } else { // xlsx
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
                            array_shift($rows);
                            
                            $linea = 2;
                            $competenciasData = [];
                            
                            // Cargar programas existentes para buscar por código de forma rápida
                            $programasMap = [];
                            try {
                                $p_list = $this->programasModel->getAll();
                                foreach ($p_list as $p) {
                                    $programasMap[strtolower(trim($p['codigo']))] = (int)$p['id'];
                                }
                            } catch (Exception $e) {
                                $errors[] = 'Error al precargar códigos de programas.';
                            }

                            foreach ($rows as $data) {
                                if (empty($data) || (empty($data[0]) && empty($data[1]) && empty($data[2]))) {
                                    $linea++;
                                    continue;
                                }

                                if (count($data) < 4) {
                                    $errors[] = "Línea $linea: Faltan columnas. Se requiere: Código Programa, Código Competencia, Nombre Competencia, Horas.";
                                    $linea++;
                                    continue;
                                }

                                $prog_code = strtolower(trim((string)($data[0] ?? '')));
                                $comp_code = trim((string)($data[1] ?? ''));
                                $comp_name = mb_strtoupper(trim((string)($data[2] ?? '')), 'UTF-8');
                                $horas = (int)($data[3] ?? 0);
                                $descripcion = trim((string)($data[4] ?? ''));

                                $rowErrors = [];
                                if (!isset($programasMap[$prog_code])) {
                                    $rowErrors[] = "Línea $linea: El código de programa '$prog_code' no existe en la base de datos.";
                                }
                                if (empty($comp_code)) {
                                    $rowErrors[] = "Línea $linea: El código de competencia está vacío.";
                                }
                                if (empty($comp_name)) {
                                    $rowErrors[] = "Línea $linea: El nombre de la competencia está vacío.";
                                }
                                if ($horas <= 0) {
                                    $rowErrors[] = "Línea $linea: Las horas deben ser un número positivo mayor que cero.";
                                }

                                if (empty($rowErrors)) {
                                    $competenciasData[] = [
                                        'programa_id' => $programasMap[$prog_code],
                                        'codigo' => $comp_code,
                                        'nombre' => $comp_name,
                                        'horas' => $horas,
                                        'descripcion' => $descripcion
                                    ];
                                } else {
                                    $errors = array_merge($errors, $rowErrors);
                                }
                                $linea++;
                            }

                            if (empty($errors)) {
                                if (count($competenciasData) > 0) {
                                    try {
                                        $this->db->beginTransaction();
                                        
                                        $importedCount = 0;
                                        foreach ($competenciasData as $c) {
                                            $this->competenciasModel->create([
                                                'programa_id' => $c['programa_id'],
                                                'codigo' => $c['codigo'],
                                                'nombre' => $c['nombre'],
                                                'descripcion' => $c['descripcion'],
                                                'horas' => $c['horas'],
                                                'estado' => 'activo'
                                            ]);
                                            $importedCount++;
                                        }

                                        // Registrar log
                                        $logStmt = $this->db->prepare("
                                            INSERT INTO logs_sistema (usuario_id, accion, modulo, tabla_afectada, descripcion)
                                            VALUES (?, 'Importar', 'Competencias', 'competencias', ?)
                                        ");
                                        $logStmt->execute([(int)getCurrentUser()['id'], "Importó masivamente $importedCount competencias académicas"]);

                                        $this->db->commit();
                                        $successMessage = "Se han importado exitosamente $importedCount competencias académicas.";
                                        $resultados = $competenciasData;
                                    } catch (Exception $e) {
                                        if ($this->db->inTransaction()) {
                                            $this->db->rollBack();
                                        }
                                        $errors[] = 'Error al insertar competencias en la BD: ' . $e->getMessage();
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
            BASE_PATH . 'modules/competencias/views/importar.view.php',
            [
                'errors' => $errors,
                'successMessage' => $successMessage,
                'resultados' => $resultados
            ],
            'Importar Competencias · SENA'
        );
    }
}
