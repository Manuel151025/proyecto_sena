<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\ManejadorErrores;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionMethod;
use Tests\CasoDePrueba;

/**
 * El manejador global de errores.
 *
 * Antes no existía ninguno: una excepción no capturada salía a la pantalla
 * como traza de PHP, con rutas absolutas del servidor y nombres de clase.
 *
 * La decisión delicada está en `manejarError`: en un proyecto nuevo lo
 * correcto sería elevar cada aviso a excepción, pero aquí hay 36 vistas que
 * acceden a claves de array que pueden no existir, y hacerlo convertiría
 * una página que hoy se pinta con un hueco en una pantalla de error. Se
 * registra y se continúa.
 */
final class ManejadorErroresTest extends CasoDePrueba {

    private function invocar(string $metodo, array $args): mixed {
        $m = new ReflectionMethod(ManejadorErrores::class, $metodo);
        $m->setAccessible(true);
        return $m->invokeArgs(null, $args);
    }

    #[TestDox('un aviso se registra pero no interrumpe la petición')]
    public function testAvisoNoInterrumpe(): void {
        $r = ManejadorErrores::manejarError(E_WARNING, 'Undefined array key "x"', __FILE__, 10);

        $this->assertTrue($r, 'debe darse por gestionado para que PHP no lo imprima');
    }

    #[TestDox('un notice tampoco interrumpe')]
    public function testNoticeNoInterrumpe(): void {
        $this->assertTrue(ManejadorErrores::manejarError(E_NOTICE, 'algo', __FILE__, 1));
    }

    #[TestDox('un deprecated tampoco interrumpe')]
    public function testDeprecatedNoInterrumpe(): void {
        $this->assertTrue(ManejadorErrores::manejarError(E_DEPRECATED, 'algo', __FILE__, 1));
    }

    /**
     * Un error recuperable sí es un fallo de tipo, no un descuido
     * cosmético: seguir adelante dejaría datos a medias.
     */
    #[TestDox('un error recuperable sí se eleva a excepción')]
    public function testErrorRecuperableSeEleva(): void {
        $this->expectException(\ErrorException::class);
        ManejadorErrores::manejarError(E_RECOVERABLE_ERROR, 'fallo de tipo', __FILE__, 1);
    }

    #[TestDox('lo silenciado con @ se respeta')]
    public function testSilenciadoConArroba(): void {
        $nivelPrevio = error_reporting();
        error_reporting(0);
        try {
            $this->assertFalse(
                ManejadorErrores::manejarError(E_WARNING, 'silenciado', __FILE__, 1),
                'debe devolver false para que PHP aplique su comportamiento por defecto'
            );
        } finally {
            error_reporting($nivelPrevio);
        }
    }

    #[TestDox('el log se escribe en la carpeta protegida por .htaccess')]
    public function testRutaDelLog(): void {
        $ruta = (string)$this->invocar('rutaLog', []);

        $this->assertStringEndsWith('error.log', $ruta);
        $this->assertStringContainsString('logs', $ruta,
            'el log debe quedar en logs/, que el .htaccess impide servir');
    }

    #[TestDox('la petición JSON se detecta por cabecera, por AJAX y por ruta')]
    public function testDeteccionDeJson(): void {
        $previo = $_SERVER;

        try {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';
            $this->assertTrue((bool)$this->invocar('esperaJson', []));

            $_SERVER['HTTP_ACCEPT'] = 'text/html';
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            $this->assertTrue((bool)$this->invocar('esperaJson', []), 'no detecta una llamada AJAX');

            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
            $_SERVER['REQUEST_URI'] = '/proyecto_sena/includes/api_notificaciones.php';
            $this->assertTrue((bool)$this->invocar('esperaJson', []), 'no detecta una ruta de API');

            $_SERVER['REQUEST_URI'] = '/proyecto_sena/index.php/dashboard';
            $_SERVER['HTTP_ACCEPT'] = 'text/html';
            $this->assertFalse((bool)$this->invocar('esperaJson', []), 'una navegación normal no es JSON');
        } finally {
            $_SERVER = $previo;
        }
    }

    /**
     * La página de error es lo único que ve el usuario, así que no puede
     * llevar nada del detalle técnico cuando no se está en desarrollo.
     */
    #[TestDox('la página de error no filtra nada sin modo desarrollo')]
    public function testPaginaDeErrorSinDetalle(): void {
        $html = (string)$this->invocar('paginaError', ['A1B2C3', null]);

        $this->assertStringContainsString('A1B2C3', $html, 'debe mostrar la referencia');
        $this->assertStringNotContainsString('xampp', $html);
        $this->assertStringNotContainsString('Exception', $html);
        $this->assertStringNotContainsString('<pre', $html, 'sin detalle no debe haber bloque de traza');
    }

    #[TestDox('en modo desarrollo la página sí incluye la traza')]
    public function testPaginaDeErrorConDetalle(): void {
        $e = new \RuntimeException('fallo concreto');
        $html = (string)$this->invocar('paginaError', ['A1B2C3', $e]);

        $this->assertStringContainsString('fallo concreto', $html);
        $this->assertStringContainsString('<pre', $html);
    }

    #[TestDox('la página de error escapa lo que inserta')]
    public function testPaginaDeErrorEscapa(): void {
        $e = new \RuntimeException('<script>alert(1)</script>');
        $html = (string)$this->invocar('paginaError', ['REF', $e]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[TestDox('registrar() es idempotente: no encadena manejadores')]
    public function testRegistroIdempotente(): void {
        ManejadorErrores::registrar();
        ManejadorErrores::registrar();
        ManejadorErrores::registrar();

        $this->assertTrue(true, 'registrar varias veces no debe apilar manejadores');
    }

    #[TestDox('los nombres de severidad son legibles en el log')]
    public function testNombresDeSeveridad(): void {
        $this->assertSame('WARNING', $this->invocar('nombreSeveridad', [E_WARNING]));
        $this->assertSame('NOTICE', $this->invocar('nombreSeveridad', [E_NOTICE]));
        $this->assertSame('DEPRECATED', $this->invocar('nombreSeveridad', [E_DEPRECATED]));
    }
}
