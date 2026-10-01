<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use Core\Services\Notificador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * La URL de una notificación termina en el enlace de la campana de cada
 * usuario. urlSegura() es la única barrera entre lo que un servicio (o una
 * fila antigua de la tabla) deja ahí y el clic: si se cuela un
 * `javascript:` es un XSS almacenado; si se cuela otro dominio, un phishing
 * servido desde el propio sistema. Se usa al escribir (Notificador) y otra
 * vez al leer (la campana y la API de notificaciones).
 */
final class NotificadorUrlTest extends CasoDePrueba {

    /** Las rutas que generan de verdad los servicios al notificar. */
    public static function rutasInternas(): array {
        return [
            'juicios del aprendiz'      => ['/index.php/evaluaciones'],
            'seguimiento de una ficha'  => ['/index.php/seguimiento?ficha_id=3'],
            'detalle de una ficha'      => ['/index.php/fichas/ver?id=12'],
            'evidencias por revisar'    => ['/index.php/evidencias?estado=enviada'],
            'ruta con guion'            => ['/index.php/resultados-aprendizaje'],
            'varios parámetros'         => ['/index.php/evaluaciones?ficha_id=1&competencia_id=4'],
        ];
    }

    #[DataProvider('rutasInternas')]
    #[TestDox('acepta la ruta interna $ruta y le antepone APP_URL')]
    public function testAceptaRutasInternas(string $ruta): void {
        $this->assertSame(APP_URL . $ruta, Notificador::urlSegura($ruta));
    }

    /**
     * Lo guardado ya lleva APP_URL y la campana lo vuelve a pasar por aquí:
     * una URL buena no puede perderse ni duplicar el prefijo al releerla.
     */
    #[DataProvider('rutasInternas')]
    #[TestDox('revalidar al leer lo que ya se guardó da la misma URL: $ruta')]
    public function testRevalidarLoGuardadoNoLoCambia(string $ruta): void {
        $guardada = Notificador::urlSegura($ruta);
        $this->assertNotNull($guardada);
        $this->assertSame($guardada, Notificador::urlSegura($guardada));
    }

    public static function rutasPeligrosas(): array {
        return [
            'nula'                          => [null],
            'vacía'                         => [''],
            'javascript:'                   => ['javascript:alert(document.cookie)'],
            'javascript: en mayúsculas'     => ['JaVaScRiPt:alert(1)'],
            'data:'                         => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'otro sitio sin esquema (//)'   => ['//otro.sitio/index.php/evaluaciones'],
            'http a otro sitio'             => ['http://otro.sitio/index.php/evaluaciones'],
            'https a otro sitio'            => ['https://otro.sitio/proyecto_sena/index.php/evaluaciones'],
            'barra invertida'               => ['/\\otro.sitio/index.php/evaluaciones'],
            'subir de carpeta'              => ['/index.php/../../config/app.php'],
            'subir a mitad de la ruta'      => ['/index.php/evaluaciones/../../.env'],
            'subir codificado'              => ['/index.php/%2e%2e/%2e%2e/.env'],
            'otro script'                   => ['/otro.php/evaluaciones'],
            'sin barra inicial'             => ['index.php/evaluaciones'],
            'espacio delante'               => [' /index.php/evaluaciones'],
            'salto de línea a mitad'        => ["/index.php/evalua\nciones"],
            'cabecera inyectada con CRLF'   => ["/index.php/evaluaciones\r\nSet-Cookie: sesion=robada"],
            'tabulador'                     => ["/index.php/eval\tuaciones"],
            'HTML en un parámetro'          => ['/index.php/evaluaciones?x="><script>alert(1)</script>'],
            'parámetro que redirige fuera'  => ['/index.php/login?volver=//otro.sitio'],
        ];
    }

    #[DataProvider('rutasPeligrosas')]
    #[TestDox('rechaza $_dataName')]
    public function testRechazaRutasPeligrosas(?string $ruta): void {
        $this->assertNull(Notificador::urlSegura($ruta));
    }

    /**
     * Solo se recorta APP_URL seguido de una barra: un prefijo que solo se
     * le parece no convierte en interna una ruta que no lo es.
     */
    #[TestDox('un prefijo que solo se parece al de la aplicación no se da por interno')]
    public function testPrefijoParecido(): void {
        $this->assertNull(Notificador::urlSegura(APP_URL . '.otro.sitio/index.php/evaluaciones'));
        $this->assertNull(Notificador::urlSegura(APP_URL . '//otro.sitio/index.php/evaluaciones'));
        $this->assertNull(Notificador::urlSegura(APP_URL . '/../otra-app/index.php/evaluaciones'));
    }

    /**
     * En PCRE, `$` casa también justo antes de un salto de línea final si
     * el patrón no lleva el modificador D.
     */
    #[TestDox('rechaza una ruta que termina en salto de línea')]
    public function testRechazaSaltoDeLineaFinal(): void {

        $this->assertNull(Notificador::urlSegura("/index.php/evaluaciones\n"));
        $this->assertNull(Notificador::urlSegura("/index.php/seguimiento?ficha_id=3\n"));
    }

    /**
     * La columna `notificaciones.url` es VARCHAR(255) y la base está en modo
     * estricto: una URL más larga haría fallar el INSERT, y como notificar()
     * no deja que un fallo tumbe la operación, el aviso se perdería entero.
     */
    #[TestDox('la URL resultante cabe en la columna notificaciones.url (255)')]
    public function testCabeEnLaColumna(): void {
        $url = Notificador::urlSegura('/index.php/evaluaciones?' . str_repeat('ficha_id=1&', 40));
        $this->assertNotNull($url);
        $this->assertLessThanOrEqual(255, mb_strlen($url));
        $this->assertStringStartsWith(APP_URL . '/index.php/evaluaciones?', $url);
    }
}
