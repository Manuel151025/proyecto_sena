<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Services\AsignacionesService;
use Core\Services\CompetenciasService;
use Core\Services\FichasService;
use Core\Services\MatriculasService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Competencias y resultados de aprendizaje (CompetenciasService).
 *
 * Cada prueba arma su propio programa con una ficha y un aprendiz en
 * formación, para no depender de cómo estén los datos de la base: el
 * líder de la ficha y el instructor de seguimiento del aprendiz son
 * personas distintas, porque de eso dependen varias reglas.
 */
final class EstructuraCurricularTest extends CasoConBaseDeDatos {

    private CompetenciasService $s;
    private int $programa;
    private int $ficha;
    private int $aprendiz;
    private int $lider;
    private int $seguimiento;

    protected function setUp(): void {
        parent::setUp();
        $this->s = new CompetenciasService($this->db);
        $instructores = $this->db->query("SELECT id FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' ORDER BY id LIMIT 2")
                                 ->fetchAll(\PDO::FETCH_COLUMN);
        if (count($instructores) < 2) {
            $this->markTestSkipped('Se necesitan dos instructores activos.');
        }
        [$this->lider, $this->seguimiento] = array_map('intval', $instructores);

        $this->db->exec("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES ('PROGRAMA DE PRUEBA QA', 'QA-PROG-1', 1000, 'activo')");
        $this->programa = (int)$this->db->lastInsertId();
        $this->ficha = (new FichasService($this->db))->crear([
            'numero_ficha' => '9999777', 'programa_id' => $this->programa, 'proyecto_id' => null, 'instructor_id' => $this->lider,
            'estado' => 'ejecucion', 'fecha_inicio' => null, 'fecha_fin' => null,
        ], $this->coordinador());
        $this->aprendiz = (new MatriculasService($this->db))->matricular([
            'nombre' => 'APRENDIZ ESTRUCTURA QA', 'email' => 'estructura.qa@example.com', 'tipo_documento' => 'CC',
            'numero_documento' => '99990000555', 'ficha_id' => $this->ficha, 'genero' => 'O', 'fecha_nacimiento' => null,
            'telefono' => '', 'ciudad' => '', 'instructor_seguimiento_id' => $this->seguimiento, 'estado' => 'matriculado',
        ], $this->coordinador())['id'];
    }

    private function coordinador(): Actor {
        return new Actor($this->idCoordinador(), ROL_COORDINADOR);
    }

    private function competencia(array $cambios = []): array {
        return array_merge(['programa_id' => $this->programa, 'codigo' => '220501046', 'nombre' => 'UTILIZAR HERRAMIENTAS INFORMÁTICAS',
                            'descripcion' => '', 'horas' => 40, 'estado' => 'activo', 'es_etapa_practica' => false], $cambios);
    }

    /** Competencia con un RAP: el aprendiz queda con un pendiente. @return array{0:int,1:int} [competencia, rap] */
    private function competenciaConRap(array $cambios = []): array {
        $c = $this->s->crearCompetencia($this->competencia($cambios), $this->coordinador());
        $r = $this->s->crearRap(['competencia_id' => $c, 'codigo' => 'QA-9001-01', 'denominacion' => 'CONFIGURAR EL EQUIPO'], $this->coordinador());
        return [$c, $r['id']];
    }

    /** Instructor que responde por la evaluación del aprendiz en ese RAP. */
    private function responsable(int $rap): int {
        $st = $this->db->prepare("SELECT instructor_id FROM evaluaciones WHERE aprendiz_id = ? AND resultado_aprendizaje_id = ?");
        $st->execute([$this->aprendiz, $rap]);
        return (int)$st->fetchColumn();
    }

    private function esperarError(callable $op, string $contiene): void {
        try {
            $op();
            $this->fail('se esperaba un ErrorDeNegocio');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsStringIgnoringCase($contiene, $e->getMessage());
        }
    }

    // =================================================================
    // COMPETENCIAS
    // =================================================================

    #[TestDox('solo la coordinación modifica la estructura curricular')]
    public function testSoloCoordinacion(): void {
        $this->esperarError(fn() => $this->s->crearCompetencia($this->competencia(), new Actor($this->lider, ROL_INSTRUCTOR)), 'coordinación');
    }

    #[TestDox('el código de una competencia se repite entre programas (transversales) pero no dentro de uno')]
    public function testCodigoTransversal(): void {
        $this->s->crearCompetencia($this->competencia(), $this->coordinador());
        $this->esperarError(fn() => $this->s->crearCompetencia($this->competencia(), $this->coordinador()), 'ya tiene una competencia');

        $this->db->exec("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES ('OTRO PROGRAMA QA', 'QA-PROG-2', 1000, 'activo')");
        $otro = (int)$this->db->lastInsertId();
        $this->assertGreaterThan(0, $this->s->crearCompetencia($this->competencia(['programa_id' => $otro]), $this->coordinador()));
    }

