<?php
declare(strict_types=1);

namespace Core;

use Core\Support\ErrorDeNegocio;
use Core\Support\Seguridad;
use Core\Support\Validador;
use Throwable;

/**
 * Base de todos los controladores.
 *
 * Reúne lo que cada controlador repetía a mano: leer el usuario de la
 * sesión, validar la entrada, responder con Post/Redirect/Get y traducir
 * una excepción a un mensaje que se pueda enseñar. Con esto cada acción
 * queda en lo que la hace distinta —qué valida y a qué servicio llama— en
 * lugar de ocho líneas de fontanería alrededor.
 *
 * Convención de las acciones POST (ver `Router::accion()`):
 *
 *     public function crear(): never {
 *         $v = $this->entrada();
 *         $datos = [...$v->texto(...), ...];
 *         $this->siHayErrores($v, $destino);
 *         $this->ejecutar(fn() => $this->servicio->crear($datos), $destino, 'Registrado.', 'Error al registrar');
 *     }
 */
abstract class BaseController {
    /**
     * Pinta una vista dentro del layout principal.
     */
    protected function render(string $viewPath, array $data = [], string $pageTitle = 'SENA'): void {
        // Marca de que la vista se está pintando desde un controlador. Cada
        // vista la comprueba: abiertas directamente por URL no deben
        // ejecutarse, porque lo harían sin sus variables y sin haber pasado
        // por ningún control de permisos.
        if (!defined('VISTA_PERMITIDA')) {
            define('VISTA_PERMITIDA', true);
        }

        // Una página autenticada no debe quedar en la caché del navegador:
        // en un equipo compartido, el botón "atrás" tras cerrar sesión
        // mostraba los datos del usuario anterior.
        Seguridad::sinCache();

        // `extract` no puede sobrescribir las variables de este método: si
        // una vista recibe un dato llamado 'viewPath' o 'contentView', el
        // layout cargaría otra cosa.
        extract($data, EXTR_SKIP);

        $contentView = $viewPath;
        $app_included = true;
        require __DIR__ . '/../layouts/app.php';
    }

    protected function redirect(string $url): never {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirige a una ruta interna de la aplicación (`/fichas/ver?id=3`).
     */
    protected function irA(string $ruta): never {
        $this->redirect(APP_URL . '/index.php' . $ruta);
    }

    /**
     * Respuesta JSON. Las API de la aplicación la usan también para los
     * errores, con su código HTTP: un 200 con `{"error": ...}` obliga al
     * cliente a mirar el cuerpo para saber si funcionó.
     */
    protected function json(mixed $data, int $status = 200): never {
        Seguridad::sinCache();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // -----------------------------------------------------------------
    // USUARIO ACTUAL
    // -----------------------------------------------------------------

    protected function usuarioId(): int {
        return (int)(getCurrentUser()['id'] ?? 0);
    }

    protected function rol(): string {
        return getCurrentRole();
    }

    protected function esRol(string ...$roles): bool {
        return in_array($this->rol(), $roles, true);
    }

    /**
     * Segunda capa del permiso por rol dentro de una acción. La primera es
     * la tabla de rutas; esta protege además las llamadas que no pasan por
     * el enrutador (pruebas, reutilización desde otro controlador).
     */
    protected function exigirRol(string ...$roles): void {
        if (!$this->esRol(...$roles)) {
            denyAccess('No tienes permiso para realizar esta acción.');
        }
    }

    // -----------------------------------------------------------------
    // ENTRADA
    // -----------------------------------------------------------------

    /** Validador sobre el cuerpo POST (o los datos que se indiquen). */
    protected function entrada(?array $datos = null): Validador {
        return new Validador($datos ?? $_POST);
    }

    /** Validador sobre la query string, para filtros de listados. */
    protected function consulta(): Validador {
        return new Validador($_GET);
    }

    /**
     * Entero positivo de la query string, o 0. Para `?id=`, `?ficha_id=`…
     * donde un valor ausente o manipulado significa simplemente "ninguno".
     */
    protected function idDeConsulta(string $campo): int {
        $v = $_GET[$campo] ?? null;
        if (!is_string($v) && !is_int($v)) {
            return 0;
        }
        $n = filter_var(trim((string)$v), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $n === false ? 0 : $n;
    }

    // -----------------------------------------------------------------
    // RESPUESTA DE LAS ACCIONES (Post/Redirect/Get)
    // -----------------------------------------------------------------

    protected function exito(string $mensaje, string $ruta): never {
        setFlashMessage($mensaje, 'success');
        $this->irA($ruta);
    }

    /** @param string|string[] $errores */
    protected function fallo(string|array $errores, string $ruta): never {
        foreach ((array)$errores as $e) {
            setFlashMessage($e, 'danger');
        }
        $this->irA($ruta);
    }

    /**
     * Si la validación acumuló errores, los muestra y vuelve al formulario.
     */
    protected function siHayErrores(Validador $v, string $ruta): void {
        if ($v->hayErrores()) {
            $this->fallo($v->errores(), $ruta);
        }
    }

    /**
     * Ejecuta la operación de negocio y responde con PRG.
     *
     * Un `ErrorDeNegocio` llega tal cual al usuario (está escrito para él);
     * cualquier otra excepción se registra y se resume con una referencia,
     * para no filtrar nombres de tabla ni rutas del servidor.
     *
     * @param callable():mixed $operacion
     * @param string|callable(mixed):string $mensaje Texto de éxito, o función
     *        que lo construye a partir del resultado de la operación.
     */
    protected function ejecutar(callable $operacion, string $ruta, string|callable $mensaje, string $contexto = ''): never {
        try {
            $resultado = $operacion();
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, $contexto), $ruta);
        }
        $this->exito(is_callable($mensaje) ? $mensaje($resultado) : $mensaje, $ruta);
    }

    /**
     * Ruta interna a la que volver tras una acción: la pantalla desde la
     * que se envió el formulario, si es de esta aplicación.
     *
     * No se usa `REQUEST_URI` a ciegas como destino ni el `Referer` sin
     * comprobar: los dos los controla el cliente, y un destino arbitrario
     * convierte la redirección en un salto a otro dominio.
     */
    protected function rutaDeVuelta(string $porDefecto): string {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $prefijo = APP_URL . '/index.php';
        if (str_starts_with($uri, $prefijo . '/') && preg_match('#^[A-Za-z0-9/_\-?=&%.]*$#', $uri)) {
            return substr($uri, strlen($prefijo));
        }
        return $porDefecto;
    }
}
