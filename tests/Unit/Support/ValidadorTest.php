<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\Validador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * El validador es la primera barrera entre `$_POST` y la consulta SQL, así
 * que se prueba con lo que de verdad llega: valores vacíos, tipos que no
 * tocan, cadenas que un cast aceptaría por error y entradas hostiles.
 */
final class ValidadorTest extends CasoDePrueba {

    // =================================================================
    // ENTEROS
    // =================================================================

    #[TestDox('entero() acepta un número normal')]
    public function testEnteroAceptaValorValido(): void {
        $v = new Validador(['n' => '42']);
        $this->assertSame(42, $v->entero('n', 'N'));
        $this->assertFalse($v->hayErrores());
    }

    #[TestDox('entero() recorta espacios alrededor')]
    public function testEnteroRecortaEspacios(): void {
        $v = new Validador(['n' => '  7  ']);
        $this->assertSame(7, $v->entero('n', 'N'));
        $this->assertFalse($v->hayErrores());
    }

    /**
     * El caso que motivó usar filter_var en vez de un cast: `(int)"12abc"`
     * devuelve 12 sin quejarse, y ese 12 acabaría siendo el id de otro
     * registro.
     */
    #[DataProvider('enterosInvalidos')]
    #[TestDox('entero() rechaza un valor que un cast aceptaría mal')]
    public function testEnteroRechazaValoresQueUnCastAceptaria(string $entrada): void {
        $v = new Validador(['n' => $entrada]);
        $this->assertSame(0, $v->entero('n', 'N'));
        $this->assertTrue($v->hayErrores(), "se aceptó '$entrada'");
    }

    public static function enterosInvalidos(): array {
        return [
            'letras al final'      => ['12abc'],
            'letras al principio'  => ['abc12'],
            'decimal'              => ['3.14'],
            'notación científica'  => ['1e3'],
            'hexadecimal'          => ['0x1A'],
            'solo texto'           => ['muchos'],
            'inyección'            => ["1 OR 1=1"],
            'desbordamiento'       => ['99999999999999999999'],
            'espacio en medio'     => ['1 2'],
            'coma de millares'     => ['1,000'],
        ];
    }

    #[TestDox('entero() aplica el rango mínimo y máximo')]
    public function testEnteroAplicaRango(): void {
        $v = new Validador(['n' => '150']);
        $this->assertSame(0, $v->entero('n', 'Porcentaje', 0, 100));
        $this->assertStringContainsString('entre 0 y 100', $v->primerError());
    }

    #[TestDox('entero() obligatorio vacío da error; opcional vacío no')]
    public function testEnteroObligatorioYOpcional(): void {
        $obligatorio = new Validador([]);
        $obligatorio->entero('n', 'N');
        $this->assertTrue($obligatorio->hayErrores());

        $opcional = new Validador([]);
        $this->assertSame(0, $opcional->entero('n', 'N', 0, PHP_INT_MAX, false));
        $this->assertFalse($opcional->hayErrores());
    }

    #[TestDox('id() exige un entero positivo')]
    public function testIdRechazaCeroYNegativos(): void {
        foreach (['0', '-1', '-999'] as $malo) {
            $v = new Validador(['id' => $malo]);
            $this->assertSame(0, $v->id('id', 'ID'));
            $this->assertTrue($v->hayErrores(), "aceptó '$malo' como id");
        }
    }

    #[TestDox('id() opcional trata "0" y vacío como «sin elegir»')]
    public function testIdOpcionalSinElegir(): void {
        foreach (['', '0', ' 0 '] as $vacio) {
            $v = new Validador(['id' => $vacio]);
            $this->assertSame(0, $v->id('id', 'ID', false));
            $this->assertFalse($v->hayErrores(), "rechazó '$vacio' en un id opcional");
        }
        $v = new Validador(['id' => '12abc']);
        $this->assertSame(0, $v->id('id', 'ID', false));
        $this->assertTrue($v->hayErrores(), 'un id opcional con basura debe rechazarse');
    }

    // =================================================================
    // TEXTO
    // =================================================================

    #[TestDox('texto() recorta, quita HTML y caracteres de control')]
    public function testTextoNormaliza(): void {
        $v = new Validador(['t' => "  <b>hola</b>\x00\x07mundo  "]);
        $this->assertSame('holamundo', $v->texto('t', 'T'));
    }

    #[TestDox('texto() conserva tildes, eñes y signos del español')]
    public function testTextoConservaCaracteresDelEspanol(): void {
        $v = new Validador(['t' => 'Muñoz Pérez — ¿Evaluación? ¡Sí!']);
        $this->assertSame('Muñoz Pérez — ¿Evaluación? ¡Sí!', $v->texto('t', 'T', 0, 100));
        $this->assertFalse($v->hayErrores());
    }

