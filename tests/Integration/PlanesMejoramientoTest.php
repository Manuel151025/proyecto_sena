<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Models\MejoramientoModel;
use Core\Services\PlanesService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Ciclo del plan de mejoramiento: se abre sobre un RAP en D, pasa a «en
 * curso» con la evidencia del aprendiz y al cerrarlo como cumplido el RAP
 * queda en A con su historial. Todo se revierte al terminar.
 */
final class PlanesMejoramientoTest extends CasoConBaseDeDatos {
    private array $eval;

    protected function setUp(): void {
        parent::setUp();
        // Un RAP en D (o uno pendiente que se pone en D para la prueba),
        // de un aprendiz activo, sin plan vigente.
        $this->eval = $this->db->query("
            SELECT e.id, e.aprendiz_id, e.instructor_id, ap.usuario_id
              FROM evaluaciones e JOIN aprendices ap ON ap.id = e.aprendiz_id
             WHERE ap.estado = 'matriculado' AND e.concepto IN ('D','pendiente')
               AND NOT EXISTS (SELECT 1 FROM planes_mejoramiento pm WHERE pm.evaluacion_id = e.id AND pm.estado IN ('abierto','en_curso'))
             ORDER BY e.concepto = 'D' DESC LIMIT 1")->fetch() ?: $this->markTestSkipped('no hay RAP candidatos');
        $this->db->exec("UPDATE evaluaciones SET concepto = 'D' WHERE id = {$this->eval['id']}");
    }

    private function coordinador(): Actor {
        return new Actor($this->idCoordinador(), ROL_COORDINADOR);
    }

    private function datos(array $cambios = []): array {
        return array_merge(['evaluacion_id' => (int)$this->eval['id'], 'actividades' => 'Rehacer el taller y sustentarlo',
                            'fecha_inicio' => date('Y-m-d'), 'fecha_limite' => date('Y-m-d', strtotime('+10 days'))], $cambios);
    }

    private function concepto(): string {
        return (string)$this->db->query("SELECT concepto FROM evaluaciones WHERE id = {$this->eval['id']}")->fetchColumn();
    }

    #[TestDox('se abre un plan sobre un RAP en D y el aprendiz recibe un aviso')]
    public function testCrear(): void {
        $avisos = $this->contar('notificaciones', 'usuario_id = ?', [(int)$this->eval['usuario_id']]);
        $id = (new PlanesService($this->db))->crear($this->datos(), $this->coordinador());
        $plan = (new MejoramientoModel($this->db))->findById($id);
        $this->assertSame('abierto', $plan['estado']);
        // Creado por coordinación: responde el instructor que califica el RAP.
        $this->assertSame((int)$this->eval['instructor_id'], (int)$plan['instructor_id']);
        $this->assertSame($avisos + 1, $this->contar('notificaciones', 'usuario_id = ?', [(int)$this->eval['usuario_id']]));
    }

    #[TestDox('un RAP solo tiene un plan vigente, y solo si está en D')]
    public function testReglasDeApertura(): void {
        $s = new PlanesService($this->db);
        $s->crear($this->datos(), $this->coordinador());
        try {
            $s->crear($this->datos(), $this->coordinador());
            $this->fail('abrió dos planes vigentes para el mismo RAP');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsString('ya tiene un plan vigente', $e->getMessage());
        }
        $otra = (int)$this->db->query("SELECT id FROM evaluaciones WHERE concepto = 'A' LIMIT 1")->fetchColumn();
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('no está en D');
        $s->crear($this->datos(['evaluacion_id' => $otra]), $this->coordinador());
    }

    #[TestDox('un instructor que no califica el RAP no abre ni cierra su plan')]
    public function testInstructorAjeno(): void {
        $this->expectException(ErrorDeNegocio::class);
        (new PlanesService($this->db))->crear($this->datos(), new Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR));
    }

    #[TestDox('la evidencia del aprendiz pone el plan en curso')]
    public function testEnCurso(): void {
        $id = (new PlanesService($this->db))->crear($this->datos(), $this->coordinador());
        (new MejoramientoModel($this->db))->marcarEnCurso((int)$this->eval['id']);
        $this->assertSame('en_curso', (new MejoramientoModel($this->db))->findById($id)['estado']);
    }

    #[TestDox('cerrar como cumplido pasa el RAP a A con historial; no cumplido lo deja en D')]
    public function testCerrar(): void {
        $s = new PlanesService($this->db);
        $id = $s->crear($this->datos(), $this->coordinador());
        $historial = $this->contar('historial_evaluaciones', 'evaluacion_id = ?', [(int)$this->eval['id']]);
        $s->cerrar(['id' => $id, 'resultado' => 'cumplido', 'observaciones' => 'Sustentó el taller corregido'], $this->coordinador());

        $this->assertSame('A', $this->concepto());
        $this->assertSame($historial + 1, $this->contar('historial_evaluaciones', 'evaluacion_id = ?', [(int)$this->eval['id']]));
        $plan = (new MejoramientoModel($this->db))->findById($id);
        $this->assertSame('cumplido', $plan['estado']);
        $this->assertNotNull($plan['fecha_cierre']);

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('ya está cerrado');
        $s->cerrar(['id' => $id, 'resultado' => 'no_cumplido', 'observaciones' => 'otra vez'], $this->coordinador());
    }

    #[TestDox('un plan no cumplido deja el RAP en D y permite abrir otro')]
    public function testNoCumplido(): void {
        $s = new PlanesService($this->db);
        $id = $s->crear($this->datos(), $this->coordinador());
        $s->cerrar(['id' => $id, 'resultado' => 'no_cumplido', 'observaciones' => 'No entregó'], $this->coordinador());
        $this->assertSame('D', $this->concepto());
        $this->assertGreaterThan($id, $s->crear($this->datos(), $this->coordinador()));
    }

    #[TestDox('los vencidos se cuentan y se filtran')]
    public function testVencidos(): void {
        $id = (new PlanesService($this->db))->crear($this->datos(), $this->coordinador());
        $this->db->exec("UPDATE planes_mejoramiento SET fecha_inicio = CURDATE() - INTERVAL 20 DAY, fecha_limite = CURDATE() - INTERVAL 2 DAY WHERE id = $id");
        $m = new MejoramientoModel($this->db);
        $ids = array_map('intval', array_column($m->listar($this->coordinador(), ['estado' => 'vencido'], 100, 0), 'id'));
        $this->assertContains($id, $ids);
        $this->assertGreaterThanOrEqual(1, $m->cifras($this->coordinador())['vencidos']);
    }
}
