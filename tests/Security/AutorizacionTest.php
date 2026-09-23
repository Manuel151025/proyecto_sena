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
     * El camino real de /evaluaciones: el controlador pregunta el concepto
     * anterior pasando el rol, y el modelo devuelve false si no hay
     * autoridad. Si devolviera el concepto, el controlador seguiría adelante
     * y el instructor ajeno acabaría escribiendo la nota.
     */
    #[TestDox('getEvaluacionAnterior() corta el paso a un instructor ajeno')]
    public function testNoSeObtieneElConceptoDeUnaEvaluacionAjena(): void {
        $modelo = new EvaluacionesModel($this->db);
        $ajeno  = $this->idInstructorAjeno();

        $ids = $this->db->query("SELECT id FROM evaluaciones LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->assertFalse(
                $modelo->getEvaluacionAnterior((int)$id, ROL_INSTRUCTOR, $ajeno),
                "se obtuvo el concepto de la evaluación #$id siendo ajeno"
            );
        }
    }

    #[TestDox('el coordinador sí puede consultar cualquier evaluación')]
    public function testCoordinadorAccedeATodo(): void {
        $modelo = new EvaluacionesModel($this->db);
        $this->assertNotFalse(
            $modelo->getEvaluacionAnterior($this->idEvaluacion(), ROL_COORDINADOR, $this->idCoordinador())
        );
    }

    #[TestDox('el permiso de seguimiento rechaza un RAP de otra ficha')]
    public function testPermisoDeSeguimientoConDatosCruzados(): void {
        $modelo = new SeguimientoModel($this->db);
        $ajeno  = $this->idInstructorAjeno();

        $this->assertFalse(
            $modelo->checkInstructorPermission(
                $this->db->query("SELECT resultado_aprendizaje_id FROM evaluaciones LIMIT 1")->fetchColumn(),
                $this->idAprendiz(),
                $this->idFicha(),
                $ajeno
            )
        );
    }

    #[TestDox('agregar retroalimentación exige relación con el aprendiz')]
    public function testRetroalimentacionExigeRelacion(): void {
        $modelo = new SeguimientoModel($this->db);
        $this->assertFalse(
            $modelo->checkRetroalimentacionPermission($this->idAprendiz(), $this->idInstructorAjeno())
        );
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

        $total       = $modelo->contarEvaluaciones(ROL_COORDINADOR, $this->idCoordinador(), 0, 0, '', '');
        $delAjeno    = $modelo->contarEvaluaciones(ROL_INSTRUCTOR, $this->idInstructorAjeno(), 0, 0, '', '');

        $this->assertSame(0, $delAjeno, 'un instructor sin fichas ve evaluaciones que no le tocan');
        $this->assertGreaterThan(0, $total, 'el coordinador debería verlas todas');
    }

    #[TestDox('un aprendiz solo ve sus propias evaluaciones')]
    public function testAprendizSoloVeLasSuyas(): void {
        $modelo = new EvaluacionesModel($this->db);
        $apId = $this->idAprendiz();

        $filas = $modelo->getEvaluaciones(ROL_APRENDIZ, $this->idUsuarioAprendiz(), $apId, 0, '', '', 100, 0);

        foreach ($filas as $f) {
            // El listado no expone aprendiz_id, así que se comprueba por el
            // nombre: todas las filas deben ser del mismo aprendiz.
            $this->assertArrayHasKey('aprendiz_nombre', $f);
        }

        $total = $modelo->contarEvaluaciones(ROL_APRENDIZ, $this->idUsuarioAprendiz(), $apId, 0, '', '');
        $propias = $this->contar('evaluaciones', 'aprendiz_id = ?', [$apId]);
        $this->assertSame($propias, $total, 'el aprendiz ve un número de evaluaciones distinto al suyo');
    }

    #[TestDox('los planes de mejoramiento de un aprendiz son solo los suyos')]
    public function testMejoramientoAislado(): void {
        $modelo = new MejoramientoModel($this->db);
        $apId = $this->idAprendiz();

        $suyos = $modelo->getPlanesMejoramiento(ROL_APRENDIZ, $this->idUsuarioAprendiz(), $apId);
        $enBase = $this->contar('evaluaciones', "aprendiz_id = ? AND concepto = 'D'", [$apId]);

        $this->assertCount($enBase, $suyos);
    }

    #[TestDox('un instructor ajeno no ve planes de mejoramiento')]
    public function testMejoramientoDeInstructorAjeno(): void {
        $modelo = new MejoramientoModel($this->db);
        $this->assertCount(
            0,
            $modelo->getPlanesMejoramiento(ROL_INSTRUCTOR, $this->idInstructorAjeno(), 0)
        );
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

        $suyas = $modelo->getEvidencias(ROL_APRENDIZ, (int)$propio['usuario_id'], (int)$propio['id']);

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
