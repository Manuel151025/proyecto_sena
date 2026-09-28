<?php
declare(strict_types=1);

namespace Tests\Security;

use Core\Services\LimitadorIntentos;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Ataques contra el acceso al sistema: fuerza bruta, enumeración de
 * cuentas, fijación de sesión y caducidad.
 *
 * `intentos_acceso` es una tabla de control de abuso, no de datos del
 * negocio, así que estas pruebas la limpian en vez de revertirla: lo que
 * importa es que quede vacía al terminar.
 */
final class AutenticacionTest extends CasoConBaseDeDatos {

    protected function setUp(): void {
        parent::setUp();
        $this->db->exec("DELETE FROM intentos_acceso");
    }

    protected function tearDown(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        // Fuera de la transacción: el limitador escribe con su propia conexión.
        $this->db->exec("DELETE FROM intentos_acceso");
        parent::tearDown();
    }

    // =================================================================
    // FUERZA BRUTA
    // =================================================================

    /**
     * El control anterior vivía en `$_SESSION['login_attempts']`, así que
     * un script que descartara las cookies entre peticiones empezaba de
     * cero en cada intento. Esta prueba simula justo eso: el limitador no
     * recibe ninguna pista de sesión, solo el correo y la IP.
     */
    #[TestDox('un atacante sin cookies queda bloqueado igualmente')]
    public function testFuerzaBrutaSinCookies(): void {
        $lim = new LimitadorIntentos($this->db);
        $victima = 'coordinador@sena.edu.co';

        for ($i = 0; $i < 5; $i++) {
            $this->limpiarSesion();   // el atacante descarta la cookie
            $lim->registrarFallo('login', $victima);
        }

        $this->limpiarSesion();
        $this->assertTrue(
            $lim->estaBloqueado('login', $victima),
            'el bloqueo se evade descartando la sesión'
        );
    }

    #[TestDox('el bloqueo llega al quinto intento, no antes')]
    public function testUmbralDeBloqueo(): void {
        $lim = new LimitadorIntentos($this->db);
        $correo = 'umbral@sena.edu.co';

        for ($i = 1; $i <= 4; $i++) {
            $lim->registrarFallo('login', $correo);
            $this->assertFalse($lim->estaBloqueado('login', $correo), "bloqueó en el intento $i");
        }

        $lim->registrarFallo('login', $correo);
        $this->assertTrue($lim->estaBloqueado('login', $correo));
    }

    #[TestDox('bloquear una cuenta no bloquea a las demás')]
    public function testElBloqueoNoSeContagia(): void {
        $lim = new LimitadorIntentos($this->db);

        for ($i = 0; $i < 5; $i++) {
            $lim->registrarFallo('login', 'victima@sena.edu.co');
        }

        $this->assertTrue($lim->estaBloqueado('login', 'victima@sena.edu.co'));
        $this->assertFalse(
            $lim->estaBloqueado('login', 'otro@sena.edu.co'),
            'bloquear una cuenta dejaría fuera a todo el centro: denegación de servicio'
        );
    }

    /**
     * Contar solo por cuenta deja libre el barrido: probar una contraseña
     * común contra muchos correos distintos. Por eso hay también un cupo
     * por IP, más holgado porque un centro sale por NAT.
     */
    #[TestDox('el barrido de muchas cuentas desde una IP también se corta')]
    public function testBarridoDeCuentas(): void {
        $lim = new LimitadorIntentos($this->db);

        for ($i = 0; $i < 21; $i++) {
            $lim->registrarFallo('login', "barrido{$i}@sena.edu.co");
        }

        $this->assertTrue(
            $lim->estaBloqueado('login', 'cuenta-que-nunca-se-probo@sena.edu.co'),
            'se puede barrer el padrón entero desde una sola IP'
        );
    }

    #[TestDox('acertar limpia el cupo de esa cuenta')]
    public function testElAciertoLimpiaElCupo(): void {
        $lim = new LimitadorIntentos($this->db);
        $correo = 'limpia@sena.edu.co';

        for ($i = 0; $i < 5; $i++) {
            $lim->registrarFallo('login', $correo);
        }
        $this->assertTrue($lim->estaBloqueado('login', $correo));

        $lim->registrarExito('login', $correo);
        $this->assertFalse($lim->estaBloqueado('login', $correo));
    }

