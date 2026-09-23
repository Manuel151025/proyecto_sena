<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Models;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Ejercita todas las consultas de lectura de todos los modelos, en los tres
 * roles, contra la base real.
 *
 * Es la red que sostiene los cambios de esquema. Cuando se sustituyeron las
 * 36 apariciones del `LIKE '%ETAPA PRÁCTICA%'` por una columna, y cuando el
 * recuento de aprendices pasó a calcularse al leer, esta suite fue la que
 * confirmó que ninguna de las 85 consultas se había roto por el camino.
 */
final class ConsultasDeModelosTest extends CasoConBaseDeDatos {

    /** Comprueba que la consulta se ejecuta y devuelve algo con forma de resultado. */
    private function ejecuta(callable $consulta, string $nombre): mixed {
        try {
            $r = $consulta();
        } catch (\Throwable $e) {
            $this->fail("$nombre lanzó: " . $e->getMessage());
        }
        $this->assertTrue(
            is_array($r) || is_int($r) || is_bool($r) || is_string($r) || is_null($r),
            "$nombre devolvió algo inesperado"
        );
        return $r;
    }

    // =================================================================
    // FICHAS
    // =================================================================

    #[TestDox('FichaModel responde en todas sus consultas')]
    public function testFichaModel(): void {
        $m = new Models\FichaModel($this->db);
        $ficha = $this->idFicha();
        $inst = $this->idInstructorConFicha();

        $coord = new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR);
        $instructor = new \Core\Support\Actor($inst, ROL_INSTRUCTOR);
        $aprendiz = new \Core\Support\Actor($this->idUsuarioAprendiz(), ROL_APRENDIZ);

        $this->ejecuta(fn() => $m->getAll(), 'getAll');
        $this->ejecuta(fn() => $m->getByInstructor($inst), 'getByInstructor');
        $this->ejecuta(fn() => $m->listar($coord, [], 25, 0), 'listar (coordinador)');
        $this->ejecuta(fn() => $m->listar($instructor, ['search' => 'a', 'estado' => 'ejecucion'], 25, 0), 'listar (instructor)');
        $this->ejecuta(fn() => $m->detalle($ficha), 'detalle');
        $this->ejecuta(fn() => $m->aprendicesConIndicadores($ficha), 'aprendicesConIndicadores');
        $this->ejecuta(fn() => $m->opciones($coord), 'opciones');
        $this->ejecuta(fn() => $m->dependencias($ficha), 'dependencias');

