<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;
use Core\XlsxParser;
use Shuchkin\SimpleXLS;
use ZipArchive;

/**
 * Lee un archivo tabular (CSV, XLSX o XLS) y devuelve sus filas como
 * texto limpio, con límites.
 *
 * Cada importador del sistema tenía su propia lectura, y las cuatro
 * compartían los mismos huecos:
 *  - `fgetcsv($h, 1000, ...)`: una línea de más de 1.000 bytes se partía
 *    en dos filas y la segunda se validaba como un registro aparte.
 *  - Separador decidido por "¿hay algún ; en la primera línea?": un nombre
 *    con punto y coma en un CSV separado por comas lo desbarataba.
 *  - Sin codificación: el CSV que guarda Excel en Windows viene en
 *    Windows-1252, y "Muñoz" llegaba como "Mu�oz" a la base.
 *  - El BOM de UTF-8 quedaba pegado al primer encabezado.
 *  - Sin tope de filas ni de tamaño descomprimido: un .xlsx de 1 MB puede
 *    ocupar cientos de MB al descomprimirse (bomba zip).
 */
final class LectorTabular {
    public const EXTENSIONES = ['csv', 'xlsx', 'xls'];
    public const MAX_MB = 5;
    /** Tamaño máximo DESCOMPRIMIDO de una hoja .xlsx. */
    private const MAX_XML_BYTES = 40 * 1024 * 1024;
    private const MAX_COLUMNAS = 40;
    private const MAX_CELDA = 2000;

    /**
     * @return array{filas: list<list<string>>, formato: string, codificacion: string, separador: ?string, truncado: bool}
     */
    public static function leer(ArchivoSubido $archivo, int $maxFilas): array {
        return match ($archivo->extension) {
            'csv'  => self::csv($archivo->ruta, $maxFilas),
            'xlsx' => self::xlsx($archivo->ruta, $maxFilas),
            'xls'  => self::xls($archivo->ruta, $maxFilas),
            default => throw new ErrorDeNegocio('Formato no admitido. Usa .csv, .xlsx o .xls.'),
        };
    }

    /** Lee una ruta ya validada (pruebas y comandos de consola). */
    public static function leerRuta(string $ruta, string $extension, int $maxFilas): array {
        return match ($extension) {
            'csv'  => self::csv($ruta, $maxFilas),
            'xlsx' => self::xlsx($ruta, $maxFilas),
            'xls'  => self::xls($ruta, $maxFilas),
            default => throw new ErrorDeNegocio('Formato no admitido.'),
        };
    }

    // -----------------------------------------------------------------

    private static function csv(string $ruta, int $maxFilas): array {
        $bruto = (string)file_get_contents($ruta);
        if (str_starts_with($bruto, "\xEF\xBB\xBF")) {
            $bruto = substr($bruto, 3);
        }
        $codificacion = 'UTF-8';
        if (!mb_check_encoding($bruto, 'UTF-8')) {
            // Excel en Windows guarda el CSV en Windows-1252.
            $bruto = mb_convert_encoding($bruto, 'UTF-8', 'Windows-1252');
            $codificacion = 'Windows-1252';
        }

        $separador = self::detectarSeparador($bruto);
        $h = fopen('php://temp', 'r+');
        fwrite($h, $bruto);
        rewind($h);

        $filas = [];
        $truncado = false;
        // Longitud 0: sin límite de línea. El límite real lo pone el
        // tamaño máximo del archivo (MAX_MB), ya comprobado.
        while (($campos = fgetcsv($h, 0, $separador, '"', '')) !== false) {
            if ($campos === [null]) {
                continue;   // línea en blanco
            }
            if (count($filas) >= $maxFilas + 1) {   // +1 por el encabezado
                $truncado = true;
                break;
            }
            $filas[] = self::limpiarFila($campos);
        }
        fclose($h);
        return ['filas' => self::sinFilasVacias($filas), 'formato' => 'CSV', 'codificacion' => $codificacion,
                'separador' => $separador, 'truncado' => $truncado];
    }

