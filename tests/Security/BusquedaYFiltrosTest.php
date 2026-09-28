<?php
declare(strict_types=1);

namespace Tests\Security;

use Core\Models\EvaluacionesModel;
use Core\Models\UsuarioModel;
use Core\Support\Enums;
use Core\Support\Validador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Abuso de los buscadores y de los filtros de los listados.
 *
 * Las dos cosas que se prueban aquí las encontró InyeccionTest y estaban
 * mal en el código original:
 *
 *  1. `%` y `_` son comodines de LIKE. Buscar «%» devolvía la tabla
 *     entera: el buscador servía para saltarse el propio buscador y para
 *     forzar un recorrido completo de una tabla de 3.900 filas.
 *
 *  2. Un filtro con un valor desconocido se ignoraba, de modo que
 *     `?rol=loquesea` mostraba TODOS los usuarios en vez de ninguno.
 */
final class BusquedaYFiltrosTest extends CasoConBaseDeDatos {

    // =================================================================
    // COMODINES DE LIKE
    // =================================================================

    #[DataProvider('comodines')]
    #[TestDox('un comodín en la búsqueda no devuelve la tabla entera')]
    public function testComodinesNoDevuelvenTodo(string $comodin): void {
        $modelo = new UsuarioModel($this->db);
        $total = $modelo->contar([]);

        $this->assertLessThan(
            $total,
            $modelo->contar(['search' => $comodin]),
            "buscar «$comodin» devuelve toda la tabla: el filtro no sirve de nada"
        );
    }

    public static function comodines(): array {
        return [
            'porcentaje'          => ['%'],
            'guion bajo'          => ['_'],
            'ambos'               => ['%_%'],
            'varios porcentajes'  => ['%%%'],
            'comodín con texto'   => ['%a%'],
            'barra invertida'     => ['\\'],
        ];
    }

    #[TestDox('el mismo comodín tampoco vacía el filtro de evaluaciones')]
    public function testComodinesEnEvaluaciones(): void {
        $modelo = new EvaluacionesModel($this->db);
        $coord = new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR);

        $total = $modelo->contar($coord, []);
        $conComodin = $modelo->contar($coord, ['search' => '%']);

        $this->assertLessThan($total, $conComodin, 'el buscador de evaluaciones acepta comodines');
    }

    /**
     * El escapado no puede romper las búsquedas legítimas: un nombre
     * existente tiene que seguir encontrándose.
     */
    #[TestDox('una búsqueda normal sigue encontrando lo que debe')]
    public function testBusquedaLegitimaSigueFuncionando(): void {
        $nombre = (string)$this->db->query(
            "SELECT nombre FROM usuarios WHERE nombre IS NOT NULL AND nombre <> '' LIMIT 1"
        )->fetchColumn();

        $fragmento = mb_substr($nombre, 0, 4);
        $modelo = new UsuarioModel($this->db);

        $this->assertGreaterThan(
            0,
            $modelo->contar(['search' => $fragmento]),
            "escapar los comodines rompió la búsqueda de «$fragmento»"
        );
    }

    #[TestDox('ahora se puede buscar un porcentaje literal')]
    public function testBusquedaDePorcentajeLiteral(): void {
        // Antes era imposible: el % se interpretaba como comodín.
        $this->db->prepare("
            INSERT INTO logs_sistema (usuario_id, accion, modulo, descripcion)
            VALUES (?, 'Prueba', 'Búsqueda', 'Cumplimiento del 50% en la ficha')
        ")->execute([$this->idCoordinador()]);

        $modelo = new \Core\Models\LogsModel($this->db);
        $conLiteral = $modelo->listar(['search' => '50%'], 25, 0);
        $sinRelacion = $modelo->listar(['search' => '99%'], 25, 0);

        $this->assertNotEmpty($conLiteral, 'no encuentra el porcentaje literal');
        $this->assertCount(0, $sinRelacion, 'el % sigue actuando como comodín');
    }

    #[TestDox('escaparLike protege los tres caracteres especiales')]
    public function testEscaparLike(): void {
        $this->assertSame('\\%', Validador::escaparLike('%'));
        $this->assertSame('\\_', Validador::escaparLike('_'));
        $this->assertSame('\\\\', Validador::escaparLike('\\'));
        // La barra se escapa primero, o escaparía a los escapes añadidos.
        $this->assertSame('\\\\\\%', Validador::escaparLike('\\%'));
        $this->assertSame('Muñoz', Validador::escaparLike('Muñoz'), 'no debe tocar el texto normal');
    }

    #[TestDox('busqueda() recorta, limpia y escapa en un paso')]
    public function testMetodoBusqueda(): void {
        $v = new Validador(['search' => '  <b>50%</b>  ']);
        $this->assertSame('50\\%', $v->busqueda());

        $largo = new Validador(['search' => str_repeat('a', 500)]);
        $this->assertSame(100, mb_strlen($largo->busqueda('search', 100)));

        $this->assertSame('', (new Validador([]))->busqueda());
    }

    // =================================================================
    // FILTROS MANIPULADOS
    // =================================================================

    /**
     * Ignorar un filtro desconocido no es "tolerante": es mostrar más de lo
     * que la pantalla ofrece a quien manipule la URL.
     */
    #[DataProvider('filtrosManipulados')]
    #[TestDox('un filtro con valor inventado no devuelve nada, en vez de todo')]
    public function testFiltroManipuladoNoDevuelveTodo(string $campo, string $valor): void {
        $modelo = new UsuarioModel($this->db);
        $total = $modelo->contar([]);

        $resultado = $modelo->contar([$campo => $valor]);

        $this->assertSame(0, $resultado, "el filtro $campo=«$valor» se ignoró y mostró $total usuarios");
    }

    public static function filtrosManipulados(): array {
        return [
            'rol inventado'      => ['rol', 'superadministrador'],
            'rol con inyección'  => ['rol', "coordinador' OR '1'='1"],
            'rol vacío con espacios' => ['rol', '   '],
            'estado inventado'   => ['estado', 'suspendido_para_siempre'],
            'estado con carga'   => ['estado', "activo' OR 1=1--"],
        ];
    }

    #[TestDox('los filtros válidos siguen funcionando')]
    public function testFiltrosValidos(): void {
        $modelo = new UsuarioModel($this->db);

        foreach (Enums::USUARIO_ROL as $rol) {
            $conFiltro = $modelo->contar(['rol' => $rol]);
            $enBase = $this->contar('usuarios', 'rol = ?', [$rol]);
            $this->assertSame($enBase, $conFiltro, "el filtro de rol '$rol' no cuadra");
        }
    }

    #[TestDox('sin filtro se ven todos: el filtro vacío no restringe')]
    public function testSinFiltro(): void {
        $modelo = new UsuarioModel($this->db);
        $this->assertSame(
            $this->contar('usuarios'),
            $modelo->contar(['rol' => '', 'estado' => ''])
        );
    }

    /**
     * El total que calcula el paginador y las filas que devuelve el listado
     * tienen que salir del mismo WHERE; si no, la última página aparece
     * vacía o se pierden filas.
     */
    #[TestDox('el conteo y el listado usan exactamente el mismo filtro')]
    public function testConteoYListadoCoinciden(): void {
        $modelo = new UsuarioModel($this->db);

        foreach ([[], ['rol' => 'instructor'], ['search' => 'a'], ['rol' => 'aprendiz', 'estado' => 'activo']] as $f) {
            $total = $modelo->contar($f);
            $filas = $modelo->paraExportar($f, 100000);
            $this->assertCount($total, $filas, 'el conteo no cuadra con el listado: ' . json_encode($f));
        }
    }
}
