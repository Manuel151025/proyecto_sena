<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Services\EvaluacionService;
use Core\Support\ErrorDeNegocio;
use PDO;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * La operación central del sistema: escribir un juicio evaluativo.
 *
 * RNF02 exige que ninguna nota cambie sin dejar constancia. Antes había
 * cuatro caminos que escribían `evaluaciones.concepto` con garantías
 * distintas —uno de ellos, calificar una evidencia, no registraba nada— y
 * el resultado eran 987 juicios emitidos con 15 filas de historial.
 *
 * Estas pruebas fijan las tres invariantes del servicio: transacción
 * siempre, bloqueo de la fila antes de leer el concepto anterior, e
 * historial si y solo si el concepto cambia de verdad.
 */
final class EvaluacionServiceTest extends CasoConBaseDeDatos {

    private EvaluacionService $servicio;
    private int $rap;
    private int $aprendiz;
    private int $ficha;
    private int $usuario;

    protected function setUp(): void {
        parent::setUp();
        $this->servicio = new EvaluacionService($this->db);

        // Se trabaja sobre una evaluación 'pendiente' real, borrándola
        // primero para poder ejercitar también el camino de creación. Todo
        // queda revertido por la transacción de la clase base.
        $fila = $this->db->query("
            SELECT e.id, e.aprendiz_id, e.ficha_id, e.resultado_aprendizaje_id, e.instructor_id
              FROM evaluaciones e WHERE e.concepto = 'pendiente' LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $this->markTestSkipped('no hay evaluaciones pendientes con las que trabajar');
        }

        $this->aprendiz = (int)$fila['aprendiz_id'];
        $this->ficha    = (int)$fila['ficha_id'];
        $this->rap      = (int)$fila['resultado_aprendizaje_id'];
        $this->usuario  = (int)$fila['instructor_id'];

        $this->db->prepare("DELETE FROM historial_evaluaciones WHERE evaluacion_id = ?")->execute([$fila['id']]);
        $this->db->prepare("DELETE FROM retroalimentacion WHERE evaluacion_id = ?")->execute([$fila['id']]);
        $this->db->prepare("DELETE FROM evaluaciones WHERE id = ?")->execute([$fila['id']]);
    }

    /** @return array{evaluacion_id:int, accion:string} */
    private function registrar(array $extra = []): array {
        return $this->servicio->registrar($extra + [
            'resultado_aprendizaje_id' => $this->rap,
            'aprendiz_id'              => $this->aprendiz,
            'ficha_id'                 => $this->ficha,
            'usuario_id'               => $this->usuario,
        ]);
    }

