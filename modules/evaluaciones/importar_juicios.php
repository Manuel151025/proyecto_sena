<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../includes/SimpleXLS.php';

use Core\Database;
use Shuchkin\SimpleXLS;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

// Aumentar límites para uploads base64 (33% más grandes que el original)
@ini_set('post_max_size', '64M');
@ini_set('upload_max_filesize', '64M');
@ini_set('memory_limit', '256M');

$db = Database::getConnection();
$errors = [];
$successMessage = '';
$import_summary = null;

// Comprobar si hay resultados de importación almacenados en sesión (de AJAX previo)
$tabId = getTabId();
if (isset($_SESSION['tabs'][$tabId]['import_success'])) {
    $successMessage = $_SESSION['tabs'][$tabId]['import_success'];
    $import_summary = $_SESSION['tabs'][$tabId]['import_summary'] ?? null;
    unset($_SESSION['tabs'][$tabId]['import_success'], $_SESSION['tabs'][$tabId]['import_summary']);
} elseif (isset($_SESSION['import_success'])) {
    $successMessage = $_SESSION['import_success'];
    $import_summary = $_SESSION['import_summary'] ?? null;
    unset($_SESSION['import_success'], $_SESSION['import_summary']);
}

$user_rol = getCurrentRole();
$user_id = (int)getCurrentUser()['id'];

if (!function_exists('toUtf8')) {
function toUtf8($str) {
    if ($str === null || $str === '') return '';
    // Si contiene caracteres corruptos por codificación de Excel, intentar convertirlos
    if (!mb_check_encoding($str, 'UTF-8')) {
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
    }
    return $str;
}
}

// Detectar si el tamaño del POST superó el límite de PHP (cuando $_POST y $_FILES están vacíos en un POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $errors[] = 'El tamaño del archivo supera el límite permitido por la configuración de PHP del servidor (post_max_size / upload_max_filesize). Intente con un archivo más pequeño.';
    @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . "POST vacío recibido. Posible exceso de post_max_size en php.ini.\n", FILE_APPEND);
}

