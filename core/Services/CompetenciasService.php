<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Models\CompetenciasModel;
use Core\Models\ResultadosAprendizajeModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use Core\Support\Transaccion;
use PDO;
use Throwable;

/**
 * Competencias y resultados de aprendizaje (RAP): la parte evaluable de la
 * estructura curricular. Solo coordinación escribe.
 *
 * Reglas que no existían:
 *  - Un instructor podía crear, editar y borrar RAP. El RAP es parte del
 *    diseño curricular del programa, no del trabajo de una ficha, y mover
 *    uno de competencia (o de programa) con juicios ya emitidos dejaba esos
 *    juicios colgando de un programa que el aprendiz no cursa.
 *  - No se puede cambiar de programa una competencia ni de competencia un
 *    RAP que ya tiene registros de evaluación.
 *  - Borrar un RAP sin juicios emitidos quita también sus filas
 *    'pendiente', que creó el sistema: antes la clave foránea lo impedía y
 *    un RAP creado por error no se podía retirar nunca.
 *  - La marca de "etapa práctica" (de la que depende quién califica) se
 *    puede editar; antes solo se fijaba por texto al migrar.
 */
final class CompetenciasService {
    private PDO $db;
    private CompetenciasModel $competencias;
    private ResultadosAprendizajeModel $raps;
    private EvaluacionesSyncService $sync;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->competencias = new CompetenciasModel($this->db);
        $this->raps = new ResultadosAprendizajeModel($this->db);
        $this->sync = new EvaluacionesSyncService($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    // -----------------------------------------------------------------
    // COMPETENCIAS
    // -----------------------------------------------------------------

    public function crearCompetencia(array $d, Actor $actor): int {
        $this->soloCoordinacion($actor);
        try {
            $id = $this->competencias->crear($d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [
                ErroresBD::DUPLICADO  => "El programa ya tiene una competencia con el código {$d['codigo']}.",
                ErroresBD::REFERENCIA => 'El programa seleccionado no existe.',
            ]);
        }
        $this->auditoria->operacion($actor, 'Crear', 'Competencias', 'competencias', $id, "Creó la competencia {$d['codigo']}");
        return $id;
    }

    public function editarCompetencia(int $id, array $d, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $actual = $this->competencias->findById($id) ?? throw new ErrorDeNegocio('La competencia no existe.');
        if ((int)$actual['programa_id'] !== (int)$d['programa_id']) {
            $uso = $this->competencias->dependencias($id);
            if ($uso['evaluaciones'] > 0) {
                throw new ErrorDeNegocio("No se puede cambiar de programa: la competencia tiene {$uso['evaluaciones']} registros de evaluación.");
            }
        }
        try {
            $this->competencias->actualizar($id, $d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "El programa ya tiene otra competencia con el código {$d['codigo']}."]);
        }
        $this->auditoria->operacion($actor, 'Editar', 'Competencias', 'competencias', $id,
            "Editó la competencia {$d['codigo']}" . ((int)$actual['es_etapa_practica'] !== (int)$d['es_etapa_practica']
                ? ' (etapa práctica: ' . ($d['es_etapa_practica'] ? 'sí' : 'no') . ')' : ''));
    }

    public function eliminarCompetencia(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $c = $this->competencias->findById($id) ?? throw new ErrorDeNegocio('La competencia no existe.');
        $uso = $this->competencias->dependencias($id);
        if ($uso['rap'] > 0) {
            throw new ErrorDeNegocio("No se puede eliminar: la competencia tiene {$uso['rap']} resultado(s) de aprendizaje. Márquela como inactiva.");
        }
        try {
            $this->competencias->eliminar($id);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::EN_USO => 'No se puede eliminar: la competencia tiene actividades o asignaciones registradas. Márquela como inactiva.']);
        }
        $this->auditoria->operacion($actor, 'Eliminar', 'Competencias', 'competencias', $id, "Eliminó la competencia {$c['codigo']}");
    }

    // -----------------------------------------------------------------
    // RESULTADOS DE APRENDIZAJE
    // -----------------------------------------------------------------

    /** @return array{id:int, habilitadas:int, sin_instructor:int} */
    public function crearRap(array $d, Actor $actor): array {
        $this->soloCoordinacion($actor);
        return $this->enTransaccion(function () use ($d, $actor) {
            try {
                $id = $this->raps->crear($d);
            } catch (Throwable $e) {
                ErroresBD::relanzar($e, [
                    ErroresBD::DUPLICADO  => "Ya existe un resultado de aprendizaje con el código {$d['codigo']}.",
                    ErroresBD::REFERENCIA => 'La competencia seleccionada no existe.',
                ]);
            }
            // Sin su fila 'pendiente', el RAP no aparece en /seguimiento ni
            // en /evaluaciones para los aprendices ya matriculados.
            $s = $this->sync->sincronizar(['competencia_id' => (int)$d['competencia_id']]);
            $this->auditoria->operacion($actor, 'Crear', 'RAP', 'resultados_aprendizaje', $id, "Creó el RAP {$d['codigo']}");
            return ['id' => $id, 'habilitadas' => $s['creadas'], 'sin_instructor' => $s['omitidas_sin_instructor']];
        });
    }

    public function editarRap(int $id, array $d, Actor $actor): int {
        $this->soloCoordinacion($actor);
        $actual = $this->raps->findById($id) ?? throw new ErrorDeNegocio('El resultado de aprendizaje no existe.');
        if ((int)$actual['competencia_id'] !== (int)$d['competencia_id']) {
            $uso = $this->raps->uso($id);
            if ($uso['juicios'] + $uso['pendientes'] > 0) {
                throw new ErrorDeNegocio('No se puede mover a otra competencia: el resultado ya tiene registros de evaluación. Cree uno nuevo en la competencia correcta.');
            }
        }
        return $this->enTransaccion(function () use ($id, $d, $actor) {
            try {
                $this->raps->actualizar($id, $d);
            } catch (Throwable $e) {
                ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe otro resultado de aprendizaje con el código {$d['codigo']}."]);
            }
            $s = $this->sync->sincronizar(['competencia_id' => (int)$d['competencia_id']]);
            $this->auditoria->operacion($actor, 'Editar', 'RAP', 'resultados_aprendizaje', $id, "Editó el RAP {$d['codigo']}");
            return $s['creadas'];
        });
    }

    public function eliminarRap(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $rap = $this->raps->findById($id) ?? throw new ErrorDeNegocio('El resultado de aprendizaje no existe.');
        $uso = $this->raps->uso($id);
        if ($uso['juicios'] > 0 || $uso['evidencias'] > 0) {
            throw new ErrorDeNegocio("No se puede eliminar: el resultado tiene {$uso['juicios']} juicio(s) emitido(s)"
                . ($uso['evidencias'] > 0 ? " y {$uso['evidencias']} evidencia(s)" : '') . '. Forma parte del historial académico.');
        }
        $this->enTransaccion(function () use ($id) {
            $this->raps->eliminarConPendientes($id);
        });
        $this->auditoria->operacion($actor, 'Eliminar', 'RAP', 'resultados_aprendizaje', $id,
            "Eliminó el RAP {$rap['codigo']} y sus {$uso['pendientes']} evaluaciones pendientes");
    }

    private function enTransaccion(callable $f): mixed {
        return Transaccion::ejecutar($this->db, $f);
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación modifica la estructura curricular.');
        }
    }
}
