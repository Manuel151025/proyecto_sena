<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Interfaces\UsuarioRepositoryInterface;
use Core\Models\UsuarioModel;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use Core\Support\ErroresBD;
use Core\Support\PoliticaContrasena;
use PDO;
use Throwable;

/**
 * Administración de cuentas (solo coordinación).
 *
 * Reglas nuevas:
 *  - La contraseña inicial la genera el sistema, distinta para cada cuenta,
 *    y se exige cambiarla en el primer acceso. Antes el coordinador tecleaba
 *    una (6 caracteres bastaban) y la conocía para siempre: la HU-04 decía
 *    que toda cuenta creada recibía una temporal, pero solo lo cumplía la
 *    importación.
 *  - Nadie puede desactivarse ni quitarse el rol de coordinador a sí mismo,
 *    y el sistema no puede quedarse sin ningún coordinador activo: con un
 *    clic se perdía el acceso a toda la administración.
 */
final class UsuariosService {
    private UsuarioRepositoryInterface $repo;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null, ?UsuarioRepositoryInterface $repo = null, ?Auditoria $auditoria = null) {
        $db ??= Database::getConnection();
        $this->repo = $repo ?? new UsuarioModel($db);
        $this->auditoria = $auditoria ?? new Auditoria($db);
    }

    /** @return array{id:int, temporal:string} */
    public function crear(array $d, Actor $actor): array {
        $this->soloCoordinacion($actor);
        if ($this->repo->existeEmail($d['email'])) {
            throw new ErrorDeNegocio("Ya existe una cuenta con el correo {$d['email']}.");
        }
        $temporal = PoliticaContrasena::temporal();
        try {
            $id = $this->repo->crear($d, password_hash($temporal, PASSWORD_DEFAULT), true);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Ya existe una cuenta con el correo {$d['email']}."]);
        }
        $this->auditoria->operacion($actor, 'Crear', 'Usuarios', 'usuarios', $id, "Creó la cuenta {$d['email']} ({$d['rol']})");
        return ['id' => $id, 'temporal' => $temporal];
    }

    public function editar(int $id, array $d, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $actual = $this->repo->findById($id) ?? throw new ErrorDeNegocio('El usuario no existe.');
        if ($this->repo->existeEmail($d['email'], $id)) {
            throw new ErrorDeNegocio("Otra cuenta ya usa el correo {$d['email']}.");
        }
        $this->protegerCoordinacion($id, $actual, $d['rol'], $d['estado'], $actor);
        try {
            $this->repo->actualizar($id, $d);
        } catch (Throwable $e) {
            ErroresBD::relanzar($e, [ErroresBD::DUPLICADO => "Otra cuenta ya usa el correo {$d['email']}."]);
        }
        $cambios = [];
        if ($actual['rol'] !== $d['rol']) {
            $cambios[] = "rol {$actual['rol']} → {$d['rol']}";
        }
        if ($actual['estado'] !== $d['estado']) {
            $cambios[] = "estado {$actual['estado']} → {$d['estado']}";
        }
        $this->auditoria->operacion($actor, 'Editar', 'Usuarios', 'usuarios', $id,
            "Editó la cuenta {$d['email']}" . ($cambios ? ' (' . implode(', ', $cambios) . ')' : ''));
    }

    public function cambiarEstado(int $id, string $estado, Actor $actor): void {
        $this->soloCoordinacion($actor);
        $actual = $this->repo->findById($id) ?? throw new ErrorDeNegocio('El usuario no existe.');
        $this->protegerCoordinacion($id, $actual, $actual['rol'], $estado, $actor);
        $this->repo->cambiarEstado($id, $estado);
        $this->auditoria->operacion($actor, $estado === 'activo' ? 'Activar' : 'Desactivar', 'Usuarios', 'usuarios', $id,
            "Cambió el estado de {$actual['email']} a $estado");
    }

    /**
     * Nueva contraseña temporal para quien la olvidó y no tiene acceso al
     * correo. La anterior deja de servir en el acto.
     */
    public function restablecerContrasena(int $id, Actor $actor): string {
        $this->soloCoordinacion($actor);
        $u = $this->repo->findById($id) ?? throw new ErrorDeNegocio('El usuario no existe.');
        $temporal = PoliticaContrasena::temporal();
        $this->repo->fijarPassword($id, password_hash($temporal, PASSWORD_DEFAULT), true);
        $this->auditoria->operacion($actor, Auditoria::PASSWORD, 'Usuarios', 'usuarios', $id, "Restableció la contraseña de {$u['email']}");
        return $temporal;
    }

    private function protegerCoordinacion(int $id, array $actual, string $rolNuevo, string $estadoNuevo, Actor $actor): void {
        if ($id === $actor->id) {
            if ($estadoNuevo !== 'activo') {
                throw new ErrorDeNegocio('No puedes desactivar tu propia cuenta.');
            }
            if ($rolNuevo !== ROL_COORDINADOR) {
                throw new ErrorDeNegocio('No puedes quitarte a ti mismo el rol de coordinador.');
            }
        }
        $dejaDeCoordinar = $actual['rol'] === ROL_COORDINADOR && $actual['estado'] === 'activo'
            && ($rolNuevo !== ROL_COORDINADOR || $estadoNuevo !== 'activo');
        if ($dejaDeCoordinar && $this->repo->contarCoordinadoresActivos($id) === 0) {
            throw new ErrorDeNegocio('Debe quedar al menos un coordinador activo en el sistema.');
        }
    }

    private function soloCoordinacion(Actor $actor): void {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación administra cuentas.');
        }
    }
}
