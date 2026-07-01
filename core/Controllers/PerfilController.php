<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Models\PerfilModel;
use PDO;
use Exception;

class PerfilController extends BaseController {
    private PDO $db;
    private PerfilModel $perfilModel;

    public function __construct(?PDO $db = null, ?PerfilModel $perfilModel = null) {
        $this->db = $db ?? Database::getConnection();
        $this->perfilModel = $perfilModel ?? new PerfilModel($this->db);
    }

    public function index(): void {
        requireAuth();

        $user_id = (int)getCurrentUser()['id'];
        $errors  = [];
        $success = '';

        $colores_validos = [
            '#39A900', '#2E7D32', '#1976D2', '#7B1FA2',
            '#F59E0B', '#E53935', '#00897B', '#5D4037',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            if (($_POST['action'] ?? '') === 'update_profile') {
                $nombre = trim($_POST['nombre'] ?? '');
                $color  = trim($_POST['avatar_color'] ?? '');

                if (mb_strlen($nombre, 'UTF-8') < 3) {
                    $errors[] = 'El nombre debe tener al menos 3 caracteres.';
                } elseif (mb_strlen($nombre, 'UTF-8') > 100) {
                    $errors[] = 'El nombre no puede exceder los 100 caracteres.';
                } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
                    $errors[] = 'El nombre solo puede contener letras y espacios.';
                }
                $nombre = strip_tags($nombre);
                if (!in_array($color, $colores_validos, true)) {
                    $errors[] = 'Color de avatar no válido.';
                }

                if (empty($errors)) {
                    try {
                        $this->perfilModel->updateProfile($user_id, $nombre, $color);

                        // Refrescar sesión de esta pestaña para que el cambio se vea de inmediato
                        $_SESSION['tabs'][getTabId()]['user_nombre']       = $nombre;
                        $_SESSION['tabs'][getTabId()]['user_avatar_color'] = $color;

                        $success = 'Datos personales actualizados correctamente.';
                    } catch (Exception $e) {
                        $errors[] = 'No se pudieron guardar los cambios.';
                    }
                }
            } elseif (($_POST['action'] ?? '') === 'change_password') {
                $actual    = $_POST['password_actual'] ?? '';
                $nueva     = $_POST['password_nueva'] ?? '';
                $confirmar = $_POST['password_confirmar'] ?? '';

                if (empty($actual)) {
                    $errors[] = 'Debes ingresar tu contraseña actual.';
                }
                if (strlen($nueva) < 8) {
                    $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
                }
                if (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/[0-9]/', $nueva)) {
                    $errors[] = 'La nueva contraseña debe contener letras y números.';
                }
                if ($nueva !== $confirmar) {
                    $errors[] = 'La confirmación no coincide con la nueva contraseña.';
                }
                if ($nueva === $actual && empty($errors)) {
                    $errors[] = 'La nueva contraseña no puede ser igual a la actual.';
                }

                if (empty($errors)) {
                    try {
                        if (!$this->perfilModel->verifyPassword($user_id, $actual)) {
                            $errors[] = 'La contraseña actual es incorrecta.';
                        } else {
                            $this->perfilModel->changePassword($user_id, $nueva);
                            $success = 'Contraseña actualizada correctamente.';
                        }
                    } catch (Exception $e) {
                        $errors[] = 'No se pudo cambiar la contraseña.';
                    }
                }
            }
        }

        $user = ['nombre' => '', 'email' => '', 'rol' => '', 'avatar_color' => '#39A900', 'fecha_creacion' => ''];
        try {
            $row = $this->perfilModel->getPerfil($user_id);
            if ($row) {
                $user = $row;
            }
        } catch (Exception $e) {
            $errors[] = 'Error al cargar tu perfil.';
        }

        $this->render(
            BASE_PATH . 'modules/perfil/views/index.view.php',
            [
                'errors' => $errors,
                'success' => $success,
                'user' => $user,
                'colores_validos' => $colores_validos
            ],
            'Mi Perfil · SENA'
        );
    }
}
