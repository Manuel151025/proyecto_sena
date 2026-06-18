<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\ProgramasModel;
use Core\Models\ProyectosModel;
use PDO;
use Exception;
use PDOException;

class EstructuraController extends BaseController {
    private PDO $db;
    private ProgramasModel $programasModel;
    private ProyectosModel $proyectosModel;

    public function __construct(?PDO $db = null, ?ProgramasModel $programasModel = null, ?ProyectosModel $proyectosModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->programasModel = $programasModel ?? new ProgramasModel($this->db);
        $this->proyectosModel = $proyectosModel ?? new ProyectosModel($this->db);
    }

    /**
     * Dashboard general de la estructura curricular.
     */
    public function index(): void {
        requireRole(ROL_COORDINADOR);

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
        requireRole(ROL_COORDINADOR);

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
        requireRole(ROL_COORDINADOR);

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
        requireRole(ROL_COORDINADOR);

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
                                    $faseDbIds[$faseName] = (int)$db->lastInsertId();
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
                                        $compProjDbIds[$cCode] = (int)$db->lastInsertId();
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
                        $text = $this->extractPdfText($file);
                        if (empty(trim($text))) {
                            throw new Exception('No se pudo extraer texto del PDF de Estructura Curricular. Verifique si es una imagen escaneada.');
                        }
                        $parsed_estructura = $this->parseEstructuraCurricular($text);
                        $pending_import['estructura'] = $parsed_estructura;
                    }
                    
