<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Falsificación de peticiones y manejo de la sesión.
 *
 * El token era por pestaña, y eso obligó a un parche que al validar
 * recorría TODAS las pestañas aceptando cualquier coincidencia: el
 * aislamiento que justificaba el diseño quedaba anulado igualmente. Ahora
 * es un token por sesión, y varias de estas pruebas existen para que nadie
 * vuelva a introducir aquel atajo.
 */
final class CsrfYSesionTest extends CasoDePrueba {

    // =================================================================
    // TOKEN
    // =================================================================

    #[TestDox('el token tiene entropía suficiente')]
    public function testEntropiaDelToken(): void {
        $t = getCsrfToken();
        $this->assertSame(64, strlen($t), '32 bytes en hexadecimal');
        $this->assertTrue(ctype_xdigit($t));
    }

    #[TestDox('dos sesiones distintas reciben tokens distintos')]
    public function testTokensDistintosPorSesion(): void {
        $tokens = [];
        for ($i = 0; $i < 50; $i++) {
            $this->limpiarSesion();
            $tokens[] = getCsrfToken();
        }
        $this->assertCount(50, array_unique($tokens), 'los tokens se repiten entre sesiones');
    }

    #[TestDox('el token no cambia dentro de la misma sesión')]
    public function testTokenEstable(): void {
        $t = getCsrfToken();
        $this->assertSame($t, getCsrfToken());
        $this->assertSame($t, getCsrfToken());
    }

    #[TestDox('valida el token correcto y rechaza cualquier otro')]
    public function testValidacionBasica(): void {
        $bueno = getCsrfToken();

        $this->assertTrue(validateCsrfToken($bueno));
        $this->assertFalse(validateCsrfToken(str_repeat('a', 64)));
        $this->assertFalse(validateCsrfToken(''));
        $this->assertFalse(validateCsrfToken(null));
        $this->assertFalse(validateCsrfToken(strtoupper($bueno)), 'debe distinguir mayúsculas');
        $this->assertFalse(validateCsrfToken(substr($bueno, 0, 63)), 'un prefijo no vale');
        $this->assertFalse(validateCsrfToken($bueno . 'x'), 'un token más largo no vale');
    }

    /**
     * El parche eliminado daba por bueno cualquier token que coincidiera
     * con el de alguna pestaña. Esta prueba falla si alguien lo reintroduce.
     */
    #[TestDox('NO se acepta un token guardado en otra pestaña')]
    public function testNoAceptaTokenDeOtraPestana(): void {
        getCsrfToken();
        $ajeno = str_repeat('b', 64);
        $_SESSION['tabs']['otra_pestana'] = ['csrf_token' => $ajeno];

        $this->assertFalse(
            validateCsrfToken($ajeno),
            'se ha reintroducido el parche que aceptaba el token de cualquier pestaña'
        );
    }

    #[TestDox('sin token en sesión no se valida nada')]
    public function testSinTokenEnSesion(): void {
        $_SESSION = ['tabs' => []];
        $this->assertFalse(validateCsrfToken(str_repeat('c', 64)));
    }

    #[TestDox('csrfField() produce un input oculto con el valor escapado')]
    public function testCampoOculto(): void {
        $html = csrfField();
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString(getCsrfToken(), $html);
    }

    /**
     * `hash_equals` compara en tiempo constante. Con `==` se podría
     * averiguar el token byte a byte midiendo la respuesta.
     */
    #[TestDox('la comparación no depende de cuántos caracteres acierten')]
    public function testComparacionEnTiempoConstante(): void {
        $bueno = getCsrfToken();
        $casiBueno = substr($bueno, 0, 63) . (($bueno[63] === 'a') ? 'b' : 'a');
        $nadaQueVer = str_repeat('z', 64);

        $medir = static function (string $t): float {
            $inicio = microtime(true);
            for ($i = 0; $i < 2000; $i++) {
                validateCsrfToken($t);
            }
            return microtime(true) - $inicio;
        };

        $conCasi = $medir($casiBueno);
        $conNada = $medir($nadaQueVer);

        $ratio = max($conCasi, $conNada) / max(0.0001, min($conCasi, $conNada));
        $this->assertLessThan(3.0, $ratio, 'el tiempo delata cuántos caracteres son correctos');
    }

    // =================================================================
    // PESTAÑAS
    // =================================================================

    #[DataProvider('idsDePestanaInvalidos')]
    #[TestDox('un tabId manipulado cae en la pestaña por defecto')]
    public function testTabIdInvalido(string $tabId): void {
        $_COOKIE['sena_tab'] = $tabId;
        $this->assertSame('default', getTabId(), "aceptó el tabId «$tabId»");
    }

    public static function idsDePestanaInvalidos(): array {
        return [
            'demasiado corto'   => ['abc'],
            'demasiado largo'   => [str_repeat('a', 40)],
            'con mayúsculas'    => ['ABCDEFGHIJ'],
            'con guiones'       => ['abc-def-ghi'],
            'travesía de rutas' => ['../../../etc'],
            'inyección'         => ["abc' OR '1'='1"],
            'vacío'             => [''],
            'con punto'         => ['abc.def.ghi'],
            'con barra'         => ['abc/def/ghi'],
        ];
    }

    #[TestDox('un tabId con el formato correcto sí se acepta')]
    public function testTabIdValido(): void {
        $_COOKIE['sena_tab'] = 'abc123def456';
        $this->assertSame('abc123def456', getTabId());
    }

