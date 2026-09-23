<?php
declare(strict_types=1);

namespace Core\Support;

use Throwable;

/**
 * Manejador global de errores y excepciones.
 *
 * Antes no existía ninguno. Una excepción no capturada —por ejemplo la que
 * lanza SeguimientoController cuando llega una acción desconocida— salía a
 * la pantalla como traza de PHP: rutas absolutas del servidor, nombres de
 * clase, línea a línea. Y los `catch (Exception $e)` de los controladores
 * mostraban `$e->getMessage()` al usuario, que en un fallo de PDO incluye
 * el nombre de la tabla y de la columna.
 *
 * La regla que implanta esta clase:
 *
 *   - Al usuario se le da un mensaje genérico y un identificador corto.
 *   - El detalle completo va a `logs/error.log`, que el .htaccess de esa
 *     carpeta ya impide servir por web.
 *   - El identificador permite cruzar lo que el usuario reporta con la
 *     línea concreta del log, sin exponerle nada.
 *
 * En DEV_MODE sí se muestra el detalle en pantalla, porque en desarrollo
 * esconder el error solo alarga la depuración.
 */
final class ManejadorErrores {
    private static bool $registrado = false;

    public static function registrar(): void {
        if (self::$registrado) {
            return;
        }
        self::$registrado = true;

        $dev = defined('DEV_MODE') && DEV_MODE;

        // display_errors se decide aquí y no en php.ini, para que el
        // comportamiento del proyecto no dependa del servidor donde caiga.
        ini_set('display_errors', $dev ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::rutaLog());
        error_reporting(E_ALL);

        set_error_handler([self::class, 'manejarError']);
        set_exception_handler([self::class, 'manejarExcepcion']);
        register_shutdown_function([self::class, 'manejarCierre']);
    }

    /**
     * Avisos y notices de PHP.
     *
     * Deliberadamente NO se convierten en excepciones. Sería lo correcto en
     * un proyecto nuevo, pero aquí hay 36 vistas que acceden a claves de
     * array que pueden no existir: elevar cada "Undefined array key" a
     * excepción cambiaría una página que hoy se pinta con un hueco por una
     * pantalla de error. El aviso se registra para poder arreglarlo, y la
     * petición continúa.
     *
     * Los errores recuperables sí se elevan: son fallos de tipo, no
     * descuidos cosméticos, y seguir adelante deja datos a medias.
     */
    public static function manejarError(int $severidad, string $mensaje, string $archivo = '', int $linea = 0): bool {
        if (!(error_reporting() & $severidad)) {
            return false;   // silenciado con @
        }

        if (in_array($severidad, [E_RECOVERABLE_ERROR, E_USER_ERROR], true)) {
            throw new \ErrorException($mensaje, 0, $severidad, $archivo, $linea);
        }

        @file_put_contents(
            self::rutaLog(),
            sprintf("[%s] AVISO | %s | %s en %s:%d\n", date('Y-m-d H:i:s'),
                self::nombreSeveridad($severidad), $mensaje, $archivo, $linea),
            FILE_APPEND | LOCK_EX
        );

        return true;   // gestionado: no se imprime en pantalla
    }

    private static function nombreSeveridad(int $s): string {
        return match ($s) {
            E_WARNING, E_USER_WARNING     => 'WARNING',
            E_NOTICE,  E_USER_NOTICE      => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default                       => 'E' . $s,
        };
    }

    public static function manejarExcepcion(Throwable $e): void {
        $id = self::registrarEnLog($e);
        self::responder($e, $id);
    }