// Determinar si el envío es via AJAX (base64) o formulario tradicional
$is_ajax = (!empty($_POST['file_data']) && !empty($_POST['file_name']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($is_ajax || isset($_FILES['excel_file']))) {

    $targetXls = null;
    $ext = '';
    
    if ($is_ajax) {
        // --- MODO AJAX: archivo enviado como base64 ---
        $originalName = $_POST['file_name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if ($ext !== 'xls') {
            $errors[] = 'El archivo debe tener extensión .xls (Reporte binario de Sofia Plus).';
        } else {
            $fileData = base64_decode($_POST['file_data'], true);
            if ($fileData === false || strlen($fileData) === 0) {
                $errors[] = 'Error: No se pudo decodificar el contenido del archivo. Intenta de nuevo.';
            } else {
                $uploadsDir = realpath(__DIR__ . '/../../uploads');
                if ($uploadsDir === false) {
                    $uploadsDir = __DIR__ . '/../../uploads';
                    if (!is_dir($uploadsDir)) {
                        @mkdir($uploadsDir, 0777, true);
                    }
                }
                $targetXls = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.xls';
                if (file_put_contents($targetXls, $fileData) === false) {
                    $errors[] = 'Error al guardar el archivo en el servidor.';
                    $targetXls = null;
                }
            }
        }
        
        // Si es AJAX, enviar respuesta JSON
        if (!empty($errors)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
    } else {
        // --- MODO TRADICIONAL: formulario multipart ---
        if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error al subir el archivo. Código: ' . $_FILES['excel_file']['error'];
        } else {
            $tmpName = $_FILES['excel_file']['tmp_name'];
            $originalName = $_FILES['excel_file']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            if ($ext !== 'xls') {
                $errors[] = 'El archivo debe tener extensión .xls (Reporte binario de Sofia Plus).';
            } elseif (!file_exists($tmpName) || filesize($tmpName) === 0) {
                $errors[] = 'Error: El archivo no se recibió correctamente en el servidor. Intenta de nuevo.';
            } else {
                $uploadsDir = realpath(__DIR__ . '/../../uploads');
                if ($uploadsDir === false) {
                    $uploadsDir = __DIR__ . '/../../uploads';
                    if (!is_dir($uploadsDir)) {
                        @mkdir($uploadsDir, 0777, true);
                    }
                }
                $targetXls = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.xls';
                if (!move_uploaded_file($tmpName, $targetXls)) {
                    $errors[] = 'Error al guardar el archivo en el servidor.';
                    $targetXls = null;
                }
            }
        }
    }

    if (empty($errors) && $targetXls) {
        // --- LEER XLS DIRECTAMENTE CON PHP (SimpleXLS) ---
        $xls = SimpleXLS::parseFile($targetXls);
        
        // Limpiar XLS temporal inmediatamente
        if (file_exists($targetXls)) {
            unlink($targetXls);
        }
        
        if (!$xls) {
            $xlsErr = (string)SimpleXLS::parseError();
            $errors[] = 'Error al leer el archivo Excel: ' . htmlspecialchars($xlsErr);
            @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . 'SimpleXLS parseError: ' . $xlsErr . "\n", FILE_APPEND);
        } else {
            $allRows = $xls->rows(0); // Primera hoja
            
            if (empty($allRows)) {
                $errors[] = 'El archivo Excel está vacío o no contiene datos legibles.';
                @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . "SimpleXLS: rows array is empty.\n", FILE_APPEND);
            } else {
                try {
                    $db->beginTransaction();
                    
                    $ficha_numero = '';
                    $programa_codigo = '';
                    $programa_nombre = '';
                    $fecha_inicio = null;
                    $fecha_fin = null;
                    
                    $rowNum = 0;
                    $headers_found = false;
                    $headers_row_index = -1;
                    
                    $stats = [
                        'ficha_estado' => 'Sin cambios',
                        'ficha_num' => '',
                        'programa' => '',
                        'aprendices_creados' => 0,
                        'aprendices_actualizados' => 0,
                        'competencias_creadas' => 0,
                        'ras_creados' => 0,
                        'evaluaciones_actualizadas' => 0,
                        'evaluaciones_creadas' => 0,
                        'warnings' => [],
                        'detalles' => []
                    ];
                    
                    $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                    $password_hash = password_hash('Sena2026', PASSWORD_DEFAULT);
                    
                    // 1. Leer cabecera para metadatos de ficha/programa
                    foreach ($allRows as $idx => $data) {
                        $rowNum = $idx + 1; // 1-indexed
                        
                        // Sanitizar todas las celdas a UTF-8
                        foreach ($data as $k => $v) {
                            $data[$k] = toUtf8(trim((string)$v));
                        }
                        
                        if ($rowNum === 3) {
                            $ficha_numero = $data[2] ?? '';
                        }
                        if ($rowNum === 4) {
                            $programa_codigo = $data[2] ?? '';
                        }
                        if ($rowNum === 6) {
                            $programa_nombre = $data[2] ?? '';
                        }
                        if ($rowNum === 8) {
                            $fecha_inicio_str = $data[2] ?? '';
                            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_inicio_str, $m)) {
                                $fecha_inicio = "{$m[3]}-{$m[2]}-{$m[1]}";
                            }
                        }
                        if ($rowNum === 9) {
                            $fecha_fin_str = $data[2] ?? '';
                            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_fin_str, $m)) {
                                $fecha_fin = "{$m[3]}-{$m[2]}-{$m[1]}";
                            }
                        }
                        
                        // Detectar la fila de títulos de columna
                        if ($rowNum >= 10 && isset($data[0]) && (stripos($data[0], 'Tipo') !== false) && stripos($data[1] ?? '', 'Documento') !== false) {
                            $headers_found = true;
                            $headers_row_index = $idx;
                            break;
                        }
                    }
                            
                            if (empty($ficha_numero) || empty($programa_codigo)) {
                                throw new Exception('No se pudo identificar el número de ficha o el código de programa en las cabeceras del archivo.');
                            }
                            
                            $stats['ficha_num'] = $ficha_numero;
                            $stats['programa'] = "$programa_nombre ($programa_codigo)";
                            
                            // 2. Registrar / Obtener Programa
                            $stmt = $db->prepare("SELECT id FROM programas WHERE codigo = ?");
                            $stmt->execute([$programa_codigo]);
                            $programa_id = $stmt->fetchColumn();
                            if ($programa_id) {
                                $programa_id = (int)$programa_id;
                            } else {
                                $stmtInsertProg = $db->prepare("
                                    INSERT INTO programas (nombre, codigo, duracion_horas, estado)
                                    VALUES (?, ?, 0, 'activo')
                                ");
                                $stmtInsertProg->execute([$programa_nombre ?: 'Programa Importado', $programa_codigo]);
                                $programa_id = (int)$db->lastInsertId();
                            }
                            
                            // 3. Registrar / Obtener Ficha
                            $stmt = $db->prepare("SELECT id, instructor_id FROM fichas WHERE numero_ficha = ?");
                            $stmt->execute([$ficha_numero]);
                            $ficha_db = $stmt->fetch();
                            
                            if ($ficha_db) {
                                $ficha_id = (int)$ficha_db['id'];
                                if ($user_rol === ROL_INSTRUCTOR && (int)$ficha_db['instructor_id'] !== $user_id) {
                                    throw new Exception("No tienes permisos para importar juicios en la ficha #$ficha_numero porque está asignada a otro instructor.");
                                }
                                $stats['ficha_estado'] = 'Actualizada (ya existía)';
                            } else {
                                // Buscar un instructor asignado por defecto (el usuario actual si es instructor, o el primero de la BD)
                                $default_instructor_id = $user_id;
                                if ($user_rol !== ROL_INSTRUCTOR) {
                                    $stmtInst = $db->query("SELECT id FROM usuarios WHERE rol = 'instructor' LIMIT 1");
                                    $default_instructor_id = (int)($stmtInst->fetchColumn() ?: $user_id);
                                }
                                
                                $stmtInsertFicha = $db->prepare("
                                    INSERT INTO fichas (numero_ficha, programa_id, instructor_id, coordinador_id, estado, cantidad_aprendices, fecha_inicio, fecha_fin)
                                    VALUES (?, ?, ?, ?, 'ejecucion', 0, ?, ?)
                                ");
                                $stmtInsertFicha->execute([$ficha_numero, $programa_id, $default_instructor_id, 1, $fecha_inicio, $fecha_fin]);
                                $ficha_id = (int)$db->lastInsertId();
                                $stats['ficha_estado'] = 'Creada e inicializada';
                            }
                            
                            // Cache para agilizar búsquedas en bucle
                            $usuarios_cache = [];
                            $aprendices_cache = [];
                            $competencias_cache = []; // codigo => id
                            $ras_cache = []; // codigo_ra => id
                            
                            // 4. Leer registros de aprendices y calificaciones
                            $dataStartIndex = $headers_row_index + 1;
                            for ($ri = $dataStartIndex; $ri < count($allRows); $ri++) {
                                $data = $allRows[$ri];
                                $rowNum = $ri + 1;
                                
                                // Sanitizar a UTF-8
                                foreach ($data as $k => $v) {
                                    $data[$k] = toUtf8(trim((string)$v));
                                }
                                
                                if (count($data) < 8 || empty($data[1])) {
                                    continue; // Fila vacía o incompleta
                                }
                                
                                $tipo_doc          = $data[0];
                                $num_doc           = $data[1];
                                $nombre_pila       = $data[2];
                                $apellidos         = $data[3];
                                $estado_matricula  = strtolower($data[4]);
                                $competencia_raw   = $data[5];
                                $ra_raw            = $data[6];
                                $juicio_str        = strtoupper($data[7]);
                                $fecha_juicio_str  = $data[8];
                                $instructor_raw    = $data[9] ?? '';
                                
                                $nombre_completo = mb_strtoupper("$nombre_pila $apellidos", 'UTF-8');
                                
                                // --- PROCESAR APRENDIZ ---
                                if (isset($aprendices_cache[$num_doc])) {
                                    $aprendiz_id = $aprendices_cache[$num_doc];
                                } else {
                                    // Comprobar si el usuario existe
                                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                                    $email = "aprendiz_" . $num_doc . "@sena.edu.co";
                                    $stmt->execute([$email]);
                                    $usuario_id = $stmt->fetchColumn();
                                    
                                    if ($usuario_id) {
                                        $usuario_id = (int)$usuario_id;
                                    } else {
                                        $avatar_color = $colors[array_rand($colors)];
                                        $stmtInsertUsr = $db->prepare("
                                            INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado)
                                            VALUES (?, ?, ?, 'aprendiz', ?, 'activo')
                                        ");
                                        $stmtInsertUsr->execute([$nombre_completo, $email, $password_hash, $avatar_color]);
                                        $usuario_id = (int)$db->lastInsertId();
                                    }
                                    
                                    // Comprobar si el aprendiz existe
                                    $stmt = $db->prepare("SELECT id, ficha_id FROM aprendices WHERE numero_documento = ?");
                                    $stmt->execute([$num_doc]);
                                    $ap_db = $stmt->fetch();
                                    
                                    // Traducir estado
                                    $estado_sena = 'matriculado';
                                    if (stripos($estado_matricula, 'retiro') !== false || stripos($estado_matricula, 'deser') !== false) {
                                        $estado_sena = 'desertado';
                                    }
                                    
                                    if ($ap_db) {
                                        $aprendiz_id = (int)$ap_db['id'];
                                        // Actualizar ficha o estado si cambió
                                        $stmtUpdateAp = $db->prepare("UPDATE aprendices SET ficha_id = ?, estado = ? WHERE id = ?");
                                        $stmtUpdateAp->execute([$ficha_id, $estado_sena, $aprendiz_id]);
                                        $stats['aprendices_actualizados']++;
                                    } else {
                                        $stmtInsertAp = $db->prepare("
                                            INSERT INTO aprendices (usuario_id, ficha_id, numero_documento, tipo_documento, genero, estado)
                                            VALUES (?, ?, ?, ?, 'O', ?)
                                        ");
                                        $stmtInsertAp->execute([$usuario_id, $ficha_id, $num_doc, $tipo_doc, $estado_sena]);
                                        $aprendiz_id = (int)$db->lastInsertId();
                                        $stats['aprendices_creados']++;
                                        
                                        // Incrementar contador de la ficha
                                        $db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$ficha_id]);
                                    }
                                    
                                    $aprendices_cache[$num_doc] = $aprendiz_id;
                                }
                                
                                // --- PROCESAR COMPETENCIA ---
                                $compCode = '';
                                $compName = '';
                                if (preg_match('/^(\d+)\s*-\s*(.+)$/u', $competencia_raw, $m)) {
                                    $compCode = trim($m[1]);
                                    $compName = trim($m[2]);
                                } else {
                                    $compCode = substr($competencia_raw, 0, 10);
                                    $compName = $competencia_raw;
                                }
                                
                                if (isset($competencias_cache[$compCode])) {
                                    $competencia_id = $competencias_cache[$compCode];
                                } else {
                                    $stmt = $db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
                                    $stmt->execute([$programa_id, $compCode]);
                                    $competencia_id = $stmt->fetchColumn();
                                    
                                    if ($competencia_id) {
                                        $competencia_id = (int)$competencia_id;
                                    } else {
                                        $stmtInsertComp = $db->prepare("
                                            INSERT INTO competencias (programa_id, codigo, nombre, estado)
                                            VALUES (?, ?, ?, 'activo')
                                        ");
                                        $stmtInsertComp->execute([$programa_id, $compCode, $compName]);
                                        $competencia_id = (int)$db->lastInsertId();
                                        $stats['competencias_creadas']++;
                                    }
                                    $competencias_cache[$compCode] = $competencia_id;
                                }
                                
                                // --- PROCESAR RESULTADO DE APRENDIZAJE (RA) ---
                                $raCode = '';
                                $raDenom = '';
                                if (preg_match('/^(\d+)\s*-\s*(\d{2})?\s*(.+)$/u', $ra_raw, $m)) {
                                    $base_code = $m[1];
                                    $num = !empty($m[2]) ? (int)$m[2] : 1;
                                    $raCode = $base_code . '-' . str_pad((string)$num, 2, '0', STR_PAD_LEFT);
                                    $raDenom = trim($m[3]);
                                } elseif (preg_match('/^(\d+)\s*-\s*(.+)$/u', $ra_raw, $m)) {
                                    $raCode = trim($m[1]);
                                    $raDenom = trim($m[2]);
                                } else {
                                    $raCode = substr($ra_raw, 0, 10);
                                    $raDenom = $ra_raw;
                                }
                                
                                if (isset($ras_cache[$raCode])) {
                                    $ra_id = $ras_cache[$raCode];
                                } else {
                                    $stmt = $db->prepare("SELECT id FROM resultados_aprendizaje WHERE competencia_id = ? AND codigo = ?");
                                    $stmt->execute([$competencia_id, $raCode]);
                                    $ra_id = $stmt->fetchColumn();
                                    
                                    if ($ra_id) {
                                        $ra_id = (int)$ra_id;
                                    } else {
                                        $stmtInsertRa = $db->prepare("
                                            INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion, estado)
                                            VALUES (?, ?, ?, 'activo')
                                        ");
                                        $stmtInsertRa->execute([$competencia_id, $raCode, $raDenom]);
                                        $ra_id = (int)$db->lastInsertId();
                                        $stats['ras_creados']++;
                                    }
                                    $ras_cache[$raCode] = $ra_id;
                                }
                                
                                // --- PROCESAR EVALUACION (JUICIO) ---
                                // Mapear concepto
                                $concepto = 'pendiente';
                                if (stripos($juicio_str, 'APROBADO') !== false) {
                                    $concepto = 'A';
                                } elseif (stripos($juicio_str, 'DEFICIENTE') !== false || stripos($juicio_str, 'NO APROBADO') !== false) {
                                    $concepto = 'D';
                                }
                                
                                // Resolver instructor
                                $instructor_id = $user_id; // por defecto
                                if (!empty($instructor_raw)) {
                                    // Intentar buscar por documento o nombre si viene ej "CC 1117523028 - OSCAR CASTRO"
                                    $instDoc = '';
                                    if (preg_match('/^CC\s+(\d+)/i', $instructor_raw, $instM)) {
                                        $instDoc = $instM[1];
                                    }
                                    
                                    if ($instDoc) {
                                        $stmtInst = $db->prepare("
                                            SELECT u.id FROM usuarios u
                                            JOIN aprendices ap ON ap.usuario_id = u.id
                                            WHERE ap.numero_documento = ? AND u.rol = 'instructor'
                                        ");
                                        $stmtInst->execute([$instDoc]);
                                        $foundInst = $stmtInst->fetchColumn();
                                        if ($foundInst) {
                                            $instructor_id = (int)$foundInst;
                                        }
                                    }
                                }
                                
                                // Comprobar si ya existe una evaluación registrada
                                $stmt = $db->prepare("
                                    SELECT id, concepto FROM evaluaciones
                                    WHERE resultado_aprendizaje_id = ? AND aprendiz_id = ?
                                ");
                                $stmt->execute([$ra_id, $aprendiz_id]);
                                $eval_db = $stmt->fetch();
                                
                                $fecha_eval = null;
                                if ($concepto !== 'pendiente') {
                                    $fecha_eval = date('Y-m-d');
                                    // Parsear fecha del juicio si viene ej "16/02/2025 16.46 a"
                                    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $fecha_juicio_str, $fM)) {
                                        $fecha_eval = "{$fM[3]}-{$fM[2]}-{$fM[1]}";
                                    }
                                }
                                
                                $eval_accion = 'Sin cambios';
                                if ($eval_db) {
                                    $eval_id = (int)$eval_db['id'];
                                    $concepto_anterior = $eval_db['concepto'];
                                    
                                    if ($concepto_anterior !== $concepto) {
                                        // Modificar evaluación
                                        $stmtUpdateEval = $db->prepare("
                                            UPDATE evaluaciones
                                            SET concepto = ?, instructor_id = ?, fecha_evaluacion = ?, fecha_actualizacion = NOW()
                                            WHERE id = ?
                                        ");
                                        $stmtUpdateEval->execute([$concepto, $instructor_id, $fecha_eval, $eval_id]);
                                        
                                        // Registrar trazabilidad
                                        $stmtHist = $db->prepare("
                                            INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo)
                                            VALUES (?, ?, ?, ?, 'Importado masivo desde reporte Sofia Plus (Excel)')
                                        ");
                                        $stmtHist->execute([$eval_id, $user_id, $concepto_anterior, $concepto]);
                                        $stats['evaluaciones_actualizadas']++;
                                        $eval_accion = 'Actualizado';
                                    }
                                } else {
                                    // Insertar evaluación
                                    $stmtInsertEval = $db->prepare("
                                        INSERT INTO evaluaciones (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, fecha_evaluacion)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmtInsertEval->execute([$ra_id, $aprendiz_id, $instructor_id, $ficha_id, $concepto, $fecha_eval]);
                                    $stats['evaluaciones_creadas']++;
                                    $eval_accion = 'Creado';
                                }
                                
                                if (!isset($stats['detalles'][$num_doc])) {
                                    $stats['detalles'][$num_doc] = [
                                        'documento' => $num_doc,
                                        'nombre' => $nombre_completo,
                                        'juicios' => []
                                    ];
                                }
                                $stats['detalles'][$num_doc]['juicios'][] = [
                                    'ra_codigo' => $raCode,
                                    'ra_denom' => $raDenom,
                                    'competencia' => $compName . ' (' . $compCode . ')',
                                    'concepto' => $concepto,
                                    'eval_accion' => $eval_accion
                                ];
                            }
                            
                            $db->commit();
                            $import_summary = $stats;
                            $successMessage = "¡Carga masiva finalizada con éxito! Todos los registros fueron procesados.";
                            
                    } catch (Exception $e) {
                        $db->rollBack();
                        $errors[] = 'Error al procesar el contenido de la importación: ' . htmlspecialchars($e->getMessage());
                        @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . 'Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
                    }
                }
            }
        }

    // Si es AJAX, almacenar resultados en sesión y enviar respuesta JSON
    if ($is_ajax) {
        if (empty($errors) && $successMessage) {
            $tabId = getTabId();
            $_SESSION['tabs'][$tabId]['import_success'] = $successMessage;
            $_SESSION['tabs'][$tabId]['import_summary'] = $import_summary;
            
            $_SESSION['import_success'] = $successMessage;
            $_SESSION['import_summary'] = $import_summary;
        }
        header('Content-Type: application/json');
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => $successMessage
            ]);
        }
        exit;
    }
}

