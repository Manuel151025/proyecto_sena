<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\ActividadesModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PDO;

/**
 * Casos de uso de las actividades del proyecto formativo.
 *
 * Reglas que antes no existían o solo se aplicaban al crear:
 *  - El instructor solo gestiona actividades de SUS fichas. La creación lo
 *    comprobaba, pero editar y eliminar no: cualquier instructor podía
 *    modificar o borrar las actividades de cualquier ficha conociendo el id.
 *    Además la lista de "sus fichas" solo reconocía al líder, así que un
 *    instructor asignado por competencia no podía programar actividades.
 *  - La fase tiene que ser del proyecto de la ficha (RF02) y la competencia
 *    del programa de la ficha: antes se aceptaba cualquier combinación.
 *  - El responsable tiene que ser un instructor activo.
 */
final class ActividadesService {
    private ActividadesModel $modelo;
    private InstructorAccessService $acceso;
    private Auditoria $auditoria;
    private Notificador $notificador;

    public function __construct(?PDO $db = null, ?ActividadesModel $modelo = null, ?InstructorAccessService $acceso = null,
                                ?Auditoria $auditoria = null, ?Notificador $notificador = null) {
        $db ??= Database::getConnection();
        $this->modelo = $modelo ?? new ActividadesModel($db);
        $this->acceso = $acceso ?? new InstructorAccessService($db);
        $this->auditoria = $auditoria ?? new Auditoria($db);
        $this->notificador = $notificador ?? new Notificador($db);
    }

    public function crear(array $d, Actor $actor): int {
        $this->validarContexto($d, $actor);
        $id = $this->modelo->crear($d);
        $this->auditoria->operacion($actor, 'Crear', 'Actividades', 'actividades', $id, "Creó la actividad «{$d['nombre']}» en la ficha {$d['ficha_id']}");
        $this->avisarResponsable($d, $actor, 'Nueva actividad asignada');
        return $id;
    }

    public function editar(int $id, array $d, Actor $actor): void {
        $actual = $this->exigirActividadGestionable($id, $actor);
        $this->validarContexto($d, $actor);
        $this->modelo->actualizar($id, $d);
        $this->auditoria->operacion($actor, 'Editar', 'Actividades', 'actividades', $id, "Editó la actividad «{$d['nombre']}»");
        if ((int)$actual['responsable_id'] !== (int)$d['responsable_id']) {
            $this->avisarResponsable($d, $actor, 'Te asignaron una actividad');
        }
    }

    public function actualizarAvance(int $id, string $estado, float $pct, Actor $actor): void {
        $act = $this->exigirActividadGestionable($id, $actor);
        $this->modelo->actualizarAvance($id, $estado, $pct);
        $this->auditoria->operacion($actor, 'Avance', 'Actividades', 'actividades', $id,
            sprintf('«%s»: %s, %s %%', $act['nombre'], $estado, rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.')));
    }

    public function eliminar(int $id, Actor $actor): void {
        $act = $this->exigirActividadGestionable($id, $actor);
        $this->modelo->eliminar($id);
        $this->auditoria->operacion($actor, 'Eliminar', 'Actividades', 'actividades', $id, "Eliminó la actividad «{$act['nombre']}»");
    }

    /**
     * La actividad existe y el actor puede tocarla. Mismo mensaje si no
     * existe que si es ajena: distinguirlos permitiría sondear ids.
     */
    private function exigirActividadGestionable(int $id, Actor $actor): array {
        $act = $this->modelo->findById($id);
        if ($act === null || !$this->acceso->puedeGestionarFicha($actor, (int)$act['ficha_id'])) {
            throw new ErrorDeNegocio('La actividad no existe o no pertenece a tus fichas.');
        }
        return $act;
    }

    private function validarContexto(array $d, Actor $actor): void {
        if (!$this->acceso->puedeGestionarFicha($actor, (int)$d['ficha_id'])) {
            throw new ErrorDeNegocio('La ficha seleccionada no está entre las tuyas.');
        }
        $ficha = $this->modelo->contextoFicha((int)$d['ficha_id']) ?? throw new ErrorDeNegocio('La ficha no existe.');

        if ($d['fase_id'] !== null) {
            if (empty($ficha['proyecto_id'])) {
                throw new ErrorDeNegocio("La ficha {$ficha['numero_ficha']} no tiene proyecto formativo, así que no tiene fases.");
            }
            if (!$this->modelo->faseDeProyecto((int)$d['fase_id'], (int)$ficha['proyecto_id'])) {
                throw new ErrorDeNegocio('La fase elegida no pertenece al proyecto formativo de la ficha.');
            }
        } elseif (!empty($ficha['proyecto_id'])) {
            throw new ErrorDeNegocio('Selecciona la fase del proyecto a la que pertenece la actividad.');
        }

        if ($d['competencia_id'] !== null && !$this->modelo->competenciaDePrograma((int)$d['competencia_id'], (int)$ficha['programa_id'])) {
            throw new ErrorDeNegocio('La competencia elegida no pertenece al programa de la ficha.');
        }
        if (!$this->modelo->esInstructorActivo((int)$d['responsable_id'])) {
            throw new ErrorDeNegocio('El responsable debe ser un instructor activo.');
        }
    }

    private function avisarResponsable(array $d, Actor $actor, string $titulo): void {
        if ((int)$d['responsable_id'] === $actor->id) {
            return;
        }
        $this->notificador->notificar((int)$d['responsable_id'], $titulo,
            "Actividad «{$d['nombre']}»" . ($d['fecha_fin'] ? ' con fecha límite ' . date('d/m/Y', strtotime($d['fecha_fin'])) : '') . '.',
            'info', '/index.php/actividades?ficha_id=' . (int)$d['ficha_id']);
    }
}