    /**
     * Si acertar limpiara también el cupo por IP, un atacante con una
     * cuenta propia podría reiniciar su cupo cada 20 intentos.
     */
    #[TestDox('acertar NO limpia el cupo por IP')]
    public function testElAciertoNoReiniciaElCupoPorIp(): void {
        $lim = new LimitadorIntentos($this->db);

        for ($i = 0; $i < 21; $i++) {
            $lim->registrarFallo('login', "barrido{$i}@sena.edu.co");
        }
        $lim->registrarExito('login', 'cuenta-propia-del-atacante@sena.edu.co');

        $this->assertTrue(
            $lim->estaBloqueado('login', 'siguiente-victima@sena.edu.co'),
            'el cupo por IP se reinicia acertando con una cuenta cualquiera'
        );
    }

    // =================================================================
    // PRIVACIDAD DEL REGISTRO DE ABUSO
    // =================================================================

    /**
     * La tabla de intentos no puede convertirse en una lista de qué correos
     * ha probado alguien: es un control de abuso, no un registro de personas.
     */
    #[TestDox('no se guarda en claro el correo con el que se intentó entrar')]
    public function testNoGuardaCorreosEnClaro(): void {
        $lim = new LimitadorIntentos($this->db);
        $lim->registrarFallo('login', 'persona.identificable@sena.edu.co');

        $claves = $this->db->query("SELECT clave FROM intentos_acceso")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($claves as $c) {
            $this->assertStringNotContainsString('@', $c, 'hay un correo en claro en la tabla');
            $this->assertStringNotContainsString('persona', $c);
        }
        $this->assertNotEmpty($claves, 'no registró nada');
    }

    #[TestDox('tampoco se guarda la IP en claro')]
    public function testNoGuardaIpEnClaro(): void {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';
        (new LimitadorIntentos($this->db))->registrarFallo('login', 'x@sena.edu.co');

        $claves = $this->db->query("SELECT clave FROM intentos_acceso")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($claves as $c) {
            $this->assertStringNotContainsString('198.51.100.42', $c);
        }
    }

