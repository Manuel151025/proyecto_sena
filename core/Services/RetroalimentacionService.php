<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Formularios\RetroalimentacionFormulario;
use Core\Models\RetroalimentacionModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\Transaccion;
use PDO;

/**
 * Registrar retroalimentación u observaciones de seguimiento.
 *
 * Lo usan /retroalimentacion y /seguimiento: antes cada pantalla tenía su
 * propia copia (con distintas reglas: una exigía que el aprendiz fuera de
 * la ficha que lidera el instructor, la otra aceptaba también asignaciones
 * y seguimiento).
 */
final class RetroalimentacionService {
    private PDO $db;
    private RetroalimentacionModel $modelo;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->modelo = new RetroalimentacionModel($this->db);
    }

    public function registrar(array $d, Actor $actor): int {
        if (!$actor->gestiona()) {
            throw new ErrorDeNegocio('Solo instructores y coordinación registran retroalimentación.');
        }
        $ap = $this->modelo->aprendiz($d['aprendiz_id']) ?? throw new ErrorDeNegocio('El aprendiz no existe.');
        if ($actor->esInstructor() && !(new InstructorAccessService($this->db))->tieneAccesoAprendiz((int)$ap['id'], $actor->id)) {
            throw new ErrorDeNegocio('El aprendiz no es de tus fichas ni de tu seguimiento.');
        }
        if ($d['evaluacion_id'] !== null && !$this->modelo->evaluacionDelAprendiz($d['evaluacion_id'], (int)$ap['id'])) {
            throw new ErrorDeNegocio('El resultado de aprendizaje no es de ese aprendiz.');
        }
        return Transaccion::ejecutar($this->db, function () use ($d, $ap, $actor) {
            $id = $this->modelo->crear($d, $actor->id);
            $tipo = RetroalimentacionFormulario::TIPOS[$d['tipo']][0] ?? $d['tipo'];
            (new Auditoria($this->db))->operacion($actor, 'Crear', 'Retroalimentación', 'retroalimentacion', $id,
                ($d['privada'] ? 'Observación privada' : $tipo) . " para {$ap['nombre']}");
            if (!$d['privada']) {
                (new Notificador($this->db))->notificar((int)$ap['usuario_id'], 'Nueva retroalimentación',
                    "$tipo: " . mb_substr($d['contenido'], 0, 120) . (mb_strlen($d['contenido']) > 120 ? '…' : ''),
                    $d['tipo'] === 'fortaleza' ? 'success' : 'info', '/index.php/retroalimentacion');
            }
            return $id;
        });
    }
}
