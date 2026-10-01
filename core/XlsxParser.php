<?php
declare(strict_types=1);

namespace Core;

use DateTimeImmutable;
use Exception;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lector mínimo de libros .xlsx (Office Open XML) para las importaciones.
 *
 * Devuelve la PRIMERA hoja del libro (la primera que ve el usuario según
 * xl/workbook.xml) como filas de texto. Antes:
 *  - Solo leía «xl/worksheets/sheet1.xml»: un libro cuya primera hoja se
 *    guarda con otro nombre (al reordenar o borrar hojas) no se podía leer.
 *  - Ignoraba las cadenas en línea (t="inlineStr"), que usan muchos
 *    generadores, incluido el Exportador de este sistema: un listado
 *    exportado, editado y vuelto a importar se leía con 0 filas.
 *  - Las fechas llegaban como el número de serie de Excel (45730) y el
 *    importador las rechazaba como fecha inválida.
 *  - Una celda sin coordenada (r="B2"), legal en el formato, se perdía.
 */
final class XlsxParser {
    /** Formatos de número de Excel que son fechas (ids integrados). */
    private const FORMATOS_FECHA = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57];

    /**
     * @return list<list<string>> Filas densas (las celdas vacías como '').
     * @throws Exception si el archivo no es un libro legible.
     */
    public static function parse(string $archivo): array {
        $zip = new ZipArchive();
        if ($zip->open($archivo) !== true) {
            throw new Exception('No se pudo abrir el archivo Excel.');
        }
        try {
            $hoja = self::primeraHoja($zip);
            $xml = $zip->getFromName($hoja);
            if ($xml === false) {
                throw new Exception('El libro no contiene una hoja de cálculo legible.');
            }
            $compartidas = self::cadenasCompartidas($zip);
            $fechas = self::estilosDeFecha($zip);
        } finally {
            $zip->close();
        }
        return self::filas(new SimpleXMLElement($xml), $compartidas, $fechas);
    }

    /**
     * Ruta, dentro del archivo, de la primera hoja del libro. Sigue
     * workbook.xml y sus relaciones; si faltan, la convención sheet1.xml.
     */
    public static function primeraHoja(ZipArchive $zip): string {
        $libro = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro !== false && $relaciones !== false) {
            try {
                $wb = new SimpleXMLElement($libro);
                $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $hojas = $wb->xpath('//m:sheets/m:sheet');
                if ($hojas) {
                    $rid = (string)($hojas[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
                    $rels = new SimpleXMLElement($relaciones);
                    foreach ($rels->Relationship as $rel) {
                        if ((string)$rel['Id'] === $rid) {
                            $destino = ltrim((string)$rel['Target'], '/');
                            $ruta = str_starts_with($destino, 'xl/') ? $destino : 'xl/' . $destino;
                            if ($zip->statName($ruta) !== false) {
                                return $ruta;
                            }
                        }
                    }
                }
            } catch (Exception) {
                // Libro con XML dañado: se intenta la convención.
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @return list<string> */
    private static function cadenasCompartidas(ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $r = [];
        foreach ((new SimpleXMLElement($xml))->si as $si) {
            $r[] = self::texto($si);
        }
        return $r;
    }

    /**
     * Texto de un <si> o un <is>: un único <t> o varias «corridas» con
     * formato (<r><t>…</t></r>). Las anotaciones fonéticas (<rPh>) no cuentan.
     */
    private static function texto(SimpleXMLElement $nodo): string {
        if (isset($nodo->t)) {
            return (string)$nodo->t;
        }
        $t = '';
        foreach ($nodo->r as $r) {
            $t .= (string)$r->t;
        }
        return $t;
    }

    /**
     * Índices de estilo de celda (atributo s) cuyo formato es de fecha.
     * @return array<int, true>
     */
    private static function estilosDeFecha(ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }
        try {
            $estilos = new SimpleXMLElement($xml);
        } catch (Exception) {
            return [];
        }
        $propios = [];
        foreach ($estilos->numFmts->numFmt ?? [] as $f) {
            $propios[(int)$f['numFmtId']] = self::esFormatoFecha((string)$f['formatCode']);
        }
        $r = [];
        $i = 0;
        foreach ($estilos->cellXfs->xf ?? [] as $xf) {
            $id = (int)$xf['numFmtId'];
            if (in_array($id, self::FORMATOS_FECHA, true) || ($propios[$id] ?? false)) {
                $r[$i] = true;
            }
            $i++;
        }
        return $r;
    }

    /** ¿El código de formato pinta una fecha? (dd/mm/aaaa, yyyy-mm-dd, d-mmm…) */
    private static function esFormatoFecha(string $codigo): bool {
        // Fuera literales entre comillas, escapes y secciones [color]/[$-es-CO].
        $c = preg_replace(['/"[^"]*"/', '/\\\\./', '/\[[^\]]*\]/'], '', $codigo) ?? '';
        return (bool)preg_match('/[dy]/i', $c);
    }

    /**
     * @param list<string> $compartidas
     * @param array<int, true> $fechas
     * @return list<list<string>>
     */
    private static function filas(SimpleXMLElement $hoja, array $compartidas, array $fechas): array {
        $filas = [];
        $siguienteFila = 0;
        foreach ($hoja->sheetData->row ?? [] as $row) {
            $numero = isset($row['r']) ? (int)$row['r'] - 1 : $siguienteFila;
            $siguienteFila = $numero + 1;
            $celdas = [];
            $siguienteCol = 0;
            foreach ($row->c as $c) {
                $col = isset($c['r']) && preg_match('/^([A-Z]{1,3})\d+$/', (string)$c['r'], $m)
                    ? self::indiceColumna($m[1])
                    : $siguienteCol;
                $siguienteCol = $col + 1;
                $celdas[$col] = self::valor($c, $compartidas, $fechas);
            }
            $filas[$numero] = $celdas;
        }
        if ($filas === []) {
            return [];
        }
        ksort($filas);
        $ancho = 0;
        foreach ($filas as $f) {
            if ($f !== []) {
                $ancho = max($ancho, max(array_keys($f)) + 1);
            }
        }
        $densas = [];
        foreach ($filas as $f) {
            $d = [];
            for ($i = 0; $i < $ancho; $i++) {
                $d[] = $f[$i] ?? '';
            }
            $densas[] = $d;
        }
        return $densas;
    }

    /**
     * @param list<string> $compartidas
     * @param array<int, true> $fechas
     */
    private static function valor(SimpleXMLElement $c, array $compartidas, array $fechas): string {
        $tipo = (string)($c['t'] ?? '');
        $v = isset($c->v) ? (string)$c->v : '';
        switch ($tipo) {
            case 's':
                return $compartidas[(int)$v] ?? '';
            case 'inlineStr':
                return isset($c->is) ? self::texto($c->is) : '';
            case 'str':
                return $v;
            case 'b':
                return $v === '1' ? '1' : '0';
            case 'e':
                return '';   // #N/A, #DIV/0!…: una celda con error no es un dato
        }
        if ($v !== '' && isset($fechas[(int)($c['s'] ?? -1)]) && is_numeric($v)) {
            return self::fechaDesdeSerie((float)$v);
        }
        return $v;
    }

    /**
     * Número de serie de Excel (días desde el 30/12/1899) a AAAA-MM-DD, con
     * la hora si la tiene.
     */
    public static function fechaDesdeSerie(float $serie): string {
        $dias = (int)floor($serie);
        $segundos = (int)round(($serie - $dias) * 86400);
        $f = (new DateTimeImmutable('1899-12-30'))->modify("+$dias days")->modify("+$segundos seconds");
        return $segundos > 0 ? $f->format('Y-m-d H:i') : $f->format('Y-m-d');
    }

    private static function indiceColumna(string $letras): int {
        $i = 0;
        foreach (str_split($letras) as $l) {
            $i = $i * 26 + (ord($l) - 64);
        }
        return $i - 1;
    }
}
