<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Models\ReportesModel;
use Core\Services\Auditoria;
use Core\Services\ReportePdfService;
use Core\Services\SemaforoReporte;
use PDO;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Bitácora de auditoría y generación de reportes.
 *
 * La bitácora tenía una sola fila y ningún evento de seguridad: ni accesos,
 * ni fallos, ni cambios de contraseña, ni permisos denegados. Los reportes
 * en PDF no existían: el botón llamaba a `window.print()`.
 */
final class AuditoriaYReportesTest extends CasoConBaseDeDatos {

    // =================================================================
    // AUDITORÍA
    // =================================================================

    #[TestDox('los cinco eventos de seguridad quedan registrados')]
    public function testEventosDeSeguridad(): void {
        $a = new Auditoria($this->db);
        $uid = $this->idCoordinador();
        $antes = $this->contar('logs_sistema');

        $a->accesoCorrecto($uid, 'coordinador');
        $a->accesoFallido('atacante@dominio-externo.com');
        $a->cierreSesion($uid);
        $a->cambioPassword($uid, 'perfil');
        $a->permisoDenegado($uid, '/usuarios', 'Rol actual: aprendiz');

        $this->assertSame($antes + 5, $this->contar('logs_sistema'));

        $acciones = $this->db->query(
            "SELECT accion FROM logs_sistema ORDER BY id DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ([Auditoria::LOGIN, Auditoria::LOGIN_FALLIDO, Auditoria::LOGOUT,
                  Auditoria::PASSWORD, Auditoria::PERMISO_DENEGADO] as $esperada) {
            $this->assertContains($esperada, $acciones, "no se registró '$esperada'");
        }
    }

    /**
     * Un acceso denegado es la señal que delata a quien prueba rutas a
     * mano, y era justo lo que no se registraba.
     */
    #[TestDox('el permiso denegado guarda el recurso y el rol que lo intentó')]
    public function testDetalleDelPermisoDenegado(): void {
        (new Auditoria($this->db))->permisoDenegado($this->idUsuarioAprendiz(), '/usuarios', 'Rol actual: aprendiz');

        $stmt = $this->db->prepare(
            "SELECT descripcion, modulo FROM logs_sistema WHERE accion = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([Auditoria::PERMISO_DENEGADO]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertStringContainsString('/usuarios', $r['descripcion']);
        $this->assertStringContainsString('aprendiz', $r['descripcion']);
        $this->assertSame('Autorización', $r['modulo']);
    }

    #[TestDox('un evento previo al login se registra sin usuario')]
    public function testEventoSinUsuario(): void {
        (new Auditoria($this->db))->accesoFallido('x@y.com');

        $stmt = $this->db->prepare("SELECT usuario_id FROM logs_sistema WHERE accion = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([Auditoria::LOGIN_FALLIDO]);

        $this->assertNull($stmt->fetchColumn(), 'un intento fallido no tiene usuario identificado');
    }

    #[TestDox('los campos desmesurados se truncan en vez de reventar')]
    public function testTruncadoDeCampos(): void {
        $a = new Auditoria($this->db);
        $antes = $this->contar('logs_sistema');

        $a->registrar(
            $this->idCoordinador(),
            str_repeat('A', 500),
            str_repeat('M', 500),
            str_repeat('D', 5000)
        );

        $this->assertSame($antes + 1, $this->contar('logs_sistema'),
            'un texto largo impidió registrar el evento');
    }

    /**
     * Auditar no puede tumbar la operación auditada: perder una línea de
     * bitácora es malo, pero impedir que un instructor califique porque la
     * tabla de logs no responde es peor.
     */
    #[TestDox('un fallo al auditar no interrumpe la operación')]
    public function testAuditarNoLanza(): void {
        $a = new Auditoria($this->db);

        // Usuario inexistente: viola la clave foránea.
        $a->registrar(999999999, 'Crear', 'Prueba', 'con un usuario que no existe');

        $this->assertTrue(true, 'registrar() no debe propagar la excepción');
    }

    // =================================================================
    // REPORTES EN PDF
    // =================================================================

    #[TestDox('los cuatro reportes se generan como PDF válido')]
    public function testGeneracionDeLosCuatroReportes(): void {
        $modelo = new ReportesModel($this->db);
        $svc = new ReportePdfService();
        $coord = $this->idCoordinador();
        $ficha = $this->idFicha();

        $casos = [
            'evaluaciones_ficha' => [
                ['Aprendiz', 'Documento', 'RA Código', 'RA Denominación', 'Competencia', 'Concepto', 'Fecha', 'Instructor'],
                $modelo->getReportEvaluacionesFicha($ficha, $coord, ROL_COORDINADOR),
            ],
            'cumplimiento_instructor' => [
                ['Instructor', 'Ficha', 'Programa', 'Competencia', 'Total', 'A', 'D', 'Pend.', '%'],
                $modelo->getReportCumplimientoInstructor($coord, ROL_COORDINADOR),
            ],
            'cumplimiento_competencia' => [
                ['Programa', 'Competencia', 'Código', 'Total', 'A', 'D', '%'],
                $modelo->getReportCumplimientoCompetencia($coord, ROL_COORDINADOR),
            ],
            'historial_cambios' => [
                ['ID', 'Aprendiz', 'RA', 'Anterior', 'Nuevo', 'Motivo', 'Por', 'Fecha'],
                $modelo->getReportHistorialCambios($coord, ROL_COORDINADOR),
            ],
        ];

        foreach ($casos as $tipo => [$cabeceras, $datos]) {
            $pdf = $svc->generar(
                'Reporte de prueba',
                $cabeceras,
                array_slice($datos, 0, 60),   // acotado: aquí importa que salga, no el volumen
                SemaforoReporte::paraReporte($tipo) + ['generado_por' => 'Pruebas']
            );

            $this->assertStringStartsWith('%PDF-', $pdf, "$tipo no produjo un PDF");
            $this->assertGreaterThan(1000, strlen($pdf), "$tipo produjo un PDF sospechosamente pequeño");
        }
    }

    #[TestDox('un reporte sin registros produce un PDF con su aviso')]
    public function testReporteVacio(): void {
        $pdf = (new ReportePdfService())->generar('Sin datos', ['A', 'B'], []);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    #[TestDox('las tildes y los caracteres conflictivos no rompen el PDF')]
    public function testCaracteresEspeciales(): void {
        $pdf = (new ReportePdfService())->generar(
            'Ñandú · Evaluación',
            ['Nombre', 'Concepto'],
            [
                ['Muñoz Pérez, José Ángel', 'A'],
                ["O'Brien & Asociados <script>alert(1)</script>", 'D'],
                [str_repeat('Denominación muy larga ', 30), 'pendiente'],
            ],
            ['columnas_concepto' => [1]]
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    /**
     * dompdf mantiene en memoria el mapa de celdas de cada tabla y el coste
     * crece muy deprisa: una tabla única de 1.683 filas agotaba 512 MB.
     * Se emite una tabla por cada 22 filas.
     */
    #[TestDox('el reporte más grande se genera sin agotar la memoria')]
    public function testVolumenGrande(): void {
        $modelo = new ReportesModel($this->db);
        $coord = $this->idCoordinador();

        $fichaMayor = (int)$this->db->query("
            SELECT ficha_id FROM evaluaciones GROUP BY ficha_id ORDER BY COUNT(*) DESC LIMIT 1
        ")->fetchColumn();

        $datos = $modelo->getReportEvaluacionesFicha($fichaMayor, $coord, ROL_COORDINADOR);
        if (count($datos) < 200) {
            $this->markTestSkipped('no hay un reporte lo bastante grande para esta prueba');
        }

        $pdf = (new ReportePdfService())->generar(
            'Volumen',
            ['Aprendiz', 'Doc', 'RA', 'Denominación', 'Competencia', 'Concepto', 'Fecha', 'Instructor'],
            $datos,
            SemaforoReporte::paraReporte('evaluaciones_ficha')
        );

        $this->assertStringStartsWith('%PDF-', $pdf);

        // Lo que se comprueba es que quepa en el límite que el propio
        // servicio se concede (512 MB). Sin trocear la tabla, este mismo
        // reporte lo agotaba y abortaba.
        $this->assertLessThan(
            512 * 1024 * 1024,
            memory_get_peak_usage(true),
            'la generación del reporte grande se sale del límite que el servicio fija'
        );

        // Y que el troceado siga haciendo su trabajo: ~22 filas por página.
        $paginas = preg_match_all('#/Type\s*/Page[^s]#', $pdf);
        $this->assertGreaterThan(0, $paginas);
        $this->assertLessThan(
            count($datos) / 10,
            $paginas,
            'salen demasiadas páginas: el reparto de anchos dejó de compactar las filas'
        );
    }

    #[TestDox('la caché de fuentes se escribe fuera de vendor/')]
    public function testCacheFueraDeVendor(): void {
        (new ReportePdfService())->generar('x', ['a'], [['1']]);

        $enVendor = glob(dirname(__DIR__, 2) . '/vendor/dompdf/dompdf/lib/fonts/*.ufm.json');
        $this->assertSame([], $enVendor ?: [],
            'dompdf escribe en vendor/, que composer reinstala en cada despliegue');

        $this->assertDirectoryExists(dirname(__DIR__, 2) . '/cache/dompdf');
    }
}