    #[TestDox('crear un RAP le habilita un pendiente a cada aprendiz del programa, a cargo del líder')]
    public function testCrearRapHabilitaPendientes(): void {
        $c = $this->s->crearCompetencia($this->competencia(), $this->coordinador());
        $r = $this->s->crearRap(['competencia_id' => $c, 'codigo' => 'QA-9001-01', 'denominacion' => 'CONFIGURAR EL EQUIPO'], $this->coordinador());

        $this->assertSame(1, $r['habilitadas']);
        $this->assertSame($this->lider, $this->responsable($r['id']));
    }

    /**
     * De la marca depende quién responde por las pendientes. Antes, al
     * cambiarla, las pendientes seguían a nombre del anterior: el panel de
     * coordinación y el calendario se las contaban a quien ya no califica.
     */
    #[TestDox('marcar una competencia como de etapa práctica pasa sus pendientes al instructor de seguimiento, y desmarcarla las devuelve')]
    public function testEtapaPracticaCambiaResponsable(): void {
        [$c, $rap] = $this->competenciaConRap();
        $this->assertSame($this->lider, $this->responsable($rap));

        $this->s->editarCompetencia($c, $this->competencia(['es_etapa_practica' => true]), $this->coordinador());
        $this->assertSame($this->seguimiento, $this->responsable($rap));

        $this->s->editarCompetencia($c, $this->competencia(['es_etapa_practica' => false]), $this->coordinador());
        $this->assertSame($this->lider, $this->responsable($rap));
    }

    #[TestDox('una competencia asignada en una ficha no se marca como de etapa práctica')]
    public function testEtapaPracticaConAsignaciones(): void {
        [$c, $rap] = $this->competenciaConRap();
        (new AsignacionesService($this->db))->asignar($this->ficha, $c, $this->seguimiento, $this->coordinador());

        $this->esperarError(fn() => $this->s->editarCompetencia($c, $this->competencia(['es_etapa_practica' => true]), $this->coordinador()), 'asignada');
        $this->assertSame(0, (int)$this->db->query("SELECT es_etapa_practica FROM competencias WHERE id = $c")->fetchColumn());
    }

    #[TestDox('una competencia con registros de evaluación no cambia de programa')]
    public function testProgramaConEvaluaciones(): void {
        [$c] = $this->competenciaConRap();
        $otro = (int)$this->db->query("SELECT id FROM programas WHERE id <> {$this->programa} LIMIT 1")->fetchColumn();

        $this->esperarError(fn() => $this->s->editarCompetencia($c, $this->competencia(['programa_id' => $otro]), $this->coordinador()), 'registros de evaluación');
    }

    #[TestDox('una competencia asignada en una ficha no cambia de programa, aunque aún no tenga RAP')]
    public function testProgramaConAsignaciones(): void {
        $c = $this->s->crearCompetencia($this->competencia(), $this->coordinador());
        (new AsignacionesService($this->db))->asignar($this->ficha, $c, $this->seguimiento, $this->coordinador());
        $otro = (int)$this->db->query("SELECT id FROM programas WHERE id <> {$this->programa} LIMIT 1")->fetchColumn();

        $this->esperarError(fn() => $this->s->editarCompetencia($c, $this->competencia(['programa_id' => $otro]), $this->coordinador()), 'asignada');
        $this->assertSame($this->programa, (int)$this->db->query("SELECT programa_id FROM competencias WHERE id = $c")->fetchColumn());
    }

    #[TestDox('una competencia con resultados de aprendizaje no se elimina')]
    public function testEliminarCompetenciaConRap(): void {
        [$c] = $this->competenciaConRap();
        $this->esperarError(fn() => $this->s->eliminarCompetencia($c, $this->coordinador()), 'resultado(s) de aprendizaje');
    }

    // =================================================================
    // RESULTADOS DE APRENDIZAJE
    // =================================================================

    #[TestDox('el código de un RAP no se repite')]
    public function testRapDuplicado(): void {
        [$c] = $this->competenciaConRap();
        $this->esperarError(fn() => $this->s->crearRap(['competencia_id' => $c, 'codigo' => 'QA-9001-01', 'denominacion' => 'OTRO RESULTADO'],
            $this->coordinador()), 'ya existe');
    }

