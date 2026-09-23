<?php
/**
 * API_NOTIFICACIONES.PHP — Endpoint AJAX para notificaciones
 *
 * GET  → devuelve JSON con notificaciones no leídas y conteo
 * POST action=marcar_leida&id=X → marca una notificación como leída
 * POST action=marcar_todas → marca todas como leídas
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Requiere autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$userId = (int) getCurrentUser()['id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Devolver notificaciones no leídas
        $notificaciones = getNotificacionesNoLeidas($userId);
        $count = contarNotificacionesNoLeidas($userId);

        // Agregar tiempo relativo a cada notificación
        foreach ($notificaciones as &$n) {
            $n['tiempo_relativo'] = timeAgo($n['fecha_creacion']);
        }
        unset($n);

        echo json_encode([
            'ok'              => true,
            'count'           => $count,
            'notificaciones'  => $notificaciones,
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar token CSRF para solicitudes POST
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido o ausente']);
            exit;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'marcar_leida') {
            $bruto = $_POST['id'] ?? '';
            $id = is_array($bruto) ? 0 : (int)filter_var(trim((string)$bruto), FILTER_VALIDATE_INT);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID inválido']);
                exit;
            }
            // La notificación tiene que ser de quien la marca.
            if (!marcarNotificacionLeida($id, $userId)) {
                // Mismo código y mensaje tanto si no existe como si es de
                // otro usuario: distinguirlos permitiría sondear qué ids
                // están ocupados.
                http_response_code(404);
                echo json_encode(['error' => 'Notificación no encontrada']);
                exit;
            }
            echo json_encode(['ok' => true]);

        } elseif ($action === 'marcar_todas') {
            marcarTodasLeidas($userId);
            echo json_encode(['ok' => true]);

        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
