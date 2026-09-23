<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\AnaliticaModel;
use Core\Models\FichaModel;
use Core\Models\MejoramientoModel;
use Core\Models\RetroalimentacionModel;
use Core\Models\SeguimientoModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Panel de inicio con la analítica de cada rol (RF04).
 *
 *  - Coordinación: el centro completo, por programa, instructor y competencia.
 *  - Instructor: sus fichas y lo que tiene pendiente de atender.
 *  - Aprendiz: su progreso, comparado con el promedio de su ficha.
 *
 * Todas las cifras salen de AnaliticaModel (calculadas al leer).
 */
class DashboardController extends BaseController {
    private AnaliticaModel $analitica;

    public function __construct(?AnaliticaModel $analitica = null) {
        $this->analitica = $analitica ?? new AnaliticaModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $nombre = (string)(getCurrentUser()['nombre'] ?? '');
        try {
            [$vista, $datos] = match (true) {
                $actor->esCoordinador() => ['coordinador', $this->coordinador($actor)],
                $actor->esInstructor()  => ['instructor', $this->instructor($actor)],
                default                 => ['aprendiz', $this->aprendiz($actor)],
            };
            $datos['errors'] = [];
        } catch (Throwable $e) {
            $vista = $actor->esCoordinador() ? 'coordinador' : ($actor->esInstructor() ? 'instructor' : 'aprendiz');
            $datos = ['errors' => [ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar el panel')], 'vacio' => true];
        }
        $this->render(BASE_PATH . "modules/dashboard/views/$vista.view.php",
            $datos + ['nombreUsuario' => $nombre, 'actor' => $actor], 'Inicio · SENA');
    }

    private function coordinador(Actor $actor): array {
        $fichas = (new FichaModel())->listar($actor, [], 100, 0);
        return [
            'resumen'       => $this->analitica->resumen($actor),
            'semaforo'      => $this->analitica->semaforo($actor),
            'tendencia'     => $this->analitica->tendencia($actor),
            'programas'     => $this->analitica->porPrograma(),
            'instructores'  => $this->analitica->porInstructor(),
            'competencias'  => $this->analitica->competenciasCriticas($actor),
            'enRiesgo'      => $this->analitica->aprendicesEnRiesgo($actor, 8),
            'fichas'        => self::peoresFichas($fichas, 8),
            'actividades'   => $this->analitica->actividadesProximas($actor),
        ];
    }

    private function instructor(Actor $actor): array {
        return [
            'resumen'      => $this->analitica->resumen($actor),
            'carga'        => $this->analitica->cargaInstructor($actor),
            'semaforo'     => $this->analitica->semaforo($actor),
            'tendencia'    => $this->analitica->tendencia($actor),
            'fichas'       => (new FichaModel())->listar($actor, [], 24, 0),
            'competencias' => $this->analitica->competenciasCriticas($actor, 6),
            'enRiesgo'     => $this->analitica->aprendicesEnRiesgo($actor, 8),
            'actividades'  => $this->analitica->actividadesProximas($actor),
        ];
    }

    private function aprendiz(Actor $actor): array {
        $seguimiento = new SeguimientoModel();
        $id = $seguimiento->aprendizDeUsuario($actor->id) ?? throw new ErrorDeNegocio('Tu cuenta no tiene una matrícula asociada.');
        $resumen = $seguimiento->resumen($id);
        return [
            'resumen'      => $resumen,
            'promedio'     => $this->analitica->promedioFicha((int)$resumen['ficha_id']),
            'competencias' => $seguimiento->competencias($id, $actor),
            'planes'       => (new MejoramientoModel())->listar($actor, ['estado' => 'vigente'], 5, 0),
            'retros'       => (new RetroalimentacionModel())->listar($actor, [], 3, 0),
            'actividades'  => $this->analitica->actividadesProximas($actor, 21, 6),
        ];
    }

    /** Fichas con menor desempeño primero (las que no tienen juicios, al final). */
    private static function peoresFichas(array $fichas, int $n): array {
        usort($fichas, static fn($a, $b) => [$a['pct_a'] === null, $a['pct_a'] ?? 0, -(int)$a['en_d']]
                                          <=> [$b['pct_a'] === null, $b['pct_a'] ?? 0, -(int)$b['en_d']]);
        return array_slice($fichas, 0, $n);
    }
}
