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

    #[TestDox('SeguimientoModel arma el expediente y marca qué puede calificar cada rol')]
    public function testSeguimientoModel(): void {
        $m = new Models\SeguimientoModel($this->db);
        $ap = $this->idAprendiz();
        $resumen = $this->ejecuta(fn() => $m->resumen($ap), 'resumen');
        $this->assertSame((int)$resumen['total'], (int)$resumen['aprobados'] + (int)$resumen['en_d'] + (int)$resumen['pendientes']);

        $coord = $m->competencias($ap, new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR));
        $raps = array_merge(...array_column($coord, 'raps') ?: [[]]);
        $this->assertSame($this->contar('evaluaciones', 'aprendiz_id = ?', [$ap]), count($raps));
        $this->assertNotContains(false, array_column($raps, 'puede_calificar'), 'coordinación puede calificar todo');

        $ajeno = $m->competencias($ap, new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR));
        $this->assertNotContains(true, array_column(array_merge(...array_column($ajeno, 'raps') ?: [[]]), 'puede_calificar'),
            'un instructor ajeno aparece con permiso de calificar');
        $propio = $m->competencias($ap, new \Core\Support\Actor($this->idUsuarioAprendiz(), ROL_APRENDIZ));
        $this->assertNotContains(true, array_column(array_merge(...array_column($propio, 'raps') ?: [[]]), 'puede_calificar'));
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

    #[TestDox('la analítica del coordinador es coherente: el semáforo suma los aprendices y los juicios suman el total')]
    public function testAnaliticaCoordinador(): void {
        $m = new Models\AnaliticaModel($this->db);
        $coord = new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR);
        $r = $this->ejecuta(fn() => $m->resumen($coord), 'resumen');

        $this->assertSame($r['aprendices'], array_sum($m->semaforo($coord)), 'el semáforo no suma los aprendices en formación');
        $this->assertSame($r['total'], $r['a'] + $r['d'] + $r['pendientes']);
        $this->assertSame($this->contar('fichas', "estado <> 'cierre'"), $r['fichas_activas']);
        foreach ([fn() => $m->porPrograma(), fn() => $m->porInstructor(), fn() => $m->competenciasCriticas($coord),
                  fn() => $m->aprendicesEnRiesgo($coord), fn() => $m->actividadesProximas($coord)] as $consulta) {
            $this->ejecuta($consulta, 'desglose');
        }
        $t = $m->tendencia($coord, 12);
        $this->assertCount(12, $t['etiquetas']);
        $this->assertCount(12, $t['a']);
        foreach ($m->aprendicesEnRiesgo($coord, 50) as $a) {
            $this->assertContains($a['semaforo'], [\Core\Support\Semaforo::CRITICO, \Core\Support\Semaforo::RIESGO]);
        }
    }

    #[TestDox('la analítica del instructor se limita a sus fichas; la de un ajeno está vacía')]
    public function testAnaliticaInstructor(): void {
        $m = new Models\AnaliticaModel($this->db);
        $coord = $m->resumen(new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR));
        $inst = new \Core\Support\Actor($this->idInstructorConFicha(), ROL_INSTRUCTOR);
        $r = $m->resumen($inst);
        $this->assertGreaterThan(0, $r['fichas_activas']);
        $this->assertLessThanOrEqual($coord['aprendices'], $r['aprendices']);
        $this->ejecuta(fn() => $m->cargaInstructor($inst), 'carga del instructor');

        $ajeno = new \Core\Support\Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR);
        $this->assertSame(0, $m->resumen($ajeno)['aprendices']);
        $this->assertSame([], $m->aprendicesEnRiesgo($ajeno));
        $this->assertSame(0, $m->cargaInstructor($ajeno)['por_calificar']);
    }

    /**
     * El progreso que ve el aprendiz tiene que sumar exactamente sus RAP:
     * si no cuadra, está viendo los de otro o le faltan los suyos.
     */
    #[TestDox('el progreso del aprendiz suma sus propias evaluaciones y el promedio de su ficha está en rango')]
    public function testProgresoDelAprendizCuadra(): void {
        $s = new Models\SeguimientoModel($this->db);
        $id = $s->aprendizDeUsuario($this->idUsuarioAprendiz()) ?? $this->markTestSkipped('sin perfil de aprendiz');
        $r = $s->resumen($id);
        $this->assertSame((int)$r['total'], (int)$r['aprobados'] + (int)$r['en_d'] + (int)$r['pendientes']);
        $this->assertSame($this->contar('evaluaciones', 'aprendiz_id = ? AND ficha_id = ?', [$id, (int)$r['ficha_id']]), (int)$r['total']);

        $prom = (new Models\AnaliticaModel($this->db))->promedioFicha((int)$r['ficha_id']);
        if ($prom['avance'] !== null) {
            $this->assertGreaterThanOrEqual(0, $prom['avance']);
            $this->assertLessThanOrEqual(100, $prom['avance']);
        }
        $alcance = (new Models\AnaliticaModel($this->db))->resumen(new \Core\Support\Actor($this->idUsuarioAprendiz(), ROL_APRENDIZ));
        $this->assertSame(1, $alcance['fichas_activas'] <= 1 ? 1 : 0, 'el aprendiz alcanza más de una ficha');
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
        foreach ([[ROL_COORDINADOR, $this->idCoordinador()], [ROL_INSTRUCTOR, $this->idInstructorConFicha()], [ROL_APRENDIZ, $apUid]] as [$rol, $uid]) {
            $actor = new \Core\Support\Actor($uid, $rol);
            $this->ejecuta(fn() => $ev->listar($actor, ['search' => 'a', 'estado' => 'enviada'], 20, 0), "evidencias ($rol)");
            $this->ejecuta(fn() => $ev->contar($actor, []), "contar evidencias ($rol)");
        }

        $mj = new Models\MejoramientoModel($this->db);
        foreach ([[ROL_COORDINADOR, $this->idCoordinador()], [ROL_INSTRUCTOR, $this->idInstructorConFicha()], [ROL_APRENDIZ, $apUid]] as [$rol, $uid]) {
            $actor = new \Core\Support\Actor($uid, $rol);
            foreach (Models\MejoramientoModel::FILTROS as $estado) {
                $this->ejecuta(fn() => $mj->listar($actor, ['estado' => $estado, 'search' => 'a'], 20, 0), "planes $estado ($rol)");
            }
            $this->ejecuta(fn() => $mj->cifras($actor), "cifras de planes ($rol)");
            $this->ejecuta(fn() => $mj->sinPlan($actor), "RAP sin plan ($rol)");
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
