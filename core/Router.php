<?php
declare(strict_types=1);

namespace Core;

class Router {
    private array $routes = [];

    public function add(string $method, string $path, string $controller, string $action): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '/' . trim($path, '/'),
            'controller' => $controller,
            'action' => $action
        ];
    }

    public function dispatch(string $method, string $uri): void {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
        
        // Ajustar para carpetas base y script name (ej: /proyecto_sena/index.php/matriculas -> /matriculas)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
        } else {
            $baseDir = str_replace('\\', '/', dirname($scriptName));
            if ($baseDir !== '/' && !empty($baseDir) && strpos($path, $baseDir) === 0) {
                $path = substr($path, strlen($baseDir));
            }
        }
        $path = '/' . trim($path, '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                $controllerClass = $route['controller'];
                $actionName = $route['action'];

                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                    if (method_exists($controller, $actionName)) {
                        $controller->$actionName();
                        return;
                    }
                }
            }
        }

        // Si no coincide ninguna ruta, responder con 404
        http_response_code(404);
        echo "404 - Página no encontrada en el enrutador";
    }
}
