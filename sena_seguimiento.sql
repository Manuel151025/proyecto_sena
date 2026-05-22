-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-05-2026 a las 05:48:22
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sena_seguimiento`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades`
--

CREATE TABLE `actividades` (
  `id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `competencia_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','en_progreso','completada','cancelada') DEFAULT 'pendiente',
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aprendices`
--

CREATE TABLE `aprendices` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `numero_documento` varchar(50) NOT NULL,
  `tipo_documento` enum('CC','TI','CE','PEP','PA') DEFAULT 'CC',
  `genero` enum('M','F','O') DEFAULT 'O',
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` enum('matriculado','suspendido','desertado','egresado') DEFAULT 'matriculado',
  `fecha_matricula` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `aprendices`
--

INSERT INTO `aprendices` (`id`, `usuario_id`, `ficha_id`, `numero_documento`, `tipo_documento`, `genero`, `fecha_nacimiento`, `telefono`, `ciudad`, `estado`, `fecha_matricula`, `fecha_actualizacion`) VALUES
(1, 7, 1, '1000100201', 'CC', 'M', '2000-01-01', '3000000000', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 8, 2, '1000100202', 'CC', 'F', '2000-01-01', '3000000000', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 9, 3, '1000100203', 'CC', 'M', '2000-01-01', '3000000000', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 10, 4, '1000100204', 'CC', 'F', '2000-01-01', '3000000000', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, 11, 5, '1000100205', 'CC', 'M', '2000-01-01', '3000000000', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(6, 12, 1, '1000100206', 'CC', 'F', '2001-12-10', '3146211289', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(7, 13, 1, '1000100207', 'CC', 'F', '1998-02-12', '3218792868', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(8, 14, 1, '1000100208', 'CC', 'F', '2002-01-01', '3083955402', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(9, 15, 1, '1000100209', 'CC', 'M', '1999-01-05', '3197918637', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(10, 16, 1, '1000100210', 'CC', 'M', '2005-02-28', '3097452237', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(11, 17, 1, '1000100211', 'CC', 'M', '2006-01-22', '3249257427', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(12, 18, 1, '1000100212', 'CC', 'F', '2007-04-27', '3192705209', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(13, 19, 1, '1000100213', 'CC', 'F', '1995-09-27', '3287612691', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(14, 20, 1, '1000100214', 'CC', 'F', '1995-07-26', '3052660287', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(15, 21, 1, '1000100215', 'CC', 'M', '2000-12-21', '3025843531', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(16, 22, 1, '1000100216', 'CC', 'F', '2004-07-12', '3254939533', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(17, 23, 1, '1000100217', 'CC', 'F', '2005-05-25', '3101440360', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(18, 24, 1, '1000100218', 'CC', 'F', '2004-08-21', '3291986250', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(19, 25, 1, '1000100219', 'CC', 'F', '2007-05-15', '3118157556', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(20, 26, 1, '1000100220', 'CC', 'F', '2001-06-22', '3282247501', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(21, 27, 1, '1000100221', 'CC', 'F', '1998-07-14', '3149693620', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(22, 28, 1, '1000100222', 'CC', 'F', '1996-05-14', '3153256941', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(23, 29, 1, '1000100223', 'CC', 'F', '2006-11-16', '3059043849', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(24, 30, 1, '1000100224', 'CC', 'F', '2000-03-29', '3038642955', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(25, 31, 1, '1000100225', 'CC', 'F', '2001-09-15', '3026679564', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(26, 32, 1, '1000100226', 'CC', 'F', '1998-07-27', '3106975948', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(27, 33, 1, '1000100227', 'CC', 'M', '1998-04-08', '3249405413', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(28, 34, 1, '1000100228', 'CC', 'M', '2000-07-02', '3276922103', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(29, 35, 1, '1000100229', 'CC', 'F', '2006-06-23', '3003073449', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(30, 36, 1, '1000100230', 'CC', 'F', '2007-11-08', '3292546401', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(31, 37, 1, '1000100231', 'CC', 'M', '2005-03-14', '3294384130', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(32, 38, 1, '1000100232', 'CC', 'F', '2003-10-28', '3273500290', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(33, 39, 1, '1000100233', 'CC', 'M', '1997-05-16', '3295399501', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(34, 40, 1, '1000100234', 'CC', 'F', '2007-07-18', '3013567020', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(35, 41, 1, '1000100235', 'CC', 'F', '1998-12-09', '3016796681', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(36, 42, 1, '1000100236', 'CC', 'F', '2006-08-29', '3255633432', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(37, 43, 2, '1000100237', 'CC', 'M', '2007-03-26', '3152479446', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(38, 44, 2, '1000100238', 'CC', 'M', '2006-11-03', '3056197152', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(39, 45, 2, '1000100239', 'CC', 'M', '2003-01-03', '3118269721', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(40, 46, 2, '1000100240', 'CC', 'M', '1999-01-23', '3197809470', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(41, 47, 2, '1000100241', 'CC', 'F', '2002-07-15', '3263048647', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(42, 48, 2, '1000100242', 'CC', 'M', '2007-11-26', '3095875653', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(43, 49, 2, '1000100243', 'CC', 'F', '2000-07-20', '3234940771', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(44, 50, 2, '1000100244', 'CC', 'F', '2006-11-12', '3171116934', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(45, 51, 2, '1000100245', 'CC', 'F', '2005-01-29', '3215673272', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(46, 52, 2, '1000100246', 'CC', 'M', '1999-09-29', '3195964674', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(47, 53, 2, '1000100247', 'CC', 'F', '1997-04-21', '3296366925', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(48, 54, 2, '1000100248', 'CC', 'M', '2003-11-10', '3074047978', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(49, 55, 2, '1000100249', 'CC', 'M', '1996-09-13', '3159968216', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(50, 56, 2, '1000100250', 'CC', 'F', '2003-10-08', '3058896385', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(51, 57, 2, '1000100251', 'CC', 'M', '2004-07-17', '3039778337', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(52, 58, 2, '1000100252', 'CC', 'F', '1997-10-02', '3161128154', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(53, 59, 2, '1000100253', 'CC', 'F', '2005-08-28', '3133060477', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(54, 60, 2, '1000100254', 'CC', 'M', '1998-07-25', '3055783496', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(55, 61, 2, '1000100255', 'CC', 'F', '2000-10-18', '3044406668', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(56, 62, 2, '1000100256', 'CC', 'M', '1996-08-30', '3146905476', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(57, 63, 2, '1000100257', 'CC', 'F', '1997-03-11', '3217636802', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(58, 64, 2, '1000100258', 'CC', 'M', '1996-06-15', '3107450532', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(59, 65, 2, '1000100259', 'CC', 'M', '2001-02-04', '3027132723', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(60, 66, 2, '1000100260', 'CC', 'F', '1995-09-11', '3173771827', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(61, 67, 2, '1000100261', 'CC', 'F', '2006-11-17', '3219019558', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(62, 68, 2, '1000100262', 'CC', 'M', '2002-08-29', '3042405492', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(63, 69, 2, '1000100263', 'CC', 'M', '2006-06-08', '3298743133', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(64, 70, 3, '1000100264', 'CC', 'M', '2000-11-22', '3085364565', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(65, 71, 3, '1000100265', 'CC', 'F', '2001-02-02', '3173752759', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(66, 72, 3, '1000100266', 'CC', 'M', '2008-04-07', '3178103825', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(67, 73, 3, '1000100267', 'CC', 'F', '2003-05-03', '3249211155', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(68, 74, 3, '1000100268', 'CC', 'M', '2005-07-10', '3025146789', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(69, 75, 3, '1000100269', 'CC', 'M', '2007-12-19', '3184874764', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(70, 76, 3, '1000100270', 'CC', 'M', '2002-07-27', '3233721213', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(71, 77, 3, '1000100271', 'CC', 'F', '2007-05-25', '3228866885', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(72, 78, 3, '1000100272', 'CC', 'F', '1996-08-29', '3012125358', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(73, 79, 3, '1000100273', 'CC', 'M', '1996-03-29', '3147887835', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(74, 80, 3, '1000100274', 'CC', 'F', '2002-10-15', '3014411679', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(75, 81, 3, '1000100275', 'CC', 'F', '2004-07-04', '3214809705', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(76, 82, 3, '1000100276', 'CC', 'M', '2007-02-13', '3272780233', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(77, 83, 3, '1000100277', 'CC', 'F', '2001-12-19', '3096496037', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(78, 84, 3, '1000100278', 'CC', 'F', '1998-11-06', '3172534292', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(79, 85, 3, '1000100279', 'CC', 'M', '1999-03-03', '3251135723', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(80, 86, 3, '1000100280', 'CC', 'F', '2003-09-02', '3205037599', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(81, 87, 3, '1000100281', 'CC', 'M', '2001-06-28', '3113696185', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(82, 88, 3, '1000100282', 'CC', 'F', '2006-12-13', '3206853714', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(83, 89, 3, '1000100283', 'CC', 'F', '1998-08-18', '3258768851', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(84, 90, 3, '1000100284', 'CC', 'F', '1999-08-14', '3053513959', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(85, 91, 3, '1000100285', 'CC', 'F', '2003-12-18', '3287989761', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(86, 92, 3, '1000100286', 'CC', 'F', '1998-03-02', '3238619329', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(87, 93, 3, '1000100287', 'CC', 'F', '2003-10-13', '3149418100', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(88, 94, 3, '1000100288', 'CC', 'M', '2001-10-02', '3283905121', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(89, 95, 3, '1000100289', 'CC', 'F', '2000-10-18', '3035328387', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(90, 96, 3, '1000100290', 'CC', 'M', '1996-07-09', '3026629932', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(91, 97, 3, '1000100291', 'CC', 'M', '1997-09-24', '3165633071', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(92, 98, 3, '1000100292', 'CC', 'F', '2000-04-20', '3236763537', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(93, 99, 5, '1000100293', 'CC', 'F', '2000-06-04', '3295439357', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(94, 100, 5, '1000100294', 'CC', 'F', '1999-05-30', '3058830443', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(95, 101, 5, '1000100295', 'CC', 'M', '2002-01-28', '3075148458', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(96, 102, 5, '1000100296', 'CC', 'F', '1998-03-01', '3188322596', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(97, 103, 5, '1000100297', 'CC', 'F', '1996-08-13', '3231318143', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(98, 104, 5, '1000100298', 'CC', 'M', '2006-05-21', '3053021076', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(99, 105, 5, '1000100299', 'CC', 'M', '1998-01-18', '3039447435', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(100, 106, 5, '1000100300', 'CC', 'F', '2006-07-15', '3131991426', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(101, 107, 5, '1000100301', 'CC', 'F', '2003-07-28', '3012189853', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(102, 108, 5, '1000100302', 'CC', 'M', '2006-09-17', '3001147642', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(103, 109, 5, '1000100303', 'CC', 'F', '2001-07-30', '3244538242', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(104, 110, 5, '1000100304', 'CC', 'F', '2006-06-01', '3079453462', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(105, 111, 5, '1000100305', 'CC', 'F', '2004-06-12', '3181583097', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(106, 112, 5, '1000100306', 'CC', 'M', '1996-05-27', '3128512018', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(107, 113, 5, '1000100307', 'CC', 'M', '2000-01-08', '3193018727', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(108, 114, 5, '1000100308', 'CC', 'M', '1996-03-28', '3222775230', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(109, 115, 5, '1000100309', 'CC', 'M', '1998-04-16', '3235084472', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(110, 116, 5, '1000100310', 'CC', 'F', '2000-02-17', '3168458886', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(111, 117, 5, '1000100311', 'CC', 'F', '1997-01-23', '3252423923', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(112, 118, 5, '1000100312', 'CC', 'M', '1998-10-16', '3084108796', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(113, 119, 5, '1000100313', 'CC', 'M', '2007-09-16', '3249096089', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(114, 120, 5, '1000100314', 'CC', 'M', '2002-01-21', '3176939025', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(115, 121, 5, '1000100315', 'CC', 'F', '2005-10-12', '3137193917', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(116, 122, 5, '1000100316', 'CC', 'M', '1997-01-10', '3136836601', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(117, 123, 5, '1000100317', 'CC', 'F', '2001-09-17', '3019226445', 'Bogotá', 'matriculado', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `competencias`
--

CREATE TABLE `competencias` (
  `id` int(11) NOT NULL,
  `programa_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `horas` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `competencias`
--

INSERT INTO `competencias` (`id`, `programa_id`, `nombre`, `codigo`, `descripcion`, `horas`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 'Analizar los requisitos del cliente para construir el sistema de información', 'C220501001', 'Análisis de requisitos de software', 480, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 1, 'Diseñar el sistema de información que cumpla con los requisitos de la solución informática', 'C220501002', 'Diseño de sistemas de información', 480, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 1, 'Construir el sistema que cumpla con los requisitos de la solución informática', 'C220501003', 'Desarrollo y codificación de software', 720, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 1, 'Implementar la solución que cumpla con los requisitos para su operación', 'C220501004', 'Implantación y puesta en marcha', 360, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, 1, 'Participar en el proceso de negociación de tecnología informática', 'C220501005', 'Negociación tecnológica', 240, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(6, 2, 'Producir textos en inglés en forma escrita y oral', 'C240201500', 'Inglés técnico', 180, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(7, 2, 'Diseñar la solución multimedial de acuerdo con el informe de análisis', 'C220501006', 'Diseño multimedia', 420, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(8, 2, 'Construir la solución multimedial según especificaciones', 'C220501007', 'Producción multimedia', 420, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(9, 3, 'Contabilizar operaciones de acuerdo con las normas vigentes', 'C210301001', 'Contabilización', 480, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(10, 3, 'Analizar los resultados contables según los criterios de evaluación', 'C210301002', 'Análisis contable', 480, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(11, 3, 'Preparar y presentar la información contable y financiera', 'C210301003', 'Información financiera', 480, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(12, 4, 'Coordinar los procesos logísticos según normativa vigente', 'C260101001', 'Coordinación logística', 300, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(13, 4, 'Organizar los objetos en la unidad de almacenamiento', 'C260101002', 'Gestión de almacenamiento', 300, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(14, 4, 'Controlar las entradas y salidas de los objetos de la unidad de almacenamiento', 'C260101003', 'Control de inventarios', 300, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones`
--

CREATE TABLE `evaluaciones` (
  `id` int(11) NOT NULL,
  `resultado_aprendizaje_id` int(11) NOT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `concepto` enum('A','D','pendiente') DEFAULT 'pendiente',
  `comentario` text DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `evaluaciones`
--

INSERT INTO `evaluaciones` (`id`, `resultado_aprendizaje_id`, `aprendiz_id`, `instructor_id`, `ficha_id`, `concepto`, `comentario`, `fecha_evaluacion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-29', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 2, 1, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-27', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 3, 1, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-17', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 4, 1, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-22', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, 5, 1, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-15', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(6, 6, 1, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-01', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(7, 7, 1, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-02-10', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(8, 8, 1, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-24', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(9, 9, 1, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-18', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(10, 10, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(11, 11, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(12, 12, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(13, 13, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(14, 14, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(15, 15, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(16, 16, 1, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(17, 1, 6, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-07', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(18, 2, 6, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-12', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(19, 3, 6, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-22', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(20, 4, 6, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-12', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(21, 5, 6, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2025-12-22', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(22, 6, 6, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-28', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(23, 7, 6, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-01', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(24, 8, 6, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-01', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(25, 9, 6, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-12', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(26, 10, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(27, 11, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(28, 12, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(29, 13, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(30, 14, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(31, 15, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(32, 16, 6, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(33, 1, 7, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-16', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(34, 2, 7, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-28', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(35, 3, 7, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-21', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(36, 4, 7, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-20', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(37, 5, 7, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-01', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(38, 6, 7, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-30', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(39, 7, 7, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-17', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(40, 8, 7, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-07', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(41, 9, 7, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-16', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(42, 10, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(43, 11, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(44, 12, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(45, 13, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(46, 14, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(47, 15, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(48, 16, 7, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(49, 1, 8, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-10', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(50, 2, 8, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-13', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(51, 3, 8, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-27', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(52, 4, 8, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-06', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(53, 5, 8, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-18', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(54, 6, 8, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-30', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(55, 7, 8, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(56, 8, 8, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-14', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(57, 9, 8, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2025-12-05', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(58, 10, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(59, 11, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(60, 12, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(61, 13, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(62, 14, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(63, 15, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(64, 16, 8, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(65, 1, 9, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-05-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(66, 2, 9, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-02', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(67, 3, 9, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-15', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(68, 4, 9, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-11-28', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(69, 5, 9, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-27', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(70, 6, 9, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-21', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(71, 7, 9, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-16', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(72, 8, 9, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-10', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(73, 9, 9, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(74, 10, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(75, 11, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(76, 12, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(77, 13, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(78, 14, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(79, 15, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(80, 16, 9, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(81, 1, 10, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-02', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(82, 2, 10, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-11', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(83, 3, 10, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-16', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(84, 4, 10, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-27', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(85, 5, 10, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-15', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(86, 6, 10, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-15', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(87, 7, 10, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(88, 8, 10, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-02-28', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(89, 9, 10, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-05-11', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(90, 10, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(91, 11, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(92, 12, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(93, 13, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(94, 14, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(95, 15, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(96, 16, 10, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(97, 1, 11, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-04', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(98, 2, 11, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(99, 3, 11, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-06', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(100, 4, 11, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-03-18', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(101, 5, 11, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-31', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(102, 6, 11, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-02-22', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(103, 7, 11, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-05', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(104, 8, 11, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-29', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(105, 9, 11, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-02-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(106, 10, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(107, 11, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(108, 12, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(109, 13, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(110, 14, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(111, 15, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(112, 16, 11, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(113, 1, 12, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-26', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(114, 2, 12, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-04-24', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(115, 3, 12, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-12', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(116, 4, 12, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-03-27', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(117, 5, 12, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-05-03', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(118, 6, 12, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-15', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(119, 7, 12, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-14', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(120, 8, 12, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-03-21', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(121, 9, 12, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-27', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(122, 10, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(123, 11, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(124, 12, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(125, 13, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(126, 14, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(127, 15, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(128, 16, 12, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(129, 1, 13, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-06', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(130, 2, 13, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-22', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(131, 3, 13, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(132, 4, 13, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-08', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(133, 5, 13, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(134, 6, 13, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(135, 7, 13, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(136, 8, 13, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(137, 9, 13, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(138, 10, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(139, 11, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(140, 12, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(141, 13, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(142, 14, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(143, 15, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(144, 16, 13, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(145, 1, 14, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(146, 2, 14, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(147, 3, 14, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(148, 4, 14, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(149, 5, 14, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(150, 6, 14, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(151, 7, 14, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(152, 8, 14, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(153, 9, 14, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(154, 10, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(155, 11, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(156, 12, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(157, 13, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(158, 14, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(159, 15, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(160, 16, 14, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(161, 1, 15, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(162, 2, 15, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(163, 3, 15, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(164, 4, 15, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(165, 5, 15, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(166, 6, 15, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(167, 7, 15, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(168, 8, 15, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2025-12-01', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(169, 9, 15, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(170, 10, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(171, 11, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(172, 12, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(173, 13, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(174, 14, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(175, 15, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(176, 16, 15, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(177, 1, 16, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(178, 2, 16, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(179, 3, 16, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(180, 4, 16, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2025-11-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(181, 5, 16, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(182, 6, 16, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(183, 7, 16, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(184, 8, 16, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(185, 9, 16, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(186, 10, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(187, 11, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(188, 12, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(189, 13, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(190, 14, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(191, 15, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(192, 16, 16, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(193, 1, 17, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(194, 2, 17, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2025-12-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(195, 3, 17, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2025-11-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(196, 4, 17, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-05-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(197, 5, 17, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(198, 6, 17, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-11-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(199, 7, 17, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-04-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(200, 8, 17, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(201, 9, 17, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(202, 10, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(203, 11, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(204, 12, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(205, 13, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(206, 14, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(207, 15, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(208, 16, 17, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(209, 1, 18, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(210, 2, 18, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(211, 3, 18, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-22', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(212, 4, 18, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(213, 5, 18, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(214, 6, 18, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(215, 7, 18, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-03-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(216, 8, 18, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(217, 9, 18, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(218, 10, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(219, 11, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(220, 12, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(221, 13, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(222, 14, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(223, 15, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(224, 16, 18, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(225, 1, 19, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-11-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(226, 2, 19, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(227, 3, 19, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(228, 4, 19, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(229, 5, 19, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-03-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(230, 6, 19, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(231, 7, 19, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(232, 8, 19, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-02-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(233, 9, 19, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-18', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(234, 10, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(235, 11, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(236, 12, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(237, 13, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(238, 14, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(239, 15, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(240, 16, 19, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(241, 1, 20, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(242, 2, 20, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-31', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(243, 3, 20, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(244, 4, 20, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(245, 5, 20, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(246, 6, 20, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(247, 7, 20, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(248, 8, 20, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(249, 9, 20, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-31', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(250, 10, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(251, 11, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(252, 12, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(253, 13, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(254, 14, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(255, 15, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(256, 16, 20, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(257, 1, 21, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(258, 2, 21, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-04-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(259, 3, 21, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(260, 4, 21, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(261, 5, 21, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(262, 6, 21, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(263, 7, 21, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(264, 8, 21, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(265, 9, 21, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(266, 10, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(267, 11, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(268, 12, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(269, 13, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(270, 14, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(271, 15, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(272, 16, 21, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(273, 1, 22, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-11-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(274, 2, 22, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(275, 3, 22, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(276, 4, 22, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(277, 5, 22, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(278, 6, 22, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(279, 7, 22, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(280, 8, 22, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(281, 9, 22, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(282, 10, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(283, 11, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(284, 12, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(285, 13, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(286, 14, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(287, 15, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(288, 16, 22, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(289, 1, 23, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(290, 2, 23, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(291, 3, 23, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(292, 4, 23, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2025-12-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(293, 5, 23, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-31', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(294, 6, 23, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-02-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(295, 7, 23, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(296, 8, 23, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-05-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(297, 9, 23, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(298, 10, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(299, 11, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(300, 12, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(301, 13, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(302, 14, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(303, 15, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(304, 16, 23, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(305, 1, 24, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(306, 2, 24, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(307, 3, 24, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(308, 4, 24, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(309, 5, 24, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-01', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(310, 6, 24, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(311, 7, 24, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(312, 8, 24, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(313, 9, 24, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2025-12-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(314, 10, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(315, 11, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(316, 12, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(317, 13, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(318, 14, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(319, 15, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(320, 16, 24, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(321, 1, 25, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2025-12-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(322, 2, 25, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(323, 3, 25, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(324, 4, 25, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-22', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(325, 5, 25, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(326, 6, 25, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(327, 7, 25, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(328, 8, 25, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(329, 9, 25, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(330, 10, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(331, 11, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(332, 12, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(333, 13, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(334, 14, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(335, 15, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(336, 16, 25, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(337, 1, 26, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(338, 2, 26, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(339, 3, 26, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(340, 4, 26, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-02-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(341, 5, 26, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(342, 6, 26, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(343, 7, 26, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(344, 8, 26, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-11-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(345, 9, 26, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-31', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(346, 10, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(347, 11, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(348, 12, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(349, 13, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(350, 14, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(351, 15, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(352, 16, 26, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(353, 1, 27, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(354, 2, 27, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(355, 3, 27, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(356, 4, 27, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-01-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(357, 5, 27, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(358, 6, 27, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(359, 7, 27, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(360, 8, 27, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(361, 9, 27, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(362, 10, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(363, 11, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(364, 12, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(365, 13, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(366, 14, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(367, 15, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(368, 16, 27, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(369, 1, 28, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(370, 2, 28, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(371, 3, 28, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(372, 4, 28, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(373, 5, 28, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(374, 6, 28, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2025-12-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(375, 7, 28, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(376, 8, 28, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(377, 9, 28, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(378, 10, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(379, 11, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(380, 12, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(381, 13, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(382, 14, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(383, 15, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(384, 16, 28, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(385, 1, 29, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(386, 2, 29, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(387, 3, 29, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(388, 4, 29, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2025-12-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(389, 5, 29, 2, 1, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-05-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(390, 6, 29, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-18', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(391, 7, 29, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-31', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(392, 8, 29, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-01', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(393, 9, 29, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-02-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(394, 10, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(395, 11, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(396, 12, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(397, 13, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(398, 14, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(399, 15, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(400, 16, 29, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(401, 1, 30, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(402, 2, 30, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(403, 3, 30, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(404, 4, 30, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(405, 5, 30, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24');
INSERT INTO `evaluaciones` (`id`, `resultado_aprendizaje_id`, `aprendiz_id`, `instructor_id`, `ficha_id`, `concepto`, `comentario`, `fecha_evaluacion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(406, 6, 30, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(407, 7, 30, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2025-12-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(408, 8, 30, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(409, 9, 30, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(410, 10, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(411, 11, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(412, 12, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(413, 13, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(414, 14, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(415, 15, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(416, 16, 30, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(417, 1, 31, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(418, 2, 31, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(419, 3, 31, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(420, 4, 31, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(421, 5, 31, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(422, 6, 31, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(423, 7, 31, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-11-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(424, 8, 31, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(425, 9, 31, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(426, 10, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(427, 11, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(428, 12, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(429, 13, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(430, 14, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(431, 15, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(432, 16, 31, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(433, 1, 32, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-05-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(434, 2, 32, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(435, 3, 32, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2025-11-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(436, 4, 32, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(437, 5, 32, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(438, 6, 32, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(439, 7, 32, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(440, 8, 32, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(441, 9, 32, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(442, 10, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(443, 11, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(444, 12, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(445, 13, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(446, 14, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(447, 15, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(448, 16, 32, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(449, 1, 33, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2025-11-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(450, 2, 33, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-02-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(451, 3, 33, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-01-01', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(452, 4, 33, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(453, 5, 33, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(454, 6, 33, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2025-12-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(455, 7, 33, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(456, 8, 33, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(457, 9, 33, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(458, 10, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(459, 11, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(460, 12, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(461, 13, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(462, 14, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(463, 15, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(464, 16, 33, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(465, 1, 34, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(466, 2, 34, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(467, 3, 34, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(468, 4, 34, 2, 1, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(469, 5, 34, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(470, 6, 34, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(471, 7, 34, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(472, 8, 34, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(473, 9, 34, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(474, 10, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(475, 11, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(476, 12, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(477, 13, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(478, 14, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(479, 15, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(480, 16, 34, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(481, 1, 35, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(482, 2, 35, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-01', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(483, 3, 35, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(484, 4, 35, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(485, 5, 35, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-16', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(486, 6, 35, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(487, 7, 35, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(488, 8, 35, 2, 1, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(489, 9, 35, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-18', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(490, 10, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(491, 11, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(492, 12, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(493, 13, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(494, 14, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(495, 15, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(496, 16, 35, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(497, 1, 36, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-02-11', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(498, 2, 36, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-10', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(499, 3, 36, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-01-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(500, 4, 36, 2, 1, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2025-12-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(501, 5, 36, 2, 1, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(502, 6, 36, 2, 1, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(503, 7, 36, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(504, 8, 36, 2, 1, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(505, 9, 36, 2, 1, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(506, 10, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(507, 11, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(508, 12, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(509, 13, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(510, 14, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(511, 15, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(512, 16, 36, 2, 1, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(513, 17, 2, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-11-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(514, 18, 2, 3, 2, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(515, 19, 2, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(516, 20, 2, 3, 2, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-03-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(517, 21, 2, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(518, 22, 2, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(519, 23, 2, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(520, 24, 2, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(521, 17, 37, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(522, 18, 37, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(523, 19, 37, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-22', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(524, 20, 37, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(525, 21, 37, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(526, 22, 37, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(527, 23, 37, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(528, 24, 37, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(529, 17, 38, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(530, 18, 38, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(531, 19, 38, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(532, 20, 38, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(533, 21, 38, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(534, 22, 38, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(535, 23, 38, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(536, 24, 38, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(537, 17, 39, 3, 2, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-02-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(538, 18, 39, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(539, 19, 39, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(540, 20, 39, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(541, 21, 39, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(542, 22, 39, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(543, 23, 39, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(544, 24, 39, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(545, 17, 40, 3, 2, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-03-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(546, 18, 40, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-11-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(547, 19, 40, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(548, 20, 40, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-21', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(549, 21, 40, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(550, 22, 40, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(551, 23, 40, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(552, 24, 40, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(553, 17, 41, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(554, 18, 41, 3, 2, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(555, 19, 41, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-29', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(556, 20, 41, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(557, 21, 41, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(558, 22, 41, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(559, 23, 41, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(560, 24, 41, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(561, 17, 42, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(562, 18, 42, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-31', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(563, 19, 42, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(564, 20, 42, 3, 2, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2025-12-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(565, 21, 42, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(566, 22, 42, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(567, 23, 42, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(568, 24, 42, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(569, 17, 43, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(570, 18, 43, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(571, 19, 43, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(572, 20, 43, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-18', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(573, 21, 43, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(574, 22, 43, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(575, 23, 43, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(576, 24, 43, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(577, 17, 44, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(578, 18, 44, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2025-11-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(579, 19, 44, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-22', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(580, 20, 44, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-02-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(581, 21, 44, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(582, 22, 44, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(583, 23, 44, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(584, 24, 44, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(585, 17, 45, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(586, 18, 45, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-11-26', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(587, 19, 45, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(588, 20, 45, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(589, 21, 45, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(590, 22, 45, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(591, 23, 45, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(592, 24, 45, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(593, 17, 46, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(594, 18, 46, 3, 2, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2025-12-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(595, 19, 46, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-27', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(596, 20, 46, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(597, 21, 46, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(598, 22, 46, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(599, 23, 46, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(600, 24, 46, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(601, 17, 47, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(602, 18, 47, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(603, 19, 47, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(604, 20, 47, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(605, 21, 47, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(606, 22, 47, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(607, 23, 47, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(608, 24, 47, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(609, 17, 48, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(610, 18, 48, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(611, 19, 48, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(612, 20, 48, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(613, 21, 48, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(614, 22, 48, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(615, 23, 48, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(616, 24, 48, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(617, 17, 49, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(618, 18, 49, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-06', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(619, 19, 49, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-01', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(620, 20, 49, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-11-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(621, 21, 49, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(622, 22, 49, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(623, 23, 49, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(624, 24, 49, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(625, 17, 50, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-14', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(626, 18, 50, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-17', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(627, 19, 50, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(628, 20, 50, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(629, 21, 50, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(630, 22, 50, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(631, 23, 50, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(632, 24, 50, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(633, 17, 51, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(634, 18, 51, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(635, 19, 51, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-18', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(636, 20, 51, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-11-24', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(637, 21, 51, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(638, 22, 51, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(639, 23, 51, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(640, 24, 51, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(641, 17, 52, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-13', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(642, 18, 52, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(643, 19, 52, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2025-12-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(644, 20, 52, 3, 2, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-04-07', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(645, 21, 52, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(646, 22, 52, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(647, 23, 52, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(648, 24, 52, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(649, 17, 53, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-04-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(650, 18, 53, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(651, 19, 53, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(652, 20, 53, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(653, 21, 53, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(654, 22, 53, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(655, 23, 53, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(656, 24, 53, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(657, 17, 54, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2025-12-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(658, 18, 54, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-18', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(659, 19, 54, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-30', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(660, 20, 54, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(661, 21, 54, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(662, 22, 54, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(663, 23, 54, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(664, 24, 54, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(665, 17, 55, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-20', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(666, 18, 55, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-15', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(667, 19, 55, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-22', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(668, 20, 55, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-09', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(669, 21, 55, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(670, 22, 55, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(671, 23, 55, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(672, 24, 55, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(673, 17, 56, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-12', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(674, 18, 56, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-23', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(675, 19, 56, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-08', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(676, 20, 56, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-03', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(677, 21, 56, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(678, 22, 56, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(679, 23, 56, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(680, 24, 56, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(681, 17, 57, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-02', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(682, 18, 57, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-28', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(683, 19, 57, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-19', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(684, 20, 57, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-04', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(685, 21, 57, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(686, 22, 57, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(687, 23, 57, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(688, 24, 57, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(689, 17, 58, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-05', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(690, 18, 58, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-11-25', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(691, 19, 58, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-22', '2026-05-21 03:44:24', '2026-05-21 03:44:24'),
(692, 20, 58, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-01', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(693, 21, 58, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(694, 22, 58, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(695, 23, 58, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(696, 24, 58, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(697, 17, 59, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-19', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(698, 18, 59, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2025-12-21', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(699, 19, 59, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-05', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(700, 20, 59, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-16', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(701, 21, 59, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(702, 22, 59, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(703, 23, 59, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(704, 24, 59, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(705, 17, 60, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-03-18', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(706, 18, 60, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-02-05', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(707, 19, 60, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-27', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(708, 20, 60, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-02-23', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(709, 21, 60, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(710, 22, 60, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(711, 23, 60, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(712, 24, 60, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(713, 17, 61, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-12', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(714, 18, 61, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-24', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(715, 19, 61, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-02', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(716, 20, 61, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-03-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(717, 21, 61, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(718, 22, 61, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(719, 23, 61, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(720, 24, 61, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(721, 17, 62, 3, 2, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-11-26', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(722, 18, 62, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-06', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(723, 19, 62, 3, 2, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-28', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(724, 20, 62, 3, 2, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-03-30', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(725, 21, 62, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(726, 22, 62, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(727, 23, 62, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(728, 24, 62, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(729, 17, 63, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-14', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(730, 18, 63, 3, 2, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-05', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(731, 19, 63, 3, 2, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-01-22', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(732, 20, 63, 3, 2, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-09', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(733, 21, 63, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(734, 22, 63, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(735, 23, 63, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(736, 24, 63, 3, 2, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(737, 33, 5, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-01', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(738, 34, 5, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-31', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(739, 35, 5, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-12', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(740, 36, 5, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-23', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(741, 37, 5, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(742, 38, 5, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(743, 39, 5, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(744, 40, 5, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(745, 33, 93, 5, 5, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-30', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(746, 34, 93, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-06', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(747, 35, 93, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-02', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(748, 36, 93, 5, 5, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-11', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(749, 37, 93, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(750, 38, 93, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(751, 39, 93, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(752, 40, 93, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(753, 33, 94, 5, 5, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-02-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(754, 34, 94, 5, 5, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-04-02', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(755, 35, 94, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-11-24', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(756, 36, 94, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-10', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(757, 37, 94, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(758, 38, 94, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(759, 39, 94, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(760, 40, 94, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(761, 33, 95, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(762, 34, 95, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-04', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(763, 35, 95, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-22', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(764, 36, 95, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-05-18', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(765, 37, 95, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(766, 38, 95, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(767, 39, 95, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(768, 40, 95, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(769, 33, 96, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(770, 34, 96, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-23', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(771, 35, 96, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-20', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(772, 36, 96, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-01-03', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(773, 37, 96, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(774, 38, 96, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(775, 39, 96, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(776, 40, 96, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(777, 33, 97, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-29', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(778, 34, 97, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-29', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(779, 35, 97, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-11-29', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(780, 36, 97, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-05-17', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(781, 37, 97, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(782, 38, 97, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(783, 39, 97, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(784, 40, 97, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(785, 33, 98, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-02', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(786, 34, 98, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-24', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(787, 35, 98, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-03-18', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(788, 36, 98, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-11-26', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(789, 37, 98, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(790, 38, 98, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(791, 39, 98, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(792, 40, 98, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(793, 33, 99, 5, 5, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-05-14', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(794, 34, 99, 5, 5, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-01-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(795, 35, 99, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-01-14', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(796, 36, 99, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-03-28', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(797, 37, 99, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(798, 38, 99, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(799, 39, 99, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(800, 40, 99, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(801, 33, 100, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-25', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(802, 34, 100, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2025-12-23', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(803, 35, 100, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-01', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(804, 36, 100, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-17', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(805, 37, 100, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(806, 38, 100, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(807, 39, 100, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(808, 40, 100, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(809, 33, 101, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-03', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(810, 34, 101, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-04-24', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(811, 35, 101, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-27', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(812, 36, 101, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-02-28', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(813, 37, 101, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(814, 38, 101, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(815, 39, 101, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(816, 40, 101, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(817, 33, 102, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-12-30', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(818, 34, 102, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-19', '2026-05-21 03:44:25', '2026-05-21 03:44:25');
INSERT INTO `evaluaciones` (`id`, `resultado_aprendizaje_id`, `aprendiz_id`, `instructor_id`, `ficha_id`, `concepto`, `comentario`, `fecha_evaluacion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(819, 35, 102, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-21', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(820, 36, 102, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-11', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(821, 37, 102, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(822, 38, 102, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(823, 39, 102, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(824, 40, 102, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(825, 33, 103, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(826, 34, 103, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-02', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(827, 35, 103, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-11-27', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(828, 36, 103, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-10', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(829, 37, 103, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(830, 38, 103, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(831, 39, 103, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(832, 40, 103, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(833, 33, 104, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-14', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(834, 34, 104, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-22', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(835, 35, 104, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-01', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(836, 36, 104, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-01-20', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(837, 37, 104, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(838, 38, 104, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(839, 39, 104, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(840, 40, 104, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(841, 33, 105, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-07', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(842, 34, 105, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-16', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(843, 35, 105, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-02-03', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(844, 36, 105, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-05-17', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(845, 37, 105, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(846, 38, 105, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(847, 39, 105, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(848, 40, 105, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(849, 33, 106, 5, 5, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-04-10', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(850, 34, 106, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(851, 35, 106, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-02', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(852, 36, 106, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-04', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(853, 37, 106, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(854, 38, 106, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(855, 39, 106, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(856, 40, 106, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(857, 33, 107, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-17', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(858, 34, 107, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-03', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(859, 35, 107, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-03-14', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(860, 36, 107, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(861, 37, 107, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(862, 38, 107, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(863, 39, 107, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(864, 40, 107, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(865, 33, 108, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-14', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(866, 34, 108, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-31', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(867, 35, 108, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-26', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(868, 36, 108, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-18', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(869, 37, 108, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(870, 38, 108, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(871, 39, 108, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(872, 40, 108, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(873, 33, 109, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-29', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(874, 34, 109, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-03-29', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(875, 35, 109, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(876, 36, 109, 5, 5, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-03-27', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(877, 37, 109, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(878, 38, 109, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(879, 39, 109, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(880, 40, 109, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(881, 33, 110, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-06', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(882, 34, 110, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-18', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(883, 35, 110, 5, 5, 'D', 'Requiere reforzar los conceptos fundamentales del resultado de aprendizaje.', '2026-04-24', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(884, 36, 110, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-05', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(885, 37, 110, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(886, 38, 110, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(887, 39, 110, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(888, 40, 110, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(889, 33, 111, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(890, 34, 111, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2025-11-28', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(891, 35, 111, 5, 5, 'D', 'Necesita más tiempo de práctica para alcanzar el resultado esperado.', '2026-02-21', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(892, 36, 111, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-02-11', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(893, 37, 111, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(894, 38, 111, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(895, 39, 111, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(896, 40, 111, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(897, 33, 112, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(898, 34, 112, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-01-19', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(899, 35, 112, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-03-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(900, 36, 112, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-16', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(901, 37, 112, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(902, 38, 112, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(903, 39, 112, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(904, 40, 112, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(905, 33, 113, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2026-04-06', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(906, 34, 113, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-04-19', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(907, 35, 113, 5, 5, 'A', 'Evidencia aprendizaje significativo y aplicación práctica.', '2025-12-25', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(908, 36, 113, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(909, 37, 113, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(910, 38, 113, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(911, 39, 113, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(912, 40, 113, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(913, 33, 114, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-04-18', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(914, 34, 114, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2025-12-03', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(915, 35, 114, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-05-01', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(916, 36, 114, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-01-13', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(917, 37, 114, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(918, 38, 114, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(919, 39, 114, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(920, 40, 114, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(921, 33, 115, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2025-12-19', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(922, 34, 115, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-05-08', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(923, 35, 115, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-01-05', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(924, 36, 115, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-03-10', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(925, 37, 115, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(926, 38, 115, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(927, 39, 115, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(928, 40, 115, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(929, 33, 116, 5, 5, 'D', 'No alcanza los criterios mínimos de evaluación. Se sugiere plan de mejoramiento.', '2026-02-04', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(930, 34, 116, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-01-22', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(931, 35, 116, 5, 5, 'D', 'Debe mejorar en la aplicación práctica de los conocimientos.', '2026-04-11', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(932, 36, 116, 5, 5, 'A', 'Demuestra dominio del resultado de aprendizaje evaluado.', '2026-04-17', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(933, 37, 116, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(934, 38, 116, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(935, 39, 116, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(936, 40, 116, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(937, 33, 117, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-01-06', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(938, 34, 117, 5, 5, 'A', 'Cumple satisfactoriamente con todos los criterios de evaluación.', '2026-05-09', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(939, 35, 117, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2026-02-06', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(940, 36, 117, 5, 5, 'A', 'Excelente desempeño en el desarrollo de la competencia.', '2025-12-11', '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(941, 37, 117, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(942, 38, 117, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(943, 39, 117, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25'),
(944, 40, 117, 5, 5, 'pendiente', NULL, NULL, '2026-05-21 03:44:25', '2026-05-21 03:44:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evidencias`
--

CREATE TABLE `evidencias` (
  `id` int(11) NOT NULL,
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
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fases_proyecto`
--

CREATE TABLE `fases_proyecto` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `numero_fase` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `cumplimiento_porcentaje` decimal(5,2) DEFAULT 0.00,
  `estado` enum('planeada','en_ejecucion','completada') DEFAULT 'planeada',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fases_proyecto`
--

INSERT INTO `fases_proyecto` (`id`, `proyecto_id`, `numero_fase`, `nombre`, `descripcion`, `fecha_inicio`, `fecha_fin`, `cumplimiento_porcentaje`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 'Análisis', 'Levantamiento de requerimientos y análisis de necesidades', '2024-06-01', '2024-10-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 1, 2, 'Planeación', 'Diseño y planificación del desarrollo', '2024-10-01', '2025-02-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 1, 3, 'Ejecución', 'Desarrollo, construcción e implementación', '2025-02-01', '2025-06-01', 60.00, 'en_ejecucion', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 1, 4, 'Evaluación', 'Pruebas, verificación y cierre del proyecto', '2025-06-01', '2025-10-01', 0.00, 'planeada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, 2, 1, 'Análisis', 'Levantamiento de requerimientos y análisis de necesidades', '2024-06-01', '2024-10-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(6, 2, 2, 'Planeación', 'Diseño y planificación del desarrollo', '2024-10-01', '2025-02-01', 80.00, 'en_ejecucion', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(7, 2, 3, 'Ejecución', 'Desarrollo, construcción e implementación', '2025-02-01', '2025-06-01', 20.00, 'en_ejecucion', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(8, 2, 4, 'Evaluación', 'Pruebas, verificación y cierre del proyecto', '2025-06-01', '2025-10-01', 0.00, 'planeada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(9, 3, 1, 'Análisis', 'Levantamiento de requerimientos y análisis de necesidades', '2024-06-01', '2024-10-01', 50.00, 'en_ejecucion', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(10, 3, 2, 'Planeación', 'Diseño y planificación del desarrollo', '2024-10-01', '2025-02-01', 10.00, 'en_ejecucion', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(11, 3, 3, 'Ejecución', 'Desarrollo, construcción e implementación', '2025-02-01', '2025-06-01', 0.00, 'planeada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(12, 3, 4, 'Evaluación', 'Pruebas, verificación y cierre del proyecto', '2025-06-01', '2025-10-01', 0.00, 'planeada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(13, 4, 1, 'Análisis', 'Levantamiento de requerimientos y análisis de necesidades', '2024-06-01', '2024-10-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(14, 4, 2, 'Planeación', 'Diseño y planificación del desarrollo', '2024-10-01', '2025-02-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(15, 4, 3, 'Ejecución', 'Desarrollo, construcción e implementación', '2025-02-01', '2025-06-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(16, 4, 4, 'Evaluación', 'Pruebas, verificación y cierre del proyecto', '2025-06-01', '2025-10-01', 100.00, 'completada', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fichas`
--

CREATE TABLE `fichas` (
  `id` int(11) NOT NULL,
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
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fichas`
--

INSERT INTO `fichas` (`id`, `numero_ficha`, `programa_id`, `proyecto_id`, `instructor_id`, `coordinador_id`, `estado`, `cantidad_aprendices`, `fecha_inicio`, `fecha_fin`, `cumplimiento_porcentaje`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, '2845671', 1, 1, 2, 1, 'ejecucion', 32, '2024-06-15', '2026-06-15', 65.00, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, '2867812', 2, 2, 3, 1, 'ejecucion', 28, '2024-07-15', '2026-07-30', 45.00, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, '2901234', 3, 3, 4, 1, 'induccion', 30, '2024-08-01', '2026-12-10', 20.00, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, '2912345', 1, 1, 2, 1, 'planeacion', 0, '2025-01-15', '2027-02-05', 0.00, '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, '2823456', 4, 4, 5, 1, 'cierre', 26, '2024-04-01', '2026-04-25', 100.00, '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_evaluaciones`
--

CREATE TABLE `historial_evaluaciones` (
  `id` int(11) NOT NULL,
  `evaluacion_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `concepto_anterior` enum('A','D','pendiente') NOT NULL,
  `concepto_nuevo` enum('A','D','pendiente') NOT NULL,
  `motivo` text DEFAULT NULL,
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_evaluaciones`
--

INSERT INTO `historial_evaluaciones` (`id`, `evaluacion_id`, `usuario_id`, `concepto_anterior`, `concepto_nuevo`, `motivo`, `fecha_cambio`) VALUES
(1, 161, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(2, 212, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(3, 136, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(4, 8, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(5, 184, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(6, 87, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(7, 262, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(8, 504, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(9, 937, 5, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(10, 865, 5, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(11, 714, 3, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(12, 469, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(13, 370, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(14, 386, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25'),
(15, 279, 2, 'D', 'A', 'El aprendiz presentó plan de mejoramiento exitosamente y demostró competencia en segunda oportunidad.', '2026-05-21 03:44:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(100) DEFAULT NULL,
  `tabla_afectada` varchar(100) DEFAULT NULL,
  `id_registro` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `ip_solicitud` varchar(45) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` varchar(50) DEFAULT 'info',
  `url` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programas`
--

CREATE TABLE `programas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `duracion_horas` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo','archivado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `programas`
--

INSERT INTO `programas` (`id`, `nombre`, `codigo`, `descripcion`, `duracion_horas`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Análisis y Desarrollo de Software', 'ADSO', 'Programa de desarrollo de aplicaciones web y móviles', 2880, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 'Multimedia', 'MM', 'Diseño gráfico y producción multimedia', 1440, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 'Contabilidad', 'CONT', 'Gestión contable y financiera', 1920, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 'Logística', 'LOG', 'Gestión de operaciones logísticas', 1200, 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyectos`
--

CREATE TABLE `proyectos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `objetivo` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','inactivo','finalizado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proyectos`
--

INSERT INTO `proyectos` (`id`, `nombre`, `codigo`, `objetivo`, `descripcion`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Sistema de Gestión de Inventarios Web', 'PF-ADSO-01', 'Desarrollar un sistema de información web que permita gestionar el inventario de una empresa mediana', 'Proyecto integrador que abarca análisis, diseño, desarrollo, pruebas e implantación de un sistema web', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 'Plataforma Multimedia Educativa', 'PF-MM-01', 'Crear una plataforma de contenidos multimediales educativos interactivos', 'Diseño y producción de contenidos multimediales para educación virtual', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 'Sistema Contable para Microempresas', 'PF-CONT-01', 'Implementar un sistema de contabilización para microempresas colombianas', 'Registro, procesamiento y presentación de información contable y tributaria', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 'Plan Logístico de Distribución Regional', 'PF-LOG-01', 'Diseñar un plan logístico integral de distribución para una empresa regional', 'Coordinación de procesos logísticos, almacenamiento y control de inventarios', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resultados_aprendizaje`
--

CREATE TABLE `resultados_aprendizaje` (
  `id` int(11) NOT NULL,
  `competencia_id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `denominacion` text NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `resultados_aprendizaje`
--

INSERT INTO `resultados_aprendizaje` (`id`, `competencia_id`, `codigo`, `denominacion`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 'RA-01-01', 'Interpretar el informe de requerimientos del cliente, estableciendo el alcance del proyecto', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 1, 'RA-01-02', 'Representar el bosquejo de la solución al problema presentado por el cliente, mediante mapas navegacionales', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 1, 'RA-01-03', 'Identificar las necesidades del sistema de información aplicando técnicas de recolección de datos', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 2, 'RA-02-01', 'Elaborar el informe de los resultados del análisis del sistema de información', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, 2, 'RA-02-02', 'Diseñar las bases de datos y el modelo entidad-relación según requerimientos', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(6, 2, 'RA-02-03', 'Construir el prototipo del sistema de información a desarrollar', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(7, 2, 'RA-02-04', 'Elaborar el documento de arquitectura del software del proyecto', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(8, 3, 'RA-03-01', 'Aplicar buenas prácticas de calidad en el proceso de desarrollo de software', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(9, 3, 'RA-03-02', 'Codificar módulos del software de acuerdo con el diseño establecido', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(10, 3, 'RA-03-03', 'Realizar pruebas de software según plan de pruebas definido', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(11, 3, 'RA-03-04', 'Construir la interfaz de usuario de acuerdo con los lineamientos de diseño', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(12, 4, 'RA-04-01', 'Preparar el ambiente de producción para la implantación del sistema', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(13, 4, 'RA-04-02', 'Documentar manuales de usuario y técnicos del sistema de información', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(14, 4, 'RA-04-03', 'Capacitar a los usuarios finales sobre el uso del sistema implementado', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(15, 5, 'RA-05-01', 'Participar en procesos de evaluación de proveedores de tecnología', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(16, 5, 'RA-05-02', 'Elaborar propuestas técnicas para la adquisición de tecnología', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(17, 6, 'RA-06-01', 'Leer textos técnicos en inglés comprendiendo la información relevante', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(18, 6, 'RA-06-02', 'Comunicarse en tareas sencillas y habituales en inglés', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(19, 7, 'RA-07-01', 'Diseñar las piezas multimediales conforme al guion técnico establecido', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(20, 7, 'RA-07-02', 'Elaborar storyboards y guiones para la producción multimedial', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(21, 7, 'RA-07-03', 'Diseñar interfaces gráficas de usuario para productos multimediales', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(22, 8, 'RA-08-01', 'Producir animaciones digitales según especificaciones de diseño', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(23, 8, 'RA-08-02', 'Editar y postproducir material audiovisual para productos multimediales', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(24, 8, 'RA-08-03', 'Integrar los elementos multimediales en una solución funcional', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(25, 9, 'RA-09-01', 'Registrar los hechos económicos de acuerdo con las normas contables', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(26, 9, 'RA-09-02', 'Elaborar los comprobantes de contabilidad según normativa vigente', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(27, 9, 'RA-09-03', 'Clasificar los documentos contables de soporte según su naturaleza', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(28, 10, 'RA-10-01', 'Interpretar los estados financieros de acuerdo con las normas internacionales', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(29, 10, 'RA-10-02', 'Calcular indicadores financieros para la toma de decisiones', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(30, 10, 'RA-10-03', 'Elaborar informes de análisis contable con recomendaciones', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(31, 11, 'RA-11-01', 'Presentar la información contable ante entidades de control según normativa', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(32, 11, 'RA-11-02', 'Preparar declaraciones tributarias según legislación colombiana', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(33, 12, 'RA-12-01', 'Planear los procesos de la cadena logística según normativa', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(34, 12, 'RA-12-02', 'Coordinar el transporte según tipo de producto y normativa', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(35, 12, 'RA-12-03', 'Aplicar normas de seguridad en operaciones logísticas', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(36, 13, 'RA-13-01', 'Organizar productos en el almacén según sus características', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(37, 13, 'RA-13-02', 'Controlar condiciones de almacenamiento según tipo de producto', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(38, 14, 'RA-14-01', 'Verificar las entradas y salidas de inventario según documentos', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(39, 14, 'RA-14-02', 'Realizar inventarios físicos y ajustes según procedimientos', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(40, 14, 'RA-14-03', 'Generar reportes de movimientos de inventario', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retroalimentacion`
--

CREATE TABLE `retroalimentacion` (
  `id` int(11) NOT NULL,
  `evaluacion_id` int(11) DEFAULT NULL,
  `aprendiz_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `tipo` enum('fortaleza','aspecto_mejorar','recomendacion') DEFAULT 'aspecto_mejorar',
  `contenido` text NOT NULL,
  `privada` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `rol` enum('coordinador','instructor','aprendiz') NOT NULL,
  `avatar_color` varchar(7) DEFAULT '#39A900',
  `estado` enum('activo','inactivo','bloqueado') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `password`, `nombre`, `rol`, `avatar_color`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'coordinador@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Carlos Andrés Martínez', 'coordinador', '#39A900', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(2, 'instructor@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Fernanda López', 'instructor', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(3, 'instructor2@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Jorge Salas', 'instructor', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(4, 'instructor3@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Diana Cruz', 'instructor', '#EC4899', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(5, 'instructor4@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Roberto Gómez', 'instructor', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(6, 'instructor5@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Ana Torres', 'instructor', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(7, 'aprendiz@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan David Ramírez', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(8, 'aprendiz2@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura Camila Vargas', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(9, 'aprendiz3@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Pedro Nel Patiño', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(10, 'aprendiz4@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía Vergara', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(11, 'aprendiz5@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Andrés Felipe Mendieta', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(12, 'aprendiz6_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Ana López Martínez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(13, 'aprendiz7_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Pérez Moreno', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(14, 'aprendiz8_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía Vargas Moreno', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(15, 'aprendiz9_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mateo Torres García', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(16, 'aprendiz10_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Diego Moreno Martínez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(17, 'aprendiz11_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro García González', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(18, 'aprendiz12_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valentina Díaz Pérez', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(19, 'aprendiz13_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Rojas Díaz', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(20, 'aprendiz14_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura García Sánchez', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(21, 'aprendiz15_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan Martínez Rojas', 'aprendiz', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(22, 'aprendiz16_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Ana Cruz Torres', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(23, 'aprendiz17_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valentina López Torres', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(24, 'aprendiz18_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Moreno Martínez', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(25, 'aprendiz19_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura Moreno Rojas', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(26, 'aprendiz20_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Martínez Martínez', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(27, 'aprendiz21_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valeria López Pérez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(28, 'aprendiz22_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Daniela Ramírez González', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(29, 'aprendiz23_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura García Rojas', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(30, 'aprendiz24_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía Martínez Pérez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(31, 'aprendiz25_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila González Cruz', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(32, 'aprendiz26_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valentina García Martínez', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(33, 'aprendiz27_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Luis Rojas Martínez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(34, 'aprendiz28_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Diego Cruz Torres', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(35, 'aprendiz29_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Ana García López', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(36, 'aprendiz30_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valeria Pérez Moreno', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(37, 'aprendiz31_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Santiago Vargas Sánchez', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(38, 'aprendiz32_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Ana González Pérez', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(39, 'aprendiz33_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro López Martínez', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(40, 'aprendiz34_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valentina Rojas González', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(41, 'aprendiz35_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura Rojas Sánchez', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(42, 'aprendiz36_f2845671@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Torres Gómez', 'aprendiz', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(43, 'aprendiz37_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sebastián Moreno Vargas', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(44, 'aprendiz38_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Luis Díaz Martínez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(45, 'aprendiz39_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Luis González Torres', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(46, 'aprendiz40_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sebastián Gómez Cruz', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(47, 'aprendiz41_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía Moreno López', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(48, 'aprendiz42_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mateo Díaz Moreno', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(49, 'aprendiz43_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Rodríguez Rojas', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(50, 'aprendiz44_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Ramírez Cruz', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(51, 'aprendiz45_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Isabella García García', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(52, 'aprendiz46_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Carlos Pérez Vargas', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(53, 'aprendiz47_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Díaz Ramírez', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(54, 'aprendiz48_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Diego Rojas Cruz', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(55, 'aprendiz49_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro Ramírez Vargas', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(56, 'aprendiz50_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Ramírez Ramírez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(57, 'aprendiz51_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Andrés Gómez González', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(58, 'aprendiz52_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valentina Pérez Gómez', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(59, 'aprendiz53_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valentina Sánchez Ramírez', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(60, 'aprendiz54_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro González Pérez', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(61, 'aprendiz55_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Rojas Sánchez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(62, 'aprendiz56_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Carlos Rodríguez González', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(63, 'aprendiz57_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía García Cruz', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(64, 'aprendiz58_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Carlos Cruz González', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(65, 'aprendiz59_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan Gómez Díaz', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(66, 'aprendiz60_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila López Moreno', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(67, 'aprendiz61_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Isabella Rojas Gómez', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(68, 'aprendiz62_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Pedro Vargas Pérez', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(69, 'aprendiz63_f2867812@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mateo Rodríguez García', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(70, 'aprendiz64_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan Pérez Rojas', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(71, 'aprendiz65_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Ana Sánchez González', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(72, 'aprendiz66_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Pedro Vargas Moreno', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(73, 'aprendiz67_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Rodríguez Pérez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(74, 'aprendiz68_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sebastián López López', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(75, 'aprendiz69_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Carlos Rojas Ramírez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(76, 'aprendiz70_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Andrés García Gómez', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(77, 'aprendiz71_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Ramírez Díaz', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(78, 'aprendiz72_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana García Torres', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(79, 'aprendiz73_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Luis Gómez Cruz', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(80, 'aprendiz74_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Ramírez Martínez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(81, 'aprendiz75_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Martínez González', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(82, 'aprendiz76_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Santiago Pérez Sánchez', 'aprendiz', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(83, 'aprendiz77_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Gómez González', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(84, 'aprendiz78_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Daniela Rodríguez López', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(85, 'aprendiz79_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mateo Vargas Sánchez', 'aprendiz', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(86, 'aprendiz80_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valeria Gómez González', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(87, 'aprendiz81_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan García Rojas', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(88, 'aprendiz82_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura Rojas Gómez', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(89, 'aprendiz83_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Daniela Vargas Pérez', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(90, 'aprendiz84_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Torres Ramírez', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(91, 'aprendiz85_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'María Ramírez Rojas', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(92, 'aprendiz86_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía Ramírez Vargas', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(93, 'aprendiz87_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valeria Vargas García', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(94, 'aprendiz88_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan Gómez Gómez', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(95, 'aprendiz89_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Sánchez Díaz', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(96, 'aprendiz90_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan Díaz López', 'aprendiz', '#3B82F6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(97, 'aprendiz91_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Carlos Rodríguez López', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(98, 'aprendiz92_f2901234@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Daniela Díaz Gómez', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(99, 'aprendiz93_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Daniela Gómez Cruz', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(100, 'aprendiz94_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Isabella Gómez Ramírez', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(101, 'aprendiz95_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan González Torres', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(102, 'aprendiz96_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila González García', 'aprendiz', '#06B6D4', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(103, 'aprendiz97_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Pérez García', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(104, 'aprendiz98_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mateo Pérez Vargas', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(105, 'aprendiz99_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mateo Cruz Díaz', 'aprendiz', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(106, 'aprendiz100_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana González Vargas', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(107, 'aprendiz101_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Valeria Gómez Torres', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(108, 'aprendiz102_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Luis Rodríguez Rodríguez', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(109, 'aprendiz103_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Isabella Rojas Sánchez', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(110, 'aprendiz104_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Isabella López Sánchez', 'aprendiz', '#F59E0B', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(111, 'aprendiz105_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Sofía García Sánchez', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(112, 'aprendiz106_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Pedro Martínez Sánchez', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(113, 'aprendiz107_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro Gómez Gómez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(114, 'aprendiz108_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro Martínez Ramírez', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(115, 'aprendiz109_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro Vargas Ramírez', 'aprendiz', '#F43F5E', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(116, 'aprendiz110_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Camila Sánchez López', 'aprendiz', '#10B981', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(117, 'aprendiz111_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Mariana Rojas Sánchez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(118, 'aprendiz112_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Andrés Gómez Torres', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(119, 'aprendiz113_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Alejandro López González', 'aprendiz', '#84CC16', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(120, 'aprendiz114_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Pedro González García', 'aprendiz', '#8B5CF6', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(121, 'aprendiz115_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Daniela Rojas Moreno', 'aprendiz', '#EF4444', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(122, 'aprendiz116_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Juan Sánchez Martínez', 'aprendiz', '#EAB308', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23'),
(123, 'aprendiz117_f2823456@sena.edu.co', '$2y$10$ZY7FXytHZLDshfBfCubWDeWpgt8443/HnHGfr2TMNsDjohy9ggyFa', 'Laura Martínez Rojas', 'aprendiz', '#D946EF', 'activo', '2026-05-21 03:44:23', '2026-05-21 03:44:23');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competencia_id` (`competencia_id`),
  ADD KEY `responsable_id` (`responsable_id`),
  ADD KEY `idx_ficha` (`ficha_id`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `aprendices`
--
ALTER TABLE `aprendices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_documento` (`numero_documento`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_documento` (`numero_documento`),
  ADD KEY `idx_ficha` (`ficha_id`);

--
-- Indices de la tabla `competencias`
--
ALTER TABLE `competencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_programa` (`programa_id`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_eval` (`resultado_aprendizaje_id`,`aprendiz_id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `idx_ra` (`resultado_aprendizaje_id`),
  ADD KEY `idx_aprendiz` (`aprendiz_id`),
  ADD KEY `idx_concepto` (`concepto`),
  ADD KEY `idx_ficha` (`ficha_id`);

--
-- Indices de la tabla `evidencias`
--
ALTER TABLE `evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluacion_id` (`evaluacion_id`),
  ADD KEY `idx_aprendiz` (`aprendiz_id`),
  ADD KEY `idx_ficha` (`ficha_id`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `fases_proyecto`
--
ALTER TABLE `fases_proyecto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_fase_proyecto` (`proyecto_id`,`numero_fase`),
  ADD KEY `idx_proyecto` (`proyecto_id`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `fichas`
--
ALTER TABLE `fichas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_ficha` (`numero_ficha`),
  ADD KEY `coordinador_id` (`coordinador_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_instructor` (`instructor_id`),
  ADD KEY `idx_programa` (`programa_id`),
  ADD KEY `idx_proyecto` (`proyecto_id`);

--
-- Indices de la tabla `historial_evaluaciones`
--
ALTER TABLE `historial_evaluaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_evaluacion` (`evaluacion_id`),
  ADD KEY `idx_fecha` (`fecha_cambio`);

--
-- Indices de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_accion` (`accion`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_leida` (`leida`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_expira` (`expira_en`);

--
-- Indices de la tabla `programas`
--
ALTER TABLE `programas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_codigo` (`codigo`);

--
-- Indices de la tabla `proyectos`
--
ALTER TABLE `proyectos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `resultados_aprendizaje`
--
ALTER TABLE `resultados_aprendizaje`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_competencia` (`competencia_id`),
  ADD KEY `idx_codigo` (`codigo`);

--
-- Indices de la tabla `retroalimentacion`
--
ALTER TABLE `retroalimentacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluacion_id` (`evaluacion_id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `idx_aprendiz` (`aprendiz_id`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_rol` (`rol`),
  ADD KEY `idx_estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `aprendices`
--
ALTER TABLE `aprendices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT de la tabla `competencias`
--
ALTER TABLE `competencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=945;

--
-- AUTO_INCREMENT de la tabla `evidencias`
--
ALTER TABLE `evidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fases_proyecto`
--
ALTER TABLE `fases_proyecto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `fichas`
--
ALTER TABLE `fichas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_evaluaciones`
--
ALTER TABLE `historial_evaluaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `programas`
--
ALTER TABLE `programas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `proyectos`
--
ALTER TABLE `proyectos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `resultados_aprendizaje`
--
ALTER TABLE `resultados_aprendizaje`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `retroalimentacion`
--
ALTER TABLE `retroalimentacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  ADD CONSTRAINT `actividades_ibfk_2` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`),
  ADD CONSTRAINT `actividades_ibfk_3` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `aprendices`
--
ALTER TABLE `aprendices`
  ADD CONSTRAINT `aprendices_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `aprendices_ibfk_2` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`);

--
-- Filtros para la tabla `competencias`
--
ALTER TABLE `competencias`
  ADD CONSTRAINT `competencias_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD CONSTRAINT `evaluaciones_ibfk_1` FOREIGN KEY (`resultado_aprendizaje_id`) REFERENCES `resultados_aprendizaje` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluaciones_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  ADD CONSTRAINT `evaluaciones_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `evaluaciones_ibfk_4` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`);

--
-- Filtros para la tabla `evidencias`
--
ALTER TABLE `evidencias`
  ADD CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  ADD CONSTRAINT `evidencias_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  ADD CONSTRAINT `evidencias_ibfk_3` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`);

--
-- Filtros para la tabla `fases_proyecto`
--
ALTER TABLE `fases_proyecto`
  ADD CONSTRAINT `fases_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fichas`
--
ALTER TABLE `fichas`
  ADD CONSTRAINT `fichas_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  ADD CONSTRAINT `fichas_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fichas_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fichas_ibfk_4` FOREIGN KEY (`coordinador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial_evaluaciones`
--
ALTER TABLE `historial_evaluaciones`
  ADD CONSTRAINT `historial_evaluaciones_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_evaluaciones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `resultados_aprendizaje`
--
ALTER TABLE `resultados_aprendizaje`
  ADD CONSTRAINT `resultados_aprendizaje_ibfk_1` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `retroalimentacion`
--
ALTER TABLE `retroalimentacion`
  ADD CONSTRAINT `retroalimentacion_ibfk_1` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  ADD CONSTRAINT `retroalimentacion_ibfk_2` FOREIGN KEY (`aprendiz_id`) REFERENCES `aprendices` (`id`),
  ADD CONSTRAINT `retroalimentacion_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