$pageTitle = 'Importar Juicios Evaluativos · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Importar Juicios Evaluativos</h1>
    <p class="text-muted mb-0">Carga el reporte de juicios evaluativos desde Excel para registrar notas, fichas y aprendices automáticamente.</p>
  </div>
  <div>
    <a href="<?= MODULES_PATH ?>/evaluaciones/" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver a Evaluaciones
    </a>
  </div>
</div>

<?php
$debug_info = "Method: " . $_SERVER['REQUEST_METHOD'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info .= " | Input size: " . strlen(file_get_contents('php://input')) . " bytes";
    $debug_info .= " | FILES count: " . count($_FILES);
    if (!empty($_FILES)) {
        foreach ($_FILES as $key => $file) {
            $debug_info .= " | File[$key] size: " . $file['size'] . " bytes, error: " . $file['error'];
        }
    }
}
?>
<div class="alert alert-info py-2 px-3 mb-4" style="font-size: 0.8rem; border-radius: 8px; border: 1px solid #bde5f8; background-color: #e0f2fe; color: #0369a1;">
  <strong>Soporte Técnico (Debug):</strong> <?= htmlspecialchars($debug_info) ?> | 
  post_max_size: <?= ini_get('post_max_size') ?> | 
  upload_max_filesize: <?= ini_get('upload_max_filesize') ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-flat danger mb-4">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>
    <?php foreach ($errors as $e) echo $e . '<br>'; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($successMessage): ?>
