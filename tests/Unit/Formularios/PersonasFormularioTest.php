<?php
declare(strict_types=1);

namespace Tests\Unit\Formularios;

use Core\Formularios\MatriculaFormulario;
use Core\Formularios\UsuarioFormulario;
use Core\Support\Validador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Formularios de personas: la cuenta de usuario y la matrícula del aprendiz.
 * Son la puerta de entrada de los datos personales del sistema; lo que dejen
 * pasar llega a la base y a todos los listados.
 */
final class PersonasFormularioTest extends CasoDePrueba {

    /** @return array{0: array, 1: list<string>} */
    private static function usuario(array $datos, bool $conEstado = false): array {
        $v = new Validador($datos);
        return [UsuarioFormulario::validar($v, $conEstado), $v->errores()];
    }

    /** @return array{0: array, 1: list<string>} */
    private static function matricula(array $datos, bool $conEstado = false): array {
        $v = new Validador($datos);
        return [MatriculaFormulario::validar($v, $conEstado), $v->errores()];
    }

    private static function usuarioValido(array $cambios = []): array {
        return array_merge(['nombre' => "  maría josé o'neill  ", 'email' => 'mjoneill@sena.edu.co', 'rol' => 'instructor',
                            'avatar_color' => '#3b82f6'], $cambios);
    }

    private static function matriculaValida(array $cambios = []): array {
        return array_merge(['nombre' => 'laura camila vargas ruiz', 'email' => 'lcvargas@soy.sena.edu.co', 'tipo_documento' => 'CC',
                            'numero_documento' => '1117500123', 'ficha_id' => '3', 'genero' => 'F',
                            'fecha_nacimiento' => date('Y-m-d', strtotime('-19 years')), 'telefono' => '310 123 4567',
                            'ciudad' => 'Florencia'], $cambios);
    }

    // -----------------------------------------------------------------
    // USUARIO
    // -----------------------------------------------------------------

    #[TestDox('usuario válido: nombre en mayúsculas sin espacios sobrantes y color normalizado')]
    public function testUsuarioValido(): void {
        [$d, $errores] = self::usuario(self::usuarioValido());
        $this->assertSame([], $errores);
        $this->assertSame("MARÍA JOSÉ O'NEILL", $d['nombre']);
        $this->assertSame('mjoneill@sena.edu.co', $d['email']);
        $this->assertSame('instructor', $d['rol']);
        $this->assertSame('#3B82F6', $d['avatar_color']);
    }

    #[TestDox('sin color elegido se usa el primero de la paleta institucional')]
    public function testColorPorDefecto(): void {
        [$d] = self::usuario(self::usuarioValido(['avatar_color' => '']));
        $this->assertSame(UsuarioFormulario::COLORES[0], $d['avatar_color']);
    }

    #[DataProvider('usuariosInvalidos')]
    #[TestDox('rechaza el usuario: $_dataName')]
    public function testUsuarioInvalido(array $cambios, string $mencion): void {
        [, $errores] = self::usuario(self::usuarioValido($cambios));
        $this->assertNotSame([], $errores);
        $this->assertStringContainsStringIgnoringCase($mencion, implode(' ', $errores));
    }

    public static function usuariosInvalidos(): array {
        return [
            'sin nombre'                       => [['nombre' => ''], 'nombre'],
            'nombre de dos letras'             => [['nombre' => 'Al'], 'nombre'],
            'nombre con números'               => [['nombre' => 'Ana 2'], 'nombre'],
            'nombre de 151 caracteres'         => [['nombre' => str_repeat('a', 151)], 'nombre'],
            'correo sin arroba'                => [['email' => 'mjoneill.sena.edu.co'], 'correo'],
            'correo de 121 caracteres'         => [['email' => str_repeat('a', 110) . '@sena.edu.co'], 'correo'],
            'rol inventado'                    => [['rol' => 'superusuario'], 'rol'],
            'rol vacío'                        => [['rol' => ''], 'rol'],
            'color con CSS'                    => [['avatar_color' => '#39A900;background:red'], 'color'],
        ];
    }

    #[TestDox('las etiquetas HTML no llegan a la base: se retiran del texto')]
    public function testRetiraEtiquetas(): void {
        [$d, $errores] = self::usuario(self::usuarioValido(['nombre' => '<b>Ana</b> <script>Ruiz</script>']));
        $this->assertSame([], $errores);
        $this->assertSame('ANA RUIZ', $d['nombre']);
        [$m] = self::matricula(self::matriculaValida(['ciudad' => '<i>Florencia</i>']));
        $this->assertSame('Florencia', $m['ciudad']);
    }

    #[TestDox('al editar exige un estado de la lista blanca')]
    public function testEstadoAlEditar(): void {
        [$d, $errores] = self::usuario(self::usuarioValido(['estado' => 'activo']), true);
        $this->assertSame([], $errores);
        $this->assertSame('activo', $d['estado']);
        [, $errores] = self::usuario(self::usuarioValido(['estado' => 'eliminado']), true);
        $this->assertNotSame([], $errores);
    }

    // -----------------------------------------------------------------
    // MATRÍCULA
    // -----------------------------------------------------------------

