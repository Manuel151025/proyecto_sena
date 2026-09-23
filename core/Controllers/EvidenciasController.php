<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Formularios\EvidenciaFormulario;
use Core\Models\EvidenciasModel;
use Core\Router;
use Core\Services\EvidenciasService;
use Core\Services\Paginator;
use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\Descarga;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Evidencias de aprendizaje.
 *
 *   GET  /evidencias?search=&estado=&ficha_id=   todos (cada rol ve lo suyo)
 *   GET  /evidencias/archivo?id=                 descarga con permiso
 *   POST /evidencias  action=enviar              aprendiz
 *   POST /evidencias  action=revisar             instructor y coordinación
 *   POST /evidencias  action=eliminar            aprendiz (la suya, sin revisar) y coordinación
 */
class EvidenciasController extends BaseController {
    public const ESTADOS = [
        'enviada'   => ['Por revisar', 'info'],
        'revisada'  => ['Requiere ajustes', 'warning'],
        'aprobada'  => ['Aprobada', 'success'],
        'rechazada' => ['Rechazada', 'danger'],
    ];

    private EvidenciasModel $modelo;

    public function __construct(?EvidenciasModel $modelo = null) {
        $this->modelo = $modelo ?? new EvidenciasModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $estado = (string)($_GET['estado'] ?? '');
        $filtros = [
            'search'   => $this->consulta()->busquedaCruda('search'),
            'estado'   => array_key_exists($estado, self::ESTADOS) ? $estado : '',
            'ficha_id' => $this->idDeConsulta('ficha_id'),
        ];
        $errors = [];
        $evidencias = $fichas = $raps = [];
        $paginacion = null;
        $porRevisar = 0;
        try {
            $paginacion = Paginator::desdePeticion($this->modelo->contar($actor, $filtros), 20);
            $evidencias = $this->modelo->listar($actor, $filtros, $paginacion->perPage(), $paginacion->offset());
            $fichas = $this->modelo->fichasDelActor($actor);
            $porRevisar = $actor->gestiona() ? $this->modelo->porRevisar($actor) : 0;
            if ($actor->esAprendiz() && ($ap = $this->modelo->aprendizDeUsuario($actor->id))) {
                $raps = $this->modelo->rapsDelAprendiz((int)$ap['id']);
            }
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar las evidencias');
        }
        $this->render(BASE_PATH . 'modules/evidencias/views/index.view.php', [
            'errors'      => $errors,
            'evidencias'  => $evidencias,
            'fichas'      => $fichas,
            'raps'        => $raps,
            'filtros'     => $filtros,
            'paginacion'  => $paginacion,
            'porRevisar'  => $porRevisar,
            'estados'     => self::ESTADOS,
            'actor'       => $actor,
        ], 'Evidencias · SENA');
    }

    public function enviar(): never {
        $this->exigirRol(ROL_APRENDIZ);
        $v = $this->entrada();
        $d = EvidenciaFormulario::validarEnvio($v);
        $this->siHayErrores($v, '/evidencias');
        $this->ejecutar(function () use ($d) {
            $campo = $_FILES['archivo'] ?? null;
            $archivo = ($campo !== null && (int)($campo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
                ? ArchivoSubido::desde($campo, EvidenciaFormulario::EXTENSIONES, EvidenciaFormulario::MAX_MB, 'El archivo')
                : null;
            return (new EvidenciasService())->enviar($d, $archivo, Actor::actual());
        }, '/evidencias', 'Evidencia enviada: tu instructor recibió un aviso.', 'No se pudo enviar la evidencia');
    }

    public function revisar(): never {
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $d = EvidenciaFormulario::validarRevision($v);
        $vuelta = $this->rutaDeVuelta('/evidencias');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new EvidenciasService())->revisar($d, Actor::actual()), $vuelta,
            'Evidencia revisada; el aprendiz recibió la retroalimentación.', 'No se pudo revisar la evidencia');
    }

    public function eliminar(): never {
        $v = $this->entrada();
        $id = $v->id('id', 'La evidencia');
        $vuelta = $this->rutaDeVuelta('/evidencias');
        $this->siHayErrores($v, $vuelta);
        $this->ejecutar(fn() => (new EvidenciasService())->eliminar($id, Actor::actual()), $vuelta,
            'Evidencia eliminada.', 'No se pudo eliminar la evidencia');
    }

    public function archivo(): never {
        try {
            [$ruta, $nombre, $ext] = (new EvidenciasService())->archivo($this->idDeConsulta('id'), Actor::actual());
        } catch (ErrorDeNegocio $e) {
            Router::responder(404, 'Archivo no disponible', $e->getMessage());
            exit;
        }
        Descarga::archivo($ruta, $nombre, $ext);
    }
}
