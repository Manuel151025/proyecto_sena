<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Exportacion\Exportador;
use Core\Formularios\FichaFormulario;
use Core\Models\FasesModel;
use Core\Models\FichaModel;
use Core\Services\Auditoria;
use Core\Services\FichasService;
use Core\Services\InstructorAccessService;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\ErrorDeNegocio;
use Core\Support\Semaforo;
use Throwable;

/**
 * Fichas de formación.
 *
 *   GET  /fichas?search=&programa_id=&estado=         listado (coordinación e instructor)
 *   GET  /fichas/ver?id=                              detalle y seguimiento del proyecto
 *   GET  /fichas/exportar?id=&formato=                aprendices con su situación
 *   POST /fichas  action=crear|editar|eliminar        coordinación
 *
 * El aprendiz que entra a /fichas va directo a la suya.
 */
class FichaController extends BaseController {
    private FichaModel $fichas;
    private FichasService $servicio;
    private InstructorAccessService $acceso;

    public const ESTADOS = [
        'planeacion' => ['Planeación', 'primary'],
        'induccion'  => ['Inducción', 'info'],
        'ejecucion'  => ['Ejecución', 'warning'],
        'cierre'     => ['Cierre', 'success'],
    ];
    public const ESTADOS_APRENDIZ = [
        'matriculado'    => ['Matriculado', 'success'],
        'suspendido'     => ['Suspendido', 'warning'],
        'desertado'      => ['Desertado', 'danger'],
        'egresado'       => ['Egresado', 'info'],
        'etapa_practica' => ['Etapa práctica', 'primary'],
    ];

    public function __construct(?FichaModel $fichas = null, ?FichasService $servicio = null, ?InstructorAccessService $acceso = null) {
        $this->fichas = $fichas ?? new FichaModel();
        $this->servicio = $servicio ?? new FichasService();
        $this->acceso = $acceso ?? new InstructorAccessService();
    }

