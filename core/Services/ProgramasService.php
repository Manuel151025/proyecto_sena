<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\ProgramasModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use PDO;
use Throwable;

/**
 * Casos de uso de los programas de formación. Solo coordinación escribe.
 */
final class ProgramasService {
    private ProgramasModel $modelo;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null, ?ProgramasModel $modelo = null, ?Auditoria $auditoria = null) {
        $db ??= Database::getConnection();
        $this->modelo = $modelo ?? new ProgramasModel($db);
        $this->auditoria = $auditoria ?? new Auditoria($db);
    }

    public function crear(array $d, Actor $actor): int {
        $this->soloCoordinacion($actor);
        try {
            $id = $this->modelo->crear($d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe un programa con el código {$d['codigo']}."]);
        }
        $this->auditoria->operacion($actor, 'Crear', 'Programas', 'programas', $id, "Creó el programa {$d['codigo']}");
        return $id;
    }

    public function editar(int $id, array $d, Actor $actor): void {
        $this->soloCoordinacion($actor);
        if ($this->modelo->findById($id) === null) {
            throw new ErrorDeNegocio('El programa no existe.');
        }
        try {
            $this->modelo->actualizar($id, $d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe otro programa con el código {$d['codigo']}."]);
        }
        $this->auditoria->operacion($actor, 'Editar', 'Programas', 'programas', $id, "Editó el programa {$d['codigo']}");
    }

    /**
     * Se explica qué lo impide en lugar de dejar que lo diga la clave
     * foránea: "tiene 3 fichas y 12 competencias" orienta; "no se puede", no.
     */
    public function eliminar(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $p = $this->modelo->findById($id);
        if ($p === null) {
            throw new ErrorDeNegocio('El programa no existe.');
        }
        $uso = $this->modelo->dependencias($id);
        $partes = [];
        if ($uso['fichas'] > 0) {
            $partes[] = "{$uso['fichas']} ficha(s)";
        }
        if ($uso['competencias'] > 0) {
            $partes[] = "{$uso['competencias']} competencia(s)";
        }
        if ($partes !== []) {
            throw new ErrorDeNegocio('No se puede eliminar: el programa tiene ' . implode(' y ', $partes)
                . '. Márquelo como archivado para retirarlo sin perder su historial.');
        }
        try {
            $this->modelo->eliminar($id);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::EN_USO => 'No se puede eliminar: el programa tiene registros asociados.']);
        }
        $this->auditoria->operacion($actor, 'Eliminar', 'Programas', 'programas', $id, "Eliminó el programa {$p['codigo']}");
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación administra los programas de formación.');
        }
    }
}