    /**
     * El aislamiento por pestaña es la razón de ser del diseño: permite
     * tener dos roles abiertos a la vez sin que uno pise al otro.
     */
    #[TestDox('cada pestaña conserva su propio usuario y rol')]
    public function testAislamientoEntrePestanas(): void {
        $_SESSION['tabs']['pestanacoord'] = ['user_id' => 1, 'user_rol' => 'coordinador', 'user_nombre' => 'C', 'user_email' => 'c@x'];
        $_SESSION['tabs']['pestanaapren'] = ['user_id' => 2, 'user_rol' => 'aprendiz',    'user_nombre' => 'A', 'user_email' => 'a@x'];

        $_COOKIE['sena_tab'] = 'pestanacoord';
        $this->assertSame('coordinador', getCurrentRole());
        $this->assertSame(1, getCurrentUser()['id']);

        $_COOKIE['sena_tab'] = 'pestanaapren';
        $this->assertSame('aprendiz', getCurrentRole());
        $this->assertSame(2, getCurrentUser()['id']);
    }

    #[TestDox('sin sesión en la pestaña, no hay usuario')]
    public function testPestanaSinSesion(): void {
        $_COOKIE['sena_tab'] = 'pestanavacia';
        $this->assertFalse(isAuthenticated());
        $this->assertNull(getCurrentUser());
        $this->assertSame('', getCurrentRole());
    }

    /**
     * Cada pestaña nueva creaba un slot que nadie retiraba: navegar todo el
     * día hacía crecer el archivo de sesión sin tope.
     */
    #[TestDox('las pestañas antiguas se descartan y no crecen sin límite')]
    public function testRecoleccionDePestanas(): void {
        for ($i = 0; $i < 30; $i++) {
            $_SESSION['tabs']['pestana' . str_pad((string)$i, 6, '0', STR_PAD_LEFT)] = [
                'user_id' => 1, 'user_rol' => 'aprendiz', 'user_nombre' => 'x', 'user_email' => 'x@x',
                '_visto' => time() - (30 - $i) * 60,
            ];
        }

        recolectarPestanas();

        $this->assertLessThanOrEqual(
            SESION_MAX_PESTANAS,
            count($_SESSION['tabs']),
            'los slots de pestaña siguen creciendo sin límite'
        );
    }

    #[TestDox('la recolección no descarta la pestaña que está pidiendo')]
    public function testLaPestanaActualSobrevive(): void {
        $_COOKIE['sena_tab'] = 'pestanaactual';
        for ($i = 0; $i < 30; $i++) {
            $_SESSION['tabs']['pestana' . str_pad((string)$i, 6, '0', STR_PAD_LEFT)] = ['_visto' => time()];
        }
        // La actual es la más antigua a propósito.
        $_SESSION['tabs']['pestanaactual'] = ['user_id' => 1, 'user_rol' => 'aprendiz',
            'user_nombre' => 'x', 'user_email' => 'x@x', '_visto' => 0];

        recolectarPestanas();

        $this->assertArrayHasKey('pestanaactual', $_SESSION['tabs'],
            'se descartó la sesión de quien está navegando');
    }

    // =================================================================
    // CADUCIDAD
    // =================================================================

    #[TestDox('una sesión inactiva demasiado tiempo se cierra')]
    public function testCaducidadPorInactividad(): void {
        $this->iniciarSesionComo('coordinador');
        $_SESSION['_creada'] = time() - 100;
        $_SESSION['_ultimo_acceso'] = time() - (SESION_INACTIVIDAD_MAX + 60);

        aplicarCaducidadSesion();

        $this->assertFalse(isAuthenticated(), 'la sesión inactiva sigue viva');
    }

    #[TestDox('una sesión demasiado larga se cierra aunque haya actividad')]
    public function testCaducidadAbsoluta(): void {
        $this->iniciarSesionComo('coordinador');
        $_SESSION['_creada'] = time() - (SESION_DURACION_MAX + 60);
        $_SESSION['_ultimo_acceso'] = time();   // activo ahora mismo

        aplicarCaducidadSesion();

        $this->assertFalse(isAuthenticated(), 'una sesión puede durar indefinidamente con actividad');
    }

    #[TestDox('una sesión en uso no se cierra')]
    public function testSesionVigenteSobrevive(): void {
        $this->iniciarSesionComo('coordinador');
        $_SESSION['_creada'] = time() - 600;
        $_SESSION['_ultimo_acceso'] = time() - 60;

        aplicarCaducidadSesion();

        $this->assertTrue(isAuthenticated(), 'se cerró una sesión que estaba en uso');
    }

    #[TestDox('cada petición refresca la marca de actividad')]
    public function testSeRefrescaLaActividad(): void {
        $this->iniciarSesionComo('coordinador');
        $_SESSION['_creada'] = time() - 600;
        $_SESSION['_ultimo_acceso'] = time() - 300;

        aplicarCaducidadSesion();

        $this->assertGreaterThan(time() - 5, $_SESSION['_ultimo_acceso']);
    }

    // =================================================================
    // ROLES
    // =================================================================

    #[TestDox('hasRole() responde según el rol de la pestaña')]
    public function testHasRole(): void {
        $this->iniciarSesionComo('instructor');

        $this->assertTrue(hasRole(ROL_INSTRUCTOR));
        $this->assertTrue(hasRole(ROL_COORDINADOR, ROL_INSTRUCTOR));
        $this->assertFalse(hasRole(ROL_COORDINADOR));
        $this->assertFalse(hasRole(ROL_APRENDIZ));
        $this->assertFalse(hasRole(), 'sin roles indicados no puede conceder acceso');
    }

    #[TestDox('la comparación de rol es estricta: no hay coacción de tipos')]
    public function testComparacionDeRolEstricta(): void {
        $_SESSION['tabs']['default'] = [
            'user_id' => 1, 'user_nombre' => 'x', 'user_email' => 'x@x', 'user_rol' => '0',
        ];
        $this->assertFalse(hasRole(ROL_COORDINADOR), '"0" no debe equipararse a un rol');
    }
}
