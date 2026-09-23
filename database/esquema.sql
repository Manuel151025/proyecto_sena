-- =====================================================================
-- Esquema de la base de datos — Sistema de Seguimiento de Proyectos
-- Formativos SENA.
--
-- GENERADO por bin/volcar-esquema.php: no editar a mano. Los cambios de
-- esquema se hacen con una migración en database/migraciones/ y después
-- se regenera este archivo.
--
-- Lo carga bin/instalar.php en una instalación nueva.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `actividades`;
CREATE TABLE `actividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ficha_id` int(11) NOT NULL,
  `competencia_id` int(11) DEFAULT NULL,
  `fase_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','en_progreso','completada','cancelada') DEFAULT 'pendiente',
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `competencia_id` (`competencia_id`),
  KEY `responsable_id` (`responsable_id`),
  KEY `idx_ficha` (`ficha_id`),
  KEY `idx_estado` (`estado`),
  KEY `fk_actividades_fase` (`fase_id`),
  KEY `idx_ficha_fase` (`ficha_id`,`fase_id`),
  CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `actividades_ibfk_2` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`),
  CONSTRAINT `actividades_ibfk_3` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_actividades_fase` FOREIGN KEY (`fase_id`) REFERENCES `fases_proyecto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `aprendices`;
CREATE TABLE `aprendices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `instructor_seguimiento_id` int(11) DEFAULT NULL,
  `numero_documento` varchar(50) NOT NULL,
  `tipo_documento` enum('CC','TI','CE','PEP','PA') DEFAULT 'CC',
  `genero` enum('M','F','O') DEFAULT 'O',
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` enum('matriculado','suspendido','desertado','egresado','etapa_practica') NOT NULL DEFAULT 'matriculado',
  `fecha_matricula` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_documento` (`numero_documento`),
  KEY `idx_ficha` (`ficha_id`),
  KEY `fk_aprendiz_instructor_seguimiento` (`instructor_seguimiento_id`),
  KEY `idx_ficha_estado` (`ficha_id`,`estado`),
  CONSTRAINT `aprendices_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `aprendices_ibfk_2` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_aprendiz_instructor_seguimiento` FOREIGN KEY (`instructor_seguimiento_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `asignaciones`;
CREATE TABLE `asignaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ficha_id` int(11) NOT NULL,
  `competencia_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ficha_competencia` (`ficha_id`,`competencia_id`),
  KEY `idx_ficha` (`ficha_id`),
  KEY `idx_competencia` (`competencia_id`),
  KEY `idx_instructor` (`instructor_id`),
  CONSTRAINT `asignaciones_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaciones_ibfk_2` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaciones_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `competencias`;
CREATE TABLE `competencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `programa_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `es_etapa_practica` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Determina si califica el instructor de seguimiento en lugar del lider de ficha',
  `codigo` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `horas` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competencias_programa_codigo` (`programa_id`,`codigo`),
  KEY `idx_programa` (`programa_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_etapa_practica` (`es_etapa_practica`),
  CONSTRAINT `competencias_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `configuraciones_sistema`;