    /**
     * Errores fatales que no pasan por set_exception_handler (agotamiento
     * de memoria, límite de tiempo). Sin esto, el reporte más grande que
     * se pase de memoria devolvería una página en blanco sin rastro.
     */
    public static function manejarCierre(): void {
        $ultimo = error_get_last();
        if ($ultimo === null || !in_array($ultimo['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $e  = new \ErrorException($ultimo['message'], 0, $ultimo['type'], $ultimo['file'], $ultimo['line']);
        $id = self::registrarEnLog($e);
        self::responder($e, $id, true);
    }

    // -----------------------------------------------------------------

    /**
     * Escribe el detalle en el log y devuelve un identificador corto para
     * enseñárselo al usuario.
     */
    private static function registrarEnLog(Throwable $e): string {
        $id = strtoupper(bin2hex(random_bytes(4)));

        $usuario = 'anónimo';
        if (function_exists('getCurrentUser')) {
            $u = @getCurrentUser();
            if (is_array($u) && isset($u['id'])) {
                $usuario = 'usuario#' . $u['id'];
            }
        }

        $linea = sprintf(
            "[%s] %s | %s | %s %s | %s: %s en %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $id,
            $usuario,
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            // Solo la ruta: la query puede llevar filtros con datos personales.
            parse_url($_SERVER['REQUEST_URI'] ?? '-', PHP_URL_PATH) ?: '-',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents(self::rutaLog(), $linea, FILE_APPEND | LOCK_EX);
        return $id;
    }

    /**
     * Responde al cliente. Distingue si esperaba JSON, para no devolverle
     * una página HTML a una llamada AJAX.
     */
    private static function responder(Throwable $e, string $id, bool $desdeCierre = false): void {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $dev = defined('DEV_MODE') && DEV_MODE;

        if (self::esperaJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success'    => false,
                'message'    => 'Ha ocurrido un error interno.',
                'referencia' => $id,
                'detalle'    => $dev ? $e->getMessage() : null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // En un fatal por agotamiento de memoria puede haber salida a
        // medias; se cierra lo que hubiera abierto antes de pintar.
        if ($desdeCierre && ob_get_level() > 0) {
            @ob_end_clean();
        }

        echo self::paginaError($id, $dev ? $e : null);
    }

    private static function esperaJson(): bool {
        $accept  = $_SERVER['HTTP_ACCEPT'] ?? '';
        $ajax    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $uri     = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($accept, 'application/json')
            || $ajax === 'xmlhttprequest'
            || str_contains($uri, '/api');
    }

    private static function paginaError(string $id, ?Throwable $e): string {
        $urlInicio = defined('APP_URL') ? APP_URL . '/index.php/dashboard' : '/';
        $detalle   = '';

        if ($e !== null) {
            $detalle = '<pre style="text-align:left;background:#0f1720;color:#e6edf3;padding:16px;'
                . 'border-radius:8px;overflow:auto;font-size:12px;line-height:1.5">'
                . htmlspecialchars(
                    get_class($e) . ': ' . $e->getMessage()
                    . "\n\n" . $e->getFile() . ':' . $e->getLine()
                    . "\n\n" . $e->getTraceAsString(),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</pre>';
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Error del sistema</title><style>'
            . 'body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f5f7fa;color:#1f2933;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}'
            . '.caja{background:#fff;border-radius:14px;box-shadow:0 8px 28px rgba(16,24,40,.09);'
            . 'padding:36px;max-width:760px;width:100%;text-align:center}'
            . 'h1{color:#00324D;font-size:21px;margin:0 0 10px}p{color:#52606d;line-height:1.6;margin:0 0 8px}'
            . 'code{background:#eef2f6;padding:3px 9px;border-radius:5px;font-size:14px;color:#00324D}'
            . 'a{display:inline-block;margin-top:22px;background:#39A900;color:#fff;text-decoration:none;'
            . 'padding:11px 26px;border-radius:8px;font-weight:600}'
            . '</style></head><body><div class="caja">'
            . '<h1>Ha ocurrido un error inesperado</h1>'
            . '<p>No se ha podido completar la operación. El incidente ha quedado registrado.</p>'
            . '<p>Si necesitas reportarlo, indica esta referencia:</p>'
            . '<p><code>' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '</code></p>'
            . $detalle
            . '<a href="' . htmlspecialchars($urlInicio, ENT_QUOTES, 'UTF-8') . '">Volver al inicio</a>'
            . '</div></body></html>';
    }

    private static function rutaLog(): string {
        $dir = defined('BASE_PATH') ? BASE_PATH . 'logs' : __DIR__ . '/../../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/error.log';
    }
}