    private static function detectarSeparador(string $contenido): string {
        // Se mira la primera línea con contenido, fuera de comillas.
        $linea = '';
        foreach (preg_split('/\R/', $contenido, 20) ?: [] as $l) {
            if (trim($l) !== '') {
                $linea = $l;
                break;
            }
        }
        $sinComillas = preg_replace('/"[^"]*"/', '', $linea) ?? $linea;
        $mejor = ',';
        $max = 0;
        foreach ([';', ',', "\t", '|'] as $sep) {
            $n = substr_count($sinComillas, $sep);
            if ($n > $max) {
                $max = $n;
                $mejor = $sep;
            }
        }
        return $mejor;
    }

    private static function xlsx(string $ruta, int $maxFilas): array {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new ErrorDeNegocio('El archivo .xlsx está dañado o no es un libro de Excel.');
        }
        foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $entrada) {
            $st = $zip->statName($entrada);
            if ($st !== false && (int)$st['size'] > self::MAX_XML_BYTES) {
                $zip->close();
                throw new ErrorDeNegocio('La hoja de cálculo es demasiado grande una vez descomprimida. Divide el archivo o guárdalo como CSV.');
            }
        }
        if ($zip->statName('xl/worksheets/sheet1.xml') === false) {
            $zip->close();
            throw new ErrorDeNegocio('El libro no tiene una primera hoja legible. Deja los datos en la primera hoja.');
        }
        $zip->close();

        try {
            $filas = XlsxParser::parse($ruta) ?? [];
        } catch (\Throwable $e) {
            throw new ErrorDeNegocio('No se pudo leer el archivo .xlsx. Ábrelo en Excel y guárdalo de nuevo, o expórtalo como CSV.');
        }
        return self::normalizarHoja($filas, $maxFilas, 'Excel (.xlsx)');
    }

    private static function xls(string $ruta, int $maxFilas): array {
        $xls = SimpleXLS::parseFile($ruta);
        if (!$xls) {
            throw new ErrorDeNegocio('No se pudo leer el archivo .xls. Guárdalo como .xlsx o CSV e inténtalo de nuevo.');
        }
        return self::normalizarHoja($xls->rows(0), $maxFilas, 'Excel 97-2003 (.xls)');
    }

    private static function normalizarHoja(array $filas, int $maxFilas, string $formato): array {
        $salida = [];
        $truncado = false;
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            if (count($salida) >= $maxFilas + 1) {
                $truncado = true;
                break;
            }
            // XlsxParser devuelve índices dispersos (celdas vacías ausentes).
            $max = $fila === [] ? -1 : max(array_keys($fila));
            $densa = [];
            for ($i = 0; $i <= min($max, self::MAX_COLUMNAS - 1); $i++) {
                $densa[] = $fila[$i] ?? '';
            }
            $salida[] = self::limpiarFila($densa);
        }
        return ['filas' => self::sinFilasVacias($salida), 'formato' => $formato, 'codificacion' => 'UTF-8',
                'separador' => null, 'truncado' => $truncado];
    }

    /** @return list<string> */
    private static function limpiarFila(array $campos): array {
        $campos = array_slice($campos, 0, self::MAX_COLUMNAS);
        return array_map(static function ($c): string {
            $c = (string)($c ?? '');
            $c = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $c) ?? '';
            $c = trim(str_replace("\u{00A0}", ' ', $c));
            return mb_substr($c, 0, self::MAX_CELDA, 'UTF-8');
        }, $campos);
    }

    private static function sinFilasVacias(array $filas): array {
        return array_values(array_filter($filas, static fn($f) => implode('', $f) !== ''));
    }

    /**
     * Encabezado comparable: minúsculas, sin tildes ni espacios.
     * "Número de Documento" → "numero_de_documento".
     */
    public static function normalizarEncabezado(string $h): string {
        $h = mb_strtolower(trim($h), 'UTF-8');
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h) ?? '';
        return trim($h, '_');
    }
}
