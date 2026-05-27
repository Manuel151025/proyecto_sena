<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

$pageTitle = 'Importar Estructura · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

$db = Database::getConnection();
$error = '';
$success = '';
$preview_mode = false;
$parsed_estructura = null;
$parsed_proyecto = null;

// Función para extraer texto de un PDF sin librerías externas
if (!function_exists('extractPdfText')) {
function extractPdfText(string $filepath): string {
    $content = file_get_contents($filepath);
    if ($content === false) return '';
    
    $texts = [];
    
    // Buscar streams comprimidos
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
                // Tj show text
                if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $decoded, $tjMatches)) {
                    foreach ($tjMatches[1] as $s) {
                        $s = stripslashes($s);
                        if (strlen(trim($s)) > 0) {
                            $texts[] = trim($s);
                        }
                    }
                }
                
                // TJ arrays
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
    
    // Si no se pudo descomprimir, intentar texto plano
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
}

// Función para parsear la estructura curricular
if (!function_exists('parseEstructuraCurricular')) {
function parseEstructuraCurricular(string $text): array {
    $lines = explode("\n", $text);
    $competencias = [];
    $current = null;
    $section = '';
    
    $prog_nombre = 'Tecnólogo en Análisis y Desarrollo de Software';
    $prog_codigo = '228118';
    $prog_duracion = 3984; // Duración total por defecto (Etapa Lectiva + Productiva)
    
    // Buscar datos del programa
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
    
    // Parsear Competencias y RAs
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
            $section = 'ra_items';
            continue;
        }
        if ($section === 'ra_items') {
            if (preg_match('/^(\d+)\s+(.+)/', $line, $m)) {
                $current['resultados'][] = [
                    'numero' => intval($m[1]),
                    'denominacion' => trim($m[2])
                ];
                continue;
            }
            if (!empty($current['resultados']) && !preg_match('/^4\.\d|^APRENDIZAJE/', $line)) {
                $last = count($current['resultados']) - 1;
                $current['resultados'][$last]['denominacion'] .= ' ' . $line;
                continue;
            }
        }
        
        if (preg_match('/^4\.6\s+CONOCIMIENTOS/i', $line)) {
            $section = '';
        }
        
        if (preg_match('/^4\.1\s+NORMA/i', $line)) {
            $section = 'norma';
            continue;
        }
        if ($section === 'norma' && $line !== 'COMPETENCIA' && strlen($line) > 3 && !preg_match('/^4\.\d/', $line)) {
            $current['norma'] = $line;
            $section = '';
            continue;
        }
    }
    
    if ($current) $competencias[] = $current;
    
    return [
        'programa_nombre' => $prog_nombre,
        'programa_codigo' => $prog_codigo,
        'programa_duracion' => $prog_duracion,
        'competencias' => $competencias
    ];
}
}

