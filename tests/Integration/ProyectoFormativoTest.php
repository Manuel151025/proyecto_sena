<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Services\ActividadesService;
use Core\Services\FasesService;
use Core\Services\ProgramasService;
use Core\Services\ProyectosService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Reglas del proyecto formativo (RF02): proyecto → fases → actividades por
 * ficha, con el alcance de cada rol.
 *
 * Todo corre dentro de la transacción de la prueba y se revierte.
 */
final class ProyectoFormativoTest extends CasoConBaseDeDatos {

    private function coordinador(): Actor {
        return new Actor($this->idCoordinador(), ROL_COORDINADOR);
    }

    /** Ficha con proyecto, su líder y una fase del proyecto. */
    private function fichaConProyecto(): array {
        $f = $this->db->query("
            SELECT f.id, f.instructor_id, f.programa_id, f.proyecto_id,
                   (SELECT fp.id FROM fases_proyecto fp WHERE fp.proyecto_id = f.proyecto_id ORDER BY fp.numero_fase LIMIT 1) AS fase_id
              FROM fichas f
             WHERE f.proyecto_id IS NOT NULL
               AND EXISTS (SELECT 1 FROM fases_proyecto fp WHERE fp.proyecto_id = f.proyecto_id)
             LIMIT 1
        ")->fetch();
        if (!$f) {
            $this->markTestSkipped('no hay fichas con proyecto y fases');
        }
        return $f;
    }

    private function datosActividad(array $ficha, array $cambios = []): array {
        return array_merge([
            'ficha_id' => (int)$ficha['id'], 'fase_id' => (int)$ficha['fase_id'], 'competencia_id' => null,
            'nombre' => 'Actividad de prueba', 'descripcion' => '', 'fecha_inicio' => null, 'fecha_fin' => null,
            'responsable_id' => (int)$ficha['instructor_id'], 'estado' => 'pendiente', 'cumplimiento_porcentaje' => 0.0,
        ], $cambios);
    }

    #[TestDox('el instructor líder crea actividades en su ficha')]
    public function testLiderCreaActividad(): void {
        $ficha = $this->fichaConProyecto();
        $id = (new ActividadesService($this->db))->crear($this->datosActividad($ficha), new Actor((int)$ficha['instructor_id'], ROL_INSTRUCTOR));
        $this->assertGreaterThan(0, $id);
    }

    #[TestDox('un instructor ajeno no puede crear ni borrar actividades de la ficha')]
    public function testInstructorAjenoNoGestiona(): void {
        $ficha = $this->fichaConProyecto();
        $servicio = new ActividadesService($this->db);
        $id = $servicio->crear($this->datosActividad($ficha), $this->coordinador());
        $ajeno = new Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR);

        try {
            $servicio->eliminar($id, $ajeno);
            $this->fail('un instructor ajeno pudo borrar la actividad');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsString('no pertenece a tus fichas', $e->getMessage());
        }
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM actividades WHERE id = $id")->fetchColumn());

        $this->expectException(ErrorDeNegocio::class);
        $servicio->crear($this->datosActividad($ficha), $ajeno);
    }

    #[TestDox('la fase tiene que ser del proyecto de la ficha')]
    public function testFaseDeOtroProyecto(): void {
        $ficha = $this->fichaConProyecto();
        $otra = (int)$this->db->query("SELECT id FROM fases_proyecto WHERE proyecto_id <> {$ficha['proyecto_id']} LIMIT 1")->fetchColumn();
        if ($otra === 0) {
            $this->markTestSkipped('no hay fases de otro proyecto');
        }
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('no pertenece al proyecto formativo');
        (new ActividadesService($this->db))->crear($this->datosActividad($ficha, ['fase_id' => $otra]), $this->coordinador());
    }

    #[TestDox('en una ficha con proyecto la fase es obligatoria')]
    public function testFaseObligatoria(): void {
        $ficha = $this->fichaConProyecto();
        $this->expectException(ErrorDeNegocio::class);
        (new ActividadesService($this->db))->crear($this->datosActividad($ficha, ['fase_id' => null]), $this->coordinador());
    }

    #[TestDox('la competencia tiene que ser del programa de la ficha')]
    public function testCompetenciaDeOtroPrograma(): void {
        $ficha = $this->fichaConProyecto();
        $comp = (int)$this->db->query("SELECT id FROM competencias WHERE programa_id <> {$ficha['programa_id']} LIMIT 1")->fetchColumn();
        if ($comp === 0) {
            $this->markTestSkipped('no hay competencias de otro programa');
        }
        $this->expectException(ErrorDeNegocio::class);
        (new ActividadesService($this->db))->crear($this->datosActividad($ficha, ['competencia_id' => $comp]), $this->coordinador());
    }

