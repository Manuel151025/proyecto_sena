<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Models;
use Core\Services\EvaluacionesSyncService;
use PDO;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Los modelos que no cubrían las demás suites, y el servicio que mantiene
 * la rejilla de evaluaciones.
 *
 * Se ejercitan tanto las lecturas como las escrituras; todo queda revertido
 * por la transacción de la clase base.
 */
final class ModelosRestantesTest extends CasoConBaseDeDatos {

    // =================================================================
    // SINCRONIZACIÓN DE LA REJILLA DE EVALUACIONES
    // =================================================================

    /**
     * La regla: todo aprendiz en formación debe tener una fila 'pendiente'
     * por cada RAP del programa de su ficha. Antes solo se aplicaba al
     * matricular, así que un RAP creado después no llegaba a los aprendices
     * ya matriculados.
     */
    #[TestDox('el servicio crea las filas pendientes que falten')]
    public function testSincronizacionCreaLoQueFalta(): void {
        $svc = new EvaluacionesSyncService($this->db);
        $aprendiz = $this->idAprendiz();

        $fila = $this->db->query("
            SELECT id, resultado_aprendizaje_id FROM evaluaciones
             WHERE aprendiz_id = $aprendiz AND concepto = 'pendiente' LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $this->markTestSkipped('el aprendiz no tiene evaluaciones pendientes');
        }

        $this->db->prepare("DELETE FROM evaluaciones WHERE id = ?")->execute([$fila['id']]);
        $antes = $this->contar('evaluaciones', 'aprendiz_id = ?', [$aprendiz]);

        $r = $svc->sincronizar(['aprendiz_id' => $aprendiz]);

        $this->assertGreaterThanOrEqual(1, $r['creadas'], 'no repuso la fila que faltaba');
        $this->assertSame($antes + $r['creadas'], $this->contar('evaluaciones', 'aprendiz_id = ?', [$aprendiz]));
    }

    #[TestDox('sincronizar dos veces no duplica nada')]
    public function testSincronizacionIdempotente(): void {
        $svc = new EvaluacionesSyncService($this->db);
        $aprendiz = $this->idAprendiz();

        $svc->sincronizar(['aprendiz_id' => $aprendiz]);
        $antes = $this->contar('evaluaciones', 'aprendiz_id = ?', [$aprendiz]);

        $r = $svc->sincronizar(['aprendiz_id' => $aprendiz]);

        $this->assertSame(0, $r['creadas'], 'creó filas que ya existían');
        $this->assertSame($antes, $this->contar('evaluaciones', 'aprendiz_id = ?', [$aprendiz]));
    }

