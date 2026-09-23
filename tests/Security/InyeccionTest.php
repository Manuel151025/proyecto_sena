<?php
declare(strict_types=1);

namespace Tests\Security;

use Core\Models\EvaluacionesModel;
use Core\Models\LogsModel;
use Core\Models\UsuarioModel;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Inyección en sus distintas formas.
 *
 * El proyecto usa consultas preparadas en todas partes, así que lo que se
 * comprueba aquí no es que "no haya concatenación" —eso lo dice el código—
 * sino que el comportamiento observable sea el correcto: que una carga
 * maliciosa se trate como texto, que no altere el número de resultados y
 * que no destruya nada.
 */
final class InyeccionTest extends CasoConBaseDeDatos {

    /** Cargas clásicas contra un campo de búsqueda. */
    public static function cargasSql(): array {
        return [
            'tautología'         => ["' OR '1'='1"],
            'comentario'         => ["admin'--"],
            'union'              => ["' UNION SELECT password FROM usuarios--"],
            'apagado de where'   => ["' OR 1=1--"],
            'sentencia colada'   => ["x'; DROP TABLE usuarios;--"],
            'subconsulta'        => ["' AND (SELECT COUNT(*) FROM usuarios) > 0--"],
            'comilla simple'     => ["'"],
            'comilla doble'      => ['"'],
            'barra invertida'    => ['\\'],
            'temporal (sleep)'   => ["' OR SLEEP(5)--"],
            'comodines de LIKE'  => ['%_%'],
            'byte nulo'          => ["admin\x00"],
            'punto y coma'       => ['a; b'],
        ];
    }

    // =================================================================
    // BÚSQUEDAS
    // =================================================================

    /**
     * El campo de búsqueda va a un `LIKE ?`. Si la carga se interpretara,
     * la tautología devolvería todas las filas; como se trata como texto,
     * devuelve las que contengan literalmente esa cadena, es decir ninguna.
     */
    #[DataProvider('cargasSql')]
    #[TestDox('la búsqueda de usuarios trata la carga como texto')]
    public function testBusquedaDeUsuarios(string $carga): void {
        $modelo = new UsuarioModel($this->db);
        $total  = $modelo->contar([]);

        $conCarga = $modelo->contar(['search' => $carga]);

        $this->assertLessThan(
            $total,
            $conCarga + 1,
            "la carga «$carga» devolvió tantos resultados como la lista completa"
        );
        $this->assertGreaterThanOrEqual(0, $conCarga);
    }

    #[DataProvider('cargasSql')]
    #[TestDox('la búsqueda de evaluaciones trata la carga como texto')]
    public function testBusquedaDeEvaluaciones(string $carga): void {
        $modelo = new EvaluacionesModel($this->db);
        $coord  = $this->idCoordinador();

        $conCarga = $modelo->contarEvaluaciones(ROL_COORDINADOR, $coord, 0, 0, '', $carga);
        $total    = $modelo->contarEvaluaciones(ROL_COORDINADOR, $coord, 0, 0, '', '');

        $this->assertLessThan($total, $conCarga + 1, "la carga «$carga» no se filtró");
    }

    #[DataProvider('cargasSql')]
    #[TestDox('la búsqueda en la bitácora trata la carga como texto')]
    public function testBusquedaEnLogs(string $carga): void {
        $modelo = new LogsModel($this->db);
        $filas = $modelo->getLogs($carga, '', 25, 0);
        $this->assertIsArray($filas);
    }

    /**
     * Después de lanzar todas las cargas, las tablas tienen que seguir ahí.
     * Es la comprobación tonta que confirma que ningún `DROP` pasó.
     */
    #[TestDox('tras las cargas, el esquema sigue intacto')]
    public function testElEsquemaSobrevive(): void {
        $modelo = new UsuarioModel($this->db);
        foreach (self::cargasSql() as [$carga]) {
            $modelo->contar(['search' => $carga]);
        }

        foreach (['usuarios', 'evaluaciones', 'fichas', 'aprendices', 'historial_evaluaciones'] as $tabla) {
            $this->assertNotFalse(
                $this->db->query("SELECT 1 FROM `$tabla` LIMIT 1"),
                "la tabla $tabla ya no responde"
            );
        }
    }

    /**
     * `SLEEP(5)` en una consulta interpretada retrasaría la respuesta cinco
     * segundos. Como texto, no cuesta nada.
     */
    #[TestDox('una inyección temporal no retrasa la respuesta')]
    public function testInyeccionTemporal(): void {
        $modelo = new UsuarioModel($this->db);

        $t = microtime(true);
        $modelo->contar(['search' => "' OR SLEEP(5)--"]);
        $tardo = microtime(true) - $t;

        $this->assertLessThan(2.0, $tardo, 'la consulta se retrasó: la carga se ejecutó');
    }

    // =================================================================
    // FILTROS Y ORDENACIÓN
    // =================================================================

    /**
     * Los filtros de rol y estado van a comparaciones de igualdad. Un valor
     * inventado debe devolver cero filas, nunca todas.
     */
    #[TestDox('un filtro con una carga devuelve cero filas, no todas')]
    public function testFiltrosConCarga(): void {
        $modelo = new UsuarioModel($this->db);
        $total = $modelo->contar([]);

        foreach (["coordinador' OR '1'='1", "' OR 1=1--", 'inventado'] as $carga) {
            $this->assertSame(
                0,
                $modelo->contar(['rol' => $carga]),
                "el filtro de rol aceptó «$carga»"
            );
        }
        $this->assertGreaterThan(0, $total);
    }

