-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-09-2026 a las 06:51:43
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `db_partner`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL,
  `empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `imagen`, `estado`, `fecha_creacion`, `fecha_actualizacion`, `empresa_id`, `usuario_id`) VALUES
(1, 'aceites', 'Clasificados según su viscosidad y tipo', '1772552045_ACEITES.png', 1, '2026-03-07 20:32:19', '2026-05-13 21:58:00', 2, 2),
(2, 'filtro', 'Se clasifican según su función, como filtro de aceite', '1772072827_FILTROS.png', 1, '2026-03-07 20:32:19', '2026-05-13 21:57:48', 2, 2),
(3, 'Bandas', 'Componentes de transmisión que permiten el movimiento eficiente', '1772072837_BANDAS.png', 1, '2026-03-07 20:32:19', '2026-05-13 21:57:40', 2, 2),
(4, 'Guayas', 'Cables de control que transmiten el movimiento del acelerador', '1772072850_GUAYAS.png', 1, '2026-03-07 20:32:19', '2026-05-13 21:57:21', 2, 2),
(5, 'llantas', 'Clasificación de llantas según su uso, tamaño y tipo de superficie', 'categoria_20260518_024712_f7eafe20.png', 1, '2026-03-07 20:32:19', '2026-05-17 19:47:12', 2, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalles_orden`
--

CREATE TABLE `detalles_orden` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL COMMENT 'Precio al momento de la venta',
  `subtotal` decimal(10,2) NOT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_temp`
--

CREATE TABLE `detalle_temp` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL COMMENT 'Identificador de sesión o cookie (para invitados)',
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Si está logueado',
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `fecha_agregado` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_temp`
--

INSERT INTO `detalle_temp` (`id`, `session_id`, `usuario_id`, `producto_id`, `cantidad`, `precio_unitario`, `fecha_agregado`, `empresa_id`) VALUES
(955, 'a2orub3v6u7aujauhisaf969jm', 2, 6, 1, 54000.00, '2026-03-10 17:52:59', 2),
(956, 'a2orub3v6u7aujauhisaf969jm', 2, 4, 1, 24000.00, '2026-03-10 17:52:59', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo_electronico` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `tipo_empresa_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` tinyint(4) DEFAULT 1,
  `configuracion_inicial_completada` tinyint(1) NOT NULL DEFAULT 0,
  `imagen` varchar(255) DEFAULT NULL,
  `usuario_creador_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `nombre`, `nit`, `direccion`, `telefono`, `correo_electronico`, `email`, `tipo_empresa_id`, `fecha_registro`, `estado`, `configuracion_inicial_completada`, `imagen`, `usuario_creador_id`) VALUES
