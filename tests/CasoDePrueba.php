<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas que no tocan la base de datos.
 *
 * Aporta el manejo de la sesión simulada: varias de las cosas que hay que
 * comprobar (CSRF, rol actual, mensajes flash) viven en `$_SESSION`, y sin
 * limpiarla entre pruebas una dejaría el estado a la siguiente.
 */
abstract class CasoDePrueba extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->limpiarSesion();
    }

    protected function tearDown(): void {
        $this->limpiarSesion();
        parent::tearDown();
    }

    /** Deja la sesión como recién creada. */
    protected function limpiarSesion(): void {
        $_SESSION = ['tabs' => []];
        $_POST = [];
        $_GET = [];
        $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /**
     * Simula que hay un usuario con sesión iniciada en la pestaña actual.
     *
     * @return array Los datos escritos, para poder compararlos.
     */
    protected function iniciarSesionComo(string $rol, int $userId = 1, string $nombre = 'Usuario de prueba'): array {
        $datos = [
            'user_id'           => $userId,
            'user_nombre'       => $nombre,
            'user_email'        => 'prueba@sena.edu.co',
            'user_rol'          => $rol,
            'user_avatar_color' => '#39A900',
        ];
        $_SESSION['tabs']['default'] = $datos;
        return $datos;
    }

    /** Simula una petición POST con un token CSRF válido. */
    protected function peticionPost(array $datos = []): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $datos + ['csrf_token' => getCsrfToken()];
    }
}
