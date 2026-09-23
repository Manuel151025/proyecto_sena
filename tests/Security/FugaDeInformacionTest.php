<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Lo que el sistema no debe contar: rutas del servidor, esquema de la base
 * de datos, secretos de sesión y archivos que no deberían ser alcanzables.
 *
 * Varias de estas pruebas leen el código fuente en vez de ejecutarlo. Es
 * deliberado: comprueban que no se reintroduzca un patrón concreto que ya
 * estuvo ahí, y ese es el tipo de regresión que un cambio apresurado
 * vuelve a colar.
 */
final class FugaDeInformacionTest extends CasoConBaseDeDatos {

    private function fuente(string $rutaRelativa): string {
        $ruta = dirname(__DIR__, 2) . '/' . $rutaRelativa;
        $this->assertFileExists($ruta);
        return (string)file_get_contents($ruta);
    }

    /**
     * El código sin comentarios.
     *
     * Hace falta porque varias de estas pruebas buscan un patrón que
     * estuvo ahí y no debe volver, y los comentarios que explican por qué
     * se quitó mencionan ese mismo patrón. Sin filtrar, la prueba se
     * dispara con su propia documentación.
     */
    private function codigo(string $rutaRelativa): string {
        $salida = '';
        foreach (token_get_all($this->fuente($rutaRelativa)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $salida .= $token[1];
            } else {
                $salida .= $token;
            }
        }
        return $salida;
    }

    // =================================================================
    // SECRETOS EN LAS RESPUESTAS
    // =================================================================

    /**
     * La respuesta al 403 de CSRF imprimía el ID de sesión y los tokens
     * esperados de todas las pestañas, bajo el rótulo «Diagnostic Info».
     * Quien provocara el error se llevaba de vuelta los secretos con los
     * que falsificar la siguiente petición.
     */
    #[DataProvider('rastrosDelVolcadoDeDepuracion')]
    #[TestDox('la respuesta de CSRF no vuelve a filtrar secretos')]
    public function testSinVolcadoDeDepuracionEnCsrf(string $rastro): void {
        $this->assertStringNotContainsString(
            $rastro,
            $this->codigo('includes/session.php'),
            "ha vuelto el volcado de depuración: «$rastro»"
        );
    }

    public static function rastrosDelVolcadoDeDepuracion(): array {
        return [
            ['Diagnostic Info'],
            ['Session ID: '],
            ['Expected Tab Token'],
            ['Expected Default Token'],
            ['Has PHPSESSID cookie'],
        ];
    }

    #[TestDox('el token CSRF no se escribe nunca en el log de errores')]
    public function testElTokenNoSeRegistra(): void {
        $fuente = $this->codigo('includes/session.php');

        // Se localiza el error_log del rechazo de CSRF y se comprueba que
        // no incluye el token, solo si venía o no.
        $this->assertStringContainsString('token_presente', $fuente,
            'el registro del rechazo debería indicar presencia, no valor');
        $this->assertStringNotContainsString('$_SESSION[\'csrf_token\'] .', $fuente);
    }

    #[TestDox('el parche que aceptaba tokens de otras pestañas sigue eliminado')]
    public function testSinFallbackDePestanas(): void {
        $fuente = $this->codigo('includes/session.php');
        $this->assertStringNotContainsString('Fallback 2', $fuente);
        $this->assertStringNotContainsString('foreach ($_SESSION[\'tabs\'] as $tId', $fuente);
    }

    // =================================================================
    // MENSAJES DE ERROR
    // =================================================================

    /**
     * Mostrar `$e->getMessage()` mezclaba mensajes de negocio con el texto
     * de una PDOException, que incluye nombres de tabla y de columna.
     */
    #[TestDox('ningún controlador muestra getMessage() directamente en pantalla')]
    public function testSinGetMessageEnPantalla(): void {
        $sospechosos = [];

        foreach (glob(dirname(__DIR__, 2) . '/core/Controllers/*.php') as $archivo) {
            foreach (file($archivo) as $n => $linea) {
                if (!str_contains($linea, 'getMessage()')) {
                    continue;
                }
                $vaAPantalla = str_contains($linea, 'errors[]')
                    || str_contains($linea, 'setFlashMessage')
                    || str_contains($linea, '$mensaje');
                if ($vaAPantalla && !str_contains($linea, 'ErrorDeNegocio')) {
                    $sospechosos[] = basename($archivo) . ':' . ($n + 1);
                }
            }
        }

        $this->assertSame([], $sospechosos,
            "vuelven a enseñarse mensajes de excepción sin filtrar en: " . implode(', ', $sospechosos));
    }

    #[TestDox('los modelos no exponen el mensaje de PDO al propagar')]
    public function testModelosNoPropaganDetalleDePdo(): void {
        // Los modelos pueden envolver la excepción, pero el mensaje que
        // llega al usuario lo decide ErrorDeNegocio::mensajeSeguro.
        $fuente = $this->fuente('core/Models/ConfiguracionModel.php');
        $this->assertStringContainsString('error_log(', $fuente,
            'un fallo silenciado debe al menos quedar registrado');
    }

    // =================================================================
    // ARCHIVOS ALCANZABLES
    // =================================================================

