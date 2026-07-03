<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ProgramasModel;
use Core\Models\ProyectosModel;
use Core\Services\EstructuraPdfParser;
use PDO;
use Exception;
use PDOException;

class EstructuraController extends BaseController {
    private PDO $db;
    private ProgramasModel $programasModel;
    private ProyectosModel $proyectosModel;
    private EstructuraPdfParser $pdfParser;

    public function __construct(?PDO $db = null, ?ProgramasModel $programasModel = null, ?ProyectosModel $proyectosModel = null, ?EstructuraPdfParser $pdfParser = null) {
        requireRole(ROL_COORDINADOR);
        $this->db = $db ?? Database::getConnection();
        $this->programasModel = $programasModel ?? new ProgramasModel($this->db);
        $this->proyectosModel = $proyectosModel ?? new ProyectosModel($this->db);
        $this->pdfParser = $pdfParser ?? new EstructuraPdfParser();
    }

    /**
     * Dashboard general de la estructura curricular.
     */
    public function index(): void {
        $mensaje = '';
        $tipo_mensaje = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($_POST['action'] === 'eliminar_programa') {
                try {
                    $this->programasModel->delete($id);
                    $mensaje = 'Programa y todas sus competencias y RAs asociadas eliminados correctamente.';
                    $tipo_mensaje = 'success';
                } catch (Exception $e) {
                    $mensaje = $e->getMessage();
                    $tipo_mensaje = 'danger';
                }
            } elseif ($_POST['action'] === 'eliminar_proyecto') {
                try {
                    $this->proyectosModel->delete($id);
                    $mensaje = 'Proyecto formativo y sus fases eliminados correctamente.';
                    $tipo_mensaje = 'success';
                } catch (Exception $e) {
                    $mensaje = $e->getMessage();
                    $tipo_mensaje = 'danger';
                }
            }
        }

        try {
            $numProgramas = $this->db->query("SELECT COUNT(*) FROM programas")->fetchColumn() ?: 0;
            $numCompetencias = $this->db->query("SELECT COUNT(*) FROM competencias")->fetchColumn() ?: 0;
            $numResultados = $this->db->query("SELECT COUNT(*) FROM resultados_aprendizaje")->fetchColumn() ?: 0;
            $numProyectos = $this->db->query("SELECT COUNT(*) FROM proyectos")->fetchColumn() ?: 0;
            $numFases = $this->db->query("SELECT COUNT(*) FROM fases_proyecto")->fetchColumn() ?: 0;

            $programas = $this->programasModel->getAll();
            $proyectos = $this->proyectosModel->getAll();
        } catch (Exception $e) {
            $mensaje = 'Error al cargar los datos de la estructura: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
            $numProgramas = 0;
            $numCompetencias = 0;
            $numResultados = 0;
            $numProyectos = 0;
            $numFases = 0;
            $programas = [];
            $proyectos = [];
        }

