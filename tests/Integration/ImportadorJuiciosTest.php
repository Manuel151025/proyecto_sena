<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Importacion\ImportacionService;
use Core\Importacion\ImportadorJuicios;
use Core\Services\EvaluacionesSyncService;
use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Importación del reporte de juicios de Sofia Plus.
 *
 * Se arma un reporte con la forma del real (cabecera con ficha y programa,
 * fila de títulos largos, una fila por aprendiz y RAP) sobre una
 * competencia creada para la prueba. Todo se revierte al terminar.
 */
final class ImportadorJuiciosTest extends CasoConBaseDeDatos {
    private string $carpeta;
    private array $ficha;
    private array $aprendices;
    private array $raps;

    protected function setUp(): void {
        parent::setUp();
        $_SESSION = [];
        $this->carpeta = sys_get_temp_dir() . '/sena_previas_' . bin2hex(random_bytes(4));
        $this->ficha = $this->db->query("
            SELECT f.id, f.numero_ficha, f.programa_id, f.instructor_id, p.codigo AS programa_codigo
              FROM fichas f JOIN programas p ON p.id = f.programa_id
             WHERE (SELECT COUNT(*) FROM aprendices a WHERE a.ficha_id = f.id AND a.estado = 'matriculado') >= 2
             LIMIT 1")->fetch() ?: $this->markTestSkipped('no hay una ficha con dos aprendices');
        $this->aprendices = $this->db->query("SELECT id, numero_documento FROM aprendices
            WHERE ficha_id = {$this->ficha['id']} AND estado = 'matriculado' ORDER BY id LIMIT 2")->fetchAll();

        // Competencia y RAP propios, con los códigos de Sofia Plus.
        $this->db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, estado) VALUES (?, '999000001', 'Competencia de prueba', 'activo')")
                 ->execute([$this->ficha['programa_id']]);
        $comp = (int)$this->db->lastInsertId();
        foreach (['01', '02'] as $n) {
            $this->db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)")
                     ->execute([$comp, "999000001-$n", "Resultado de prueba $n"]);
            $this->raps[$n] = (int)$this->db->lastInsertId();
        }
        (new EvaluacionesSyncService($this->db))->sincronizar(['ficha_id' => (int)$this->ficha['id']]);
    }

