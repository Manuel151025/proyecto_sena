<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Shuchkin\SimpleXLS;
use Exception;
use PDO;

class JuiciosImportService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    private function toUtf8(?string $str): string {
        if ($str === null || $str === '') return '';
        if (!mb_check_encoding($str, 'UTF-8')) {
            return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
        }
        return $str;
    }

    /**
     * Procesa la importación de un archivo Excel XLS.
     * 
     * @param string $fileTmpPath Ruta del archivo temporal
     * @param string $originalName Nombre original del archivo
     * @param int $userId ID del usuario que importa
     * @param string $userRole Rol del usuario
     * @return array Resumen de importación
     * @throws Exception
     */
    public function import(string $fileTmpPath, string $originalName, int $userId, string $userRole): array {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'xls') {
            throw new Exception('El archivo debe tener extensión .xls (Reporte binario de Sofia Plus).');
        }

        // Crear directorio uploads si no existe
        $uploadsDir = realpath(__DIR__ . '/../../uploads');
        if ($uploadsDir === false) {
            $uploadsDir = __DIR__ . '/../../uploads';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0777, true);
            }
        }

        $targetXls = $uploadsDir . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.xls';
        
        // Guardar archivo temporal
        if (is_uploaded_file($fileTmpPath)) {
            if (!move_uploaded_file($fileTmpPath, $targetXls)) {
                throw new Exception('Error al guardar el archivo subido en el servidor.');
            }
        } else {
            if (!copy($fileTmpPath, $targetXls)) {
                throw new Exception('Error al copiar el archivo temporal en el servidor.');
            }
        }

        $xls = SimpleXLS::parseFile($targetXls);

        // Limpiar archivo temporal inmediatamente
        if (file_exists($targetXls)) {
            unlink($targetXls);
        }

        if (!$xls) {
            $xlsErr = (string)SimpleXLS::parseError();
            @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . 'SimpleXLS parseError: ' . $xlsErr . "\n", FILE_APPEND);
            throw new Exception('Error al leer el archivo Excel: ' . $xlsErr);
        }

        $allRows = $xls->rows(0); // Primera hoja
        if (empty($allRows)) {
            @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . "SimpleXLS: rows array is empty.\n", FILE_APPEND);
            throw new Exception('El archivo Excel está vacío o no contiene datos legibles.');
        }

        try {
            $this->db->beginTransaction();

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
                    $data[$k] = $this->toUtf8(trim((string)$v));
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
            $stmt = $this->db->prepare("SELECT id FROM programas WHERE codigo = ?");
            $stmt->execute([$programa_codigo]);
            $programa_id = $stmt->fetchColumn();
            if ($programa_id) {
                $programa_id = (int)$programa_id;
            } else {
                $stmtInsertProg = $this->db->prepare("
                    INSERT INTO programas (nombre, codigo, duracion_horas, estado)
                    VALUES (?, ?, 0, 'activo')
                ");
                $stmtInsertProg->execute([$programa_nombre ?: 'Programa Importado', $programa_codigo]);
                $programa_id = (int)$this->db->lastInsertId();
            }

            // 3. Registrar / Obtener Ficha
            $stmt = $this->db->prepare("SELECT id, instructor_id FROM fichas WHERE numero_ficha = ?");
            $stmt->execute([$ficha_numero]);
            $ficha_db = $stmt->fetch();

            if ($ficha_db) {
                $ficha_id = (int)$ficha_db['id'];
                if ($userRole === ROL_INSTRUCTOR && (int)$ficha_db['instructor_id'] !== $userId) {
                    throw new Exception("No tienes permisos para importar juicios en la ficha #$ficha_numero porque está asignada a otro instructor.");
                }
                $stats['ficha_estado'] = 'Actualizada (ya existía)';
            } else {
                $default_instructor_id = $userId;
                if ($userRole !== ROL_INSTRUCTOR) {
                    $stmtInst = $this->db->query("SELECT id FROM usuarios WHERE rol = 'instructor' LIMIT 1");
                    $default_instructor_id = (int)($stmtInst->fetchColumn() ?: $userId);
                }

                $stmtInsertFicha = $this->db->prepare("
                    INSERT INTO fichas (numero_ficha, programa_id, instructor_id, coordinador_id, estado, cantidad_aprendices, fecha_inicio, fecha_fin)
                    VALUES (?, ?, ?, ?, 'ejecucion', 0, ?, ?)
                ");
                $stmtInsertFicha->execute([$ficha_numero, $programa_id, $default_instructor_id, 1, $fecha_inicio, $fecha_fin]);
                $ficha_id = (int)$this->db->lastInsertId();
                $stats['ficha_estado'] = 'Creada e inicializada';
            }

            // Caché
            $aprendices_cache = [];
            $competencias_cache = [];
            $ras_cache = [];

            // 4. Leer registros de aprendices y calificaciones
            $dataStartIndex = $headers_row_index + 1;
            for ($ri = $dataStartIndex; $ri < count($allRows); $ri++) {
                $data = $allRows[$ri];
                if (count($data) < 8 || empty($data[1])) {
                    continue; 
                }

                foreach ($data as $k => $v) {
                    $data[$k] = $this->toUtf8(trim((string)$v));
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
                    $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $email = "aprendiz_" . $num_doc . "@sena.edu.co";
                    $stmt->execute([$email]);
                    $usuario_id = $stmt->fetchColumn();

                    if ($usuario_id) {
                        $usuario_id = (int)$usuario_id;
                    } else {
                        $avatar_color = $colors[array_rand($colors)];
                        $stmtInsertUsr = $this->db->prepare("
                            INSERT INTO usuarios (nombre, email, password, rol, avatar_color, estado)
                            VALUES (?, ?, ?, 'aprendiz', ?, 'activo')
                        ");
                        $stmtInsertUsr->execute([$nombre_completo, $email, $password_hash, $avatar_color]);
                        $usuario_id = (int)$this->db->lastInsertId();
                    }

                    $stmt = $this->db->prepare("SELECT id FROM aprendices WHERE numero_documento = ?");
                    $stmt->execute([$num_doc]);
                    $ap_db = $stmt->fetch();

                    $estado_sena = 'matriculado';
                    if (stripos($estado_matricula, 'retiro') !== false || stripos($estado_matricula, 'deser') !== false) {
                        $estado_sena = 'desertado';
                    }

                    if ($ap_db) {
                        $aprendiz_id = (int)$ap_db['id'];
                        $stmtUpdateAp = $this->db->prepare("UPDATE aprendices SET ficha_id = ?, estado = ? WHERE id = ?");
                        $stmtUpdateAp->execute([$ficha_id, $estado_sena, $aprendiz_id]);
                        $stats['aprendices_actualizados']++;
                    } else {
                        $stmtInsertAp = $this->db->prepare("
                            INSERT INTO aprendices (usuario_id, ficha_id, numero_documento, tipo_documento, genero, estado)
                            VALUES (?, ?, ?, ?, 'O', ?)
                        ");
                        $stmtInsertAp->execute([$usuario_id, $ficha_id, $num_doc, $tipo_doc, $estado_sena]);
                        $aprendiz_id = (int)$this->db->lastInsertId();
                        $stats['aprendices_creados']++;

                        $this->db->prepare("UPDATE fichas SET cantidad_aprendices = cantidad_aprendices + 1 WHERE id = ?")->execute([$ficha_id]);
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
                    $stmt = $this->db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
                    $stmt->execute([$programa_id, $compCode]);
                    $competencia_id = $stmt->fetchColumn();

                    if ($competencia_id) {
                        $competencia_id = (int)$competencia_id;
                    } else {
                        $stmtInsertComp = $this->db->prepare("
                            INSERT INTO competencias (programa_id, codigo, nombre, estado)
                            VALUES (?, ?, ?, 'activo')
                        ");
                        $stmtInsertComp->execute([$programa_id, $compCode, $compName]);
                        $competencia_id = (int)$this->db->lastInsertId();
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
                    $stmt = $this->db->prepare("SELECT id FROM resultados_aprendizaje WHERE competencia_id = ? AND codigo = ?");
                    $stmt->execute([$competencia_id, $raCode]);
                    $ra_id = $stmt->fetchColumn();

                    if ($ra_id) {
                        $ra_id = (int)$ra_id;
                    } else {
                        $stmtInsertRa = $this->db->prepare("
                            INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion, estado)
                            VALUES (?, ?, ?, 'activo')
                        ");
                        $stmtInsertRa->execute([$competencia_id, $raCode, $raDenom]);
                        $ra_id = (int)$this->db->lastInsertId();
                        $stats['ras_creados']++;
                    }
                    $ras_cache[$raCode] = $ra_id;
                }

                // --- PROCESAR EVALUACION ---
                $concepto = 'pendiente';
                if (stripos($juicio_str, 'APROBADO') !== false) {
                    $concepto = 'A';
                } elseif (stripos($juicio_str, 'DEFICIENTE') !== false || stripos($juicio_str, 'NO APROBADO') !== false) {
                    $concepto = 'D';
                }

                $instructor_id = $userId;
                if (!empty($instructor_raw)) {
                    $instDoc = '';
                    if (preg_match('/^CC\s+(\d+)/i', $instructor_raw, $instM)) {
                        $instDoc = $instM[1];
                    }
                    if ($instDoc) {
                        $stmtInst = $this->db->prepare("
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

                $stmt = $this->db->prepare("
                    SELECT id, concepto FROM evaluaciones
                    WHERE resultado_aprendizaje_id = ? AND aprendiz_id = ?
                ");
                $stmt->execute([$ra_id, $aprendiz_id]);
                $eval_db = $stmt->fetch();

                $fecha_eval = null;
                if ($concepto !== 'pendiente') {
                    $fecha_eval = date('Y-m-d');
                    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $fecha_juicio_str, $fM)) {
                        $fecha_eval = "{$fM[3]}-{$fM[2]}-{$fM[1]}";
                    }
                }

                $eval_accion = 'Sin cambios';
                if ($eval_db) {
                    $eval_id = (int)$eval_db['id'];
                    $concepto_anterior = $eval_db['concepto'];

                    if ($concepto_anterior !== $concepto) {
                        $stmtUpdateEval = $this->db->prepare("
                            UPDATE evaluaciones
                            SET concepto = ?, instructor_id = ?, fecha_evaluacion = ?, fecha_actualizacion = NOW()
                            WHERE id = ?
                        ");
                        $stmtUpdateEval->execute([$concepto, $instructor_id, $fecha_eval, $eval_id]);

                        $stmtHist = $this->db->prepare("
                            INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo)
                            VALUES (?, ?, ?, ?, 'Importado masivo desde reporte Sofia Plus (Excel)')
                        ");
                        $stmtHist->execute([$eval_id, $userId, $concepto_anterior, $concepto]);
                        $stats['evaluaciones_actualizadas']++;
                        $eval_accion = 'Actualizado';
                    }
                } else {
                    $stmtInsertEval = $this->db->prepare("
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

            $this->db->commit();
            return $stats;
        } catch (Exception $e) {
            $this->db->rollBack();
            @file_put_contents(__DIR__ . '/../../logs/import_errors.log', date('[Y-m-d H:i:s] ') . 'Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            throw $e;
        }
    }
}
