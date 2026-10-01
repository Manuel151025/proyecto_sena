<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\BaseController;
use Core\Exportacion\Exportador;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\CasoDePrueba;

/**
 * Las exportaciones salían cortadas en silencio a partir de 20.000 filas:
 * en un centro grande, «exportar todos los juicios» entregaba un archivo
 * incompleto sin ningún aviso. Ahora se piden MAX_FILAS + 1 y, si llega la
 * de más, se vuelve al listado pidiendo filtrar.
 */
final class ExportacionCompletaTest extends CasoDePrueba {

    private function controlador(): object {
        return new class extends BaseController {
            protected function fallo(string|array $errores, string $ruta): never {
                throw new RuntimeException(json_encode(['errores' => (array)$errores, 'ruta' => $ruta], JSON_UNESCAPED_UNICODE));
            }
            public function probar(callable $consulta, string $listado): array {
                return $this->filasParaExportar($consulta, $listado);
            }
        };
    }

    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    #[TestDox('pide una fila más que el tope y, si caben, las devuelve todas')]
    public function testCabenTodas(): void {
        $pedidas = null;
        $filas = $this->controlador()->probar(function (int $n) use (&$pedidas) {
            $pedidas = $n;
            return array_fill(0, Exportador::MAX_FILAS, ['x']);
        }, '/evaluaciones');
        $this->assertSame(Exportador::MAX_FILAS + 1, $pedidas);
        $this->assertCount(Exportador::MAX_FILAS, $filas);
    }

    #[TestDox('si hay más filas que el tope, no exporta: vuelve al listado con sus filtros y un aviso')]
    public function testDemasiadasFilas(): void {
        $_GET = ['ficha_id' => '3', 'concepto' => 'pendiente', 'formato' => 'xlsx', 'search' => ''];
        try {
            $this->controlador()->probar(fn(int $n) => array_fill(0, $n, ['x']), '/evaluaciones');
            $this->fail('exportó un archivo cortado');
        } catch (RuntimeException $e) {
            $r = json_decode($e->getMessage(), true);
            $this->assertSame('/evaluaciones?ficha_id=3&concepto=pendiente', $r['ruta']);
            $this->assertStringContainsString('Filtra', $r['errores'][0]);
            $this->assertStringContainsString('20.000', $r['errores'][0]);
        }
    }

    /** Una fila más que el tope, sin ocupar memoria. */
    private static function filas(int $n): \Generator {
        for ($i = 0; $i < $n; $i++) {
            yield ['fila ' . $i, $i];
        }
    }

    #[TestDox('el CSV y el Excel no se cortan en silencio: con una fila de más avisan')]
    public function testArchivoNoSeCorta(): void {
        $this->assertStringContainsString('fila ' . (Exportador::MAX_FILAS - 1), Exportador::csv('a.csv', ['A', 'B'], self::filas(Exportador::MAX_FILAS)));
        foreach (['csv', 'xlsx'] as $formato) {
            try {
                $formato === 'csv'
                    ? Exportador::csv('a.csv', ['A', 'B'], self::filas(Exportador::MAX_FILAS + 1))
                    : Exportador::xlsx('Hoja', ['A', 'B'], self::filas(Exportador::MAX_FILAS + 1));
                $this->fail("$formato entregó un archivo cortado");
            } catch (\Core\Support\ErrorDeNegocio $e) {
                $this->assertStringContainsString('20.000', $e->getMessage(), $formato);
            }
        }
    }

    #[TestDox('un PDF con más filas de las que caben avisa antes de componerse (antes agotaba la memoria)')]
    public function testPdfDemasiadoGrande(): void {
        $inicio = microtime(true);
        try {
            (new \Core\Services\ReportePdfService())->generar('Reporte', ['A', 'B'],
                iterator_to_array(self::filas(\Core\Services\ReportePdfService::MAX_FILAS + 1)));
            $this->fail('compuso un PDF que no cabe en memoria');
        } catch (\Core\Support\ErrorDeNegocio $e) {
            $this->assertStringContainsString('Excel o CSV', $e->getMessage());
        }
        $this->assertLessThan(2.0, microtime(true) - $inicio, 'debe avisar sin intentar componerlo');
    }
}
