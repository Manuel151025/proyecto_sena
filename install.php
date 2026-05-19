<?php
/**
 * INSTALL.PHP - Script de instalación de la base de datos
 * Ejecutar en navegador: http://localhost/proyecto_sena/install.php
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);

    // Crear tablas
    $schema = <<<SQL
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(120) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    rol ENUM('coordinador', 'instructor', 'aprendiz') NOT NULL,
    avatar_color VARCHAR(7) DEFAULT '#39A900',
    estado ENUM('activo', 'inactivo', 'bloqueado') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rol (rol),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT,
    duracion_horas INT,
    estado ENUM('activo', 'inactivo', 'archivado') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fichas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_ficha VARCHAR(50) UNIQUE NOT NULL,
    programa_id INT NOT NULL,
    instructor_id INT NOT NULL,
    coordinador_id INT,
    estado ENUM('planeacion', 'induccion', 'ejecucion', 'cierre') DEFAULT 'planeacion',
    cantidad_aprendices INT DEFAULT 0,
    fecha_inicio DATE,
    fecha_fin DATE,
    cumplimiento_porcentaje DECIMAL(5, 2) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programa_id) REFERENCES programas(id),
    FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
    FOREIGN KEY (coordinador_id) REFERENCES usuarios(id),
    INDEX idx_estado (estado),
    INDEX idx_instructor (instructor_id),
    INDEX idx_programa (programa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aprendices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    ficha_id INT,
    numero_documento VARCHAR(50) UNIQUE NOT NULL,
    tipo_documento ENUM('CC', 'TI', 'CE', 'PEP', 'PA') DEFAULT 'CC',
    genero ENUM('M', 'F', 'O') DEFAULT 'O',
    fecha_nacimiento DATE,
    telefono VARCHAR(20),
    ciudad VARCHAR(100),
    estado ENUM('matriculado', 'suspendido', 'desertado', 'egresado') DEFAULT 'matriculado',
    fecha_matricula TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (ficha_id) REFERENCES fichas(id),
    INDEX idx_estado (estado),
    INDEX idx_documento (numero_documento),
    INDEX idx_ficha (ficha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    programa_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(100),
    descripcion TEXT,
    horas INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programa_id) REFERENCES programas(id),
    INDEX idx_programa (programa_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actividades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    competencia_id INT,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE,
    fecha_fin DATE,
    responsable_id INT,
    estado ENUM('pendiente', 'en_progreso', 'completada', 'cancelada') DEFAULT 'pendiente',
    cumplimiento_porcentaje DECIMAL(5, 2) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ficha_id) REFERENCES fichas(id),
    FOREIGN KEY (competencia_id) REFERENCES competencias(id),
    FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
    INDEX idx_ficha (ficha_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    actividad_id INT NOT NULL,
    aprendiz_id INT NOT NULL,
    instructor_id INT NOT NULL,
    concepto ENUM('aprobado', 'en_proceso', 'no_aplica') DEFAULT 'en_proceso',
    comentario TEXT,
    calificacion DECIMAL(5, 2),
    fecha_evaluacion DATE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id),
    FOREIGN KEY (aprendiz_id) REFERENCES aprendices(id),
    FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
    INDEX idx_actividad (actividad_id),
    INDEX idx_aprendiz (aprendiz_id),
    INDEX idx_concepto (concepto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evaluacion_id INT,
    aprendiz_id INT NOT NULL,
    ficha_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    archivo_url VARCHAR(500),
    tipo_archivo VARCHAR(50),
    tamaño_kb INT,
    estado ENUM('enviada', 'revisada', 'aprobada', 'rechazada') DEFAULT 'enviada',
    retroalimentacion TEXT,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_revision DATE,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id),
    FOREIGN KEY (aprendiz_id) REFERENCES aprendices(id),
    FOREIGN KEY (ficha_id) REFERENCES fichas(id),
    INDEX idx_aprendiz (aprendiz_id),
    INDEX idx_ficha (ficha_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fases_proyecto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    numero_fase INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE,
    fecha_fin DATE,
    cumplimiento_porcentaje DECIMAL(5, 2) DEFAULT 0,
    estado ENUM('planeada', 'en_ejecucion', 'completada') DEFAULT 'planeada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ficha_id) REFERENCES fichas(id),
    INDEX idx_ficha (ficha_id),
    INDEX idx_estado (estado),
    UNIQUE KEY unique_fase_ficha (ficha_id, numero_fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retroalimentacion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evaluacion_id INT,
    aprendiz_id INT NOT NULL,
    instructor_id INT NOT NULL,
    tipo ENUM('fortaleza', 'aspecto_mejorar', 'recomendacion') DEFAULT 'aspecto_mejorar',
    contenido TEXT NOT NULL,
    privada BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id),
    FOREIGN KEY (aprendiz_id) REFERENCES aprendices(id),
    FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
    INDEX idx_aprendiz (aprendiz_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    modulo VARCHAR(100),
    tabla_afectada VARCHAR(100),
    id_registro INT,
    descripcion TEXT,
    ip_address VARCHAR(45),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha),
    INDEX idx_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement . ';');
        }
    }

    echo "<h1>✅ Base de datos creada exitosamente</h1>";
    echo "<p>Ahora insertando datos de prueba...</p>";

    // Datos iniciales
    $pdo->exec("DELETE FROM usuarios");
    $pdo->exec("DELETE FROM programas");

    // Usuarios demo
    $stmt = $pdo->prepare("INSERT INTO usuarios (email, password, nombre, rol, avatar_color, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
    
    $users = [
        ['coordinador@sena.edu.co', 'Carlos Andrés Martínez', 'coordinador', '#39A900'],
        ['instructor@sena.edu.co', 'María Fernanda López', 'instructor', '#3B82F6'],
        ['instructor2@sena.edu.co', 'Jorge Salas', 'instructor', '#8B5CF6'],
        ['instructor3@sena.edu.co', 'Diana Cruz', 'instructor', '#EC4899'],
        ['aprendiz@sena.edu.co', 'Juan David Ramírez', 'aprendiz', '#F59E0B'],
    ];

    foreach ($users as [$email, $nombre, $rol, $color]) {
        $stmt->execute([
            $email,
            password_hash('admin123', PASSWORD_DEFAULT),
            $nombre,
            $rol,
            $color
        ]);
    }

    // Programas
    $stmt = $pdo->prepare("INSERT INTO programas (nombre, codigo, descripcion, duracion_horas, estado) VALUES (?, ?, ?, ?, 'activo')");
    $programs = [
        ['Análisis y Desarrollo de Software', 'ADSO', 'Programa de desarrollo de aplicaciones web y móviles', 2880],
        ['Multimedia', 'MM', 'Diseño gráfico y producción multimedia', 1440],
        ['Contabilidad', 'CONT', 'Gestión contable y financiera', 1920],
        ['Logística', 'LOG', 'Gestión de operaciones logísticas', 1200],
    ];

    foreach ($programs as [$nombre, $codigo, $desc, $horas]) {
        $stmt->execute([$nombre, $codigo, $desc, $horas]);
    }

    // Obtener IDs de programas e instructores para fichas
    $stmt = $pdo->prepare("SELECT id FROM programas WHERE codigo = ?");
    $stmt->execute(['ADSO']);
    $prog_adso = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    
    $stmt->execute(['MM']);
    $prog_mm = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    
    $stmt->execute(['CONT']);
    $prog_cont = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    
    $stmt->execute(['LOG']);
    $prog_log = $stmt->fetch(PDO::FETCH_ASSOC)['id'];

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rol = 'instructor' ORDER BY id");
    $stmt->execute();
    $instructors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fichas de prueba
    $stmt = $pdo->prepare("INSERT INTO fichas (numero_ficha, programa_id, instructor_id, coordinador_id, estado, cantidad_aprendices, fecha_inicio, fecha_fin, cumplimiento_porcentaje) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $fichas = [
        ['2845671', $prog_adso, $instructors[0] ?? 1, 1, 'ejecucion', 32, '2024-06-15', '2026-06-15', 65],
        ['2867812', $prog_mm, $instructors[1] ?? 2, 1, 'ejecucion', 28, '2024-07-15', '2026-07-30', 45],
        ['2901234', $prog_cont, $instructors[2] ?? 3, 1, 'induccion', 30, '2024-08-01', '2026-12-10', 20],
        ['2912345', $prog_adso, $instructors[0] ?? 1, 1, 'planeacion', 0, '2025-01-15', '2027-02-05', 0],
        ['2823456', $prog_log, $instructors[3] ?? 4, 1, 'cierre', 26, '2024-04-01', '2026-04-25', 100],
    ];

    foreach ($fichas as [$num_ficha, $prog_id, $inst_id, $coord_id, $estado, $aprendices, $f_inicio, $f_fin, $cumplimiento]) {
        $stmt->execute([$num_ficha, $prog_id, $inst_id, $coord_id, $estado, $aprendices, $f_inicio, $f_fin, $cumplimiento]);
    }

    echo "<p>✅ Esquema y datos creados correctamente</p>";
    echo "<p><a href='login.php'>Ir al login →</a></p>";
    echo "<p><strong>Credenciales demo:</strong><br>";
    echo "Coordinador: coordinador@sena.edu.co / admin123<br>";
    echo "Instructor: instructor@sena.edu.co / admin123<br>";
    echo "Aprendiz: aprendiz@sena.edu.co / admin123<br>";
    echo "</p>";

} catch (Exception $e) {
    echo "<h1>❌ Error en la instalación</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - SENA</title>
    <style>
        body {
            font-family: Inter, system-ui, sans-serif;
            padding: 2rem;
            background: linear-gradient(135deg, #39A900 0%, #2d8000 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        div {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            max-width: 500px;
        }
        h1 { color: #39A900; margin: 0 0 1rem; }
        p { margin: 0.5rem 0; line-height: 1.6; }
        a { color: #39A900; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div></div>
</body>
</html>