    #[TestDox('estado y avance no se contradicen')]
    public function testCoherenciaEstadoAvance(): void {
        $v = new \Core\Support\Validador(['estado' => 'completada', 'cumplimiento_porcentaje' => '30']);
        $this->assertSame(100.0, \Core\Formularios\ActividadFormulario::validarAvance($v)['cumplimiento_porcentaje']);
        $v = new \Core\Support\Validador(['estado' => 'pendiente', 'cumplimiento_porcentaje' => '80']);
        $this->assertSame(0.0, \Core\Formularios\ActividadFormulario::validarAvance($v)['cumplimiento_porcentaje']);
        $v = new \Core\Support\Validador(['estado' => 'en_progreso', 'cumplimiento_porcentaje' => '140']);
        \Core\Formularios\ActividadFormulario::validarAvance($v);
        $this->assertTrue($v->hayErrores(), 'un avance de 140 % debe rechazarse');
    }

    #[TestDox('el avance de la fase se calcula con sus actividades')]
    public function testAvanceDeFaseCalculado(): void {
        $ficha = $this->fichaConProyecto();
        $s = new ActividadesService($this->db);
        $coord = $this->coordinador();
        $s->crear($this->datosActividad($ficha, ['estado' => 'completada', 'cumplimiento_porcentaje' => 100.0]), $coord);
        $s->crear($this->datosActividad($ficha, ['estado' => 'en_progreso', 'cumplimiento_porcentaje' => 50.0]), $coord);
        $s->crear($this->datosActividad($ficha, ['estado' => 'cancelada', 'cumplimiento_porcentaje' => 0.0]), $coord);

        $fases = (new \Core\Models\FasesModel($this->db))->listarDeProyecto((int)$ficha['proyecto_id'], [(int)$ficha['id']]);
        $fase = array_values(array_filter($fases, static fn($f) => (int)$f['id'] === (int)$ficha['fase_id']))[0];
        $previas = (int)$this->db->query("SELECT COUNT(*) FROM actividades WHERE fase_id = {$ficha['fase_id']} AND ficha_id = {$ficha['id']} AND estado <> 'cancelada'")->fetchColumn();
        $this->assertSame($previas, (int)$fase['total_actividades'], 'la cancelada no cuenta');
        if ($previas === 2) {
            $this->assertEqualsWithDelta(75.0, (float)$fase['avance'], 0.01);
        }
    }

    #[TestDox('solo coordinación borra fases, y no si tienen actividades')]
    public function testBorradoDeFases(): void {
        $ficha = $this->fichaConProyecto();
        $fases = new FasesService($this->db);
        try {
            $fases->eliminar((int)$ficha['fase_id'], new Actor((int)$ficha['instructor_id'], ROL_INSTRUCTOR));
            $this->fail('un instructor pudo borrar una fase');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsString('Solo la coordinación', $e->getMessage());
        }
        (new ActividadesService($this->db))->crear($this->datosActividad($ficha), $this->coordinador());
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('tiene');
        $fases->eliminar((int)$ficha['fase_id'], $this->coordinador());
    }

    #[TestDox('un instructor no crea fases en proyectos ajenos')]
    public function testFaseEnProyectoAjeno(): void {
        $ficha = $this->fichaConProyecto();
        $this->expectException(ErrorDeNegocio::class);
        (new FasesService($this->db))->crear((int)$ficha['proyecto_id'], [
            'numero_fase' => 19, 'nombre' => 'Intrusa', 'descripcion' => '', 'fecha_inicio' => null, 'fecha_fin' => null, 'estado' => 'planeada',
        ], new Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR));
    }

    #[TestDox('no se borra un programa ni un proyecto en uso, y se explica por qué')]
    public function testBorradoProtegido(): void {
        $ficha = $this->fichaConProyecto();
        try {
            (new ProgramasService($this->db))->eliminar((int)$ficha['programa_id'], $this->coordinador());
            $this->fail('se borró un programa con fichas');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsString('ficha(s)', $e->getMessage());
        }
        try {
            (new ProyectosService($this->db))->eliminar((int)$ficha['proyecto_id'], $this->coordinador());
            $this->fail('se borró un proyecto con fichas');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsString('desarrollan este proyecto', $e->getMessage());
        }
    }

    #[TestDox('código de proyecto duplicado da un mensaje de negocio, no un error de base')]
    public function testCodigoDuplicado(): void {
        $codigo = (string)$this->db->query("SELECT codigo FROM proyectos LIMIT 1")->fetchColumn();
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage("Ya existe un proyecto con el código $codigo");
        (new ProyectosService($this->db))->crear(['nombre' => 'Otro', 'codigo' => $codigo, 'objetivo' => '', 'descripcion' => '', 'estado' => 'activo'], $this->coordinador());
    }
}
