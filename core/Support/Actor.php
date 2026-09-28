<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Quién ejecuta una operación.
 *
 * Los servicios reciben al actor como argumento en lugar de leer la sesión
 * por su cuenta. Así una regla de negocio ("un instructor solo edita las
 * actividades de sus fichas") no depende de `$_SESSION`, se prueba pasando
 * un Actor construido a mano y se puede invocar igual desde un comando de
 * consola que desde una petición web.
 */
final class Actor {
    public function __construct(
        public readonly int $id,
        public readonly string $rol,
    ) {}

    /** El usuario de la pestaña actual. */
    public static function actual(): self {
        $u = getCurrentUser();
        return new self((int)($u['id'] ?? 0), (string)($u['rol'] ?? ''));
    }

    public function esCoordinador(): bool {
        return $this->rol === ROL_COORDINADOR;
    }

    public function esInstructor(): bool {
        return $this->rol === ROL_INSTRUCTOR;
    }

    public function esAprendiz(): bool {
        return $this->rol === ROL_APRENDIZ;
    }

    /** Coordinador o instructor: quien gestiona la formación. */
    public function gestiona(): bool {
        return $this->esCoordinador() || $this->esInstructor();
    }
}
