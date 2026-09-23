<?php
declare(strict_types=1);

namespace Tests\Security;

use Core\Models\EvaluacionesModel;
use Core\Models\EvidenciasModel;
use Core\Models\MejoramientoModel;
use Core\Models\NotificacionesModel;
use Core\Models\ReportesModel;
use Core\Models\SeguimientoModel;
use Core\Services\InstructorAccessService;
use Core\Support\ErrorDeNegocio;
use PDO;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Escalada de privilegios y acceso a datos ajenos.
 *
 * Son las dos formas en que este sistema puede fallar de verdad: que un
 * instructor califique a un aprendiz que no le corresponde, o que un
 * aprendiz vea las notas de otro. El identificador siempre viaja en la
 * petición, así que la pregunta en cada caso es "¿y si envío otro número?".
 */
final class AutorizacionTest extends CasoConBaseDeDatos {

    // =================================================================
    // AUTORIDAD DEL INSTRUCTOR SOBRE UNA EVALUACIÓN
    // =================================================================

    #[TestDox('un instructor ajeno no tiene autoridad sobre una evaluación')]
    public function testInstructorAjenoSinAutoridad(): void {
        $acceso = new InstructorAccessService($this->db);
        $ajeno  = $this->idInstructorAjeno();

        $evaluaciones = $this->db->query("SELECT id FROM evaluaciones LIMIT 25")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotEmpty($evaluaciones);

        foreach ($evaluaciones as $id) {
            $this->assertFalse(
                $acceso->tieneAccesoEvaluacion((int)$id, $ajeno),
                "el instructor #$ajeno alcanza la evaluación #$id sin relación con ella"
            );
        }
    }

    #[TestDox('el instructor líder sí tiene autoridad sobre su ficha')]
    public function testInstructorLiderConAutoridad(): void {
        $acceso = new InstructorAccessService($this->db);

        // Una evaluación de una ficha cuya competencia no es de etapa
        // práctica y no está asignada a nadie: la cubre el líder.
        $fila = $this->db->query("
            SELECT e.id, f.instructor_id
              FROM evaluaciones e
              JOIN fichas f ON f.id = e.ficha_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c ON c.id = ra.competencia_id
             WHERE c.es_etapa_practica = 0
               AND NOT EXISTS (SELECT 1 FROM asignaciones a
                                WHERE a.ficha_id = f.id AND a.competencia_id = c.id)
             LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $this->markTestSkipped('no hay una evaluación que ejemplifique este caso');
        }

        $this->assertTrue(
            $acceso->tieneAccesoEvaluacion((int)$fila['id'], (int)$fila['instructor_id']),
            'el instructor líder debería poder calificar lo que nadie tiene asignado'
        );
    }

    /**
     * La regla de la etapa práctica: ahí manda el instructor de seguimiento
     * del aprendiz, no el líder de la ficha. Es la rama que dependía de un
     * `LIKE` sobre el nombre de la competencia.
     */
    #[TestDox('en etapa práctica el líder de ficha pierde la autoridad')]
    public function testEtapaPracticaDesplazaAlLider(): void {
        $acceso = new InstructorAccessService($this->db);

        $fila = $this->db->query("
            SELECT e.id, f.instructor_id, ap.instructor_seguimiento_id
              FROM evaluaciones e
              JOIN fichas f ON f.id = e.ficha_id
              JOIN aprendices ap ON ap.id = e.aprendiz_id
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c ON c.id = ra.competencia_id
             WHERE c.es_etapa_practica = 1
               AND NOT EXISTS (SELECT 1 FROM asignaciones a
                                WHERE a.ficha_id = f.id AND a.competencia_id = c.id)
             LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $this->markTestSkipped('no hay evaluaciones de competencias de etapa práctica');
        }

        $lider = (int)$fila['instructor_id'];
        $seguimiento = (int)($fila['instructor_seguimiento_id'] ?? 0);

        $this->assertFalse(
            $acceso->tieneAccesoEvaluacion((int)$fila['id'], $lider),
            'el líder de ficha no debe calificar competencias de etapa práctica'
        );

        if ($seguimiento > 0) {
            $this->assertTrue(
                $acceso->tieneAccesoEvaluacion((int)$fila['id'], $seguimiento),
                'el instructor de seguimiento sí debe poder calificarlas'
            );
        }
    }