    /**
     * LIMIT y OFFSET no admiten parámetros ligados con
     * ATTR_EMULATE_PREPARES en false, así que se interpolan tras un cast a
     * entero. Esta prueba confirma que el cast es efectivo.
     */
    #[TestDox('LIMIT y OFFSET interpolados no admiten inyección')]
    public function testLimitYOffsetNoInyectables(): void {
        $modelo = new UsuarioModel($this->db);

        // Los tipos declarados ya impiden pasar una cadena; se comprueba
        // que valores extremos tampoco rompan la consulta.
        foreach ([[25, 0], [1, 0], [100, 999999], [1, 0]] as [$limite, $desplazamiento]) {
            $filas = $modelo->listar([], $limite, $desplazamiento);
            $this->assertIsArray($filas);
            $this->assertLessThanOrEqual($limite, count($filas));
        }
    }

    // =================================================================
    // ESCRITURA
    // =================================================================

    /**
     * Una carga guardada como texto y recuperada intacta demuestra dos
     * cosas: que no se interpretó al escribir y que no se corrompió.
     */
    #[TestDox('una carga se guarda y se recupera literalmente')]
    public function testCargaAlmacenadaLiteral(): void {
        $carga = "'; DROP TABLE usuarios;-- <script>alert(1)</script>";

        $this->db->prepare("
            INSERT INTO logs_sistema (usuario_id, accion, modulo, descripcion)
            VALUES (?, 'Prueba', 'Inyección', ?)
        ")->execute([$this->idCoordinador(), $carga]);

        $id = (int)$this->db->lastInsertId();
        $guardado = $this->db->query("SELECT descripcion FROM logs_sistema WHERE id = $id")->fetchColumn();

        $this->assertSame($carga, $guardado, 'el texto se alteró al guardarlo');
        $this->assertNotFalse($this->db->query("SELECT 1 FROM usuarios LIMIT 1"), 'la tabla desapareció');
    }

    // =================================================================
    // XSS ALMACENADO
    // =================================================================

    /**
     * El escapado ocurre en la vista, no en el modelo. Lo que se comprueba
     * aquí es la propiedad que lo hace posible: que `htmlspecialchars` con
     * ENT_QUOTES neutraliza todas las cargas habituales.
     */
    #[DataProvider('cargasXss')]
    #[TestDox('el escapado de la vista neutraliza la carga de XSS')]
    public function testEscapadoDeXss(string $carga): void {
        $escapado = htmlspecialchars($carga, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script', $escapado);
        $this->assertStringNotContainsString('<img', $escapado);
        $this->assertStringNotContainsString('<svg', $escapado);
        // Sin comillas sin escapar no se puede romper un atributo.
        $this->assertStringNotContainsString('"', str_replace('&quot;', '', $escapado));
        $this->assertStringNotContainsString("'", str_replace('&#039;', '', $escapado));
    }

    public static function cargasXss(): array {
        return [
            'script simple'    => ['<script>alert(1)</script>'],
            'imagen con onerror' => ['<img src=x onerror=alert(1)>'],
            'svg con onload'   => ['<svg onload=alert(1)>'],
            'romper atributo'  => ['" onmouseover="alert(1)'],
            'romper con simple'=> ["' onfocus='alert(1)"],
            'javascript: en href' => ['javascript:alert(1)'],
            'cierre de etiqueta'  => ['</textarea><script>alert(1)</script>'],
            'entidad codificada'  => ['&lt;script&gt;alert(1)&lt;/script&gt;'],
        ];
    }

    /**
     * En los `onclick` la interpolación va por `json_encode` + escapado:
     * el valor se convierte en un literal de JavaScript válido y seguro.
     */
    #[TestDox('el patrón json_encode + htmlspecialchars aísla el valor en JS')]
    public function testPatronDeInterpolacionEnJs(): void {
        foreach (["O'Brien", '"; alert(1); //', '</script>', "salto\nde línea"] as $valor) {
            $literal = htmlspecialchars(json_encode($valor), ENT_QUOTES, 'UTF-8');

            $this->assertStringNotContainsString('</script>', $literal);
            // Al deshacer el escapado HTML debe quedar JSON válido.
            $this->assertSame($valor, json_decode(html_entity_decode($literal, ENT_QUOTES, 'UTF-8')));
        }
    }

    // =================================================================
    // TRAVESÍA DE RUTAS
    // =================================================================

    /**
     * Los nombres de archivo subidos se sustituyen por uno aleatorio, y la
     * extensión se toma con `pathinfo`. Se comprueba que ninguna de las
     * formas habituales de travesía sobreviva a ese tratamiento.
     */
    #[DataProvider('nombresMaliciosos')]
    #[TestDox('un nombre de archivo malicioso no produce una ruta peligrosa')]
    public function testTravesiaDeRutasEnSubidas(string $nombre): void {
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $generado  = bin2hex(random_bytes(16)) . '.' . $extension;

        $this->assertStringNotContainsString('..', $generado);
        $this->assertStringNotContainsString('/', $generado);
        $this->assertStringNotContainsString('\\', $generado);
        $this->assertStringNotContainsString("\x00", $generado);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.[a-z0-9]*$/', $generado);
    }

    public static function nombresMaliciosos(): array {
        return [
            'travesía unix'     => ['../../../etc/passwd'],
            'travesía windows'  => ['..\\..\\windows\\system32\\config.sys'],
            'ruta absoluta'     => ['/var/www/html/shell.php'],
            'byte nulo'         => ["inofensivo.pdf\x00.php"],
            'doble extensión'   => ['documento.pdf.php'],
            'codificada'        => ['%2e%2e%2fshell.php'],
        ];
    }
}