<div class="alert-flat success mb-4">
  <i class="bi bi-check-circle-fill"></i>
  <div><?= htmlspecialchars($successMessage) ?></div>
</div>
<?php endif; ?>

<!-- RESUMEN DE LA IMPORTACIÓN -->
<?php if ($import_summary): ?>
<div class="card border-0 shadow-sm mb-4" style="border-top: 4px solid var(--sena-primary); border-radius: 12px;">
  <div class="card-body p-4">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Resumen de Datos Cargados</h5>
    
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="p-3 border rounded bg-light">
          <small class="text-muted d-block">Ficha de Caracterización</small>
          <strong style="font-size: 1.1rem;">#<?= htmlspecialchars($import_summary['ficha_num']) ?></strong>
          <span class="badge bg-soft primary d-block mt-1"><?= htmlspecialchars($import_summary['ficha_estado']) ?></span>
        </div>
      </div>
      <div class="col-sm-6 col-lg-5">
        <div class="p-3 border rounded bg-light">
          <small class="text-muted d-block">Programa de Formación</small>
          <strong style="font-size: 1.1rem;"><?= htmlspecialchars($import_summary['programa']) ?></strong>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle" style="font-size:0.9rem;">
        <thead class="table-light">
          <tr>
            <th>Concepto / Entidad</th>
            <th class="text-center" style="width: 150px;">Registros Creados</th>
            <th class="text-center" style="width: 150px;">Registros Actualizados</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Aprendices</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['aprendices_creados'] ?></td>
            <td class="text-center text-primary"><?= $import_summary['aprendices_actualizados'] ?></td>
          </tr>
          <tr>
            <td><strong>Competencias</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['competencias_creadas'] ?></td>
            <td class="text-center text-muted">0</td>
          </tr>
          <tr>
            <td><strong>Resultados de Aprendizaje (RAs)</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['ras_creados'] ?></td>
            <td class="text-center text-muted">0</td>
          </tr>
          <tr>
            <td><strong>Juicios de Evaluación (A/D)</strong></td>
            <td class="text-center text-success fw-bold"><?= $import_summary['evaluaciones_creadas'] ?></td>
            <td class="text-center text-primary"><?= $import_summary['evaluaciones_actualizadas'] ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <?php if (!empty($import_summary['detalles'])): ?>
    <div class="mt-4 border-top pt-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Listado Detallado de Juicios Importados</h6>
        <div class="d-flex align-items-center gap-2">
          <input type="text" id="detailSearchInput" class="form-control form-control-sm" placeholder="🔍 Buscar aprendiz o documento..." style="max-width: 250px; border-radius: 8px;">
        </div>
      </div>

      <div class="table-responsive" style="max-height: 450px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
        <table class="table table-hover align-middle mb-0" id="detailsTable" style="font-size:0.85rem;">
          <thead class="table-light sticky-top" style="z-index: 1;">
            <tr>
              <th style="width: 180px;">Documento</th>
              <th>Aprendiz</th>
              <th class="text-center" style="width: 150px;">Juicios Procesados</th>
              <th class="text-center" style="width: 250px;">Resumen de Acciones</th>
              <th class="text-center" style="width: 100px;">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $apIndex = 0;
            foreach ($import_summary['detalles'] as $ap): 
              $apIndex++;
              $detailsId = "details-" . $apIndex;
              $iconId = "icon-" . $detailsId;
              
              // Contar estados
              $creados = 0; 
              $actualizados = 0; 
              $sin_cambios = 0;
              foreach ($ap['juicios'] as $j) {
                  if ($j['eval_accion'] === 'Creado') $creados++;
                  elseif ($j['eval_accion'] === 'Actualizado') $actualizados++;
                  else $sin_cambios++;
              }
              
              $resumen_acciones = [];
              if ($creados > 0) $resumen_acciones[] = "<span class='badge bg-soft success text-success'>$creados Nuevos</span>";
              if ($actualizados > 0) $resumen_acciones[] = "<span class='badge bg-soft primary text-primary'>$actualizados Act.</span>";
              if ($sin_cambios > 0) $resumen_acciones[] = "<span class='badge bg-soft secondary text-secondary'>$sin_cambios S/C</span>";
              
              $resumenHtml = implode(' ', $resumen_acciones);
            ?>
            <tr class="cursor-pointer" onclick="toggleDetails('<?= $detailsId ?>')" data-details-id="<?= $detailsId ?>" style="transition: background-color 0.2s;">
              <td>
                <i class="bi bi-chevron-right me-2 text-muted" id="<?= $iconId ?>" style="transition: transform 0.2s; display: inline-block;"></i>
                <?= htmlspecialchars($ap['documento']) ?>
              </td>
              <td><strong><?= htmlspecialchars($ap['nombre']) ?></strong></td>
              <td class="text-center fw-semibold text-dark"><?= count($ap['juicios']) ?> Juicios</td>
              <td class="text-center"><?= $resumenHtml ?></td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-primary py-0.5 px-2" style="font-size: 0.75rem; border-radius: 6px;">
                  Ver
                </button>
              </td>
            </tr>
            <tr id="<?= $detailsId ?>" style="display: none; background-color: #f8fafc;">
              <td colspan="5" class="p-3">
                <div class="px-4 py-3 border rounded bg-white shadow-sm" style="border-radius: 8px;">
                  <h6 class="fw-bold mb-3 text-muted" style="font-size: 0.8rem;"><i class="bi bi-journal-check me-2"></i>Detalle de Evaluaciones</h6>
                  <table class="table table-sm table-bordered mb-0 align-middle" style="font-size: 0.8rem;">
                    <thead class="table-light">
                      <tr>
                        <th>Competencia</th>
                        <th style="width: 120px;">Código RA</th>
                        <th>Resultado de Aprendizaje (RA)</th>
                        <th class="text-center" style="width: 120px;">Juicio</th>
                        <th class="text-center" style="width: 120px;">Acción de BD</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($ap['juicios'] as $j): 
                        $badgeConcepto = 'bg-secondary text-dark';
                        $textoConcepto = 'Pendiente';
                        if ($j['concepto'] === 'A') {
                            $badgeConcepto = 'bg-soft success text-success';
                            $textoConcepto = 'Aprobado';
                        } elseif ($j['concepto'] === 'D') {
                            $badgeConcepto = 'bg-soft danger text-danger';
                            $textoConcepto = 'Deficiente';
                        }
                        
                        $badgeAccion = 'bg-light text-dark';
                        if ($j['eval_accion'] === 'Creado') {
                            $badgeAccion = 'bg-soft success text-success';
                        } elseif ($j['eval_accion'] === 'Actualizado') {
                            $badgeAccion = 'bg-soft primary text-primary';
                        } else {
                            $badgeAccion = 'bg-soft secondary text-secondary';
                        }
                      ?>
                      <tr>
                        <td style="font-size: 0.75rem; color: #555;"><i class="bi bi-award me-1"></i><?= htmlspecialchars($j['competencia'] ?? '') ?></td>
                        <td><code class="text-dark bg-light px-1.5 py-0.5 rounded" style="font-size: 0.8rem;"><?= htmlspecialchars($j['ra_codigo']) ?></code></td>
                        <td style="font-size: 0.75rem;"><?= htmlspecialchars($j['ra_denom'] ?? '') ?></td>
                        <td class="text-center"><span class="badge <?= $badgeConcepto ?>" style="padding: 4px 8px; font-weight:600;"><?= $textoConcepto ?></span></td>
                        <td class="text-center"><span class="badge <?= $badgeAccion ?>" style="padding: 4px 8px; font-weight:600;"><?= htmlspecialchars($j['eval_accion']) ?></span></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    function toggleDetails(id) {
        const row = document.getElementById(id);
        const icon = document.getElementById('icon-' + id);
        if (!row || !icon) return;
        
        if (row.style.display === 'none') {
            row.style.display = '';
            icon.style.transform = 'rotate(90deg)';
            icon.classList.add('text-primary');
        } else {
            row.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            icon.classList.remove('text-primary');
        }
    }

    document.getElementById('detailSearchInput')?.addEventListener('keyup', function() {
        const value = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#detailsTable tbody tr:not([id^="details-"])');
        rows.forEach(row => {
            const doc = row.cells[0]?.textContent.toLowerCase();
            const name = row.cells[1]?.textContent.toLowerCase();
            const detailsId = row.getAttribute('data-details-id');
            const detailsRow = document.getElementById(detailsId);
            const icon = document.getElementById('icon-' + detailsId);
            
            if (doc.includes(value) || name.includes(value)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                if (detailsRow) {
                    detailsRow.style.display = 'none';
                }
                if (icon) {
                    icon.style.transform = 'rotate(0deg)';
                    icon.classList.remove('text-primary');
                }
            }
        });
    });
    </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- FORMULARIO DE CARGA -->