    #[TestDox('una evaluación inexistente no concede acceso')]
    public function testEvaluacionInexistente(): void {
        $acceso = new InstructorAccessService($this->db);
        foreach ([0, -1, 999999999] as $id) {
            $this->assertFalse($acceso->tieneAccesoEvaluacion($id, $this->idInstructorConFicha()));
        }
    }

    #[TestDox('un usuario que no existe no tiene acceso a nada')]
    public function testUsuarioInexistente(): void {
        $acceso = new InstructorAccessService($this->db);
        $this->assertFalse($acceso->tieneAccesoEvaluacion($this->idEvaluacion(), 999999999));
        $this->assertFalse($acceso->tieneAccesoAprendiz($this->idAprendiz(), 999999999));
    }

    // =================================================================
    // CALIFICAR UNA EVALUACIÓN AJENA
    // =================================================================

    /**
     * El camino real de /evaluaciones (acción «evaluar»): un instructor sin
     * autoridad sobre el RAP no puede escribir la nota, y la evaluación
     * queda como estaba.
     */
    #[TestDox('un instructor ajeno no puede calificar una evaluación')]
    public function testNoSeCalificaUnaEvaluacionAjena(): void {
        $servicio = new \Core\Services\JuiciosService($this->db);
        $ajeno = new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR);

