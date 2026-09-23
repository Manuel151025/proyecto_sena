<?php
declare(strict_types=1);

namespace Core\Exportacion;

use Core\Support\ErrorDeNegocio;
use ZipArchive;

/**
 * Exportación de tablas a CSV y a Excel (.xlsx real).
 *
 * Antes el "Excel" de los reportes era una tabla HTML servida con extensión
 * .xls: Excel la abría con un aviso de "el formato no coincide con la
 * extensión", sin tipos (los números eran texto) y la ofrecía como
 * potencialmente peligrosa. Aquí se escribe un libro OOXML de verdad con
 * ZipArchive, sin dependencias.
 *
 * Dos defensas que valen para los dos formatos:
 *  - Inyección de fórmulas (CSV/formula injection): una celda que empieza
 *    por = + - @ o por tabulador/retorno se ejecuta como fórmula al abrir
 *    el archivo. El objetivo no es quien exporta sino quien RECIBE el
 *    archivo. Esas celdas se prefijan con un apóstrofo.
 *  - Límite de filas: una exportación no puede convertirse en un volcado
 *    ilimitado de la base (ni agotar la memoria del servidor).
 */
final class Exportador {
    public const MAX_FILAS = 20000;

    /**
     * @param list<string> $encabezados
     * @param iterable<array> $filas Cada fila, lista de valores en el orden de los encabezados.
     */
    public static function csv(string $nombreArchivo, array $encabezados, iterable $filas): string {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF"); // BOM: Excel en español lo necesita para leer UTF-8.
        // Punto y coma: el separador de listas de Excel con configuración
        // regional de Colombia. Con coma, todo caía en la primera columna.
        fputcsv($h, array_map([self::class, 'celdaSegura'], $encabezados), ';', '"', '');
        $n = 0;
        foreach ($filas as $fila) {
            if (++$n > self::MAX_FILAS) {
                break;
            }
            fputcsv($h, array_map([self::class, 'celdaSegura'], array_values($fila)), ';', '"', '');
        }
        rewind($h);
        $contenido = (string)stream_get_contents($h);
        fclose($h);
        return $contenido;
    }

    /**
     * @param list<string> $encabezados
     * @param iterable<array> $filas
     * @param array{titulo?:string, anchos?:list<int>, estilos?:array} $opciones
     *   `estilos`: columnas con semáforo, en el formato de SemaforoReporte
     *   ('columnas_concepto', 'columna_porcentaje'); esas celdas se pintan
     *   con los mismos colores que el PDF.
     * @return string Contenido binario del .xlsx
     */
    public static function xlsx(string $hoja, array $encabezados, iterable $filas, array $opciones = []): string {
        if (!class_exists(ZipArchive::class)) {
            throw new ErrorDeNegocio('El servidor no tiene la extensión zip de PHP: no se puede generar Excel. Usa la exportación CSV.');
        }
        $filasXml = [];
        $r = 1;
        $titulo = $opciones['titulo'] ?? '';
        $inicioTabla = 1;
        if ($titulo !== '') {
            $filasXml[] = '<row r="1"><c r="A1" t="inlineStr" s="2"><is><t>' . self::xml($titulo) . '</t></is></c></row>';
            $r = 3;
            $inicioTabla = 3;
        }
        $filasXml[] = self::filaXml($r++, $encabezados, 1);
        $n = 0;
        foreach ($filas as $fila) {
            if (++$n > self::MAX_FILAS) {
                break;
            }
            $filasXml[] = self::filaXml($r++, array_values($fila), 0, $opciones['estilos'] ?? []);
        }

        $cols = '';
        $anchos = $opciones['anchos'] ?? [];
        if ($anchos === []) {
            foreach ($encabezados as $e) {
                $anchos[] = max(10, min(60, mb_strlen($e) + 4));
            }
        }
        foreach ($anchos as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (int)$w . '" customWidth="1"/>';
        }
        $ultimaCol = self::columna(max(0, count($encabezados) - 1));
        $ultimaFila = $r - 1;

        $hojaXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $inicioTabla . '" topLeftCell="A' . ($inicioTabla + 1)
            . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . implode('', $filasXml) . '</sheetData>'
            . ($ultimaFila > $inicioTabla ? '<autoFilter ref="A' . $inicioTabla . ':' . $ultimaCol . $ultimaFila . '"/>' : '')
            . '</worksheet>';

        $nombreHoja = self::xml(mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $hoja) ?: 'Datos', 0, 31));
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new ErrorDeNegocio('No se pudo generar el archivo de Excel.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::xml($titulo !== '' ? $titulo : $hoja) . '</dc:title>'
            . '<dc:creator>SENA · Seguimiento de Proyectos Formativos</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '</cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $nombreHoja . '" sheetId="1" r:id="rId1"/></sheets>'
            . ($ultimaFila > $inicioTabla ? '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $nombreHoja . '\'!$A$' . $inicioTabla . ':$' . $ultimaCol . '$' . $ultimaFila . '</definedName></definedNames>' : '')
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        // Estilos: 0 normal, 1 encabezado (negrita, fondo verde SENA, texto blanco), 2 título.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="6"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF00324D"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFA51D24"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFB06000"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF137333"/><name val="Calibri"/></font></fonts>'
            . '<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF39A900"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE8E6"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF7E0"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE6F4EA"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="1" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            // 3 crítico, 4 riesgo, 5 al día: los colores del semáforo del PDF.
            . '<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="5" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="top"/></xf></cellXfs>'
            . '</styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $hojaXml);
        $zip->close();

        $bin = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    /** Envía el archivo al navegador y termina. */
    public static function descargar(string $contenido, string $nombreArchivo, string $formato): never {
        $nombreArchivo = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $nombreArchivo) ?: 'exportacion';
        $tipo = match ($formato) {
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf'   => 'application/pdf',
            default => 'text/csv; charset=utf-8',
        };
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($contenido));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        echo $contenido;
        exit;
    }

    /**
     * Neutraliza la inyección de fórmulas en hojas de cálculo (OWASP CSV
     * Injection): si la celda empieza por un carácter que Excel/LibreOffice
     * interpretan como fórmula, se antepone un apóstrofo.
     */
    public static function celdaSegura(mixed $v): string {
        $s = $v === null ? '' : (is_bool($v) ? ($v ? 'Sí' : 'No') : (string)$v);
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s)) {
            return "'" . $s;
        }
        return $s;
    }

    private const ESTILO_SEMAFORO = ['critico' => 3, 'riesgo' => 4, 'aldia' => 5];

    private static function filaXml(int $r, array $valores, int $estilo, array $semaforo = []): string {
        $celdas = '';
        foreach ($valores as $i => $v) {
            $ref = self::columna($i) . $r;
            $propio = $semaforo !== [] ? (self::ESTILO_SEMAFORO[\Core\Services\SemaforoReporte::clase($i, (string)($v ?? ''), $semaforo)] ?? $estilo) : $estilo;
            $s = $propio > 0 ? ' s="' . $propio . '"' : '';
            if ($estilo === 0 && (is_int($v) || is_float($v)) && is_finite((float)$v)) {
                $celdas .= '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
                continue;
            }
            $texto = self::celdaSegura($v);
            if ($texto === '') {
                continue;
            }
            $celdas .= '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . self::xml($texto) . '</t></is></c>';
        }
        return '<row r="' . $r . '">' . $celdas . '</row>';
    }

    private static function columna(int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    /** Texto seguro para XML 1.0: escapado y sin caracteres prohibidos. */
    private static function xml(string $s): string {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
        return htmlspecialchars(mb_substr($s, 0, 32000), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
