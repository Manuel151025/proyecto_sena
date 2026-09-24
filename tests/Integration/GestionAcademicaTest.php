<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Services\AsignacionesService;
use Core\Services\FichasService;
use Core\Services\MatriculasService;
use Core\Services\UsuariosService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\PoliticaContrasena;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Reglas de fichas, matrículas y asignación de instructores.
 *
 * Todo corre dentro de la transacción de la prueba y se revierte.
 */
final class GestionAcademicaTest extends CasoConBaseDeDatos {

    private function coordinador(): Actor {
        return new Actor($this->idCoordinador(), ROL_COORDINADOR);
    }

    private function unaFila(string $sql): array {
        $f = $this->db->query($sql)->fetch();
        if (!$f) {
            $this->markTestSkipped('No hay datos para esta prueba: ' . $sql);
        }
        return $f;
    }

    private function datosFicha(array $cambios = []): array {
        return array_merge([
            'numero_ficha' => '9999001', 'programa_id' => (int)$this->db->query("SELECT id FROM programas LIMIT 1")->fetchColumn(),
            'proyecto_id' => null, 'instructor_id' => $this->idInstructorConFicha(), 'estado' => 'planeacion',
            'fecha_inicio' => null, 'fecha_fin' => null,
        ], $cambios);
    }

    private function datosMatricula(int $fichaId, array $cambios = []): array {
        return array_merge([
            'nombre' => 'APRENDIZ DE PRUEBA', 'email' => 'aprendiz.prueba.qa@example.com', 'tipo_documento' => 'CC',
            'numero_documento' => '99990000123', 'ficha_id' => $fichaId, 'genero' => 'O', 'fecha_nacimiento' => null,
            'telefono' => '', 'ciudad' => '', 'instructor_seguimiento_id' => null, 'estado' => 'matriculado',
        ], $cambios);
    }

    /** Datos de edición a partir de la matrícula actual. */
    private function matriculaActual(int $aprendizId, array $cambios = []): array {
        $a = $this->db->query("SELECT a.*, u.nombre, u.email FROM aprendices a JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = $aprendizId")->fetch();
        return array_merge([
            'nombre' => $a['nombre'], 'email' => $a['email'], 'tipo_documento' => $a['tipo_documento'],
            'numero_documento' => $a['numero_documento'], 'ficha_id' => (int)$a['ficha_id'], 'genero' => $a['genero'],
            'fecha_nacimiento' => $a['fecha_nacimiento'], 'telefono' => (string)$a['telefono'], 'ciudad' => (string)$a['ciudad'],
            'instructor_seguimiento_id' => $a['instructor_seguimiento_id'] !== null ? (int)$a['instructor_seguimiento_id'] : null,
            'estado' => $a['estado'],
        ], $cambios);
    }