        $ids = $this->db->query("SELECT id FROM evaluaciones LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $antes = $this->db->query("SELECT concepto FROM evaluaciones WHERE id = " . (int)$id)->fetchColumn();
            try {
                $servicio->calificar(['evaluacion_id' => (int)$id, 'concepto' => $antes === 'A' ? 'D' : 'A',
                                      'comentario' => '', 'motivo' => 'prueba'], $ajeno);
                $this->fail("un instructor ajeno calificó la evaluación #$id");
            } catch (\Core\Support\ErrorDeNegocio $e) {
                $this->assertStringContainsString('otro instructor', $e->getMessage());
            }
            $this->assertSame($antes, $this->db->query("SELECT concepto FROM evaluaciones WHERE id = " . (int)$id)->fetchColumn());
        }
    }

    #[TestDox('el aprendiz no puede calificar ni su propia evaluación')]
    public function testAprendizNoCalifica(): void {
        $this->expectException(\Core\Support\ErrorDeNegocio::class);
        (new \Core\Services\JuiciosService($this->db))->calificar(
            ['evaluacion_id' => $this->idEvaluacion(), 'concepto' => 'A', 'comentario' => '', 'motivo' => ''],
            new \Core\Support\Actor($this->idUsuarioAprendiz(), ROL_APRENDIZ));
    }

    #[TestDox('el coordinador sí puede calificar cualquier evaluación, y queda en el historial')]
    public function testCoordinadorCalifica(): void {
        $id = $this->idEvaluacion();
        $antes = $this->contar('historial_evaluaciones', 'evaluacion_id = ?', [$id]);
        $r = (new \Core\Services\JuiciosService($this->db))->calificar(
            ['evaluacion_id' => $id, 'concepto' => 'A', 'comentario' => 'Buen trabajo', 'motivo' => ''],
            new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR));
        $this->assertSame('actualizada', $r);
        $this->assertSame($antes + 1, $this->contar('historial_evaluaciones', 'evaluacion_id = ?', [$id]));
    }

    #[TestDox('el expediente no ofrece calificar a un instructor ajeno')]
    public function testPermisoDeSeguimientoConDatosCruzados(): void {
        $modelo = new SeguimientoModel($this->db);
        $competencias = $modelo->competencias($this->idAprendiz(), new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR));
        foreach ($competencias as $c) {
            foreach ($c['raps'] as $r) {
                $this->assertFalse($r['puede_calificar'], "el ajeno podría calificar {$r['ra_codigo']}");
            }
        }
    }

    #[TestDox('agregar retroalimentación exige relación con el aprendiz')]
    public function testRetroalimentacionExigeRelacion(): void {
        $this->expectException(\Core\Support\ErrorDeNegocio::class);
        (new \Core\Services\RetroalimentacionService($this->db))->registrar(
            ['aprendiz_id' => $this->idAprendiz(), 'tipo' => 'fortaleza', 'contenido' => 'Sin relación con el aprendiz', 'privada' => 0, 'evaluacion_id' => null],
            new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR));
    }

    // =================================================================
    // AISLAMIENTO DE LOS LISTADOS
    // =================================================================

    /**
     * No basta con que las acciones estén protegidas: un listado que
     * devuelva de más ya es una fuga, aunque no se pueda escribir.
     */
    #[TestDox('el listado de evaluaciones de un instructor no incluye las ajenas')]
    public function testListadoDeEvaluacionesFiltrado(): void {
        $modelo = new EvaluacionesModel($this->db);

        $total    = $modelo->contar(new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR), []);
        $delAjeno = $modelo->contar(new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR), []);

        $this->assertSame(0, $delAjeno, 'un instructor sin fichas ve evaluaciones que no le tocan');
        $this->assertGreaterThan(0, $total, 'el coordinador debería verlas todas');
    }

    #[TestDox('un aprendiz solo ve sus propias evaluaciones')]
    public function testAprendizSoloVeLasSuyas(): void {
        $modelo = new EvaluacionesModel($this->db);
        $usuario = $this->idUsuarioAprendiz();
        $apId = (int)$this->db->query("SELECT id FROM aprendices WHERE usuario_id = $usuario")->fetchColumn();
        $actor = new \Core\Support\Actor($usuario, ROL_APRENDIZ);

        foreach ($modelo->listar($actor, [], 100, 0) as $f) {
            $this->assertSame($apId, (int)$f['aprendiz_id'], 'el aprendiz ve una evaluación de otro');
        }
        // Ni filtrando por otra ficha sale de lo suyo.
        $otraFicha = (int)$this->db->query("SELECT id FROM fichas WHERE id <> (SELECT ficha_id FROM aprendices WHERE id = $apId) LIMIT 1")->fetchColumn();
        $this->assertSame(0, $modelo->contar($actor, ['ficha_id' => $otraFicha]));

        $propias = $this->contar('evaluaciones', 'aprendiz_id = ?', [$apId]);
        $this->assertSame($propias, $modelo->contar($actor, []), 'el aprendiz ve un número de evaluaciones distinto al suyo');
    }

    #[TestDox('los planes de mejoramiento de un aprendiz son solo los suyos')]
    public function testMejoramientoAislado(): void {
        $modelo = new MejoramientoModel($this->db);
        $usuario = $this->idUsuarioAprendiz();
        $apId = (int)$this->db->query("SELECT id FROM aprendices WHERE usuario_id = $usuario")->fetchColumn();
        $actor = new \Core\Support\Actor($usuario, ROL_APRENDIZ);

        $this->assertSame($this->contar('planes_mejoramiento', 'aprendiz_id = ?', [$apId]), $modelo->contar($actor, []));
        foreach ($modelo->listar($actor, [], 100, 0) as $p) {
            $this->assertSame($apId, (int)$p['aprendiz_id'], 'el aprendiz ve el plan de otro');
        }
    }

    #[TestDox('un instructor ajeno no ve planes de mejoramiento ni RAP en D')]
    public function testMejoramientoDeInstructorAjeno(): void {
        $modelo = new MejoramientoModel($this->db);
        $ajeno = new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR);
        $this->assertSame(0, $modelo->contar($ajeno, []));
        $this->assertSame(0, $modelo->contarSinPlan($ajeno));
    }

    /**
     * La tabla de evidencias está vacía en la base de trabajo, así que la
     * prueba siembra las suyas: sin datos, un filtro roto pasaría
     * desapercibido porque el listado saldría vacío de todos modos.
     */
    #[TestDox('las evidencias de un aprendiz no incluyen las de otros')]
    public function testEvidenciasAisladas(): void {
        $modelo = new EvidenciasModel($this->db);

        $dos = $this->db->query("
            SELECT id, ficha_id, usuario_id FROM aprendices
             WHERE usuario_id IS NOT NULL LIMIT 2
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (count($dos) < 2) {
            $this->markTestSkipped('hacen falta dos aprendices para comprobar el aislamiento');
        }
        [$propio, $ajeno] = $dos;

        $insertar = $this->db->prepare("
            INSERT INTO evidencias (aprendiz_id, ficha_id, titulo, descripcion, estado, fecha_envio)
            VALUES (?, ?, ?, 'descripción', 'enviada', NOW())
        ");
        $insertar->execute([$propio['id'], $propio['ficha_id'], 'Evidencia propia']);
        $insertar->execute([$ajeno['id'],  $ajeno['ficha_id'],  'Evidencia AJENA']);

        $suyas = $modelo->listar(new \Core\Support\Actor((int)$propio['usuario_id'], ROL_APRENDIZ), [], 100, 0);

        $this->assertNotEmpty($suyas, 'el aprendiz no ve ni siquiera las suyas');
        foreach ($suyas as $e) {
            $this->assertSame(
                (int)$propio['id'], (int)$e['aprendiz_id'],
                'el listado incluye la evidencia de otro aprendiz'
            );
        }
        $titulos = array_column($suyas, 'titulo');
        $this->assertNotContains('Evidencia AJENA', $titulos);
    }

    // =================================================================
    // REPORTES
    // =================================================================

    #[TestDox('un instructor no puede exportar el reporte de una ficha ajena')]
    public function testExportacionDeFichaAjena(): void {
        $modelo = new ReportesModel($this->db);
        $ajeno  = $this->idInstructorAjeno();

        $fichas = $this->db->query("SELECT id FROM fichas")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fichas as $f) {
            $this->assertFalse(
                $modelo->checkFichaInstructorAccess((int)$f, $ajeno),
                "puede exportar el reporte de la ficha #$f sin tenerla asignada"
            );
        }
    }

    #[TestDox('el reporte de un instructor abarca menos que el del coordinador')]
    public function testAlcanceDeLosReportes(): void {
        $modelo = new ReportesModel($this->db);

        $delCoordinador = count($modelo->getReportHistorialCambios($this->idCoordinador(), ROL_COORDINADOR));
        $delInstructor  = count($modelo->getReportHistorialCambios($this->idInstructorConFicha(), ROL_INSTRUCTOR));

        $this->assertLessThanOrEqual(
            $delCoordinador, $delInstructor,
            'un instructor ve más trazabilidad que la coordinación'
        );
    }

    #[TestDox('un instructor sin fichas no obtiene ningún reporte con datos')]
    public function testReportesDeInstructorAjenoVacios(): void {
        $modelo = new ReportesModel($this->db);
        $ajeno  = $this->idInstructorAjeno();

        $this->assertCount(0, $modelo->getReportHistorialCambios($ajeno, ROL_INSTRUCTOR));
        $this->assertCount(0, $modelo->getReportCumplimientoInstructor($ajeno, ROL_INSTRUCTOR));
    }

    // =================================================================
    // NOTIFICACIONES
    // =================================================================

    /**
     * El `UPDATE` no filtraba por usuario, así que cualquiera podía marcar
     * como leídas las notificaciones de otro probando identificadores.
     */
    #[TestDox('no se pueden marcar como leídas las notificaciones de otro')]
    public function testIdorEnNotificaciones(): void {
        $propietario = $this->idCoordinador();
        $intruso     = $this->idUsuarioAprendiz();

        $this->db->prepare("
            INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leida)
            VALUES (?, 'Aviso de prueba', 'contenido', 'info', 0)
        ")->execute([$propietario]);
        $id = (int)$this->db->lastInsertId();

        $this->assertFalse(
            (new NotificacionesModel($this->db))->marcarLeida($id, $intruso),
            'un usuario ajeno pudo marcar la notificación de otro'
        );
        $this->assertSame(
            0,
            (int)$this->db->query("SELECT leida FROM notificaciones WHERE id = $id")->fetchColumn(),
            'la notificación quedó marcada por un tercero'
        );

        $this->assertTrue((new NotificacionesModel($this->db))->marcarLeida($id, $propietario), 'el dueño sí debe poder');
    }

    #[TestDox('marcar todas solo afecta a las propias')]
    public function testMarcarTodasSoloLasPropias(): void {
        $a = $this->idCoordinador();
        $b = $this->idUsuarioAprendiz();

        $stmt = $this->db->prepare("
            INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leida)
            VALUES (?, 'x', 'y', 'info', 0)
        ");
        $stmt->execute([$a]);
        $stmt->execute([$b]);
        $idDeB = (int)$this->db->lastInsertId();

        (new NotificacionesModel($this->db))->marcarTodas($a);

        $this->assertSame(
            0,
            (int)$this->db->query("SELECT leida FROM notificaciones WHERE id = $idDeB")->fetchColumn(),
            'marcar las propias afectó a las de otro usuario'
        );
    }
}