// Función para parsear el reporte de proyecto formativo
if (!function_exists('parseProyectoFormativo')) {
function parseProyectoFormativo(string $text): array {
    $lines = explode("\n", $text);
    
    $proyecto_nombre = 'Proyecto Formativo';
    $proyecto_codigo = '';
    $proyecto_objetivo = '';
    $programa_nombre = '';
    $programa_codigo = '';
    
    // Buscar información básica del proyecto
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
            // Check up to 5 lines above and 5 lines below to find program code (6 to 10 digits)
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
    $competencias = []; // codigo => nombre
    $resultados = [];   // ra_code => [numero, denominacion, competencia_codigo, fase]
    $currentFase = '';
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        // Match phase supporting encoding question mark replacements (flexible prefix e.g. "1.ANÁLISIS")
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
        
        // Try matching format A: 6 digits - 2 digits - description
        if (preg_match('/^(\d{6})\s+-\s+(\d{2})\s+(.+)$/i', $line, $raMatch)) {
            $isRa = true;
            $raCode = $raMatch[1];
            $raNum = intval($raMatch[2]);
            $raDenom = trim($raMatch[3]);
        }
        // Try matching format B: 6 digits - description
        elseif (preg_match('/^(\d{6})\s+-\s+(.+)$/i', $line, $raMatch)) {
            $isRa = true;
            $raCode = $raMatch[1];
            $raNum = 1;
            $raDenom = trim($raMatch[2]);
        }
        
        if ($isRa) {
            // Read continuing description lines for the RA
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
                // Competencies have 7-10 digits codes
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

// ─────────────────────────────────────────────────────────────────────────
// PROCESAR FORMULARIO DE CONFIRMACIÓN O ANALISIS
// ─────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CASO 1: Confirmar importación en la Base de Datos
    if (isset($_POST['action']) && $_POST['action'] === 'confirmar') {
        $importData = $_SESSION['pending_import'] ?? null;
        if (!$importData) {
            $error = 'No hay datos pendientes de importación. Intenta de nuevo.';
        } else {
            try {
                $db->beginTransaction();
                
                $progId = null;
                $projId = null;
                
                // 1. Registrar / Actualizar Programa
                if (!empty($importData['estructura'])) {
                    $est = $importData['estructura'];
                    $stmt = $db->prepare("SELECT id FROM programas WHERE codigo = ?");
                    $stmt->execute([$est['programa_codigo']]);
                    $progId = $stmt->fetchColumn();
                    
                    if ($progId) {
                        $stmtUpdate = $db->prepare("UPDATE programas SET nombre = ?, duracion_horas = ? WHERE id = ?");
                        $stmtUpdate->execute([$est['programa_nombre'], $est['programa_duracion'], $progId]);
                        $progId = (int)$progId;
                    } else {
                        $stmtInsert = $db->prepare("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES (?, ?, ?, 'activo')");
                        $stmtInsert->execute([$est['programa_nombre'], $est['programa_codigo'], $est['programa_duracion']]);
                        $progId = (int)$db->lastInsertId();
                    }
                    
                    // Registrar Competencias del programa
                    $compDbIds = [];
                    foreach ($est['competencias'] as $c) {
                        $stmtComp = $db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
                        $stmtComp->execute([$progId, $c['codigo']]);
                        $compId = $stmtComp->fetchColumn();
                        
                        // Limpiar duración (ej: "480 horas" -> 480)
                        $duracionHoras = null;
                        if (preg_match('/(\d+)/', $c['duracion'], $dm)) {
                            $duracionHoras = (int)$dm[1];
                        }
                        
                        if ($compId) {
                            $stmtUpdateComp = $db->prepare("UPDATE competencias SET nombre = ?, horas = ? WHERE id = ?");
                            $stmtUpdateComp->execute([$c['nombre'], $duracionHoras, $compId]);
                            $compDbIds[$c['codigo']] = (int)$compId;
                        } else {
                            $stmtInsertComp = $db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, horas, estado) VALUES (?, ?, ?, ?, 'activo')");
                            $stmtInsertComp->execute([$progId, $c['codigo'], $c['nombre'], $duracionHoras]);
                            $compDbIds[$c['codigo']] = (int)$db->lastInsertId();
                        }
                        
                        // Registrar Resultados del programa
                        foreach ($c['resultados'] as $ra) {
                            $raCodigo = $c['codigo'] . '-' . str_pad((string)$ra['numero'], 2, '0', STR_PAD_LEFT);
                            
                            $stmtRa = $db->prepare("SELECT id FROM resultados_aprendizaje WHERE competencia_id = ? AND codigo = ?");
                            $stmtRa->execute([$compDbIds[$c['codigo']], $raCodigo]);
                            $raId = $stmtRa->fetchColumn();
                            
                            if ($raId) {
                                $stmtUpdateRa = $db->prepare("UPDATE resultados_aprendizaje SET denominacion = ? WHERE id = ?");
                                $stmtUpdateRa->execute([$ra['denominacion'], $raId]);
                            } else {
                                $stmtInsertRa = $db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
                                $stmtInsertRa->execute([$compDbIds[$c['codigo']], $raCodigo, $ra['denominacion']]);
                            }
                        }
                    }
                }
                
                // 2. Registrar / Actualizar Proyecto y Fases
                if (!empty($importData['proyecto'])) {
                    $proj = $importData['proyecto'];
                    
                    // Si el programa no fue importado antes, pero tenemos su código desde el proyecto
                    if (!$progId && !empty($proj['programa_codigo'])) {
                        $stmt = $db->prepare("SELECT id FROM programas WHERE codigo = ?");
                        $stmt->execute([$proj['programa_codigo']]);
                        $progId = $stmt->fetchColumn();
                        if ($progId) {
                            $progId = (int)$progId;
                        } else {
                            $stmtInsertProg = $db->prepare("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES (?, ?, ?, 'activo')");
                            $stmtInsertProg->execute([
                                $proj['programa_nombre'] ?: 'Programa de Formación',
                                $proj['programa_codigo'],
                                0
                            ]);
                            $progId = (int)$db->lastInsertId();
                        }
                    }
                    
                    $stmtProj = $db->prepare("SELECT id FROM proyectos WHERE codigo = ?");
                    $stmtProj->execute([$proj['proyecto_codigo']]);
                    $projId = $stmtProj->fetchColumn();
                    
                    if ($projId) {
                        $stmtUpdateProj = $db->prepare("UPDATE proyectos SET nombre = ?, objetivo = ? WHERE id = ?");
                        $stmtUpdateProj->execute([$proj['proyecto_nombre'], $proj['proyecto_objetivo'], $projId]);
                        $projId = (int)$projId;
                    } else {
                        $stmtInsertProj = $db->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, estado) VALUES (?, ?, ?, 'activo')");
                        $stmtInsertProj->execute([$proj['proyecto_nombre'], $proj['proyecto_codigo'], $proj['proyecto_objetivo']]);
                        $projId = (int)$db->lastInsertId();
                    }
                    
                    // Registrar fases del proyecto
                    $faseDbIds = [];
                    $ordenFases = ['ANÁLISIS' => 1, 'PLANEACIÓN' => 2, 'EJECUCIÓN' => 3, 'EVALUACIÓN' => 4];
                    foreach ($proj['fases'] as $faseName) {
                        $numeroFase = $ordenFases[mb_strtoupper($faseName)] ?? 0;
                        $stmtFase = $db->prepare("SELECT id FROM fases_proyecto WHERE proyecto_id = ? AND numero_fase = ?");
                        $stmtFase->execute([$projId, $numeroFase]);
                        $faseId = $stmtFase->fetchColumn();
                        
                        if ($faseId) {
                            $stmtUpdateFase = $db->prepare("UPDATE fases_proyecto SET nombre = ? WHERE id = ?");
                            $stmtUpdateFase->execute([ucfirst(mb_strtolower($faseName)), $faseId]);
                            $faseDbIds[$faseName] = (int)$faseId;
                        } else {
                            $stmtInsertFase = $db->prepare("INSERT INTO fases_proyecto (proyecto_id, nombre, numero_fase, descripcion) VALUES (?, ?, ?, ?)");
                            $stmtInsertFase->execute([$projId, ucfirst(mb_strtolower($faseName)), $numeroFase, "Fase de $faseName del proyecto formativo"]);
                            $faseDbIds[$faseName] = (int)$db->lastInsertId();
                        }
                    }
                    
                    // Si el PDF del proyecto tiene mapeo de competencias y RAs, e importamos el programa
                    if ($progId) {
                        $compProjDbIds = [];
                        foreach ($proj['competencias'] as $cCode => $cName) {
                            $stmtComp = $db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
                            $stmtComp->execute([$progId, $cCode]);
                            $compId = $stmtComp->fetchColumn();
                            
                            if ($compId) {
                                $compProjDbIds[$cCode] = (int)$compId;
                            } else {
                                $stmtInsertComp = $db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, estado) VALUES (?, ?, ?, 'activo')");
                                $stmtInsertComp->execute([$progId, $cCode, $cName]);
                                $compProjDbIds[$cCode] = (int)$db->lastInsertId();
                            }
                        }
                        
                        foreach ($proj['resultados'] as $ra) {
                            if (!isset($compProjDbIds[$ra['competencia_code']])) continue;
                            $compId = $compProjDbIds[$ra['competencia_code']];
                            $raCodigo = $ra['ra_code'] . '-' . str_pad((string)$ra['ra_num'], 2, '0', STR_PAD_LEFT);
                            
                            $stmtRa = $db->prepare("SELECT id FROM resultados_aprendizaje WHERE competencia_id = ? AND codigo = ?");
                            $stmtRa->execute([$compId, $raCodigo]);
                            $raId = $stmtRa->fetchColumn();
                            
                            if ($raId) {
                                $stmtUpdateRa = $db->prepare("UPDATE resultados_aprendizaje SET denominacion = ? WHERE id = ?");
                                $stmtUpdateRa->execute([$ra['denominacion'], $raId]);
                            } else {
                                $stmtInsertRa = $db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
                                $stmtInsertRa->execute([$compId, $raCodigo, $ra['denominacion']]);
                            }
                        }
                    }
                }
                
                $db->commit();
                $success = '¡La estructura curricular y el proyecto formativo se han importado y registrado correctamente en la base de datos!';
                unset($_SESSION['pending_import']);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Error durante la inserción en base de datos: ' . $e->getMessage();
            }
        }
    }
    
    // CASO 2: Cancelar la previsualización y volver a empezar
    elseif (isset($_POST['action']) && $_POST['action'] === 'cancelar') {
        unset($_SESSION['pending_import']);
        header('Location: ' . MODULES_PATH . '/estructura/importar.php');
        exit;
    }
    
    // CASO 3: Cargar y analizar archivos PDFs
    else {
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
                $text = extractPdfText($file);
                if (empty(trim($text))) {
                    throw new Exception('No se pudo extraer texto del PDF de Estructura Curricular. Verifique si es una imagen escaneada.');
                }
                $parsed_estructura = parseEstructuraCurricular($text);
                $pending_import['estructura'] = $parsed_estructura;
            }
            
            if ($hasProyecto) {
                $file = $_FILES['pdf_proyecto']['tmp_name'];
                $text = extractPdfText($file);
                if (empty(trim($text))) {
                    throw new Exception('No se pudo extraer texto del PDF de Proyecto Formativo. Verifique si es una imagen escaneada.');
                }
                $parsed_proyecto = parseProyectoFormativo($text);
                $pending_import['proyecto'] = $parsed_proyecto;
            }
            
            $_SESSION['pending_import'] = $pending_import;
            $preview_mode = true;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Importar Datos Académicos</h1>
    <p class="text-muted mb-0">Carga los documentos PDF para estructurar automáticamente tu programa de formación.</p>
  </div>
  <div>
    <a href="<?= MODULES_PATH ?>/estructura/" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver al Módulo
    </a>
  </div>