    #[TestDox('texto() cuenta caracteres, no bytes')]
    public function testTextoMideEnCaracteresNoEnBytes(): void {
        // 10 eñes son 20 bytes en UTF-8; el límite es de caracteres.
        $v = new Validador(['t' => str_repeat('ñ', 10)]);
        $v->texto('t', 'T', 0, 15);
        $this->assertFalse($v->hayErrores(), 'contó bytes en lugar de caracteres');
    }

    #[TestDox('texto() aplica longitud mínima y máxima')]
    public function testTextoAplicaLongitudes(): void {
        $corto = new Validador(['t' => 'ab']);
        $corto->texto('t', 'T', 5, 100);
        $this->assertStringContainsString('al menos 5', $corto->primerError());

        $largo = new Validador(['t' => str_repeat('x', 300)]);
        $largo->texto('t', 'T', 0, 255);
        $this->assertStringContainsString('255', $largo->primerError());
    }

    #[TestDox('texto() con limpiarHtml=false conserva el marcado')]
    public function testTextoPuedeConservarHtml(): void {
        $v = new Validador(['t' => '<b>negrita</b>']);
        $this->assertSame('<b>negrita</b>', $v->texto('t', 'T', 0, 100, true, false));
    }

    // =================================================================
    // ENUM
    // =================================================================

    #[TestDox('enum() acepta exactamente los valores de la lista')]
    public function testEnumAceptaValoresDeLaLista(): void {
        $v = new Validador(['e' => 'activo']);
        $this->assertSame('activo', $v->enum('e', 'Estado', ['activo', 'inactivo']));
        $this->assertFalse($v->hayErrores());
    }

    #[DataProvider('enumsHostiles')]
    #[TestDox('enum() rechaza lo que no está en la lista')]
    public function testEnumRechazaLoQueNoEsta(string $entrada): void {
        $v = new Validador(['e' => $entrada]);
        $r = $v->enum('e', 'Estado', ['activo', 'inactivo'], 'activo');
        $this->assertSame('activo', $r, "no cayó al valor por defecto con '$entrada'");
        $this->assertTrue($v->hayErrores());
    }

    public static function enumsHostiles(): array {
        return [
            'valor inventado'   => ['pendiente_de_algo'],
            'mayúsculas'        => ['ACTIVO'],
            'inyección SQL'     => ["activo' OR '1'='1"],
            'sentencia colada'  => ['activo; DROP TABLE fichas'],
            'XSS'               => ['<script>alert(1)</script>'],
            'byte nulo'         => ["activo\x00inactivo"],
            'travesía de rutas' => ['../../etc/passwd'],
            'cadena enorme'     => ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ];
    }

    /**
     * El mensaje de error no debe repetir lo que envió el usuario: sería
     * devolverle su propia entrada al navegador sin ninguna necesidad.
     */
    #[TestDox('enum() no refleja en el mensaje el valor recibido')]
    public function testEnumNoReflejaLaEntradaEnElMensaje(): void {
        $v = new Validador(['e' => '<script>alert(1)</script>']);
        $v->enum('e', 'Estado', ['activo']);
        $this->assertStringNotContainsString('script', implode(' ', $v->errores()));
    }

    #[TestDox('enum() usa el valor por defecto cuando llega vacío')]
    public function testEnumUsaValorPorDefecto(): void {
        $v = new Validador(['e' => '']);
        $this->assertSame('activo', $v->enum('e', 'Estado', ['activo', 'inactivo'], 'activo'));
        $this->assertFalse($v->hayErrores());
    }

    // =================================================================
    // FECHAS
    // =================================================================

    #[TestDox('fecha() acepta el formato ISO que emite <input type=date>')]
    public function testFechaAceptaIso(): void {
        $v = new Validador(['f' => '2026-03-15']);
        $this->assertSame('2026-03-15', $v->fecha('f', 'Fecha'));
        $this->assertFalse($v->hayErrores());
    }

    #[DataProvider('fechasInvalidas')]
    #[TestDox('fecha() rechaza lo que no es una fecha ISO real')]
    public function testFechaRechazaInvalidas(string $entrada, string $motivo): void {
        $v = new Validador(['f' => $entrada]);
        $this->assertNull($v->fecha('f', 'Fecha'), "aceptó '$entrada' ($motivo)");
        $this->assertTrue($v->hayErrores());
    }

