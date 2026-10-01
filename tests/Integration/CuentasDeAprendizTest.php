<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Importacion\ImportadorMatriculas;
use Core\Importacion\ImportadorUsuarios;
use Core\Services\MatriculasService;
use Core\Services\UsuariosService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * El rol de aprendiz va unido a la matrícula.
 *
 * Antes, Usuarios creaba cuentas de aprendiz sin ficha que después no se
 * podían matricular (su correo "ya estaba registrado"), y cambiaba el rol
 * de cualquiera: un instructor con fichas a cargo podía pasar a aprendiz.
 */
final class CuentasDeAprendizTest extends CasoConBaseDeDatos {

    private const CORREO = 'sin.ficha.qa@example.com';

    private function coordinador(): Actor {
        return new Actor($this->idCoordinador(), ROL_COORDINADOR);
    }

    /** Cuenta de aprendiz sin matrícula, como las que creaba Usuarios. */
    private function cuentaSinMatricula(): int {
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password, debe_cambiar_password, rol, avatar_color, estado)
                            VALUES ('CUENTA SIN FICHA', ?, ?, 0, 'aprendiz', '#39A900', 'activo')")
                 ->execute([self::CORREO, password_hash('ClaveVieja2026*', PASSWORD_DEFAULT)]);
        return (int)$this->db->lastInsertId();
    }

    /** Una ficha que admite matrículas y cuyo programa tiene RAP. */
    private function fichaAbierta(): int {
        $id = $this->db->query("
            SELECT f.id FROM fichas f
             WHERE f.estado <> 'cierre' AND f.instructor_id IS NOT NULL
               AND EXISTS (SELECT 1 FROM competencias c JOIN resultados_aprendizaje ra ON ra.competencia_id = c.id
                            WHERE c.programa_id = f.programa_id)
             LIMIT 1")->fetchColumn();
        return $id !== false ? (int)$id : $this->markTestSkipped('No hay una ficha abierta con RAP.');
    }

    private function datosMatricula(int $fichaId, array $cambios = []): array {
        return array_merge([
            'nombre' => 'LAURA PRUEBA QA', 'email' => self::CORREO, 'tipo_documento' => 'CC',
            'numero_documento' => '99990000777', 'ficha_id' => $fichaId, 'genero' => 'F', 'fecha_nacimiento' => null,
            'telefono' => '', 'ciudad' => '', 'instructor_seguimiento_id' => null, 'estado' => 'matriculado',
        ], $cambios);
    }

    private function usuario(int $id): array {
        return $this->db->query("SELECT * FROM usuarios WHERE id = $id")->fetch();
    }

    /** Datos de edición a partir de la cuenta actual. */
    private function edicion(int $id, array $cambios): array {
        $u = $this->usuario($id);
        return array_merge(['nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol'], 'estado' => $u['estado'],
                            'avatar_color' => $u['avatar_color'] ?: '#39A900'], $cambios);
    }

    private function esperarError(callable $op, string $contiene): void {
        try {
            $op();
            $this->fail('se esperaba un ErrorDeNegocio');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsStringIgnoringCase($contiene, $e->getMessage());
        }
    }

    // =================================================================
    // USUARIOS
    // =================================================================

    #[TestDox('Usuarios no crea cuentas de aprendiz: se crean al matricular')]
    public function testNoCreaAprendices(): void {
        $this->esperarError(fn() => (new UsuariosService($this->db))->crear(
            ['nombre' => 'NUEVO APRENDIZ', 'email' => 'nuevo.qa@example.com', 'rol' => ROL_APRENDIZ, 'avatar_color' => '#39A900'],
            $this->coordinador()), 'Matrículas');
        $this->assertSame(0, $this->contar('usuarios', 'email = ?', ['nuevo.qa@example.com']));
    }

    #[TestDox('un instructor no pasa a aprendiz desde Usuarios')]
    public function testInstructorNoPasaAAprendiz(): void {
        $id = $this->idInstructorConFicha();
        $this->esperarError(fn() => (new UsuariosService($this->db))->editar($id, $this->edicion($id, ['rol' => ROL_APRENDIZ]),
            $this->coordinador()), 'al matricular');
        $this->assertSame('instructor', $this->usuario($id)['rol']);
    }

    #[TestDox('un aprendiz con matrícula no cambia de rol desde Usuarios')]
    public function testAprendizMatriculadoNoCambiaDeRol(): void {
        $id = $this->idUsuarioAprendiz();
        $this->esperarError(fn() => (new UsuariosService($this->db))->editar($id, $this->edicion($id, ['rol' => ROL_INSTRUCTOR]),
            $this->coordinador()), 'tiene matrícula');
        $this->assertSame('aprendiz', $this->usuario($id)['rol']);
    }

    #[TestDox('una cuenta de aprendiz sin matrícula sí se corrige a otro rol')]
    public function testCuentaSinMatriculaCambiaDeRol(): void {
        $id = $this->cuentaSinMatricula();
        (new UsuariosService($this->db))->editar($id, $this->edicion($id, ['rol' => ROL_INSTRUCTOR]), $this->coordinador());
        $this->assertSame('instructor', $this->usuario($id)['rol']);
    }

    #[TestDox('la cuenta de un aprendiz desertado no se reactiva por fuera de su matrícula')]
    public function testNoReactivaDesertado(): void {
        $id = $this->idUsuarioAprendiz();
        $this->db->exec("UPDATE aprendices SET estado = 'desertado' WHERE usuario_id = $id");
        $this->db->exec("UPDATE usuarios SET estado = 'inactivo' WHERE id = $id");
        $s = new UsuariosService($this->db);
        $this->esperarError(fn() => $s->cambiarEstado($id, 'activo', $this->coordinador()), 'desertado');
        $this->esperarError(fn() => $s->editar($id, $this->edicion($id, ['estado' => 'activo']), $this->coordinador()), 'Matrículas');
        $this->assertSame('inactivo', $this->usuario($id)['estado']);

        // Con la matrícula vigente, sí.
        $this->db->exec("UPDATE aprendices SET estado = 'matriculado' WHERE usuario_id = $id");
        $s->cambiarEstado($id, 'activo', $this->coordinador());
        $this->assertSame('activo', $this->usuario($id)['estado']);
    }

    // =================================================================
    // MATRÍCULAS
    // =================================================================

    #[TestDox('matricular con el correo de una cuenta de aprendiz sin ficha completa esa cuenta')]
    public function testMatriculaCompletaCuentaSinFicha(): void {
        $cuenta = $this->cuentaSinMatricula();
        $r = (new MatriculasService($this->db))->matricular($this->datosMatricula($this->fichaAbierta()), $this->coordinador());

        $this->assertTrue($r['cuenta_existente']);
        $this->assertSame($cuenta, (int)$this->db->query("SELECT usuario_id FROM aprendices WHERE id = {$r['id']}")->fetchColumn());
        $this->assertSame(1, $this->contar('usuarios', 'email = ?', [self::CORREO]));
        $u = $this->usuario($cuenta);
        $this->assertSame('LAURA PRUEBA QA', $u['nombre']);
        $this->assertSame(1, (int)$u['debe_cambiar_password']);
        $this->assertTrue(password_verify($r['temporal'], $u['password']));
        $this->assertFalse(password_verify('ClaveVieja2026*', $u['password']));
        $this->assertGreaterThan(0, $this->contar('evaluaciones', 'aprendiz_id = ?', [$r['id']]));
    }

    #[TestDox('el correo de una cuenta que no es de aprendiz sigue rechazándose al matricular')]
    public function testCorreoDeInstructorNoSeMatricula(): void {
        $correo = $this->usuario($this->idInstructorConFicha())['email'];
        $this->esperarError(fn() => (new MatriculasService($this->db))->matricular(
            $this->datosMatricula($this->fichaAbierta(), ['email' => $correo]), $this->coordinador()), 'ya está registrado');
        $this->assertSame(1, $this->contar('usuarios', 'email = ?', [$correo]));
    }

    #[TestDox('la importación de matrículas avisa y completa la cuenta de aprendiz sin ficha')]
    public function testImportacionCompletaCuentaSinFicha(): void {
        $cuenta = $this->cuentaSinMatricula();
        $importador = new ImportadorMatriculas($this->db);
        $contexto = ['ficha_id' => $this->fichaAbierta()];
        $fila = ['nombre' => 'LAURA PRUEBA QA', 'email' => self::CORREO, 'tipo_documento' => 'CC', 'numero_documento' => '99990000777',
                 'genero' => 'F', 'telefono' => '', 'ciudad' => '', 'fecha_nacimiento' => ''];

        $v = $importador->validarFila($fila, $this->coordinador(), $contexto);
        $this->assertSame([], $v['errores']);
        $this->assertStringContainsString('sin ficha', implode(' ', $v['avisos']));

        $r = $importador->guardar([$v['datos']], $this->coordinador(), $contexto);
        $this->assertSame(1, $r['creados']);
        $this->assertSame(1, $this->contar('aprendices', 'usuario_id = ?', [$cuenta]));
    }

    #[TestDox('la importación de usuarios rechaza las filas con rol aprendiz')]
    public function testImportacionDeUsuariosSinAprendices(): void {
        $v = (new ImportadorUsuarios($this->db))->validarFila(
            ['nombre' => 'CAMILA PRUEBA QA', 'email' => 'camila.qa@example.com', 'rol' => 'Aprendiz'], $this->coordinador(), []);
        $this->assertStringContainsString('Matrículas', implode(' ', $v['errores']));
    }
}