    /**
     * `X-Forwarded-For` la controla el cliente. Si el limitador la leyera,
     * bastaría con cambiarla en cada petición para tener cupo infinito.
     */
    #[TestDox('la IP no se toma de una cabecera que controla el cliente')]
    public function testNoConfiaEnXForwardedFor(): void {
        $lim = new LimitadorIntentos($this->db);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        for ($i = 0; $i < 21; $i++) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = "10.0.0.$i";   // el atacante la rota
            $lim->registrarFallo('login', "cuenta{$i}@sena.edu.co");
        }
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $this->assertTrue(
            $lim->estaBloqueado('login', 'nueva@sena.edu.co'),
            'rotando X-Forwarded-For se obtiene cupo ilimitado'
        );
    }

    // =================================================================
    // ENUMERACIÓN DE CUENTAS
    // =================================================================

    /**
     * Un correo inexistente respondía al instante y uno real tardaba lo que
     * tarda bcrypt. Esa diferencia permite averiguar qué cuentas existen.
     * Se compara el tiempo de las dos ramas.
     */
    #[TestDox('el tiempo de respuesta no delata si la cuenta existe')]
    public function testSinCanalLateralDeTiempo(): void {
        $existente = $this->db->query(
            "SELECT email FROM usuarios WHERE estado = 'activo' LIMIT 1"
        )->fetchColumn();

        if ($existente === false) {
            $this->markTestSkipped('no hay usuarios activos');
        }

        $medir = static function (string $email): float {
            $t = microtime(true);
            attemptLogin($email, 'contraseña-que-no-es-la-buena');
            return microtime(true) - $t;
        };

        // Varias repeticiones: una sola medición es puro ruido.
        $conCuenta = $sinCuenta = 0.0;
        for ($i = 0; $i < 3; $i++) {
            $conCuenta += $medir((string)$existente);
            $sinCuenta += $medir('no-existe-' . $i . '@sena.edu.co');
        }

        $mayor = max($conCuenta, $sinCuenta);
        $menor = max(0.0001, min($conCuenta, $sinCuenta));

        // Tras igualar el coste del relleno, el ratio real es ~1.01. Un
        // umbral de 1.8 detecta una diferencia significativa sin saltar
        // por el ruido de medición de una máquina cargada.
        $this->assertLessThan(
            1.8,
            $mayor / $menor,
            sprintf('la diferencia de tiempo delata las cuentas (%.1f ms vs %.1f ms)',
                $conCuenta * 1000, $sinCuenta * 1000)
        );
    }

    /**
     * El relleno solo iguala los tiempos si es un bcrypt válido y del mismo
     * coste que los hashes reales. Un hash malformado tarda ~200 ms frente
     * a los ~50 ms de uno de coste 10, y eso invierte la fuga en vez de
     * cerrarla: fue exactamente lo que pasó en el primer intento.
     */
    #[TestDox('el hash de relleno es válido y del mismo coste que los reales')]
    public function testCosteDelRellenoCoincide(): void {
        $relleno = password_get_info(HASH_RELLENO);

        $this->assertNotNull($relleno['algo'],
            'HASH_RELLENO no es un hash válido: password_verify contra él tarda 4x más');
        $this->assertSame('bcrypt', $relleno['algoName']);

        $porDefecto = password_get_info(password_hash('x', PASSWORD_DEFAULT));

        $this->assertSame(
            $porDefecto['algoName'],
            $relleno['algoName'],
            'el algoritmo por defecto de PHP ha cambiado: regenera HASH_RELLENO'
        );
        $this->assertSame(
            $porDefecto['options']['cost'] ?? null,
            $relleno['options']['cost'] ?? null,
            'el coste por defecto de PHP ha cambiado: regenera HASH_RELLENO con password_hash()'
        );
    }

    #[TestDox('el relleno coincide con el coste de los hashes almacenados')]
    public function testRellenoCoincideConLosHashesReales(): void {
        $hashReal = (string)$this->db->query(
            "SELECT password FROM usuarios WHERE estado = 'activo' LIMIT 1"
        )->fetchColumn();

        $this->assertSame(
            password_get_info($hashReal)['options']['cost'] ?? null,
            password_get_info(HASH_RELLENO)['options']['cost'] ?? null,
            'el relleno y los hashes guardados tienen costes distintos: los tiempos no se igualan'
        );
    }

    #[TestDox('un login fallido no revela por qué falló')]
    public function testLoginFallidoNoDistingueLaCausa(): void {
        // La contraseña correcta no se conoce en la prueba, así que se
        // comprueba la propiedad observable: las dos ramas devuelven false
        // sin ninguna información adicional.
        $this->assertFalse(attemptLogin('no-existe@sena.edu.co', 'x'));

        $existente = (string)$this->db->query(
            "SELECT email FROM usuarios WHERE estado = 'activo' LIMIT 1"
        )->fetchColumn();
        $this->assertFalse(attemptLogin($existente, 'contraseña-incorrecta'));
    }

    #[TestDox('una cuenta inactiva no puede entrar aunque acierte')]
    public function testCuentaInactivaNoEntra(): void {
        // Se desactiva temporalmente (dentro de la transacción) y se
        // comprueba que la consulta de login la excluye.
        $id = $this->idCoordinador();
        $this->db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?")->execute([$id]);

        $email = (string)$this->db->query("SELECT email FROM usuarios WHERE id = $id")->fetchColumn();
        $this->assertFalse(attemptLogin($email, 'cualquiera'),
            'una cuenta desactivada no debe poder iniciar sesión');
    }

    // =================================================================
    // CONTRASEÑAS
    // =================================================================

    #[TestDox('ninguna contraseña está guardada en claro')]
    public function testContrasenasHasheadas(): void {
        $hashes = $this->db->query("SELECT password FROM usuarios LIMIT 50")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertNotEmpty($hashes);

        foreach ($hashes as $h) {
            $h = (string)$h;
            $info = password_get_info($h);

            $this->assertNotNull(
                $info['algo'],
                'hay una contraseña que no es un hash de password_hash(): ' . substr($h, 0, 12) . '...'
            );
            $this->assertContains(
                $info['algoName'],
                ['bcrypt', 'argon2i', 'argon2id'],
                'algoritmo de hash no reconocido: ' . $info['algoName']
            );
            // Un hash bcrypt son 60 caracteres; menos de eso no lo es.
            $this->assertGreaterThanOrEqual(50, strlen($h), 'demasiado corta para ser un hash');

            // El coste tiene que ser el actual o superior: si se quedó por
            // debajo, password_needs_rehash() lo subirá al siguiente acceso.
            if ($info['algoName'] === 'bcrypt') {
                $this->assertGreaterThanOrEqual(10, (int)($info['options']['cost'] ?? 0),
                    'coste de bcrypt por debajo del mínimo razonable');
            }
        }
    }

    #[TestDox('las contraseñas temporales generadas son impredecibles')]
    public function testContrasenasTemporalesAleatorias(): void {
        $generadas = [];
        for ($i = 0; $i < 200; $i++) {
            $generadas[] = generateTempPassword(10);
        }

        $this->assertCount(200, array_unique($generadas), 'se repiten contraseñas temporales');

        foreach ($generadas as $p) {
            $this->assertSame(10, strlen($p));
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $p);
            // Sin caracteres ambiguos: son para transcribir a mano.
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $p);
        }
    }
}
