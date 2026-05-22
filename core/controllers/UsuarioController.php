<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\Models\UsuarioModel;
use Core\XlsxParser;
use Exception;

require_once __DIR__ . '/../XlsxParser.php';

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

    /**
     * Orquesta la vista de crear usuario y maneja la petición POST de creación.
     */
    public function create(): void {
        global $app_included;

        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];
        $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre' => mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8'),
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'rol' => $_POST['rol'] ?? 'aprendiz',
                'avatar_color' => $_POST['avatar_color'] ?? '#39A900'
            ];

            // Validaciones
            if (empty($data['nombre'])) $errors[] = 'El nombre es requerido';
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
            if (strlen($data['password']) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';
            if (!in_array($data['rol'], ['coordinador', 'instructor', 'aprendiz'])) $errors[] = 'Rol inválido';

            if (empty($errors)) {
                try {
                    if ($this->usuarioModel->create($data)) {
                        $mensaje = 'Usuario creado correctamente';
                        $tipo_mensaje = 'success';
                        $_POST = []; // Limpiar formulario
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $pageTitle = 'Crear Usuario · SENA';
        $contentView = __DIR__ . '/../../modules/usuarios/views/crear.view.php';
        require_once __DIR__ . '/../../layouts/app.php';
    }

    /**
     * Orquesta la vista de editar usuario y maneja la petición POST de actualización.
     */
    public function edit(int $id): void {
        global $app_included;

        $mensaje = '';
        $tipo_mensaje = '';
        $errors = [];
        $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];

        // Cargar datos actuales
        $usuario = null;
        try {
            $usuario = $this->usuarioModel->findById($id);
            if (!$usuario) {
                // Podríamos redirigir o mostrar un error si el usuario no existe
                header('Location: ' . APP_URL . '/modules/usuarios/');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
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

            // Validaciones
            if (empty($data['nombre'])) $errors[] = 'El nombre es requerido';
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
            if (!empty($data['password']) && strlen($data['password']) < 6) {
                $errors[] = 'Si deseas cambiar la contraseña, debe tener al menos 6 caracteres';
            }
            if (!in_array($data['rol'], ['coordinador', 'instructor', 'aprendiz'])) $errors[] = 'Rol inválido';
            if (!in_array($data['estado'], ['activo', 'inactivo', 'bloqueado'])) $errors[] = 'Estado inválido';

            if (empty($errors)) {
                try {
                    if ($this->usuarioModel->update($id, $data)) {
                        $mensaje = 'Usuario actualizado correctamente';
                        $tipo_mensaje = 'success';
                        // Recargar el usuario con los datos actualizados para mostrar en la vista
                        $usuario = $this->usuarioModel->findById($id);
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $pageTitle = 'Editar Usuario · SENA';
        $contentView = __DIR__ . '/../../modules/usuarios/views/editar.view.php';
        require_once __DIR__ . '/../../layouts/app.php';
    }

    /**
     * Orquesta la vista de importación masiva y procesa el archivo CSV/XLSX.
     */
    public function import(): void {
        global $app_included;

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
                            // Detectar delimitador si es punto y coma o coma
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
                            // Ignorar la cabecera
                            array_shift($rows);
                            
                            $linea = 2; // Empezamos en la línea 2
                            $usersData = [];
                            $colors = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444'];
                            
                            foreach ($rows as $data) {
                                // Omitir filas vacías
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
                                    // Contraseña genérica por defecto acordada: Sena2026
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

        $pageTitle = 'Importar Usuarios · SENA';
        $contentView = __DIR__ . '/../../modules/usuarios/views/importar.view.php';
        require_once __DIR__ . '/../../layouts/app.php';
    }
}
