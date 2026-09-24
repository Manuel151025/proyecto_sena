<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\PoliticaContrasena;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Una sola política para el perfil, la recuperación y las claves
 * temporales. Antes cada pantalla tenía la suya y ninguna ponía tope: lo
 * que pasaba de 72 bytes se truncaba en silencio al hashear con bcrypt.
 */
final class PoliticaContrasenaTest extends CasoDePrueba {

    #[DataProvider('invalidas')]
    #[TestDox('rechaza la contraseña que incumple una regla')]
    public function testRechaza(string $clave, string $motivo): void {
        $errores = PoliticaContrasena::errores($clave, 'ana.perez@soy.sena.edu.co');
        $this->assertNotSame([], $errores, "aceptó una contraseña $motivo");
    }

    public static function invalidas(): array {
        return [
            'corta'                  => ['abc123', 'de 6 caracteres'],
            'solo letras'            => ['abcdefghij', 'sin números'],
            'solo números'           => ['1234567890', 'sin letras'],
            'más de 72 bytes'        => [str_repeat('a1', 37), 'de 74 bytes'],
            '72 bytes con tildes'    => [str_repeat('ñ', 36) . 'a1', 'de 74 bytes con tildes'],
            'caracter de control'    => ["clave123\x00x", 'con un byte nulo'],
            'usuario del correo'     => ['ana.perez2026', 'con el usuario del correo'],
            'común'                  => ['sena2026', 'común'],
        ];
    }

    #[TestDox('acepta una contraseña que cumple todas las reglas, también con tildes')]
    public function testAcepta(): void {
        $this->assertSame([], PoliticaContrasena::errores('Montaña2026', 'ana.perez@soy.sena.edu.co'));
        $this->assertSame([], PoliticaContrasena::errores(str_repeat('a1', 36)), 'rechazó exactamente 72 bytes');
    }

    #[TestDox('la clave temporal cumple siempre la política')]
    public function testTemporalCumple(): void {
        for ($i = 0; $i < 200; $i++) {
            $this->assertSame([], PoliticaContrasena::errores(PoliticaContrasena::temporal()));
        }
    }
}
