<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Services\AuditoriaContrasenas;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * La herramienta que se ejecuta en producción tras conocer la filtración
 * del volcado (123 cuentas con `admin123`). Si no encuentra una cuenta, la
 * deja abierta; si anula de más, deja fuera a quien no debía.
 */
final class AuditoriaContrasenasTest extends CasoConBaseDeDatos {

    private function fijarClave(int $id, string $hash): void {
        $this->db->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 0 WHERE id = ?')->execute([$hash, $id]);
    }

    #[TestDox('encuentra las cuentas con una contraseña conocida, también si comparten el mismo hash')]
    public function testEncuentraLasConocidas(): void {
        $filtrado = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 4]);
        $a = $this->idUsuarioAprendiz();
        $b = $this->idCoordinador();
        $c = $this->idInstructorConFicha();
        $this->fijarClave($a, $filtrado);
        $this->fijarClave($b, $filtrado);   // el volcado repetía el mismo hash en todas
        $this->fijarClave($c, password_hash('Otra-Clave-2026', PASSWORD_BCRYPT, ['cost' => 4]));

        $halladas = (new AuditoriaContrasenas($this->db, ['admin123', 'Sena2026']))->buscar([$a, $b, $c]);
        $ids = array_column($halladas, 'id');
        sort($ids);
        $esperados = [$a, $b];
        sort($esperados);
        $this->assertSame($esperados, $ids);
        $this->assertSame(['admin123'], array_values(array_unique(array_column($halladas, 'clave'))));
        $this->assertSame([], (new AuditoriaContrasenas($this->db))->buscar([]));
    }

    #[TestDox('anular deja inservible la clave conocida, exige cambiarla y queda en la bitácora')]
    public function testAnular(): void {
        $id = $this->idUsuarioAprendiz();
        $this->fijarClave($id, password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 4]));
        $auditor = new AuditoriaContrasenas($this->db, ['admin123']);

        $anuladas = $auditor->anular($auditor->buscar([$id]));
        $this->assertCount(1, $anuladas);

        $u = $this->db->query("SELECT password, debe_cambiar_password FROM usuarios WHERE id = $id")->fetch();
        $this->assertFalse(password_verify('admin123', $u['password']), 'la clave filtrada sigue sirviendo');
        $this->assertTrue(password_verify($anuladas[0]['temporal'], $u['password']));
        $this->assertSame(1, (int)$u['debe_cambiar_password']);
        $this->assertSame([], \Core\Support\PoliticaContrasena::errores($anuladas[0]['temporal']));
        $this->assertSame(1, $this->contar('logs_sistema', "tabla_afectada = 'usuarios' AND id_registro = ? AND modulo = 'Consola'", [$id]));
        $this->assertSame([], $auditor->buscar([$id]), 'después de anular ya no debe aparecer');
    }
}