</div>

<?php if ($error): ?>
<div class="alert-flat danger mb-4">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div><?= htmlspecialchars($error) ?></div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-flat success mb-4">
  <i class="bi bi-check-circle-fill"></i>
  <div><?= htmlspecialchars($success) ?></div>
</div>
<div class="card p-4 text-center">
  <i class="bi bi-clipboard2-check text-success mb-3" style="font-size: 3.5rem;"></i>
  <h4 class="fw-bold text-dark">Importación Completada</h4>
  <p class="text-muted mb-3">La estructura curricular y el proyecto se cargaron en el sistema de manera exitosa.</p>
  <div>
    <a href="<?= MODULES_PATH ?>/estructura/" class="btn btn-primary px-4"><i class="bi bi-house-door me-2"></i>Ir al Dashboard</a>
  </div>
</div>
<?php endif; ?>

<?php if (!$success && !$preview_mode): ?>
<!-- FORMULARIO DE CARGA DE ARCHIVOS -->
<div class="row">
  <div class="col-xl-8 mx-auto">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-pdf text-primary me-2"></i>Sube tus Documentos PDF</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          
          <!-- Estructura Curricular -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark d-flex justify-content-between">
              <span>Estructura Curricular (PDF)</span>
              <span class="text-muted small fw-normal">Opcional</span>
            </label>
            <p class="text-muted small mb-2">Este archivo contiene todas las competencias del programa, su duración y la denominación de los resultados de aprendizaje.</p>
            <div class="upload-dropzone" id="dropzoneEstructura" onclick="document.getElementById('pdf_estructura').click()">
              <i class="bi bi-file-earmark-arrow-up icon"></i>
              <span class="text">Arrastra aquí el archivo o haz clic para buscar</span>
              <span class="filename" id="file_estructura_name">No se ha seleccionado ningún archivo</span>
              <input type="file" name="pdf_estructura" id="pdf_estructura" class="d-none" accept=".pdf" onchange="updateFilename('pdf_estructura', 'file_estructura_name')">
            </div>
          </div>

          <!-- Proyecto Formativo -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark d-flex justify-content-between">
              <span>Reporte Proyecto Formativo (PDF)</span>
              <span class="text-muted small fw-normal">Opcional</span>
            </label>
            <p class="text-muted small mb-2">Este archivo contiene la información del proyecto formativo, las fases asociadas (Análisis, Planeación, Ejecución, Evaluación) y los RAs vinculados a cada fase.</p>
            <div class="upload-dropzone" id="dropzoneProyecto" onclick="document.getElementById('pdf_proyecto').click()">
              <i class="bi bi-kanban-fill icon"></i>
              <span class="text">Arrastra aquí el archivo o haz clic para buscar</span>
              <span class="filename" id="file_proyecto_name">No se ha seleccionado ningún archivo</span>
              <input type="file" name="pdf_proyecto" id="pdf_proyecto" class="d-none" accept=".pdf" onchange="updateFilename('pdf_proyecto', 'file_proyecto_name')">
            </div>
          </div>

          <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary py-2.5 fw-bold"><i class="bi bi-cpu me-2"></i>Analizar y Previsualizar Documentos</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.upload-dropzone {
  border: 2px dashed var(--border);
  border-radius: var(--radius-lg);
  padding: 2.5rem 1.5rem;
  text-align: center;
  background: var(--bg);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