    public static function fechasInvalidas(): array {
        return [
            'texto'                 => ['no-es-fecha', 'antes se guardaba como 0000-00-00'],
            'fecha cero'            => ['0000-00-00', 'valor centinela sin significado'],
            'día imposible'         => ['2026-02-30', 'encaja con el patrón pero no existe'],
            'mes imposible'         => ['2026-13-01', 'mes 13'],
            'formato español'       => ['15/03/2026', 'no es ISO'],
            'sin ceros a izquierda' => ['2026-2-3', 'ambiguo'],
            'palabra relativa'      => ['now', 'strtotime lo aceptaría'],
            'año muy antiguo'       => ['1899-01-01', 'fuera de rango académico'],
            'año muy lejano'        => ['2200-01-01', 'fuera de rango académico'],
            'inyección'             => ["2026-01-01' OR '1'='1", 'intento de inyección'],
            'con hora'              => ['2026-03-15 10:00:00', 'lleva hora'],
        ];
    }

    #[TestDox('fecha() opcional vacía devuelve null sin error')]
    public function testFechaOpcionalVacia(): void {
        $v = new Validador([]);
        $this->assertNull($v->fecha('f', 'Fecha', false));
        $this->assertFalse($v->hayErrores());
    }

    #[TestDox('rangoFechas() detecta un fin anterior al inicio')]
    public function testRangoFechasDetectaOrdenInvertido(): void {
        $v = new Validador([]);
        $v->rangoFechas('2026-05-01', '2026-01-01');
        $this->assertTrue($v->hayErrores());
    }

    #[TestDox('rangoFechas() admite fechas iguales y nulos')]
    public function testRangoFechasAdmiteIgualesYNulos(): void {
        $v = new Validador([]);
        $v->rangoFechas('2026-05-01', '2026-05-01');
        $v->rangoFechas(null, '2026-01-01');
        $v->rangoFechas('2026-01-01', null);
        $this->assertFalse($v->hayErrores());
    }

    // =================================================================
    // DECIMAL Y CORREO
    // =================================================================

    #[TestDox('decimal() admite la coma decimal del español')]
    public function testDecimalAdmiteComa(): void {
        $v = new Validador(['d' => '85,5']);
        $this->assertSame(85.5, $v->decimal('d', 'D', 0, 100));
        $this->assertFalse($v->hayErrores());
    }

    #[TestDox('decimal() aplica el rango')]
    public function testDecimalAplicaRango(): void {
        foreach (['-1', '101'] as $fuera) {
            $v = new Validador(['d' => $fuera]);
            $v->decimal('d', 'Porcentaje', 0, 100);
            $this->assertTrue($v->hayErrores(), "aceptó $fuera");
        }
    }

    #[TestDox('email() normaliza a minúsculas y recorta')]
    public function testEmailNormaliza(): void {
        $v = new Validador(['m' => '  Coordinador@SENA.Edu.CO  ']);
        $this->assertSame('coordinador@sena.edu.co', $v->email('m'));
    }

    #[DataProvider('correosInvalidos')]
    #[TestDox('email() rechaza formatos inválidos')]
    public function testEmailRechazaInvalidos(string $entrada): void {
        $v = new Validador(['m' => $entrada]);
        $this->assertSame('', $v->email('m'));
        $this->assertTrue($v->hayErrores());
    }

    public static function correosInvalidos(): array {
        return [
            'sin arroba'     => ['sinarroba.com'],
            'sin dominio'    => ['usuario@'],
            'sin usuario'    => ['@dominio.com'],
            'dos arrobas'    => ['a@b@c.com'],
            'con espacios'   => ['usu ario@sena.edu.co'],
            'inyección'      => ["a@b.com' OR '1'='1"],
            'salto de línea' => ["a@b.com\nBcc: otro@x.com"],
            // 160 caracteres: por encima del tope de 150 que impone el
            // validador, y del tamaño de la columna.
            'demasiado largo'=> [str_repeat('a', 140) . '@sena.edu.co'],
        ];
    }

    /**
     * Un salto de línea en un correo es el vector clásico de inyección de
     * cabeceras SMTP: permitiría añadir un Bcc al correo de recuperación.
     */
    #[TestDox('email() rechaza saltos de línea (inyección de cabeceras SMTP)')]
    public function testEmailRechazaInyeccionDeCabeceras(): void {
        $v = new Validador(['m' => "victima@sena.edu.co\r\nBcc: atacante@externo.com"]);
        $this->assertSame('', $v->email('m'));
        $this->assertTrue($v->hayErrores());
    }

    // =================================================================
    // BOOLEANO Y LISTAS
    // =================================================================

    #[TestDox('booleano() interpreta la presencia de la casilla')]
    public function testBooleano(): void {
        $this->assertSame(1, (new Validador(['c' => 'on']))->booleano('c'));
        $this->assertSame(1, (new Validador(['c' => '1']))->booleano('c'));
        $this->assertSame(0, (new Validador([]))->booleano('c'));
        $this->assertSame(0, (new Validador(['c' => '0']))->booleano('c'));
        $this->assertSame(0, (new Validador(['c' => '']))->booleano('c'));
    }

