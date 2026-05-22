<?php
/**
 * NOTIFICACIONES.PHP — Funciones de gestión de notificaciones
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

use Core\Database;

/**
 * Obtener notificaciones no leídas de un usuario
 */
function getNotificacionesNoLeidas(int $userId): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        "SELECT id, titulo, mensaje, tipo, url, fecha_creacion
         FROM notificaciones
         WHERE usuario_id = ? AND leida = 0
         ORDER BY fecha_creacion DESC
         LIMIT 20"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Contar notificaciones no leídas de un usuario
 */
function contarNotificacionesNoLeidas(int $userId): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Marcar una notificación como leída
 */
function marcarNotificacionLeida(int $id): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ?");
    $stmt->execute([$id]);
}

/**
 * Marcar todas las notificaciones de un usuario como leídas
 */
function marcarTodasLeidas(int $userId): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
    $stmt->execute([$userId]);
}
