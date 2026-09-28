<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\FichaModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use Core\Support\Transaccion;
use PDO;
use Throwable;

/**
 * Casos de uso de las fichas de formación (solo coordinación).
 *
 * Reglas nuevas:
 *  - No se cambia el programa de una ficha que ya tiene juicios emitidos:
 *    los juicios quedarían sobre RAP de un programa que la ficha ya no
 *    cursa, y los del nuevo aparecerían todos pendientes.
 *  - No se cambia el proyecto formativo si ya hay actividades asignadas a
 *    fases del proyecto actual.
 *  - El instructor líder tiene que ser un instructor activo: sin él la
 *    ficha no puede generar evaluaciones (la columna es NOT NULL).
 */
final class FichasService {
    private PDO $db;
    private FichaModel $fichas;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null, ?FichaModel $fichas = null) {
        $this->db = $db ?? Database::getConnection();
        $this->fichas = $fichas ?? new FichaModel($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    public function crear(array $d, Actor $actor): int {
        $this->soloCoordinacion($actor);
        $this->validarLider($d);
        try {
            $id = $this->fichas->crear($d, $actor->id);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [
                ErroresBD::DUPLICADO  => "Ya existe la ficha {$d['numero_ficha']}.",
                ErroresBD::REFERENCIA => 'El programa o el proyecto seleccionado no existe.',
            ]);
        }
        $this->auditoria->operacion($actor, 'Crear', 'Fichas', 'fichas', $id, "Creó la ficha {$d['numero_ficha']}");
        return $id;
    }

    /** @return int Evaluaciones pendientes habilitadas por el cambio. */
    public function editar(int $id, array $d, Actor $actor): int {
        $this->soloCoordinacion($actor);
        $actual = $this->fichas->findById($id) ?? throw new ErrorDeNegocio('La ficha no existe.');
        $this->validarLider($d);
        $uso = $this->fichas->dependencias($id);
        if ((int)$actual['programa_id'] !== (int)$d['programa_id'] && $uso['juicios'] > 0) {
            throw new ErrorDeNegocio("No se puede cambiar el programa: la ficha ya tiene {$uso['juicios']} juicios emitidos sobre el programa actual.");
        }
        if ((int)($actual['proyecto_id'] ?? 0) !== (int)($d['proyecto_id'] ?? 0) && $uso['actividades_con_fase'] > 0) {
            throw new ErrorDeNegocio("No se puede cambiar el proyecto formativo: la ficha tiene {$uso['actividades_con_fase']} actividades en fases del proyecto actual.");
        }
        return Transaccion::ejecutar($this->db, function () use ($id, $d, $actual, $actor) {
            try {
                $this->fichas->actualizar($id, $d);
            } catch (Throwable $e) {
                ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe la ficha {$d['numero_ficha']}."]);
            }
            // Un programa nuevo o un primer instructor líder habilitan
            // evaluaciones que antes no se podían crear.
            $evaluaciones = new EvaluacionesSyncService($this->db);
            $s = $evaluaciones->sincronizar(['ficha_id' => $id]);
            // Con otro líder, las pendientes que eran del anterior pasan a él.
            $evaluaciones->actualizarResponsables(['ficha_id' => $id]);
            $cambios = [];
            if ((int)$actual['instructor_id'] !== (int)$d['instructor_id']) {
                $cambios[] = 'nuevo instructor líder';
                (new Notificador($this->db))->notificar((int)$d['instructor_id'], 'Te asignaron como instructor líder',
                    "Ahora lideras la ficha {$d['numero_ficha']}.", 'info', '/index.php/fichas/ver?id=' . $id);
            }
            if ($actual['estado'] !== $d['estado']) {
                $cambios[] = "estado {$actual['estado']} → {$d['estado']}";
            }
            $this->auditoria->operacion($actor, 'Editar', 'Fichas', 'fichas', $id,
                "Editó la ficha {$d['numero_ficha']}" . ($cambios ? ' (' . implode(', ', $cambios) . ')' : ''));
            return $s['creadas'];
        });
    }

    public function eliminar(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $f = $this->fichas->findById($id) ?? throw new ErrorDeNegocio('La ficha no existe.');
        $uso = $this->fichas->dependencias($id);
        if ($uso['aprendices'] > 0 || $uso['actividades'] > 0) {
            throw new ErrorDeNegocio("No se puede eliminar: la ficha tiene {$uso['aprendices']} aprendiz(ces) y {$uso['actividades']} actividad(es). Cámbiela a estado «cierre».");
        }
        try {
            $this->fichas->eliminar($id);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::EN_USO => 'No se puede eliminar: la ficha tiene registros asociados. Cámbiela a estado «cierre».']);
        }
        $this->auditoria->operacion($actor, 'Eliminar', 'Fichas', 'fichas', $id, "Eliminó la ficha {$f['numero_ficha']}");
    }

    private function validarLider(array $d): void {
        if (!$this->fichas->esInstructorActivo((int)$d['instructor_id'])) {
            throw new ErrorDeNegocio('El instructor líder debe ser un instructor con cuenta activa.');
        }
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación administra las fichas.');
        }
    }
}