.upload-dropzone:hover {
  border-color: var(--sena-primary);
  background: var(--sena-primary-50);
}
.upload-dropzone .icon {
  font-size: 2.5rem;
  color: var(--text-soft);
  transition: color 0.2s ease;
}
.upload-dropzone:hover .icon {
  color: var(--sena-primary);
}
.upload-dropzone .text {
  font-weight: 500;
  color: var(--text-muted);
}
.upload-dropzone .filename {
  font-size: 0.8rem;
  color: var(--text-soft);
  font-style: italic;
}
</style>

<script>
function updateFilename(inputId, nameId) {
  const input = document.getElementById(inputId);
  const display = document.getElementById(nameId);
  if (input.files && input.files[0]) {
    display.textContent = input.files[0].name;
    display.classList.add('text-success', 'fw-bold');
  } else {
    display.textContent = 'No se ha seleccionado ningún archivo';
    display.classList.remove('text-success', 'fw-bold');
  }
}

// Agregar efectos de drag-and-drop
['dropzoneEstructura', 'dropzoneProyecto'].forEach(zoneId => {
  const zone = document.getElementById(zoneId);
  const inputId = zoneId === 'dropzoneEstructura' ? 'pdf_estructura' : 'pdf_proyecto';
  const nameId = zoneId === 'dropzoneEstructura' ? 'file_estructura_name' : 'file_proyecto_name';
  const input = document.getElementById(inputId);

  zone.addEventListener('dragover', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--sena-primary)';
    zone.style.background = 'var(--sena-primary-50)';
  });

  zone.addEventListener('dragleave', () => {
    zone.style.borderColor = 'var(--border)';
    zone.style.background = 'var(--bg)';
  });

  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--border)';
    zone.style.background = 'var(--bg)';
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      input.files = e.dataTransfer.files;
      updateFilename(inputId, nameId);
    }
  });
});
</script>
<?php endif; ?>

