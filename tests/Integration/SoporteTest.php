<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Support\Configuracion;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use Core\Support\Transaccion;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\CasoConBaseDeDatos;
use Throwable;

/**
 * Tres piezas de soporte de las que dependen todos los servicios que
 * escriben:
 *
 *  - Transaccion: si confirmara dentro de la transacción del llamador, una
 *    importación o una calificación quedaría guardada a medias aunque
 *    después fallara; si no revirtiera la suya, dejaría filas huérfanas.
 *  - ErroresBD: decide por el código del motor, no por el texto, qué
 *    violación de integridad se le explica al usuario y cuál sigue siendo
 *    un error interno.
 *  - Configuracion: los datos institucionales de los reportes, con su
 *    respaldo cuando la base no los tiene.
 *
 * Las pruebas de Transaccion necesitan una conexión SIN la transacción
 * envolvente: usan una conexión aparte con una tabla temporal, que no deja
 * rastro aunque la clase fallara y confirmara de más.
 */
final class SoporteTest extends CasoConBaseDeDatos {
    private ?PDO $aparte = null;

    protected function setUp(): void {
        parent::setUp();
        Configuracion::olvidar();
    }

    protected function tearDown(): void {
        // La tabla temporal desaparece al cerrar su conexión.
        $this->aparte = null;
        // La caché es estática: sin esto, la prueba siguiente leería los
        // valores de esta transacción, ya revertida.
        Configuracion::olvidar();
        parent::tearDown();
    }

    private function conexionAparte(): PDO {
        $this->aparte = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->aparte->exec('CREATE TEMPORARY TABLE prueba_transaccion (id INT PRIMARY KEY, nota VARCHAR(80) NOT NULL) ENGINE=InnoDB');
        return $this->aparte;
    }

