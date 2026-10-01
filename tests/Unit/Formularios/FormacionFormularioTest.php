<?php
declare(strict_types=1);

namespace Tests\Unit\Formularios;

use Core\Formularios\ActividadFormulario;
use Core\Formularios\CompetenciaFormulario;
use Core\Formularios\FaseFormulario;
use Core\Formularios\FichaFormulario;
use Core\Formularios\ProgramaFormulario;
use Core\Formularios\ProyectoFormulario;
use Core\Support\Validador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Formularios de la estructura de la formación (RF01, RF02): programa,
 * competencia, resultado de aprendizaje, proyecto formativo, fase, actividad
 * y ficha. Normalizan los códigos y acotan cada número y cada fecha.
 */
final class FormacionFormularioTest extends CasoDePrueba {

    /**
     * Ejecuta un formulario y devuelve [datos, errores].
     * @return array{0: array, 1: list<string>}
     */
    private static function con(callable $formulario, array $datos): array {
        $v = new Validador($datos);
        return [$formulario($v), $v->errores()];
    }

    // -----------------------------------------------------------------
    // PROGRAMA, COMPETENCIA Y RAP
    // -----------------------------------------------------------------

    #[TestDox('programa válido: código en mayúsculas, estado por defecto activo')]
    public function testProgramaValido(): void {
        [$d, $e] = self::con([ProgramaFormulario::class, 'validar'],
            ['nombre' => 'Análisis y Desarrollo de Software', 'codigo' => ' adso-228118 ', 'duracion_horas' => '3984']);
        $this->assertSame([], $e);
        $this->assertSame('ADSO-228118', $d['codigo']);
        $this->assertSame(3984, $d['duracion_horas']);
        $this->assertSame('activo', $d['estado']);
    }

    #[DataProvider('programasInvalidos')]
    #[TestDox('rechaza el programa: $_dataName')]
    public function testProgramaInvalido(array $datos): void {
        [, $e] = self::con([ProgramaFormulario::class, 'validar'],
            array_merge(['nombre' => 'Contabilidad', 'codigo' => 'CONT', 'duracion_horas' => '2640'], $datos));
        $this->assertNotSame([], $e);
    }

    public static function programasInvalidos(): array {
        return [
            'sin código'                => [['codigo' => '']],
            'código con espacios'       => [['codigo' => 'CON T']],
            'código de 31 caracteres'   => [['codigo' => str_repeat('A', 31)]],
            'horas en cero'             => [['duracion_horas' => '0']],
            'horas sobre el tope'       => [['duracion_horas' => '20001']],
            'horas con decimales'       => [['duracion_horas' => '12.5']],
            'horas con texto'           => [['duracion_horas' => '100 horas']],
            'estado inventado'          => [['estado' => 'eliminado']],
            'nombre de dos letras'      => [['nombre' => 'Co']],
        ];
    }

    #[TestDox('competencia: nombre en mayúsculas, código normalizado y marca de etapa práctica')]
    public function testCompetencia(): void {
        [$d, $e] = self::con([CompetenciaFormulario::class, 'validar'], ['programa_id' => '1', 'codigo' => '220501094',
            'nombre' => 'Establecer requisitos de la solución', 'horas' => '48', 'es_etapa_practica' => '1']);
        $this->assertSame([], $e);
        $this->assertSame('ESTABLECER REQUISITOS DE LA SOLUCIÓN', $d['nombre']);
        $this->assertSame(1, $d['es_etapa_practica']);
        [$d] = self::con([CompetenciaFormulario::class, 'validar'], ['programa_id' => '1', 'codigo' => '220501095',
            'nombre' => 'Evaluar requisitos', 'horas' => '48']);
        $this->assertSame(0, $d['es_etapa_practica'], 'sin marcar no es etapa práctica');
    }