    #[TestDox('un RAP con registros de evaluación no se mueve a otra competencia')]
    public function testMoverRapConEvaluaciones(): void {
        [, $rap] = $this->competenciaConRap();
        $otra = $this->s->crearCompetencia($this->competencia(['codigo' => '240201500', 'nombre' => 'PROMOVER LA INTERACCIÓN IDÓNEA']), $this->coordinador());

        $this->esperarError(fn() => $this->s->editarRap($rap, ['competencia_id' => $otra, 'codigo' => 'QA-9001-01', 'denominacion' => 'CONFIGURAR EL EQUIPO'],
            $this->coordinador()), 'registros de evaluación');
    }

    #[TestDox('eliminar un RAP sin juicios quita también sus pendientes')]
    public function testEliminarRapSinJuicios(): void {
        [, $rap] = $this->competenciaConRap();
        $this->s->eliminarRap($rap, $this->coordinador());

        $this->assertSame(0, $this->contar('resultados_aprendizaje', 'id = ?', [$rap]));
        $this->assertSame(0, $this->contar('evaluaciones', 'resultado_aprendizaje_id = ?', [$rap]));
    }

    #[TestDox('un RAP con juicios emitidos no se elimina: es historial académico')]
    public function testEliminarRapConJuicio(): void {
        [, $rap] = $this->competenciaConRap();
        $this->db->exec("UPDATE evaluaciones SET concepto = 'A', fecha_evaluacion = CURDATE() WHERE resultado_aprendizaje_id = $rap");

        $this->esperarError(fn() => $this->s->eliminarRap($rap, $this->coordinador()), 'juicio');
        $this->assertSame(1, $this->contar('resultados_aprendizaje', 'id = ?', [$rap]));
    }

    /**
     * En etapa práctica el líder no califica. Al quitarle el instructor de
     * seguimiento al aprendiz, el plan pasaba al líder (la regla de las
     * pendientes cae en él), y el líder podía cerrarlo y aprobar el RAP.
     */
    #[TestDox('el plan de un RAP de etapa práctica nunca pasa al líder, que no la califica')]
    public function testPlanDeEtapaPracticaNoPasaAlLider(): void {
        [, $rap] = $this->competenciaConRap(['es_etapa_practica' => true]);
        $eval = (int)$this->db->query("SELECT id FROM evaluaciones WHERE aprendiz_id = {$this->aprendiz} AND resultado_aprendizaje_id = $rap")->fetchColumn();
        (new \Core\Services\JuiciosService($this->db))->calificar(['evaluacion_id' => $eval, 'concepto' => 'D',
            'comentario' => 'Falta la bitácora de la etapa práctica', 'motivo' => ''], new Actor($this->seguimiento, ROL_INSTRUCTOR));
        $planes = new \Core\Services\PlanesService($this->db);
        $plan = $planes->crear(['evaluacion_id' => $eval, 'actividades' => 'Completar la bitácora',
            'fecha_inicio' => date('Y-m-d'), 'fecha_limite' => date('Y-m-d', strtotime('+10 days'))], $this->coordinador());
        $responsable = fn() => (int)$this->db->query("SELECT instructor_id FROM planes_mejoramiento WHERE id = $plan")->fetchColumn();
        $this->assertSame($this->seguimiento, $responsable());

        $this->db->exec("UPDATE aprendices SET instructor_seguimiento_id = NULL WHERE id = {$this->aprendiz}");
        (new \Core\Services\EvaluacionesSyncService($this->db))->actualizarResponsables(['aprendiz_id' => $this->aprendiz]);
        $this->assertNotSame($this->lider, $responsable());

        $this->esperarError(fn() => $planes->cerrar(['id' => $plan, 'resultado' => 'cumplido', 'observaciones' => 'Cierre del líder'],
            new Actor($this->lider, ROL_INSTRUCTOR)), 'No calificas');
        $this->assertSame('D', (string)$this->db->query("SELECT concepto FROM evaluaciones WHERE id = $eval")->fetchColumn());
    }

    #[TestDox('una competencia movida a otro programa llega a los aprendices de ese programa')]
    public function testCompetenciaMovidaLlegaALosAprendices(): void {
        $this->db->exec("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES ('PROGRAMA SIN FICHAS QA', 'QA-PROG-3', 100, 'activo')");
        $sinFichas = (int)$this->db->lastInsertId();
        [$c, $rap] = $this->competenciaConRap(['programa_id' => $sinFichas]);
        $this->assertSame(0, $this->contar('evaluaciones', 'resultado_aprendizaje_id = ?', [$rap]));

        $this->s->editarCompetencia($c, $this->competencia(), $this->coordinador());
        $this->assertSame(1, $this->contar('evaluaciones', "aprendiz_id = ? AND resultado_aprendizaje_id = ? AND concepto = 'pendiente'",
            [$this->aprendiz, $rap]));
    }
}
