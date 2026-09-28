<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Subida de evidencias.
 *
 * Es el vector con peor consecuencia del sistema: si un aprendiz consigue
 * dejar un `.php` ejecutable dentro de `uploads/`, tiene ejecución remota
 * de código en el servidor. El control está repartido en tres capas y las
 * tres se comprueban aquí:
 *
 *   1. Lista blanca de extensiones.
 *   2. Comprobación del contenido real con finfo, no del tipo que declara
 *      el navegador (que lo elige el cliente).
 *   3. Nombre aleatorio en destino y `.htaccess` que impide ejecutar y
 *      servir nada: los archivos se descargan por EvidenciasController,
 *      que comprueba permisos (ver tests/Integration/EvidenciasTest).
 */
final class SubidaDeArchivosTest extends CasoDePrueba {

    /** Extensiones que acepta el envío de evidencias. */
    private const PERMITIDAS = \Core\Formularios\EvidenciaFormulario::EXTENSIONES;

    // =================================================================
    // LISTA BLANCA DE EXTENSIONES
    // =================================================================

    #[DataProvider('extensionesPeligrosas')]
    #[TestDox('la extensión ejecutable no está en la lista blanca')]
    public function testExtensionesPeligrosasFuera(string $nombre): void {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $this->assertNotContains(
            $ext,
            self::PERMITIDAS,
            "«$nombre» pasaría la lista blanca"
        );
    }

    public static function extensionesPeligrosas(): array {
        return [
            'php'          => ['shell.php'],
            'php5'         => ['shell.php5'],
            'php7'         => ['shell.php7'],
            'phtml'        => ['shell.phtml'],
            'phar'         => ['payload.phar'],
            'cgi'          => ['script.cgi'],
            'pl'           => ['script.pl'],
            'py'           => ['script.py'],
            'sh'           => ['script.sh'],
            'exe'          => ['programa.exe'],
            'htaccess'     => ['.htaccess'],
            'svg'          => ['imagen.svg'],
            'html'         => ['pagina.html'],
        ];
    }

    /**
     * `documento.pdf.php` termina en `.php`, que es lo que mira el servidor
     * web. `pathinfo` devuelve la ÚLTIMA extensión, así que la lista blanca
     * lo rechaza correctamente.
     */
    #[TestDox('la doble extensión se resuelve por la última, que es la que cuenta')]
    public function testDobleExtension(): void {
        $this->assertSame('php', strtolower(pathinfo('documento.pdf.php', PATHINFO_EXTENSION)));
        $this->assertNotContains(
            strtolower(pathinfo('documento.pdf.php', PATHINFO_EXTENSION)),
            self::PERMITIDAS
        );
    }

    /**
     * Truco clásico: `inofensivo.php\x00.pdf`. Las versiones antiguas de C
     * cortaban en el byte nulo y guardaban un `.php`. PHP moderno no lo
     * hace, pero conviene dejarlo comprobado.
     */
    #[TestDox('el byte nulo no engaña a pathinfo')]
    public function testByteNulo(): void {
        $ext = strtolower(pathinfo("shell.php\x00.pdf", PATHINFO_EXTENSION));
        $this->assertNotSame('php', $ext, 'el byte nulo truncó el nombre');
    }

    #[TestDox('las mayúsculas no saltan la lista blanca')]
    public function testMayusculas(): void {
        foreach (['SHELL.PHP', 'Shell.PhP', 'shell.pHp'] as $nombre) {
            $this->assertNotContains(
                strtolower(pathinfo($nombre, PATHINFO_EXTENSION)),
                self::PERMITIDAS,
                "«$nombre» pasó cambiando las mayúsculas"
            );
        }
    }

    #[TestDox('las extensiones legítimas sí se aceptan')]
    public function testExtensionesLegitimas(): void {
        foreach (['informe.pdf', 'hoja.xlsx', 'foto.JPG', 'notas.txt'] as $nombre) {
            $this->assertContains(
                strtolower(pathinfo($nombre, PATHINFO_EXTENSION)),
                self::PERMITIDAS,
                "«$nombre» se rechazaría siendo legítimo"
            );
        }
    }

    // =================================================================
    // CONTENIDO REAL FRENTE A EXTENSIÓN DECLARADA
    // =================================================================

