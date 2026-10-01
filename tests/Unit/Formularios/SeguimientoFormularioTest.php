<?php
declare(strict_types=1);

namespace Tests\Unit\Formularios;

use Core\Formularios\EvidenciaFormulario;
use Core\Formularios\JuicioFormulario;
use Core\Formularios\PlanFormulario;
use Core\Formularios\RetroalimentacionFormulario;
use Core\Support\Validador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoDePrueba;

/**
 * Formularios del seguimiento (RF03, RF04): el juicio de un RAP, el plan de
 * mejoramiento, la retroalimentación y la evidencia. Las reglas de permiso
 * viven en los servicios; aquí se prueba que lo que llega esté bien formado.
 */
final class SeguimientoFormularioTest extends CasoDePrueba {

    /** @return array{0: array, 1: list<string>} */
    private static function con(callable $formulario, array $datos): array {
        $v = new Validador($datos);
        return [$formulario($v), $v->errores()];
    }

    // -----------------------------------------------------------------
    // JUICIO
    // -----------------------------------------------------------------

    #[DataProvider('conceptos')]
    #[TestDox('el juicio solo admite A o D: $_dataName')]
    public function testConcepto(string $concepto, bool $valido): void {
        [, $e] = self::con([JuicioFormulario::class, 'validar'], ['evaluacion_id' => '10', 'concepto' => $concepto]);
        $valido ? $this->assertSame([], $e) : $this->assertNotSame([], $e);
    }

    public static function conceptos(): array {
        return [
            'A'                          => ['A', true],
            'D'                          => ['D', true],
            'devolver a pendiente'       => ['pendiente', false],
            'minúscula'                  => ['a', false],
            'vacío'                      => ['', false],
            'nota numérica'              => ['4.5', false],
        ];
    }

    #[TestDox('comentario y motivo opcionales, acotados y sin etiquetas')]
    public function testComentarioYMotivo(): void {
        [$d, $e] = self::con([JuicioFormulario::class, 'validar'], ['evaluacion_id' => '10', 'concepto' => 'D',
            'comentario' => '  Falta el <script>diagrama</script> de procesos ', 'motivo' => 'Revisión del informe']);
        $this->assertSame([], $e);
        $this->assertSame('Falta el diagrama de procesos', $d['comentario']);
        $this->assertSame('Revisión del informe', $d['motivo']);

        [, $e] = self::con([JuicioFormulario::class, 'validar'], ['evaluacion_id' => '10', 'concepto' => 'D',
            'motivo' => str_repeat('m', JuicioFormulario::MAX_MOTIVO + 1)]);
        $this->assertNotSame([], $e, 'un motivo más largo que la columna del historial');
        [, $e] = self::con([JuicioFormulario::class, 'validar'], ['evaluacion_id' => 'x', 'concepto' => 'A']);
        $this->assertNotSame([], $e, 'la evaluación debe ser un identificador');
    }

    // -----------------------------------------------------------------
    // PLAN DE MEJORAMIENTO
    // -----------------------------------------------------------------

    private static function plan(array $cambios = []): array {
        return array_merge(['evaluacion_id' => '25', 'actividades' => 'Rehacer el informe y sustentarlo ante el instructor',
                            'fecha_inicio' => '2026-05-01', 'fecha_limite' => '2026-06-15'], $cambios);
    }

    #[DataProvider('plazos')]
    #[TestDox('el plazo del plan: $_dataName')]
    public function testPlazo(array $cambios, bool $valido): void {
        [, $e] = self::con([PlanFormulario::class, 'validarCreacion'], self::plan($cambios));
        $valido ? $this->assertSame([], $e) : $this->assertNotSame([], $e);
    }

    public static function plazos(): array {
        return [
            '45 días'                       => [[], true],
            'justo 180 días'                => [['fecha_limite' => '2026-10-28'], true],
            '181 días'                      => [['fecha_limite' => '2026-10-29'], false],
            'límite antes del inicio'       => [['fecha_limite' => '2026-04-30'], false],
            'sin fecha límite'              => [['fecha_limite' => ''], false],
            'actividades de 9 caracteres'   => [['actividades' => 'Corto ok.'], false],
            'actividades sobre el tope'     => [['actividades' => str_repeat('a', PlanFormulario::MAX_ACTIVIDADES + 1)], false],
            'sin RAP'                       => [['evaluacion_id' => ''], false],
        ];
    }

    #[TestDox('sin fecha de inicio el plan empieza hoy')]
    public function testInicioPorDefecto(): void {
        [$d, $e] = self::con([PlanFormulario::class, 'validarCreacion'],
            self::plan(['fecha_inicio' => '', 'fecha_limite' => date('Y-m-d', strtotime('+30 days'))]));
        $this->assertSame([], $e);
        $this->assertSame(date('Y-m-d'), $d['fecha_inicio']);
    }