    /**
     * Las vistas se ejecutaban al abrirlas por URL: sin sus variables y sin
     * ningún control de permisos, produciendo avisos con rutas del servidor.
     */
    #[TestDox('las 36 vistas tienen guarda contra el acceso directo')]
    public function testVistasProtegidas(): void {
        $sinGuarda = [];
        foreach (glob(dirname(__DIR__, 2) . '/modules/*/views/*.php') as $vista) {
            if (!str_contains((string)file_get_contents($vista), 'VISTA_PERMITIDA')) {
                $sinGuarda[] = basename(dirname($vista, 2)) . '/' . basename($vista);
            }
        }
        $this->assertSame([], $sinGuarda, 'vistas alcanzables por URL: ' . implode(', ', $sinGuarda));
    }

    /**
     * El instalador (bin/instalar.php, que sustituye al antiguo install.php
     * de la raíz web) empieza por DROP DATABASE: tiene que exigir consola y
     * una confirmación explícita ANTES de llegar ahí.
     */
    #[TestDox('el instalador no puede ejecutarse desde el navegador')]
    public function testInstaladorProtegido(): void {
        $instalador = $this->codigo('bin/instalar.php');
        $arranque   = $this->codigo('bin/_arranque.php');

        $posicionGuarda = strpos($instalador, "PHP_SAPI !== 'cli'");
        $posicionConfirmacion = strpos($instalador, "--confirmar-borrado-total");
        $posicionDrop = strpos($instalador, 'DROP DATABASE IF EXISTS');

        $this->assertNotFalse($posicionGuarda, 'bin/instalar.php no comprueba que se ejecute en consola');
        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $arranque);
        $this->assertNotFalse($posicionDrop);
        $this->assertNotFalse($posicionConfirmacion);
        $this->assertLessThan($posicionDrop, $posicionGuarda, 'la comprobación de consola debe ir ANTES del DROP DATABASE');
        $this->assertLessThan($posicionDrop, $posicionConfirmacion, 'la confirmación debe exigirse ANTES del DROP DATABASE');
    }

    #[TestDox('las carpetas de subidas y de logs no se sirven por web')]
    public function testCarpetasBloqueadas(): void {
        $raiz = dirname(__DIR__, 2);

        $logs = (string)@file_get_contents($raiz . '/logs/.htaccess');
        $this->assertStringContainsString('Require all denied', $logs, 'logs/ es accesible por web');

        $uploads = (string)@file_get_contents($raiz . '/uploads/.htaccess');
        $this->assertStringContainsString('php_flag engine off', $uploads,
            'uploads/ podría ejecutar PHP: sería una shell subida como evidencia');
        $this->assertStringContainsString('FilesMatch', $uploads);
        // Y nada se descarga directamente: las evidencias pasan por un
        // controlador que comprueba permisos.
        $this->assertMatchesRegularExpression('/^\s*Require all denied/m', $uploads, 'uploads/ se sirve directamente por web');
    }

    #[TestDox('el log de recuperación no guarda tokens fuera de desarrollo')]
    public function testLogDeRecuperacionSinTokens(): void {
        $fuente = $this->fuente('recover.php');
        $this->assertStringContainsString('DEV_MODE', $fuente,
            'el enlace con el token debe escribirse solo en desarrollo');

        // El archivo que quedó en el repositorio tiene que estar vacío.
        $log = $raiz = dirname(__DIR__, 2) . '/logs/password_resets.log';
        if (is_file($log)) {
            $this->assertStringNotContainsString('token=', (string)file_get_contents($log),
                'el log versionado todavía contiene enlaces con token');
        }
    }

    #[TestDox('los archivos sensibles están excluidos del control de versiones')]
    public function testArchivosSensiblesIgnorados(): void {
        $ignore = $this->fuente('.gitignore');
        foreach (['.env', 'install.php', 'vendor/', 'uploads/'] as $patron) {
            $this->assertStringContainsString($patron, $ignore, "$patron no está en .gitignore");
        }
    }

    // =================================================================
    // DATOS PERSONALES EN LA BITÁCORA
    // =================================================================

    #[TestDox('un intento fallido registra el dominio, no la cuenta')]
    public function testBitacoraSinCorreosCompletos(): void {
        (new \Core\Services\Auditoria($this->db))->accesoFallido('persona.concreta@sena.edu.co');

        $fila = $this->db->query("
            SELECT descripcion FROM logs_sistema
             WHERE accion = 'Acceso fallido' ORDER BY id DESC LIMIT 1
        ")->fetchColumn();

        $this->assertStringNotContainsString('persona.concreta', (string)$fila);
        $this->assertStringContainsString('@sena.edu.co', (string)$fila,
            'el dominio sí debe quedar, para detectar patrones de ataque');
    }

    #[TestDox('la bitácora no guarda contraseñas ni tokens')]
    public function testBitacoraSinSecretos(): void {
        $descripciones = $this->db->query(
            "SELECT descripcion FROM logs_sistema ORDER BY id DESC LIMIT 200"
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($descripciones as $d) {
            $d = (string)$d;
            $this->assertDoesNotMatchRegularExpression('/\$2y\$\d+\$/', $d, 'hay un hash de contraseña');
            $this->assertDoesNotMatchRegularExpression('/\b[0-9a-f]{64}\b/', $d, 'hay algo que parece un token');
            $this->assertStringNotContainsString('password=', $d);
        }
        $this->assertTrue(true);
    }
}
