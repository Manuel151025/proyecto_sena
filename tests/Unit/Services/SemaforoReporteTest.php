<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use Core\Services\SemaforoReporte;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Dos responsabilidades muy distintas en la misma clase: el color de las
 * celdas (cosmético) y la protección frente a inyección de fórmulas en los
 * CSV (seguridad real, con la víctima al otro lado del archivo).
 */
final class SemaforoReporteTest extends CasoDePrueba {

    // =================================================================
    // INYECCIÓN DE FÓRMULAS EN CSV
    // =================================================================

    /**
     * Excel y LibreOffice evalúan como fórmula toda celda que empiece por
     * `=`, `+`, `-` o `@`. Los reportes exportan texto escrito por usuarios
     * (el motivo de un cambio de nota, el nombre de un aprendiz), así que
     * el atacante escribe en el sistema y la víctima es el coordinador que
     * abre el archivo.
     */
    #[DataProvider('cargasDeFormula')]
    #[TestDox('neutraliza una fórmula anteponiendo un apóstrofo')]
    public function testNeutralizaFormulas(string $carga, string $ataque): void {
        $r = SemaforoReporte::protegerCsv($carga);
        $this->assertSame("'", $r[0], "no se neutralizó: $ataque");
        $this->assertStringContainsString($carga, $r, 'el contenido debe conservarse íntegro');
    }

    public static function cargasDeFormula(): array {
        return [
            'enlace a sitio externo' => ['=HYPERLINK("http://atacante","Ver nota")', 'exfiltración por clic'],
            'suma'                   => ['=1+1', 'evaluación de fórmula'],
            'referencia externa'     => ['=SUM(A1:A9)', 'lectura de otras celdas'],
            'más al inicio'          => ['+1+1', 'variante con +'],
            'menos al inicio'        => ['-2+3', 'variante con -'],
            'arroba al inicio'       => ['@SUM(A1)', 'variante con @'],
            'ejecución de comando'   => ["=cmd|'/c calc'!A0", 'DDE: ejecución de programa'],
            'con tabulador delante'  => ["\t=1+1", 'salta una comprobación ingenua'],
            'con retorno de carro'   => ["\r=1+1", 'salta una comprobación ingenua'],
            'con salto de línea'     => ["\n=1+1", 'salta una comprobación ingenua'],
            'con espacio delante'    => [' =1+1', 'salta una comprobación ingenua'],
        ];
    }

    #[DataProvider('textosInofensivos')]
    #[TestDox('no toca el texto que no es una fórmula')]
    public function testNoTocaTextoNormal(string $texto): void {
        $this->assertSame($texto, SemaforoReporte::protegerCsv($texto));
    }

    public static function textosInofensivos(): array {
        return [
            'nombre con tilde'   => ['Muñoz Pérez'],
            'número'             => ['85.5'],
            'porcentaje al final'=> ['71.9'],
            'código de RAP'      => ['220501094-RA01'],
            'frase normal'       => ['Cambio tras revisión del instructor'],
            'fecha'              => ['2026-03-15'],
            'guion en medio'     => ['Análisis - Desarrollo'],
            'concepto'           => ['A'],
        ];
    }

    #[TestDox('un valor vacío o nulo sigue vacío')]
    public function testValoresVacios(): void {
        $this->assertSame('', SemaforoReporte::protegerCsv(null));
        $this->assertSame('', SemaforoReporte::protegerCsv(''));
    }

    #[TestDox('protegerFilaCsv aplica la protección a toda la fila')]
    public function testProtegeFilaCompleta(): void {
        $fila = ['Muñoz Pérez', '=HYPERLINK("http://x","y")', '85.5', '@SUM(A1)'];
        $r = SemaforoReporte::protegerFilaCsv($fila);

        $this->assertSame('Muñoz Pérez', $r[0]);
        $this->assertSame("'", $r[1][0]);
        $this->assertSame('85.5', $r[2]);
        $this->assertSame("'", $r[3][0]);
    }

    #[TestDox('protegerFilaCsv reindexa: fputcsv necesita claves numéricas')]
    public function testProtegeFilaReindexada(): void {
        $fila = ['nombre' => 'Ana', 'concepto' => 'A'];
        $this->assertSame([0, 1], array_keys(SemaforoReporte::protegerFilaCsv($fila)));
    }

    // =================================================================
    // SEMÁFORO DE COLOR
    // =================================================================

    #[TestDox('colorea el concepto evaluativo')]
    public function testColorDeConcepto(): void {
        $estilos = ['columnas_concepto' => [5]];
        $this->assertSame('aldia',   SemaforoReporte::clase(5, 'A', $estilos));
        $this->assertSame('critico', SemaforoReporte::clase(5, 'D', $estilos));
        $this->assertSame('riesgo',  SemaforoReporte::clase(5, 'pendiente', $estilos));
    }

    #[TestDox('solo colorea la columna declarada')]
    public function testSoloLaColumnaDeclarada(): void {
        $estilos = ['columnas_concepto' => [5]];
        $this->assertSame('', SemaforoReporte::clase(2, 'A', $estilos), 'coloreó una columna que no toca');
    }

    #[DataProvider('umbralesDeCumplimiento')]
    #[TestDox('aplica los umbrales de cumplimiento')]
    public function testUmbralesDeCumplimiento(string $valor, string $esperado): void {
        $this->assertSame($esperado, SemaforoReporte::clase(8, $valor, ['columna_porcentaje' => 8]));
    }

    public static function umbralesDeCumplimiento(): array {
        return [
            'perfecto'        => ['100', 'aldia'],
            'justo en 80'     => ['80', 'aldia'],
            'por debajo de 80'=> ['79.9', 'riesgo'],
            'justo en 60'     => ['60', 'riesgo'],
            'por debajo de 60'=> ['59.9', 'critico'],
            'cero'            => ['0', 'critico'],
            'con símbolo'     => ['85.5%', 'aldia'],
            'con coma'        => ['85,5', 'aldia'],
        ];
    }

    #[TestDox('los números sin semáforo se centran')]
    public function testNumerosSeCentran(): void {
        $this->assertSame('num', SemaforoReporte::clase(3, '42', []));
        $this->assertSame('', SemaforoReporte::clase(3, 'texto', []));
    }

    #[TestDox('los umbrales son los del semáforo del sistema')]
    public function testUmbralesComunes(): void {
        $estilos = ['columna_porcentaje' => 0];
        $this->assertSame('aldia', SemaforoReporte::clase(0, (string)\Core\Support\Semaforo::UMBRAL_AL_DIA, $estilos));
        $this->assertSame('riesgo', SemaforoReporte::clase(0, (string)(\Core\Support\Semaforo::UMBRAL_AL_DIA - 0.1), $estilos));
        $this->assertSame('critico', SemaforoReporte::clase(0, (string)(\Core\Support\Semaforo::UMBRAL_RIESGO - 0.1), $estilos));
        $this->assertSame('', SemaforoReporte::clase(0, '', $estilos), 'un porcentaje vacío (sin juicios) no lleva color');
    }

    #[TestDox('el historial colorea las dos columnas de concepto')]
    public function testHistorialColoreaAmbasColumnas(): void {
        $estilos = ['columnas_concepto' => [5, 6]];
        $this->assertSame('riesgo',  SemaforoReporte::clase(5, 'pendiente', $estilos));
        $this->assertSame('aldia',   SemaforoReporte::clase(6, 'A', $estilos));
        $this->assertSame('critico', SemaforoReporte::clase(6, 'D', $estilos));
    }
}
