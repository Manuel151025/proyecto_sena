<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\EvaluacionesModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PDO;

/**
 * Calificar un RAP desde /evaluaciones: quién puede y sobre qué aprendiz.
 *
 * La escritura (historial, transacción, aviso al aprendiz) la hace
 * EvaluacionService; aquí van las reglas de permiso que antes vivían en el
 * controlador y en una copia del SQL de acceso dentro del modelo.
 */
final class JuiciosService {
    private PDO $db;
    private EvaluacionesModel $modelo;
    private EvaluacionService $evaluaciones;
    private InstructorAccessService $acceso;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->modelo = new EvaluacionesModel($this->db);
        $this->evaluaciones = new EvaluacionService($this->db);
        $this->acceso = new InstructorAccessService($this->db);
    }

    /** @return string 'actualizada' | 'sin_cambios' */
    public function calificar(array $d, Actor $actor): string {
        $e = $this->modelo->paraCalificar($d['evaluacion_id']) ?? throw new ErrorDeNegocio('La evaluación no existe.');
        if (!$actor->gestiona()) {
            throw new ErrorDeNegocio('Solo instructores y coordinación emiten juicios.');
        }
        if ($actor->esInstructor() && !$this->acceso->tieneAccesoEvaluacion((int)$e['id'], $actor->id)) {
            throw new ErrorDeNegocio("No calificas el RAP {$e['ra_codigo']} de este aprendiz: lo tiene otro instructor.");
        }
        if ($e['aprendiz_estado'] === 'desertado') {
            throw new ErrorDeNegocio('El aprendiz está desertado: no se registran juicios nuevos.');
        }
        $r = $this->evaluaciones->actualizarPorId((int)$e['id'], [
            'concepto'   => $d['concepto'],
            'comentario' => $d['comentario'],
            'motivo'     => $d['motivo'],
            'usuario_id' => $actor->id,
        ]);
        return $r['accion'];
    }
}
