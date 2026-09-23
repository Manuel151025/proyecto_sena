<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\AsignacionesModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use Core\Support\Transaccion;
use PDO;
use Throwable;

/**
 * Asignación de instructores a competencias de una ficha (coordinación).
 *
 * Reglas que faltaban:
 *  - La competencia tiene que ser del programa de la ficha. Antes el
 *    selector ofrecía todas las competencias del centro y el servidor no lo
 *    comprobaba: se podía "asignar" una competencia que la ficha no cursa.
 *  - Las competencias de etapa práctica no se asignan: las califica el
 *    instructor de seguimiento de cada aprendiz.
 *  - El instructor tiene que estar activo.
 *  - Cambiar el responsable ya no exige borrar y volver a crear: se
 *    reasigna, y sus evaluaciones pendientes pasan al nuevo instructor.
 */
final class AsignacionesService {
    private PDO $db;
    private AsignacionesModel $modelo;
    private Auditoria $auditoria;
    private Notificador $notificador;
    private EvaluacionesSyncService $evaluaciones;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->modelo = new AsignacionesModel($this->db);
        $this->auditoria = new Auditoria($this->db);
        $this->notificador = new Notificador($this->db);
        $this->evaluaciones = new EvaluacionesSyncService($this->db);
    }

    public function asignar(int $fichaId, int $competenciaId, int $instructorId, Actor $actor): int {
        $this->soloCoordinacion($actor);
        $ctx = $this->validar($fichaId, $competenciaId, $instructorId);
        if ($this->modelo->checkAsignacionExiste($fichaId, $competenciaId)) {
            throw new ErrorDeNegocio("La competencia {$ctx['codigo']} ya tiene instructor en la ficha {$ctx['numero_ficha']}. Use «Cambiar instructor».");
        }
        return Transaccion::ejecutar($this->db, function () use ($fichaId, $competenciaId, $instructorId, $ctx, $actor) {
            try {
                $id = $this->modelo->crear($fichaId, $competenciaId, $instructorId);
            } catch (Throwable $e) {
                ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => 'Esa competencia ya tiene instructor en la ficha.']);
            }
            $n = $this->evaluaciones->actualizarResponsables(['ficha_id' => $fichaId, 'competencia_id' => $competenciaId]);
            $this->auditoria->operacion($actor, 'Asignar', 'Asignaciones', 'asignaciones', $id,
                "Asignó la competencia {$ctx['codigo']} de la ficha {$ctx['numero_ficha']} al instructor $instructorId ($n evaluaciones pendientes)");
            $this->notificador->notificar($instructorId, 'Nueva competencia a cargo',
                "Te asignaron la competencia {$ctx['codigo']} en la ficha {$ctx['numero_ficha']}: $n resultados por evaluar.",
                'info', '/index.php/seguimiento?ficha_id=' . $fichaId);
            return $id;
        });
    }

    public function cambiarInstructor(int $id, int $instructorId, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $a = $this->modelo->findById($id) ?? throw new ErrorDeNegocio('La asignación no existe.');
        $ctx = $this->validar((int)$a['ficha_id'], (int)$a['competencia_id'], $instructorId);
        Transaccion::ejecutar($this->db, function () use ($id, $a, $instructorId, $ctx, $actor) {
            $this->modelo->cambiarInstructor($id, $instructorId);
            $n = $this->evaluaciones->actualizarResponsables(['ficha_id' => (int)$a['ficha_id'], 'competencia_id' => (int)$a['competencia_id']]);
            $this->auditoria->operacion($actor, 'Reasignar', 'Asignaciones', 'asignaciones', $id,
                "Pasó la competencia {$ctx['codigo']} de la ficha {$ctx['numero_ficha']} del instructor {$a['instructor_id']} al $instructorId");
            $this->notificador->notificar($instructorId, 'Nueva competencia a cargo',
                "Ahora llevas la competencia {$ctx['codigo']} en la ficha {$ctx['numero_ficha']}: $n resultados por evaluar.",
                'info', '/index.php/seguimiento?ficha_id=' . (int)$a['ficha_id']);
        });
    }

    public function eliminar(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $a = $this->modelo->findById($id) ?? throw new ErrorDeNegocio('La asignación no existe.');
        Transaccion::ejecutar($this->db, function () use ($id, $a, $actor) {
            $this->modelo->eliminar($id);
            // Sin asignación, la competencia vuelve al instructor líder.
            $this->evaluaciones->actualizarResponsables(['ficha_id' => (int)$a['ficha_id'], 'competencia_id' => (int)$a['competencia_id']]);
            $this->auditoria->operacion($actor, 'Eliminar', 'Asignaciones', 'asignaciones', $id,
                "Quitó la asignación de la competencia {$a['competencia_id']} en la ficha {$a['ficha_id']}; vuelve al líder");
        });
    }

    private function validar(int $fichaId, int $competenciaId, int $instructorId): array {
        $ctx = $this->modelo->contexto($fichaId, $competenciaId) ?? throw new ErrorDeNegocio('La ficha o la competencia no existen.');
        if ((int)$ctx['programa_ficha'] !== (int)$ctx['programa_competencia']) {
            throw new ErrorDeNegocio("La competencia {$ctx['codigo']} no pertenece al programa de la ficha {$ctx['numero_ficha']}.");
        }
        if ((int)$ctx['es_etapa_practica'] === 1) {
            throw new ErrorDeNegocio('Las competencias de etapa práctica no se asignan: las califica el instructor de seguimiento de cada aprendiz.');
        }
        if (!$this->modelo->esInstructorActivo($instructorId)) {
            throw new ErrorDeNegocio('El instructor debe tener una cuenta activa.');
        }
        return $ctx;
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación asigna instructores.');
        }
    }
}
