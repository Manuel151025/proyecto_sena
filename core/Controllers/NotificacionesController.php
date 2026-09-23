<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\NotificacionesModel;
use Core\Services\Notificador;
use Throwable;

/**
 * API JSON de la campana de notificaciones.
 *
 * Antes era `includes/api_notificaciones.php`, un script al que el
 * navegador llamaba directamente. Pasar por el enrutador tiene dos
 * efectos: la carpeta `includes/` puede cerrarse por completo al acceso
 * web, y el permiso queda declarado en la tabla de rutas como el de
 * cualquier otra pantalla.
 *
 *   GET  /api/notificaciones                      → no leídas + total
 *   POST /api/notificaciones  action=marcar_leida → una (solo si es tuya)
 *   POST /api/notificaciones  action=marcar_todas → todas las tuyas
 */
class NotificacionesController extends BaseController {
    private NotificacionesModel $modelo;

    public function __construct(?NotificacionesModel $modelo = null) {
        $this->modelo = $modelo ?? new NotificacionesModel();
    }

    public function listar(): void {
        $uid = $this->usuarioId();
        try {
            $items = array_map(static function (array $n): array {
                return [
                    'id'              => (int)$n['id'],
                    'titulo'          => (string)$n['titulo'],
                    'mensaje'         => (string)$n['mensaje'],
                    'tipo'            => in_array($n['tipo'], Notificador::TIPOS, true) ? $n['tipo'] : 'info',
                    // Se revalida al salir: una fila antigua o escrita a
                    // mano no debe llegar al href sin comprobar.
                    'url'             => Notificador::urlSegura($n['url'] ?? null),
                    'tiempo_relativo' => timeAgo((string)$n['fecha_creacion']),
                ];
            }, $this->modelo->noLeidas($uid));

            $this->json([
                'ok'             => true,
                'count'          => $this->modelo->contarNoLeidas($uid),
                'notificaciones' => $items,
            ]);
        } catch (Throwable $e) {
            error_log('API notificaciones: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar las notificaciones.'], 500);
        }
    }

    public function marcar(): void {
        $uid = $this->usuarioId();
        $accion = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

        if ($accion === 'marcar_todas') {
            $this->modelo->marcarTodas($uid);
            $this->json(['ok' => true]);
        }

        if ($accion === 'marcar_leida') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $this->json(['ok' => false, 'error' => 'Identificador no válido.'], 400);
            }
            // Mismo código tanto si no existe como si es de otro usuario:
            // distinguirlos permitiría sondear qué ids están ocupados.
            if (!$this->modelo->marcarLeida($id, $uid)) {
                $this->json(['ok' => false, 'error' => 'Notificación no encontrada.'], 404);
            }
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Acción no válida.'], 400);
    }
}
