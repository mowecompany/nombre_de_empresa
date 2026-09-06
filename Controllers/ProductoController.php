<?php
// cSpell:disable
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Producto.php';
require_once ROOT_PATH . '/Models/Categoria.php';

try {
    $db = Database::connect();
    
    $producto = new Producto($db);

    $empresaIdSesion = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
    $usuarioIdSesion = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
    if ($empresaIdSesion <= 0 && $usuarioIdSesion > 0) {
        try {
            $stmtEmpresa = $db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
            $stmtEmpresa->execute([':id' => $usuarioIdSesion]);
            $empresaIdSesion = (int)($stmtEmpresa->fetchColumn() ?: 0);
            if ($empresaIdSesion > 0) {
                $_SESSION['empresa_id'] = $empresaIdSesion;
            }
        } catch (Exception $e) {
            error_log('ProductoController fallback empresa_id error: ' . $e->getMessage());
        }
    }

    // Manejar peticiones GET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'getAll') {
            // Obtener todos los productos
            $ordenIdExcluir = isset($_GET['orden_id']) && is_numeric($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
            $result = $producto->getAll($ordenIdExcluir);
            if ($result !== null) {
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                throw new Exception('Error al obtener productos');
            }
        } elseif (isset($_GET['action']) && $_GET['action'] === 'getOne' && isset($_GET['id'])) {
            // Obtener un producto por ID
            if (!is_numeric($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }

            $producto->setId($_GET['id']);
            $result = $producto->getOne();
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Producto no encontrado',
                    'empresa_id_busqueda' => $empresaIdSesion,
                    'usuario_id_sesion' => $usuarioIdSesion
                ]);
            }
        } elseif (isset($_GET['action']) && $_GET['action'] === 'obtenerProximoCodigo' && isset($_GET['prefijo'])) {
            // Obtener el próximo código con el prefijo dado
            $prefijo = strtoupper(trim($_GET['prefijo']));
            $prefijo = preg_replace('/[^A-Z0-9]/', '', $prefijo);
            if ($prefijo === '') {
                throw new Exception('Prefijo inválido');
            }
            $proximoCodigo = $producto->obtenerProximoCodigo($prefijo);
            
            if ($proximoCodigo) {
                echo json_encode([
                    'success' => true,
                    'codigo' => $proximoCodigo
                ]);
            } else {
                throw new Exception('Error al generar código');
            }
        } elseif (isset($_GET['action']) && $_GET['action'] === 'verificarCodigoBarrasUnico') {
            $codigo = trim((string)($_GET['codigo_barras'] ?? ''));
            if ($codigo === '') {
                echo json_encode(['success' => true, 'duplicado' => false, 'message' => 'Sin código de barras para validar.']);
                exit;
            }

            $duplicado = $producto->existeCodigoBarras($codigo);
            echo json_encode([
                'success' => true,
                'duplicado' => $duplicado,
                'message' => $duplicado ? 'Este código de barras ya está asociado a un producto.' : 'Código de barras disponible.'
            ]);
            exit;
        } else {
            throw new Exception('Acción no válida o ID no proporcionado');
        }
        exit;
    }

    // Manejar peticiones POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['action'])) {
            throw new Exception('Acción no especificada');
        }

        $parseDecimalInput = function($value): float {
            $str = trim((string)$value);
            if ($str === '') return 0.0;

            $str = str_replace(['$', ' '], '', $str);
            $hasComma = strpos($str, ',') !== false;
            $hasDot = strpos($str, '.') !== false;

            if ($hasComma && $hasDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } elseif ($hasComma) {
                $str = str_replace(',', '.', $str);
            }

            return floatval($str);
        };

        $action = $_POST['action'];
        
        switch($action) {
            case 'crear':
                // Permiso: Administradores pueden crear productos
                
                if (!isset($_POST['nombre']) || !isset($_POST['categoria_id'])) {
                    throw new Exception('Faltan datos requeridos');
                }

                $nombre = trim((string)$_POST['nombre']);
                $descripcion = trim((string)($_POST['descripcion'] ?? ''));
                $precio = $_POST['precio'] ?? 0;  // Por defecto 0
                $stock = $_POST['stock'] ?? 0;    // Por defecto 0
                $categoria_id = (int)$_POST['categoria_id'];
                
                // Validar que la categoría exista
                if ($categoria_id <= 0) {
                    throw new Exception('Debe seleccionar una categoría válida');
                }
                
                $stmtCat = $db->prepare("SELECT id FROM categorias WHERE id = :id LIMIT 1");
                $stmtCat->execute([':id' => $categoria_id]);
                if (!$stmtCat->fetch()) {
                    throw new Exception('La categoría seleccionada no existe');
                }
                
                $codigo = $_POST['codigo'] ?? '';  // Puede ser vacío para generación automática
                try {
                    $codigo_barras = $producto->prepararCodigoBarras($_POST['codigo_barras'] ?? '');
                } catch (InvalidArgumentException $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                    exit;
                }
                // No se permiten colores manuales en la creación del producto.
                // El color se asigna automáticamente en el modelo.
                $colorNombre = null;
                $colorId = null;
                
                $imagen = null;
                $rutaImagenNueva = null;
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                    $imagenInfo = guardarImagenSubidaValidada('imagen', [
                        'label' => 'La imagen del producto',
                        'max_bytes' => 500 * 1024,
                        'allowed_mimes' => ['image/png' => 'png'],
                        'exact_width' => 600,
                        'exact_height' => 1050,
                        'rel_dir' => 'Assets/images/productos',
                        'file_prefix' => 'producto',
                    ]);
                    $imagen = (string)($imagenInfo['file_name'] ?? '');
                    $rutaImagenNueva = (string)($imagenInfo['relative_path'] ?? '');
                }
                
                $resultado = $producto->crear([
                    'codigo' => $codigo,
                    'codigo_barras' => $codigo_barras,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'precio' => $precio,
                    'stock' => $stock,
                    'categoria_id' => $categoria_id,
                    'imagen' => $imagen,
                    'estado' => 1,
                    'color' => null,
                    'color_id' => null,
                    'color_nombre' => null
                ]);
                
                if ($resultado['success']) {
                    $idCreado = (int)$db->lastInsertId();
                    if ($idCreado > 0 && dbColumnExists($db, 'productos', 'venta_por_kilo')) {
                        $stmtKilo = $db->prepare('UPDATE productos SET venta_por_kilo = :venta_por_kilo WHERE id = :id');
                        $stmtKilo->execute([
                            ':venta_por_kilo' => !empty($_POST['venta_por_kilo']) ? 1 : 0,
                            ':id' => $idCreado
                        ]);
                    }
                    $productoCreado = null;
                    if ($idCreado > 0) {
                        $producto->setId($idCreado);
                        $productoCreado = $producto->getOne();
                    }

                    $datosAfter = $productoCreado ? [
                        'id' => $productoCreado->id ?? $idCreado,
                        'codigo' => $productoCreado->codigo ?? ($resultado['codigo'] ?? null),
                        'nombre' => $productoCreado->nombre ?? $nombre,
                        'descripcion' => $productoCreado->descripcion ?? $descripcion,
                        'precio' => $productoCreado->precio ?? $precio,
                        'stock' => $productoCreado->stock ?? $stock,
                        'categoria_id' => $productoCreado->categoria_id ?? $categoria_id,
                        'imagen' => $productoCreado->imagen ?? $imagen,
                        'estado' => $productoCreado->estado ?? 1,
                    ] : [
                        'id' => $idCreado > 0 ? $idCreado : null,
                        'codigo' => $resultado['codigo'] ?? null,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'precio' => $precio,
                        'stock' => $stock,
                        'categoria_id' => $categoria_id,
                        'imagen' => $imagen,
                        'estado' => 1,
                    ];

                    ActualizacionesHelper::registrarCambio(
                        'productos',
                        'crear',
                        null,
                        $datosAfter,
                        ['entidad' => 'producto', 'registro_id' => (string)($idCreado > 0 ? $idCreado : ($resultado['codigo'] ?? ''))]
                    );

                    echo json_encode([
                        'success' => true,
                        'message' => 'PRODUCTO CREADO CORRECTAMENTE',
                        'codigo' => $resultado['codigo']
                    ]);
                } else {
                    if ($rutaImagenNueva !== null) {
                        eliminarArchivoProyectoSiExiste($rutaImagenNueva);
                    }
                    $mensajeError = $resultado['message'] ?? 'Error al crear el producto';
                    echo json_encode([
                        'success' => false,
                        'message' => $mensajeError
                    ]);
                }
                break;

            case 'editar':
                // Permiso: Administradores pueden actualizar productos
                
                if (!isset($_POST['id']) || !isset($_POST['nombre'])) {
                    throw new Exception('Faltan datos requeridos');
                }

                try {
                    $productoAntes = null;
                    $rutaImagenNueva = null;
                    $imagenAnterior = '';

                    // Verificar que solo Super Administrador puede cambiar el ID
                    $idOriginal = $_POST['id_original'] ?? $_POST['id'];
                    $idNuevo = $_POST['id'] ?? $_POST['id'];
                    $rolActual = $_SESSION['rol'] ?? '';
                    
                    if ($idOriginal !== $idNuevo && $rolActual !== 'Super Administrador') {
                        throw new Exception('Solo Super Administrador puede cambiar el ID del producto');
                    }

                    // Si el ID cambió, hacerlo primero
                    if ($idOriginal !== $idNuevo) {
                        try {
                            $producto->cambiarIdProducto($idOriginal, $idNuevo);
                        } catch(Exception $e) {
                            throw new Exception('Error al cambiar el ID: ' . $e->getMessage());
                        }
                    }

                    // Actualizar el producto (nombre, descripción, precio, etc)
                    $producto->setId($idNuevo);
                    $productoAntes = $producto->getOne();
                    $imagenAnterior = trim((string)($productoAntes->imagen ?? ''));
                    $producto->setNombre($_POST['nombre']);
                    $producto->setDescripcion($_POST['descripcion'] ?? '');
                    $colorNombre = trim((string)($_POST['color'] ?? $_POST['color_nombre'] ?? ''));
                    if ($colorNombre !== '') {
                        $producto->setColor($colorNombre);
                    }
                    // permitir actualizar stock y precio
                    if (isset($_POST['stock'])) {
                        $producto->setStock($_POST['stock']);
                    }

                    // Si envían porcentaje de ganancia y tenemos precio_compra, recalcular precio
                    $precioCalculadoDesdePorcentaje = false;
                    if (isset($_POST['porcentaje_ganancia']) && trim((string)$_POST['porcentaje_ganancia']) !== '') {
                        $porc = $parseDecimalInput($_POST['porcentaje_ganancia']);
                        $producto->setPorcentajeGanancia($porc);
                        $pc = 0;
                        if (isset($_POST['precio_compra']) && trim((string)$_POST['precio_compra']) !== '') {
                            $pc = $parseDecimalInput($_POST['precio_compra']);
                        }
                        if ($pc <= 0) {
                            $stmtPc = $db->prepare("SELECT precio_compra FROM entradas_inventario WHERE producto_id = ? ORDER BY fecha_entrada DESC LIMIT 1");
                            $stmtPc->execute([$idNuevo]);
                            $pc = floatval($stmtPc->fetchColumn() ?? 0);
                        }
                        if ($pc > 0) {
                            $nuevoPrecio = $pc + ($pc * ($porc / 100));
                            $producto->setPrecio($nuevoPrecio);
                            $precioCalculadoDesdePorcentaje = true;
                        }
                    }

                    if (isset($_POST['precio']) && !$precioCalculadoDesdePorcentaje) {
                        $producto->setPrecio($parseDecimalInput($_POST['precio']));
                    }

                    $categoriaIdNuevo = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
                    $categoriaAnterior = isset($productoAntes->categoria_id) ? (int)$productoAntes->categoria_id : 0;
                    if ($categoriaIdNuevo <= 0) {
                        throw new Exception('Debe seleccionar una categoría válida');
                    }

                    // Validar existencia de la categoría seleccionada
                    $stmtCat = $db->prepare("SELECT id FROM categorias WHERE id = :id LIMIT 1");
                    $stmtCat->execute([':id' => $categoriaIdNuevo]);
                    if (!$stmtCat->fetch()) {
                        throw new Exception('La categoría seleccionada no existe');
                    }

                    $producto->setCategoriaId($categoriaIdNuevo);
                    if ($categoriaAnterior !== $categoriaIdNuevo) {
                        $nuevoCodigo = $producto->generarCodigo();
                        if (!$nuevoCodigo) {
                            throw new Exception('No se pudo generar el código para la nueva categoría');
                        }
                        $producto->setCodigo($nuevoCodigo);
                    }

                    // Actualizar solo código de barras si se proporciona (NO el código manualmente)
                    if (isset($_POST['codigo_barras'])) {
                        $producto->setCodigoBarras($producto->prepararCodigoBarras($_POST['codigo_barras'], (int)$idNuevo));
                    }

                    // Registrar un movimiento de entrada informativo al editar el producto con datos de stock/precio
                    $stockAnterior = isset($productoAntes->stock) ? (int)$productoAntes->stock : 0;
                    $stockNuevo = isset($_POST['stock']) ? (int)$_POST['stock'] : $stockAnterior;
                    $precioAnterior = isset($productoAntes->precio) ? (float)$productoAntes->precio : 0;
                    $precioNuevo = isset($_POST['precio']) ? $parseDecimalInput($_POST['precio']) : $precioAnterior;
                    $precioCompraEnviado = isset($_POST['precio_compra']) && trim((string)$_POST['precio_compra']) !== '' ? $parseDecimalInput($_POST['precio_compra']) : null;
                    $precioCompraNuevo = $precioCompraEnviado !== null ? $precioCompraEnviado : null;
                    $cambioStock = $stockNuevo - $stockAnterior;
                    $cambioPrecio = $precioNuevo - $precioAnterior;
                    $cambioPrecioCompra = $precioCompraNuevo !== null && $productoAntes !== null ? $precioCompraNuevo - (float)($productoAntes->precio ?? 0) : 0;
                    $debeRegistrarEntradaEdicion = $cambioStock > 0 || ($precioCompraNuevo !== null && $precioCompraNuevo > 0) || abs($cambioPrecio) > 0;

                    if ($debeRegistrarEntradaEdicion) {
                        try {
                            $empresaIdSesion = (int)($_SESSION['empresa_id'] ?? ($_SESSION['userData']['empresa_id'] ?? 0));
                            $usuarioIdSesion = (int)($_SESSION['usuario_id'] ?? ($_SESSION['userData']['idusuario'] ?? ($_SESSION['userData']['id'] ?? 0)));
                            $tieneEmpresa = dbColumnExists($db, 'entradas_inventario', 'empresa_id');
                            $tieneUsuario = dbColumnExists($db, 'entradas_inventario', 'usuario_id');
                            $tieneNotas = dbColumnExists($db, 'entradas_inventario', 'notas');
                            $tieneProveedor = dbColumnExists($db, 'entradas_inventario', 'proveedor_id');

                            $proveedorId = null;
                            if ($tieneProveedor) {
                                $stmtProveedor = $db->prepare("SELECT proveedor_id FROM entradas_inventario WHERE producto_id = ? ORDER BY fecha_entrada DESC LIMIT 1");
                                $stmtProveedor->execute([$idNuevo]);
                                $proveedorId = $stmtProveedor->fetchColumn();
                            }

                            $cantidadEntrada = max(0, $cambioStock);
                            $precioCompraGuardado = $precioCompraNuevo !== null ? $precioCompraNuevo : ($productoAntes->ultimo_precio_compra ?? null);
                            $precioCompraGuardado = $precioCompraGuardado !== null ? (float)$precioCompraGuardado : 0;

                            $columnas = ['producto_id', 'cantidad', 'precio_compra'];
                            $placeholders = [':producto_id', ':cantidad', ':precio_compra'];
                            $paramsEntrada = [
                                ':producto_id' => $idNuevo,
                                ':cantidad' => $cantidadEntrada,
                                ':precio_compra' => $precioCompraGuardado > 0 ? $precioCompraGuardado : 0,
                            ];

                            if ($tieneProveedor) {
                                $columnas[] = 'proveedor_id';
                                $placeholders[] = ':proveedor_id';
                                $paramsEntrada[':proveedor_id'] = $proveedorId ?: null;
                            }

                            if ($tieneUsuario) {
                                $columnas[] = 'usuario_id';
                                $placeholders[] = ':usuario_id';
                                $paramsEntrada[':usuario_id'] = $usuarioIdSesion > 0 ? $usuarioIdSesion : null;
                            }

                            if ($tieneEmpresa) {
                                $columnas[] = 'empresa_id';
                                $placeholders[] = ':empresa_id';
                                $paramsEntrada[':empresa_id'] = $empresaIdSesion > 0 ? $empresaIdSesion : null;
                            }

                            if ($tieneNotas) {
                                $columnas[] = 'notas';
                                $placeholders[] = ':notas';
                                $paramsEntrada[':notas'] = 'Edición de producto | stock anterior: ' . $stockAnterior . ' | stock nuevo: ' . $stockNuevo . ' | precio anterior: ' . number_format($precioAnterior, 2, '.', '') . ' | precio nuevo: ' . number_format($precioNuevo, 2, '.', '') . ' | precio compra: ' . number_format($precioCompraGuardado, 2, '.', '') . ' | proveedor: ' . ($proveedorId ? $proveedorId : 'no especificado');
                            }

                            $sqlEntrada = "INSERT INTO entradas_inventario (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $placeholders) . ")";
                            $stmt = $db->prepare($sqlEntrada);
                            $stmt->execute($paramsEntrada);
                        } catch (Exception $e) {
                            error_log("No se pudo registrar la edición como entrada de inventario: " . $e->getMessage());
                        }
                    }

                    // Manejar la imagen si se subió una nueva
                    $campoImagen = isset($_FILES['imagenEdit']) ? 'imagenEdit' : 'imagen';
                    if (isset($_FILES[$campoImagen]) && $_FILES[$campoImagen]['error'] === 0) {
                        $imagenInfo = guardarImagenSubidaValidada($campoImagen, [
                            'label' => 'La imagen del producto',
                            'max_bytes' => 500 * 1024,
                            'allowed_mimes' => ['image/png' => 'png', 'image/jpeg' => 'jpeg', 'image/jpg' => 'jpg'],
                            'exact_width' => 600,
                            'exact_height' => 1050,
                            'rel_dir' => 'Assets/images/productos',
                            'file_prefix' => 'producto',
                        ]);
                        $rutaImagenNueva = (string)($imagenInfo['relative_path'] ?? '');
                        $producto->setImagen((string)($imagenInfo['file_name'] ?? ''));
                    }

                    if ($producto->update()) {
                        if (dbColumnExists($db, 'productos', 'venta_por_kilo')) {
                            $stmtKilo = $db->prepare('UPDATE productos SET venta_por_kilo = :venta_por_kilo WHERE id = :id');
                            $stmtKilo->execute([
                                ':venta_por_kilo' => !empty($_POST['venta_por_kilo']) ? 1 : 0,
                                ':id' => $idNuevo
                            ]);
                        }
                        $producto->setId($idNuevo);
                        $productoDespues = $producto->getOne();
                        $imagenNueva = trim((string)($productoDespues->imagen ?? ''));
                        if ($rutaImagenNueva !== null && $imagenAnterior !== '' && $imagenAnterior !== $imagenNueva) {
                            eliminarArchivoProyectoSiExiste('Assets/images/productos/' . $imagenAnterior);
                        }
                        ActualizacionesHelper::registrarCambio(
                            'productos',
                            'editar',
                            $productoAntes,
                            $productoDespues,
                            ['entidad' => 'producto', 'registro_id' => (string)$idNuevo]
                        );
                        echo json_encode([
                            'success' => true,
                            'message' => 'Producto actualizado correctamente'
                        ]);
                    } else {
                        if ($rutaImagenNueva !== null) {
                            eliminarArchivoProyectoSiExiste($rutaImagenNueva);
                        }
                        throw new Exception('No se pudo actualizar el producto. Verifique los datos e intente nuevamente.');
                    }
                } catch (Exception $e) {
                    if (!empty($rutaImagenNueva)) {
                        eliminarArchivoProyectoSiExiste($rutaImagenNueva);
                    }
                    error_log("Error en actualización: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'actualizarColor':
                if (!isset($_POST['id']) || !isset($_POST['color'])) {
                    throw new Exception('Faltan datos requeridos para actualizar el color');
                }

                try {
                    $producto->setId($_POST['id']);
                    $productoActual = $producto->getOne();
                    if (!$productoActual) {
                        throw new Exception('Producto no encontrado');
                    }

                    $producto->setNombre($productoActual->nombre ?? '');
                    $producto->setDescripcion($productoActual->descripcion ?? '');
                    $producto->setCategoriaId($productoActual->categoria_id ?? null);
                    $producto->setColor(trim((string)$_POST['color']));

                    if ($producto->update()) {
                        $productoDespues = $producto->getOne();
                        ActualizacionesHelper::registrarCambio(
                            'productos',
                            'actualizar_color',
                            $productoActual,
                            $productoDespues,
                            ['entidad' => 'producto', 'registro_id' => (string)$_POST['id']]
                        );
                        echo json_encode([
                            'success' => true,
                            'message' => 'Color actualizado correctamente'
                        ]);
                    } else {
                        throw new Exception('No se pudo actualizar el color del producto');
                    }
                } catch (Exception $e) {
                    error_log('Error al actualizar color: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar el color: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'cambiarEstado':
                // Permiso: Administradores pueden cambiar estado de productos
                
                if (!isset($_POST['id']) || !isset($_POST['estado'])) {
                    throw new Exception('Faltan datos requeridos');
                }

                try {
                    $producto->setId($_POST['id']);
                    $productoAntes = $producto->getOne();
                    $producto->setEstado($_POST['estado']);
                    
                    if ($producto->updateEstado()) {
                        $productoDespues = $producto->getOne();
                        ActualizacionesHelper::registrarCambio(
                            'productos',
                            'cambiar_estado',
                            $productoAntes,
                            $productoDespues,
                            ['entidad' => 'producto', 'registro_id' => (string)$_POST['id']]
                        );
                        echo json_encode([
                            'success' => true,
                            'message' => 'Estado actualizado correctamente'
                        ]);
                    } else {
                        throw new Exception('Error al actualizar el estado');
                    }
                } catch (Exception $e) {
                    error_log("Error al cambiar estado: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar el estado: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'actualizarDescuento':
                // Permiso: Administradores pueden actualizar productos
                
                if (!isset($_POST['id'])) {
                    throw new Exception('Falta el producto a actualizar');
                }

                $producto->setId($_POST['id']);
                $productoActual = $producto->getOne();
                if (!$productoActual) {
                    throw new Exception('Producto no encontrado');
                }

                $producto->setNombre($productoActual->nombre ?? '');
                $producto->setDescripcion($productoActual->descripcion ?? '');
                $producto->setCategoriaId($productoActual->categoria_id ?? null);

                if (!$producto->update()) {
                    throw new Exception('No se pudo actualizar el descuento del producto');
                }

                ActualizacionesHelper::registrarCambio(
                    'productos',
                    'actualizar_descuento',
                    [
                        'id' => $productoActual->id ?? $_POST['id'],
                        'descuento_porcentaje' => $productoActual->descuento_porcentaje ?? 0,
                    ],
                    [
                        'id' => $productoActual->id ?? $_POST['id'],
                        'descuento_porcentaje' => $descuento,
                    ],
                    ['entidad' => 'producto', 'registro_id' => (string)($_POST['id'] ?? '')]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'DESCUENTO ACTUALIZADO CORRECTAMENTE'
                ]);
                break;

            case 'eliminar':
                // Permiso: Administradores pueden eliminar productos
                
                if (!isset($_POST['id']) || empty($_POST['id'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID no proporcionado'
                    ]);
                    exit;
                }

                try {
                    $id = (int)$_POST['id'];
                    $producto->setId($id);
                    $productoAntes = $producto->getOne();
                    $resultado = $producto->delete();
                    
                    if ($resultado) {
                        $datosBefore = $productoAntes ? [
                            'id' => $productoAntes->id ?? $id,
                            'codigo' => $productoAntes->codigo ?? null,
                            'nombre' => $productoAntes->nombre ?? null,
                            'descripcion' => $productoAntes->descripcion ?? null,
                            'precio' => $productoAntes->precio ?? null,
                            'stock' => $productoAntes->stock ?? null,
                            'categoria_id' => $productoAntes->categoria_id ?? null,
                            'imagen' => $productoAntes->imagen ?? null,
                            'estado' => $productoAntes->estado ?? null,
                        ] : ['id' => $id];

                        ActualizacionesHelper::registrarCambio(
                            'productos',
                            'eliminar',
                            $datosBefore,
                            ['id' => $id, 'eliminado' => true],
                            ['entidad' => 'producto', 'registro_id' => (string)$id]
                        );

                        http_response_code(200);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Producto eliminado correctamente'
                        ]);
                    } else {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'No se pudo eliminar el producto'
                        ]);
                    }
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                exit;

            case 'reiniciar':
                if (!PermisosHelper::esSuperAdminSesion()) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Permisos insuficientes para reiniciar productos'
                    ]);
                    exit;
                }

                try {
                    $resultado = $producto->reiniciarProductos();
                    echo json_encode($resultado);
                } catch (Exception $e) {
                    error_log('Error en acción reiniciar productos: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al reiniciar productos: ' . $e->getMessage()
                    ]);
                }
                exit;

            case 'deshacer':
                if (!PermisosHelper::esSuperAdminSesion()) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Permisos insuficientes para deshacer reinicio de productos'
                    ]);
                    exit;
                }

                try {
                    $resultado = $producto->deshacerProductos();
                    echo json_encode($resultado);
                } catch (Exception $e) {
                    error_log('Error en acción deshacer productos: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al restaurar productos: ' . $e->getMessage()
                    ]);
                }
                exit;

            default:
                throw new Exception('Acción no válida');
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
    exit;
}
?>