    #[TestDox('listaIds() filtra basura y duplicados')]
    public function testListaIdsFiltra(): void {
        $v = new Validador(['ids' => ['3', '1', 'abc', '-5', '0', '1', '', '2']]);
        $this->assertSame([3, 1, 2], $v->listaIds('ids', 'IDs'));
    }

    #[TestDox('listaIds() rechaza una lista desmesurada')]
    public function testListaIdsRechazaListaEnorme(): void {
        $v = new Validador(['ids' => range(1, 600)]);
        $this->assertSame([], $v->listaIds('ids', 'IDs', 500));
        $this->assertTrue($v->hayErrores());
    }

    #[TestDox('listaIds() rechaza un escalar donde espera lista')]
    public function testListaIdsRechazaEscalar(): void {
        $v = new Validador(['ids' => '1']);
        $this->assertSame([], $v->listaIds('ids', 'IDs'));
        $this->assertTrue($v->hayErrores());
    }

    // =================================================================
    // MANIPULACIÓN DE LA PETICIÓN
    // =================================================================

    /**
     * `?id[]=1` convierte `$_GET['id']` en array. Un `(int)` sobre un array
     * devuelve 1 con un aviso, de modo que la petición seguiría adelante
     * con un id que el usuario no envió.
     */
    #[TestDox('un array donde se espera un escalar se rechaza, no se castea')]
    public function testArrayDondeSeEsperaEscalar(): void {
        $v = new Validador(['id' => ['1', '2'], 'e' => ['activo'], 'f' => ['2026-01-01'], 't' => ['x']]);

        $this->assertSame(0, $v->entero('id', 'ID'));
        $this->assertSame('activo', $v->enum('e', 'Estado', ['activo'], 'activo'));
        $this->assertNull($v->fecha('f', 'Fecha', false));
        $this->assertSame('', $v->texto('t', 'T', 0, 10, false));
    }

    // =================================================================
    // ACUMULACIÓN DE ERRORES
    // =================================================================

    #[TestDox('acumula todos los errores en vez de parar en el primero')]
    public function testAcumulaErrores(): void {
        $v = new Validador(['nombre' => '', 'estado' => 'x', 'fecha' => 'y', 'n' => 'abc']);
        $v->texto('nombre', 'El nombre', 3, 50);
        $v->enum('estado', 'El estado', ['activo']);
        $v->fecha('fecha', 'La fecha');
        $v->entero('n', 'El número');

        $this->assertCount(4, $v->errores());
    }

    #[TestDox('no repite dos veces el mismo mensaje')]
    public function testNoDuplicaMensajes(): void {
        $v = new Validador(['a' => '', 'b' => '']);
        $v->texto('a', 'Campo', 1, 10);
        $v->texto('b', 'Campo', 1, 10);
        $this->assertCount(1, $v->errores());
    }

    #[TestDox('agregarError() permite sumar reglas de negocio')]
    public function testAgregarError(): void {
        $v = new Validador([]);
        $v->agregarError('Ese número de ficha ya existe.');
        $this->assertSame('Ese número de ficha ya existe.', $v->primerError());
    }

    #[TestDox('primerError() devuelve cadena vacía si no hay errores')]
    public function testPrimerErrorSinErrores(): void {
        $this->assertSame('', (new Validador([]))->primerError());
    }

    #[TestDox('sin argumentos lee de $_POST')]
    public function testLeeDePostPorDefecto(): void {
        $_POST = ['n' => '5'];
        $this->assertSame(5, (new Validador())->entero('n', 'N'));
    }

    /**
     * El color del avatar termina en un atributo style: sin esta regla,
     * «red;background:url(...)» inyectaba CSS en la página de quien lo viera.
     */
    #[DataProvider('coloresInvalidos')]
    #[TestDox('colorHex() solo acepta #RRGGBB: nada de CSS arbitrario')]
    public function testColorHexRechazaCss(string $valor): void {
        $v = new Validador(['c' => $valor]);
        $this->assertSame('#39A900', $v->colorHex('c', 'El color', '#39A900'));
        $this->assertNotSame([], $v->errores());
    }

    public static function coloresInvalidos(): array {
        return [
            'declaración extra' => ['#39A900;background:url(//x.y/a.png)'],
            'nombre de color'   => ['red'],
            'corto'             => ['#fff'],
            'no hexadecimal'    => ['#12345G'],
            'comillas'          => ['#39A900" onmouseover="x'],
            'expresión'         => ['expression(alert(1))'],
        ];
    }

    #[TestDox('colorHex() normaliza a mayúsculas un color válido')]
    public function testColorHexValido(): void {
        $v = new Validador(['c' => ' #a1b2c3 ']);
        $this->assertSame('#A1B2C3', $v->colorHex('c', 'El color', '#000000'));
        $this->assertSame([], $v->errores());
    }
}