CREATE TABLE `configuraciones_sistema` (
  `clave` varchar(60) NOT NULL,
  `valor` text NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resultado_aprendizaje_id` int(11) NOT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `concepto` enum('A','D','pendiente') DEFAULT 'pendiente',
  `comentario` text DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_eval` (`resultado_aprendizaje_id`,`aprendiz_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `idx_ra` (`resultado_aprendizaje_id`),
  KEY `idx_aprendiz` (`aprendiz_id`),
  KEY `idx_concepto` (`concepto`),
  KEY `idx_ficha` (`ficha_id`),
  KEY `idx_ficha_concepto` (`ficha_id`,`concepto`),
  CONSTRAINT `evaluaciones_ibfk_1` FOREIGN KEY (`resultado_aprendizaje_id`) REFERENCES `resultados_aprendizaje` (`id`),
  CONSTRAINT `evaluaciones_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `evaluaciones_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `evaluaciones_ibfk_4` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `eventos_calendario`;
CREATE TABLE `eventos_calendario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `creado_por` int(11) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#f59e0b',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ficha_id` (`ficha_id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `evcal_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evcal_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `evidencias`;
CREATE TABLE `evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) DEFAULT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `archivo_url` varchar(500) DEFAULT NULL,
  `tipo_archivo` varchar(50) DEFAULT NULL,
  `tamaño_kb` int(11) DEFAULT NULL,
  `estado` enum('enviada','revisada','aprobada','rechazada') DEFAULT 'enviada',
  `retroalimentacion` text DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_revision` date DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evaluacion_id` (`evaluacion_id`),
  KEY `idx_aprendiz` (`aprendiz_id`),
  KEY `idx_ficha` (`ficha_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `evidencias_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `evidencias_ibfk_3` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fases_proyecto`;
CREATE TABLE `fases_proyecto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proyecto_id` int(11) NOT NULL,
  `numero_fase` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `estado` enum('planeada','en_ejecucion','completada') DEFAULT 'planeada',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fase_proyecto` (`proyecto_id`,`numero_fase`),
  KEY `idx_proyecto` (`proyecto_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fases_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fichas`;
CREATE TABLE `fichas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_ficha` varchar(50) NOT NULL,
  `programa_id` int(11) NOT NULL,
  `proyecto_id` int(11) DEFAULT NULL,
  `instructor_id` int(11) NOT NULL,
  `coordinador_id` int(11) DEFAULT NULL,
  `estado` enum('planeacion','induccion','ejecucion','cierre') DEFAULT 'planeacion',
  `cantidad_aprendices` int(11) DEFAULT 0,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_ficha` (`numero_ficha`),
  KEY `coordinador_id` (`coordinador_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_instructor` (`instructor_id`),
  KEY `idx_programa` (`programa_id`),
  KEY `idx_proyecto` (`proyecto_id`),
  CONSTRAINT `fichas_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fichas_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fichas_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fichas_ibfk_4` FOREIGN KEY (`coordinador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `historial_evaluaciones`;
CREATE TABLE `historial_evaluaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `concepto_anterior` enum('A','D','pendiente') NOT NULL,
  `concepto_nuevo` enum('A','D','pendiente') NOT NULL,
  `motivo` text DEFAULT NULL,
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_evaluacion` (`evaluacion_id`),
  KEY `idx_fecha` (`fecha_cambio`),
  CONSTRAINT `historial_evaluaciones_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `historial_evaluaciones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `intentos_acceso`;
CREATE TABLE `intentos_acceso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `accion` varchar(40) NOT NULL COMMENT 'login | recuperacion',
  `clave` varchar(80) NOT NULL COMMENT 'hash de identidad (id:) o de IP (ip:)',
  `intentos` int(11) NOT NULL DEFAULT 0,
  `primer_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unico_accion_clave` (`accion`,`clave`),
  KEY `idx_ultimo` (`ultimo_intento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `logs_sistema`;
CREATE TABLE `logs_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(100) DEFAULT NULL,
  `tabla_afectada` varchar(100) DEFAULT NULL,
  `id_registro` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_accion` (`accion`),
  CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `migraciones`;
CREATE TABLE `migraciones` (
  `id` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL DEFAULT '',
  `aplicada_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `duracion_ms` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` varchar(50) DEFAULT 'info',
  `url` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_leida` (`leida`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `ip_solicitud` varchar(45) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_expira` (`expira_en`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `planes_mejoramiento`;
CREATE TABLE `planes_mejoramiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) NOT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL COMMENT 'responsable del plan',
  `actividades` text NOT NULL COMMENT 'qué debe hacer el aprendiz',
  `fecha_inicio` date NOT NULL,
  `fecha_limite` date NOT NULL,
  `estado` enum('abierto','en_curso','cumplido','no_cumplido') NOT NULL DEFAULT 'abierto',
  `observaciones_cierre` text DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_evaluacion` (`evaluacion_id`),
  KEY `idx_aprendiz_estado` (`aprendiz_id`,`estado`),
  KEY `idx_ficha_estado` (`ficha_id`,`estado`),
  KEY `idx_instructor_estado` (`instructor_id`,`estado`),
  KEY `idx_limite` (`fecha_limite`),
  KEY `fk_plan_cerrado_por` (`cerrado_por`),
  KEY `fk_plan_creado_por` (`creado_por`),
  CONSTRAINT `fk_plan_aprendiz` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `fk_plan_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_plan_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_plan_evaluacion` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `fk_plan_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_plan_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_plan_fechas` CHECK (`fecha_limite` >= `fecha_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `programas`;
CREATE TABLE `programas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `duracion_horas` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo','archivado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_estado` (`estado`),
  KEY `idx_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `proyectos`;
CREATE TABLE `proyectos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `objetivo` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','inactivo','finalizado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `resultados_aprendizaje`;
CREATE TABLE `resultados_aprendizaje` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `competencia_id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `denominacion` text NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_resultados_aprendizaje_codigo` (`codigo`),
  KEY `idx_competencia` (`competencia_id`),
  KEY `idx_codigo` (`codigo`),
  CONSTRAINT `resultados_aprendizaje_ibfk_1` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `retroalimentacion`;
CREATE TABLE `retroalimentacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) DEFAULT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `tipo` enum('fortaleza','aspecto_mejorar','recomendacion') DEFAULT 'aspecto_mejorar',
  `contenido` text NOT NULL,
  `privada` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evaluacion_id` (`evaluacion_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `idx_aprendiz` (`aprendiz_id`),
  KEY `idx_tipo` (`tipo`),
  CONSTRAINT `retroalimentacion_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `retroalimentacion_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  CONSTRAINT `retroalimentacion_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `nombre` varchar(150) NOT NULL,
  `rol` enum('coordinador','instructor','aprendiz') NOT NULL,
  `avatar_color` varchar(7) DEFAULT '#39A900',
  `estado` enum('activo','inactivo','bloqueado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_rol` (`rol`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
