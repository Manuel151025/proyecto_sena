<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use Core\Services\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * El paginador sustituyó a los topes fijos (`LIMIT 200`) que recortaban
 * datos en silencio. Los casos que importan son los bordes: página cero,
 * página inventada, cero resultados y el tope de `?por_pagina=`.
 */
final class PaginatorTest extends CasoDePrueba {

    #[TestDox('reparte el total en páginas del tamaño indicado')]
    public function testCalculoBasico(): void {
        $p = new Paginator(162, 1, 25);
        $this->assertSame(7, $p->totalPages());
        $this->assertSame(162, $p->totalItems());
        $this->assertSame(0, $p->offset());
        $this->assertSame(25, $p->perPage());
    }

    #[TestDox('el offset avanza con la página')]
    public function testOffsetPorPagina(): void {
        $this->assertSame(0,  (new Paginator(100, 1, 25))->offset());
        $this->assertSame(25, (new Paginator(100, 2, 25))->offset());
        $this->assertSame(75, (new Paginator(100, 4, 25))->offset());
    }

    /**
     * Un `?pagina=999` escrito a mano, o un enlace guardado que ya no
     * existe porque se filtró la lista, debe mostrar la última página en
     * vez de una tabla vacía sin explicación.
     */
    #[DataProvider('paginasFueraDeRango')]
    #[TestDox('sujeta la página al rango válido')]
    public function testSujetaLaPagina(int $pedida, int $esperada): void {
        $this->assertSame($esperada, (new Paginator(100, $pedida, 25))->currentPage());
    }

    public static function paginasFueraDeRango(): array {
        return [
            'muy alta'   => [999, 4],
            'cero'       => [0, 1],
            'negativa'   => [-5, 1],
            'la última'  => [4, 4],
            'la primera' => [1, 1],
        ];
    }

    #[TestDox('con cero resultados sigue habiendo una página')]
    public function testCeroResultados(): void {
        $p = new Paginator(0, 1, 25);
        $this->assertSame(1, $p->totalPages());
        $this->assertSame(0, $p->totalItems());
        $this->assertSame(0, $p->primerItem(), 'sin filas no hay "mostrando 1 de 0"');
        $this->assertSame(0, $p->ultimoItem());
        $this->assertFalse($p->tieneVariasPaginas());
    }

    #[TestDox('un total negativo se trata como cero')]
    public function testTotalNegativo(): void {
        $this->assertSame(0, (new Paginator(-10, 1, 25))->totalItems());
    }

    #[TestDox('el tamaño de página tiene un tope: nadie pide un millón de filas')]
    public function testTopeDeTamanoDePagina(): void {
        $this->assertSame(100, (new Paginator(10000, 1, 1000000))->perPage());
        $this->assertSame(100, (new Paginator(10000, 1, 101))->perPage());
        $this->assertSame(1,   (new Paginator(10000, 1, 0))->perPage(), 'cero por página sería división por cero');
        $this->assertSame(1,   (new Paginator(10000, 1, -5))->perPage());
    }

    #[TestDox('"mostrando X–Y de Z" cuadra en la última página incompleta')]
    public function testRangoMostradoEnUltimaPagina(): void {
        $p = new Paginator(162, 7, 25);   // 7ª página: 151..162
        $this->assertSame(151, $p->primerItem());
        $this->assertSame(162, $p->ultimoItem(), 'no debe pasarse del total');
    }

    #[TestDox('la navegación anterior/siguiente se activa donde toca')]
    public function testNavegacion(): void {
        $primera = new Paginator(100, 1, 25);
        $this->assertFalse($primera->hayAnterior());
        $this->assertTrue($primera->haySiguiente());

        $ultima = new Paginator(100, 4, 25);
        $this->assertTrue($ultima->hayAnterior());
        $this->assertFalse($ultima->haySiguiente());

        $unica = new Paginator(10, 1, 25);
        $this->assertFalse($unica->hayAnterior());
        $this->assertFalse($unica->haySiguiente());
    }

    #[TestDox('la ventana de páginas resume con puntos suspensivos')]
    public function testVentanaDePaginas(): void {
        // 155 páginas no caben en pantalla: se muestra 1 … 77 78 79 … 155
        $p = new Paginator(3873, 78, 25);
        $v = $p->ventanaPaginas(1);

        $this->assertSame(1, $v[0], 'siempre debe verse la primera');
        $this->assertSame($p->totalPages(), end($v), 'siempre debe verse la última');
        $this->assertContains(78, $v, 'debe verse la página actual');
        $this->assertContains(null, $v, 'debe haber un salto');
        $this->assertLessThan(10, count($v), 'la ventana no debe crecer sin control');
    }

    #[TestDox('con una sola página la ventana es solo esa')]
    public function testVentanaConUnaPagina(): void {
        $this->assertSame([1], (new Paginator(10, 1, 25))->ventanaPaginas());
    }

    #[TestDox('sin salto cuando las páginas caben todas')]
    public function testVentanaSinSalto(): void {
        $v = (new Paginator(75, 2, 25))->ventanaPaginas(1);   // 3 páginas
        $this->assertNotContains(null, $v);
        $this->assertSame([1, 2, 3], $v);
    }

    /**
     * Cambiar de página perdía los filtros y devolvía al usuario a la lista
     * completa. La URL tiene que arrastrar el resto de parámetros.
     */
    #[TestDox('la URL de página conserva los filtros de la búsqueda')]
    public function testUrlConservaFiltros(): void {
        $_GET = ['search' => 'Muñoz', 'ficha_id' => '3', 'pagina' => '1'];
        $url = (new Paginator(100, 1, 25))->urlPagina(3);

        $this->assertStringContainsString('pagina=3', $url);
        $this->assertStringContainsString('ficha_id=3', $url);
        $this->assertStringContainsString('search=', $url);
        parse_str(ltrim($url, '?'), $q);
        $this->assertSame('Muñoz', $q['search'], 'el término de búsqueda debe sobrevivir al cambio de página');
    }

    #[TestDox('desdePeticion() lee la página y el tamaño de la URL')]
    public function testDesdePeticion(): void {
        $_GET = ['pagina' => '3', 'por_pagina' => '50'];
        $p = Paginator::desdePeticion(1000);
        $this->assertSame(3, $p->currentPage());
        $this->assertSame(50, $p->perPage());
    }

    #[TestDox('desdePeticion() aguanta parámetros manipulados')]
    public function testDesdePeticionConBasura(): void {
        $_GET = ['pagina' => 'abc', 'por_pagina' => "50'; DROP TABLE x"];
        $p = Paginator::desdePeticion(1000);

        $this->assertSame(1, $p->currentPage(), 'una página no numérica debe caer a la primera');
        $this->assertGreaterThanOrEqual(1, $p->perPage());
        $this->assertLessThanOrEqual(100, $p->perPage());
    }

    #[TestDox('el tamaño por defecto es el declarado en la clase')]
    public function testTamanoPorDefecto(): void {
        $_GET = [];
        $this->assertSame(Paginator::POR_PAGINA, Paginator::desdePeticion(1000)->perPage());
    }
}
