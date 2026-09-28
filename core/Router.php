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
     * Acciones POST por ruta: `[ruta][nombre] => destino`.
     *
     * @var array<string, array<string, array{controller:string, action:string, roles:string[]}>>
     */
    private array $acciones = [];

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

    /**
     * Registra una acción POST de una pantalla: el formulario envía
     * `action=<nombre>` a la misma dirección que la lista.
     *
     * Antes cada controlador tenía un único `index()` que atendía GET y POST
     * y, dentro, una cadena de `if ($_POST['action'] === ...)` con la
     * validación, el permiso y la escritura de cada operación mezclados.
     * Con esto cada operación es un método propio y **su permiso se declara
     * aquí**, por separado del de la pantalla: `/proyectos` lo ven los tres
     * roles, pero `crear` en `/proyectos` solo lo tiene coordinación. Esa
     * diferencia antes vivía enterrada en un `&& $user_rol === ...`.
     *
     * @param string[] $roles
     */
    public function accion(string $path, string $nombre, string $controller, string $method, array $roles): void {
        $path = '/' . trim($path, '/');
        $this->acciones[$path][$nombre] = [
            'controller' => $controller,
            'action'     => $method,
            'roles'      => $roles,
        ];
        $this->metodosPorRuta[$path][] = 'POST';
    }

    public function dispatch(string $method, string $uri): void {
        $path   = self::normalizarRuta($uri);
        $method = strtoupper($method);
        $esApi  = self::esRutaApi($path);

        $ruta = $this->resolver($method, $path);

        if ($ruta === null) {
            if ($method === 'POST' && isset($this->acciones[$path])) {
                // La pantalla existe y tiene acciones, pero no esta. Suele
                // ser un formulario manipulado: se responde 400 en vez de
                // caer en silencio al listado como antes.
                self::responder(400, 'Acción no válida',
                    'La operación solicitada no existe en esta sección.', [], $esApi);
                return;
            }
            if (isset($this->metodosPorRuta[$path])) {
                self::responder(
                    405,
                    'Método no permitido',
                    'Esta dirección no admite peticiones ' . $method . '.',
                    ['Allow: ' . implode(', ', array_unique($this->metodosPorRuta[$path]))],
                    $esApi
                );
                return;
            }
            self::responder(404, 'Página no encontrada',
                'La dirección solicitada no existe en el sistema.', [], $esApi);
            return;
        }

        // Permiso declarado en la ruta.
        if ($ruta['roles'] !== [] && !in_array(getCurrentRole(), $ruta['roles'], true)) {
            if ($esApi) {
                // Una API no puede responder con una redirección al panel:
                // el `fetch` la seguiría y recibiría HTML donde espera JSON.
                $u = getCurrentUser();
                (new \Core\Services\Auditoria())->permisoDenegado(
                    $u !== null ? (int)$u['id'] : null, $path,
                    'Rol actual: ' . getCurrentRole()
                );
                self::responder(403, 'Acceso denegado', 'No tienes permiso para esta operación.', [], true);
                return;
            }
            // requireRole() audita el rechazo y redirige al panel del usuario.
            requireRole(...$ruta['roles']);
        }

        // Sesión válida contra la base (usuario activo, rol vigente) y
        // contraseña temporal cambiada. Se hace aquí para todas las rutas y
        // no solo en los constructores que se acordaban de llamarlo.
        requireAuth();

        $clase  = $ruta['controller'];
        $accion = $ruta['action'];

        if (!class_exists($clase) || !method_exists($clase, $accion)) {
            // Es un fallo de configuración de rutas, no del usuario: se
            // registra con nombres concretos para poder arreglarlo.
            error_log("Router: la ruta $method $path apunta a $clase::$accion, que no existe.");
            self::responder(500, 'Error de configuración',
                'La sección solicitada no está disponible.', [], $esApi);
            return;
        }

        (new $clase())->$accion();
    }

    /**
     * Destino de una petición: primero la acción POST concreta
     * (`action=crear`), después la ruta general del método.
     *
     * @return array{controller:string, action:string, roles:string[]}|null
     */
    public function resolver(string $method, string $path, ?array $post = null): ?array {
        $post ??= $_POST;
        if ($method === 'POST' && isset($this->acciones[$path])) {
            $nombre = $post['action'] ?? null;
            if (is_string($nombre) && isset($this->acciones[$path][$nombre])) {
                return $this->acciones[$path][$nombre];
            }
            // Formularios de la misma pantalla que no usan `action` (p. ej.
            // una importación) siguen yendo a la ruta POST general.
            return $this->routes['POST ' . $path] ?? null;
        }
        return $this->routes[$method . ' ' . $path] ?? null;
    }

    /** Las API responden siempre JSON, también los errores. */
    public static function esRutaApi(string $path): bool {
        return str_starts_with($path, '/api/') || $path === '/calendario/api';
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
     * del sistema (o en JSON, si la ruta es una API).
     *
     * @param string[] $cabeceras
     */
    public static function responder(int $codigo, string $titulo, string $detalle, array $cabeceras = [], bool $json = false): void {
        http_response_code($codigo);
        foreach ($cabeceras as $c) {
            header($c);
        }

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $detalle], JSON_UNESCAPED_UNICODE);
            return;
        }

        $base   = defined('APP_URL') ? APP_URL : '';
        $inicio = $base . '/index.php/dashboard';

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title>'
           . '<link rel="stylesheet" href="' . htmlspecialchars($base . '/assets/css/error.css', ENT_QUOTES, 'UTF-8') . '">'
           . '</head><body class="pagina-error"><main class="caja">'
           . '<div class="cod">' . $codigo . '</div>'
           . '<h1>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>'
           . '<p>' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . '</p>'
           . '<a href="' . htmlspecialchars($inicio, ENT_QUOTES, 'UTF-8') . '">Volver al inicio</a>'
           . '</main></body></html>';
    }

    /**
     * Rutas registradas, para poder auditarlas o probarlas.
     *
     * @return array<string, array{controller:string, action:string, roles:string[]}>
     */
    public function rutas(): array {
        return $this->routes;
    }

    /**
     * Acciones POST registradas, por ruta.
     *
     * @return array<string, array<string, array{controller:string, action:string, roles:string[]}>>
     */
    public function acciones(): array {
        return $this->acciones;
    }
}