        $this->render(
            BASE_PATH . 'modules/estructura/views/index.view.php',
            [
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'numProgramas' => $numProgramas,
                'numCompetencias' => $numCompetencias,
                'numResultados' => $numResultados,
                'numProyectos' => $numProyectos,
                'numFases' => $numFases,
                'programas' => $programas,
                'proyectos' => $proyectos
            ],
            'Estructura Curricular · SENA'
        );
    }

    /**
     * Edición de un programa formativo.
     */
    public function editPrograma(): void {
        $id = (int)($_GET['id'] ?? 0);
        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];

        $programa = null;
        if ($id > 0) {
            try {
                $programa = $this->programasModel->findById($id);
                if (!$programa) {
                    $errors[] = 'Programa no encontrado';
                }
            } catch (Exception $e) {
                $errors[] = 'Error al cargar el programa';
            }
        } else {
            $errors[] = 'ID de programa inválido';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $programa) {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $duracion_horas = (int)($_POST['duracion_horas'] ?? 0);
            $estado = $_POST['estado'] ?? 'activo';

            if (empty($nombre)) {
                $errors[] = 'El nombre del programa es requerido';
            } elseif (mb_strlen($nombre) > 200) {
                $errors[] = 'El nombre del programa no puede exceder los 200 caracteres';
            }

            if (empty($codigo)) {
                $errors[] = 'El código del programa es requerido';
            } elseif (mb_strlen($codigo) > 50) {
                $errors[] = 'El código del programa no puede exceder los 50 caracteres';
            }

            if ($duracion_horas <= 0) {
                $errors[] = 'La duración en horas debe ser un número positivo';
            }

            if (empty($errors)) {
                try {
                    $data = [
                        'nombre' => $nombre,
                        'codigo' => $codigo,
                        'duracion_horas' => $duracion_horas,
                        'estado' => $estado
                    ];
                    $this->programasModel->update($id, $data);
                    $mensaje = 'Programa actualizado correctamente';
                    $tipo_mensaje = 'success';
                    $programa = array_merge($programa, $data);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $this->render(
            BASE_PATH . 'modules/estructura/views/editar_programa.view.php',
            [
                'programa' => $programa,
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'errors' => $errors
            ],
            'Editar Programa · SENA'
        );
    }

    /**
     * Edición de un proyecto formativo.
     */
    public function editProyecto(): void {
        $id = (int)($_GET['id'] ?? 0);
        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];

        $proyecto = null;
        if ($id > 0) {
            try {
                $proyecto = $this->proyectosModel->findById($id);
                if (!$proyecto) {
                    $errors[] = 'Proyecto no encontrado';
                }
            } catch (Exception $e) {
                $errors[] = 'Error al cargar el proyecto';
            }
        } else {
            $errors[] = 'ID de proyecto inválido';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proyecto) {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $objetivo = trim($_POST['objetivo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $estado = $_POST['estado'] ?? 'activo';

            if (empty($nombre)) {
                $errors[] = 'El nombre del proyecto es requerido';
            }

            if (empty($codigo)) {
                $errors[] = 'El código del proyecto es requerido';
            }

            if (empty($errors)) {
                try {
                    $data = [
                        'nombre' => $nombre,
                        'codigo' => $codigo,
                        'objetivo' => $objetivo,
                        'descripcion' => $descripcion,
                        'estado' => $estado
                    ];
                    $this->proyectosModel->update($id, $data);
                    $mensaje = 'Proyecto formativo actualizado correctamente';
                    $tipo_mensaje = 'success';
                    $proyecto = array_merge($proyecto, $data);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $this->render(
            BASE_PATH . 'modules/estructura/views/editar_proyecto.view.php',
            [
                'proyecto' => $proyecto,
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'errors' => $errors
            ],
            'Editar Proyecto · SENA'
        );
    }

    /**
     * Importador de Estructuras Curriculares desde PDF.
     */
    public function import(): void {
        $error = '';
        $success = '';
        $preview_mode = false;
        $parsed_estructura = null;
        $parsed_proyecto = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'confirmar') {
                $importData = $_SESSION['pending_import'] ?? null;
                if (!$importData) {
                    $error = 'No hay datos pendientes de importación. Intenta de nuevo.';
                } else {
                    try {
                        $this->db->beginTransaction();
                        
                        $progId = null;
                        $projId = null;
                        
                        // 1. Registrar / Actualizar Programa
                        if (!empty($importData['estructura'])) {
                            $est = $importData['estructura'];
                            $stmt = $this->db->prepare("SELECT id FROM programas WHERE codigo = ?");
                            $stmt->execute([$est['programa_codigo']]);
                            $progId = $stmt->fetchColumn();
                            
                            if ($progId) {
                                $stmtUpdate = $this->db->prepare("UPDATE programas SET nombre = ?, duracion_horas = ? WHERE id = ?");
                                $stmtUpdate->execute([$est['programa_nombre'], $est['programa_duracion'], $progId]);
                                $progId = (int)$progId;
                            } else {
                                $stmtInsert = $this->db->prepare("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES (?, ?, ?, 'activo')");
                                $stmtInsert->execute([$est['programa_nombre'], $est['programa_codigo'], $est['programa_duracion']]);
                                $progId = (int)$this->db->lastInsertId();
                            }
                            
                            // Registrar Competencias del programa
                            $compDbIds = [];
                            foreach ($est['competencias'] as $c) {
                                $stmtComp = $this->db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
                                $stmtComp->execute([$progId, $c['codigo']]);
                                $compId = $stmtComp->fetchColumn();
                                
                                $duracionHoras = null;
                                if (preg_match('/(\d+)/', $c['duracion'], $dm)) {
                                    $duracionHoras = (int)$dm[1];
                                }
                                
                                if ($compId) {
                                    $stmtUpdateComp = $this->db->prepare("UPDATE competencias SET nombre = ?, horas = ? WHERE id = ?");
                                    $stmtUpdateComp->execute([$c['nombre'], $duracionHoras, $compId]);
                                    $compDbIds[$c['codigo']] = (int)$compId;
                                } else {
                                    $stmtInsertComp = $this->db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, horas, estado) VALUES (?, ?, ?, ?, 'activo')");
                                    $stmtInsertComp->execute([$progId, $c['codigo'], $c['nombre'], $duracionHoras]);
                                    $compDbIds[$c['codigo']] = (int)$this->db->lastInsertId();
                                }
                                
                                // Registrar Resultados del programa
                                foreach ($c['resultados'] as $ra) {
                                    $raCodigo = $c['codigo'] . '-' . str_pad((string)$ra['numero'], 2, '0', STR_PAD_LEFT);
                                    
                                    $stmtRa = $this->db->prepare("SELECT id FROM resultados_aprendizaje WHERE competencia_id = ? AND codigo = ?");
                                    $stmtRa->execute([$compDbIds[$c['codigo']], $raCodigo]);
                                    $raId = $stmtRa->fetchColumn();
                                    
                                    if ($raId) {
                                        $stmtUpdateRa = $this->db->prepare("UPDATE resultados_aprendizaje SET denominacion = ? WHERE id = ?");
                                        $stmtUpdateRa->execute([$ra['denominacion'], $raId]);
                                    } else {
                                        $stmtInsertRa = $this->db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
                                        $stmtInsertRa->execute([$compDbIds[$c['codigo']], $raCodigo, $ra['denominacion']]);
                                    }
                                }
                            }
                        }
                        
                        // 2. Registrar / Actualizar Proyecto y Fases
                        if (!empty($importData['proyecto'])) {
                            $proj = $importData['proyecto'];
                            
                            if (!$progId && !empty($proj['programa_codigo'])) {
                                $stmt = $this->db->prepare("SELECT id FROM programas WHERE codigo = ?");
                                $stmt->execute([$proj['programa_codigo']]);
                                $progId = $stmt->fetchColumn();
                                if ($progId) {
                                    $progId = (int)$progId;
                                } else {
                                    $stmtInsertProg = $this->db->prepare("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES (?, ?, ?, 'activo')");
                                    $stmtInsertProg->execute([
                                        $proj['programa_nombre'] ?: 'Programa de Formación',
                                        $proj['programa_codigo'],
                                        0
                                    ]);
                                    $progId = (int)$this->db->lastInsertId();
                                }
                            }
                            
                            $stmtProj = $this->db->prepare("SELECT id FROM proyectos WHERE codigo = ?");
                            $stmtProj->execute([$proj['proyecto_codigo']]);
                            $projId = $stmtProj->fetchColumn();
                            
                            if ($projId) {
                                $stmtUpdateProj = $this->db->prepare("UPDATE proyectos SET nombre = ?, objetivo = ? WHERE id = ?");
                                $stmtUpdateProj->execute([$proj['proyecto_nombre'], $proj['proyecto_objetivo'], $projId]);
                                $projId = (int)$projId;
                            } else {
                                $stmtInsertProj = $this->db->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, estado) VALUES (?, ?, ?, 'activo')");
                                $stmtInsertProj->execute([$proj['proyecto_nombre'], $proj['proyecto_codigo'], $proj['proyecto_objetivo']]);
                                $projId = (int)$this->db->lastInsertId();
                            }
                            
                            // Registrar fases
                            $faseDbIds = [];
                            $ordenFases = ['ANÁLISIS' => 1, 'PLANEACIÓN' => 2, 'EJECUCIÓN' => 3, 'EVALUACIÓN' => 4];
                            foreach ($proj['fases'] as $faseName) {
                                $numeroFase = $ordenFases[mb_strtoupper($faseName)] ?? 0;
                                $stmtFase = $this->db->prepare("SELECT id FROM fases_proyecto WHERE proyecto_id = ? AND numero_fase = ?");
                                $stmtFase->execute([$projId, $numeroFase]);
                                $faseId = $stmtFase->fetchColumn();
                                
                                if ($faseId) {
                                    $stmtUpdateFase = $this->db->prepare("UPDATE fases_proyecto SET nombre = ? WHERE id = ?");
                                    $stmtUpdateFase->execute([ucfirst(mb_strtolower($faseName)), $faseId]);
                                    $faseDbIds[$faseName] = (int)$faseId;
                                } else {
                                    $stmtInsertFase = $this->db->prepare("INSERT INTO fases_proyecto (proyecto_id, nombre, numero_fase, descripcion) VALUES (?, ?, ?, ?)");
                                    $stmtInsertFase->execute([$projId, ucfirst(mb_strtolower($faseName)), $numeroFase, "Fase de $faseName del proyecto formativo"]);
                                    $faseDbIds[$faseName] = (int)$this->db->lastInsertId();
                                }
                            }
                            
                            // Registrar mapeo de competencias y RAs del proyecto
                            if ($progId) {
                                $compProjDbIds = [];
                                foreach ($proj['competencias'] as $cCode => $cName) {
                                    $stmtComp = $this->db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
                                    $stmtComp->execute([$progId, $cCode]);
                                    $compId = $stmtComp->fetchColumn();
                                    
                                    if ($compId) {
                                        $compProjDbIds[$cCode] = (int)$compId;
                                    } else {
                                        $stmtInsertComp = $this->db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, estado) VALUES (?, ?, ?, 'activo')");
                                        $stmtInsertComp->execute([$progId, $cCode, $cName]);
                                        $compProjDbIds[$cCode] = (int)$this->db->lastInsertId();
                                    }
                                }
                                
                                foreach ($proj['resultados'] as $ra) {
                                    if (!isset($compProjDbIds[$ra['competencia_code']])) continue;
                                    $compId = $compProjDbIds[$ra['competencia_code']];
                                    $raCodigo = $ra['ra_code'] . '-' . str_pad((string)$ra['ra_num'], 2, '0', STR_PAD_LEFT);
                                    
                                    $stmtRa = $this->db->prepare("SELECT id FROM resultados_aprendizaje WHERE competencia_id = ? AND codigo = ?");
                                    $stmtRa->execute([$compId, $raCodigo]);
                                    $raId = $stmtRa->fetchColumn();
                                    
                                    if ($raId) {
                                        $stmtUpdateRa = $this->db->prepare("UPDATE resultados_aprendizaje SET denominacion = ? WHERE id = ?");
                                        $stmtUpdateRa->execute([$ra['denominacion'], $raId]);
                                    } else {
                                        $stmtInsertRa = $this->db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
                                        $stmtInsertRa->execute([$compId, $raCodigo, $ra['denominacion']]);
                                    }
                                }
                            }
                        }
                        
                        $this->db->commit();
                        $success = '¡La estructura curricular y el proyecto formativo se han importado y registrado correctamente en la base de datos!';
                        unset($_SESSION['pending_import']);
                    } catch (Exception $e) {
                        if ($this->db->inTransaction()) $this->db->rollBack();
                        $error = 'Error durante la inserción en base de datos: ' . $e->getMessage();
                    }
                }
            } elseif (isset($_POST['action']) && $_POST['action'] === 'cancelar') {
                unset($_SESSION['pending_import']);
                $this->redirect(APP_URL . '/index.php/estructura/importar');
            } else {
                // Analizar archivos subidos
                try {
                    $hasEstructura = isset($_FILES['pdf_estructura']) && $_FILES['pdf_estructura']['error'] === UPLOAD_ERR_OK;
                    $hasProyecto = isset($_FILES['pdf_proyecto']) && $_FILES['pdf_proyecto']['error'] === UPLOAD_ERR_OK;
                    
                    if (!$hasEstructura && !$hasProyecto) {
                        throw new Exception('Debes subir al menos un archivo PDF de estructura curricular o de proyecto formativo.');
                    }
                    
                    $pending_import = [
                        'estructura' => null,
                        'proyecto' => null
                    ];
                    
                    if ($hasEstructura) {
                        $file = $_FILES['pdf_estructura']['tmp_name'];
                        $text = $this->pdfParser->extractText($file);
                        if (empty(trim($text))) {
                            throw new Exception('No se pudo extraer texto del PDF de Estructura Curricular. Verifique si es una imagen escaneada.');
                        }
                        $parsed_estructura = $this->pdfParser->parseEstructuraCurricular($text);
                        $pending_import['estructura'] = $parsed_estructura;
                    }

                    if ($hasProyecto) {
                        $file = $_FILES['pdf_proyecto']['tmp_name'];
                        $text = $this->pdfParser->extractText($file);
                        if (empty(trim($text))) {
                            throw new Exception('No se pudo extraer texto del PDF de Proyecto Formativo. Verifique si es una imagen escaneada.');
                        }
                        $parsed_proyecto = $this->pdfParser->parseProyectoFormativo($text);
                        $pending_import['proyecto'] = $parsed_proyecto;
                    }
                    
                    $_SESSION['pending_import'] = $pending_import;
                    $preview_mode = true;
                    
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        } else {
            // GET request: si hay algo pendiente en la sesión, mostrar previsualización
            if (!empty($_SESSION['pending_import'])) {
                $parsed_estructura = $_SESSION['pending_import']['estructura'];
                $parsed_proyecto = $_SESSION['pending_import']['proyecto'];
                $preview_mode = true;
            }
        }

        $this->render(
            BASE_PATH . 'modules/estructura/views/importar.view.php',
            [
                'error' => $error,
                'success' => $success,
                'preview_mode' => $preview_mode,
                'parsed_estructura' => $parsed_estructura,
                'parsed_proyecto' => $parsed_proyecto
            ],
            'Importar Estructura · SENA'
        );
    }
}
