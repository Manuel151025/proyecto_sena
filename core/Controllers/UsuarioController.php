<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Interfaces\UsuarioRepositoryInterface;
use Core\Models\UsuarioModel;
use Core\XlsxParser;
use Exception;

class UsuarioController extends BaseController {
    private UsuarioRepositoryInterface $usuarioModel;

    public function __construct(?UsuarioRepositoryInterface $usuarioModel = null) {
        parent::__construct();
        // Exigir sesión y rol de coordinador para todo este controlador
        requireRole(ROL_COORDINADOR);
        $this->usuarioModel = $usuarioModel ?? new UsuarioModel();
    }

    /**
     * Valida los datos de un usuario. (Cumple SRP: Single Responsibility Principle)
     */
    private function validateUser(array $data, bool $isEdit): array {
        $errors = [];
        if (empty($data['nombre'])) {
            $errors[] = 'El nombre es requerido';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        if ($isEdit) {
            if (!empty($data['password']) && strlen($data['password']) < 6) {
                $errors[] = 'Si deseas cambiar la contraseña, debe tener al menos 6 caracteres';
            }
            if (!in_array($data['estado'], ['activo', 'inactivo', 'bloqueado'])) {
                $errors[] = 'Estado inválido';
            }
        } else {
            if (strlen($data['password']) < 6) {
                $errors[] = 'La contraseña debe tener al menos 6 caracteres';
            }
        }

        if (!in_array($data['rol'], ['coordinador', 'instructor', 'aprendiz'])) {
            $errors[] = 'Rol inválido';
        }

        return $errors;
    }

    /**
     * Orquesta la vista principal de listar usuarios y maneja acciones simples como eliminar.
     */
    public function index(): void {
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

        $this->render(
            BASE_PATH . 'modules/usuarios/views/index.view.php',
            [
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'usuarios' => $usuarios,
                'roles_label' => $roles_label,
                'estados_label' => $estados_label
            ],
            'Usuarios · SENA'
        );
    }

    /**
     * Orquesta la vista de crear usuario y maneja la petición POST de creación.
     */
    public function create(): void {
        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];
        $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre' => mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8'),
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'rol' => $_POST['rol'] ?? 'aprendiz',
                'avatar_color' => $_POST['avatar_color'] ?? '#39A900'
            ];

            // Validar usando el método extraído
            $errors = $this->validateUser($data, false);

            if (empty($errors)) {
                try {
                    if ($this->usuarioModel->create($data)) {
                        if ($isAjax) {
                            $this->json(['status' => 'success', 'message' => 'Usuario creado correctamente']);
                        }
                        $mensaje = 'Usuario creado correctamente';
                        $tipo_mensaje = 'success';
                        $_POST = []; // Limpiar formulario
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if ($isAjax && !empty($errors)) {
                $this->json(['status' => 'error', 'errors' => $errors]);
            }
        }

        $this->render(
            BASE_PATH . 'modules/usuarios/views/crear.view.php',
            [
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'errors' => $errors,
                'colors' => $colors
            ],
            'Crear Usuario · SENA'
        );
    }

    /**
     * Orquesta la vista de editar usuario y maneja la petición POST de actualización.
     */
    public function edit(?int $id = null): void {
        if ($id === null) {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        }

        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];
        $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Cargar datos actuales
        $usuario = null;
        try {
            $usuario = $this->usuarioModel->findById($id);
            if (!$usuario) {
                if ($isAjax) {
                    $this->json(['status' => 'error', 'message' => 'Usuario no encontrado']);
                }
                $this->redirect(APP_URL . '/index.php/usuarios');
            }
        } catch (Exception $e) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
            $errors[] = $e->getMessage();
        }

        // Si es una petición GET por AJAX, devolver los datos del usuario en JSON
        if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->json(['status' => 'success', 'data' => $usuario]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
            $data = [
                'nombre' => mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8'),
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '', // opcional al editar
                'rol' => $_POST['rol'] ?? 'aprendiz',
                'estado' => $_POST['estado'] ?? 'activo',
                'avatar_color' => $_POST['avatar_color'] ?? '#39A900'
            ];

            // Validar usando el método extraído
            $errors = $this->validateUser($data, true);

            if (empty($errors)) {
                try {
                    if ($this->usuarioModel->update($id, $data)) {
                        if ($isAjax) {
                            $this->json(['status' => 'success', 'message' => 'Usuario actualizado correctamente']);
                        }
                        $mensaje = 'Usuario actualizado correctamente';
                        $tipo_mensaje = 'success';
                        // Recargar el usuario con los datos actualizados para mostrar en la vista
                        $usuario = $this->usuarioModel->findById($id);
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if ($isAjax && !empty($errors)) {
                $this->json(['status' => 'error', 'errors' => $errors]);
            }
        }

        $this->render(
            BASE_PATH . 'modules/usuarios/views/editar.view.php',
            [
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'errors' => $errors,
                'colors' => $colors,
                'usuario' => $usuario
            ],
            'Editar Usuario · SENA'
        );
    }

    /**
     * Orquesta la vista de importación masiva y procesa el archivo CSV/XLSX.
     */
    public function import(): void {
        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];
        $resultados = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['archivo_csv']['tmp_name'];
                $fileName = $_FILES['archivo_csv']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExtension === 'xls') {
                    $errors[] = 'El formato .xls (Excel antiguo) no está soportado. Por favor, guarde su archivo como .xlsx o expórtelo como .csv.';
                } elseif (!in_array($fileExtension, ['csv', 'xlsx'])) {
                    $errors[] = 'El archivo debe ser un archivo de Excel (.xlsx) o un archivo de texto separado por comas (.csv).';
                } else {
                    $rows = [];
                    if ($fileExtension === 'csv') {
                        $handle = fopen($fileTmpPath, 'r');
                        if ($handle !== false) {
                            $firstLine = fgets($handle);
                            $separator = (strpos($firstLine, ';') !== false) ? ';' : ',';
                            rewind($handle);

                            while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
                                $rows[] = $data;
                            }
                            fclose($handle);
                        } else {
                            $errors[] = 'No se pudo abrir el archivo CSV.';
                        }
                    } else { // xlsx
                        try {
                            $rows = XlsxParser::parse($fileTmpPath);
                        } catch (Exception $e) {
                            $errors[] = 'Error al procesar el archivo Excel: ' . $e->getMessage();
                        }
                    }

                    if (empty($errors)) {
                        if (count($rows) <= 1) {
                            $errors[] = 'El archivo está vacío o solo contiene la cabecera.';
                        } else {
                            array_shift($rows);
                            
                            $linea = 2;
                            $usersData = [];
                            $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                            
                            foreach ($rows as $data) {
                                if (empty($data) || (empty($data[0]) && empty($data[1]) && empty($data[2]))) {
                                    $linea++;
                                    continue;
                                }

                                if (count($data) < 3) {
                                    $errors[] = "Línea $linea: Faltan columnas. Se requiere Nombre, Email, y Rol.";
                                    $linea++;
                                    continue;
                                }
                                
                                $nombre = mb_strtoupper(trim((string)($data[0] ?? '')), 'UTF-8');
                                $email = trim((string)($data[1] ?? ''));
                                $rol = strtolower(trim((string)($data[2] ?? '')));

                                $rowErrors = [];
                                if (empty($nombre)) {
                                    $rowErrors[] = "Línea $linea: El nombre está vacío.";
                                }
                                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $rowErrors[] = "Línea $linea: Email '$email' inválido.";
                                }
                                if (!in_array($rol, ['coordinador', 'instructor', 'aprendiz'])) {
                                    $rowErrors[] = "Línea $linea: Rol '$rol' inválido. Debe ser coordinador, instructor o aprendiz.";
                                }

                                if (empty($rowErrors)) {
                                    $usersData[] = [
                                        'nombre' => $nombre,
                                        'email' => $email,
                                        'password' => 'Sena2026',
                                        'rol' => $rol,
                                        'avatar_color' => $colors[array_rand($colors)]
                                    ];
                                } else {
                                    $errors = array_merge($errors, $rowErrors);
                                }
                                $linea++;
                            }

                            if (empty($errors)) {
                                if (count($usersData) > 0) {
                                    try {
                                        $count = $this->usuarioModel->createMultiple($usersData);
                                        $mensaje = "Se han importado exitosamente $count usuarios.";
                                        $tipo_mensaje = 'success';
                                        $resultados = $usersData;
                                    } catch (Exception $e) {
                                        $errors[] = $e->getMessage();
                                    }
                                } else {
                                    $errors[] = 'El archivo no contiene datos válidos.';
                                }
                            }
                        }
                    }
                }
            } else {
                $errors[] = 'No se ha subido ningún archivo o hubo un error en la subida.';
            }
        }

        $this->render(
            BASE_PATH . 'modules/usuarios/views/importar.view.php',
            [
                'mensaje' => $mensaje,
                'tipo_mensaje' => $tipo_mensaje,
                'errors' => $errors,
                'resultados' => $resultados
            ],
            'Importar Usuarios · SENA'
        );
    }
}
