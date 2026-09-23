<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Qué alcanza el navegador.
 *
 * La carpeta del proyecto entera vive bajo la raíz web (XAMPP y la imagen
 * Docker la sirven así), y hasta que se añadió el .htaccess de la raíz se
 * podían descargar `.env` (credenciales de base de datos y SMTP),
 * `.git/config` (y con él el repositorio), el volcado SQL con los hash de
 * contraseñas, y se EJECUTABAN las migraciones al pedirlas por URL.
 *
 * Estas pruebas no levantan Apache: comprueban que las reglas existan y
 * cubran todo lo que hay en disco, de modo que añadir una carpeta nueva sin
 * decidir si es pública rompa la suite en lugar de publicarla en silencio.
 */
final class SuperficieWebTest extends CasoDePrueba {
    /** Carpetas de la raíz que el navegador sí necesita. */
    private const PUBLICAS = ['assets', 'modules', 'uploads'];

    private function raiz(): string {
        return dirname(__DIR__, 2);
    }

    private function htaccessRaiz(): string {
        $ruta = $this->raiz() . '/.htaccess';
        $this->assertFileExists($ruta, 'falta el .htaccess de la raíz');
        return (string)file_get_contents($ruta);
    }

    #[TestDox('toda carpeta de la raíz es pública a propósito o está negada')]
    public function testCarpetasCubiertas(): void {
        $reglas = $this->htaccessRaiz();
        $this->assertMatchesRegularExpression('/RewriteRule \^\(([^)]*)\)\(\/\|\$\) - \[F,L\]/', $reglas);
        preg_match('/RewriteRule \^\(([^)]*)\)\(\/\|\$\) - \[F,L\]/', $reglas, $m);
        $negadas = array_map(static fn($x) => stripslashes($x), explode('|', $m[1]));

        $sinDecidir = [];
        foreach (scandir($this->raiz()) as $entrada) {
            if ($entrada === '.' || $entrada === '..' || !is_dir($this->raiz() . '/' . $entrada)) {
                continue;
            }
            if (in_array($entrada, self::PUBLICAS, true) || in_array($entrada, $negadas, true)) {
                continue;
            }
            $sinDecidir[] = $entrada;
        }
        $this->assertSame([], $sinDecidir,
            'carpetas sin regla en el .htaccess de la raíz (¿son públicas o no?): ' . implode(', ', $sinDecidir));
    }

    #[TestDox('las carpetas internas versionadas llevan su propia denegación')]
    public function testSegundaCapa(): void {
        foreach (['core', 'includes', 'config', 'database', 'tests', 'docs', 'layouts', 'components', 'logs', 'bin'] as $carpeta) {
            $archivo = $this->raiz() . "/$carpeta/.htaccess";
            $this->assertFileExists($archivo, "$carpeta/ no tiene .htaccess propio");
            $this->assertStringContainsString('Require all denied', (string)file_get_contents($archivo), "$carpeta/.htaccess no deniega");
        }
    }

    #[TestDox('los archivos ocultos, volcados y manifiestos del proyecto están negados')]
    public function testArchivosSensiblesNegados(): void {
        $reglas = $this->htaccessRaiz();
        $this->assertStringContainsString('<FilesMatch "^\.">', $reglas, 'no se niegan los archivos ocultos (.env, .git)');
        foreach (['sql', 'md', 'lock', 'yml', 'log', 'env'] as $ext) {
            $this->assertMatchesRegularExpression('/FilesMatch "\\\\\.\([^"]*\b' . $ext . '\b/', $reglas, "no se niega *.$ext");
        }
        $this->assertStringContainsString('composer\.json', $reglas);
        $this->assertStringContainsString('install\.php', $reglas);
        $this->assertStringContainsString('Options -Indexes', $reglas, 'los listados de directorio siguen activos');
    }

    #[TestDox('ninguna migración se puede ejecutar desde el navegador')]
    public function testMigracionesSoloPorConsola(): void {
        $sinGuarda = [];
        foreach (glob($this->raiz() . '/{database/migraciones,database/semillas,bin}/*.php', GLOB_BRACE) ?: [] as $archivo) {
            $fuente = (string)file_get_contents($archivo);
            if (!str_contains($fuente, "PHP_SAPI !== 'cli'")) {
                $sinGuarda[] = basename($archivo);
            }
        }
        $this->assertSame([], $sinGuarda, 'scripts ejecutables por URL: ' . implode(', ', $sinGuarda));
    }

    #[TestDox('la imagen Docker no incluye el repositorio ni los secretos')]
    public function testDockerignore(): void {
        $ruta = $this->raiz() . '/.dockerignore';
        $this->assertFileExists($ruta);
        $lineas = array_map('trim', file($ruta) ?: []);
        foreach (['.git', '.env', '*.sql', 'tests'] as $patron) {
            $this->assertContains($patron, $lineas, "$patron entraría en la imagen");
        }
    }

    #[TestDox('includes/ ya no tiene puntos de entrada que pida el navegador')]
    public function testSinEntradasEnIncludes(): void {
        $this->assertFileDoesNotExist($this->raiz() . '/includes/api_notificaciones.php');
        $auth = (string)file_get_contents($this->raiz() . '/includes/auth.php');
        $this->assertStringNotContainsString("\$_GET['action']", $auth, 'auth.php vuelve a atender peticiones directas');
    }
}