<?php if ($preview_mode && !empty($_SESSION['pending_import'])): ?>
<!-- PREVISUALIZACIÓN DE DATOS ANTES DE IMPORTAR -->
<div class="row">
  <div class="col-12">
    <div class="alert-flat warning mb-4 border-0">
      <i class="bi bi-info-circle-fill"></i>
      <div>
        <h6 class="fw-bold mb-1">Previsualización de Importación</h6>
        Revisa los datos extraídos de los documentos PDF. Si estás conforme con la información estructurada, haz clic en **Confirmar e Importar** para registrar los datos en la base de datos de manera definitiva.
      </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="card p-3 mb-4 border-0 shadow-sm d-flex flex-row justify-content-between align-items-center bg-light">
      <div class="fw-semibold text-muted"><i class="bi bi-layers me-2"></i>Estado: Esperando confirmación de escritura</div>
      <div class="d-flex gap-2">
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="cancelar">
          <button type="submit" class="btn btn-soft px-4"><i class="bi bi-trash me-2"></i>Cancelar</button>
        </form>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="confirmar">
          <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-check-all me-2"></i>Confirmar e Importar</button>
        </form>
      </div>
    </div>

    <!-- Estructura Curricular Preview -->
    <?php if (!empty($parsed_estructura)): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold text-success"><i class="bi bi-collection me-2"></i>Estructura Curricular: <?= htmlspecialchars($parsed_estructura['programa_nombre']) ?></h5>
      </div>
      <div class="card-body">
        <div class="row mb-3 g-2">
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Código del Programa</small>
              <strong style="font-size: 1.1rem;"><code><?= htmlspecialchars($parsed_estructura['programa_codigo']) ?></code></strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Total Competencias</small>
              <strong style="font-size: 1.1rem;"><?= count($parsed_estructura['competencias']) ?></strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Duración Estimada</small>
              <strong style="font-size: 1.1rem;"><?= $parsed_estructura['programa_duracion'] ?> horas</strong>
            </div>
          </div>
        </div>

        <div class="accordion" id="accordionEstructura">
          <?php foreach ($parsed_estructura['competencias'] as $index => $comp): ?>
          <div class="accordion-item" style="border-radius: var(--radius-lg); margin-bottom: 0.5rem; overflow: hidden; border: 1px solid var(--border);">
            <h2 class="accordion-header" id="headingEst<?= $index ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEst<?= $index ?>" aria-expanded="false" style="font-size: 0.95rem; font-weight: 600; padding: 1rem 1.25rem;">
                <span class="badge bg-soft primary me-2">Código: <?= htmlspecialchars($comp['codigo']) ?></span>
                <?= htmlspecialchars(substr($comp['nombre'], 0, 110)) ?><?= strlen($comp['nombre']) > 110 ? '...' : '' ?>
                <span class="badge bg-secondary ms-auto text-white ms-2" style="font-size: 0.72rem;"><?= count($comp['resultados']) ?> RAs</span>
              </button>
            </h2>
            <div id="collapseEst<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#accordionEstructura">
              <div class="accordion-body bg-white p-3">
                <p class="mb-3 text-muted"><i class="bi bi-clock me-1"></i> Duración de la Competencia: <strong><?= htmlspecialchars($comp['duracion']) ?></strong></p>
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-2">Resultados de Aprendizaje (RAs):</h6>
                <ul class="list-group list-group-flush">
                  <?php foreach ($comp['resultados'] as $ra): ?>
                  <li class="list-group-item px-0 py-2 d-flex align-items-start gap-2">
                    <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem; font-family: monospace;">RA-<?= str_pad((string)$ra['numero'], 2, '0', STR_PAD_LEFT) ?></span>
                    <span style="font-size: 0.9rem;"><?= htmlspecialchars($ra['denominacion']) ?></span>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Proyecto Formativo Preview -->
    <?php if (!empty($parsed_proyecto)): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-kanban me-2"></i>Proyecto Formativo: <?= htmlspecialchars($parsed_proyecto['proyecto_nombre']) ?></h5>
      </div>
      <div class="card-body">
        <div class="row mb-4 g-2">
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Código del Proyecto</small>
              <strong style="font-size: 1.1rem;"><code><?= htmlspecialchars($parsed_proyecto['proyecto_codigo']) ?></code></strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Fases del Proyecto</small>
              <strong style="font-size: 1.1rem; text-transform: capitalize;"><?= count($parsed_proyecto['fases']) ?> fases</strong>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="p-3 border rounded-3 bg-light">
              <small class="text-muted d-block">Programa Asociado</small>
              <strong style="font-size: 1.05rem;" class="text-truncate d-block" title="<?= htmlspecialchars($parsed_proyecto['programa_nombre']) ?>"><?= htmlspecialchars($parsed_proyecto['programa_nombre'] ?: 'Desconocido') ?></strong>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <h6 class="fw-bold text-dark mb-1">Objetivo General del Proyecto:</h6>
          <p class="p-3 border rounded-3 bg-light text-muted mb-0" style="font-size: 0.92rem; line-height: 1.6;"><?= htmlspecialchars($parsed_proyecto['proyecto_objetivo']) ?></p>
        </div>

        <h6 class="fw-bold text-dark mt-4 mb-3"><i class="bi bi-diagram-2 me-2"></i>Fases y Resultados del Proyecto Formativo</h6>
        <div class="accordion" id="accordionProyecto">
          <?php 
          // Agrupar resultados por fase para la vista
          $raPorFase = [];
          foreach ($parsed_proyecto['fases'] as $f) {
              $raPorFase[$f] = [];
          }
          foreach ($parsed_proyecto['resultados'] as $r) {
              if (isset($raPorFase[$r['fase']])) {
                  $raPorFase[$r['fase']][] = $r;
              }
          }
          ?>
          <?php foreach ($parsed_proyecto['fases'] as $index => $fase): ?>
          <div class="accordion-item" style="border-radius: var(--radius-lg); margin-bottom: 0.5rem; overflow: hidden; border: 1px solid var(--border);">
            <h2 class="accordion-header" id="headingProj<?= $index ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProj<?= $index ?>" aria-expanded="false" style="font-size: 0.95rem; font-weight: 600; padding: 1rem 1.25rem;">
                <span class="badge bg-success me-2">Fase</span>
                <?= htmlspecialchars($fase) ?>
                <span class="badge bg-secondary ms-auto text-white ms-2" style="font-size: 0.72rem;"><?= count($raPorFase[$fase]) ?> RAs en esta fase</span>
              </button>
            </h2>
            <div id="collapseProj<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#accordionProyecto">
              <div class="accordion-body bg-white p-3">
                <div class="table-wrap border-0 rounded-0">
                  <table class="table mb-0" style="font-size: 0.85rem;">
                    <thead>
                      <tr>
                        <th>Código RA</th>
                        <th>Resultado de Aprendizaje (RA)</th>
                        <th>Competencia Asociada</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($raPorFase[$fase] as $ra): ?>
                      <tr>
                        <td style="white-space: nowrap;"><strong><?= htmlspecialchars($ra['ra_code']) ?>-<?= str_pad((string)$ra['ra_num'], 2, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= htmlspecialchars($ra['denominacion']) ?></td>
                        <td>
                          <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($ra['competencia_code'] . ' - ' . ($parsed_proyecto['competencias'][$ra['competencia_code']] ?? '')) ?>">
                            <small class="badge bg-light text-dark border"><?= htmlspecialchars($ra['competencia_code']) ?></small>
                            <?= htmlspecialchars($parsed_proyecto['competencias'][$ra['competencia_code']] ?? 'Ver competencia') ?>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($raPorFase[$fase])): ?>
                      <tr>
                        <td colspan="3" class="text-center py-3 text-muted">No se encontraron resultados de aprendizaje asociados a esta fase en el PDF.</td>
                      </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>
