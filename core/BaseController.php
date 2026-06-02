<?php
declare(strict_types=1);

namespace Core;

class BaseController {
    /**
     * Renders a view inside the main layout
     */
    protected function render(string $viewPath, array $data = [], string $pageTitle = 'SENA'): void {
        // Extraer los datos para que estén disponibles como variables locales en la vista
        extract($data);

        // Definir la variable del contenido que requiere el layout
        $contentView = $viewPath;

        // Incluir el layout principal
        $app_included = true;
        require_once __DIR__ . '/../layouts/app.php';
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
