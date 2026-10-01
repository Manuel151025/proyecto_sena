<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Formularios\UsuarioFormulario;
use Core\Models\AprendizModel;
use Core\Models\MejoramientoModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use Core\Support\PoliticaContrasena;
use Core\Support\Transaccion;
use PDO;
use Throwable;

/**
 * Matrícula de aprendices (solo coordinación).
 *
 * Reglas nuevas o corregidas:
 *  - Traslado de ficha: antes se aceptaba a cualquier ficha y no se creaban
 *    las evaluaciones del programa de destino. Ahora, si el aprendiz ya tiene
 *    juicios emitidos, solo se traslada a otra ficha del MISMO programa; y
 *    en todo traslado se sincronizan sus filas pendientes y pasan al nuevo
 *    instructor líder.
 *  - Estado y cuenta van juntos: un aprendiz desertado o egresado no inicia
 *    sesión; al reactivarlo, vuelve a poder hacerlo. Antes solo "eliminar"
 *    desactivaba la cuenta; cambiar el estado a desertado desde la edición
 *    la dejaba activa.
 *  - La etapa práctica exige instructor de seguimiento: es quien califica
 *    esas competencias (ver InstructorAccessService).
 *  - Quien deja la formación (retiro, deserción o egreso) no conserva planes
 *    de mejoramiento vigentes: se cierran como no cumplidos con constancia.
 *  - Una ficha en cierre no recibe aprendices, ni nuevos ni trasladados.
 *  - Una cuenta de aprendiz sin matrícula (las creaba la gestión de
 *    usuarios) se completa al matricular con su correo; antes el correo se
 *    rechazaba como ya registrado y esa persona no se podía matricular.
 */
