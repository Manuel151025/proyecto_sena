<?php
declare(strict_types=1);

namespace Core;

class BaseController {
    /**
     * Renders a view inside the main layout
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
        \Core\Support\Seguridad::sinCache();

        // `extract` no puede sobrescribir las variables de este método: si
        // una vista recibe un dato llamado 'viewPath' o 'contentView', el
        // layout cargaría otra cosa.
        extract($data, EXTR_SKIP);

        // Definir la variable del contenido que requiere el layout
        $contentView = $viewPath;

        // `require_once` impedía renderizar dos vistas en una misma
        // petición (por ejemplo, una parcial dentro de otra).
        $app_included = true;
        require __DIR__ . '/../layouts/app.php';
    }

    /**
     * Redirects to a given URL
     */
    protected function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Returns JSON response
     */
    protected function json(mixed $data): void {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
