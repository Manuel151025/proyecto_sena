<?php
declare(strict_types=1);

namespace Core;

use ZipArchive;
use SimpleXMLElement;
use Exception;

class XlsxParser {
    /**
     * Parsea un archivo .xlsx y retorna un array bidimensional con los datos.
     */
    public static function parse(string $filename): ?array {
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            throw new Exception("No se pudo abrir el archivo Excel.");
        }

        // 1. Leer los Shared Strings (Cadenas compartidas que optimizan el tamaño de Excel)
        $sharedStrings = [];
        $sharedStringsEntry = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsEntry !== false) {
            try {
                $xml = new SimpleXMLElement($sharedStringsEntry);
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } else {
                        $text = '';
                        if (isset($si->r)) {
                            foreach ($si->r as $r) {
                                $text .= (string)$r->t;
                            }
                        }
                        $sharedStrings[] = $text;
                    }
                }
            } catch (Exception $e) {
                // Error parseando shared strings, continuamos sin ellos
            }
        }

        // 2. Leer la primera hoja de cálculo (sheet1.xml)
        $sheetEntry = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetEntry === false) {
            $zip->close();
            throw new Exception("El archivo Excel no contiene una hoja de cálculo válida (sheet1.xml).");
        }

        $xml = new SimpleXMLElement($sheetEntry);
        $rows = [];

        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $rowNode) {
                $rowIndex = (int)$rowNode['r'] - 1; // Índices base 0
                $rows[$rowIndex] = [];
                
                foreach ($rowNode->c as $cell) {
                    $coord = (string)$cell['r'];
                    preg_match('/^([A-Z]+)([0-9]+)$/', $coord, $matches);
                    if (empty($matches)) continue;
                    
                    $colName = $matches[1];
                    $colIndex = self::colNameToIndex($colName);
                    
                    $type = isset($cell['t']) ? (string)$cell['t'] : '';
                    $val = isset($cell->v) ? (string)$cell->v : '';

                    if ($type === 's') { // Tipo Shared String
                        $val = $sharedStrings[(int)$val] ?? '';
                    }

                    $rows[$rowIndex][$colIndex] = $val;
                }
            }
        }
        $zip->close();

        if (empty($rows)) {
            return [];
        }

        // 3. Normalizar filas y columnas (rellenar vacíos)
        $maxCols = 0;
        foreach ($rows as $r) {
            if (count($r) > 0) {
                $maxCols = max($maxCols, max(array_keys($r)) + 1);
            }
        }

        $normalized = [];
        ksort($rows); // Ordenar las filas por su número de fila
        foreach ($rows as $rowIndex => $r) {
            $normalizedRow = [];
            for ($i = 0; $i < $maxCols; $i++) {
                $normalizedRow[$i] = $r[$i] ?? '';
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * Convierte la letra de una columna (A, B, C... AA) a un índice de columna numérico base 0.
     */
    private static function colNameToIndex(string $col): int {
        $len = strlen($col);
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }
        return $idx - 1;
    }
}
