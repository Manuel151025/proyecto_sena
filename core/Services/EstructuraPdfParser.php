<?php
declare(strict_types=1);

namespace Core\Services;

/**
 * Extrae texto crudo de PDFs de Estructura Curricular / Proyecto Formativo
 * (formato SOFIA Plus) y lo interpreta en arrays estructurados.
 *
 * Extraído de EstructuraController, que mezclaba esta lógica de parseo
 * (sin dependencia de base de datos) con el routing HTTP del controlador,
 * siguiendo el mismo patrón que ya usa JuiciosImportService para Excel.
 */
class EstructuraPdfParser {

    public function extractText(string $filepath): string {
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

    public function parseEstructuraCurricular(string $text): array {
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

    public function parseProyectoFormativo(string $text): array {
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