    protected function tearDown(): void {
        foreach (glob($this->carpeta . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->carpeta);
        parent::tearDown();
    }

    /** Reporte con la forma del de Sofia Plus. */
    private function reporte(array $filas, ?string $ficha = null): ArchivoSubido {
        $lineas = [
            'Reporte de Juicios Evaluativos',
            '',
            'Ficha de Caracterización:;;' . ($ficha ?? $this->ficha['numero_ficha']),
            'Código:;;' . $this->ficha['programa_codigo'],
            'Versión:;;1',
            'Denominación:;;PROGRAMA',
            '',
            'Fecha Inicio:;;01/02/2025',
            'Fecha Fin:;;01/02/2027',
            'Tipo de Documento;Número de Documento;Nombre;Apellidos;Estado;Competencia;Resultado de Aprendizaje;Juicio de Evaluación;Fecha y Hora del Juicio Evaluativo;Funcionario que registro el juicio evaluativo',
        ];
        foreach ($filas as [$doc, $rap, $juicio]) {
            $lineas[] = "CC;$doc;NOMBRE;APELLIDO;EN FORMACION;999000001 - Competencia de prueba;999000001 - $rap Resultado de prueba;$juicio;15/03/2025 10:00;CC 1 - INSTRUCTOR";
        }
        $ruta = tempnam(sys_get_temp_dir(), 'rep') . '.csv';
        file_put_contents($ruta, implode("\n", $lineas) . "\n");
        return ArchivoSubido::desde(['name' => 'reporte.csv', 'tmp_name' => $ruta, 'size' => filesize($ruta), 'error' => 0], ['csv'], 5);
    }

    private function concepto(int $aprendiz, int $rap): string {
        return (string)$this->db->query("SELECT concepto FROM evaluaciones WHERE aprendiz_id = $aprendiz AND resultado_aprendizaje_id = $rap")->fetchColumn();
    }

    public static function juicios(): array {
        return [
            'aprobado'            => ['APROBADO', 'A'],
            'no aprobado'         => ['NO APROBADO', 'D'],   // el fallo anterior: entraba como A
            'deficiente'          => ['Deficiente', 'D'],
            'por evaluar'         => ['POR EVALUAR', 'pendiente'],
            'vacío'               => ['', 'pendiente'],
            'letra A'             => ['A', 'A'],
            'desconocido'         => ['EXCELENTE', null],
        ];
    }

    #[DataProvider('juicios')]
    #[TestDox('interpreta el juicio del reporte: $texto')]
    public function testConcepto(string $texto, ?string $esperado): void {
        $this->assertSame($esperado, ImportadorJuicios::concepto($texto));
    }

    #[TestDox('reconoce los códigos de competencia y RAP de Sofia Plus')]
    public function testCodigos(): void {
        $this->assertSame(['220501046', 'Utilizar herramientas'], ImportadorJuicios::codigoCompetencia('220501046 - Utilizar herramientas'));
        $this->assertSame(['220501046-01', 'Configurar el equipo'], ImportadorJuicios::codigoRap('220501046 - 01 Configurar el equipo'));
        $this->assertSame(['220501046-03', 'Otro'], ImportadorJuicios::codigoRap('220501046-03 Otro'));
        $this->assertNull(ImportadorJuicios::codigoRap('Sin código'));
    }

    #[TestDox('el líder importa: emite A y D, deja lo pendiente y no toca lo que no existe')]
    public function testImportaComoLider(): void {
        [$a1, $a2] = $this->aprendices;
        $lider = new Actor((int)$this->ficha['instructor_id'], ROL_INSTRUCTOR);
        $imp = new ImportadorJuicios($this->db);
        $servicio = new ImportacionService($this->carpeta);

        $previa = $servicio->analizar($imp, $this->reporte([
            [$a1['numero_documento'], '01', 'APROBADO'],
            [$a1['numero_documento'], '02', 'NO APROBADO'],
            [$a2['numero_documento'], '01', 'POR EVALUAR'],
            [$a2['numero_documento'], '99', 'APROBADO'],
            ['999999999', '01', 'APROBADO'],
        ]), $lider, ['ficha_id' => 0]);

        $this->assertSame(5, $previa['total']);
        $this->assertSame(3, $previa['validas']);
        $errores = implode(' | ', array_merge(...array_column($previa['filas'], 'errores')));
        $this->assertStringContainsString('999000001-99 de la competencia 999000001 no existe', $errores);
        $this->assertStringContainsString('no está matriculado en la ficha', $errores);

        $r = $servicio->confirmar(new ImportadorJuicios($this->db), $lider);
        $this->assertSame(2, $r['creados']);
        $this->assertSame('A', $this->concepto((int)$a1['id'], $this->raps['01']));
        $this->assertSame('D', $this->concepto((int)$a1['id'], $this->raps['02']));
        $this->assertSame('pendiente', $this->concepto((int)$a2['id'], $this->raps['01']));
        $st = $this->db->prepare("SELECT COUNT(*) FROM historial_evaluaciones h JOIN evaluaciones e ON e.id = h.evaluacion_id
                                   WHERE e.aprendiz_id = ? AND e.resultado_aprendizaje_id IN (?, ?) AND h.motivo = ?");
        $st->execute([(int)$a1['id'], $this->raps['01'], $this->raps['02'], ImportadorJuicios::MOTIVO]);
        $this->assertSame(2, (int)$st->fetchColumn(), 'cada juicio importado deja su fila de historial');
        $this->assertNull($servicio->pendiente($imp, $lider), 'la vista previa se descarta al confirmar');
    }

    #[TestDox('un «POR EVALUAR» del reporte no borra un juicio ya emitido')]
    public function testNoBorraJuiciosEmitidos(): void {
        [$a1] = $this->aprendices;
        $coord = new Actor($this->idCoordinador(), ROL_COORDINADOR);
        $servicio = new ImportacionService($this->carpeta);
        $servicio->analizar(new ImportadorJuicios($this->db), $this->reporte([[$a1['numero_documento'], '01', 'APROBADO']]), $coord, ['ficha_id' => 0]);
        $servicio->confirmar(new ImportadorJuicios($this->db), $coord);

        $previa = $servicio->analizar(new ImportadorJuicios($this->db), $this->reporte([[$a1['numero_documento'], '01', 'POR EVALUAR']]), $coord, ['ficha_id' => 0]);
        $this->assertSame('sin_cambios', $previa['filas'][0]['datos']['accion']);
        $this->assertStringContainsString('no se borra', implode(' ', $previa['filas'][0]['avisos']));
        $servicio->confirmar(new ImportadorJuicios($this->db), $coord);
        $this->assertSame('A', $this->concepto((int)$a1['id'], $this->raps['01']));
    }

    #[TestDox('un RAP asignado a otro instructor no lo carga el líder')]
    public function testRespetaAsignaciones(): void {
        [$a1] = $this->aprendices;
        $otro = (int)$this->db->query("SELECT id FROM usuarios WHERE rol = 'instructor' AND estado = 'activo' AND id <> {$this->ficha['instructor_id']} LIMIT 1")->fetchColumn();
        $comp = (int)$this->db->query("SELECT id FROM competencias WHERE codigo = '999000001' AND programa_id = {$this->ficha['programa_id']}")->fetchColumn();
        $this->db->prepare("INSERT INTO asignaciones (ficha_id, competencia_id, instructor_id) VALUES (?, ?, ?)")->execute([$this->ficha['id'], $comp, $otro]);

        $previa = (new ImportacionService($this->carpeta))->analizar(new ImportadorJuicios($this->db),
            $this->reporte([[$a1['numero_documento'], '01', 'APROBADO']]), new Actor((int)$this->ficha['instructor_id'], ROL_INSTRUCTOR), ['ficha_id' => 0]);
        $this->assertSame(0, $previa['validas']);
        $this->assertStringContainsString('otro instructor', $previa['filas'][0]['errores'][0]);
    }

    #[TestDox('un instructor sin la ficha no puede importar sus juicios')]
    public function testInstructorAjeno(): void {
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('No tienes a cargo la ficha');
        (new ImportacionService($this->carpeta))->analizar(new ImportadorJuicios($this->db),
            $this->reporte([[$this->aprendices[0]['numero_documento'], '01', 'APROBADO']]),
            new Actor($this->idInstructorAjeno(), ROL_INSTRUCTOR), ['ficha_id' => 0]);
    }

    #[TestDox('una ficha que no existe no se crea desde el reporte')]
    public function testFichaInexistente(): void {
        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('no existe en el sistema');
        (new ImportacionService($this->carpeta))->analizar(new ImportadorJuicios($this->db),
            $this->reporte([[$this->aprendices[0]['numero_documento'], '01', 'APROBADO']], '99887766'),
            new Actor($this->idCoordinador(), ROL_COORDINADOR), ['ficha_id' => 0]);
    }

    #[TestDox('la vista previa no viaja en la sesión: solo su referencia')]
    public function testPreviaFueraDeLaSesion(): void {
        $coord = new Actor($this->idCoordinador(), ROL_COORDINADOR);
        (new ImportacionService($this->carpeta))->analizar(new ImportadorJuicios($this->db),
            $this->reporte([[$this->aprendices[0]['numero_documento'], '01', 'APROBADO']]), $coord, ['ficha_id' => 0]);
        $ref = $_SESSION['importaciones']['juicios'];
        $this->assertSame(['archivo', 'actor', 'creada'], array_keys($ref));
        $this->assertFileExists($this->carpeta . '/' . $ref['archivo']);
    }
}
