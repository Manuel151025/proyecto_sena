<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use PDO;
use Throwable;

/**
 * Bitácora de auditoría (`logs_sistema`), en un solo sitio.
 *
 * Había 17 `INSERT INTO logs_sistema` escritos a mano repartidos por nueve
 * archivos, cada uno con su propio criterio para el nombre de la acción y
 * el texto de la descripción. Y faltaba justo lo que una auditoría tiene
 * que registrar: ni el inicio de sesión, ni el cierre, ni los intentos
 * fallidos, ni los cambios de contraseña, ni los accesos denegados dejaban
 * rastro. La tabla tenía una sola fila.
 *
 * Reglas que impone esta clase:
 *
 *  - Registrar nunca rompe la operación auditada. Si la bitácora falla, se
 *    anota en el log de errores y la acción del usuario continúa: perder
 *    una línea de auditoría es malo, pero impedir que un instructor
 *    califique porque la tabla de logs no responde es peor.
 *  - Nunca se guardan contraseñas, tokens ni el contenido de los campos
 *    sensibles. La descripción dice qué pasó, no con qué datos.
 */
final class Auditoria {
    // Acciones sobre datos
    public const CREAR     = 'Crear';
    public const EDITAR    = 'Editar';
    public const ELIMINAR  = 'Eliminar';
    public const CALIFICAR = 'Calificar';
    public const IMPORTAR  = 'Importar';
    public const EXPORTAR  = 'Exportar';

    // Eventos de seguridad, que antes no se registraban
    public const LOGIN           = 'Acceso';
    public const LOGIN_FALLIDO   = 'Acceso fallido';
    public const LOGOUT          = 'Cierre de sesión';
    public const PASSWORD        = 'Cambio de contraseña';
    public const PERMISO_DENEGADO = 'Permiso denegado';

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Registra una entrada.
     *
     * @param int|null $usuarioId Quién. null para eventos previos al login.
     * @param string   $accion    Una de las constantes de esta clase.
     * @param string   $modulo    Pantalla o servicio de origen.
     * @param string|null $tabla  Tabla afectada, si aplica.
     * @param int|null $registro  Id del registro afectado, si aplica.
     */
    public function registrar(
        ?int $usuarioId,
        string $accion,
        string $modulo,
        string $descripcion,
        ?string $tabla = null,
        ?int $registro = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO logs_sistema
                    (usuario_id, accion, modulo, tabla_afectada, id_registro, descripcion)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuarioId,
                mb_substr($accion, 0, 50),
                mb_substr($modulo, 0, 50),
                $tabla !== null ? mb_substr($tabla, 0, 50) : null,
                $registro,
                mb_substr($descripcion, 0, 500),
            ]);
        } catch (Throwable $e) {
            // Ver la nota de la cabecera: auditar no puede tumbar la acción.
            error_log('Auditoria: no se pudo registrar "' . $accion . '" — ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // EVENTOS DE SEGURIDAD
    // -----------------------------------------------------------------

    public function accesoCorrecto(int $usuarioId, string $rol): void {
        $this->registrar($usuarioId, self::LOGIN, 'Autenticación',
            "Inicio de sesión correcto (rol: $rol)", 'usuarios', $usuarioId);
    }

    /**
     * Intento fallido. No se guarda el correo completo: basta el dominio
     * para detectar un patrón de ataque, y así la bitácora no se convierte
     * en una lista de cuentas que alguien estuvo probando.
     */
    public function accesoFallido(string $email): void {
        $dominio = strstr($email, '@') ?: '(sin dominio)';
        $this->registrar(null, self::LOGIN_FALLIDO, 'Autenticación',
            "Intento de acceso fallido para una cuenta $dominio", 'usuarios');
    }

    public function cierreSesion(int $usuarioId): void {
        $this->registrar($usuarioId, self::LOGOUT, 'Autenticación',
            'Cierre de sesión', 'usuarios', $usuarioId);
    }

    public function cambioPassword(int $usuarioId, string $via): void {
        $this->registrar($usuarioId, self::PASSWORD, 'Autenticación',
            "Contraseña modificada ($via)", 'usuarios', $usuarioId);
    }

    /**
     * Acceso denegado. Es la señal que delata a alguien probando ids o
     * rutas que no le corresponden, y era justo lo que no se registraba.
     */
    public function permisoDenegado(?int $usuarioId, string $recurso, string $detalle = ''): void {
        $this->registrar($usuarioId, self::PERMISO_DENEGADO, 'Autorización',
            trim("Acceso denegado a $recurso. $detalle"), null);
    }
}