(2, 'taller de mecanica', NULL, 'Colombia', '3182410777', NULL, NULL, 1, '2026-03-07 19:10:04', 1, 0, 'Assets/images/Empresas/empresa_2_20260513_192142_0767b2cd.png', NULL),
(3, 'kk', '414', 'kdx 618 - 320', '5161631651', 'deivids8ortega@gmail.com', NULL, 2, '2026-03-08 01:36:53', 1, 0, 'Assets/images/Empresas/empresa_20260307_235615_3d3553ca.png', 1),
(4, 'Empresa L L', NULL, NULL, '4444444444', 'l@gmail.com', NULL, 3, '2026-03-08 08:44:29', 1, 0, NULL, 1),
(6, 'dd', '123456', 'kdx 13-23', '5555555555', 'j@gmail.com', NULL, 3, '2026-03-08 08:45:00', 1, 1, 'Assets/images/Empresas/empresa_6_20260310101355_091c9642.png', 1),
(7, 'Empresa E E', NULL, NULL, '7777777777', 'e@gmail.com', NULL, 3, '2026-03-08 08:45:31', 1, 0, NULL, 1),
(8, 'Empresa T T', NULL, NULL, '9999999999', 't@gmail.com', NULL, 2, '2026-03-08 08:46:38', 1, 0, NULL, 1),
(10, 'Empresa O O', NULL, NULL, '7777777788', 'o@gmail.com', NULL, 1, '2026-03-08 08:47:16', 1, 0, NULL, 1),
(12, 'Empresa A A', NULL, NULL, '1111111111', 'a@gmail.com', NULL, 4, '2026-03-08 08:49:59', 1, 0, NULL, 1),
(13, 'Empresa B B', NULL, NULL, '2222222222', 'b@gmail.com', NULL, 2, '2026-03-08 08:50:31', 1, 0, NULL, 1),
(14, 'Empresa M M', NULL, NULL, '4141444444', 'm@gmail.com', NULL, 1, '2026-03-08 08:51:12', 1, 0, 'Assets/images/Empresas/empresa_14_20260310110046_4392ffab.png', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entradas_inventario`
--

CREATE TABLE `entradas_inventario` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `fecha_entrada` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `ruta` varchar(255) DEFAULT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id`, `nombre`, `ruta`, `icono`, `descripcion`, `estado`, `created_at`) VALUES
(1, 'Inicio', NULL, NULL, NULL, 1, '2025-03-15 21:55:52'),
(2, 'Tienda', NULL, NULL, NULL, 1, '2025-03-15 21:55:52'),
(3, 'inventario', NULL, NULL, NULL, 1, '2025-03-15 21:55:52'),
(4, 'Roles', NULL, NULL, NULL, 1, '2025-03-15 21:55:52'),
(5, 'Módulos', NULL, NULL, NULL, 1, '2025-03-15 21:55:52'),
(6, 'Productos', NULL, NULL, NULL, 1, '2025-03-15 22:17:47'),
(7, 'Categorias', NULL, NULL, NULL, 1, '2025-03-15 22:23:30'),
(8, 'Usuarios', NULL, NULL, NULL, 1, '2025-03-23 01:37:24'),
(9, 'ordenes_taller', NULL, NULL, NULL, 1, '2026-02-18 03:00:34'),
(10, 'Módulos_Tipos_de_Empresa', NULL, NULL, NULL, 1, '2026-03-04 03:11:37'),
(11, 'empresas', NULL, NULL, NULL, 1, '2026-03-05 01:47:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `total_movimiento` decimal(10,2) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL COMMENT 'ID de entrada o salida',
  `orden_taller_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_taller`
--

CREATE TABLE `ordenes_taller` (
  `id` int(11) NOT NULL,
  `cliente_nombre` varchar(255) NOT NULL,
  `estado` enum('activa','pendiente','finalizada','cancelada') DEFAULT 'activa',
  `total` decimal(10,2) DEFAULT 0.00,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_finalizacion` datetime DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `pagada` tinyint(1) NOT NULL DEFAULT 0,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido`
--

CREATE TABLE `pedido` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL COMMENT 'Código legible ej: PED-2026-0001',
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Cliente que hizo el pedido (NULL si venta rápida o invitado)',
  `tipo_pago_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `costo_envio` decimal(10,2) DEFAULT 0.00,
  `total_final` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'total - descuento + envio',
  `estado` enum('pendiente','pagado','enviado','entregado','cancelado','devuelto') DEFAULT 'pendiente',
  `direccion_envio` text DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_roles`
--

CREATE TABLE `permisos_roles` (
  `id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `ver` tinyint(1) DEFAULT 0,
  `crear` tinyint(1) DEFAULT 0,
  `actualizar` tinyint(1) DEFAULT 0,
  `eliminar` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos_roles`
--

INSERT INTO `permisos_roles` (`id`, `rol_id`, `modulo`, `ver`, `crear`, `actualizar`, `eliminar`, `created_at`, `empresa_id`) VALUES
(445, 2, 'Inicio', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(446, 2, 'Tienda', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(447, 2, 'inventario', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(448, 2, 'Roles', 0, 0, 0, 0, '2026-03-10 13:56:10', 2),
(449, 2, 'Módulos', 0, 0, 0, 0, '2026-03-10 13:56:10', 2),
(450, 2, 'Productos', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(451, 2, 'Categorias', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(452, 2, 'Usuarios', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(453, 2, 'ordenes_taller', 1, 1, 1, 1, '2026-03-10 13:56:10', 2),
(454, 2, 'Módulos_Tipos_de_Empresa', 0, 0, 0, 0, '2026-03-10 13:56:10', 2),
(455, 2, 'empresas', 0, 0, 0, 0, '2026-03-10 13:56:10', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `codigo_barras` varchar(50) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `descuento_ganacia` decimal(5,2) NOT NULL DEFAULT 0.00,
  `precio_original` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `imagen` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo`, `codigo_barras`, `categoria_id`, `nombre`, `descripcion`, `precio`, `descuento_ganacia`, `precio_original`, `stock`, `imagen`, `estado`, `fecha_creacion`, `empresa_id`, `usuario_id`) VALUES
(1, 'AC01', '', 1, 'Aceite Repsol', 'Lubricante de alta calidad de Repsol', 26000.00, 0.00, 26000.00, 10, '1771774870_REPSOL.png', 1, '2026-03-07 20:32:19', 2, 2),
(2, 'AC02', '', 1, 'Aceite Yamalube', 'Lubricante original de Yamaha', 26000.00, 0.00, 26000.00, 12, '1771774942_YAMALUBE.png', 1, '2026-03-07 20:32:19', 2, 2),
(3, 'FI01', '', 2, 'Filtros FZ16', 'Filtros diseñados para la Yamaha FZ16', 0.00, 0.00, NULL, 0, '1771775054_FILTRO DE ACEITE FZ16.png', 1, '2026-03-07 20:32:19', 2, 2),
(4, 'FI02', '', 2, 'Filtro de aceite GN125', 'Filtro de aceite diseñado para la Suzuki', 0.00, 0.00, NULL, 0, '1771775588_FILTRO ACEITE GN 125.png', 1, '2026-03-07 20:32:19', 2, 2),
(5, 'BA01', '', 3, 'Bandas Kross', 'Bandas de transmisión originales de la marca Kross.', 0.00, 0.00, NULL, 0, '1771776128_Bandas Kross.png', 1, '2026-03-07 20:32:19', 2, 2),
(6, 'BA02', '', 3, 'Bandas Yamaha', 'Bandas de transmisión originales de Yamaha.', 0.00, 0.00, NULL, 0, '1771776187_Bandas Yamaha.png', 1, '2026-03-07 20:32:19', 2, 2),
(7, 'GU01', '', 4, 'Guayas FZ16', 'Guayas diseñadas para la Yamaha FZ16.', 0.00, 0.00, NULL, 0, '1771776633_guallas fz16.png', 1, '2026-03-07 20:32:19', 2, 2),
(8, 'GU02', '', 4, 'Guaya GN', 'Guayas diseñadas para la Suzuki GN125.', 0.00, 0.00, NULL, 0, 'producto_20260514_022732_536e328e.png', 1, '2026-03-07 20:32:19', 2, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `estado`, `created_at`, `updated_at`, `empresa_id`) VALUES
(1, 'Super Administrador', 'Tiene el nivel mas alto de acceso dentro del sistema. Controla configuraciones generales, usuarios y permisos en toda la plataforma.', 1, '2026-03-07 19:14:54', '2026-05-14 00:43:16', 2),
(2, 'Administrador', 'Gestiona usuarios, contenidos y configuraciones del sistema. Supervisa las operaciones diarias y garantiza su correcto funcionamiento.', 1, '2026-03-07 19:14:54', '2026-05-14 00:43:55', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salidas_inventario`
--

CREATE TABLE `salidas_inventario` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo_salida` enum('venta','dañado','perdida','ajuste') DEFAULT 'venta',
  `fecha_salida` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `precio_venta_unitario` decimal(10,2) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `ganancia_unitaria` decimal(10,2) DEFAULT NULL,
  `porcentaje_ganancia` decimal(10,2) DEFAULT NULL,
  `total_venta` decimal(12,2) DEFAULT NULL,
  `total_ganancia` decimal(12,2) DEFAULT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipopago`
--

CREATE TABLE `tipopago` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Ej: Efectivo, Transferencia, Nequi, Tarjeta, Contraentrega',
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `documento` varchar(255) NOT NULL,
  `tipo_documento` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 0,
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `token_recuperacion_contrasena` varchar(255) DEFAULT NULL,
  `fecha_expiracion_token_contrasena` datetime DEFAULT NULL,
  `token_recuperacion_correo` varchar(255) DEFAULT NULL,
  `fecha_expiracion_token_correo` datetime DEFAULT NULL,
  `ultima_actividad` datetime DEFAULT NULL,
  `requiere_cambio_contrasena` tinyint(1) NOT NULL DEFAULT 0,
  `empresa_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `id_tipos_empresa` int(11) DEFAULT NULL,
  `requiere_configuracion_empresa` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `correo`, `telefono`, `documento`, `tipo_documento`, `contrasena`, `rol`, `estado`, `intentos_fallidos`, `bloqueado_hasta`, `token_recuperacion_contrasena`, `fecha_expiracion_token_contrasena`, `token_recuperacion_correo`, `fecha_expiracion_token_correo`, `ultima_actividad`, `requiere_cambio_contrasena`, `empresa_id`, `admin_id`, `id_tipos_empresa`, `requiere_configuracion_empresa`) VALUES
(1, 'juan josé', 'pacheco prado', 'l2c5++uhqbRDm/JE4Q30UVFrTXpyd3J5MloybHdTbGloRjVmdFdLL2dkYjNScjI5WXRYNktDT29aSVE9', '3182140886', 'lH+Xb9PwP2IAQBRWe3hSgEtBT3NaRTBocXJlcHRUWVRnc25NbHc9PQ==', 'Cédula de Ciudadanía', '$2y$10$7Y5LSQOZpOwbYbghedVJMODjLo.FHwB16wZScivy0K4p8d/KUCgzG', 'Super Administrador', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-05-23 19:51:38', 0, NULL, NULL, NULL, 0),
(2, 'gabriel', 'bayona', '7hEapNnFKyHmTuf/5vuXTzIwQlU5b2RFeDdKTmxDSytzd1NnMUlnOVlVM3NMeFdZb2lFek4wWC9yY3c9', '3182410777', 'enIwrwALmGgEAJo5SrWxsDBmb0RMVjRVTjFhaDFuTzIwSEJ6bnc9PQ==', 'Cédula de Ciudadanía', '$2y$10$kEESPwNm5QpslkIHDp6ooO3vor7Wv4cuiF1JNCuyGYdQcc3PsV9r2', 'Administrador', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-05-24 10:24:10', 0, 2, NULL, 2, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `empresa_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_envio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `fecha_venta` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `pedido_id`, `empresa_id`, `cliente_id`, `usuario_id`, `subtotal`, `descuento`, `costo_envio`, `total`, `estado`, `fecha_venta`, `created_at`, `updated_at`) VALUES
(1, 2, 1, NULL, 1, 54000.00, 0.00, 0.00, 54000.00, 'pendiente', '2026-02-27 20:28:36', '2026-03-07 17:15:35', NULL),
(2, 3, 1, NULL, 1, 36000.00, 0.00, 0.00, 36000.00, 'pendiente', '2026-02-27 20:35:43', '2026-03-07 17:15:35', NULL),
(3, 4, 1, NULL, 1, 36000.00, 0.00, 0.00, 36000.00, 'pendiente', '2026-02-27 21:07:29', '2026-03-07 17:15:35', NULL),
(4, 5, 1, NULL, 1, 36000.00, 0.00, 0.00, 36000.00, 'pendiente', '2026-02-27 21:34:16', '2026-03-07 17:15:35', NULL),
(5, 6, 1, NULL, 1, 36000.00, 0.00, 0.00, 36000.00, 'pendiente', '2026-02-27 22:16:22', '2026-03-07 17:15:35', NULL),
(6, 7, 1, NULL, 1, 18000.00, 0.00, 0.00, 18000.00, 'pagado', '2026-02-27 22:30:50', '2026-03-07 17:15:35', NULL),
(7, 8, 1, NULL, 1, 6000.00, 0.00, 0.00, 6000.00, 'pagado', '2026-02-27 22:35:07', '2026-03-07 17:15:35', NULL),
(8, 9, 1, NULL, 1, 48000.00, 0.00, 0.00, 48000.00, 'pendiente', '2026-03-01 00:06:39', '2026-03-07 17:15:35', NULL),
(9, 10, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:17:10', '2026-03-07 17:15:35', NULL),
(10, 11, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:17:27', '2026-03-07 17:15:35', NULL),
(11, 12, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:22:48', '2026-03-07 17:15:35', NULL),
(12, 13, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:24:34', '2026-03-07 17:15:35', NULL),
(13, 14, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:26:57', '2026-03-07 17:15:35', NULL),
(14, 15, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:37:44', '2026-03-07 17:15:35', NULL),
(15, 16, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:38:26', '2026-03-07 17:15:35', NULL),
(16, 17, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:38:53', '2026-03-07 17:15:35', NULL),
(17, 18, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 20:40:21', '2026-03-07 17:15:35', NULL),
(18, 19, 1, NULL, 1, 500.00, 0.00, 0.00, 500.00, 'pendiente', '2026-03-02 21:55:45', '2026-03-07 17:15:35', NULL),
(19, 20, 1, NULL, 1, 24000.00, 0.00, 0.00, 24000.00, 'pendiente', '2026-03-03 09:54:26', '2026-03-07 17:15:35', NULL),
(20, 21, 1, NULL, 1, 252500.00, 0.00, 0.00, 252500.00, 'pendiente', '2026-03-03 19:40:57', '2026-03-07 17:15:35', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_categorias_usuario_id` (`usuario_id`),
  ADD KEY `idx_categorias_empresa_usuario` (`empresa_id`,`usuario_id`);

--
-- Indices de la tabla `detalles_orden`
--
ALTER TABLE `detalles_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_id` (`orden_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`);

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_detalle_pedido_pedido_empresa` (`pedido_id`,`empresa_id`);

--
-- Indices de la tabla `detalle_temp`
--
ALTER TABLE `detalle_temp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_detalle_temp_usuario_empresa` (`usuario_id`,`empresa_id`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_empresas_tipo` (`tipo_empresa_id`),
  ADD KEY `idx_empresas_usuario_creador_id` (`usuario_creador_id`);

--
-- Indices de la tabla `entradas_inventario`
--
ALTER TABLE `entradas_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_entradas_usuario_id` (`usuario_id`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `tipo_movimiento` (`tipo_movimiento`),
  ADD KEY `fecha_movimiento` (`fecha_movimiento`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_movimientos_usuario_id` (`usuario_id`);

--
-- Indices de la tabla `ordenes_taller`
--
ALTER TABLE `ordenes_taller`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_ordenes_usuario` (`usuario_id`),
  ADD KEY `idx_ordenes_empresa_estado_fecha` (`empresa_id`,`estado`,`fecha_creacion`);

--
-- Indices de la tabla `pedido`
--
ALTER TABLE `pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `tipo_pago_id` (`tipo_pago_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_pedido_usuario_id` (`usuario_id`),
  ADD KEY `idx_pedido_empresa_usuario` (`empresa_id`,`usuario_id`),
  ADD KEY `idx_pedido_usuario_empresa` (`usuario_id`,`empresa_id`);

--
-- Indices de la tabla `permisos_roles`
--
ALTER TABLE `permisos_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rol_id` (`rol_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `idx_codigo_barras` (`codigo_barras`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_productos_usuario_id` (`usuario_id`),
  ADD KEY `idx_productos_empresa_usuario` (`empresa_id`,`usuario_id`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_proveedores_empresa_id` (`empresa_id`),
  ADD KEY `idx_proveedores_empresa_nombre` (`empresa_id`,`nombre`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_empresa_id` (`empresa_id`);

--
-- Indices de la tabla `salidas_inventario`
--
ALTER TABLE `salidas_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_salidas_usuario_id` (`usuario_id`),
  ADD KEY `idx_salidas_fecha_empresa` (`fecha_salida`,`empresa_id`);

--
-- Indices de la tabla `tipopago`
--
ALTER TABLE `tipopago`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_intentos_fallidos` (`intentos_fallidos`),
  ADD KEY `idx_bloqueado_hasta` (`bloqueado_hasta`),
  ADD KEY `idx_token_recuperacion_contrasena` (`token_recuperacion_contrasena`),
  ADD KEY `idx_fecha_expiracion_token_contrasena` (`fecha_expiracion_token_contrasena`),
  ADD KEY `idx_token_recuperacion_correo` (`token_recuperacion_correo`),
  ADD KEY `idx_fecha_expiracion_token_correo` (`fecha_expiracion_token_correo`),
  ADD KEY `idx_empresa_id` (`empresa_id`),
  ADD KEY `idx_usuarios_id_tipos_empresa` (`id_tipos_empresa`),
  ADD KEY `idx_usuarios_empresa_id` (`empresa_id`),
  ADD KEY `idx_usuarios_admin_id` (`admin_id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ventas_empresa` (`empresa_id`),
  ADD KEY `idx_ventas_cliente` (`cliente_id`),
  ADD KEY `idx_ventas_usuario` (`usuario_id`),
  ADD KEY `idx_ventas_pedido` (`pedido_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `detalles_orden`
--
ALTER TABLE `detalles_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `detalle_temp`
--
ALTER TABLE `detalle_temp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=957;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `entradas_inventario`
--
ALTER TABLE `entradas_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ordenes_taller`
--
ALTER TABLE `ordenes_taller`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido`
--
ALTER TABLE `pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `permisos_roles`
--
ALTER TABLE `permisos_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=478;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `salidas_inventario`
--
ALTER TABLE `salidas_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `fk_categorias_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_categorias_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_categorias_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalles_orden`
--
ALTER TABLE `detalles_orden`
  ADD CONSTRAINT `detalles_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_taller` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalles_orden_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  ADD CONSTRAINT `fk_detalles_orden_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `detalle_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedido` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_pedido_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  ADD CONSTRAINT `fk_detalle_pedido_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_temp`
--
ALTER TABLE `detalle_temp`
  ADD CONSTRAINT `detalle_temp_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_temp_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_detalle_temp_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `entradas_inventario`
--
ALTER TABLE `entradas_inventario`
  ADD CONSTRAINT `entradas_inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  ADD CONSTRAINT `entradas_inventario_ibfk_2` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `entradas_inventario_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_entradas_inventario_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_entradas_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
