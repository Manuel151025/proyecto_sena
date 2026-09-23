<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\CasoDePrueba;

/** Comprueba que el entorno de pruebas arranca de verdad. */
final class ArranqueTest extends CasoDePrueba {
    public function testElProyectoSeCargaEnElEntornoDePruebas(): void {
        $this->assertTrue(defined('APP_URL'), 'config.php no definió las constantes');
        $this->assertTrue(function_exists('getCsrfToken'), 'session.php no se cargó');
        $this->assertTrue(class_exists(\Core\Support\Validador::class), 'el autoload de Core no funciona');
    }

    public function testLaSesionSimuladaSeLimpiaEntrePruebas(): void {
        $this->assertSame([], $_SESSION['tabs']);
        $this->iniciarSesionComo('coordinador');
        $this->assertSame('coordinador', getCurrentRole());
    }

    public function testLaSesionDeLaPruebaAnteriorNoSeFiltra(): void {
        $this->assertSame('', getCurrentRole(), 'quedó sesión de otra prueba');
    }
}
