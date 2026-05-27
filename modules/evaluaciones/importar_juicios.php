<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR);

$db = Database::getConnection();
$errors = [];
$successMessage = '';
$import_summary = null;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {

    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error al subir el archivo Excel.';
    } else {
        $tmpName = $_FILES['excel_file']['tmp_name'];
        
        // Diagnóstico de archivo temporal al iniciar el script
        $exists_at_start = file_exists($tmpName);
        $size_at_start = $exists_at_start ? filesize($tmpName) : -1;
        $readable_at_start = $exists_at_start ? is_readable($tmpName) : false;
        $is_uploaded_at_start = is_uploaded_file($tmpName);
        
        $originalName = $_FILES['excel_file']['name'];
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        
        if (strtolower($ext) !== 'xls') {
            $errors[] = 'El archivo debe tener extensión .xls (Reporte binario de Sofia Plus).';
        } elseif (!$exists_at_start) {
            $errors[] = '<strong>Error de Acceso al Archivo:</strong> El navegador no pudo leer el contenido del archivo original.';
            $errors[] = 'Esto ocurre cuando el archivo está bloqueado por el sistema en tu computadora, impidiendo que el navegador web (Chrome/Edge) lea sus datos para subirlos. Las causas más comunes son:';
            $errors[] = '<ul>
                            <li>El archivo está abierto en <strong>Microsoft Excel</strong> (o hay un proceso oculto de Excel reteniéndolo).</li>
                            <li>El archivo está sincronizándose en <strong>OneDrive / SharePoint</strong> y se encuentra bloqueado temporalmente.</li>
                            <li>Tienes el <strong>Panel de Vista Previa</strong> (Preview Pane) activo en el Explorador de Archivos de Windows y tienes seleccionado este archivo, lo cual bloquea su lectura.</li>
                         </ul>';
            $errors[] = '<strong>Soluciones rápidas:</strong>
                         <ol>
                            <li><strong>La solución definitiva:</strong> Haz una copia del archivo (clic derecho -> Copiar y Pegar en el Escritorio) e intenta subir la copia. Al ser un archivo nuevo, no heredará ningún bloqueo activo.</li>
                            <li>Cierra Microsoft Excel completamente. Si persiste, abre el Administrador de Tareas (Ctrl+Shift+Esc), busca procesos de "Microsoft Excel" en segundo plano y finalízalos.</li>
                            <li>Desactiva el panel de vista previa en tu explorador de archivos si lo tienes abierto.</li>
                         </ol>';
            
            // Adjuntar detalle técnico en un bloque colapsable para diagnóstico
            $errors[] = '<details class="mt-2 text-start"><summary class="text-muted small" style="cursor:pointer; outline:none;">Ver detalle técnico del servidor</summary>' .
                        '<pre class="bg-light p-2 rounded small mt-1 text-dark" style="font-family:monospace; font-size:0.8rem; border:1px solid #ddd;">' .
                        "- Origen temporal (tmp_name): " . htmlspecialchars($tmpName ?? 'No definido') . "\n" .
                        "- Existe al iniciar: NO\n" .
                        "- Tamaño al iniciar: N/A\n" .
                        "- Legible al iniciar: NO\n" .
                        "- Es archivo subido: NO\n" .
                        "- Error PHP: Ninguno registrado por PHP." .
                        '</pre></details>';
        } elseif ($size_at_start === 0) {
            $errors[] = '<strong>Archivo Vacío (0 bytes):</strong> El archivo recibido en el servidor no tiene contenido.';
            $errors[] = 'Esto ocurre comúnmente cuando el archivo original está bloqueado por otro programa en tu computadora, haciendo que el navegador envíe un archivo vacío de 0 bytes.';
            $errors[] = '<strong>Soluciones rápidas:</strong>
                         <ol>
                            <li><strong>La solución definitiva:</strong> Haz una copia del archivo (clic derecho -> Copiar y Pegar en el Escritorio) e intenta subir la copia. Esto rompe cualquier bloqueo del sistema.</li>
                            <li>Cierra Microsoft Excel por completo.</li>
                            <li>Si el archivo está en OneDrive, espera a que termine de sincronizar o colócalo en una carpeta no sincronizada local.</li>
                         </ol>';
            
            $errors[] = '<details class="mt-2 text-start"><summary class="text-muted small" style="cursor:pointer; outline:none;">Ver detalle técnico del servidor</summary>' .
                        '<pre class="bg-light p-2 rounded small mt-1 text-dark" style="font-family:monospace; font-size:0.8rem; border:1px solid #ddd;">' .
                        "- Origen temporal (tmp_name): " . htmlspecialchars($tmpName ?? 'No definido') . "\n" .
                        "- Existe al iniciar: SÍ\n" .
                        "- Tamaño al iniciar: 0 bytes\n" .
                        "- Legible al iniciar: SÍ\n" .
                        "- Es archivo subido: SÍ\n" .
                        "- Error PHP: Ninguno registrado por PHP." .
                        '</pre></details>';
        } else {
            // Guardar el archivo en el directorio de cargas del servidor (permisos ya reparados)
            $uploadsDir = realpath(__DIR__ . '/../../uploads');
            if ($uploadsDir === false) {
                $uploadsDir = __DIR__ . '/../../uploads'; // Fallback
            }
            
            $targetXls = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.xls';
            $targetCsv = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.csv';
            
            $moved = move_uploaded_file($tmpName, $targetXls);
            
            if ($moved) {
                // Garantizar directorios de perfil de sistema para evitar fallos de Excel COM en Session 0 (Apache Ejecutado como Servicio)
                if (!is_dir('C:\\Windows\\System32\\config\\systemprofile\\Desktop')) {
                    @mkdir('C:\\Windows\\System32\\config\\systemprofile\\Desktop', 0777, true);
                }
                if (!is_dir('C:\\Windows\\SysWOW64\\config\\systemprofile\\Desktop')) {
                    @mkdir('C:\\Windows\\SysWOW64\\config\\systemprofile\\Desktop', 0777, true);
                }

                // Ejecutar el script convertidor de PowerShell
                $psScript = __DIR__ . '/../../includes/convert_xls.ps1';
                $xlsPath = realpath($targetXls);
                
                $cmd = "powershell -NoProfile -ExecutionPolicy Bypass -File \"" . $psScript . "\" \"" . $xlsPath . "\" \"" . $targetCsv . "\" 2>&1";
                exec($cmd, $output, $returnCode);
                
                // Limpiar XLS temporal inmediatamente
                if (file_exists($targetXls)) {
                    unlink($targetXls);
                }
                
                if ($returnCode !== 0 || !file_exists($targetCsv)) {
                    $errors[] = 'Error al convertir el archivo Excel en el servidor. Detalles: <pre class="bg-light p-2 rounded small mt-1 text-dark" style="font-family:monospace; font-size:0.8rem; border:1px solid #ddd;">' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                } else {
                    // Procesar el CSV
                    $handle = fopen($targetCsv, 'r');
                    if ($handle === false) {
                        $errors[] = 'No se pudo abrir el archivo de intercambio temporal.';
                    } else {
                        // Detectar delimitador
                        $firstLine = fgets($handle);
                        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                        rewind($handle);
                        
                        try {
                            $db->beginTransaction();
                            
                            $ficha_numero = '';
                            $programa_codigo = '';
                            $programa_nombre = '';
                            $fecha_inicio = null;
                            $fecha_fin = null;
                            
                            $rowNum = 0;
                            $headers_found = false;
                            
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
                                'warnings' => []
                            ];
                            
                            $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                            $password_hash = password_hash('Sena2026', PASSWORD_DEFAULT);
                            
                            // 1. Leer cabecera para metadatos de ficha/programa
                            while (($data = fgetcsv($handle, 2000, $delimiter)) !== false) {
                                $rowNum++;
                                
                                // Sanitizar todas las celdas a UTF-8
                                foreach ($data as $k => $v) {
                                    $data[$k] = toUtf8(trim($v));
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
                                if ($rowNum >= 10 && isset($data[0]) && (stripos($data[0], 'Tipo') !== false) && stripos($data[1], 'Documento') !== false) {
                                    $headers_found = true;
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
                            $stmt = $db->prepare("SELECT id FROM fichas WHERE numero_ficha = ?");
                            $stmt->execute([$ficha_numero]);
                            $ficha_db = $stmt->fetch();
                            
                            if ($ficha_db) {
                                $ficha_id = (int)$ficha_db['id'];
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
                            while (($data = fgetcsv($handle, 2000, $delimiter)) !== false) {
                                $rowNum++;
                                
                                // Sanitizar a UTF-8
                                foreach ($data as $k => $v) {
                                    $data[$k] = toUtf8(trim($v));
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
                                    }
                                } else {
                                    // Insertar evaluación
                                    $stmtInsertEval = $db->prepare("
                                        INSERT INTO evaluaciones (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, fecha_evaluacion)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmtInsertEval->execute([$ra_id, $aprendiz_id, $instructor_id, $ficha_id, $concepto, $fecha_eval]);
                                    $stats['evaluaciones_creadas']++;
                                }
                            }
                            
                            $db->commit();
                            $import_summary = $stats;
                            $successMessage = "¡Carga masiva finalizada con éxito! Todos los registros fueron procesados.";
                            
                        } catch (Exception $e) {
                            $db->rollBack();
                            $errors[] = 'Error al procesar el contenido de la importación: ' . htmlspecialchars($e->getMessage());
                        }
                        
                        fclose($handle);
                    }
                    
                    // Limpiar CSV temporal
                    if (file_exists($targetCsv)) {
                        unlink($targetCsv);
                    }
                }
            } else {
                $lastError = error_get_last();
                $errors[] = 'No se pudo guardar el archivo en el directorio de cargas del servidor.';
                $errors[] = 'Detalle técnico:';
                $errors[] = '- Origen temporal (tmp_name): ' . htmlspecialchars($tmpName ?? 'No definido');
                $errors[] = '- Destino (target_xls): ' . htmlspecialchars($targetXls ?? 'No definido');
                $errors[] = '- Existe al iniciar: ' . ($exists_at_start ? 'SÍ' : 'NO');
                $errors[] = '- Tamaño al iniciar: ' . ($size_at_start !== -1 ? $size_at_start . ' bytes' : 'N/A');
                $errors[] = '- Legible al iniciar: ' . ($readable_at_start ? 'SÍ' : 'NO');
                $errors[] = '- Es archivo subido: ' . ($is_uploaded_at_start ? 'SÍ' : 'NO');
                $errors[] = '- Error PHP: ' . htmlspecialchars($lastError['message'] ?? 'Ninguno registrado por PHP.');
            }
        }
    }
}

$pageTitle = 'Importar Juicios Sofia Plus · SENA';
$contentView = __FILE__;

if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="mb-1">Importar Juicios desde Excel</h1>
    <p class="text-muted mb-0">Carga el reporte de juicios evaluativos exportado de Sofia Plus para registrar notas, fichas y aprendices automáticamente.</p>
  </div>
  <div>
    <a href="<?= MODULES_PATH ?>/evaluaciones/" class="btn btn-soft">
      <i class="bi bi-arrow-left me-2"></i>Volver a Evaluaciones
    </a>
  </div>
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
        <form method="POST" enctype="multipart/form-data">
          <p class="text-muted small mb-4">
            Selecciona el archivo binario de Excel (<strong>.xls</strong>) exportado de Sofia Plus. El sistema lo convertirá y procesará para crear la ficha, los aprendices, las competencias y todos los juicios de evaluación.
          </p>
          
          <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Archivo Excel (.xls)</label>
            <input type="file" name="excel_file" class="form-control" accept=".xls" required>
            <div class="form-text text-muted" style="font-size:0.75rem;">
              * Asegúrate de no alterar el archivo original descargado de Sofia Plus.
            </div>
          </div>
          
          <div class="d-grid">
            <button type="submit" class="btn btn-primary py-2.5 fw-bold">
              <i class="bi bi-cloud-arrow-up me-2"></i>Iniciar Carga Masiva
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
