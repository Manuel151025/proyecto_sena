<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use PDO;
use Throwable;

/**
 * Limitador de intentos para las acciones sensibles no autenticadas:
 * inicio de sesión y solicitud de recuperación de contraseña.
 *
 * El control anterior vivía en `$_SESSION['login_attempts']`. Eso solo
 * frena a quien conserve la cookie: un script que descarte las cookies
 * entre peticiones —lo normal en un ataque de fuerza bruta— empezaba
 * siempre de cero. Y la recuperación de contraseña no tenía ningún límite,
 * así que se podía enumerar qué correos existen y enviar correos sin tope
 * a una víctima.
 *
 * Aquí el registro está en la base de datos y se cuenta por dos claves a
 * la vez:
 *
 *   - Por identidad (el correo): frena el ataque a una cuenta concreta
 *     aunque venga de muchas direcciones.
 *   - Por IP: frena el barrido de muchas cuentas desde un mismo origen.
 *
 * Contar solo por IP castigaría a todo un centro de formación que sale por
 * NAT; contar solo por correo deja libre el barrido. Por eso van las dos.
 */
final class LimitadorIntentos {
    /** Intentos fallidos por identidad antes de bloquear. */
    private const MAX_POR_IDENTIDAD = 5;

    /** Intentos fallidos por IP antes de bloquear (más holgado por el NAT). */
    private const MAX_POR_IP = 20;

    /** Ventana de observación, en segundos. */
    private const VENTANA = 900;      // 15 minutos

    /** Duración del bloqueo una vez superado el máximo. */
    private const BLOQUEO = 900;      // 15 minutos

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Segundos que quedan de bloqueo, o 0 si la acción está permitida.
     *
     * Ante un fallo de base de datos devuelve 0 (permite): dejar a todo el
     * mundo fuera del sistema porque la tabla de intentos no responde sería
     * peor que el riesgo que cubre.
     */
    public function segundosBloqueo(string $accion, string $identidad): int {
        try {
            $restanteIdentidad = $this->restante($accion, 'id:' . $this->normalizar($identidad), self::MAX_POR_IDENTIDAD);
            $restanteIp        = $this->restante($accion, 'ip:' . $this->ip(), self::MAX_POR_IP);
            return max($restanteIdentidad, $restanteIp);
        } catch (Throwable $e) {
            error_log('LimitadorIntentos: no se pudo consultar (' . $e->getMessage() . ')');
            return 0;
        }
    }

    public function estaBloqueado(string $accion, string $identidad): bool {
        return $this->segundosBloqueo($accion, $identidad) > 0;
    }

    /** Registra un intento fallido para las dos claves. */
    public function registrarFallo(string $accion, string $identidad): void {
        try {
            $this->incrementar($accion, 'id:' . $this->normalizar($identidad));
            $this->incrementar($accion, 'ip:' . $this->ip());
        } catch (Throwable $e) {
            error_log('LimitadorIntentos: no se pudo registrar el fallo (' . $e->getMessage() . ')');
        }
    }

    /**
     * Limpia el contador tras un intento correcto. Solo se limpia la clave
     * de identidad: la de IP se mantiene para que un atacante no pueda
     * reiniciar su cupo acertando con una cuenta cualquiera que controle.
     */
    public function registrarExito(string $accion, string $identidad): void {
        try {
            $stmt = $this->db->prepare("DELETE FROM intentos_acceso WHERE accion = ? AND clave = ?");
            $stmt->execute([$accion, 'id:' . $this->normalizar($identidad)]);
        } catch (Throwable $e) {
            error_log('LimitadorIntentos: no se pudo limpiar (' . $e->getMessage() . ')');
        }
    }

    /** Minutos redondeados hacia arriba, para el mensaje al usuario. */
    public static function minutos(int $segundos): int {
        return max(1, (int)ceil($segundos / 60));
    }

    // -----------------------------------------------------------------

    private function restante(string $accion, string $clave, int $maximo): int {
        $stmt = $this->db->prepare("
            SELECT intentos, UNIX_TIMESTAMP(ultimo_intento) AS ultimo
              FROM intentos_acceso
             WHERE accion = ? AND clave = ?
        ");
        $stmt->execute([$accion, $clave]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return 0;
        }

        $transcurrido = time() - (int)$fila['ultimo'];

        // Fuera de la ventana, el historial ya no cuenta.
        if ($transcurrido > self::VENTANA) {
            return 0;
        }
        if ((int)$fila['intentos'] < $maximo) {
            return 0;
        }
        return max(0, self::BLOQUEO - $transcurrido);
    }

    private function incrementar(string $accion, string $clave): void {
        // El reinicio de la ventana se resuelve en la misma sentencia: si el
        // último intento quedó fuera de la ventana, el contador vuelve a 1.
        $stmt = $this->db->prepare("
            INSERT INTO intentos_acceso (accion, clave, intentos, primer_intento, ultimo_intento)
            VALUES (?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                intentos = IF(ultimo_intento < (NOW() - INTERVAL ? SECOND), 1, intentos + 1),
                primer_intento = IF(ultimo_intento < (NOW() - INTERVAL ? SECOND), NOW(), primer_intento),
                ultimo_intento = NOW()
        ");
        $stmt->execute([$accion, $clave, self::VENTANA, self::VENTANA]);
    }

    /**
     * La identidad se normaliza para que 'Admin@SENA.edu.co' y
     * 'admin@sena.edu.co' compartan contador, y se hashea para no guardar
     * en claro los correos con los que alguien intentó entrar.
     */
    private function normalizar(string $identidad): string {
        return hash('sha256', mb_strtolower(trim($identidad), 'UTF-8'));
    }

    private function ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Deliberadamente NO se lee X-Forwarded-For: el cliente la controla,
        // así que confiar en ella permitiría saltarse el límite cambiando una
        // cabecera. Si se despliega tras un proxy inverso, hay que resolver
        // la IP real en el servidor web (mod_remoteip) y no aquí.
        return hash('sha256', (string)$ip);
    }
}