                    if ($hasProyecto) {
                        $file = $_FILES['pdf_proyecto']['tmp_name'];
                        $text = $this->extractPdfText($file);
                        if (empty(trim($text))) {
                            throw new Exception('No se pudo extraer texto del PDF de Proyecto Formativo. Verifique si es una imagen escaneada.');
                        }
                        $parsed_proyecto = $this->parseProyectoFormativo($text);
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

    // ─────────────────────────────────────────────────────────────────────────
    // MÉTODOS AUXILIARES PRIVADOS PARA PARSEO DE PDF
    // ─────────────────────────────────────────────────────────────────────────

    private function extractPdfText(string $filepath): string {
        $content = file_get_contents($filepath);
        if ($content === false) return '';
        
        $texts = [];
        
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $streamMatches)) {
            foreach ($streamMatches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if ($decoded === false) {
                    $decoded = @gzinflate(substr($stream, 2));
                }
                if ($decoded !== false) {
                    if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $decoded, $tjMatches)) {
                        foreach ($tjMatches[1] as $s) {
                            $s = stripslashes($s);
                            if (strlen(trim($s)) > 0) {
                                $texts[] = trim($s);
                            }
                        }
                    }
                    if (preg_match_all('/\[((?:\([^)]*\)|[^]]*)*)\]\s*TJ/s', $decoded, $tjArrays)) {
                        foreach ($tjArrays[1] as $arr) {
                            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $arr, $parts)) {
                                $line = '';
                                foreach ($parts[1] as $p) {
                                    $line .= stripslashes($p);
                                }
                                if (strlen(trim($line)) > 0) {
                                    $texts[] = trim($line);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($texts)) {
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.){2,})\)/', $content, $plainMatches)) {
                foreach ($plainMatches[1] as $s) {
                    $s = stripslashes($s);
                    if (strlen(trim($s)) > 1 && !preg_match('/^[\x00-\x1f]+$/', $s)) {
                        $texts[] = trim($s);
                    }
                }
            }
        }
        
        $res = implode("\n", $texts);
        if (!mb_check_encoding($res, 'UTF-8')) {
            $res = mb_convert_encoding($res, 'UTF-8', 'ISO-8859-1');
        }
        return $res;
    }

    private function parseEstructuraCurricular(string $text): array {
        $lines = explode("\n", $text);
        $competencias = [];
        $current = null;
        $section = '';
        
        $prog_nombre = 'Tecnólogo en Análisis y Desarrollo de Software';
        $prog_codigo = '228118';
        $prog_duracion = 3984;
        
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match('/1\.\s+INFORMACION\s+B[AÁ\?]+SICA/i', $line)) {
                for ($j = $idx - 1; $j >= max(0, $idx - 10); $j--) {
                    $prevLine = trim($lines[$j]);
                    if (!empty($prevLine) && !preg_match('/RED\s+DE\s+CONOCIMIENTO|LINEA\s+TECNOLOGICA/i', $prevLine)) {
                        $prog_nombre = trim($prevLine, " \t\n\r\0\x0B.");
                        break;
                    }
                }
            }
            
            if (preg_match('/C[OÓ\?]+DIGO\s+PROGRAMA/i', $line)) {
                for ($j = $idx + 1; $j < min($idx + 10, count($lines)); $j++) {
                    $val = trim($lines[$j]);
                    if (preg_match('/^\d{6}$/', $val)) {
                        $prog_codigo = $val;
                        break;
                    }
                }
            }
        }
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            if (preg_match('/^4\.tCONTENIDOS CURRICULARES/i', $line)) {
                if ($current) $competencias[] = $current;
                $current = [
                    'norma' => '',
                    'codigo' => '',
                    'nombre' => '',
                    'duracion' => '',
                    'resultados' => []
                ];
                $section = 'inicio';
                continue;
            }
            
            if (!$current) continue;
            
            if (preg_match('/^4\.2\s+C.DIGO/i', $line)) {
                $section = 'codigo';
                continue;
            }
            if ($section === 'codigo' && preg_match('/^(COMPETENCIA|LABORAL)/i', $line)) {
                continue;
            }
            if ($section === 'codigo' && preg_match('/^\d{6,10}$/', $line)) {
                $current['codigo'] = trim($line);
                $section = '';
                continue;
            }
            
            if (preg_match('/^4\.3\s+NOMBRE\s+DE\s+LA/i', $line)) {
                $section = 'nombre';
                continue;
            }
            if ($section === 'nombre' && strtoupper($line) === 'COMPETENCIA') {
                continue;
            }
            if ($section === 'nombre' && strlen($line) > 5 && !preg_match('/^4\.\d/', $line)) {
                $current['nombre'] .= ($current['nombre'] ? ' ' : '') . $line;
                if (isset($lines[$i+1]) && !preg_match('/^4\.\d|^DENOMINACI|^APRENDIZAJE/', trim($lines[$i+1]))) {
                    continue;
                }
                $section = '';
                continue;
            }
            
            if (preg_match('/^4\.4\s+DURACI/i', $line)) {
                $section = 'duracion';
                continue;
            }
            if ($section === 'duracion' && preg_match('/(\d+)\s*horas/i', $line, $m)) {
                $current['duracion'] = $m[1] . ' horas';
                $section = '';
                continue;
            }
            if ($section === 'duracion' && preg_match('/^\d+$/', $line)) {
                $current['duracion'] = $line . ' horas';
                $section = '';
                continue;
            }
            
            if (preg_match('/^4\.5\s+RESULTADOS/i', $line)) {
                $section = 'resultados';
                continue;
            }
            if (preg_match('/^DENOMINACI/i', $line)) {
                continue;
            }
            if ($section === 'resultados') {
                if (preg_match('/^4\.\d|^5\./', $line) || preg_match('/^\d{6,10}$/', $line)) {
                    $section = '';
                    continue;
                }
                
                if (preg_match('/^(\d+)\s*-\s*(.+)$/i', $line, $raM)) {
                    $current['resultados'][] = [
                        'numero' => intval($raM[1]),
                        'denominacion' => trim($raM[2])
                    ];
                } elseif (count($current['resultados']) > 0) {
                    $idx = count($current['resultados']) - 1;
                    $current['resultados'][$idx]['denominacion'] .= ' ' . $line;
                }
            }
        }
        if ($current) {
            $competencias[] = $current;
        }
        
        return [
            'programa_nombre' => $prog_nombre,
            'programa_codigo' => $prog_codigo,
            'programa_duracion' => $prog_duracion,
            'competencias' => $competencias
        ];
    }

    private function parseProyectoFormativo(string $text): array {
        $lines = explode("\n", $text);
        
        $proyecto_nombre = 'Proyecto Formativo';
        $proyecto_codigo = '';
        $proyecto_objetivo = '';
        $programa_nombre = '';
        $programa_codigo = '';
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            if (preg_match('/C[oó\?]+digo\s+Proyecto\s+SOFIA:/i', $line)) {
                if (isset($lines[$i+2]) && preg_match('/^\d+$/', trim($lines[$i+2]))) {
                    $proyecto_codigo = trim($lines[$i+2]);
                } elseif (isset($lines[$i+1]) && preg_match('/^\d+$/', trim($lines[$i+1]))) {
                    $proyecto_codigo = trim($lines[$i+1]);
                }
            }
            
            if (preg_match('/C[oó\?]+digo\s+del\s+Programa\s+SOFIA:/i', $line)) {
                for ($j = max(0, $i - 5); $j < min(count($lines), $i + 6); $j++) {
                    $val = trim($lines[$j]);
                    if (preg_match('/^\d{6,10}$/', $val)) {
                        if ($val !== $proyecto_codigo) {
                            $programa_codigo = $val;
                            break;
                        }
                    }
                }
            }
            
            if (preg_match('/Nombre\s+del\s+proyecto:/i', $line)) {
                if (isset($lines[$i+1])) {
                    $proyecto_nombre = trim($lines[$i+1]);
                    if (isset($lines[$i+2]) && !preg_match('/Programa\s+de/i', $lines[$i+2])) {
                        $proyecto_nombre .= ' ' . trim($lines[$i+2]);
                    }
                }
            }
            
            if (preg_match('/Programa\s+de\s+Formaci[oó\?]+n\s+al/i', $line)) {
                if (isset($lines[$i+1])) {
                    $next_val = trim($lines[$i+1], " \t\n\r\0\x0B.");
                    if (preg_match('/que\s+da\s+respuesta/i', $next_val)) {
                        if (isset($lines[$i+2])) {
                            $programa_nombre = trim($lines[$i+2], " \t\n\r\0\x0B.");
                        }
                    } else {
                        $programa_nombre = $next_val;
                    }
                }
            }
            
            if (preg_match('/Objetivo\s+general/i', $line)) {
                if (isset($lines[$i+1])) {
                    $proyecto_objetivo = trim($lines[$i+1]);
                    if (isset($lines[$i+2]) && !preg_match('/Objetivos\s+espec/i', $lines[$i+2])) {
                        $proyecto_objetivo .= ' ' . trim($lines[$i+2]);
                    }
                }
            }
        }
        
        $fases = [];
        $competencias = [];
        $resultados = [];
        $currentFase = '';
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            if (preg_match('/^(?:\d+\.)?(AN[AÁ\?]+LISIS|PLANEACI[OÓ\?]+N|EJECUCI[OÓ\?]+N|EVALUACI[OÓ\?]+N)$/i', $line, $m)) {
                $phaseMatch = mb_strtoupper($m[1]);
                if (preg_match('/AN[AÁ\?]+LISIS/i', $phaseMatch)) {
                    $currentFase = 'ANÁLISIS';
                } elseif (preg_match('/PLANEACI[OÓ\?]+N/i', $phaseMatch)) {
                    $currentFase = 'PLANEACIÓN';
                } elseif (preg_match('/EJECUCI[OÓ\?]+N/i', $phaseMatch)) {
                    $currentFase = 'EJECUCIÓN';
                } elseif (preg_match('/EVALUACI[OÓ\?]+N/i', $phaseMatch)) {
                    $currentFase = 'EVALUACIÓN';
                }
                
                if (!in_array($currentFase, $fases)) {
                    $fases[] = $currentFase;
                }
                continue;
            }
            
            $isRa = false;
            $raCode = '';
            $raNum = 1;
            $raDenom = '';
            
            if (preg_match('/^(\d{6})\s+-\s+(\d{2})\s+(.+)$/i', $line, $raMatch)) {
                $isRa = true;
                $raCode = $raMatch[1];
                $raNum = intval($raMatch[2]);
                $raDenom = trim($raMatch[3]);
            }
            elseif (preg_match('/^(\d{6})\s+-\s+(.+)$/i', $line, $raMatch)) {
                $isRa = true;
                $raCode = $raMatch[1];
                $raNum = 1;
                $raDenom = trim($raMatch[2]);
            }
            
            if ($isRa) {
                while (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    if (preg_match('/^(\d{6,9})\s+-\s+(.+)$/i', $nextLine)) {
                        break;
                    }
                    if (empty($nextLine) || preg_match('/^(?:\d+\.)?(AN[AÁ\?]+LISIS|PLANEACI|EJECUCI|EVALUACI|P[áa\?]+gina \d)/i', $nextLine)) {
                        break;
                    }
                    $raDenom .= ' ' . $nextLine;
                    $i++;
                }
                
                $compCode = '';
                $compName = '';
                for ($j = $i + 1; $j < min($i + 8, count($lines)); $j++) {
                    $cl = trim($lines[$j]);
                    if (preg_match('/^(\d{7,10})\s+-\s+(.+)$/i', $cl, $compMatch)) {
                        $compCode = $compMatch[1];
                        $compName = trim($compMatch[2]);
                        while (isset($lines[$j + 1])) {
                            $nl = trim($lines[$j + 1]);
                            if (empty($nl) || preg_match('/^(?:\d+\.)?(AN[AÁ\?]+LISIS|PLANEACI|EJECUCI|EVALUACI|P[áa\?]+gina \d|\d{6})/i', $nl)) {
                                break;
                            }
                            $compName .= ' ' . $nl;
                            $j++;
                        }
                        $i = $j;
                        break;
                    }
                }
                
                if ($compCode && !isset($competencias[$compCode])) {
                    $competencias[$compCode] = $compName;
                }
                
                $resultados[] = [
                    'ra_code' => $raCode,
                    'ra_num' => $raNum,
                    'denominacion' => $raDenom,
                    'competencia_code' => $compCode,
                    'fase' => $currentFase
                ];
            }
        }
        
        return [
            'proyecto_nombre' => $proyecto_nombre,
            'proyecto_codigo' => $proyecto_codigo,
            'proyecto_objetivo' => $proyecto_objetivo,
            'programa_nombre' => $programa_nombre,
            'programa_codigo' => $programa_codigo,
            'fases' => $fases,
            'competencias' => $competencias,
            'resultados' => $resultados
        ];
    }
}
