<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\Seguridad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionMethod;
use Tests\CasoDePrueba;

/**
 * Las cabeceras no se pueden comprobar con `headers_list()` bajo CLI, así
 * que se prueba la política que se emite (la cadena de CSP) y la decisión
 * de si la conexión es segura, que es la que determina si se envía HSTS y
 * si la cookie de sesión va marcada como Secure.
 */
final class SeguridadTest extends CasoDePrueba {

    private function csp(): string {
        $m = new ReflectionMethod(Seguridad::class, 'csp');
        $m->setAccessible(true);
        return (string)$m->invoke(null);
    }

    // =================================================================
    // POLÍTICA DE CONTENIDO
    // =================================================================

    #[DataProvider('directivasObligatorias')]
    #[TestDox('la CSP declara una directiva que cierra un vector concreto')]
    public function testCspDeclaraDirectiva(string $directiva, string $porQue): void {
        $this->assertStringContainsString($directiva, $this->csp(), "falta: $porQue");
    }

    public static function directivasObligatorias(): array {
        return [
            ["default-src 'self'",     'sin base restrictiva, el resto de directivas no cubre lo no declarado'],
            ["frame-ancestors 'none'", 'permitiría embeber la app en un iframe ajeno (clickjacking)'],
            ["object-src 'none'",      'bloquea plugins y applets, superficie muerta pero explotable'],
            ["base-uri 'self'",        'un <base> inyectado redirigiría todas las rutas relativas'],
            ["form-action 'self'",     'impide que un XSS reenvíe el formulario a otro servidor'],
            ["frame-src 'none'",       'la aplicación no incrusta nada de terceros'],
        ];
    }

    #[TestDox('la CSP no abre script-src a cualquier origen')]
    public function testCspNoAbreScriptSrc(): void {
        $csp = $this->csp();
        $this->assertStringNotContainsString('script-src *', $csp);
        $this->assertStringNotContainsString("script-src 'unsafe-eval'", $csp);
    }

    #[TestDox('la CSP solo admite los CDN que la aplicación usa de verdad')]
    public function testCspListaLosOrigenesConcretos(): void {
        $csp = $this->csp();
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
    }

    /**
     * Sin `'unsafe-inline'` en script-src, un script inyectado en la página
     * no se ejecuta. Solo puede volver si alguien reintroduce código en línea.
     */
    #[TestDox('script-src no admite código en línea')]
    public function testScriptSrcSinUnsafeInline(): void {
        preg_match('/script-src ([^;]+)/', $this->csp(), $m);
        $this->assertStringNotContainsString("'unsafe-inline'", $m[1] ?? '', 'script-src vuelve a admitir código en línea');
        $this->assertStringNotContainsString("'unsafe-eval'", $m[1] ?? '');
    }

    /**
     * La garantía anterior solo vale si ninguna vista trae scripts o
     * manejadores en línea: si alguien añade uno, la pantalla se rompe en
     * silencio (la CSP lo bloquea). Esta prueba lo detecta antes.
     */
    #[TestDox('ninguna vista trae scripts ni manejadores de eventos en línea')]
    public function testSinCodigoEnLinea(): void {
        $raiz = dirname(__DIR__, 3);
        $archivos = array_merge(glob($raiz . '/modules/*/views/*.php') ?: [], glob($raiz . '/layouts/*.php') ?: [],
                                glob($raiz . '/components/*.php') ?: [], [$raiz . '/login.php', $raiz . '/recover.php']);
        $conCodigo = [];
        foreach ($archivos as $f) {
            $html = (string)file_get_contents($f);
            // Los <script type="application/json"> son datos, no código.
            if (preg_match('/<script(?![^>]*\\bsrc=)(?![^>]*type="application\/json")[^>]*>/i', $html)
                || preg_match('/\\son(click|change|submit|input|load|error|focus|blur|key\w+|mouse\w+)\\s*=/i', $html)) {
                $conCodigo[] = str_replace($raiz . '/', '', $f);
            }
        }
        $this->assertSame([], $conCodigo, 'con JavaScript en línea (la CSP lo bloquearía): ' . implode(', ', $conCodigo));
    }

    // =================================================================
    // DETECCIÓN DE HTTPS
    // =================================================================

    #[DataProvider('escenariosHttps')]
    #[TestDox('esHttps() decide bien según cómo llega la petición')]
    public function testDeteccionHttps(array $server, bool $esperado, string $caso): void {
        $previo = $_SERVER;
        foreach (['HTTPS', 'SERVER_PORT', 'HTTP_X_FORWARDED_PROTO'] as $k) {
            unset($_SERVER[$k]);
        }
        $_SERVER = array_merge($_SERVER, $server);

        try {
            $this->assertSame($esperado, Seguridad::esHttps(), $caso);
        } finally {
            $_SERVER = $previo;
        }
    }

    public static function escenariosHttps(): array {
        return [
            'HTTPS=on'                => [['HTTPS' => 'on'], true, 'conexión directa cifrada'],
            'HTTPS=off'               => [['HTTPS' => 'off'], false, 'off literal significa HTTP'],
            'HTTPS vacío'             => [['HTTPS' => ''], false, 'vacío no es HTTPS'],
            'puerto 443'              => [['SERVER_PORT' => '443'], true, 'puerto estándar de TLS'],
            'puerto 80'               => [['SERVER_PORT' => '80'], false, 'HTTP plano'],
            'tras proxy inverso'      => [['HTTP_X_FORWARDED_PROTO' => 'https'], true, 'el despliegue previsto en VPS'],
            'proxy con http'          => [['HTTP_X_FORWARDED_PROTO' => 'http'], false, 'el proxy indica HTTP'],
            'sin ninguna pista'       => [[], false, 'por defecto, no asumir cifrado'],
        ];
    }

    /**
     * `SERVER_PORT` no existe en CLI ni en algunas configuraciones de
     * FastCGI. Leerlo sin comprobar provocaba un aviso justo antes de
     * `session_start()`, y esa salida impedía arrancar la sesión.
     */
    #[TestDox('esHttps() no falla si faltan las variables del servidor')]
    public function testDeteccionHttpsSinVariables(): void {
        $previo = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        try {
            $this->assertFalse(Seguridad::esHttps());
        } finally {
            $_SERVER = $previo;
        }
    }

    #[TestDox('las cabeceras se emiten sin lanzar error bajo CLI')]
    public function testCabecerasNoFallan(): void {
        Seguridad::cabeceras();
        Seguridad::sinCache();
        $this->assertTrue(true, 'no debe lanzar aunque las cabeceras ya se hayan enviado');
    }
}
