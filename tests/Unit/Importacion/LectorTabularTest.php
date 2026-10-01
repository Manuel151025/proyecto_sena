<?php
declare(strict_types=1);

namespace Tests\Unit\Importacion;

use Core\Exportacion\Exportador;
use Core\Importacion\LectorTabular;
use Core\Support\ErrorDeNegocio;
use Core\XlsxParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;
use ZipArchive;

/**
 * Lectura de los archivos que sube la coordinación y los instructores (CSV
 * de Excel en Windows, .xlsx de Excel, Google Sheets o del propio sistema).
 * Si el lector se equivoca, la vista previa enseña datos que no son, o se
 * importan cero filas sin explicación.
 */
final class LectorTabularTest extends CasoDePrueba {
    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void {
        foreach ($this->temporales as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function archivo(string $contenido, string $extension): string {
        $ruta = tempnam(sys_get_temp_dir(), 'lt') . '.' . $extension;
        file_put_contents($ruta, $contenido);
        $this->temporales[] = $ruta;
        return $ruta;
    }

    private function csv(string $contenido, int $maxFilas = 100): array {
        return LectorTabular::leerRuta($this->archivo($contenido, 'csv'), 'csv', $maxFilas);
    }

    /**
     * Libro .xlsx mínimo. $hojas: ruta dentro del zip => XML de sheetData.
     * La primera de $orden es la que el libro declara como primera hoja.
     */
    private function libro(array $hojas, ?array $orden = null, string $compartidas = '', string $estilos = ''): string {
        $ruta = tempnam(sys_get_temp_dir(), 'lx') . '.xlsx';
        $this->temporales[] = $ruta;
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $orden ??= array_keys($hojas);
        $sheets = $rels = '';
        foreach ($orden as $i => $destino) {
            $n = $i + 1;
            $sheets .= "<sheet name=\"Hoja$n\" sheetId=\"$n\" r:id=\"rId$n\"/>";
            $rels .= "<Relationship Id=\"rId$n\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"" . substr($destino, 3) . '"/>';
        }
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
        foreach ($hojas as $destino => $datos) {
            $zip->addFromString($destino, '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $datos . '</sheetData></worksheet>');
        }
        if ($compartidas !== '') {
            $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $compartidas . '</sst>');
        }
        if ($estilos !== '') {
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $estilos . '</styleSheet>');
        }
        $zip->close();
        return $ruta;
    }

    // -----------------------------------------------------------------
    // CSV
    // -----------------------------------------------------------------

    #[DataProvider('separadores')]
    #[TestDox('detecta el separador del CSV: $_dataName')]
    public function testSeparador(string $sep): void {
        $r = $this->csv("documento{$sep}nombre{$sep}ficha\n1117500123{$sep}LAURA VARGAS{$sep}2845671\n");
        $this->assertSame($sep, $r['separador']);
        $this->assertSame([['documento', 'nombre', 'ficha'], ['1117500123', 'LAURA VARGAS', '2845671']], $r['filas']);
    }

    public static function separadores(): array {
        return ['punto y coma' => [';'], 'coma' => [','], 'tabulador' => ["\t"], 'barra' => ['|']];
    }

    #[TestDox('un título sin separadores en la primera línea no confunde la detección')]
    public function testTituloArriba(): void {
        $r = $this->csv("Reporte de Juicios Evaluativos\n\nFicha;;2845671\nDocumento;Juicio\n1117500123;APROBADO\n");
        $this->assertSame(';', $r['separador']);
        $this->assertContains(['Documento', 'Juicio'], $r['filas']);
    }

    #[TestDox('quita el BOM y lee UTF-8 con tildes y ñ')]
    public function testUtf8ConBom(): void {
        $r = $this->csv("\xEF\xBB\xBFnombre;ciudad\nJOSÉ MUÑOZ;Belén de los Andaquíes\n");
        $this->assertSame('UTF-8', $r['codificacion']);
        $this->assertSame(['nombre', 'ciudad'], $r['filas'][0], 'el BOM quedaba pegado al primer encabezado');
        $this->assertSame(['JOSÉ MUÑOZ', 'Belén de los Andaquíes'], $r['filas'][1]);
    }

    #[TestDox('convierte el CSV que guarda Excel en Windows (Windows-1252)')]
    public function testWindows1252(): void {
        $r = $this->csv(mb_convert_encoding("nombre;ciudad\nJOSÉ MUÑOZ PEÑA;Florencia\n", 'Windows-1252', 'UTF-8'));
        $this->assertSame('Windows-1252', $r['codificacion']);
        $this->assertSame(['JOSÉ MUÑOZ PEÑA', 'Florencia'], $r['filas'][1]);
    }

    #[TestDox('respeta las comillas: separador y salto de línea dentro de un campo')]
    public function testComillas(): void {
        $r = $this->csv("codigo;denominacion\n220501094-01;\"Caracterizar los procesos; según\nel manual\"\n220501094-02;\"Dice \"\"hola\"\"\"\n");
        $this->assertCount(3, $r['filas']);
        $this->assertSame("Caracterizar los procesos; según\nel manual", $r['filas'][1][1]);
        $this->assertSame('Dice "hola"', $r['filas'][2][1]);
    }

    #[TestDox('limpia las celdas: espacios, espacios duros y caracteres de control; descarta filas vacías')]
    public function testLimpieza(): void {
        $r = $this->csv("a;b\n  uno \xC2\xA0;dos\x07\n;\n\n;;\ntres;cuatro\n");
        $this->assertSame([['a', 'b'], ['uno', 'dos'], ['tres', 'cuatro']], $r['filas']);
    }

    #[TestDox('pone tope a las filas y lo avisa; recorta columnas y celdas desmesuradas')]
    public function testTopes(): void {
        $lineas = ["n"];
        for ($i = 1; $i <= 12; $i++) {
            $lineas[] = (string)$i;
        }
        $r = $this->csv(implode("\n", $lineas) . "\n", 10);
        $this->assertTrue($r['truncado']);
        $this->assertCount(11, $r['filas'], 'encabezado + 10 filas');

        $ancha = $this->csv(implode(';', range(1, 60)) . "\n");
        $this->assertCount(40, $ancha['filas'][0]);
        $larga = $this->csv('a;' . str_repeat('x', 5000) . "\n");
        $this->assertSame(2000, mb_strlen($larga['filas'][0][1]));
    }

    #[TestDox('rechaza una extensión que no es de hoja de cálculo')]
    public function testExtensionNoAdmitida(): void {
        $this->expectException(ErrorDeNegocio::class);
        LectorTabular::leerRuta($this->archivo('x', 'txt'), 'txt', 10);
    }

    #[DataProvider('encabezados')]
    #[TestDox('normaliza encabezados para compararlos: «$texto»')]
    public function testNormalizarEncabezado(string $texto, string $esperado): void {
        $this->assertSame($esperado, LectorTabular::normalizarEncabezado($texto));
    }

    public static function encabezados(): array {
        return [
            ['Número de Documento', 'numero_de_documento'],
            ['  CORREO ELECTRÓNICO ', 'correo_electronico'],
            ['Año', 'ano'],
            ['Juicio de Evaluación', 'juicio_de_evaluacion'],
            ['e-mail', 'e_mail'],
        ];
    }

    // -----------------------------------------------------------------
    // XLSX
    // -----------------------------------------------------------------

    /**
     * Regresión: el Exportador escribe cadenas en línea (t="inlineStr") y el
     * lector las ignoraba; exportar, editar y volver a importar daba 0 filas.
     */
    #[TestDox('lee de vuelta el .xlsx que genera el propio sistema (ida y vuelta)')]
    public function testIdaYVuelta(): void {
        $enc = ['nombre', 'email', 'numero_documento', 'ciudad'];
        $filas = [["JOSÉ O'NEILL PEÑA", 'joneill@soy.sena.edu.co', '1117600001', 'Belén de los Andaquíes'],
                  ['ANA MUÑOZ', 'amunoz@soy.sena.edu.co', '1117600002', 'Florencia']];
        $ruta = tempnam(sys_get_temp_dir(), 'ex') . '.xlsx';
        $this->temporales[] = $ruta;
        file_put_contents($ruta, Exportador::xlsx('Matrículas', $enc, $filas));
        $r = LectorTabular::leerRuta($ruta, 'xlsx', 100);
        $this->assertSame([$enc, ...$filas], $r['filas']);
        $this->assertSame('Excel (.xlsx)', $r['formato']);
    }

    #[TestDox('xlsx: cadenas compartidas (también con formato), en línea, de fórmula, booleanos y errores')]
    public function testTiposDeCelda(): void {
        $hoja = '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="inlineStr"><is><t>en línea</t></is></c></row>'
              . '<row r="2"><c r="A2" t="str"><f>CONCAT("A","B")</f><v>AB</v></c><c r="B2" t="b"><v>1</v></c><c r="C2" t="e"><v>#N/A</v></c><c r="D2"><v>1117500123</v></c></row>';
        $sst = '<si><t>Documento</t></si><si><r><t>Nom</t></r><r><rPr><b/></rPr><t>bre</t></r><rPh><t>ignorar</t></rPh></si>';
        $filas = XlsxParser::parse($this->libro(['xl/worksheets/sheet1.xml' => $hoja], null, $sst));
        $this->assertSame([['Documento', 'Nombre', 'en línea', ''], ['AB', '1', '', '1117500123']], $filas);
    }

    #[TestDox('xlsx: las fechas de Excel (número de serie) salen como AAAA-MM-DD, con hora si la tienen')]
    public function testFechas(): void {
        $estilos = '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy;@"/></numFmts>'
                 . '<cellXfs count="4"><xf numFmtId="0"/><xf numFmtId="14"/><xf numFmtId="164"/><xf numFmtId="22"/></cellXfs>';
        $hoja = '<row r="1"><c r="A1" s="1"><v>45730</v></c><c r="B1" s="2"><v>37725</v></c><c r="C1" s="3"><v>45730.5</v></c><c r="D1" s="0"><v>45730</v></c></row>';
        $filas = XlsxParser::parse($this->libro(['xl/worksheets/sheet1.xml' => $hoja], null, '', $estilos));
        $this->assertSame([['2025-03-14', '2003-04-14', '2025-03-14 12:00', '45730']], $filas,
            'un número sin formato de fecha (un documento, una cantidad) no se toca');
        $this->assertSame('2025-01-01', XlsxParser::fechaDesdeSerie(45658));
        $this->assertSame('2026-01-01', XlsxParser::fechaDesdeSerie(46023));
        $this->assertSame('1900-03-01', XlsxParser::fechaDesdeSerie(61));
    }

    #[TestDox('xlsx: celdas y filas sin coordenada se colocan en orden')]
    public function testSinCoordenadas(): void {
        $hoja = '<row><c t="inlineStr"><is><t>a</t></is></c><c t="inlineStr"><is><t>b</t></is></c></row>'
              . '<row><c t="inlineStr"><is><t>c</t></is></c><c r="C2" t="inlineStr"><is><t>e</t></is></c></row>';
        $this->assertSame([['a', 'b', ''], ['c', '', 'e']], XlsxParser::parse($this->libro(['xl/worksheets/sheet1.xml' => $hoja])));
    }

    #[TestDox('xlsx: lee la primera hoja que declara el libro aunque no se llame sheet1.xml')]
    public function testPrimeraHojaReal(): void {
        $ruta = $this->libro(
            ['xl/worksheets/sheet2.xml' => '<row r="1"><c r="A1" t="inlineStr"><is><t>primera</t></is></c></row>',
             'xl/worksheets/sheet3.xml' => '<row r="1"><c r="A1" t="inlineStr"><is><t>segunda</t></is></c></row>'],
            ['xl/worksheets/sheet2.xml', 'xl/worksheets/sheet3.xml']);
        $this->assertSame([['primera']], LectorTabular::leerRuta($ruta, 'xlsx', 10)['filas']);
    }

    #[TestDox('xlsx: rechaza una hoja que descomprimida supera el límite (bomba zip)')]
    public function testBombaZip(): void {
        $fila = '<row><c t="inlineStr"><is><t>xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</t></is></c></row>';
        $ruta = $this->libro(['xl/worksheets/sheet1.xml' => str_repeat($fila, (int)ceil(41 * 1024 * 1024 / strlen($fila)))]);
        $this->assertLessThan(5 * 1024 * 1024, filesize($ruta), 'el archivo comprimido cabe en el límite de subida');
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('descomprimida');
        LectorTabular::leerRuta($ruta, 'xlsx', 10);
    }

    #[TestDox('xlsx: un archivo que no es un libro se rechaza con un mensaje claro')]
    public function testNoEsUnLibro(): void {
        $this->expectException(ErrorDeNegocio::class);
        LectorTabular::leerRuta($this->archivo('esto no es un zip', 'xlsx'), 'xlsx', 10);
    }
}
