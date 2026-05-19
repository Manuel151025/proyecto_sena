-- Base de datos para el Proyecto SENA: Seguimiento de Fichas
-- Nombre de la base de datos: sena_seguimiento

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- Base de Datos: `sena_seguimiento`
-- ---------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `sena_seguimiento` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sena_seguimiento`;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `usuarios`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('coordinador','instructor','aprendiz') COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#39A900',
  `estado` enum('activo','inactivo','bloqueado') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_rol` (`rol`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `programas`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `programas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duracion_horas` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo','archivado') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `fichas`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fichas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_ficha` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `programa_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `coordinador_id` int(11) DEFAULT NULL,
  `estado` enum('planeacion','induccion','ejecucion','cierre') COLLATE utf8mb4_unicode_ci DEFAULT 'planeacion',
  `cantidad_aprendices` int(11) DEFAULT 0,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_ficha` (`numero_ficha`),
  KEY `programa_id` (`programa_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `coordinador_id` (`coordinador_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fichas_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fichas_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fichas_ibfk_3` FOREIGN KEY (`coordinador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `aprendices`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `aprendices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `numero_documento` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` enum('CC','TI','CE','PEP','PA') COLLATE utf8mb4_unicode_ci DEFAULT 'CC',
  `genero` enum('M','F','O') COLLATE utf8mb4_unicode_ci DEFAULT 'O',
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('matriculado','suspendido','desertado','egresado') COLLATE utf8mb4_unicode_ci DEFAULT 'matriculado',
  `fecha_matricula` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `usuario_id` (`usuario_id`),
  KEY `ficha_id` (`ficha_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `aprendices_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `aprendices_ibfk_2` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `competencias`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `programa_id` int(11) NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `horas` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `programa_id` (`programa_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `competencias_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `actividades`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ficha_id` int(11) NOT NULL,
  `competencia_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','en_progreso','completada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ficha_id` (`ficha_id`),
  KEY `competencia_id` (`competencia_id`),
  KEY `responsable_id` (`responsable_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `actividades_ibfk_2` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`),
  CONSTRAINT `actividades_ibfk_3` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `evaluaciones`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actividad_id` int(11) NOT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `concepto` enum('aprobado','en_proceso','no_aplica') COLLATE utf8mb4_unicode_ci DEFAULT 'en_proceso',
  `comentario` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calificacion` decimal(5,2) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `actividad_id` (`actividad_id`),
  KEY `aprendiz_id` (`aprendiz_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `idx_concepto` (`concepto`),
  CONSTRAINT `evaluaciones_ibfk_1` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`),
  CONSTRAINT `evaluaciones_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `evaluaciones_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `evidencias`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) DEFAULT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_archivo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamaño_kb` int(11) DEFAULT NULL,
  `estado` enum('enviada','revisada','aprobada','rechazada') COLLATE utf8mb4_unicode_ci DEFAULT 'enviada',
  `retroalimentacion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_revision` date DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evaluacion_id` (`evaluacion_id`),
  KEY `aprendiz_id` (`aprendiz_id`),
  KEY `ficha_id` (`ficha_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `evidencias_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `evidencias_ibfk_3` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `fases_proyecto`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fases_proyecto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ficha_id` int(11) NOT NULL,
  `numero_fase` int(11) NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `estado` enum('planeada','en_ejecucion','completada') COLLATE utf8mb4_unicode_ci DEFAULT 'planeada',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fase_ficha` (`ficha_id`,`numero_fase`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fases_proyecto_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `retroalimentacion`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `retroalimentacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) DEFAULT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `tipo` enum('fortaleza','aspecto_mejorar','recomendacion') COLLATE utf8mb4_unicode_ci DEFAULT 'aspecto_mejorar',
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `privada` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evaluacion_id` (`evaluacion_id`),
  KEY `aprendiz_id` (`aprendiz_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `idx_tipo` (`tipo`),
  CONSTRAINT `retroalimentacion_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `retroalimentacion_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `retroalimentacion_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Estructura de tabla para la tabla `logs_sistema`
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabla_afectada` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_registro` int(11) DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_accion` (`accion`),
  CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Volcado de datos para la tabla `usuarios`
-- Password para todos: admin123
-- ---------------------------------------------------------
DELETE FROM `usuarios`;
INSERT INTO `usuarios` (`id`, `email`, `password`, `nombre`, `rol`, `avatar_color`, `estado`) VALUES
(1, 'coordinador@sena.edu.co', '$2y$10$R1cHGZvrY/39gj.UYP5xA.3mfG55xZu37J8oLP.XIeO5fVZG8fmV6', 'Carlos Andrés Martínez', 'coordinador', '#39A900', 'activo'),
(2, 'instructor@sena.edu.co', '$2y$10$R1cHGZvrY/39gj.UYP5xA.3mfG55xZu37J8oLP.XIeO5fVZG8fmV6', 'María Fernanda López', 'instructor', '#3B82F6', 'activo'),
(3, 'instructor2@sena.edu.co', '$2y$10$R1cHGZvrY/39gj.UYP5xA.3mfG55xZu37J8oLP.XIeO5fVZG8fmV6', 'Jorge Salas', 'instructor', '#8B5CF6', 'activo'),
(4, 'instructor3@sena.edu.co', '$2y$10$R1cHGZvrY/39gj.UYP5xA.3mfG55xZu37J8oLP.XIeO5fVZG8fmV6', 'Diana Cruz', 'instructor', '#EC4899', 'activo'),
(5, 'aprendiz@sena.edu.co', '$2y$10$R1cHGZvrY/39gj.UYP5xA.3mfG55xZu37J8oLP.XIeO5fVZG8fmV6', 'Juan David Ramírez', 'aprendiz', '#F59E0B', 'activo');

-- ---------------------------------------------------------
-- Volcado de datos para la tabla `programas`
-- ---------------------------------------------------------
DELETE FROM `programas`;
INSERT INTO `programas` (`id`, `nombre`, `codigo`, `descripcion`, `duracion_horas`, `estado`) VALUES
(1, 'Análisis y Desarrollo de Software', 'ADSO', 'Programa de desarrollo de aplicaciones web y móviles', 2880, 'activo'),
(2, 'Multimedia', 'MM', 'Diseño gráfico y producción multimedia', 1440, 'activo'),
(3, 'Contabilidad', 'CONT', 'Gestión contable y financiera', 1920, 'activo'),
(4, 'Logística', 'LOG', 'Gestión de operaciones logísticas', 1200, 'activo');

-- ---------------------------------------------------------
-- Volcado de datos para la tabla `fichas`
-- ---------------------------------------------------------
DELETE FROM `fichas`;
INSERT INTO `fichas` (`id`, `numero_ficha`, `programa_id`, `instructor_id`, `coordinador_id`, `estado`, `cantidad_aprendices`, `fecha_inicio`, `fecha_fin`, `cumplimiento_porcentaje`) VALUES
(1, '2845671', 1, 2, 1, 'ejecucion', 32, '2024-06-15', '2026-06-15', 65.00),
(2, '2867812', 2, 3, 1, 'ejecucion', 28, '2024-07-15', '2026-07-30', 45.00),
(3, '2901234', 3, 4, 1, 'induccion', 30, '2024-08-01', '2026-12-10', 20.00),
(4, '2912345', 1, 2, 1, 'planeacion', 0, '2025-01-15', '2027-02-05', 0.00),
(5, '2823456', 4, 4, 1, 'cierre', 26, '2024-04-01', '2026-04-25', 100.00);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