    #[TestDox('matrícula válida: nombre en mayúsculas y datos opcionales tal como llegan')]
    public function testMatriculaValida(): void {
        [$d, $errores] = self::matricula(self::matriculaValida());
        $this->assertSame([], $errores);
        $this->assertSame('LAURA CAMILA VARGAS RUIZ', $d['nombre']);
        $this->assertSame('CC', $d['tipo_documento']);
        $this->assertSame('1117500123', $d['numero_documento']);
        $this->assertSame(3, $d['ficha_id']);
        $this->assertSame('Florencia', $d['ciudad']);
        $this->assertNull($d['instructor_seguimiento_id']);
    }

    #[TestDox('sin datos opcionales: género «O», sin fecha de nacimiento ni teléfono')]
    public function testMatriculaMinima(): void {
        [$d, $errores] = self::matricula(['nombre' => 'Pedro Pérez', 'email' => 'pperez@soy.sena.edu.co',
                                          'numero_documento' => '1117500999', 'ficha_id' => '1']);
        $this->assertSame([], $errores);
        $this->assertSame('CC', $d['tipo_documento']);
        $this->assertSame('O', $d['genero']);
        $this->assertNull($d['fecha_nacimiento']);
    }

    /**
     * Regresión: la fecha de nacimiento se validaba con la cota de las fechas
     * académicas (2000-2100) y rechazaba a todo aprendiz de más de 26 años.
     */
    #[TestDox('acepta aprendices nacidos antes del año 2000 si la edad es válida')]
    public function testNacidoAntesDel2000(): void {
        foreach (['1998-03-15', '1990-12-01', date('Y-m-d', strtotime('-45 years'))] as $fecha) {
            [$d, $errores] = self::matricula(self::matriculaValida(['fecha_nacimiento' => $fecha]));
            $this->assertSame([], $errores, "rechazó la fecha de nacimiento $fecha");
            $this->assertSame($fecha, $d['fecha_nacimiento']);
        }
    }

    #[DataProvider('edades')]
    #[TestDox('la edad debe estar entre 14 y 90 años: $_dataName')]
    public function testEdad(string $fecha, bool $valida): void {
        [, $errores] = self::matricula(self::matriculaValida(['fecha_nacimiento' => $fecha]));
        $valida ? $this->assertSame([], $errores) : $this->assertNotSame([], $errores);
    }

    public static function edades(): array {
        $hace = fn(string $intervalo) => date('Y-m-d', strtotime($intervalo));
        return [
            'cumple 14 hoy'             => [$hace('-14 years'), true],
            'le falta un día para 14'   => [$hace('-14 years +1 day'), false],
            '13 años'                   => [$hace('-13 years'), false],
            '90 años'                   => [$hace('-90 years'), true],
            '91 años'                   => [$hace('-91 years -1 day'), false],
            'nacido en el futuro'       => [$hace('+1 year'), false],
            'fecha imposible'           => ['2001-02-30', false],
            'formato día/mes/año'       => ['15/03/2004', false],
        ];
    }

    #[DataProvider('documentos')]
    #[TestDox('el documento según su tipo: $_dataName')]
    public function testDocumento(string $tipo, string $numero, bool $valido): void {
        [, $errores] = self::matricula(self::matriculaValida(['tipo_documento' => $tipo, 'numero_documento' => $numero]));
        $valido ? $this->assertSame([], $errores) : $this->assertNotSame([], $errores);
    }

    public static function documentos(): array {
        return [
            'CC numérica'                 => ['CC', '1117500123', true],
            'CC con letras'               => ['CC', '11175AB123', false],
            'TI numérica'                 => ['TI', '1006789012', true],
            'TI con letras'               => ['TI', 'AB1234567', false],
            'CE con letras'               => ['CE', 'E1234567', false],
            'pasaporte con letras'        => ['PA', 'AB1234567', true],
            'PEP con letras'              => ['PEP', 'PEP123456', true],
            'con puntos'                  => ['CC', '1.117.500.123', false],
            'con espacios'                => ['CC', '1117 500 123', false],
            'de cuatro dígitos'           => ['CC', '1234', false],
            'de 21 caracteres'            => ['PA', str_repeat('A', 21), false],
            'tipo inventado'              => ['NIT', '900123456', false],
        ];
    }

    #[DataProvider('contactosInvalidos')]
    #[TestDox('rechaza datos de contacto inválidos: $_dataName')]
    public function testContactoInvalido(array $cambios): void {
        [, $errores] = self::matricula(self::matriculaValida($cambios));
        $this->assertNotSame([], $errores);
    }

    public static function contactosInvalidos(): array {
        return [
            'teléfono con letras'      => [['telefono' => '310-ABC-4567']],
            'teléfono de seis dígitos' => [['telefono' => '123456']],
            'ciudad con símbolos'      => [['ciudad' => 'Florencia @!']],
            'género inventado'         => [['genero' => 'X']],
            'sin ficha'                => [['ficha_id' => '']],
            'ficha no numérica'        => [['ficha_id' => 'abc']],
            'sin correo'               => [['email' => '']],
        ];
    }

    #[TestDox('al editar, el estado de la matrícula sale de la lista blanca')]
    public function testEstadoDeMatricula(): void {
        [$d, $errores] = self::matricula(self::matriculaValida(['estado' => 'etapa_practica']), true);
        $this->assertSame([], $errores);
        $this->assertSame('etapa_practica', $d['estado']);
        [, $errores] = self::matricula(self::matriculaValida(['estado' => 'graduado']), true);
        $this->assertNotSame([], $errores);
    }
}