final class MatriculasService {
    private PDO $db;
    private AprendizModel $aprendices;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->aprendices = new AprendizModel($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    /** @return array{id:int, temporal:string, habilitadas:int, cuenta_existente:bool} */
    public function matricular(array $d, Actor $actor): array {
        $this->soloCoordinacion($actor);
        $cuenta = $this->aprendices->cuentaSinMatricula($d['email']);
        $ficha = $this->validar($d, null, $cuenta);
        $temporal = PoliticaContrasena::temporal();
        $colores = UsuarioFormulario::COLORES;

        return Transaccion::ejecutar($this->db, function () use ($d, $ficha, $temporal, $colores, $actor, $cuenta) {
            try {
                $id = $this->aprendices->crear($d, password_hash($temporal, PASSWORD_DEFAULT),
                                               $colores[random_int(0, count($colores) - 1)], $cuenta);
            } catch (Throwable $e) {
                ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => 'El correo o el documento ya están registrados.']);
            }
            $s = (new EvaluacionesSyncService($this->db))->sincronizar(['aprendiz_id' => $id]);
            $this->auditoria->operacion($actor, 'Matricular', 'Matrículas', 'aprendices', $id,
                "Matriculó a {$d['nombre']} ({$d['tipo_documento']} {$d['numero_documento']}) en la ficha {$ficha['numero_ficha']}"
                . ($cuenta !== null ? '; ya tenía cuenta, sin matrícula' : ''));
            return ['id' => $id, 'temporal' => $temporal, 'habilitadas' => $s['creadas'], 'cuenta_existente' => $cuenta !== null];
        });
    }

    public function editar(int $id, array $d, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $actual = $this->aprendices->findById($id) ?? throw new ErrorDeNegocio('El aprendiz no existe.');
        $fichaNueva = $this->validar($d, $id, (int)$actual['usuario_id']);
        $traslado = (int)$actual['ficha_id'] !== (int)$d['ficha_id'];

        if ($traslado && $fichaNueva['estado'] === 'cierre') {
            throw new ErrorDeNegocio("La ficha {$fichaNueva['numero_ficha']} está en cierre: no recibe aprendices trasladados.");
        }
        if ($traslado && $this->aprendices->juiciosEmitidos($id) > 0) {
            $fichaVieja = $this->aprendices->datosFicha((int)$actual['ficha_id']);
            if ($fichaVieja && (int)$fichaVieja['programa_id'] !== (int)$fichaNueva['programa_id']) {
                throw new ErrorDeNegocio('El aprendiz ya tiene juicios emitidos: solo se puede trasladar a otra ficha del mismo programa.');
            }
        }

        Transaccion::ejecutar($this->db, function () use ($id, $d, $actual, $fichaNueva, $traslado, $actor) {
            try {
                $this->aprendices->actualizar($id, (int)$actual['usuario_id'], $d);
            } catch (Throwable $e) {
                ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => 'El correo o el documento ya están registrados por otra persona.']);
            }
            $evaluaciones = new EvaluacionesSyncService($this->db);
            if ($traslado) {
                $this->aprendices->trasladarRegistros($id, (int)$d['ficha_id']);
                $evaluaciones->sincronizar(['aprendiz_id' => $id]);
            }
            // Traslado o cambio de instructor de seguimiento: sus pendientes
            // pasan a quien ahora responde por cada una.
            $evaluaciones->actualizarResponsables(['aprendiz_id' => $id]);
            $activo = !in_array($d['estado'], ['desertado', 'egresado'], true);
            $cambios = [];
            if ($activo !== !in_array($actual['estado'], ['desertado', 'egresado'], true)) {
                $this->aprendices->cambiarEstadoCuenta((int)$actual['usuario_id'], $activo ? 'activo' : 'inactivo');
                if (!$activo) {
                    $n = $this->cerrarPlanes($id, $d['estado'] === 'egresado' ? 'al egresar' : 'por deserción', $actor);
                    if ($n > 0) {
                        $cambios[] = "$n plan(es) de mejoramiento cerrados";
                    }
                }
            }
            if ($traslado) {
                $cambios[] = "traslado a la ficha {$fichaNueva['numero_ficha']}";
            }
            if ($actual['estado'] !== $d['estado']) {
                $cambios[] = "estado {$actual['estado']} → {$d['estado']}";
            }
            $this->auditoria->operacion($actor, 'Editar', 'Matrículas', 'aprendices', $id,
                "Actualizó la matrícula de {$d['nombre']}" . ($cambios ? ' (' . implode(', ', $cambios) . ')' : ''));
        });
    }

    /**
     * Retiro de la matrícula: queda como desertado y sin acceso. No se borra:
     * sus juicios y evidencias son historial académico.
     */
    public function retirar(int $id, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $a = $this->aprendices->findById($id) ?? throw new ErrorDeNegocio('El aprendiz no existe.');
        if ($a['estado'] === 'egresado') {
            throw new ErrorDeNegocio('El aprendiz ya egresó: no se puede retirar su matrícula.');
        }
        Transaccion::ejecutar($this->db, function () use ($id, $a, $actor) {
            $this->aprendices->marcarDesertado($id);
            $this->aprendices->cambiarEstadoCuenta((int)$a['usuario_id'], 'inactivo');
            $n = $this->cerrarPlanes($id, 'al retirar la matrícula', $actor);
            $this->auditoria->operacion($actor, 'Retirar', 'Matrículas', 'aprendices', $id,
                "Retiró la matrícula de {$a['nombre']}: queda como desertado y sin acceso"
                . ($n > 0 ? "; $n plan(es) de mejoramiento cerrados" : ''));
        });
    }

    /** Cierra los planes vigentes del aprendiz que deja la formación. */
    private function cerrarPlanes(int $aprendizId, string $motivo, Actor $actor): int {
        return (new MejoramientoModel($this->db))->cerrarVigentesDeAprendiz($aprendizId,
            "Cerrado automáticamente $motivo: el aprendiz dejó la formación.", $actor->id);
    }

    /** @return array Datos de la ficha de destino. */
    private function validar(array $d, ?int $aprendizId, ?int $usuarioId): array {
        $ficha = $this->aprendices->datosFicha((int)$d['ficha_id']) ?? throw new ErrorDeNegocio('La ficha seleccionada no existe.');
        if ($ficha['estado'] === 'cierre' && $aprendizId === null) {
            throw new ErrorDeNegocio("La ficha {$ficha['numero_ficha']} está en cierre: no admite matrículas nuevas.");
        }
        if ($this->aprendices->existeEmail($d['email'], $usuarioId)) {
            throw new ErrorDeNegocio("El correo {$d['email']} ya está registrado.");
        }
        if ($this->aprendices->existeDocumento($d['numero_documento'], $aprendizId)) {
            throw new ErrorDeNegocio("El documento {$d['numero_documento']} ya está matriculado.");
        }
        if ($d['instructor_seguimiento_id'] !== null && !$this->aprendices->esInstructorActivo((int)$d['instructor_seguimiento_id'])) {
            throw new ErrorDeNegocio('El instructor de seguimiento debe ser un instructor activo.');
        }
        if (($d['estado'] ?? 'matriculado') === 'etapa_practica' && $d['instructor_seguimiento_id'] === null) {
            throw new ErrorDeNegocio('En etapa práctica el aprendiz necesita un instructor de seguimiento, que es quien lo califica.');
        }
        return $ficha;
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación gestiona las matrículas.');
        }
    }
}
