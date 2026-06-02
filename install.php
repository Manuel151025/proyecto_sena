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
    $pdo->exec("DROP DATABASE IF EXISTS " . DB_NAME);
    $pdo->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    // ==========================================
    // ESQUEMA DE TABLAS
    // ==========================================
    $schema = <<<SQL
CREATE TABLE usuarios (
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

CREATE TABLE programas (
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

CREATE TABLE competencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    programa_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(100),
    descripcion TEXT,
    horas INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programa_id) REFERENCES programas(id) ON DELETE CASCADE,
    INDEX idx_programa (programa_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resultados_aprendizaje (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competencia_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    denominacion TEXT NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    INDEX idx_competencia (competencia_id),
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proyectos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    objetivo TEXT,
    descripcion TEXT,
    estado ENUM('activo', 'inactivo', 'finalizado') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fichas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_ficha VARCHAR(50) UNIQUE NOT NULL,
    programa_id INT NOT NULL,
    proyecto_id INT,
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
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
    FOREIGN KEY (coordinador_id) REFERENCES usuarios(id),
    INDEX idx_estado (estado),
    INDEX idx_instructor (instructor_id),
    INDEX idx_programa (programa_id),
    INDEX idx_proyecto (proyecto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asignaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    competencia_id INT NOT NULL,
    instructor_id INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ficha_competencia (ficha_id, competencia_id),
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_ficha (ficha_id),
    INDEX idx_competencia (competencia_id),
    INDEX idx_instructor (instructor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aprendices (
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

CREATE TABLE actividades (
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

CREATE TABLE evaluaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resultado_aprendizaje_id INT NOT NULL,
    aprendiz_id INT NOT NULL,
    instructor_id INT NOT NULL,
    ficha_id INT NOT NULL,
    concepto ENUM('A', 'D', 'pendiente') DEFAULT 'pendiente',
    comentario TEXT,
    fecha_evaluacion DATE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resultado_aprendizaje_id) REFERENCES resultados_aprendizaje(id) ON DELETE CASCADE,
    FOREIGN KEY (aprendiz_id) REFERENCES aprendices(id),
    FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
    FOREIGN KEY (ficha_id) REFERENCES fichas(id),
    INDEX idx_ra (resultado_aprendizaje_id),
    INDEX idx_aprendiz (aprendiz_id),
    INDEX idx_concepto (concepto),
    INDEX idx_ficha (ficha_id),
    UNIQUE KEY unique_eval (resultado_aprendizaje_id, aprendiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_evaluaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evaluacion_id INT NOT NULL,
    usuario_id INT NOT NULL,
    concepto_anterior ENUM('A', 'D', 'pendiente') NOT NULL,
    concepto_nuevo ENUM('A', 'D', 'pendiente') NOT NULL,
    motivo TEXT,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_evaluacion (evaluacion_id),
    INDEX idx_fecha (fecha_cambio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evidencias (
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

CREATE TABLE fases_proyecto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proyecto_id INT NOT NULL,
    numero_fase INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE,
    fecha_fin DATE,
    cumplimiento_porcentaje DECIMAL(5, 2) DEFAULT 0,
    estado ENUM('planeada', 'en_ejecucion', 'completada') DEFAULT 'planeada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    INDEX idx_proyecto (proyecto_id),
    INDEX idx_estado (estado),
    UNIQUE KEY unique_fase_proyecto (proyecto_id, numero_fase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE retroalimentacion (
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

CREATE TABLE notificaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT 'info',
    url VARCHAR(255),
    leida TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_leida (leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_sistema (
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

CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expira_en DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    ip_solicitud VARCHAR(45),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_expira (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement . ';');
        }
    }

    echo "<h1>✅ Esquema de base de datos creado</h1>";
    echo "<p>Insertando datos de prueba...</p>";

    // ==========================================
    // DATOS DE PRUEBA
    // ==========================================

    // --- USUARIOS ---
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO usuarios (email, password, nombre, rol, avatar_color, estado) VALUES (?, ?, ?, ?, ?, 'activo')");

    $users = [
        ['coordinador@sena.edu.co', 'Carlos Andrés Martínez', 'coordinador', '#39A900'],
        ['instructor@sena.edu.co', 'María Fernanda López', 'instructor', '#3B82F6'],
        ['instructor2@sena.edu.co', 'Jorge Salas', 'instructor', '#8B5CF6'],
        ['instructor3@sena.edu.co', 'Diana Cruz', 'instructor', '#EC4899'],
        ['instructor4@sena.edu.co', 'Roberto Gómez', 'instructor', '#10B981'],
        ['instructor5@sena.edu.co', 'Ana Torres', 'instructor', '#F43F5E'],
        ['aprendiz@sena.edu.co', 'Juan David Ramírez', 'aprendiz', '#F59E0B'],
        ['aprendiz2@sena.edu.co', 'Laura Camila Vargas', 'aprendiz', '#06B6D4'],
        ['aprendiz3@sena.edu.co', 'Pedro Nel Patiño', 'aprendiz', '#84CC16'],
        ['aprendiz4@sena.edu.co', 'Sofía Vergara', 'aprendiz', '#D946EF'],
        ['aprendiz5@sena.edu.co', 'Andrés Felipe Mendieta', 'aprendiz', '#EAB308'],
    ];

    foreach ($users as [$email, $nombre, $rol, $color]) {
        $stmtUser->execute([$email, $password_hash, $nombre, $rol, $color]);
    }

    // --- PROGRAMAS ---
    $stmtProg = $pdo->prepare("INSERT INTO programas (nombre, codigo, descripcion, duracion_horas, estado) VALUES (?, ?, ?, ?, 'activo')");
    $programs = [
        ['Análisis y Desarrollo de Software', 'ADSO', 'Programa de desarrollo de aplicaciones web y móviles', 2880],
        ['Multimedia', 'MM', 'Diseño gráfico y producción multimedia', 1440],
        ['Contabilidad', 'CONT', 'Gestión contable y financiera', 1920],
        ['Logística', 'LOG', 'Gestión de operaciones logísticas', 1200],
    ];
    foreach ($programs as [$nombre, $codigo, $desc, $horas]) {
        $stmtProg->execute([$nombre, $codigo, $desc, $horas]);
    }

    // Obtener IDs de programas
    $progIds = [];
    foreach (['ADSO', 'MM', 'CONT', 'LOG'] as $code) {
        $s = $pdo->prepare("SELECT id FROM programas WHERE codigo = ?");
        $s->execute([$code]);
        $progIds[$code] = (int)$s->fetchColumn();
    }

    // --- COMPETENCIAS ---
    $stmtComp = $pdo->prepare("INSERT INTO competencias (programa_id, nombre, codigo, descripcion, horas) VALUES (?, ?, ?, ?, ?)");
    $competencias = [
        // ADSO
        [$progIds['ADSO'], 'Analizar los requisitos del cliente para construir el sistema de información', 'C220501001', 'Análisis de requisitos de software', 480],
        [$progIds['ADSO'], 'Diseñar el sistema de información que cumpla con los requisitos de la solución informática', 'C220501002', 'Diseño de sistemas de información', 480],
        [$progIds['ADSO'], 'Construir el sistema que cumpla con los requisitos de la solución informática', 'C220501003', 'Desarrollo y codificación de software', 720],
        [$progIds['ADSO'], 'Implementar la solución que cumpla con los requisitos para su operación', 'C220501004', 'Implantación y puesta en marcha', 360],
        [$progIds['ADSO'], 'Participar en el proceso de negociación de tecnología informática', 'C220501005', 'Negociación tecnológica', 240],
        // MM
        [$progIds['MM'], 'Producir textos en inglés en forma escrita y oral', 'C240201500', 'Inglés técnico', 180],
        [$progIds['MM'], 'Diseñar la solución multimedial de acuerdo con el informe de análisis', 'C220501006', 'Diseño multimedia', 420],
        [$progIds['MM'], 'Construir la solución multimedial según especificaciones', 'C220501007', 'Producción multimedia', 420],
        // CONT
        [$progIds['CONT'], 'Contabilizar operaciones de acuerdo con las normas vigentes', 'C210301001', 'Contabilización', 480],
        [$progIds['CONT'], 'Analizar los resultados contables según los criterios de evaluación', 'C210301002', 'Análisis contable', 480],
        [$progIds['CONT'], 'Preparar y presentar la información contable y financiera', 'C210301003', 'Información financiera', 480],
        // LOG
        [$progIds['LOG'], 'Coordinar los procesos logísticos según normativa vigente', 'C260101001', 'Coordinación logística', 300],
        [$progIds['LOG'], 'Organizar los objetos en la unidad de almacenamiento', 'C260101002', 'Gestión de almacenamiento', 300],
        [$progIds['LOG'], 'Controlar las entradas y salidas de los objetos de la unidad de almacenamiento', 'C260101003', 'Control de inventarios', 300],
    ];
    foreach ($competencias as $c) {
        $stmtComp->execute($c);
    }

    // --- RESULTADOS DE APRENDIZAJE (RA) ---
    $stmtRA = $pdo->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)");
    
    // Obtener todas las competencias
    $allComps = $pdo->query("SELECT id, codigo FROM competencias ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    // Definir 3-4 RAs por competencia
    $raTemplates = [
        // ADSO
        'C220501001' => [
            ['RA-01-01', 'Interpretar el informe de requerimientos del cliente, estableciendo el alcance del proyecto'],
            ['RA-01-02', 'Representar el bosquejo de la solución al problema presentado por el cliente, mediante mapas navegacionales'],
            ['RA-01-03', 'Identificar las necesidades del sistema de información aplicando técnicas de recolección de datos'],
        ],
        'C220501002' => [
            ['RA-02-01', 'Elaborar el informe de los resultados del análisis del sistema de información'],
            ['RA-02-02', 'Diseñar las bases de datos y el modelo entidad-relación según requerimientos'],
            ['RA-02-03', 'Construir el prototipo del sistema de información a desarrollar'],
            ['RA-02-04', 'Elaborar el documento de arquitectura del software del proyecto'],
        ],
        'C220501003' => [
            ['RA-03-01', 'Aplicar buenas prácticas de calidad en el proceso de desarrollo de software'],
            ['RA-03-02', 'Codificar módulos del software de acuerdo con el diseño establecido'],
            ['RA-03-03', 'Realizar pruebas de software según plan de pruebas definido'],
            ['RA-03-04', 'Construir la interfaz de usuario de acuerdo con los lineamientos de diseño'],
        ],
        'C220501004' => [
            ['RA-04-01', 'Preparar el ambiente de producción para la implantación del sistema'],
            ['RA-04-02', 'Documentar manuales de usuario y técnicos del sistema de información'],
            ['RA-04-03', 'Capacitar a los usuarios finales sobre el uso del sistema implementado'],
        ],
        'C220501005' => [
            ['RA-05-01', 'Participar en procesos de evaluación de proveedores de tecnología'],
            ['RA-05-02', 'Elaborar propuestas técnicas para la adquisición de tecnología'],
        ],
        // MM
        'C240201500' => [
            ['RA-06-01', 'Leer textos técnicos en inglés comprendiendo la información relevante'],
            ['RA-06-02', 'Comunicarse en tareas sencillas y habituales en inglés'],
        ],
        'C220501006' => [
            ['RA-07-01', 'Diseñar las piezas multimediales conforme al guion técnico establecido'],
            ['RA-07-02', 'Elaborar storyboards y guiones para la producción multimedial'],
            ['RA-07-03', 'Diseñar interfaces gráficas de usuario para productos multimediales'],
        ],
        'C220501007' => [
            ['RA-08-01', 'Producir animaciones digitales según especificaciones de diseño'],
            ['RA-08-02', 'Editar y postproducir material audiovisual para productos multimediales'],
            ['RA-08-03', 'Integrar los elementos multimediales en una solución funcional'],
        ],
        // CONT
        'C210301001' => [
            ['RA-09-01', 'Registrar los hechos económicos de acuerdo con las normas contables'],
            ['RA-09-02', 'Elaborar los comprobantes de contabilidad según normativa vigente'],
            ['RA-09-03', 'Clasificar los documentos contables de soporte según su naturaleza'],
        ],
        'C210301002' => [
            ['RA-10-01', 'Interpretar los estados financieros de acuerdo con las normas internacionales'],
            ['RA-10-02', 'Calcular indicadores financieros para la toma de decisiones'],
            ['RA-10-03', 'Elaborar informes de análisis contable con recomendaciones'],
        ],
        'C210301003' => [
            ['RA-11-01', 'Presentar la información contable ante entidades de control según normativa'],
            ['RA-11-02', 'Preparar declaraciones tributarias según legislación colombiana'],
        ],
        // LOG
        'C260101001' => [
            ['RA-12-01', 'Planear los procesos de la cadena logística según normativa'],
            ['RA-12-02', 'Coordinar el transporte según tipo de producto y normativa'],
            ['RA-12-03', 'Aplicar normas de seguridad en operaciones logísticas'],
        ],
        'C260101002' => [
            ['RA-13-01', 'Organizar productos en el almacén según sus características'],
            ['RA-13-02', 'Controlar condiciones de almacenamiento según tipo de producto'],
        ],
        'C260101003' => [
            ['RA-14-01', 'Verificar las entradas y salidas de inventario según documentos'],
            ['RA-14-02', 'Realizar inventarios físicos y ajustes según procedimientos'],
            ['RA-14-03', 'Generar reportes de movimientos de inventario'],
        ],
    ];

    foreach ($allComps as $comp) {
        $code = $comp['codigo'];
        if (isset($raTemplates[$code])) {
            foreach ($raTemplates[$code] as [$raCodigo, $denominacion]) {
                $stmtRA->execute([(int)$comp['id'], $raCodigo, $denominacion]);
            }
        }
    }

    // --- PROYECTOS FORMATIVOS ---
    $stmtProy = $pdo->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, descripcion) VALUES (?, ?, ?, ?)");
    $proyectos = [
        ['Sistema de Gestión de Inventarios Web', 'PF-ADSO-01', 'Desarrollar un sistema de información web que permita gestionar el inventario de una empresa mediana', 'Proyecto integrador que abarca análisis, diseño, desarrollo, pruebas e implantación de un sistema web'],
        ['Plataforma Multimedia Educativa', 'PF-MM-01', 'Crear una plataforma de contenidos multimediales educativos interactivos', 'Diseño y producción de contenidos multimediales para educación virtual'],
        ['Sistema Contable para Microempresas', 'PF-CONT-01', 'Implementar un sistema de contabilización para microempresas colombianas', 'Registro, procesamiento y presentación de información contable y tributaria'],
        ['Plan Logístico de Distribución Regional', 'PF-LOG-01', 'Diseñar un plan logístico integral de distribución para una empresa regional', 'Coordinación de procesos logísticos, almacenamiento y control de inventarios'],
    ];
    foreach ($proyectos as $p) {
        $stmtProy->execute($p);
    }

    // Obtener IDs de proyectos
    $proyIds = [];
    foreach (['PF-ADSO-01', 'PF-MM-01', 'PF-CONT-01', 'PF-LOG-01'] as $code) {
        $s = $pdo->prepare("SELECT id FROM proyectos WHERE codigo = ?");
        $s->execute([$code]);
        $proyIds[$code] = (int)$s->fetchColumn();
    }

    // Obtener IDs de instructores
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rol = 'instructor' ORDER BY id");
    $stmt->execute();
    $instructors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // --- FICHAS (con proyecto_id) ---
    $stmtFicha = $pdo->prepare("INSERT INTO fichas (numero_ficha, programa_id, proyecto_id, instructor_id, coordinador_id, estado, cantidad_aprendices, fecha_inicio, fecha_fin, cumplimiento_porcentaje) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $fichas = [
        ['2845671', $progIds['ADSO'],  $proyIds['PF-ADSO-01'], $instructors[0], 1, 'ejecucion',  32, '2024-06-15', '2026-06-15', 65],
        ['2867812', $progIds['MM'],    $proyIds['PF-MM-01'],   $instructors[1], 1, 'ejecucion',  28, '2024-07-15', '2026-07-30', 45],
        ['2901234', $progIds['CONT'],  $proyIds['PF-CONT-01'], $instructors[2], 1, 'induccion',  30, '2024-08-01', '2026-12-10', 20],
        ['2912345', $progIds['ADSO'],  $proyIds['PF-ADSO-01'], $instructors[0], 1, 'planeacion',  0, '2025-01-15', '2027-02-05', 0],
        ['2823456', $progIds['LOG'],   $proyIds['PF-LOG-01'],  $instructors[3], 1, 'cierre',     26, '2024-04-01', '2026-04-25', 100],
    ];
    foreach ($fichas as $f) {
        $stmtFicha->execute($f);
    }

    // --- FASES DE PROYECTO (vinculadas a proyecto_id) ---
    $stmtFase = $pdo->prepare("INSERT INTO fases_proyecto (proyecto_id, numero_fase, nombre, descripcion, fecha_inicio, fecha_fin, cumplimiento_porcentaje, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $fasesNombres = ['Análisis', 'Planeación', 'Ejecución', 'Evaluación'];
    $fasesDesc = [
        'Levantamiento de requerimientos y análisis de necesidades',
        'Diseño y planificación del desarrollo',
        'Desarrollo, construcción e implementación',
        'Pruebas, verificación y cierre del proyecto'
    ];
    
    foreach ($proyIds as $code => $pid) {
        $avances = [];
        if (strpos($code, 'LOG') !== false)  $avances = [100, 100, 100, 100];
        elseif (strpos($code, 'ADSO') !== false) $avances = [100, 100, 60, 0];
        elseif (strpos($code, 'MM') !== false) $avances = [100, 80, 20, 0];
        else $avances = [50, 10, 0, 0];
        
        foreach ($fasesNombres as $i => $fase) {
            $estado = 'planeada';
            if ($avances[$i] == 100) $estado = 'completada';
            elseif ($avances[$i] > 0) $estado = 'en_ejecucion';
            
            $stmtFase->execute([
                $pid, $i + 1, $fase, $fasesDesc[$i],
                date('Y-m-d', strtotime('+' . ($i * 4) . ' months', strtotime('2024-06-01'))),
                date('Y-m-d', strtotime('+' . (($i + 1) * 4) . ' months', strtotime('2024-06-01'))),
                $avances[$i], $estado
            ]);
        }
    }

    // --- APRENDICES ---
    $stmtAprendiz = $pdo->prepare("INSERT INTO aprendices (usuario_id, ficha_id, numero_documento, tipo_documento, genero, fecha_nacimiento, telefono, ciudad, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'matriculado')");

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rol = 'aprendiz' ORDER BY id");
    $stmt->execute();
    $aprendices_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmtFichas = $pdo->prepare("SELECT id, numero_ficha, cantidad_aprendices FROM fichas ORDER BY id");
    $stmtFichas->execute();
    $fichas_db = $stmtFichas->fetchAll(PDO::FETCH_ASSOC);

    $document_start = 1000100201;

    // Asignar aprendices estáticos
    foreach ($aprendices_ids as $index => $u_id) {
        $ficha_idx = $index % count($fichas_db);
        $ficha_id = $fichas_db[$ficha_idx]['id'];
        $num_doc = (string)($document_start++);
        $genero = ($index % 2 == 0) ? 'M' : 'F';
        $stmtAprendiz->execute([$u_id, $ficha_id, $num_doc, 'CC', $genero, '2000-01-01', '3000000000', 'Bogotá']);
        if ($fichas_db[$ficha_idx]['cantidad_aprendices'] > 0) {
            $fichas_db[$ficha_idx]['cantidad_aprendices']--;
        }
    }

    // Generar el resto de aprendices
    $stmtInsertUsuario = $pdo->prepare("INSERT INTO usuarios (email, password, nombre, rol, avatar_color, estado) VALUES (?, ?, ?, 'aprendiz', ?, 'activo')");
    $nombres_f = ['María', 'Ana', 'Laura', 'Sofía', 'Daniela', 'Valentina', 'Camila', 'Isabella', 'Valeria', 'Mariana'];
    $nombres_m = ['Juan', 'Carlos', 'Andrés', 'Pedro', 'Luis', 'Diego', 'Santiago', 'Sebastián', 'Mateo', 'Alejandro'];
    $apellidos = ['García', 'Martínez', 'López', 'González', 'Rodríguez', 'Pérez', 'Sánchez', 'Ramírez', 'Cruz', 'Gómez', 'Torres', 'Vargas', 'Rojas', 'Díaz', 'Moreno'];
    $colores = ['#F59E0B', '#06B6D4', '#84CC16', '#D946EF', '#EAB308', '#3B82F6', '#EF4444', '#10B981', '#8B5CF6', '#F43F5E'];
    $user_counter = count($aprendices_ids) + 1;

    foreach ($fichas_db as $ficha) {
        $cant = $ficha['cantidad_aprendices'];
        $ficha_id = $ficha['id'];
        $numero_ficha = $ficha['numero_ficha'];

        for ($i = 0; $i < $cant; $i++) {
            $es_masculino = rand(0, 1);
            $nombre_pila = $es_masculino ? $nombres_m[array_rand($nombres_m)] : $nombres_f[array_rand($nombres_f)];
            $apellido1 = $apellidos[array_rand($apellidos)];
            $apellido2 = $apellidos[array_rand($apellidos)];
            $nombre_completo = "$nombre_pila $apellido1 $apellido2";
            $email = "aprendiz" . $user_counter . "_f" . $numero_ficha . "@sena.edu.co";
            $color = $colores[array_rand($colores)];

            $stmtInsertUsuario->execute([$email, $password_hash, $nombre_completo, $color]);
            $u_id = $pdo->lastInsertId();

            $num_doc = (string)($document_start++);
            $genero = $es_masculino ? 'M' : 'F';
            $fecha_nac = date('Y-m-d', strtotime('-' . rand(18, 30) . ' years -' . rand(0, 365) . ' days'));
            $telefono = '3' . rand(0, 2) . rand(0, 9) . rand(1000000, 9999999);

            $stmtAprendiz->execute([$u_id, $ficha_id, $num_doc, 'CC', $genero, $fecha_nac, $telefono, 'Bogotá']);
            $user_counter++;
        }
    }

    // --- EVALUACIONES (Conceptos A/D sobre Resultados de Aprendizaje) ---
    $stmtEval = $pdo->prepare("INSERT INTO evaluaciones (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // Solo generar evaluaciones para fichas en ejecución o cierre
    $fichasActivas = $pdo->query("
        SELECT f.id, f.programa_id, f.instructor_id 
        FROM fichas f 
        WHERE f.estado IN ('ejecucion', 'cierre')
    ")->fetchAll(PDO::FETCH_ASSOC);

    $comentariosA = [
        'Demuestra dominio del resultado de aprendizaje evaluado.',
        'Cumple satisfactoriamente con todos los criterios de evaluación.',
        'Evidencia aprendizaje significativo y aplicación práctica.',
        'Excelente desempeño en el desarrollo de la competencia.',
    ];
    $comentariosD = [
        'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.',
        'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.',
        'Debe mejorar en la aplicación práctica de los conocimientos.',
        'Necesita más tiempo de práctica para alcanzar el resultado esperado.',
    ];

    foreach ($fichasActivas as $fichaAct) {
        // Obtener RAs del programa de esta ficha
        $rasDePrograma = $pdo->prepare("
            SELECT ra.id 
            FROM resultados_aprendizaje ra 
            JOIN competencias c ON ra.competencia_id = c.id 
            WHERE c.programa_id = ?
        ");
        $rasDePrograma->execute([$fichaAct['programa_id']]);
        $raIds = $rasDePrograma->fetchAll(PDO::FETCH_COLUMN);

        // Obtener aprendices de esta ficha
        $aprendicesFicha = $pdo->prepare("SELECT id FROM aprendices WHERE ficha_id = ?");
        $aprendicesFicha->execute([$fichaAct['id']]);
        $aprendicesIds = $aprendicesFicha->fetchAll(PDO::FETCH_COLUMN);

        if (empty($raIds) || empty($aprendicesIds)) continue;

        // Evaluar un subconjunto de RAs para cada aprendiz
        $cantRAsEvaluar = max(1, (int)(count($raIds) * 0.6)); // Evaluar 60% de los RAs
        foreach ($aprendicesIds as $apId) {
            $rasAEvaluar = array_slice($raIds, 0, $cantRAsEvaluar);
            foreach ($rasAEvaluar as $raId) {
                $esAprobado = (rand(1, 100) <= 75); // 75% aprobados
                $concepto = $esAprobado ? 'A' : 'D';
                $comentario = $esAprobado 
                    ? $comentariosA[array_rand($comentariosA)] 
                    : $comentariosD[array_rand($comentariosD)];
                $fechaEval = date('Y-m-d', strtotime('-' . rand(1, 180) . ' days'));

                try {
                    $stmtEval->execute([
                        $raId, $apId, $fichaAct['instructor_id'], $fichaAct['id'],
                        $concepto, $comentario, $fechaEval
                    ]);
                } catch (Exception $e) {
                    // Unique constraint: skip duplicates
                    continue;
                }
            }
            
            // Dejar los RAs restantes como pendientes
            $rasPendientes = array_slice($raIds, $cantRAsEvaluar);
            foreach ($rasPendientes as $raId) {
                try {
                    $stmtEval->execute([
                        $raId, $apId, $fichaAct['instructor_id'], $fichaAct['id'],
                        'pendiente', null, null
                    ]);
                } catch (Exception $e) {
                    continue;
                }
            }
        }
    }

    // --- HISTORIAL DE EVALUACIONES (simular algunos cambios) ---
    $stmtHist = $pdo->prepare("INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo) VALUES (?, ?, ?, ?, ?)");
    
    // Tomar algunas evaluaciones con concepto 'A' y simular que antes fueron 'D'
    $evalsConCambio = $pdo->query("SELECT id, instructor_id FROM evaluaciones WHERE concepto = 'A' ORDER BY RAND() LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($evalsConCambio as $ev) {
        $stmtHist->execute([
            $ev['id'], $ev['instructor_id'], 'D', 'A',
            'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.'
        ]);
    }

    echo "<p>✅ Datos de prueba insertados correctamente</p>";
    echo "<p><a href='login.php'>Ir al login →</a></p>";
    echo "<p><strong>Credenciales demo:</strong><br>";
    echo "Coordinador: coordinador@sena.edu.co / admin123<br>";
    echo "Instructor: instructor@sena.edu.co a instructor5@sena.edu.co / admin123<br>";
    echo "Aprendiz: aprendiz@sena.edu.co a aprendiz5@sena.edu.co / admin123<br>";
    echo "</p>";
    echo "<h2>📊 Resumen de datos generados</h2>";
    
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $totalAprendices = $pdo->query("SELECT COUNT(*) FROM aprendices")->fetchColumn();
    $totalComps = $pdo->query("SELECT COUNT(*) FROM competencias")->fetchColumn();
    $totalRAs = $pdo->query("SELECT COUNT(*) FROM resultados_aprendizaje")->fetchColumn();
    $totalProyectos = $pdo->query("SELECT COUNT(*) FROM proyectos")->fetchColumn();
    $totalEvals = $pdo->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
    $totalHist = $pdo->query("SELECT COUNT(*) FROM historial_evaluaciones")->fetchColumn();
    
    echo "<ul>";
    echo "<li>Usuarios: $totalUsers</li>";
    echo "<li>Aprendices: $totalAprendices</li>";
    echo "<li>Competencias: $totalComps</li>";
    echo "<li>Resultados de Aprendizaje: $totalRAs</li>";
    echo "<li>Proyectos Formativos: $totalProyectos</li>";
    echo "<li>Evaluaciones (A/D/pendiente): $totalEvals</li>";
    echo "<li>Registros de historial: $totalHist</li>";
    echo "</ul>";

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
            max-width: 600px;
        }
        h1 { color: #39A900; margin: 0 0 1rem; }
        h2 { color: #2d8000; margin: 1.5rem 0 0.5rem; }
        p { margin: 0.5rem 0; line-height: 1.6; }
        a { color: #39A900; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
        ul { margin: 0.5rem 0; padding-left: 1.5rem; }
        li { margin: 0.25rem 0; }
    </style>
</head>
<body>
<div></div>
</body>
</html>
