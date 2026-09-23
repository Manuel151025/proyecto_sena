<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\ErrorDeNegocio;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\CasoDePrueba;
use TypeError;

/**
 * Esta clase decide qué se le enseña al usuario cuando algo falla. Si se
 * equivoca en un sentido, filtra el esquema de la base de datos; si se
 * equivoca en el otro, el usuario deja de entender por qué no le dejan
 * hacer algo. Las dos direcciones se prueban.
 */
final class ErrorDeNegocioTest extends CasoDePrueba {

    #[TestDox('un ErrorDeNegocio sí muestra su mensaje: está escrito para el usuario')]
    public function testMensajeDeNegocioSeMuestra(): void {
        $e = new ErrorDeNegocio('No tiene permisos para calificar esta competencia.');
        $this->assertSame(
            'No tiene permisos para calificar esta competencia.',
            ErrorDeNegocio::mensajeSeguro($e)
        );
    }

    #[TestDox('el contexto no se antepone a un mensaje de negocio')]
    public function testMensajeDeNegocioNoLlevaContexto(): void {
        $e = new ErrorDeNegocio('El motivo del cambio es requerido.');
        $this->assertSame(
            'El motivo del cambio es requerido.',
            ErrorDeNegocio::mensajeSeguro($e, 'Error al guardar')
        );
    }

    /**
     * Las excepciones de infraestructura llevan dentro el nombre de la
     * tabla, el de la columna o la ruta del archivo en el servidor. Nada de
     * eso puede salir a la pantalla.
     */
    #[DataProvider('excepcionesTecnicas')]
    #[TestDox('una excepción técnica no filtra su detalle')]
    public function testExcepcionTecnicaNoFiltraDetalle(\Throwable $e, string $secreto): void {
        $msg = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar', false);
        $this->assertStringNotContainsString($secreto, $msg);
    }

    public static function excepcionesTecnicas(): array {
        return [
            'PDO: columna' => [
                new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'f.password_hash'"),
                'password_hash',
            ],
            'PDO: tabla' => [
                new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'sena.usuarios_internos'"),
                'usuarios_internos',
            ],
            'PDO: credenciales' => [
                new PDOException("SQLSTATE[28000] Access denied for user 'root'@'localhost'"),
                'root',
            ],
            'TypeError con ruta' => [
                new TypeError('Argument #1 must be int, called in C:\\xampp\\htdocs\\proyecto_sena\\core\\X.php on line 42'),
                'xampp',
            ],
            'Runtime con ruta' => [
                new RuntimeException('No se pudo abrir /var/www/secreto/.env'),
                '.env',
            ],
        ];
    }

    #[TestDox('una excepción técnica tampoco filtra el código SQLSTATE')]
    public function testNoFiltraSqlstate(): void {
        $e = new PDOException("SQLSTATE[42S22]: Column not found");
        $this->assertStringNotContainsString('SQLSTATE', ErrorDeNegocio::mensajeSeguro($e, '', false));
    }

    #[TestDox('el usuario recibe el contexto, para saber qué falló')]
    public function testConservaElContexto(): void {
        $msg = ErrorDeNegocio::mensajeSeguro(new PDOException('lo que sea'), 'Error al cargar la ficha', false);
        $this->assertStringContainsString('Error al cargar la ficha', $msg);
    }

    #[TestDox('el mensaje lleva una referencia cruzable con el log')]
    public function testIncluyeReferencia(): void {
        $msg = ErrorDeNegocio::mensajeSeguro(new PDOException('x'), '', false);
        $this->assertMatchesRegularExpression('/Referencia: [0-9A-F]{6}/', $msg);
    }

    #[TestDox('cada fallo recibe una referencia distinta')]
    public function testLasReferenciasSonUnicas(): void {
        $refs = [];
        for ($i = 0; $i < 20; $i++) {
            preg_match('/Referencia: ([0-9A-F]{6})/',
                ErrorDeNegocio::mensajeSeguro(new PDOException('x'), '', false), $m);
            $refs[] = $m[1] ?? '';
        }
        $this->assertGreaterThan(15, count(array_unique($refs)),
            'las referencias se repiten: no servirían para localizar un incidente');
    }

    #[TestDox('en modo desarrollo sí se muestra la causa técnica')]
    public function testEnDesarrolloMuestraLaCausa(): void {
        $e = new PDOException('SQLSTATE[42S22]: Column not found');
        $msg = ErrorDeNegocio::mensajeSeguro($e, '', true);
        $this->assertStringContainsString('SQLSTATE', $msg);
        $this->assertStringContainsString('Error interno', $msg);
    }

    #[TestDox('ErrorDeNegocio es una Exception normal y se puede capturar')]
    public function testEsCapturableComoException(): void {
        $capturada = false;
        try {
            throw new ErrorDeNegocio('mensaje');
        } catch (\Exception $e) {
            $capturada = $e instanceof ErrorDeNegocio;
        }
        $this->assertTrue($capturada);
    }

    #[TestDox('admite encadenar la excepción original sin exponerla')]
    public function testAdmiteExcepcionAnidada(): void {
        $causa = new PDOException('SQLSTATE[23000]: Duplicate entry');
        $e = new ErrorDeNegocio('Ese número de ficha ya existe.', 0, $causa);

        $this->assertSame('Ese número de ficha ya existe.', ErrorDeNegocio::mensajeSeguro($e));
        $this->assertSame($causa, $e->getPrevious(), 'la causa debe conservarse para el log');
    }
}
