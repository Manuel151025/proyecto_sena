<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\ProyectosModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use PDO;
use Throwable;

/**
 * Casos de uso de los proyectos formativos.
 *
 * Lo usan /proyectos y /estructura: antes cada pantalla tenía su propia
 * copia de "crear/editar/eliminar proyecto", con validaciones distintas.
 */
final class ProyectosService {
    private ProyectosModel $modelo;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null, ?ProyectosModel $modelo = null, ?Auditoria $auditoria = null) {
        $db ??= Database::getConnection();
        $this->modelo = $modelo ?? new ProyectosModel($db);
        $this->auditoria = $auditoria ?? new Auditoria($db);
    }

    public function crear(array $datos, Actor $actor): int {
        $this->soloCoordinacion($actor);
        try {
            $id = $this->modelo->crear($datos);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe un proyecto con el código {$datos['codigo']}."]);
        }
        $this->auditoria->operacion($actor, 'Crear', 'Proyectos', 'proyectos', $id, "Creó el proyecto {$datos['codigo']}");
        return $id;
    }

    public function editar(int $id, array $datos, Actor $actor): void {
        $this->soloCoordinacion($actor);
        if (!$this->modelo->existe($id)) {
            throw new ErrorDeNegocio('El proyecto no existe.');
        }
        try {
            $this->modelo->actualizar($id, $datos);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe otro proyecto con el código {$datos['codigo']}."]);
        }
        $this->auditoria->operacion($actor, 'Editar', 'Proyectos', 'proyectos', $id, "Editó el proyecto {$datos['codigo']}");
    }

    public function eliminar(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $proyecto = $this->modelo->findById($id);
        if ($proyecto === null) {
            throw new ErrorDeNegocio('El proyecto no existe.');
        }
        $fichas = $this->modelo->contarFichas($id);
        if ($fichas > 0) {
            throw new ErrorDeNegocio("No se puede eliminar: $fichas ficha(s) desarrollan este proyecto. Márquelo como finalizado o inactivo.");
        }
        try {
            $this->modelo->eliminar($id);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::EN_USO => 'No se puede eliminar: sus fases tienen actividades registradas.']);
        }
        $this->auditoria->operacion($actor, 'Eliminar', 'Proyectos', 'proyectos', $id, "Eliminó el proyecto {$proyecto['codigo']}");
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación administra los proyectos formativos.');
        }
    }
}
