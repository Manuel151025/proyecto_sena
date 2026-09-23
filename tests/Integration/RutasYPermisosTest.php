<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Router;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * La tabla de rutas es, además, la matriz de control de acceso del sistema.
 *
 * Antes el permiso vivía en el constructor de cada controlador y nueve de
 * ellos solo comprobaban que hubiera sesión, sin mirar el rol. Estas
 * pruebas verifican que la declaración de `index.php` sea completa y que no
 * conceda más de lo que el propio controlador permite.
 */
final class RutasYPermisosTest extends CasoDePrueba {

    /** Enrutador con la tabla real de `config/rutas.php`. */
    private function router(): Router {
        $router = new Router();
        (require dirname(__DIR__, 2) . '/config/rutas.php')($router);
        return $router;
    }

    /** Rutas (pantallas y envíos sin `action`), por "MÉTODO /ruta". */
    private function rutas(): array {
        return $this->router()->rutas();
    }

    /**
     * Todo destino alcanzable: rutas y acciones POST, estas últimas con
     * clave "POST /ruta#accion".
     */
    private function destinos(): array {
        $router = $this->router();
        $todos = $router->rutas();
        foreach ($router->acciones() as $ruta => $acciones) {
            foreach ($acciones as $nombre => $destino) {
                $todos["POST $ruta#$nombre"] = $destino;
            }
        }
        return $todos;
    }

    #[TestDox('toda ruta declara explícitamente qué roles la alcanzan')]
    public function testTodasLasRutasDeclaranRoles(): void {
        $sinRoles = [];
        foreach ($this->rutas() as $clave => $r) {
            if ($r['roles'] === []) {
                $sinRoles[] = $clave;
            }
        }
        $this->assertSame([], $sinRoles,
            'estas rutas no declaran permiso: ' . implode(', ', $sinRoles));
    }

    #[TestDox('toda ruta y toda acción apuntan a una clase y un método que existen')]
    public function testDestinosExisten(): void {
        $rotas = [];
        foreach ($this->destinos() as $clave => $r) {
            if (!class_exists($r['controller']) || !method_exists($r['controller'], $r['action'])) {
                $rotas[] = "$clave -> {$r['controller']}::{$r['action']}";
            }
        }
        $this->assertSame([], $rotas, 'rutas rotas: ' . implode(', ', $rotas));
    }

    /**
     * La ruta no puede abrir una puerta que el controlador cierra: sería
     * una declaración engañosa, y quien audite `index.php` creería que el
     * acceso es más amplio de lo que es.
     */
    #[TestDox('ninguna ruta concede más de lo que su controlador permite')]
    public function testLasRutasNoAmplianElPermiso(): void {
        $contradicciones = [];

        foreach ($this->rutas() as $clave => $r) {
            $archivo = dirname(__DIR__, 2) . '/core/Controllers/'
                     . basename(str_replace('\\', '/', $r['controller'])) . '.php';
            if (!is_file($archivo)) {
                continue;
            }

            $fuente = (string)file_get_contents($archivo);
            if (!preg_match('/requireRole\(([^)]*)\)/', $fuente, $m)) {
                continue;   // solo exige sesión: la ruta puede restringir más
            }

            $exigidos = array_map(
                static fn($x) => trim(str_replace(
                    ['ROL_COORDINADOR', 'ROL_INSTRUCTOR', 'ROL_APRENDIZ'],
                    [ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ],
                    $x
                )),
                explode(',', $m[1])
            );

            $sobran = array_diff($r['roles'], $exigidos);
            if ($sobran !== []) {
                $contradicciones[] = "$clave declara [" . implode(',', $r['roles'])
                                   . '] pero el controlador exige [' . implode(',', $exigidos) . ']';
            }
        }

        $this->assertSame([], $contradicciones, implode(' | ', $contradicciones));
    }

    #[TestDox('las pantallas de administración son exclusivas de coordinación')]
    public function testPantallasDeAdministracionRestringidas(): void {
        $rutas = $this->rutas();

        $soloCoordinador = [
            '/usuarios', '/usuarios/exportar', '/usuarios/importar', '/matriculas/importar',
            '/estructura', '/estructura/importar', '/configuracion', '/logs',
        ];

        foreach ($soloCoordinador as $ruta) {
            $clave = 'GET ' . $ruta;
            $this->assertArrayHasKey($clave, $rutas, "no existe la ruta $ruta");
            $this->assertSame(
                [ROL_COORDINADOR],
                $rutas[$clave]['roles'],
                "$ruta debería ser exclusiva de coordinación"
            );
        }
    }

    /**
     * La administración académica (qué se cursa, quién está matriculado y
     * quién califica qué) la decide coordinación: toda operación de escritura
     * de estas pantallas tiene que ser exclusiva de ese rol.
     */
    #[TestDox('las operaciones de administración académica son exclusivas de coordinación')]
    public function testOperacionesAdministrativasSoloCoordinacion(): void {
        $prefijos = ['/usuarios', '/programas', '/competencias', '/resultados-aprendizaje', '/estructura',
                     '/fichas', '/matriculas', '/asignaciones', '/proyectos', '/configuracion'];
        $abiertas = [];
        foreach ($this->destinos() as $clave => $r) {
            if (!str_starts_with($clave, 'POST ')) {
                continue;
            }
            $ruta = explode('#', substr($clave, 5))[0];
            foreach ($prefijos as $p) {
                if ($ruta === $p || str_starts_with($ruta, $p . '/')) {
                    if ($r['roles'] !== [ROL_COORDINADOR]) {
                        $abiertas[] = "$clave [" . implode(',', $r['roles']) . ']';
                    }
                    break;
                }
            }
        }
        $this->assertSame([], $abiertas, 'abiertas a más roles: ' . implode(' | ', $abiertas));
    }

    #[TestDox('ninguna escritura está abierta al aprendiz por descuido')]
    public function testEscriturasSensiblesCerradasAlAprendiz(): void {
        // Lo único que escribe el aprendiz: sus evidencias, su perfil, sus
        // avisos, su calendario y el cierre de sesión.
        $permitidas = ['/evidencias', '/perfil', '/api/notificaciones', '/calendario', '/logout',
                       '/evaluaciones', '/seguimiento', '/retroalimentacion'];
        $abiertas = [];
        foreach ($this->destinos() as $clave => $r) {
            if (!str_starts_with($clave, 'POST ') || !in_array(ROL_APRENDIZ, $r['roles'], true)) {
                continue;
            }
            $ruta = explode('#', substr($clave, 5))[0];
            if (!in_array($ruta, $permitidas, true)) {
                $abiertas[] = $clave;
            }
        }
        $this->assertSame([], $abiertas, 'escrituras abiertas al aprendiz: ' . implode(', ', $abiertas));
    }

    #[TestDox('la importación de juicios no está abierta al aprendiz')]
    public function testImportacionRestringida(): void {
        $rutas = $this->rutas();
        foreach (['POST /evaluaciones/importar', 'POST /competencias/importar', 'POST /estructura/importar'] as $clave) {
            if (isset($rutas[$clave])) {
                $this->assertNotContains(ROL_APRENDIZ, $rutas[$clave]['roles'], "$clave permite al aprendiz importar");
            }
        }
    }

    #[TestDox('el número de rutas no ha cambiado sin que nadie se entere')]
    public function testNumeroDeRutas(): void {
        // Es un canario: si alguien añade o quita una ruta, esta prueba
        // falla y obliga a revisar conscientemente el permiso que declara.
        $this->assertCount(103, $this->destinos(),
            'ha cambiado el número de rutas: revisa los permisos declarados y actualiza esta cifra');
    }
}