    /** @return list<int> */
    private function filas(PDO $pdo): array {
        return array_map('intval', $pdo->query('SELECT id FROM prueba_transaccion ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    // =================================================================
    // TRANSACCION
    // =================================================================

    #[TestDox('sin transacción abierta abre la suya, la confirma y devuelve el resultado de la operación')]
    public function testConfirmaLaSuya(): void {
        $pdo = $this->conexionAparte();
        $dentro = null;

        $r = Transaccion::ejecutar($pdo, function () use ($pdo, &$dentro): string {
            $dentro = $pdo->inTransaction();
            $pdo->exec("INSERT INTO prueba_transaccion VALUES (1, 'matrícula de VALENTINA ORTIZ CASTRO')");
            return 'matriculada';
        });

        $this->assertSame('matriculada', $r);
        $this->assertTrue($dentro, 'la operación no corrió dentro de una transacción');
        $this->assertFalse($pdo->inTransaction(), 'dejó la transacción abierta');
        $this->assertSame([1], $this->filas($pdo), 'no confirmó lo escrito');
    }

    /**
     * Es el caso de confirmar una importación: el importador abre su
     * transacción y los modelos que llama usan Transaccion por su cuenta.
     */
    #[TestDox('dentro de la transacción del llamador participa sin confirmarla')]
    public function testParticipaSinConfirmar(): void {
        $pdo = $this->conexionAparte();
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO prueba_transaccion VALUES (1, 'competencia 220501098')");

        Transaccion::ejecutar($pdo, static fn() => $pdo->exec("INSERT INTO prueba_transaccion VALUES (2, 'RAP 220501098-01')"));

        $this->assertTrue($pdo->inTransaction(), 'cerró la transacción del llamador');
        $pdo->rollBack();
        $this->assertSame([], $this->filas($pdo), 'confirmó por su cuenta: el llamador ya no puede revertir');
    }

    #[TestDox('ante una excepción revierte la suya y relanza la misma excepción')]
    public function testRevierteYRelanza(): void {
        $pdo = $this->conexionAparte();
        $fallo = new RuntimeException('Falló la sincronización de evaluaciones');

        try {
            Transaccion::ejecutar($pdo, static function () use ($pdo, $fallo): void {
                $pdo->exec("INSERT INTO prueba_transaccion VALUES (1, 'cuenta de aprendiz creada a medias')");
                throw $fallo;
            });
            $this->fail('la excepción no salió de Transaccion::ejecutar()');
        } catch (RuntimeException $e) {
            $this->assertSame($fallo, $e, 'no relanzó la excepción original');
        }

        $this->assertFalse($pdo->inTransaction());
        $this->assertSame([], $this->filas($pdo), 'quedó escrita la mitad de la operación');
    }

    /**
     * Si la operación falla dentro de la transacción de otro, revertir es
     * decisión del dueño: un rollBack aquí cerraría su transacción y el
     * resto de su trabajo seguiría en autocommit.
     */
    #[TestDox('ante una excepción dentro de la del llamador la relanza sin revertirle la transacción')]
    public function testFalloDentroDeLaDelLlamador(): void {
        $pdo = $this->conexionAparte();
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO prueba_transaccion VALUES (1, 'trabajo previo del llamador')");

        try {
            Transaccion::ejecutar($pdo, static fn() => throw new RuntimeException('Falló a mitad'));
            $this->fail('la excepción no salió de Transaccion::ejecutar()');
        } catch (RuntimeException $e) {
            $this->assertSame('Falló a mitad', $e->getMessage());
        }

        $this->assertTrue($pdo->inTransaction(), 'revirtió y cerró la transacción del llamador');
        $this->assertSame([1], $this->filas($pdo));
        $pdo->rollBack();
    }

    // =================================================================
    // ERRORES DE LA BASE
    // =================================================================

    /** Sentencia que viola de verdad una restricción de la base de demostración. */
    public static function violaciones(): array {
        return [
            'correo repetido (UNIQUE)' => [
                "INSERT INTO usuarios (nombre, email, password, rol)
                 SELECT 'OTRA PERSONA', email, 'x', 'instructor' FROM usuarios ORDER BY id LIMIT 1",
                ErroresBD::DUPLICADO,
            ],
            'ficha de un programa que no existe' => [
                "INSERT INTO fichas (numero_ficha, programa_id, instructor_id)
                 SELECT '99999901', (SELECT MAX(id) + 1000 FROM programas), (SELECT MIN(id) FROM usuarios)",
                ErroresBD::REFERENCIA,
            ],
            'borrar un programa con fichas' => [
                'DELETE FROM programas WHERE id = (SELECT programa_id FROM fichas ORDER BY id LIMIT 1)',
                ErroresBD::EN_USO,
            ],
        ];
    }

    /** Provoca la violación dentro de la transacción de la prueba y devuelve la PDOException real. */
    private function violar(string $sql): PDOException {
        if ($this->contar('fichas') === 0) {
            $this->markTestSkipped('Hacen falta fichas y usuarios en la base.');
        }
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            return $e;
        }
        $this->fail("La sentencia no violó ninguna restricción: $sql");
    }

    #[DataProvider('violaciones')]
    #[TestDox('codigo() lee el código del motor de una violación real: $_dataName')]
    public function testCodigoDeUnaViolacionReal(string $sql, int $esperado): void {
        $this->assertSame($esperado, ErroresBD::codigo($this->violar($sql)));
    }

    #[TestDox('relanzar() convierte un duplicado real en el mensaje de negocio y conserva la causa')]
    public function testRelanzarTraduceElDuplicado(): void {
        $original = $this->violar(self::violaciones()['correo repetido (UNIQUE)'][0]);

        try {
            ErroresBD::relanzar($original, [
                ErroresBD::DUPLICADO => 'El correo o el documento ya están registrados.',
                ErroresBD::EN_USO    => 'No se puede eliminar: tiene registros asociados.',
            ]);
        } catch (ErrorDeNegocio $e) {
            $this->assertSame('El correo o el documento ya están registrados.', $e->getMessage());
            $this->assertSame($original, $e->getPrevious(), 'se perdió la excepción original (y con ella el detalle para el log)');
            return;
        }
    }

    #[TestDox('relanzar() deja pasar intacta una violación que no sabe explicar')]
    public function testRelanzarDejaPasarLoDesconocido(): void {
        $original = $this->violar(self::violaciones()['correo repetido (UNIQUE)'][0]);

        try {
            ErroresBD::relanzar($original, [ErroresBD::EN_USO => 'No se puede eliminar: la ficha tiene registros asociados.']);
        } catch (Throwable $e) {
            $this->assertSame($original, $e);
            return;
        }
    }

    /**
     * Se decide por el código del motor, no por el texto: una excepción que
     * no viene de la base nunca se traduce, aunque su mensaje o su código
     * se parezcan a los de un duplicado.
     */
    #[TestDox('lo que no viene de la base tiene código 0 y relanzar() no lo traduce')]
    public function testErroresAjenosALaBase(): void {
        $this->assertSame(0, ErroresBD::codigo(new RuntimeException("Duplicate entry 'x' for key 'email'", 1062)));
        $this->assertSame(0, ErroresBD::codigo(new PDOException('PDOException sin errorInfo')));

        $ajeno = new RuntimeException("Duplicate entry 'x' for key 'email'", 1062);
        try {
            ErroresBD::relanzar($ajeno, [ErroresBD::DUPLICADO => 'Ya existe.', 0 => 'Ya existe.']);
        } catch (Throwable $e) {
            $this->assertSame($ajeno, $e);
            return;
        }
    }

    // =================================================================
    // CONFIGURACIÓN
    // =================================================================

    private function guardarConfiguracion(string $clave, ?string $valor): void {
        if ($valor === null) {
            $this->db->prepare('DELETE FROM configuraciones_sistema WHERE clave = ?')->execute([$clave]);
            return;
        }
        $this->db->prepare('INSERT INTO configuraciones_sistema (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
                 ->execute([$clave, $valor]);
    }

    #[TestDox('valor() devuelve lo guardado en la base, sin los espacios de alrededor')]
    public function testValorGuardado(): void {
        $this->guardarConfiguracion('regional', '  Centro Agroempresarial y Desarrollo Pecuario · Regional Huila  ');
        $this->assertSame('Centro Agroempresarial y Desarrollo Pecuario · Regional Huila', Configuracion::valor('regional'));
    }

    public static function sinValor(): array {
        return [
            'la fila no existe'  => [null],
            'vacía'              => [''],
            'solo espacios'      => ['   '],
        ];
    }

    #[DataProvider('sinValor')]
    #[TestDox('valor() cae al valor por defecto si la base no lo tiene: $_dataName')]
    public function testValorPorDefecto(?string $guardado): void {
        foreach (array_keys(Configuracion::CLAVES) as $clave) {
            $this->guardarConfiguracion($clave, $guardado);
        }
        foreach (Configuracion::CLAVES as $clave => [, $porDefecto]) {
            $this->assertSame($porDefecto, Configuracion::valor($clave), $clave);
        }
    }

    #[TestDox('valor() de una clave que no es de la configuración devuelve cadena vacía')]
    public function testClaveDesconocida(): void {
        $this->guardarConfiguracion('porcentaje_aprobacion', null);
        $this->assertSame('', Configuracion::valor('porcentaje_aprobacion'));
    }

    /**
     * La pantalla de configuración guarda y vuelve a pintar en la misma
     * petición: sin olvidar() mostraría el valor de antes de guardar.
     */
    #[TestDox('valor() lee la base una vez; olvidar() obliga a leer lo recién guardado')]
    public function testOlvidarReleeLoGuardado(): void {
        $this->guardarConfiguracion('system_title', 'Seguimiento SENA');
        $this->assertSame('Seguimiento SENA', Configuracion::valor('system_title'));

        $this->guardarConfiguracion('system_title', 'Seguimiento de Proyectos Formativos · CTA');
        $this->assertSame('Seguimiento SENA', Configuracion::valor('system_title'), 'debía servirse de la caché');

        Configuracion::olvidar();
        $this->assertSame('Seguimiento de Proyectos Formativos · CTA', Configuracion::valor('system_title'));
    }
}