    private function historial(int $evalId): array {
        $s = $this->db->prepare(
            "SELECT * FROM historial_evaluaciones WHERE evaluacion_id = ? ORDER BY id DESC"
        );
        $s->execute([$evalId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function evaluacion(int $id): array {
        $s = $this->db->prepare("SELECT * FROM evaluaciones WHERE id = ?");
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // =================================================================
    // CREACIÓN
    // =================================================================

    #[TestDox('crear una evaluación pendiente no genera historial')]
    public function testCrearPendienteSinHistorial(): void {
        $r = $this->registrar(['concepto' => 'pendiente']);

        $this->assertSame('creada', $r['accion']);
        $this->assertCount(0, $this->historial($r['evaluacion_id']),
            'una fila pendiente no es un juicio: registrarla sería ruido');
    }

    /**
     * Una nota pendiente no tiene fecha de evaluación. Ponerle la de hoy
     * hacía que los listados mostraran como «evaluado hoy» lo que nadie
     * había mirado.
     */
    #[TestDox('una evaluación pendiente no lleva fecha de evaluación')]
    public function testPendienteSinFecha(): void {
        $r = $this->registrar(['concepto' => 'pendiente']);
        $this->assertNull($this->evaluacion($r['evaluacion_id'])['fecha_evaluacion']);
    }

    /**
     * Es el agujero que dejaba el importador: al CREAR una evaluación con
     * nota no registraba nada, y de ahí salían miles de juicios sin rastro.
     */
    #[TestDox('crear una evaluación ya calificada SÍ deja constancia')]
    public function testCrearCalificadaConHistorial(): void {
        $r = $this->registrar(['concepto' => 'A']);
        $h = $this->historial($r['evaluacion_id']);

        $this->assertCount(1, $h, 'crear un juicio A sin historial era el fallo del importador');
        $this->assertSame('pendiente', $h[0]['concepto_anterior']);
        $this->assertSame('A', $h[0]['concepto_nuevo']);
    }

    // =================================================================
    // ACTUALIZACIÓN
    // =================================================================

    #[TestDox('calificar una pendiente deja constancia del cambio')]
    public function testCalificarPendiente(): void {
        $id = $this->registrar(['concepto' => 'pendiente'])['evaluacion_id'];
        $r = $this->registrar(['concepto' => 'A', 'comentario' => 'Buen trabajo']);

        $this->assertSame('actualizada', $r['accion']);
        $this->assertSame($id, $r['evaluacion_id'], 'debería reutilizar la fila, no crear otra');

        $h = $this->historial($id);
        $this->assertCount(1, $h);
        $this->assertSame(EvaluacionService::MOTIVO_INICIAL, $h[0]['motivo']);
    }

    #[TestDox('editar solo el comentario no genera una fila de historial')]
    public function testEditarComentarioSinHistorial(): void {
        $id = $this->registrar(['concepto' => 'A', 'comentario' => 'primero'])['evaluacion_id'];
        $antes = count($this->historial($id));

        $r = $this->registrar(['concepto' => 'A', 'comentario' => 'corregido']);

        $this->assertSame('sin_cambios', $r['accion']);
        $this->assertCount($antes, $this->historial($id), 'un cambio de comentario no es un cambio de juicio');
        $this->assertSame('corregido', $this->evaluacion($id)['comentario'], 'pero el comentario sí debe guardarse');
    }

    #[TestDox('editar el comentario no mueve la fecha de evaluación')]
    public function testEditarComentarioNoMueveLaFecha(): void {
        $id = $this->registrar(['concepto' => 'A', 'fecha_evaluacion' => '2026-01-15'])['evaluacion_id'];
        $this->registrar(['concepto' => 'A', 'comentario' => 'otro comentario']);

        $this->assertSame('2026-01-15', $this->evaluacion($id)['fecha_evaluacion'],
            'corregir una errata movía la fecha en que se evaluó');
    }

    // =================================================================
    // EXIGENCIA DE MOTIVO
    // =================================================================

    /**
     * Cambiar una nota ya emitida sin explicar por qué es exactamente lo
     * que RNF02 quiere impedir.
     */
    #[TestDox('cambiar un juicio ya emitido sin motivo se rechaza')]
    public function testCambioSinMotivoSeRechaza(): void {
        $id = $this->registrar(['concepto' => 'A'])['evaluacion_id'];

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessageMatches('/motivo/i');

        $this->registrar(['concepto' => 'D']);
    }

    #[TestDox('el rechazo no deja la nota a medio cambiar')]
    public function testElRechazoNoDejaRastro(): void {
        $id = $this->registrar(['concepto' => 'A'])['evaluacion_id'];
        $historialAntes = count($this->historial($id));

        try {
            $this->registrar(['concepto' => 'D']);
        } catch (ErrorDeNegocio) {
            // esperado
        }

        $this->assertSame('A', $this->evaluacion($id)['concepto'], 'el concepto cambió pese al rechazo');
        $this->assertCount($historialAntes, $this->historial($id));
    }

    #[TestDox('con motivo, el cambio se registra con su explicación')]
    public function testCambioConMotivo(): void {
        $id = $this->registrar(['concepto' => 'A'])['evaluacion_id'];
        $this->registrar(['concepto' => 'D', 'motivo' => 'Reevaluación tras revisión del portafolio']);

        $h = $this->historial($id);
        $this->assertSame('A', $h[0]['concepto_anterior']);
        $this->assertSame('D', $h[0]['concepto_nuevo']);
        $this->assertSame('Reevaluación tras revisión del portafolio', $h[0]['motivo']);
    }

    /**
     * Pasar de 'pendiente' a una nota es la primera calificación, no una
     * corrección: no tiene sentido exigir que se justifique.
     */
    #[TestDox('pasar de pendiente a una nota no exige motivo')]
    public function testDesdePendienteNoExigeMotivo(): void {
        $this->registrar(['concepto' => 'pendiente']);
        $r = $this->registrar(['concepto' => 'D']);
        $this->assertSame('actualizada', $r['accion']);
    }

    #[TestDox('exigir_motivo=false permite el cambio con un motivo generado')]
    public function testSinExigirMotivo(): void {
        $id = $this->registrar(['concepto' => 'A'])['evaluacion_id'];

        $this->registrar([
            'concepto' => 'D',
            'motivo' => 'Calificación de la evidencia: Informe final',
            'exigir_motivo' => false,
        ]);

        $this->assertStringContainsString('Informe final', $this->historial($id)[0]['motivo']);
    }

    // =================================================================
    // CAMINO DEL IMPORTADOR
    // =================================================================

    #[TestDox('el importador no borra el comentario que escribió un instructor')]
    public function testImportadorNoPisaComentarios(): void {
        $id = $this->registrar(['concepto' => 'A', 'comentario' => 'Observación del instructor'])['evaluacion_id'];

        // El reporte de Sofia Plus no trae comentarios: llega sin la clave.
        $this->registrar([
            'concepto' => 'D',
            'motivo' => 'Importado masivo',
            'exigir_motivo' => false,
            'retroalimentacion' => false,
        ]);

        $this->assertSame(
            'Observación del instructor',
            $this->evaluacion($id)['comentario'],
            'la importación borró el trabajo del instructor'
        );
    }

    #[TestDox('la nota se atribuye al instructor y el historial a quien importa')]
    public function testAtribucionSeparada(): void {
        $otro = $this->idCoordinador();

        $id = $this->registrar([
            'concepto' => 'A',
            'usuario_id' => $otro,          // quien sube el Excel
            'instructor_id' => $this->usuario,  // a quién se atribuye la nota
            'exigir_motivo' => false,
        ])['evaluacion_id'];

        $this->assertSame($this->usuario, (int)$this->evaluacion($id)['instructor_id'],
            'la nota debe atribuirse al instructor de la ficha');
        $this->assertSame($otro, (int)$this->historial($id)[0]['usuario_id'],
            'el historial debe registrar a quien ejecutó la importación');
    }

    #[TestDox('respeta la fecha del reporte en lugar de la de hoy')]
    public function testFechaDelReporte(): void {
        $id = $this->registrar([
            'concepto' => 'A',
            'fecha_evaluacion' => '2026-03-15',
            'exigir_motivo' => false,
        ])['evaluacion_id'];

        $this->assertSame('2026-03-15', $this->evaluacion($id)['fecha_evaluacion']);
    }

    // =================================================================
    // RETROALIMENTACIÓN
    // =================================================================

    #[TestDox('el comentario se refleja en la bandeja del aprendiz')]
    public function testGeneraRetroalimentacion(): void {
        $id = $this->registrar(['concepto' => 'A', 'comentario' => 'Excelente trabajo'])['evaluacion_id'];

        $this->assertSame(1, $this->contar('retroalimentacion', 'evaluacion_id = ?', [$id]),
            'el comentario quedaba atrapado y el aprendiz no lo veía');
    }

    #[TestDox('el tipo de retroalimentación depende del concepto')]
    public function testTipoSegunConcepto(): void {
        $id = $this->registrar(['concepto' => 'A', 'comentario' => 'bien'])['evaluacion_id'];

        $tipo = $this->db->query(
            "SELECT tipo FROM retroalimentacion WHERE evaluacion_id = $id ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $this->assertSame('fortaleza', $tipo);
    }

    #[TestDox('retroalimentacion=false la deja para quien la escriba aparte')]
    public function testSinRetroalimentacion(): void {
        $id = $this->registrar([
            'concepto' => 'A',
            'comentario' => 'texto',
            'retroalimentacion' => false,
        ])['evaluacion_id'];

        $this->assertSame(0, $this->contar('retroalimentacion', 'evaluacion_id = ?', [$id]));
    }

    #[TestDox('un comentario vacío no genera retroalimentación')]
    public function testComentarioVacio(): void {
        $id = $this->registrar(['concepto' => 'A', 'comentario' => '   '])['evaluacion_id'];
        $this->assertSame(0, $this->contar('retroalimentacion', 'evaluacion_id = ?', [$id]));
    }

    // =================================================================
    // VALIDACIÓN Y ATOMICIDAD
    // =================================================================

    #[TestDox('un concepto fuera del enum se rechaza antes de tocar la base')]
    public function testConceptoInvalido(): void {
        $this->expectException(ErrorDeNegocio::class);
        $this->registrar(['concepto' => 'Z']);
    }

    #[TestDox('sin usuario no se puede registrar nada')]
    public function testSinUsuario(): void {
        $this->expectException(ErrorDeNegocio::class);
        $this->servicio->registrar([
            'resultado_aprendizaje_id' => $this->rap,
            'aprendiz_id' => $this->aprendiz,
            'ficha_id' => $this->ficha,
            'concepto' => 'A',
            'usuario_id' => 0,
        ]);
    }

    #[TestDox('faltando identificadores se rechaza')]
    public function testDatosIncompletos(): void {
        $this->expectException(ErrorDeNegocio::class);
        $this->servicio->registrar([
            'resultado_aprendizaje_id' => 0,
            'aprendiz_id' => $this->aprendiz,
            'ficha_id' => $this->ficha,
            'concepto' => 'A',
            'usuario_id' => $this->usuario,
        ]);
    }

    #[TestDox('actualizarPorId con un id inexistente falla sin efectos')]
    public function testActualizarInexistente(): void {
        $this->expectException(ErrorDeNegocio::class);
        $this->servicio->actualizarPorId(999999999, ['concepto' => 'A', 'usuario_id' => $this->usuario]);
    }

    /**
     * El servicio participa en la transacción de quien lo llama en vez de
     * abrir otra: PDO no anida, y abrir una segunda cerraría la del
     * llamador a mitad de su trabajo.
     */
    #[TestDox('participa en la transacción del llamador en vez de anidar')]
    public function testParticipaEnLaTransaccionAbierta(): void {
        $this->assertTrue($this->db->inTransaction(), 'la prueba corre dentro de una transacción');

        $r = $this->registrar(['concepto' => 'A']);

        $this->assertTrue($this->db->inTransaction(),
            'el servicio cerró la transacción del llamador');
        $this->assertGreaterThan(0, $r['evaluacion_id']);
    }

    // =================================================================
    // IDENTIDAD DE LA FILA
    // =================================================================

    /**
     * El índice UNIQUE real es (rap, aprendiz), sin la ficha. Buscar
     * incluyendo la ficha hacía que tras un traslado no se encontrara la
     * fila y el INSERT reventara contra el índice.
     */
    #[TestDox('encuentra la evaluación aunque el aprendiz haya cambiado de ficha')]
    public function testBusquedaPorLaClaveReal(): void {
        $id = $this->registrar(['concepto' => 'A'])['evaluacion_id'];

        $otraFicha = (int)$this->db->query(
            "SELECT id FROM fichas WHERE id <> {$this->ficha} LIMIT 1"
        )->fetchColumn();

        if ($otraFicha === 0) {
            $this->markTestSkipped('hace falta una segunda ficha');
        }

        // Se registra indicando otra ficha: debe actualizar la fila que ya
        // existe, no intentar crear una segunda.
        $r = $this->servicio->registrar([
            'resultado_aprendizaje_id' => $this->rap,
            'aprendiz_id' => $this->aprendiz,
            'ficha_id' => $otraFicha,
            'concepto' => 'D',
            'motivo' => 'traslado',
            'usuario_id' => $this->usuario,
        ]);

        $this->assertSame($id, $r['evaluacion_id'], 'creó una fila duplicada en vez de reutilizar');
        $this->assertSame('actualizada', $r['accion']);
    }
}
