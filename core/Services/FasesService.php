<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\FasesModel;
use Core\Models\ProyectosModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use PDO;
use Throwable;

/**
 * Casos de uso de las fases del proyecto formativo.
 *
 * Quién puede qué:
 *  - Coordinación: todo, en cualquier proyecto.
 *  - Instructor: crear y editar fases solo de los proyectos que desarrollan
 *    sus fichas. Antes podía crear, editar y BORRAR las fases de cualquier
 *    proyecto del centro: el controlador solo miraba el rol.
 *  - Borrar una fase es solo de coordinación: la fase es común a todas las
 *    fichas del proyecto, y un instructor la quitaría también de las
 *    fichas de sus compañeros.
 */
final class FasesService {
    private FasesModel $fases;
    private ProyectosModel $proyectos;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null, ?FasesModel $fases = null, ?ProyectosModel $proyectos = null, ?Auditoria $auditoria = null) {
        $db ??= Database::getConnection();
        $this->fases = $fases ?? new FasesModel($db);
        $this->proyectos = $proyectos ?? new ProyectosModel($db);
        $this->auditoria = $auditoria ?? new Auditoria($db);
    }

    public function crear(int $proyectoId, array $d, Actor $actor): int {
        $this->exigirAccesoProyecto($proyectoId, $actor);
        try {
            $id = $this->fases->crear($proyectoId, $d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "El proyecto ya tiene una fase número {$d['numero_fase']}."]);
        }
        $this->auditoria->operacion($actor, 'Crear', 'Fases', 'fases_proyecto', $id, "Creó la fase {$d['numero_fase']} ({$d['nombre']}) del proyecto $proyectoId");
        return $id;
    }

    public function editar(int $id, array $d, Actor $actor): int {
        $fase = $this->fases->findById($id) ?? throw new ErrorDeNegocio('La fase no existe.');
        $this->exigirAccesoProyecto((int)$fase['proyecto_id'], $actor);
        try {
            $this->fases->actualizar($id, $d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "El proyecto ya tiene una fase número {$d['numero_fase']}."]);
        }
        $this->auditoria->operacion($actor, 'Editar', 'Fases', 'fases_proyecto', $id, "Editó la fase {$d['nombre']}");
        return (int)$fase['proyecto_id'];
    }

    public function eliminar(int $id, Actor $actor): int {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación puede eliminar fases: la fase es común a todas las fichas del proyecto.');
        }
        $fase = $this->fases->findById($id) ?? throw new ErrorDeNegocio('La fase no existe.');
        $n = $this->fases->contarActividades($id);
        if ($n > 0) {
            throw new ErrorDeNegocio("No se puede eliminar: la fase tiene $n actividad(es) registradas.");
        }
        $this->fases->eliminar($id);
        $this->auditoria->operacion($actor, 'Eliminar', 'Fases', 'fases_proyecto', $id, "Eliminó la fase {$fase['nombre']}");
        return (int)$fase['proyecto_id'];
    }

    private function exigirAccesoProyecto(int $proyectoId, Actor $actor): void {
        if (!$actor->gestiona()) {
            throw new ErrorDeNegocio('No tienes permiso para administrar fases.');
        }
        if (!$this->proyectos->puedeVer($proyectoId, $actor)) {
            throw new ErrorDeNegocio($actor->esCoordinador()
                ? 'El proyecto no existe.'
                : 'Solo puedes administrar las fases de los proyectos que desarrollan tus fichas.');
        }
    }
}