        $this->assertSame($this->contar('fichas'), $m->contar($coord));
        $this->assertLessThanOrEqual(1, $m->contar($aprendiz), 'el aprendiz solo alcanza su ficha');
        $this->assertSame(0, $m->contar($coord, ['estado' => 'inventado']), 'un estado inventado no debe devolver filas');
        $this->ejecuta(fn() => $m->getProgramasActivos(), 'getProgramasActivos');
        $this->ejecuta(fn() => $m->getInstructoresActivos(), 'getInstructoresActivos');
        $this->ejecuta(fn() => $m->getProyectosActivos(), 'getProyectosActivos');
    }

    /**
     * El contador desnormalizado estaba desviado en 5 de 7 fichas. Ahora el
     * listado calcula el recuento al leer, así que tiene que coincidir con
     * las matrículas reales.
     */
    #[TestDox('el recuento de aprendices del listado coincide con las matrículas')]
    public function testRecuentoDeAprendicesCoherente(): void {
        $m = new Models\FichaModel($this->db);

        $coord = new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR);
        foreach ($m->listar($coord, [], 100, 0) as $ficha) {
            $real = $this->contar(
                'aprendices',
                "ficha_id = ? AND estado <> 'desertado'",
                [$ficha['id']]
            );
            $this->assertSame(
                $real,
                (int)$ficha['aprendices_activos'],
                "la ficha #{$ficha['id']} muestra un número de aprendices que no cuadra"
            );
        }
    }

    #[TestDox('contarAprendices excluye a los desertados')]
    public function testContarAprendicesExcluyeDesertados(): void {
        $m = new Models\FichaModel($this->db);
        $ficha = $this->idFicha();

        $antes = $m->contarAprendices($ficha);

        $ap = $this->db->query("SELECT id FROM aprendices WHERE ficha_id = $ficha LIMIT 1")->fetchColumn();
        if ($ap === false) {
            $this->markTestSkipped('la ficha no tiene aprendices');
        }

        $this->db->prepare("UPDATE aprendices SET estado = 'desertado' WHERE id = ?")->execute([$ap]);

        $this->assertSame($antes - 1, $m->contarAprendices($ficha),
            'un aprendiz desertado sigue ocupando cupo en la ficha');
    }

    // =================================================================
    // SEGUIMIENTO Y EVALUACIÓN
    // =================================================================

    #[TestDox('SeguimientoModel responde en los dos roles de gestión')]
    public function testSeguimientoModel(): void {
        $m = new Models\SeguimientoModel($this->db);
        $ficha = $this->idFicha();
        $programa = (int)$this->db->query("SELECT programa_id FROM fichas WHERE id = $ficha")->fetchColumn();

        foreach ([[ROL_COORDINADOR, $this->idCoordinador()], [ROL_INSTRUCTOR, $this->idInstructorConFicha()]] as [$rol, $uid]) {
            $this->ejecuta(fn() => $m->getFichas($uid, $rol), "getFichas ($rol)");
            $this->ejecuta(fn() => $m->getAprendicesStats($ficha, $programa, $rol, $uid), "getAprendicesStats ($rol)");
            $this->ejecuta(fn() => $m->getTodasActividades($ficha, $programa, $rol, $uid), "getTodasActividades ($rol)");
        }

        $this->ejecuta(fn() => $m->getTodasEvaluaciones($ficha), 'getTodasEvaluaciones');
        $this->ejecuta(fn() => $m->getRetroalimentacionesFicha($ficha), 'getRetroalimentacionesFicha');
        $this->ejecuta(fn() => $m->getFichaDetalle($ficha), 'getFichaDetalle');
        $this->ejecuta(fn() => $m->getPerfilAprendiz($this->idUsuarioAprendiz()), 'getPerfilAprendiz');
    }

    #[TestDox('EvaluacionesModel lista y cuenta en los tres roles')]
    public function testEvaluacionesModel(): void {
        $m = new Models\EvaluacionesModel($this->db);

        foreach ([
            [ROL_COORDINADOR, $this->idCoordinador()],
            [ROL_INSTRUCTOR, $this->idInstructorConFicha()],
            [ROL_APRENDIZ, $this->idUsuarioAprendiz()],
        ] as [$rol, $uid]) {
            $actor = new \Core\Support\Actor($uid, $rol);
            $filas = $this->ejecuta(fn() => $m->listar($actor, [], 25, 0), "listar ($rol)");
            $total = $this->ejecuta(fn() => $m->contar($actor, []), "contar ($rol)");
            $this->assertLessThanOrEqual($total, count($filas),
                "el listado devuelve más filas de las que dice el total ($rol)");
            $cifras = $m->cifras($actor, []);
            $this->assertSame($total, $cifras['total'], "las cifras no cuadran con el listado ($rol)");
            $this->assertSame($cifras['total'], $cifras['a'] + $cifras['d'] + $cifras['pendientes']);
        }
        $this->ejecuta(fn() => $m->historial([$this->idEvaluacion('A'), $this->idEvaluacion()]), 'historial');
        $ajeno = new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR);
        $this->assertSame(0, $m->contar($ajeno, []), 'un instructor sin fichas ve juicios');
        $this->assertSame([], $m->fichasDelActor(new \Core\Support\Actor($this->idUsuarioAprendiz(), ROL_APRENDIZ)));
    }

    /**
     * El total del paginador y las filas del listado salen del mismo WHERE.
     * Si divergen, la última página sale vacía o se pierden registros.
     */
    #[TestDox('el conteo y el listado de evaluaciones usan el mismo filtro')]
    public function testPaginacionCoherente(): void {
        $m = new Models\EvaluacionesModel($this->db);
        $coord = new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR);

        $total = $m->contar($coord, ['concepto' => 'A']);
        $paginas = (int)ceil($total / 25);
        $this->assertSame(0, $m->contar($coord, ['concepto' => 'inventado']), 'un concepto inventado no debe devolver filas');

        if ($paginas > 1) {
            $ultima = $m->listar($coord, ['concepto' => 'A'], 25, ($paginas - 1) * 25);
            $this->assertNotEmpty($ultima, 'la última página que anuncia el paginador está vacía');
        }
        $this->assertGreaterThan(0, $total);
    }

    // =================================================================
    // PANELES
    // =================================================================

    #[TestDox('el panel del coordinador calcula todos sus indicadores')]
    public function testDashboardCoordinador(): void {
        $m = new Models\DashboardModel($this->db);

        $this->ejecuta(fn() => $m->getKpiMetrics(), 'getKpiMetrics');
        $this->ejecuta(fn() => $m->getSparklineData(), 'getSparklineData');
        $this->ejecuta(fn() => $m->getCriticasFichas(5), 'getCriticasFichas');
        $this->ejecuta(fn() => $m->getCumplimientoPorPrograma(), 'getCumplimientoPorPrograma');
        $this->ejecuta(fn() => $m->getStatsProgramasDesercion(5), 'getStatsProgramasDesercion');
        $this->ejecuta(fn() => $m->getTopInstructores(5), 'getTopInstructores');
        $this->ejecuta(fn() => $m->getRecentEvaluations(5), 'getRecentEvaluations');
    }

    #[TestDox('el panel del instructor calcula sus indicadores')]
    public function testDashboardInstructor(): void {
        $m = new Models\InstructorDashboardModel($this->db);
        $inst = $this->idInstructorConFicha();

        $this->ejecuta(fn() => $m->getKpis($inst), 'getKpis');
        $this->ejecuta(fn() => $m->getFichasAsignadas($inst), 'getFichasAsignadas');
        $this->ejecuta(fn() => $m->getRecentDeficiencies($inst, 10), 'getRecentDeficiencies');
        $this->ejecuta(fn() => $m->getConceptDistribution($inst), 'getConceptDistribution');
        $this->ejecuta(fn() => $m->getAprendicesSeguimiento($inst), 'getAprendicesSeguimiento');
    }

    #[TestDox('el panel del aprendiz calcula su progreso')]
    public function testDashboardAprendiz(): void {
        $m = new Models\AprendizDashboardModel($this->db);
        $info = $m->getAprendizInfo($this->idUsuarioAprendiz());

        $this->assertNotNull($info, 'no se encuentra el perfil del aprendiz');

        $this->ejecuta(fn() => $m->getProgresoGlobal((int)$info['id']), 'getProgresoGlobal');
        $this->ejecuta(fn() => $m->getProgresoCompetencias((int)$info['ficha_id'], (int)$info['id']), 'getProgresoCompetencias');
        $this->ejecuta(fn() => $m->getRecentEvaluations((int)$info['id'], 6), 'getRecentEvaluations');
        $this->ejecuta(fn() => $m->getAlertasD((int)$info['id'], 3), 'getAlertasD');
    }

    /**
     * El progreso que ve el aprendiz tiene que sumar exactamente sus RAP:
     * si no cuadra, está viendo los de otro o le faltan los suyos.
     */
    #[TestDox('el progreso del aprendiz suma sus propias evaluaciones')]
    public function testProgresoDelAprendizCuadra(): void {
        $m = new Models\AprendizDashboardModel($this->db);
        $info = $m->getAprendizInfo($this->idUsuarioAprendiz());

        if ($info === null) {
            $this->markTestSkipped('sin perfil de aprendiz');
        }

        $p = $m->getProgresoGlobal((int)$info['id']);
        $suma = (int)$p['aprobados'] + (int)$p['reprobados'] + (int)$p['pendientes'];

        $this->assertSame((int)$p['total_ra'], $suma,
            'los conceptos no suman el total: hay evaluaciones sin clasificar');
        $this->assertSame(
            $this->contar('evaluaciones', 'aprendiz_id = ?', [(int)$info['id']]),
            (int)$p['total_ra'],
            'el total del panel no coincide con las evaluaciones del aprendiz'
        );
    }

    // =================================================================
    // REPORTES
    // =================================================================

    #[TestDox('los cuatro reportes se generan para los dos roles')]
    public function testReportes(): void {
        $m = new Models\ReportesModel($this->db);
        $ficha = $this->idFicha();

        foreach ([[ROL_COORDINADOR, $this->idCoordinador()], [ROL_INSTRUCTOR, $this->idInstructorConFicha()]] as [$rol, $uid]) {
            $this->ejecuta(fn() => $m->getReportEvaluacionesFicha($ficha, $uid, $rol), "evaluaciones_ficha ($rol)");
            $this->ejecuta(fn() => $m->getReportCumplimientoInstructor($uid, $rol), "cumplimiento_instructor ($rol)");
            $this->ejecuta(fn() => $m->getReportCumplimientoCompetencia($uid, $rol), "cumplimiento_competencia ($rol)");
            $this->ejecuta(fn() => $m->getReportHistorialCambios($uid, $rol), "historial_cambios ($rol)");
        }

        $this->ejecuta(fn() => $m->getGlobalStats(), 'getGlobalStats');
        $this->ejecuta(fn() => $m->getInstructorStats($this->idInstructorConFicha()), 'getInstructorStats');
        $this->ejecuta(fn() => $m->getFichaResumen($ficha), 'getFichaResumen');
    }

    // =================================================================
    // RESTO
    // =================================================================

    #[TestDox('los demás modelos responden sin errores')]
    public function testRestoDeModelos(): void {
        $apId = $this->idAprendiz();
        $apUid = $this->idUsuarioAprendiz();

        $ev = new Models\EvidenciasModel($this->db);
        foreach ([ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ] as $rol) {
            $this->ejecuta(fn() => $ev->getEvidencias($rol, $apUid, $apId), "getEvidencias ($rol)");
        }

        $mj = new Models\MejoramientoModel($this->db);
        foreach ([ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ] as $rol) {
            $this->ejecuta(fn() => $mj->getPlanesMejoramiento($rol, $apUid, $apId), "getPlanes ($rol)");
        }

        $us = new Models\UsuarioModel($this->db);
        $this->ejecuta(fn() => $us->listar([], 25, 0), 'listar');
        $this->ejecuta(fn() => $us->contar([]), 'contar');
        $this->ejecuta(fn() => $us->findById($this->idCoordinador()), 'findById');

        $lg = new Models\LogsModel($this->db);
        $this->ejecuta(fn() => $lg->getLogs('', '', 25, 0), 'getLogs');

        $cf = new Models\ConfiguracionModel($this->db);
        $this->ejecuta(fn() => $cf->getAll(), 'getAll');

        $cal = new Models\CalendarioModel($this->db);
        $desde = date('Y-01-01');
        $hasta = date('Y-12-31');
        $this->ejecuta(fn() => $cal->getCoordinadorEvents($desde, $hasta), 'getCoordinadorEvents');
        $this->ejecuta(fn() => $cal->getInstructorEvents($this->idInstructorConFicha(), $desde, $hasta), 'getInstructorEvents');
        $this->ejecuta(fn() => $cal->getAprendizEvents($apUid, $desde, $hasta), 'getAprendizEvents');

        $pr = new Models\ProyectosModel($this->db);
        $this->ejecuta(fn() => $pr->listar(new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR)), 'ProyectosModel::listar');
    }

    /**
     * El módulo de configuración consultaba una tabla que no existía y el
     * `catch` devolvía un array vacío: aparentaba funcionar y descartaba
     * lo guardado sin avisar.
     */
    #[TestDox('la configuración se guarda y se vuelve a leer')]
    public function testConfiguracionPersiste(): void {
        $m = new Models\ConfiguracionModel($this->db);

        $m->save('clave_de_prueba', 'valor guardado');
        $this->assertSame('valor guardado', $m->get('clave_de_prueba'));

        $m->save('clave_de_prueba', 'valor corregido');
        $this->assertSame('valor corregido', $m->get('clave_de_prueba'),
            'el guardado no sobrescribe');

        $this->assertArrayHasKey('clave_de_prueba', $m->getAll());
    }

    #[TestDox('una clave inexistente devuelve el valor por defecto')]
    public function testConfiguracionPorDefecto(): void {
        $m = new Models\ConfiguracionModel($this->db);
        $this->assertSame('respaldo', $m->get('clave_que_no_existe', 'respaldo'));
    }
}
