<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\CompetenciaFormulario;
use Core\Models\CompetenciasModel;
use Core\Models\ProgramasModel;
use Core\Services\CompetenciasService;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\Enums;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Competencias de formación.
 *
 *   GET  /competencias?search=&programa_id=&estado=&pagina=   gestión (lectura)
 *   POST /competencias  action=crear|editar|eliminar          coordinación
 */
class CompetenciasController extends BaseController {
    private const RUTA = '/competencias';

    private CompetenciasModel $modelo;
    private CompetenciasService $servicio;

    public function __construct(?CompetenciasModel $modelo = null, ?CompetenciasService $servicio = null) {
        $this->modelo = $modelo ?? new CompetenciasModel();
        $this->servicio = $servicio ?? new CompetenciasService();
    }

    public function index(): void {
        $filtros = [
            'search'      => $this->consulta()->busquedaCruda('search'),
            'programa_id' => $this->idDeConsulta('programa_id'),
            'estado'      => in_array($_GET['estado'] ?? '', Enums::COMPETENCIA_ESTADO, true) ? $_GET['estado'] : '',
        ];
        $errors = [];
        $competencias = $programas = [];
        $paginacion = null;
        try {
            $paginacion = Paginator::desdePeticion($this->modelo->contar($filtros));
            $competencias = $this->modelo->listar($filtros, $paginacion->perPage(), $paginacion->offset());
            $programas = (new ProgramasModel())->opciones(false);
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las competencias');
        }
        $this->render(BASE_PATH . 'modules/competencias/views/index.view.php', [
            'errors'       => $errors,
            'competencias' => $competencias,
            'programas'    => $programas,
            'filtros'      => $filtros,
            'paginacion'   => $paginacion,
            'puedeEditar'  => $this->esRol(ROL_COORDINADOR),
            'limites'      => ['nombre' => CompetenciaFormulario::MAX_NOMBRE, 'codigo' => CompetenciaFormulario::MAX_CODIGO,
                               'texto' => CompetenciaFormulario::MAX_DESCRIPCION, 'horas' => CompetenciaFormulario::MAX_HORAS],
        ], 'Competencias · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $d = CompetenciaFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->crearCompetencia($d, Actor::actual()), $vuelta, 'Competencia registrada.', 'No se pudo registrar la competencia');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La competencia');
        $d = CompetenciaFormulario::validar($v);
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editarCompetencia($id, $d, Actor::actual()), $vuelta, 'Competencia actualizada.', 'No se pudo actualizar la competencia');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'La competencia');
        $vuelta = $this->rutaDeVuelta(self::RUTA);
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->eliminarCompetencia($id, Actor::actual()), $vuelta, 'Competencia eliminada.', 'No se pudo eliminar la competencia');
    }
}