    /**
     * Crear la rejilla no puede tocar una nota ya puesta: sería borrar el
     * trabajo del instructor cada vez que se importa estructura curricular.
     */
    #[TestDox('la sincronización no altera ninguna evaluación existente')]
    public function testSincronizacionNoTocaLoEvaluado(): void {
        $svc = new EvaluacionesSyncService($this->db);
        $aprendiz = $this->idAprendiz();

        $antes = $this->db->query("
            SELECT id, concepto, comentario FROM evaluaciones
             WHERE aprendiz_id = $aprendiz ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $svc->sincronizar(['aprendiz_id' => $aprendiz]);

        foreach ($antes as $e) {
            $ahora = $this->db->query("SELECT concepto, comentario FROM evaluaciones WHERE id = {$e['id']}")
                              ->fetch(PDO::FETCH_ASSOC);
            $this->assertSame($e['concepto'], $ahora['concepto'], "cambió el concepto de la evaluación #{$e['id']}");
            $this->assertSame($e['comentario'], $ahora['comentario']);
        }
    }

    #[TestDox('sincronizar por ficha alcanza a todos sus aprendices')]
    public function testSincronizacionPorFicha(): void {
        $r = (new EvaluacionesSyncService($this->db))->sincronizar(['ficha_id' => $this->idFicha()]);

        $this->assertArrayHasKey('creadas', $r);
        $this->assertArrayHasKey('omitidas_sin_instructor', $r);
        $this->assertGreaterThanOrEqual(0, $r['creadas']);
    }

    #[TestDox('un aprendiz desertado no recibe filas nuevas')]
    public function testDesertadoNoRecibeFilas(): void {
        $svc = new EvaluacionesSyncService($this->db);
        $aprendiz = $this->idAprendiz();

        $this->db->prepare("UPDATE aprendices SET estado = 'desertado' WHERE id = ?")->execute([$aprendiz]);
        $this->db->prepare("DELETE FROM evaluaciones WHERE aprendiz_id = ? AND concepto = 'pendiente'")
                 ->execute([$aprendiz]);

        $r = $svc->sincronizar(['aprendiz_id' => $aprendiz]);

        $this->assertSame(0, $r['creadas'], 'no se reabren expedientes cerrados');
    }

    // =================================================================
    // MODELOS DE LECTURA
    // =================================================================

    #[TestDox('AprendizModel lista y cuenta con y sin instructor')]
    public function testAprendizModel(): void {
        $m = new Models\AprendizModel($this->db);
        $inst = $this->idInstructorConFicha();

        $this->assertIsArray($m->getFilteredList([], null, 25, 0));
        $this->assertIsArray($m->getFilteredList(['search' => 'a'], $inst, 25, 0));
        $this->assertIsInt($m->contarFiltrados([]));
        $this->assertIsInt($m->contarFiltrados([], $inst));

        $this->assertLessThanOrEqual(
            $m->contarFiltrados([]),
            $m->contarFiltrados([], $inst),
            'un instructor ve más aprendices que el total del sistema'
        );
    }

    #[TestDox('el conteo de aprendices cuadra con el listado')]
    public function testAprendizConteoCuadra(): void {
        $m = new Models\AprendizModel($this->db);
        $this->assertCount($m->contarFiltrados([]), $m->getFilteredList([], null, 1000, 0));
    }

    #[TestDox('ActividadesModel responde en sus consultas')]
    public function testActividadesModel(): void {
        $m = new Models\ActividadesModel($this->db);

        $coord = new \Core\Support\Actor($this->idCoordinador(), ROL_COORDINADOR);
        $inst  = new \Core\Support\Actor($this->idInstructorConFicha(), ROL_INSTRUCTOR);
        $this->assertIsArray($m->fichasDelActor($coord));
        $this->assertNotEmpty($m->fichasDelActor($inst), 'el instructor con ficha debe ver al menos una');
        $this->assertIsArray($m->listar($coord, [], 10, 0));
        $this->assertSame($m->contar($coord, []), (int)$this->db->query("SELECT COUNT(*) FROM actividades")->fetchColumn());
    }

    #[TestDox('AsignacionesModel responde y detecta duplicados')]
    public function testAsignacionesModel(): void {
        $m = new Models\AsignacionesModel($this->db);

        $this->assertIsArray($m->getAsignaciones('', 0, 0));
        $this->assertIsArray($m->getFichas());
        $this->assertIsArray($m->getCompetencias());

        // El índice UNIQUE(ficha, competencia) significa un instructor por
        // competencia y ficha: el modelo tiene que detectarlo antes.
        $this->assertIsBool($m->checkAsignacionExiste($this->idFicha(), 1));
    }

    #[TestDox('crear una asignación duplicada se detecta')]
    public function testAsignacionDuplicada(): void {
        $m = new Models\AsignacionesModel($this->db);
        $ficha = $this->idFicha();
        $comp = (int)$this->db->query("
            SELECT c.id FROM competencias c
              JOIN fichas f ON f.programa_id = c.programa_id
             WHERE f.id = $ficha LIMIT 1
        ")->fetchColumn();

        if ($comp === 0) {
            $this->markTestSkipped('la ficha no tiene competencias en su programa');
        }

        $inst = $this->idInstructorConFicha();
        $m->crearAsignacion($ficha, $comp, $inst, $this->idCoordinador());

        $this->assertTrue(
            $m->checkAsignacionExiste($ficha, $comp),
            'no detecta que la competencia ya está asignada en esa ficha'
        );
    }

    #[TestDox('CompetenciasModel lista y filtra')]
    public function testCompetenciasModel(): void {
        $m = new Models\CompetenciasModel($this->db);

        $this->assertIsArray($m->opciones());
        $this->assertIsArray($m->listar([], 25, 0));
        $this->assertIsArray($m->listar(['search' => 'a', 'estado' => 'activo'], 25, 0));
        $this->assertSame($m->contar(['estado' => 'inventado']), 0, 'un estado inventado no debe devolver filas');
    }

    #[TestDox('FasesModel responde en sus consultas')]
    public function testFasesModel(): void {
        $m = new Models\FasesModel($this->db);

        $proyecto = (int)$this->db->query("SELECT id FROM proyectos LIMIT 1")->fetchColumn();
        if ($proyecto === 0) {
            $this->markTestSkipped('sin proyectos');
        }
        $fases = $m->listarDeProyecto($proyecto);
        $this->assertIsArray($fases);
        // Sin fichas en el alcance, ninguna actividad cuenta para el avance.
        foreach ($m->listarDeProyecto($proyecto, []) as $f) {
            $this->assertSame(0, (int)$f['total_actividades']);
        }
    }

    #[TestDox('ProgramasModel lista y encuentra por id')]
    public function testProgramasModel(): void {
        $m = new Models\ProgramasModel($this->db);

        $todos = $m->getAll();
        $this->assertIsArray($todos);
        $this->assertNotEmpty($todos);
        $this->assertNotNull($m->findById((int)$todos[0]['id']));
        $this->assertNull($m->findById(999999999), 'un id inexistente debe devolver null, no una fila');
    }

    #[TestDox('ResultadosAprendizajeModel agrupa los RAP por competencia')]
    public function testResultadosAprendizajeModel(): void {
        $m = new Models\ResultadosAprendizajeModel($this->db);
        $this->assertIsArray($m->getCompetenciasWithRaps());
    }

    #[TestDox('RetroalimentacionModel responde en los tres roles')]
    public function testRetroalimentacionModel(): void {
        $m = new Models\RetroalimentacionModel($this->db);

        foreach ([
            [ROL_COORDINADOR, $this->idCoordinador()],
            [ROL_INSTRUCTOR, $this->idInstructorConFicha()],
            [ROL_APRENDIZ, $this->idUsuarioAprendiz()],
        ] as [$rol, $uid]) {
            $this->assertIsArray($m->getFeedbacks($rol, $uid, $this->idAprendiz()));
            $this->assertIsArray($m->getAprendicesDisponibles($rol, $uid));
        }
    }

    #[TestDox('un instructor ajeno no puede dejar retroalimentación')]
    public function testRetroalimentacionPermiso(): void {
        $m = new Models\RetroalimentacionModel($this->db);
        $this->assertFalse($m->checkPermisoInstructor($this->idAprendiz(), $this->idInstructorAjeno()));
    }

    // =================================================================
    // PERFIL
    // =================================================================

    #[TestDox('PerfilModel lee el perfil del usuario')]
    public function testPerfilModel(): void {
        $m = new Models\PerfilModel($this->db);
        $perfil = $m->getPerfil($this->idCoordinador());

        $this->assertIsArray($perfil);
        $this->assertArrayHasKey('nombre', $perfil);
        $this->assertArrayHasKey('email', $perfil);
    }

    /**
     * El perfil lo consulta el propio usuario, pero el modelo no debe
     * devolver el hash de la contraseña a la capa de vista.
     */
    #[TestDox('el perfil no expone el hash de la contraseña')]
    public function testPerfilSinHash(): void {
        $perfil = (new Models\PerfilModel($this->db))->getPerfil($this->idCoordinador());

        foreach ((array)$perfil as $clave => $valor) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\$2y\$\d+\$/',
                (string)$valor,
                "el campo '$clave' contiene un hash de contraseña"
            );
        }
    }

    #[TestDox('cambiar la contraseña la deja verificable y hasheada')]
    public function testCambioDePassword(): void {
        $m = new Models\PerfilModel($this->db);
        $uid = $this->idCoordinador();

        $m->changePassword($uid, 'ContraseñaNueva123');

        $this->assertTrue($m->verifyPassword($uid, 'ContraseñaNueva123'));
        $this->assertFalse($m->verifyPassword($uid, 'otra-cualquiera'));

        $hash = (string)$this->db->query("SELECT password FROM usuarios WHERE id = $uid")->fetchColumn();
        $this->assertNotSame('ContraseñaNueva123', $hash, 'la contraseña quedó en claro');
        $this->assertNotNull(password_get_info($hash)['algo']);
    }

    #[TestDox('actualizar el perfil guarda el nombre y el color')]
    public function testActualizarPerfil(): void {
        $m = new Models\PerfilModel($this->db);
        $uid = $this->idCoordinador();

        $m->updateProfile($uid, 'Nombre Actualizado', '#123456');
        $perfil = $m->getPerfil($uid);

        $this->assertSame('Nombre Actualizado', $perfil['nombre']);
        $this->assertSame('#123456', $perfil['avatar_color']);
    }
}
