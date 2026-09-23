<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\CompetenciaFormulario;
use Core\Models\CompetenciasModel;
use Core\Models\ProgramasModel;
use Core\Models\ResultadosAprendizajeModel;
use Core\Services\CompetenciasService;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Resultados de aprendizaje (RAP), agrupados por competencia.
 *
 *   GET  /resultados-aprendizaje?programa_id=&search=     gestión (lectura)
 *   POST /resultados-aprendizaje  action=crear|editar|eliminar   coordinación
 */
class ResultadosAprendizajeController extends BaseController {
    private ResultadosAprendizajeModel $modelo;
    private CompetenciasService $servicio;

    public function __construct(?ResultadosAprendizajeModel $modelo = null, ?CompetenciasService $servicio = null) {
        $this->modelo = $modelo ?? new ResultadosAprendizajeModel();
        $this->servicio = $servicio ?? new CompetenciasService();
    }

    public function index(): void {
        $programaId = $this->idDeConsulta('programa_id');
        $busqueda = $this->consulta()->busquedaCruda('search');
        $errors = [];
        $competencias = $programas = $opciones = [];
        try {
            $programas = (new ProgramasModel())->opciones(false);
            // Sin filtro, el primer programa: pintar todos los RAP del centro
            // de una vez no escala (hoy 194, y crece con cada programa).
            if ($programaId === 0 && $busqueda === '' && $programas !== []) {
                $programaId = (int)$programas[0]['id'];
            }
            $competencias = $this->modelo->competenciasConRaps($programaId ?: null, $busqueda);
            if ($this->esRol(ROL_COORDINADOR)) {
                $opciones = (new CompetenciasModel())->opciones();
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar los resultados de aprendizaje');
        }
        $this->render(BASE_PATH . 'modules/resultados-aprendizaje/views/index.view.php', [
            'errors'       => $errors,
            'competencias' => $competencias,
            'programas'    => $programas,
            'opciones'     => $opciones,
            'programaId'   => $programaId,
            'busqueda'     => $busqueda,
            'puedeEditar'  => $this->esRol(ROL_COORDINADOR),
            'limites'      => ['codigo' => CompetenciaFormulario::MAX_CODIGO_RAP, 'texto' => CompetenciaFormulario::MAX_DENOMINACION],
        ], 'Resultados de Aprendizaje · SENA');
    }

    public function crear(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $d = CompetenciaFormulario::validarRap($v);
        $vuelta = $this->rutaDeVuelta('/resultados-aprendizaje');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->crearRap($d, Actor::actual()), $vuelta,
            static function (array $r): string {
                $m = 'Resultado de aprendizaje registrado.';
                if ($r['habilitadas'] > 0) {
                    $m .= " Se habilitó su evaluación para {$r['habilitadas']} aprendiz(ces) ya matriculados.";
                }
                if ($r['sin_instructor'] > 0) {
                    $m .= " {$r['sin_instructor']} quedaron sin habilitar en fichas sin instructor líder.";
                }
                return $m;
            }, 'No se pudo registrar el resultado');
    }

    public function editar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El resultado de aprendizaje');
        $d = CompetenciaFormulario::validarRap($v);
        $vuelta = $this->rutaDeVuelta('/resultados-aprendizaje');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->editarRap($id, $d, Actor::actual()), $vuelta,
            'Resultado de aprendizaje actualizado.', 'No se pudo actualizar el resultado');
    }

    public function eliminar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $id = $v->id('id', 'El resultado de aprendizaje');
        $vuelta = $this->rutaDeVuelta('/resultados-aprendizaje');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => $this->servicio->eliminarRap($id, Actor::actual()), $vuelta,
            'Resultado de aprendizaje eliminado.', 'No se pudo eliminar el resultado');
    }
}
