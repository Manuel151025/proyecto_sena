<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

use Core\Support\Enums;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Enums.php duplica en PHP los valores que declara el esquema. La
 * duplicación es deliberada —hace falta validar antes de tocar la base—
 * pero tiene un riesgo evidente: que las dos listas se separen. Aquí se
 * comprueban las propiedades que deben cumplirse siempre; que coincidan
 * con el esquema real lo verifica la prueba de integración.
 */
final class EnumsTest extends CasoDePrueba {

    /** @return array<string, string[]> */
    private function todas(): array {
        return Enums::mapaEsquema();
    }

    #[TestDox('ninguna lista de valores está vacía')]
    public function testNingunaListaVacia(): void {
        foreach ($this->todas() as $columna => $valores) {
            $this->assertNotEmpty($valores, "$columna no declara ningún valor");
        }
    }

    #[TestDox('ninguna lista tiene valores repetidos')]
    public function testSinDuplicados(): void {
        foreach ($this->todas() as $columna => $valores) {
            $this->assertSame(
                count($valores),
                count(array_unique($valores)),
                "$columna tiene valores repetidos"
            );
        }
    }

    /**
     * Un valor con espacios alrededor nunca coincidiría con lo que hay en
     * la base, así que el `in_array` estricto del validador lo rechazaría
     * siempre y el campo quedaría inutilizable sin que nadie entienda por qué.
     */
    #[TestDox('ningún valor lleva espacios alrededor')]
    public function testSinEspaciosAlrededor(): void {
        foreach ($this->todas() as $columna => $valores) {
            foreach ($valores as $v) {
                $this->assertSame(trim($v), $v, "'$v' de $columna lleva espacios");
            }
        }
    }

    #[TestDox('todos los valores son cadenas, no números ni nulos')]
    public function testTodosSonCadenas(): void {
        foreach ($this->todas() as $columna => $valores) {
            foreach ($valores as $v) {
                $this->assertIsString($v, "$columna contiene un valor que no es cadena");
                $this->assertNotSame('', $v, "$columna contiene una cadena vacía");
            }
        }
    }

    #[TestDox('las claves del mapa tienen forma tabla.columna')]
    public function testFormaDeLasClaves(): void {
        foreach (array_keys($this->todas()) as $clave) {
            $this->assertMatchesRegularExpression(
                '/^[a-z_]+\.[a-z_]+$/', $clave,
                "la clave '$clave' no identifica una columna"
            );
        }
    }

    #[TestDox('los conceptos evaluativos son exactamente A, D y pendiente')]
    public function testConceptosEvaluativos(): void {
        // Es la regla del SENA y el núcleo del sistema: si cambia, cambia
        // el significado de toda la trazabilidad.
        $this->assertSame(['A', 'D', 'pendiente'], Enums::CONCEPTO);
    }

    #[TestDox('los tres roles del sistema coinciden con las constantes globales')]
    public function testRolesCoincidenConLasConstantes(): void {
        $this->assertSame(
            [ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ],
            Enums::USUARIO_ROL,
            'config/app.php y Enums.php declaran roles distintos'
        );
    }

    #[TestDox('el historial usa los mismos conceptos que la evaluación')]
    public function testHistorialUsaLosMismosConceptos(): void {
        $mapa = $this->todas();
        $this->assertSame($mapa['evaluaciones.concepto'], $mapa['historial_evaluaciones.concepto_anterior']);
        $this->assertSame($mapa['evaluaciones.concepto'], $mapa['historial_evaluaciones.concepto_nuevo']);
    }

    #[TestDox('los estados de aprendiz incluyen los que el sistema trata aparte')]
    public function testEstadosDeAprendizRelevantes(): void {
        // 'desertado' se excluye del recuento de matriculados y
        // 'etapa_practica' cambia quién califica: los dos tienen que existir.
        $this->assertContains('desertado', Enums::APRENDIZ_ESTADO);
        $this->assertContains('etapa_practica', Enums::APRENDIZ_ESTADO);
    }
}
