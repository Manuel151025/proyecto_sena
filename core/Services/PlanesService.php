<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Formularios\PlanFormulario;
use Core\Models\MejoramientoModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Transaccion;
use PDO;

/**
 * Ciclo del plan de mejoramiento de un RAP en D:
 *
 *   crear (abierto) → evidencia del aprendiz (en_curso) → cerrar:
 *     cumplido     el RAP pasa a A por EvaluacionService (con historial)
 *     no_cumplido  el RAP sigue en D; se puede abrir otro plan
 *
 * Reglas: solo para un RAP en D, uno vigente por RAP, no a desertados, y
 * lo gestiona quien califica ese RAP (o coordinación).
 */
final class PlanesService {
    private PDO $db;
    private MejoramientoModel $modelo;
    private InstructorAccessService $acceso;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->modelo = new MejoramientoModel($this->db);
        $this->acceso = new InstructorAccessService($this->db);
    }

    public function crear(array $d, Actor $actor): int {
        $e = $this->modelo->evaluacion($d['evaluacion_id']) ?? throw new ErrorDeNegocio('El resultado de aprendizaje no existe.');
        $this->exigirAutoridad((int)$e['id'], $actor);
        if ($e['concepto'] !== 'D') {
            throw new ErrorDeNegocio("El RAP {$e['ra_codigo']} no está en D: el plan de mejoramiento es para lo no aprobado.");
        }
        if (in_array($e['aprendiz_estado'], ['desertado', 'egresado'], true)) {
            throw new ErrorDeNegocio("El aprendiz está {$e['aprendiz_estado']}: no se le abren planes.");
        }
        if ($this->modelo->vigenteDe((int)$e['id']) !== null) {
            throw new ErrorDeNegocio("El RAP {$e['ra_codigo']} ya tiene un plan vigente: ciérrelo antes de abrir otro.");
        }
        if ($d['fecha_limite'] < date('Y-m-d')) {
            throw new ErrorDeNegocio('La fecha límite ya pasó.');
        }
        return Transaccion::ejecutar($this->db, function () use ($d, $e, $actor) {
            $id = $this->modelo->crear([
                'evaluacion_id' => (int)$e['id'], 'aprendiz_id' => (int)$e['aprendiz_id'], 'ficha_id' => (int)$e['ficha_id'],
                // Responsable: quien lo crea si es instructor; si lo crea
                // coordinación, el instructor que califica ese RAP.
                'instructor_id' => $actor->esInstructor() ? $actor->id : (int)$e['instructor_id'],
                'actividades' => $d['actividades'], 'fecha_inicio' => $d['fecha_inicio'], 'fecha_limite' => $d['fecha_limite'],
                'creado_por' => $actor->id,
            ]);
            (new Auditoria($this->db))->operacion($actor, 'Crear', 'Mejoramiento', 'planes_mejoramiento', $id,
                "Abrió un plan de mejoramiento para el RAP {$e['ra_codigo']} (hasta {$d['fecha_limite']})");
            (new Notificador($this->db))->notificar((int)$e['aprendiz_usuario_id'], 'Nuevo plan de mejoramiento',
                "Tienes un plan para el RAP {$e['ra_codigo']} hasta el " . date('d/m/Y', strtotime($d['fecha_limite'])) . '.',
                'warning', '/index.php/mejoramiento');
            return $id;
        });
    }

    public function editar(array $d, Actor $actor): void {
        $plan = $this->vigente($d['id'], $actor);
        $v = new \Core\Support\Validador([]);
        PlanFormulario::plazo($v, (string)$plan['fecha_inicio'], $d['fecha_limite']);
        if ($v->hayErrores()) {
            throw new ErrorDeNegocio(implode(' ', $v->errores()));
        }
        Transaccion::ejecutar($this->db, function () use ($d, $plan, $actor) {
            $this->modelo->actualizar((int)$plan['id'], $d['actividades'], $d['fecha_limite']);
            $cambio = $plan['fecha_limite'] !== $d['fecha_limite'] ? " (plazo {$plan['fecha_limite']} → {$d['fecha_limite']})" : '';
            (new Auditoria($this->db))->operacion($actor, 'Editar', 'Mejoramiento', 'planes_mejoramiento', (int)$plan['id'],
                "Editó el plan del RAP {$plan['ra_codigo']}$cambio");
            if ($cambio !== '') {
                (new Notificador($this->db))->notificar((int)$plan['aprendiz_usuario_id'], 'Plan de mejoramiento actualizado',
                    "El plan del RAP {$plan['ra_codigo']} ahora vence el " . date('d/m/Y', strtotime($d['fecha_limite'])) . '.',
                    'info', '/index.php/mejoramiento');
            }
        });
    }

    public function cerrar(array $d, Actor $actor): void {
        $plan = $this->vigente($d['id'], $actor);
        Transaccion::ejecutar($this->db, function () use ($d, $plan, $actor) {
            $this->modelo->cerrar((int)$plan['id'], $d['resultado'], $d['observaciones'], $actor->id);
            if ($d['resultado'] === 'cumplido' && $plan['concepto'] !== 'A') {
                (new EvaluacionService($this->db))->actualizarPorId((int)$plan['evaluacion_id'], [
                    'concepto'   => 'A',
                    'usuario_id' => $actor->id,
                    'instructor_id' => $actor->esInstructor() ? $actor->id : (int)$plan['instructor_id'],
                    'motivo'     => mb_substr('Plan de mejoramiento cumplido: ' . $d['observaciones'], 0, 255),
                    'comentario' => $d['observaciones'],
                    'retroalimentacion' => true,
                    'notificar'  => false,
                ]);
            }
            $texto = $d['resultado'] === 'cumplido' ? 'cumplido: el RAP pasa a A' : 'no cumplido: el RAP sigue en D';
            (new Auditoria($this->db))->operacion($actor, 'Cerrar', 'Mejoramiento', 'planes_mejoramiento', (int)$plan['id'],
                "Cerró el plan del RAP {$plan['ra_codigo']} como $texto");
            (new Notificador($this->db))->notificar((int)$plan['aprendiz_usuario_id'], 'Plan de mejoramiento cerrado',
                "Tu plan del RAP {$plan['ra_codigo']} se cerró como $texto.",
                $d['resultado'] === 'cumplido' ? 'success' : 'danger', '/index.php/mejoramiento');
        });
    }

    /** Plan vigente sobre el que el actor tiene autoridad. */
    private function vigente(int $id, Actor $actor): array {
        $plan = $this->modelo->findById($id) ?? throw new ErrorDeNegocio('El plan no existe.');
        if (!in_array($plan['estado'], MejoramientoModel::VIGENTES, true)) {
            throw new ErrorDeNegocio('El plan ya está cerrado.');
        }
        if (!($actor->esInstructor() && (int)$plan['instructor_id'] === $actor->id)) {
            $this->exigirAutoridad((int)$plan['evaluacion_id'], $actor);
        }
        return $plan;
    }

    private function exigirAutoridad(int $evaluacionId, Actor $actor): void {
        if ($actor->esCoordinador()) {
            return;
        }
        if (!$actor->esInstructor() || !$this->acceso->tieneAccesoEvaluacion($evaluacionId, $actor->id)) {
            throw new ErrorDeNegocio('No calificas este resultado de aprendizaje: no puedes gestionar su plan.');
        }
    }
}
