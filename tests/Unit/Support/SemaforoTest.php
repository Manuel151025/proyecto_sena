<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\Semaforo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * La regla del semáforo decide a qué aprendiz se atiende primero en
 * paneles, fichas, expediente y reportes. Se prueban sus bordes: un
 * desplazamiento de una décima o de un D cambia a quién se prioriza.
 */
final class SemaforoTest extends CasoDePrueba {

    #[DataProvider('casosDeAprendiz')]
    #[TestDox('clasifica al aprendiz por desempeño y número de D')]
    public function testAprendiz(?float $pct, int $enD, string $esperado): void {
        $this->assertSame($esperado, Semaforo::aprendiz($pct, $enD));
    }

    public static function casosDeAprendiz(): array {
        return [
            'sin juicios'                => [null, 0, Semaforo::SIN_DATOS],
            'todo aprobado'              => [100.0, 0, Semaforo::AL_DIA],
            'justo en 80 sin D'          => [80.0, 0, Semaforo::AL_DIA],
            'por debajo de 80'           => [79.9, 0, Semaforo::RIESGO],
            'alto pero con un D'         => [95.0, 1, Semaforo::RIESGO],
            'alto con dos D'             => [90.0, 2, Semaforo::RIESGO],
            'alto con tres D'            => [90.0, 3, Semaforo::CRITICO],
            'justo en 60'                => [60.0, 1, Semaforo::RIESGO],
            'por debajo de 60'           => [59.9, 1, Semaforo::CRITICO],
            'nada aprobado'              => [0.0, 1, Semaforo::CRITICO],
        ];
    }

    #[DataProvider('casosDePorcentaje')]
    #[TestDox('clasifica una ficha o un grupo por su desempeño agregado')]
    public function testPorcentaje(?float $pct, string $esperado): void {
        $this->assertSame($esperado, Semaforo::porcentaje($pct));
    }

    public static function casosDePorcentaje(): array {
        return [
            'sin juicios'      => [null, Semaforo::SIN_DATOS],
            'justo en 80'      => [80.0, Semaforo::AL_DIA],
            'por debajo de 80' => [79.9, Semaforo::RIESGO],
            'justo en 60'      => [60.0, Semaforo::RIESGO],
            'por debajo de 60' => [59.9, Semaforo::CRITICO],
        ];
    }

    #[TestDox('cada estado tiene etiqueta y color, y sin juicios no se pinta como crítico')]
    public function testEtiquetasYClases(): void {
        $this->assertSame('Crítico', Semaforo::etiqueta(Semaforo::CRITICO));
        $this->assertSame('danger', Semaforo::clase(Semaforo::CRITICO));
        $this->assertSame('warning', Semaforo::clase(Semaforo::RIESGO));
        $this->assertSame('success', Semaforo::clase(Semaforo::AL_DIA));
        $this->assertSame('secondary', Semaforo::clase(Semaforo::SIN_DATOS));
    }
}