    #[TestDox('competencia: exige programa y horas dentro de 1-5000')]
    public function testCompetenciaInvalida(): void {
        foreach ([['programa_id' => ''], ['horas' => '0'], ['horas' => '5001'], ['nombre' => 'Abcd']] as $cambio) {
            [, $e] = self::con([CompetenciaFormulario::class, 'validar'], array_merge(['programa_id' => '1', 'codigo' => '220501096',
                'nombre' => 'Desarrollar la solución de software', 'horas' => '96'], $cambio));
            $this->assertNotSame([], $e, json_encode($cambio));
        }
    }

    #[TestDox('resultado de aprendizaje: denominación en mayúsculas y sin etiquetas')]
    public function testRap(): void {
        [$d, $e] = self::con([CompetenciaFormulario::class, 'validarRap'],
            ['competencia_id' => '4', 'codigo' => '220501094-01', 'denominacion' => 'Caracterizar los <b>procesos</b> de la organización']);
        $this->assertSame([], $e);
        $this->assertSame('220501094-01', $d['codigo']);
        $this->assertSame('CARACTERIZAR LOS PROCESOS DE LA ORGANIZACIÓN', $d['denominacion']);
        [, $e] = self::con([CompetenciaFormulario::class, 'validarRap'], ['competencia_id' => '4', 'codigo' => '01', 'denominacion' => 'Hola']);
        $this->assertNotSame([], $e, 'una denominación de 4 caracteres no describe un resultado');
    }

    // -----------------------------------------------------------------
    // PROYECTO, FASE Y ACTIVIDAD
    // -----------------------------------------------------------------

    #[TestDox('proyecto: al crear siempre nace activo aunque se envíe otro estado')]
    public function testProyecto(): void {
        [$d, $e] = self::con([ProyectoFormulario::class, 'validar'],
            ['nombre' => 'Sistema de inventarios', 'codigo' => 'pf-adso-01', 'estado' => 'finalizado']);
        $this->assertSame([], $e);
        $this->assertSame('PF-ADSO-01', $d['codigo']);
        $this->assertSame('activo', $d['estado']);
        [$d] = self::con(fn($v) => ProyectoFormulario::validar($v, true),
            ['nombre' => 'Sistema de inventarios', 'codigo' => 'PF-ADSO-01', 'estado' => 'finalizado']);
        $this->assertSame('finalizado', $d['estado'], 'al editar sí se cambia');
    }

