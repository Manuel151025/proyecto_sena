<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\Models\UsuarioModel;
use Exception;

class UsuarioController {
    private UsuarioModel $usuarioModel;

    public function __construct() {
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Orquesta la vista principal de listar usuarios y maneja acciones simples como eliminar.
     */
    public function index(): void {
        global $app_included;

        $mensaje = '';
        $tipo_mensaje = '';

        // Procesar acción de eliminar
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
            try {
                $id = (int) $_POST['id'];
                if ($this->usuarioModel->delete($id)) {
                    $mensaje = 'Usuario eliminado correctamente';
                    $tipo_mensaje = 'success';
                }
            } catch (Exception $e) {
                $mensaje = 'Error al eliminar usuario';
                $tipo_mensaje = 'danger';
            }
        }

        // Obtener la lista de usuarios
        try {
            $usuarios = $this->usuarioModel->getAll();
        } catch (Exception $e) {
            $usuarios = [];
            $mensaje = 'Error al cargar usuarios';
            $tipo_mensaje = 'danger';
        }

        // Definiciones para la vista
        $roles_label = [
            'coordinador' => 'Coordinador',
            'instructor' => 'Instructor',
            'aprendiz' => 'Aprendiz'
        ];

        $estados_label = [
            'activo' => ['Activo', 'success'],
            'inactivo' => ['Inactivo', 'warning'],
            'bloqueado' => ['Bloqueado', 'danger']
        ];

        // Preparar las variables requeridas por el layout global
        $pageTitle = 'Usuarios · SENA';
        // Ajustar ruta relativa en base al entry point actual (modules/usuarios/index.php)
        $contentView = __DIR__ . '/../../modules/usuarios/views/index.view.php';
        
        // Incluir el layout global. Al ser incluido dentro de este método,
        // tendrá acceso a $usuarios, $roles_label, $estados_label, $mensaje, etc.
        require_once __DIR__ . '/../../layouts/app.php';
    }
}
