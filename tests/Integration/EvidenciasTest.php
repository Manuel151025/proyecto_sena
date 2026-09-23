<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Models\EvidenciasModel;
use Core\Services\EvidenciasService;
use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Evidencias: envío, acceso al archivo, revisión y retiro.
 *
 * Los archivos se guardan en una carpeta temporal (no en uploads/) y todo
 * lo de la base se revierte al terminar.
 */
final class EvidenciasTest extends CasoConBaseDeDatos {
    private string $raiz;
    private array $aprendiz;

    protected function setUp(): void {
        parent::setUp();
        $this->raiz = sys_get_temp_dir() . '/sena_evid_' . bin2hex(random_bytes(4));
        mkdir($this->raiz);
        $this->aprendiz = $this->db->query("
            SELECT a.id, a.usuario_id, a.ficha_id, f.instructor_id AS lider
              FROM aprendices a JOIN fichas f ON f.id = a.ficha_id
             WHERE a.estado = 'matriculado' AND a.usuario_id IS NOT NULL
               AND EXISTS (SELECT 1 FROM evaluaciones e WHERE e.aprendiz_id = a.id)
             LIMIT 1")->fetch() ?: $this->markTestSkipped('no hay un aprendiz matriculado con evaluaciones');
    }

    protected function tearDown(): void {
        foreach (glob($this->raiz . '/uploads/evidencias/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->raiz . '/uploads/evidencias');
        @rmdir($this->raiz . '/uploads');
        @rmdir($this->raiz);
        parent::tearDown();
    }

    private function servicio(): EvidenciasService {
        return new EvidenciasService($this->db, $this->raiz);
    }

    private function actorAprendiz(): Actor {
        return new Actor((int)$this->aprendiz['usuario_id'], ROL_APRENDIZ);
    }

    private function pdf(): ArchivoSubido {
        $ruta = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($ruta, "%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        return ArchivoSubido::desde(['name' => 'informe.pdf', 'tmp_name' => $ruta, 'size' => filesize($ruta), 'error' => 0], ['pdf'], 10);
    }

    private function evaluacion(string $concepto = 'pendiente'): ?int {
        $st = $this->db->prepare("SELECT id FROM evaluaciones WHERE aprendiz_id = ? AND concepto = ? LIMIT 1");
        $st->execute([$this->aprendiz['id'], $concepto]);
        return ($id = $st->fetchColumn()) !== false ? (int)$id : null;
    }

    private function enviar(?int $evaluacionId = null): int {
        return $this->servicio()->enviar(['titulo' => 'Evidencia de prueba', 'descripcion' => 'Descripción', 'evaluacion_id' => $evaluacionId],
            $this->pdf(), $this->actorAprendiz());
    }

    #[TestDox('el aprendiz envía una evidencia: se guarda con nombre aleatorio y se avisa al instructor')]
    public function testEnviar(): void {
        $avisos = $this->contar('notificaciones', 'usuario_id = ?', [(int)$this->aprendiz['lider']]);
        $id = $this->enviar();
        $ev = (new EvidenciasModel($this->db))->findById($id);
        $this->assertMatchesRegularExpression('#^uploads/evidencias/[a-f0-9]{32}\.pdf$#', $ev['archivo_url']);
        $this->assertFileExists($this->raiz . '/' . $ev['archivo_url']);
        $this->assertSame('enviada', $ev['estado']);
        $this->assertGreaterThan($avisos, $this->contar('notificaciones', 'usuario_id = ?', [(int)$this->aprendiz['lider']]));
    }

    #[TestDox('no se liga una evidencia al RAP de otro aprendiz')]
    public function testRapAjeno(): void {
        $ajena = (int)$this->db->query("SELECT id FROM evaluaciones WHERE aprendiz_id <> {$this->aprendiz['id']} LIMIT 1")->fetchColumn();
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('no es tuyo');
        $this->enviar($ajena);
    }

    #[TestDox('un aprendiz desertado no envía evidencias')]
    public function testDesertado(): void {
        $this->db->exec("UPDATE aprendices SET estado = 'desertado' WHERE id = {$this->aprendiz['id']}");
        $this->expectException(ErrorDeNegocio::class);
        $this->enviar();
    }

    #[TestDox('el archivo solo lo descargan el aprendiz, quien revisa y coordinación')]
    public function testAccesoAlArchivo(): void {
        $id = $this->enviar();
        $s = $this->servicio();
        $this->assertFileExists($s->archivo($id, $this->actorAprendiz())[0]);
        $this->assertFileExists($s->archivo($id, new Actor($this->idCoordinador(), ROL_COORDINADOR))[0]);
        $this->assertFileExists($s->archivo($id, new Actor((int)$this->aprendiz['lider'], ROL_INSTRUCTOR))[0]);

        $otro = (int)$this->db->query("SELECT usuario_id FROM aprendices WHERE usuario_id IS NOT NULL AND id <> {$this->aprendiz['id']} LIMIT 1")->fetchColumn();
        foreach ([new Actor($otro, ROL_APRENDIZ), new Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR)] as $intruso) {
            try {
                $s->archivo($id, $intruso);
                $this->fail("{$intruso->rol} ajeno descargó la evidencia");
            } catch (ErrorDeNegocio) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[TestDox('una ruta alterada en la base no sirve otro archivo del servidor')]
    public function testRutaAlterada(): void {
        $id = $this->enviar();
        $this->db->prepare("UPDATE evidencias SET archivo_url = '../../.env' WHERE id = ?")->execute([$id]);
        $this->expectException(ErrorDeNegocio::class);
        $this->servicio()->archivo($id, new Actor($this->idCoordinador(), ROL_COORDINADOR));
    }

    #[TestDox('rechazar una evidencia no toca el juicio del RAP')]
    public function testRechazarNoCambiaElJuicio(): void {
        $eval = $this->evaluacion('A') ?? $this->markTestSkipped('el aprendiz no tiene un RAP en A');
        $id = $this->enviar($eval);
        $this->servicio()->revisar(['id' => $id, 'estado' => 'rechazada', 'retroalimentacion' => 'Falta el anexo', 'juicio' => ''],
            new Actor($this->idCoordinador(), ROL_COORDINADOR));
        $this->assertSame('A', $this->db->query("SELECT concepto FROM evaluaciones WHERE id = $eval")->fetchColumn());
        $this->assertSame(1, $this->contar('retroalimentacion', 'evaluacion_id = ? AND contenido = ?', [$eval, 'Falta el anexo']));
    }

    #[TestDox('al revisar se puede registrar el juicio, y queda en el historial')]
    public function testRevisarConJuicio(): void {
        $eval = $this->evaluacion() ?? $this->markTestSkipped('el aprendiz no tiene RAP pendientes');
        $id = $this->enviar($eval);
        $antes = $this->contar('historial_evaluaciones', 'evaluacion_id = ?', [$eval]);
        $this->servicio()->revisar(['id' => $id, 'estado' => 'aprobada', 'retroalimentacion' => 'Cumple los criterios', 'juicio' => 'A'],
            new Actor($this->idCoordinador(), ROL_COORDINADOR));
        $this->assertSame('A', $this->db->query("SELECT concepto FROM evaluaciones WHERE id = $eval")->fetchColumn());
        $this->assertSame($antes + 1, $this->contar('historial_evaluaciones', 'evaluacion_id = ?', [$eval]));
    }

    #[TestDox('sin RAP ligado no se registra juicio, y un instructor ajeno no revisa')]
    public function testRevisionesInvalidas(): void {
        $id = $this->enviar();
        $d = ['id' => $id, 'estado' => 'aprobada', 'retroalimentacion' => 'Bien hecho', 'juicio' => 'A'];
        try {
            $this->servicio()->revisar($d, new Actor($this->idCoordinador(), ROL_COORDINADOR));
            $this->fail('registró un juicio sin RAP ligado');
        } catch (ErrorDeNegocio $e) {
            $this->assertStringContainsString('no está ligada', $e->getMessage());
        }
        $this->expectException(ErrorDeNegocio::class);
        $this->servicio()->revisar(['juicio' => ''] + $d, new Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR));
    }

    #[TestDox('el aprendiz retira su evidencia sin revisar, pero no una ya revisada')]
    public function testRetirar(): void {
        $id = $this->enviar();
        $archivo = $this->raiz . '/' . (new EvidenciasModel($this->db))->findById($id)['archivo_url'];
        $this->servicio()->eliminar($id, $this->actorAprendiz());
        $this->assertSame(0, $this->contar('evidencias', 'id = ?', [$id]));
        $this->assertFileDoesNotExist($archivo);

        $id = $this->enviar();
        $this->servicio()->revisar(['id' => $id, 'estado' => 'revisada', 'retroalimentacion' => 'Ajusta el formato', 'juicio' => ''],
            new Actor($this->idCoordinador(), ROL_COORDINADOR));
        $this->expectException(ErrorDeNegocio::class);
        $this->servicio()->eliminar($id, $this->actorAprendiz());
    }
}
