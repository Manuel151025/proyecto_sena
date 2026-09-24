<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\CalendarioModel;
use Core\Models\EvaluacionesModel;
use Core\Services\Auditoria;
use Core\Services\InstructorAccessService;
use Core\Services\Notificador;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Transaccion;
use Core\Database;

/**
 * Calendario académico.
 *
 *   GET  /calendario
 *   GET  /calendario/api?start=&end=        eventos en JSON (FullCalendar)
 *   POST /calendario  action=crear|eliminar  instructor y coordinación
 */
class CalendarioController extends BaseController {
    /** Un rango mayor no lo pide ninguna vista y solo serviría para volcar datos. */
    private const MAX_DIAS = 100;

    private CalendarioModel $modelo;

    public function __construct(?CalendarioModel $modelo = null) {
        $this->modelo = $modelo ?? new CalendarioModel();
    }

    public function index(): void {
        $actor = Actor::actual();
        $this->render(BASE_PATH . 'modules/calendario/views/index.view.php', [
            'actor'  => $actor,
            'fichas' => $actor->gestiona() ? (new EvaluacionesModel())->fichasDelActor($actor) : [],
            'apiUrl' => APP_URL . '/index.php/calendario/api',
        ], 'Calendario · SENA');
    }

    public function apiEvents(): never {
        $desde = self::fecha($_GET['start'] ?? '') ?? date('Y-m-01');
        $hasta = self::fecha($_GET['end'] ?? '') ?? date('Y-m-t');
        if ($hasta < $desde || (strtotime($hasta) - strtotime($desde)) / 86400 > self::MAX_DIAS) {
            $this->json(['error' => 'Rango de fechas no válido.'], 400);
        }
        $this->json($this->modelo->eventos(Actor::actual(), $desde, $hasta));
    }

    public function crear(): never {
        $actor = Actor::actual();
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $titulo = $v->nombre('titulo', 'El título', 3, 150);
        $descripcion = $v->texto('descripcion', 'La descripción', 0, 1000, false);
        $fecha = $v->fecha('fecha', 'La fecha');
        $fichaId = $v->id('ficha_id', 'La ficha');
        $this->siHayErrores($v, '/calendario');
        $this->ejecutar(function () use ($actor, $titulo, $descripcion, $fecha, $fichaId) {
            if (!(new InstructorAccessService())->puedeGestionarFicha($actor, $fichaId)) {
                throw new ErrorDeNegocio('No tienes a cargo esa ficha.');
            }
            $db = Database::getConnection();
            Transaccion::ejecutar($db, function () use ($db, $actor, $titulo, $descripcion, $fecha, $fichaId) {
                $id = $this->modelo->crearEvento($titulo, $descripcion !== '' ? $descripcion : null, (string)$fecha, $fichaId, $actor->id);
                (new Auditoria($db))->operacion($actor, 'Crear', 'Calendario', 'eventos_calendario', $id, "Creó el evento «{$titulo}» ({$fecha})");
                (new Notificador($db))->notificarVarios($this->modelo->usuariosDeFicha($fichaId), 'Nuevo evento en tu ficha',
                    "«{$titulo}» el " . date('d/m/Y', strtotime((string)$fecha)) . '.', 'info', '/index.php/calendario');
            });
        }, '/calendario', 'Evento agregado; los aprendices de la ficha recibieron un aviso.', 'No se pudo crear el evento');
    }

    public function eliminar(): never {
        $actor = Actor::actual();
        $this->exigirRol(ROL_COORDINADOR, ROL_INSTRUCTOR);
        $v = $this->entrada();
        $id = $v->id('evento_id', 'El evento');
        $this->siHayErrores($v, '/calendario');
        $this->ejecutar(function () use ($actor, $id) {
            $e = $this->modelo->findEvento($id) ?? throw new ErrorDeNegocio('El evento no existe.');
            if (!$actor->esCoordinador() && (int)$e['creado_por'] !== $actor->id) {
                throw new ErrorDeNegocio('Solo quien creó el evento o la coordinación pueden eliminarlo.');
            }
            $this->modelo->eliminarEvento($id);
            (new Auditoria())->operacion($actor, 'Eliminar', 'Calendario', 'eventos_calendario', $id, "Eliminó el evento «{$e['titulo']}»");
        }, '/calendario', 'Evento eliminado.', 'No se pudo eliminar el evento');
    }

    private static function fecha(string $valor): ?string {
        $f = substr($valor, 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && checkdate((int)substr($f, 5, 2), (int)substr($f, 8, 2), (int)substr($f, 0, 4)) ? $f : null;
    }
}