    public function index(): void {
        $actor = Actor::actual();
        if ($actor->esAprendiz()) {
            $id = $this->fichas->getFichaIdByUsuarioId($actor->id);
            $id ? $this->irA('/fichas/ver?id=' . $id) : denyAccess('No estás matriculado en ninguna ficha.');
        }
        $filtros = $this->filtros();
        $errors = [];
        $fichas = $programas = $instructores = $proyectos = [];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->fichas->contar($actor, $filtros), 24);
            $fichas = $this->fichas->listar($actor, $filtros, $paginacion->perPage(), $paginacion->offset());
            $programas = $this->fichas->getProgramasActivos();
            if ($actor->esCoordinador()) {
                $instructores = $this->fichas->getInstructoresActivos();
                $proyectos = $this->fichas->getProyectosActivos();
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las fichas');
        }
        $this->render(BASE_PATH . 'modules/fichas/views/index.view.php', [
            'errors'        => $errors,
            'fichas'        => $fichas,
            'paginacion'    => $paginacion,
            'filtros'       => $filtros,
            'programas'     => $programas,
            'instructores'  => $instructores,
            'proyectos'     => $proyectos,
            'esCoordinador' => $actor->esCoordinador(),
            'estados_label' => self::ESTADOS,
        ], 'Fichas de formación · SENA');
    }

    public function ver(): void {
        $actor = Actor::actual();
        $id = $this->idDeConsulta('id');
        $this->exigirAccesoFicha($actor, $id);

        $ficha = $this->fichas->detalle($id);
        if ($ficha === null) {
            $this->fallo('La ficha no existe.', '/fichas');
        }
        $aprendices = $this->fichas->aprendicesConIndicadores($id);
        $fases = $ficha['proyecto_id'] ? (new FasesModel())->listarDeProyecto((int)$ficha['proyecto_id'], [$id]) : [];

        // El aprendiz ve su propia fila, no la situación de sus compañeros.
        if ($actor->esAprendiz()) {
            $aprendices = array_values(array_filter($aprendices, static fn($a) => (int)$a['usuario_id'] === $actor->id));
        }
        $conteo = array_count_values(array_map(static fn($a) => $a['semaforo'], $aprendices));

        $this->render(BASE_PATH . 'modules/fichas/views/ver.view.php', [
            'ficha'            => $ficha,
            'aprendices'       => $aprendices,
            'fases'            => $fases,
            'conteo'           => $conteo,
            'rol'              => $actor->rol,
            'estados_label'    => self::ESTADOS,
            'estados_aprendiz' => self::ESTADOS_APRENDIZ,
        ], 'Ficha ' . $ficha['numero_ficha'] . ' · SENA');
    }

    /**
     * Sin `id`: el listado de fichas con sus indicadores (con los filtros de
     * la pantalla). Con `id`: los aprendices de esa ficha y su situación.
     */
    public function exportar(): never {
        $actor = Actor::actual();
        $id = $this->idDeConsulta('id');
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $formato = ($_GET['formato'] ?? '') === 'csv' ? 'csv' : 'xlsx';
        if ($id === 0) {
            $this->exportarListado($actor, $formato);
        }
        $this->exigirAccesoFicha($actor, $id);
        $ficha = $this->fichas->detalle($id) ?? $this->fallo('La ficha no existe.', '/fichas');

        $filas = array_map(static fn($a) => [
            $a['nombre'], $a['tipo_documento'] . ' ' . $a['numero_documento'], $a['email'],
            self::ESTADOS_APRENDIZ[$a['estado']][0] ?? $a['estado'],
            (int)$a['aprobados'], (int)$a['en_d'], (int)$a['pendientes'],
            $a['pct_a'] !== null ? (float)$a['pct_a'] : '', Semaforo::etiqueta($a['semaforo']), (int)$a['planes_abiertos'],
        ], $this->fichas->aprendicesConIndicadores($id));
        $enc = ['Aprendiz', 'Documento', 'Correo', 'Estado', 'RAP en A', 'RAP en D', 'Pendientes', '% A sobre evaluados', 'Semáforo', 'Planes abiertos'];

        (new Auditoria())->operacion($actor, 'Exportar', 'Fichas', 'fichas', $id, 'Exportó los aprendices de la ficha ' . $ficha['numero_ficha']);
        $nombre = 'ficha_' . $ficha['numero_ficha'] . '_' . date('Ymd') . '.' . $formato;
        Exportador::descargar(
            $formato === 'csv'
                ? Exportador::csv($nombre, $enc, $filas)
                : Exportador::xlsx('Ficha ' . $ficha['numero_ficha'], $enc, $filas, [
                    'titulo' => 'Ficha ' . $ficha['numero_ficha'] . ' · ' . $ficha['programa'] . ' · ' . date('d/m/Y'),
                    'anchos' => [34, 18, 32, 14, 9, 9, 11, 12, 12, 10]]),
            $nombre, $formato);
    }

    private function exportarListado(Actor $actor, string $formato): never {
        $pct = static fn($v) => $v !== null ? (float)$v : '';
        $filas = array_map(static fn($f) => [
            $f['numero_ficha'], $f['codigo_programa'] . ' — ' . $f['programa'], $f['instructor'],
            self::ESTADOS[$f['estado']][0] ?? $f['estado'], $f['fecha_inicio'] ?? '', $f['fecha_fin'] ?? '',
            (int)$f['aprendices_activos'], (int)$f['aprobados'], (int)$f['en_d'], $pct($f['pct_a']), Semaforo::etiqueta($f['semaforo']), $pct($f['cumplimiento']),
            $f['proyecto_codigo'] ?? '', $pct($f['avance_proyecto']),
        ], $this->fichas->paraExportar($actor, $this->filtros(), Exportador::MAX_FILAS));
        $enc = ['Ficha', 'Programa', 'Instructor líder', 'Estado', 'Inicio', 'Fin', 'Aprendices activos',
                'RAP en A', 'RAP en D', '% A sobre evaluados', 'Semáforo', '% avance de RAP', 'Proyecto', '% avance proyecto'];

        (new Auditoria())->operacion($actor, 'Exportar', 'Fichas', 'fichas', null, count($filas) . " fichas exportadas en $formato");
        $nombre = 'fichas_' . date('Ymd_His') . '.' . $formato;
        Exportador::descargar(
            $formato === 'csv'
                ? Exportador::csv($nombre, $enc, $filas)
                : Exportador::xlsx('Fichas', $enc, $filas, [
                    'titulo' => 'Fichas de formación · ' . date('d/m/Y'),
                    'anchos' => [11, 40, 30, 12, 11, 11, 10, 9, 9, 12, 12, 12, 12, 12]]),
            $nombre, $formato);
    }

    /** Filtros del listado, compartidos por la pantalla y la exportación. */
    private function filtros(): array {
        return [
            'search'      => $this->consulta()->busquedaCruda('search'),
            'programa_id' => $this->idDeConsulta('programa_id'),
            'estado'      => in_array($_GET['estado'] ?? '', Enums::FICHA_ESTADO, true) ? $_GET['estado'] : '',
        ];
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $d = FichaFormulario::validar($v);
        $this->siHayErrores($v, '/fichas');
        $this->ejecutar(fn() => $this->servicio->crear($d, Actor::actual()), '/fichas', 'Ficha creada.', 'No se pudo crear la ficha');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La ficha');
        $d = FichaFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta('/fichas');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editar($id, $d, Actor::actual()), $vuelta,
            static fn(int $n) => 'Ficha actualizada.' . ($n > 0 ? " Se habilitaron $n evaluaciones pendientes para sus aprendices." : ''),
            'No se pudo actualizar la ficha');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La ficha');
        $this->siHayErrores($v, '/fichas');
        $this->ejecutar(fn() => $this->servicio->eliminar($id, Actor::actual()), '/fichas', 'Ficha eliminada.', 'No se pudo eliminar la ficha');
    }

    /**
     * El coordinador ve todas; el instructor, las de sus tres vías de
     * autoridad; el aprendiz, la suya. Antes el instructor solo podía abrir
     * las fichas que LIDERABA, aunque el listado le mostrara también las de
     * sus asignaciones: el enlace le llevaba a un "acceso denegado".
     */
    private function exigirAccesoFicha(Actor $actor, int $id): void {
        $ok = match (true) {
            $actor->esCoordinador() => $id > 0,
            $actor->esInstructor()  => $this->acceso->tieneAccesoFicha($id, $actor->id),
            default                 => $id > 0 && $this->fichas->getFichaIdByUsuarioId($actor->id) === $id,
        };
        if (!$ok) {
            denyAccess('No tienes acceso a esa ficha.');
        }
    }
}