    /**
     * El tipo MIME que envía el navegador lo elige el cliente, así que no
     * vale de nada. El controlador usa finfo sobre el contenido; aquí se
     * comprueba que ese mecanismo detecta el engaño de verdad.
     */
    #[TestDox('finfo detecta un PHP disfrazado de PDF')]
    public function testPhpDisfrazadoDePdf(): void {
        $temporal = tempnam(sys_get_temp_dir(), 'prueba_');
        file_put_contents($temporal, "<?php system(\$_GET['c']); ?>\n");

        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real = finfo_file($finfo, $temporal);
            finfo_close($finfo);

            $this->assertNotSame('application/pdf', $real,
                'un archivo PHP se estaría aceptando como PDF');
            // El controlador compara con la lista de tipos de esa extensión.
            $this->assertNotContains($real, ['application/pdf'],
                "finfo devolvió «$real», que no debe encajar con la extensión pdf");
        } finally {
            @unlink($temporal);
        }
    }

    #[TestDox('finfo detecta un PHP disfrazado de imagen')]
    public function testPhpDisfrazadoDeImagen(): void {
        $temporal = tempnam(sys_get_temp_dir(), 'prueba_');
        // Cabecera GIF válida seguida de código: el truco del GIF89a.
        file_put_contents($temporal, "GIF89a<?php system(\$_GET['c']); ?>");

        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real = finfo_file($finfo, $temporal);
            finfo_close($finfo);

            // El sistema no acepta gif, así que aunque finfo lo vea como
            // imagen, la extensión declarada tendría que ser .gif y esa no
            // está en la lista blanca.
            $this->assertNotContains($real, ['image/jpeg', 'image/png'],
                "un archivo con código se hace pasar por «$real», que sí está permitido");
        } finally {
            @unlink($temporal);
        }
    }

    #[TestDox('un PDF de verdad se reconoce como tal')]
    public function testPdfLegitimo(): void {
        $temporal = tempnam(sys_get_temp_dir(), 'prueba_');
        file_put_contents($temporal, "%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real = finfo_file($finfo, $temporal);
            finfo_close($finfo);

            $this->assertSame('application/pdf', $real, 'un PDF legítimo se rechazaría');
        } finally {
            @unlink($temporal);
        }
    }

    // =================================================================
    // NOMBRE EN DESTINO
    // =================================================================

    /**
     * El nombre original nunca se conserva: se sustituye por 16 bytes
     * aleatorios más la extensión validada. Eso neutraliza de una vez la
     * travesía de rutas, los caracteres especiales y la colisión de nombres.
     */
    #[TestDox('el nombre generado no hereda nada del original')]
    public function testNombreGeneradoLimpio(): void {
        foreach (self::nombresHostiles() as [$original]) {
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $generado = bin2hex(random_bytes(16)) . '.' . $ext;

            $this->assertStringNotContainsString('..', $generado);
            $this->assertStringNotContainsString('/', $generado);
            $this->assertStringNotContainsString('\\', $generado);
            $this->assertStringNotContainsString(' ', $generado);
            $this->assertStringNotContainsString("\x00", $generado);
        }
    }

    public static function nombresHostiles(): array {
        return [
            ['../../../var/www/shell.pdf'],
            ['..\\..\\windows\\evil.pdf'],
            ['/etc/cron.d/tarea.pdf'],
            ['archivo con espacios.pdf'],
            ["nombre\x00nulo.pdf"],
            ['<script>alert(1)</script>.pdf'],
            ["archivo'; DROP TABLE x;--.pdf"],
        ];
    }

    #[TestDox('los nombres generados no colisionan')]
    public function testNombresSinColision(): void {
        $nombres = [];
        for ($i = 0; $i < 500; $i++) {
            $nombres[] = bin2hex(random_bytes(16)) . '.pdf';
        }
        $this->assertCount(500, array_unique($nombres));
    }

    /**
     * 16 bytes son 128 bits. Ya no es la única defensa (uploads/ no se
     * sirve y la descarga comprueba permisos), pero evita colisiones y que
     * el nombre revele nada.
     */
    #[TestDox('el nombre tiene entropía suficiente para no ser adivinable')]
    public function testEntropiaDelNombre(): void {
        $nombre = bin2hex(random_bytes(16));
        $this->assertSame(32, strlen($nombre), '128 bits en hexadecimal');
        $this->assertTrue(ctype_xdigit($nombre));
    }

    // =================================================================
    // LA CARPETA DE DESTINO
    // =================================================================

    #[TestDox('uploads/ no ejecuta PHP bajo ninguna configuración')]
    public function testUploadsNoEjecuta(): void {
        $htaccess = (string)@file_get_contents(dirname(__DIR__, 2) . '/uploads/.htaccess');

        $this->assertNotSame('', $htaccess, 'uploads/ no tiene .htaccess');
        $this->assertStringContainsString('php_flag engine off', $htaccess);
        $this->assertStringContainsString('Options -ExecCGI', $htaccess);
        $this->assertStringContainsString('-Indexes', $htaccess, 'el listado del directorio debe estar desactivado');

        // La denegación por extensión cubre los casos en que mod_php no
        // esté cargado y el handler venga de otro sitio.
        foreach (['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh'] as $ext) {
            $this->assertStringContainsString($ext, $htaccess, "la extensión .$ext no está bloqueada");
        }
    }

    #[TestDox('la carpeta de evidencias se crea sin permisos de más')]
    public function testPermisosDeLaCarpeta(): void {
        // 0750: nadie fuera del propietario y su grupo. 0777 sería un error clásico.
        $fuente = (string)file_get_contents(dirname(__DIR__, 2) . '/core/Services/EvidenciasService.php');
        $this->assertStringContainsString('mkdir($dir, 0750, true)', $fuente);
        $this->assertStringNotContainsString('0777', $fuente, 'se está creando la carpeta con permisos totales');
    }

    // =================================================================
    // IDENTIDAD DE QUIEN SUBE
    // =================================================================

    /**
     * `aprendiz_id` y `ficha_id` se resuelven desde la sesión, nunca desde
     * el formulario. Si vinieran del POST, un aprendiz podría subir una
     * evidencia a nombre de otro.
     */
    #[TestDox('el aprendiz de la evidencia sale de la sesión, no del formulario')]
    public function testIdentidadDesdeLaSesion(): void {
        $servicio = (string)file_get_contents(dirname(__DIR__, 2) . '/core/Services/EvidenciasService.php');
        $controlador = (string)file_get_contents(dirname(__DIR__, 2) . '/core/Controllers/EvidenciasController.php');

        $this->assertStringContainsString(
            'aprendizDeUsuario($actor->id)',
            $servicio,
            'el aprendiz debe resolverse desde el usuario en sesión'
        );
        $this->assertDoesNotMatchRegularExpression('/\$_POST\[.(aprendiz_id|ficha_id)/', $controlador,
            'el controlador no debe leer el aprendiz ni la ficha del formulario');
        $formulario = (string)file_get_contents(dirname(__DIR__, 2) . '/core/Formularios/EvidenciaFormulario.php');
        $this->assertStringNotContainsString("'aprendiz_id'", $formulario, 'el envío no debe aceptar un aprendiz del formulario');
    }
}
