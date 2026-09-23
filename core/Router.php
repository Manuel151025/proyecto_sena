<?php
declare(strict_types=1);

namespace Core;

use Core\Support\Auditable;
use Throwable;

/**
 * Enrutador del front controller.
 *
 * Cambios respecto a la versión anterior y por qué:
 *
 * 1. LAS RUTAS DECLARAN QUIÉN ENTRA. Antes el permiso vivía dentro del
 *    constructor de cada controlador, así que para saber quién podía llegar
 *    a /usuarios había que abrir UsuarioController. Con 69 rutas y 23
 *    controladores, auditar el control de acceso exigía leerlos todos, y
 *    nueve de ellos solo comprobaban "hay sesión" sin mirar el rol. Ahora
 *    el permiso está junto a la ruta: `index.php` se lee de arriba abajo
 *    como la matriz de accesos del sistema.
 *
 *    La comprobación del constructor se mantiene: son dos capas, y la del
 *    controlador protege también las llamadas que no pasan por el router.
 *
 * 2. LA TABLA DE RUTAS ES UN ÍNDICE, NO UNA LISTA. El despacho recorría
 *    las 69 rutas comparando cadenas una a una. Ahora se buscan por clave.
 *
 * 3. 405 FRENTE A 404. Pedir por GET algo que solo acepta POST devolvía
 *    "404 no encontrada", que es engañoso al depurar. Se distingue.
 *
 * 4. LA VALIDACIÓN CSRF YA NO SE REPITE AQUÍ. La hace `session.php` al
 *    cargarse, una sola vez y para todos los puntos de entrada, incluidos
 *    los que no pasan por el router.
 */
class Router {
    /** @var array<string, array{controller:string, action:string, roles:string[]}> */
    private array $routes = [];

    /** Métodos admitidos por cada ruta, para poder responder 405. */
    private array $metodosPorRuta = [];

    /**
     * Registra una ruta.
     *
     * @param string[] $roles Roles con acceso. Vacío significa "cualquier
     *        usuario autenticado"; el front controller ya exige sesión.
     */
    public function add(string $method, string $path, string $controller, string $action, array $roles = []): void {
        $method = strtoupper($method);
        $path   = '/' . trim($path, '/');

        $this->routes[$method . ' ' . $path] = [
            'controller' => $controller,
            'action'     => $action,
            'roles'      => $roles,
        ];
        $this->metodosPorRuta[$path][] = $method;
    }

    public function dispatch(string $method, string $uri): void {
        $path   = self::normalizarRuta($uri);
        $method = strtoupper($method);

        $ruta = $this->routes[$method . ' ' . $path] ?? null;

        if ($ruta === null) {
            if (isset($this->metodosPorRuta[$path])) {
                $this->responder(
                    405,
                    'Método no permitido',
                    'Esta dirección no admite peticiones ' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '.',
                    ['Allow: ' . implode(', ', array_unique($this->metodosPorRuta[$path]))]
                );
                return;
            }
            $this->responder(404, 'Página no encontrada',
                'La dirección solicitada no existe en el sistema.');
            return;
        }

        // Permiso declarado en la ruta. requireRole() ya audita el rechazo
        // y redirige al panel del usuario.
        if ($ruta['roles'] !== []) {
            requireRole(...$ruta['roles']);
        }

        $clase  = $ruta['controller'];
        $accion = $ruta['action'];

        if (!class_exists($clase) || !method_exists($clase, $accion)) {
            // Es un fallo de configuración de rutas, no del usuario: se
            // registra con nombres concretos para poder arreglarlo.
            error_log("Router: la ruta $method $path apunta a $clase::$accion, que no existe.");
            $this->responder(500, 'Error de configuración',
                'La sección solicitada no está disponible.');
            return;
        }

        (new $clase())->$accion();
    }

    /**
     * Quita del path el nombre del script y la carpeta base, para que las
     * rutas se declaren sin saber dónde está instalada la aplicación
     * (/proyecto_sena/index.php/usuarios -> /usuarios).
     */
    public static function normalizarRuta(string $uri): string {
        $path = '/' . trim((string)parse_url($uri, PHP_URL_PATH), '/');
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        if ($scriptName !== '' && str_starts_with($path, $scriptName)) {
            $path = substr($path, strlen($scriptName));
        } else {
            $baseDir = str_replace('\\', '/', dirname($scriptName));
            if ($baseDir !== '/' && $baseDir !== '' && str_starts_with($path, $baseDir)) {
                $path = substr($path, strlen($baseDir));
            }
        }

        return '/' . trim($path, '/');
    }

    /**
     * Respuesta de error del enrutador, con el mismo aspecto que el resto
     * del sistema. Antes era `echo "404 - Página no encontrada"` en texto
     * plano sobre fondo blanco.
     *
     * @param string[] $cabeceras
     */
    private function responder(int $codigo, string $titulo, string $detalle, array $cabeceras = []): void {
        http_response_code($codigo);
        foreach ($cabeceras as $c) {
            header($c);
        }

        $inicio = defined('APP_URL') ? APP_URL . '/index.php/dashboard' : '/';

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title><style>'
           . 'body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f5f7fa;'
           . 'color:#1f2933;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}'
           . '.caja{background:#fff;border-radius:14px;box-shadow:0 8px 28px rgba(16,24,40,.09);'
           . 'padding:40px;max-width:480px;text-align:center}'
           . '.cod{font-size:52px;font-weight:800;color:#39A900;line-height:1;margin-bottom:8px}'
           . 'h1{font-size:19px;color:#00324D;margin:0 0 10px}p{color:#52606d;line-height:1.6;margin:0}'
           . 'a{display:inline-block;margin-top:22px;background:#39A900;color:#fff;text-decoration:none;'
           . 'padding:11px 26px;border-radius:8px;font-weight:600}'
           . '</style></head><body><div class="caja">'
           . '<div class="cod">' . $codigo . '</div>'
           . '<h1>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>'
           . '<p>' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . '</p>'
           . '<a href="' . htmlspecialchars($inicio, ENT_QUOTES, 'UTF-8') . '">Volver al inicio</a>'
           . '</div></body></html>';
    }

    /**
     * Rutas registradas, para poder auditarlas o probarlas.
     *
     * @return array<string, array{controller:string, action:string, roles:string[]}>
     */
    public function rutas(): array {
        return $this->routes;
    }
}
