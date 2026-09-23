<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Models\NotificacionesModel;
use PDO;
use Throwable;

/**
 * Crea los avisos internos que ve cada usuario en la campana de la barra
 * superior.
 *
 * La infraestructura de notificaciones existía completa (tabla, API y
 * campana), pero ningún evento de negocio generaba avisos: la tabla estaba
 * vacía. Este servicio es el único punto por el que se escriben, y es
 * quien garantiza tres cosas que la vista y el JavaScript dan por hechas:
 *
 *  1. Los textos van acotados a lo que cabe en la columna y sin marcado.
 *  2. La URL es siempre una ruta interna de la aplicación. Un enlace
 *     `javascript:` o a otro dominio en una notificación es un XSS o un
 *     phishing servido desde dentro del propio sistema.
 *  3. Un fallo al notificar NUNCA tumba la operación de negocio que lo
 *     provocó: calificar un RAP no puede fallar porque no se pudo avisar.
 */
final class Notificador {
    public const TIPOS = ['info', 'success', 'warning', 'danger'];

    private NotificacionesModel $modelo;

    public function __construct(?PDO $db = null, ?NotificacionesModel $modelo = null) {
        $this->modelo = $modelo ?? new NotificacionesModel($db);
    }

    /**
     * @param string      $ruta Ruta interna relativa a APP_URL, p. ej.
     *                          '/index.php/evaluaciones'. Cualquier otra
     *                          cosa se descarta.
     * @return int|null Id creado, o null si no se pudo (queda en el log).
     */
    public function notificar(int $usuarioId, string $titulo, string $mensaje, string $tipo = 'info', ?string $ruta = null): ?int {
        if ($usuarioId <= 0) {
            return null;
        }
        try {
            $tipo = in_array($tipo, self::TIPOS, true) ? $tipo : 'info';
            return $this->modelo->crear(
                $usuarioId,
                self::recortar($titulo, 255),
                self::recortar($mensaje, 1000),
                $tipo,
                self::urlSegura($ruta)
            );
        } catch (Throwable $e) {
            error_log('Notificador: no se pudo crear el aviso para el usuario ' . $usuarioId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mismo aviso a varios destinatarios (p. ej. todos los aprendices de
     * una ficha). Los ids repetidos o no válidos se descartan.
     *
     * @param int[] $usuarios
     */
    public function notificarVarios(array $usuarios, string $titulo, string $mensaje, string $tipo = 'info', ?string $ruta = null): int {
        $creadas = 0;
        foreach (array_unique(array_map('intval', $usuarios)) as $uid) {
            if ($this->notificar($uid, $titulo, $mensaje, $tipo, $ruta) !== null) {
                $creadas++;
            }
        }
        return $creadas;
    }

    /**
     * Devuelve la URL completa si `$ruta` es una ruta interna segura, o
     * null en cualquier otro caso.
     *
     * Se admite solo `/index.php/...` con caracteres de URL corrientes: nada
     * de esquemas (`javascript:`, `data:`), ni `//dominio`, ni barras
     * invertidas que algunos navegadores normalizan a `/`.
     */
    public static function urlSegura(?string $ruta): ?string {
        if ($ruta === null || $ruta === '') {
            return null;
        }
        $base = defined('APP_URL') ? APP_URL : '';

        // Se acepta la ruta ya prefijada con APP_URL o sin prefijo.
        if ($base !== '' && str_starts_with($ruta, $base . '/')) {
            $ruta = substr($ruta, strlen($base));
        }
        if (!preg_match('#^/index\.php/[A-Za-z0-9/_\-]*(\?[A-Za-z0-9_\-=&%.]*)?$#', $ruta)) {
            return null;
        }
        return mb_substr($base . $ruta, 0, 255);
    }

    private static function recortar(string $texto, int $max): string {
        $limpio = trim(preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? '');
        return mb_strlen($limpio, 'UTF-8') > $max ? mb_substr($limpio, 0, $max - 1, 'UTF-8') . '…' : $limpio;
    }
}
