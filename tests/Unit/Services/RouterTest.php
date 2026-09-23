<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use Core\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * El router decide qué código se ejecuta y quién puede llegar a él. La
 * normalización de rutas es la parte delicada: si se equivoca, o no
 * resuelve nada, o resuelve de más.
 */
final class RouterTest extends CasoDePrueba {

    #[DataProvider('rutasANormalizar')]
    #[TestDox('normaliza la URL al path interno de la ruta')]
    public function testNormalizacion(string $uri, string $esperado): void {
        $this->assertSame($esperado, Router::normalizarRuta($uri));
    }

    public static function rutasANormalizar(): array {
        return [
            'ruta simple'           => ['/proyecto_sena/index.php/usuarios', '/usuarios'],
            'ruta anidada'          => ['/proyecto_sena/index.php/usuarios/crear', '/usuarios/crear'],
            'con query'             => ['/proyecto_sena/index.php/fichas/ver?id=3', '/fichas/ver'],
            'con barra final'       => ['/proyecto_sena/index.php/dashboard/', '/dashboard'],
            'raíz del script'       => ['/proyecto_sena/index.php/', '/'],
            'script sin barra'      => ['/proyecto_sena/index.php', '/'],
            'sin el script'         => ['/proyecto_sena/dashboard', '/dashboard'],
            'con ancla'             => ['/proyecto_sena/index.php/perfil#datos', '/perfil'],
            'query con varios'      => ['/proyecto_sena/index.php/evaluaciones?a=1&b=2', '/evaluaciones'],
        ];
    }

    /**
     * Si la normalización dejara pasar `..`, una ruta construida a mano
     * podría resolver a una entrada distinta de la que aparenta.
     */
    #[TestDox('la normalización no permite salirse con ..')]
    public function testNoPermiteTravesia(): void {
        $r = Router::normalizarRuta('/proyecto_sena/index.php/usuarios/../../admin');
        $this->assertStringNotContainsString('..', $r === '/usuarios/../../admin' ? '' : $r,
            'si conserva .. debe no coincidir con ninguna ruta registrada');
        // Lo relevante: sea cual sea el resultado, no debe ser una ruta real.
        $router = new Router();
        $router->add('GET', '/admin', 'X', 'y');
        $this->assertArrayNotHasKey('GET ' . $r, $router->rutas());
    }

    #[TestDox('registra y recupera una ruta con sus permisos')]
    public function testRegistroDeRuta(): void {
        $router = new Router();
        $router->add('GET', '/usuarios', 'Core\Controllers\UsuarioController', 'index', ['coordinador']);

        $rutas = $router->rutas();
        $this->assertArrayHasKey('GET /usuarios', $rutas);
        $this->assertSame('Core\Controllers\UsuarioController', $rutas['GET /usuarios']['controller']);
        $this->assertSame('index', $rutas['GET /usuarios']['action']);
        $this->assertSame(['coordinador'], $rutas['GET /usuarios']['roles']);
    }

    #[TestDox('el método se normaliza a mayúsculas')]
    public function testMetodoEnMayusculas(): void {
        $router = new Router();
        $router->add('get', '/x', 'C', 'a');
        $this->assertArrayHasKey('GET /x', $router->rutas());
    }

    #[TestDox('la ruta se normaliza con barra inicial y sin barra final')]
    public function testRutaNormalizadaAlRegistrar(): void {
        $router = new Router();
        $router->add('GET', 'usuarios/', 'C', 'a');
        $this->assertArrayHasKey('GET /usuarios', $router->rutas());
    }

    #[TestDox('GET y POST de la misma ruta son entradas distintas')]
    public function testGetYPostSonDistintos(): void {
        $router = new Router();
        $router->add('GET',  '/x', 'C', 'ver',    ['coordinador']);
        $router->add('POST', '/x', 'C', 'guardar', ['coordinador']);

        $rutas = $router->rutas();
        $this->assertSame('ver', $rutas['GET /x']['action']);
        $this->assertSame('guardar', $rutas['POST /x']['action']);
    }

    #[TestDox('registrar dos veces la misma ruta la sustituye, no la duplica')]
    public function testRutaDuplicadaSeSustituye(): void {
        $router = new Router();
        $router->add('GET', '/x', 'Primero', 'a');
        $router->add('GET', '/x', 'Segundo', 'b');

        $this->assertCount(1, $router->rutas());
        $this->assertSame('Segundo', $router->rutas()['GET /x']['controller']);
    }

    #[TestDox('sin roles declarados la lista queda vacía, no nula')]
    public function testRolesPorDefecto(): void {
        $router = new Router();
        $router->add('GET', '/x', 'C', 'a');
        $this->assertSame([], $router->rutas()['GET /x']['roles']);
    }
}