    #[TestDox('el cierre solo es cumplido o no cumplido, con observaciones')]
    public function testCierre(): void {
        [$d, $e] = self::con([PlanFormulario::class, 'validarCierre'], ['id' => '7', 'resultado' => 'cumplido', 'observaciones' => 'Sustentó el informe']);
        $this->assertSame([], $e);
        $this->assertSame('cumplido', $d['resultado']);
        foreach ([['resultado' => 'abierto'], ['resultado' => ''], ['observaciones' => 'ok']] as $cambio) {
            [, $e] = self::con([PlanFormulario::class, 'validarCierre'],
                array_merge(['id' => '7', 'resultado' => 'no_cumplido', 'observaciones' => 'No entregó la evidencia'], $cambio));
            $this->assertNotSame([], $e, json_encode($cambio));
        }
    }

    // -----------------------------------------------------------------
    // RETROALIMENTACIÓN
    // -----------------------------------------------------------------

    #[TestDox('retroalimentación: tipo por defecto recomendación, privada como 0/1 y RAP opcional')]
    public function testRetroalimentacion(): void {
        [$d, $e] = self::con([RetroalimentacionFormulario::class, 'validar'],
            ['aprendiz_id' => '5', 'contenido' => 'Participa activamente en las sesiones']);
        $this->assertSame([], $e);
        $this->assertSame('recomendacion', $d['tipo']);
        $this->assertSame(0, $d['privada']);
        $this->assertNull($d['evaluacion_id']);

        [$d] = self::con([RetroalimentacionFormulario::class, 'validar'],
            ['aprendiz_id' => '5', 'tipo' => 'fortaleza', 'contenido' => 'Lidera el trabajo en equipo', 'privada' => '1', 'evaluacion_id' => '12']);
        $this->assertSame(1, $d['privada']);
        $this->assertSame(12, $d['evaluacion_id']);
    }

    #[TestDox('retroalimentación: contenido entre 10 y 2000 caracteres y tipo de la lista')]
    public function testRetroalimentacionInvalida(): void {
        foreach ([['contenido' => 'Bien hecho'], ['contenido' => 'Muy bien'], ['tipo' => 'regaño'],
                  ['contenido' => str_repeat('a', RetroalimentacionFormulario::MAX_CONTENIDO + 1)], ['aprendiz_id' => '']] as $cambio) {
            [, $e] = self::con([RetroalimentacionFormulario::class, 'validar'],
                array_merge(['aprendiz_id' => '5', 'contenido' => 'Participa activamente en las sesiones'], $cambio));
            if ($cambio === ['contenido' => 'Bien hecho']) {
                $this->assertSame([], $e, 'diez caracteres justos se aceptan');
                continue;
            }
            $this->assertNotSame([], $e, json_encode($cambio, JSON_UNESCAPED_UNICODE));
        }
    }

    // -----------------------------------------------------------------
    // EVIDENCIA
    // -----------------------------------------------------------------

    #[TestDox('envío de evidencia: título obligatorio, RAP opcional')]
    public function testEnvio(): void {
        [$d, $e] = self::con([EvidenciaFormulario::class, 'validarEnvio'], ['titulo' => 'Diagrama de casos de uso', 'evaluacion_id' => '0']);
        $this->assertSame([], $e);
        $this->assertNull($d['evaluacion_id'], '«0» en el selector es «sin RAP»');
        [, $e] = self::con([EvidenciaFormulario::class, 'validarEnvio'], ['titulo' => 'Ok']);
        $this->assertNotSame([], $e);
    }

    #[DataProvider('revisiones')]
    #[TestDox('revisión de evidencia: $_dataName')]
    public function testRevision(array $cambios, bool $valida, string $juicio = ''): void {
        [$d, $e] = self::con([EvidenciaFormulario::class, 'validarRevision'],
            array_merge(['id' => '3', 'estado' => 'aprobada', 'retroalimentacion' => 'Cumple los criterios'], $cambios));
        $valida ? $this->assertSame([], $e) : $this->assertNotSame([], $e);
        if ($valida) {
            $this->assertSame($juicio, $d['juicio']);
        }
    }

    public static function revisiones(): array {
        return [
            'aprobada sin tocar el juicio'   => [[], true, ''],
            'aprobada y juicio A'            => [['juicio' => 'A'], true, 'A'],
            'requiere ajustes y juicio D'    => [['estado' => 'revisada', 'juicio' => 'D'], true, 'D'],
            'devolver el juicio a pendiente' => [['juicio' => 'pendiente'], false],
            'decisión inventada'             => [['estado' => 'enviada'], false],
            'sin retroalimentación'          => [['retroalimentacion' => ''], false],
        ];
    }

    #[TestDox('las extensiones admitidas no incluyen nada ejecutable')]
    public function testExtensiones(): void {
        foreach (['php', 'phtml', 'phar', 'html', 'svg', 'js', 'exe', 'sh', 'bat'] as $peligrosa) {
            $this->assertNotContains($peligrosa, EvidenciaFormulario::EXTENSIONES);
        }
        $this->assertSame(10, EvidenciaFormulario::MAX_MB);
    }
}
