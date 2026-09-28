<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Exportacion\Exportador;
use Core\Formularios\MatriculaFormulario;
use Core\Models\AprendizModel;
use Core\Models\FichaModel;
use Core\Services\Auditoria;
use Core\Services\MatriculasService;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Matrículas.
 *
 *   GET  /matriculas?search=&ficha_id=&estado=     coordinación (todas) e instructor (las suyas)
 *   GET  /matriculas/exportar                      el mismo listado en XLSX o CSV
 *   POST /matriculas  action=matricular|editar|retirar   coordinación
 *
 * La carga masiva está en /matriculas/importar (ImportacionController).
 */
class MatriculaController extends BaseController {
    private const RUTA = '/matriculas';
    private const CLAVE_CREDENCIAL = 'credencial_matricula';

    private AprendizModel $aprendices;
    private MatriculasService $servicio;

    public function __construct(?AprendizModel $aprendices = null, ?MatriculasService $servicio = null) {
        $this->aprendices = $aprendices ?? new AprendizModel();
        $this->servicio = $servicio ?? new MatriculasService();
    }

    private function filtros(): array {
        return [
            'search'   => $this->consulta()->busquedaCruda('search'),
            'ficha_id' => $this->idDeConsulta('ficha_id'),
            'estado'   => in_array($_GET['estado'] ?? '', Enums::APRENDIZ_ESTADO, true) ? $_GET['estado'] : '',
        ];
    }

    public function index(): void {
        $actor = Actor::actual();
        $filtros = $this->filtros();
        $errors = [];
        $aprendices = $fichas = $instructores = [];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->aprendices->contar($filtros, $actor));
            $aprendices = $this->aprendices->listar($filtros, $actor, $paginacion->perPage(), $paginacion->offset());
            $fm = new FichaModel();
            $fichas = $fm->opciones($actor);
            $instructores = $fm->getInstructoresActivos();
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las matrículas');
        }
        $credencial = $_SESSION[self::CLAVE_CREDENCIAL] ?? null;
        unset($_SESSION[self::CLAVE_CREDENCIAL]);

        $this->render(BASE_PATH . 'modules/matriculas/views/index.view.php', [
            'errors'        => $errors,
            'aprendices'    => $aprendices,
            'paginacion'    => $paginacion,
            'filtros'       => $filtros,
            'fichas'        => $fichas,
            'instructores'  => $instructores,
            'credencial'    => $credencial,
            'esCoordinador' => $actor->esCoordinador(),
            'estados_label' => FichaController::ESTADOS_APRENDIZ,
            'tipos_doc'     => Enums::TIPO_DOCUMENTO,
        ], 'Matrículas · SENA');
    }

    public function exportar(): never {
        $actor = Actor::actual();
        $formato = ($_GET['formato'] ?? '') === 'csv' ? 'csv' : 'xlsx';
        $filas = array_map(static fn($a) => [
            $a['numero_ficha'], $a['nombre'], $a['tipo_documento'], $a['numero_documento'], $a['email'],
            FichaController::ESTADOS_APRENDIZ[$a['estado']][0] ?? $a['estado'], $a['genero'], $a['telefono'], $a['ciudad'],
            $a['fecha_matricula'] ? date('Y-m-d', strtotime((string)$a['fecha_matricula'])) : '',
        ], $this->aprendices->paraExportar($this->filtros(), $actor, Exportador::MAX_FILAS));
        $enc = ['Ficha', 'Aprendiz', 'Tipo doc.', 'Documento', 'Correo', 'Estado', 'Género', 'Teléfono', 'Ciudad', 'Matrícula'];
        (new Auditoria())->operacion($actor, 'Exportar', 'Matrículas', 'aprendices', null, count($filas) . " matrículas exportadas en $formato");
        $nombre = 'matriculas_' . date('Ymd_His') . '.' . $formato;
        Exportador::descargar($formato === 'csv' ? Exportador::csv($nombre, $enc, $filas)
            : Exportador::xlsx('Matrículas', $enc, $filas, ['titulo' => 'Matrículas · ' . date('d/m/Y'), 'anchos' => [10, 34, 8, 14, 32, 14, 8, 14, 16, 12]]),
            $nombre, $formato);
    }

    public function matricular(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $d = MatriculaFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        try {
            $r = $this->servicio->matricular($d, Actor::actual());
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo matricular'), $vuelta);
        }
        $_SESSION[self::CLAVE_CREDENCIAL] = ['nombre' => $d['nombre'], 'email' => $d['email'], 'password' => $r['temporal']];
        $this->exito('Aprendiz matriculado.' . ($r['habilitadas'] > 0 ? " Se habilitaron {$r['habilitadas']} evaluaciones pendientes." : ''), $vuelta);
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El aprendiz');
        $d = MatriculaFormulario::validar($v, true);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editar($id, $d, Actor::actual()), $vuelta, 'Matrícula actualizada.', 'No se pudo actualizar la matrícula');
    }

    public function retirar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El aprendiz');
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->retirar($id, Actor::actual()), $vuelta,
            'Matrícula retirada: el aprendiz queda como desertado y sin acceso. Su historial se conserva.', 'No se pudo retirar la matrícula');
    }
}