    #[DataProvider('fases')]
    #[TestDox('fase: $_dataName')]
    public function testFase(array $datos, bool $valida): void {
        [, $e] = self::con([FaseFormulario::class, 'validar'],
            array_merge(['numero_fase' => '2', 'nombre' => 'Planeación', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-06-30'], $datos));
        $valida ? $this->assertSame([], $e) : $this->assertNotSame([], $e);
    }

    public static function fases(): array {
        return [
            'válida'                       => [[], true],
            'sin fechas'                   => [['fecha_inicio' => '', 'fecha_fin' => ''], true],
            'fin antes del inicio'         => [['fecha_inicio' => '2026-06-30', 'fecha_fin' => '2026-03-01'], false],
            'mismo día'                    => [['fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-01'], true],
            'fase 0'                       => [['numero_fase' => '0'], false],
            'fase 21'                      => [['numero_fase' => '21'], false],
            'fecha imposible'              => [['fecha_fin' => '2026-02-30'], false],
            'fecha fuera de rango'         => [['fecha_inicio' => '1999-12-31'], false],
            'estado inventado'             => [['estado' => 'pausada'], false],
        ];
    }

    /**
     * El avance tiene que ser coherente con el estado: una actividad
     * completada es el 100 % y una pendiente el 0 %, diga lo que diga el
     * formulario; las demás se acotan a 0-100.
     */
    #[DataProvider('avances')]
    #[TestDox('el avance de la actividad es coherente con su estado: $_dataName')]
    public function testAvanceCoherente(string $estado, string $pct, float $esperado): void {
        [$d, $e] = self::con([ActividadFormulario::class, 'validarAvance'], ['estado' => $estado, 'cumplimiento_porcentaje' => $pct]);
        $this->assertSame([], $e);
        $this->assertSame($esperado, $d['cumplimiento_porcentaje']);
    }

    public static function avances(): array {
        return [
            'completada con 40'  => ['completada', '40', 100.0],
            'pendiente con 70'   => ['pendiente', '70', 0.0],
            'en progreso con 45' => ['en_progreso', '45.456', 45.46],
            'cancelada con 30'   => ['cancelada', '30', 30.0],
        ];
    }

    #[TestDox('el avance fuera de 0-100 o un estado inventado se rechazan')]
    public function testAvanceInvalido(): void {
        foreach ([['en_progreso', '101'], ['en_progreso', '-1'], ['terminada', '50'], ['en_progreso', 'mucho']] as [$estado, $pct]) {
            [, $e] = self::con([ActividadFormulario::class, 'validarAvance'], ['estado' => $estado, 'cumplimiento_porcentaje' => $pct]);
            $this->assertNotSame([], $e, "$estado $pct");
        }
    }

    #[TestDox('actividad: fase y competencia opcionales, responsable obligatorio, fecha límite no anterior al inicio')]
    public function testActividad(): void {
        $base = ['ficha_id' => '1', 'nombre' => 'Levantamiento de requisitos', 'responsable_id' => '2',
                 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-15'];
        [$d, $e] = self::con([ActividadFormulario::class, 'validar'], $base);
        $this->assertSame([], $e);
        $this->assertNull($d['fase_id']);
        $this->assertNull($d['competencia_id']);
        $this->assertSame('pendiente', $d['estado']);
        $this->assertSame(0.0, $d['cumplimiento_porcentaje']);

        [, $e] = self::con([ActividadFormulario::class, 'validar'], array_merge($base, ['fecha_fin' => '2026-03-30']));
        $this->assertStringContainsString('fecha límite', implode(' ', $e));
        [, $e] = self::con([ActividadFormulario::class, 'validar'], array_merge($base, ['responsable_id' => '']));
        $this->assertNotSame([], $e);
    }

    // -----------------------------------------------------------------
    // FICHA
    // -----------------------------------------------------------------

    #[DataProvider('fichas')]
    #[TestDox('ficha: $_dataName')]
    public function testFicha(array $datos, bool $valida): void {
        [$d, $e] = self::con([FichaFormulario::class, 'validar'],
            array_merge(['numero_ficha' => '2845671', 'programa_id' => '1', 'instructor_id' => '2'], $datos));
        $valida ? $this->assertSame([], $e) : $this->assertNotSame([], $e);
        if ($valida && !isset($datos['estado'])) {
            $this->assertSame('planeacion', $d['estado']);
        }
    }

    public static function fichas(): array {
        return [
            'válida'                    => [[], true],
            'de convenio con guion'     => [['numero_ficha' => 'CONV-2026-01'], true],
            'número de dos dígitos'     => [['numero_ficha' => '12'], false],
            'número de 21 caracteres'   => [['numero_ficha' => str_repeat('9', 21)], false],
            'número con espacio'        => [['numero_ficha' => '2845 671'], false],
            'sin programa'              => [['programa_id' => ''], false],
            'sin líder'                 => [['instructor_id' => '0'], false],
            'líder no numérico'         => [['instructor_id' => '2abc'], false],
            'estado de la lista'        => [['estado' => 'ejecucion'], true],
            'estado inventado'          => [['estado' => 'activa'], false],
            'fin antes del inicio'      => [['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-01-01'], false],
        ];
    }

    #[TestDox('el proyecto de la ficha es opcional: vacío o «0» quedan como sin proyecto')]
    public function testFichaSinProyecto(): void {
        foreach (['', '0'] as $sin) {
            [$d, $e] = self::con([FichaFormulario::class, 'validar'],
                ['numero_ficha' => '2845671', 'programa_id' => '1', 'instructor_id' => '2', 'proyecto_id' => $sin]);
            $this->assertSame([], $e);
            $this->assertNull($d['proyecto_id']);
        }
    }
}