<?php if (!$import_summary): ?>
<div class="row">
  <div class="col-xl-6 mx-auto">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
      <div class="card-header bg-transparent py-3 border-bottom-0">
        <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-excel text-primary me-2"></i>Sube tu archivo .xls</h5>
      </div>
      <div class="card-body p-4 pt-0">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
          <p class="text-muted small mb-4">
            Selecciona el archivo binario de Excel (<strong>.xls</strong>) que contiene los juicios evaluativos. El sistema lo convertirá y procesará para crear la ficha, los aprendices, las competencias y todos los juicios de evaluación.
          </p>
          
          <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Archivo Excel (.xls)</label>
            <input type="file" id="excelFileInput" name="excel_file" class="form-control" accept=".xls" required>
            <div class="form-text text-muted" style="font-size:0.75rem;">
              * Asegúrate de no alterar el archivo original de juicios evaluativos.
            </div>
          </div>
          
          <!-- Barra de progreso (oculta inicialmente) -->
          <div id="uploadProgress" class="mb-3" style="display:none;">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
              <span id="uploadStatusText" class="small text-muted">Leyendo archivo...</span>
            </div>
            <div class="progress" style="height: 6px;">
              <div id="uploadProgressBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width: 0%"></div>
            </div>
          </div>

          <!-- Contenedor de errores AJAX -->
          <div id="ajaxErrors" class="alert-flat danger mb-3" style="display:none;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div id="ajaxErrorContent"></div>
          </div>
          
          <div class="d-grid">
            <button type="submit" id="submitBtn" class="btn btn-primary py-2.5 fw-bold">
              <i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('excelFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Selecciona un archivo primero.');
        return;
    }
    
    // Validar extensión
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xls') {
        alert('El archivo debe tener extensión .xls');
        return;
    }
    
    const progressDiv = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const statusText = document.getElementById('uploadStatusText');
    const submitBtn = document.getElementById('submitBtn');
    const errorsDiv = document.getElementById('ajaxErrors');
    const errorsContent = document.getElementById('ajaxErrorContent');
    
    // Ocultar errores previos
    errorsDiv.style.display = 'none';
    
    // Mostrar progreso
    progressDiv.style.display = 'block';
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    statusText.textContent = 'Leyendo archivo...';
    progressBar.style.width = '10%';
    
    // Leer el archivo con FileReader (esto bypassa bloqueos del OS)
    const reader = new FileReader();
    
    reader.onload = function(event) {
        statusText.textContent = 'Enviando al servidor...';
        progressBar.style.width = '40%';
        
        // Obtener el base64 sin el prefijo "data:..."
        const base64Data = event.target.result.split(',')[1];
        
        // Enviar via fetch como POST con datos base64
        const formData = new FormData();
        formData.append('file_data', base64Data);
        formData.append('file_name', file.name);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            statusText.textContent = 'Procesando importación...';
            progressBar.style.width = '80%';
            return response.text();
        })
        .then(text => {
            // Verificar si es JSON (respuesta AJAX) o HTML (redirect/page)
            try {
                const data = JSON.parse(text);
                progressBar.style.width = '100%';
                
                if (data.success) {
                    statusText.textContent = '¡Importación completada!';
                    progressBar.classList.remove('progress-bar-animated');
                    progressBar.classList.add('bg-success');
                    // Recargar la página para mostrar el resumen
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    // Mostrar errores
                    progressDiv.style.display = 'none';
                    errorsDiv.style.display = 'flex';
                    errorsContent.innerHTML = data.errors.join('<br>');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
                }
            } catch(err) {
                // La respuesta no es JSON válido. Mostramos el error en lugar de recargar silenciosamente.
                progressDiv.style.display = 'none';
                errorsDiv.style.display = 'flex';
                errorsContent.innerHTML = '<strong>Error del servidor al procesar la respuesta:</strong><br>' + 
                    '<div style="text-align: left; max-height: 250px; overflow-y: auto; font-family: monospace; font-size: 0.75rem; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 6px; margin-top: 8px; white-space: pre-wrap; word-break: break-all;">' + 
                    text.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
            }
        })
        .catch(error => {
            progressDiv.style.display = 'none';
            errorsDiv.style.display = 'flex';
            errorsContent.innerHTML = 'Error de conexión: ' + error.message + '<br>Intenta de nuevo.';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
        });
    };
    
    reader.onerror = function() {
        progressDiv.style.display = 'none';
        errorsDiv.style.display = 'flex';
        errorsContent.innerHTML = 'Error: No se pudo leer el archivo. Intenta con una copia del archivo.';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva';
    };
    
    // Leer como base64 (DataURL)
    reader.readAsDataURL(file);
});
</script>
<?php endif; ?>