    private function n(string $sql, array $params = []): int {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    private function esperarError(callable $op, string $contiene = ''): void {
        try {
            $op();
            $this->fail('se esperaba un ErrorDeNegocio');
        } catch (ErrorDeNegocio $e) {
            if ($contiene !== '') {
                $this->assertStringContainsStringIgnoringCase($contiene, $e->getMessage());
            } else {
                $this->addToAssertionCount(1);
            }
        }
    }

    // =================================================================
    // FICHAS
    // =================================================================

    #[TestDox('solo coordinación crea fichas, matricula y asigna instructores')]
    public function testSoloCoordinacion(): void {
        $inst = new Actor($this->idInstructorConFicha(), ROL_INSTRUCTOR);
        $this->esperarError(fn() => (new FichasService($this->db))->crear($this->datosFicha(), $inst), 'coordinación');
        $this->esperarError(fn() => (new MatriculasService($this->db))->matricular($this->datosMatricula($this->idFicha()), $inst), 'coordinación');
        $this->esperarError(fn() => (new AsignacionesService($this->db))->asignar($this->idFicha(), 1, $inst->id, $inst), 'coordinación');
    }

    #[TestDox('el líder de una ficha tiene que ser un instructor activo')]
    public function testLiderInstructorActivo(): void {
        $this->esperarError(fn() => (new FichasService($this->db))->crear($this->datosFicha(['instructor_id' => $this->idCoordinador()]), $this->coordinador()), 'instructor');
    }

    #[TestDox('el número de ficha no se repite')]
    public function testFichaDuplicada(): void {
        $numero = (string)$this->db->query("SELECT numero_ficha FROM fichas LIMIT 1")->fetchColumn();
        $this->esperarError(fn() => (new FichasService($this->db))->crear($this->datosFicha(['numero_ficha' => $numero]), $this->coordinador()), $numero);
    }

    #[TestDox('no se cambia el programa de una ficha con juicios emitidos')]
    public function testProgramaConJuicios(): void {
        $f = $this->unaFila("
            SELECT f.* FROM fichas f
             WHERE EXISTS (SELECT 1 FROM evaluaciones e WHERE e.ficha_id = f.id AND e.concepto IN ('A','D'))
               AND EXISTS (SELECT 1 FROM programas p WHERE p.id <> f.programa_id)
             LIMIT 1");
        $otro = (int)$this->db->query("SELECT id FROM programas WHERE id <> {$f['programa_id']} LIMIT 1")->fetchColumn();
        $d = $this->datosFicha(['numero_ficha' => $f['numero_ficha'], 'programa_id' => $otro, 'proyecto_id' => $f['proyecto_id'],
                                'instructor_id' => (int)$f['instructor_id'], 'estado' => $f['estado']]);
        $this->esperarError(fn() => (new FichasService($this->db))->editar((int)$f['id'], $d, $this->coordinador()), 'programa');
    }

    #[TestDox('una ficha con aprendices no se elimina: se cierra')]
    public function testNoEliminaFichaConAprendices(): void {
        $f = $this->unaFila("SELECT f.id FROM fichas f WHERE EXISTS (SELECT 1 FROM aprendices a WHERE a.ficha_id = f.id) LIMIT 1");
        $this->esperarError(fn() => (new FichasService($this->db))->eliminar((int)$f['id'], $this->coordinador()), 'cierre');
    }

    #[TestDox('cambiar el líder pasa al nuevo las pendientes que no tienen asignación')]
    public function testCambioDeLiderMuevePendientes(): void {
        $f = $this->unaFila("SELECT f.* FROM fichas f WHERE EXISTS (SELECT 1 FROM evaluaciones e WHERE e.ficha_id = f.id AND e.concepto = 'pendiente') LIMIT 1");
        $nuevo = (int)$this->db->query("SELECT id FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' AND id <> {$f['instructor_id']} LIMIT 1")->fetchColumn();
        $d = $this->datosFicha(['numero_ficha' => $f['numero_ficha'], 'programa_id' => (int)$f['programa_id'], 'proyecto_id' => $f['proyecto_id'],
                                'instructor_id' => $nuevo, 'estado' => $f['estado']]);
        (new FichasService($this->db))->editar((int)$f['id'], $d, $this->coordinador());

        $restantes = $this->n("SELECT COUNT(*) FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id JOIN competencias c ON c.id = ra.competencia_id WHERE e.ficha_id = ? AND e.concepto = 'pendiente' AND e.instructor_id = ? AND c.es_etapa_practica = 0
             AND NOT EXISTS (SELECT 1 FROM asignaciones asg WHERE asg.ficha_id = e.ficha_id AND asg.competencia_id = c.id)",
            [(int)$f['id'], (int)$f['instructor_id']]);
        $this->assertSame(0, $restantes, 'quedaron pendientes a nombre del líder anterior');
    }

    /**
     * El semáforo mide el desempeño (A sobre lo evaluado), no el avance: una
     * ficha recién iniciada, sin juicios, no es «Crítica».
     */
    #[TestDox('una ficha sin juicios no tiene semáforo, aunque su avance sea 0 %')]
    public function testFichaSinJuiciosSinSemaforo(): void {
        $c = $this->coordinador();
        $fichaId = (new FichasService($this->db))->crear($this->datosFicha(), $c);
        (new MatriculasService($this->db))->matricular($this->datosMatricula($fichaId), $c);

        $f = (new \Core\Models\FichaModel($this->db))->detalle($fichaId);
        $this->assertSame(0.0, $f['cumplimiento']);
        $this->assertNull($f['pct_a']);
        $this->assertSame(\Core\Support\Semaforo::SIN_DATOS, $f['semaforo']);
    }

    // =================================================================
    // MATRÍCULAS
    // =================================================================

    #[TestDox('matricular crea la cuenta, una clave temporal válida y un pendiente por RAP del programa')]
    public function testMatricularCreaPendientes(): void {
        $f = $this->unaFila("SELECT f.id, f.programa_id FROM fichas f WHERE f.estado <> 'cierre' AND f.instructor_id IS NOT NULL LIMIT 1");
        $r = (new MatriculasService($this->db))->matricular($this->datosMatricula((int)$f['id']), $this->coordinador());

        $this->assertSame([], PoliticaContrasena::errores($r['temporal']));
        $raps = $this->n('SELECT COUNT(*) FROM resultados_aprendizaje ra JOIN competencias c ON c.id = ra.competencia_id WHERE c.programa_id = ?', [(int)$f['programa_id']]);
        $this->assertSame($raps, $this->contar('evaluaciones', "aprendiz_id = ? AND concepto = 'pendiente'", [$r['id']]));
        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM usuarios u JOIN aprendices a ON a.usuario_id = u.id WHERE a.id = ? AND u.debe_cambiar_password = 1 AND u.rol = 'aprendiz'", [$r['id']]));
    }

    #[TestDox('una ficha en cierre no admite matrículas nuevas')]
    public function testFichaEnCierre(): void {
        $id = $this->idFicha();
        $this->db->exec("UPDATE fichas SET estado = 'cierre' WHERE id = $id");
        $this->esperarError(fn() => (new MatriculasService($this->db))->matricular($this->datosMatricula($id), $this->coordinador()), 'cierre');
    }

    #[TestDox('no se matricula dos veces el mismo documento')]
    public function testDocumentoDuplicado(): void {
        $doc = (string)$this->db->query("SELECT numero_documento FROM aprendices LIMIT 1")->fetchColumn();
        $f = $this->unaFila("SELECT id FROM fichas WHERE estado <> 'cierre' LIMIT 1");
        $this->esperarError(fn() => (new MatriculasService($this->db))->matricular($this->datosMatricula((int)$f['id'], ['numero_documento' => $doc]), $this->coordinador()), $doc);
    }

    #[TestDox('la etapa práctica exige instructor de seguimiento')]
    public function testEtapaPracticaSinSeguimiento(): void {
        $id = $this->idAprendiz();
        $d = $this->matriculaActual($id, ['estado' => 'etapa_practica', 'instructor_seguimiento_id' => null]);
        $this->esperarError(fn() => (new MatriculasService($this->db))->editar($id, $d, $this->coordinador()), 'seguimiento');
    }

    #[TestDox('con juicios emitidos no se traslada a una ficha de otro programa')]
    public function testTrasladoAOtroPrograma(): void {
        $a = $this->unaFila("
            SELECT a.id, f.programa_id FROM aprendices a JOIN fichas f ON f.id = a.ficha_id
             WHERE EXISTS (SELECT 1 FROM evaluaciones e WHERE e.aprendiz_id = a.id AND e.concepto IN ('A','D'))
               AND EXISTS (SELECT 1 FROM fichas f2 WHERE f2.programa_id <> f.programa_id)
             LIMIT 1");
        $destino = (int)$this->db->query("SELECT id FROM fichas WHERE programa_id <> {$a['programa_id']} LIMIT 1")->fetchColumn();
        $d = $this->matriculaActual((int)$a['id'], ['ficha_id' => $destino]);
        $this->esperarError(fn() => (new MatriculasService($this->db))->editar((int)$a['id'], $d, $this->coordinador()), 'mismo programa');
    }

    #[TestDox('retirar deja al aprendiz desertado y sin acceso, sin borrar su historial')]
    public function testRetirar(): void {
        $id = $this->idAprendiz();
        $antes = $this->contar('evaluaciones', 'aprendiz_id = ?', [$id]);
        (new MatriculasService($this->db))->retirar($id, $this->coordinador());

        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM aprendices a JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = ? AND a.estado = 'desertado' AND u.estado = 'inactivo'", [$id]));
        $this->assertSame($antes, $this->contar('evaluaciones', 'aprendiz_id = ?', [$id]));
    }

    // =================================================================
    // ASIGNACIONES
    // =================================================================

    #[TestDox('no se asigna una competencia de otro programa')]
    public function testCompetenciaDeOtroPrograma(): void {
        $f = $this->unaFila("SELECT f.id, f.programa_id FROM fichas f WHERE EXISTS (SELECT 1 FROM competencias c WHERE c.programa_id <> f.programa_id) LIMIT 1");
        $comp = (int)$this->db->query("SELECT id FROM competencias WHERE programa_id <> {$f['programa_id']} AND es_etapa_practica = 0 LIMIT 1")->fetchColumn();
        $this->esperarError(fn() => (new AsignacionesService($this->db))->asignar((int)$f['id'], $comp, $this->idInstructorConFicha(), $this->coordinador()), 'no pertenece');
    }

    #[TestDox('las competencias de etapa práctica no se asignan')]
    public function testEtapaPracticaNoSeAsigna(): void {
        $x = $this->unaFila("SELECT f.id AS ficha, c.id AS comp FROM fichas f JOIN competencias c ON c.programa_id = f.programa_id AND c.es_etapa_practica = 1 LIMIT 1");
        $this->esperarError(fn() => (new AsignacionesService($this->db))->asignar((int)$x['ficha'], (int)$x['comp'], $this->idInstructorConFicha(), $this->coordinador()), 'etapa práctica');
    }

    #[TestDox('asignar pasa las pendientes al instructor; quitar la asignación las devuelve al líder')]
    public function testAsignarYQuitar(): void {
        $x = $this->unaFila("
            SELECT f.id AS ficha, f.instructor_id AS lider, c.id AS comp
              FROM fichas f JOIN competencias c ON c.programa_id = f.programa_id AND c.es_etapa_practica = 0
             WHERE NOT EXISTS (SELECT 1 FROM asignaciones asg WHERE asg.ficha_id = f.id AND asg.competencia_id = c.id)
               AND EXISTS (SELECT 1 FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
                            WHERE e.ficha_id = f.id AND ra.competencia_id = c.id AND e.concepto = 'pendiente')
             LIMIT 1");
        $otro = (int)$this->db->query("SELECT id FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' AND id <> {$x['lider']} LIMIT 1")->fetchColumn();
        $pendientesDe = fn(int $inst) => $this->n("SELECT COUNT(*) FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id WHERE e.ficha_id = ? AND ra.competencia_id = ? AND e.concepto = 'pendiente' AND e.instructor_id = ?", [(int)$x['ficha'], (int)$x['comp'], $inst]);
        $total = $this->n("SELECT COUNT(*) FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id WHERE e.ficha_id = ? AND ra.competencia_id = ? AND e.concepto = 'pendiente'", [(int)$x['ficha'], (int)$x['comp']]);

        $s = new AsignacionesService($this->db);
        $id = $s->asignar((int)$x['ficha'], (int)$x['comp'], $otro, $this->coordinador());
        $this->assertSame($total, $pendientesDe($otro));

        $this->esperarError(fn() => $s->asignar((int)$x['ficha'], (int)$x['comp'], $otro, $this->coordinador()), 'ya tiene instructor');

        $s->eliminar($id, $this->coordinador());
        $this->assertSame($total, $pendientesDe((int)$x['lider']));
    }

    // =================================================================
    // CUENTAS DE COORDINACIÓN
    // =================================================================

    /**
     * Un clic dejaba a la institución sin nadie que pudiera administrar
     * cuentas: el coordinador podía desactivarse a sí mismo o desactivar al
     * último que quedaba.
     */
    #[TestDox('la coordinación no puede quedarse sin ningún coordinador activo')]
    public function testSiempreQuedaUnCoordinador(): void {
        $s = new UsuariosService($this->db);
        $yo = $this->coordinador();
        $this->esperarError(fn() => $s->cambiarEstado($yo->id, 'inactivo', $yo), 'propia');

        // Solo queda un coordinador activo; otro actor con rol de
        // coordinación intenta desactivarlo.
        $this->db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE rol = 'coordinador' AND id <> ?")->execute([$yo->id]);
        $otro = new Actor($this->idInstructorConFicha(), ROL_COORDINADOR);
        $this->esperarError(fn() => $s->cambiarEstado($yo->id, 'inactivo', $otro), 'al menos un coordinador');
        $this->assertSame('activo', (string)$this->db->query("SELECT estado FROM usuarios WHERE id = {$yo->id}")->fetchColumn());
    }
}
