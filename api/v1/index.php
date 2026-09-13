<?php
/**
 * API Local - Fase 2: Endpoints Multi-Caja
 * 
 * Proporciona interfaz REST para operaciones de inventario
 * compatible con arquitectura multi-caja en red local.
 * 
 * Endpoints:
 * - POST /api/v1/auth/login
 * - POST /api/v1/inventario/entrada
 * - POST /api/v1/inventario/salida
 * - GET  /api/v1/inventario/stock/{producto_id}
 * - GET  /api/v1/inventario/movimientos
 * - GET  /api/v1/health
 * 
 * Uso: http://192.168.1.100/nombre_de_empresa/api/v1/[endpoint]
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// Cargar configuración
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Inventario.php';
require_once ROOT_PATH . '/Models/Usuario.php';

/**
 * Respuesta API estándar
 */
function api_response($success, $data = null, $message = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validar token/sesión
 */
function validar_sesion() {
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['empresa_id'])) {
        api_response(false, null, 'No autenticado', 401);
    }
}

/**
 * Obtener cuerpo de solicitud JSON
 */
function obtener_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

/**
 * Extraer ruta y método
 */
$ruta = $_SERVER['REQUEST_URI'];
$ruta = preg_replace('/\?.*/', '', $ruta); // Remover query string
$ruta = str_replace('/nombre_de_empresa/api/v1/index.php', '', $ruta);
$ruta = str_replace('/nombre_de_empresa/api/v1', '', $ruta);
$metodo = $_SERVER['REQUEST_METHOD'];
$accionApi = trim((string)($_GET['action'] ?? ''));

try {
    // ===== ENDPOINT: Health Check =====
    if ($ruta === '/health' && $metodo === 'GET') {
        $db = Database::connect();
        api_response(true, [
            'status' => 'online',
            'database' => 'sqlite',
            'version' => '1.0',
            'uptime' => date('Y-m-d H:i:s')
        ], 'API operativa');
    }

    // ===== ENDPOINTS: Presencia de cajas en la red local =====
    if (($ruta === '/presence' || $accionApi === 'presence') && $metodo === 'POST') {
        $input = obtener_json_input();
        $cajaId = trim((string)($input['caja_id'] ?? ''));
        if ($cajaId === '' || strlen($cajaId) > 120) {
            api_response(false, null, 'Identificador de caja inválido', 400);
        }

        $db = Database::connect();
        $db->exec("CREATE TABLE IF NOT EXISTS cajas_activas (
            caja_id TEXT PRIMARY KEY,
            nombre TEXT NOT NULL DEFAULT 'CAJA',
            modo TEXT NOT NULL DEFAULT 'portable',
            ip TEXT NOT NULL DEFAULT '',
            puerto INTEGER NOT NULL DEFAULT 0,
            usuario_id INTEGER NULL,
            rol TEXT NULL,
            ultima_conexion TEXT NOT NULL
        )");
        $stmt = $db->prepare("INSERT INTO cajas_activas (caja_id, nombre, modo, ip, puerto, usuario_id, rol, ultima_conexion)
            VALUES (:caja_id, :nombre, :modo, :ip, :puerto, :usuario_id, :rol, :ultima_conexion)
            ON CONFLICT(caja_id) DO UPDATE SET nombre=excluded.nombre, modo=excluded.modo, ip=excluded.ip,
            puerto=excluded.puerto, usuario_id=excluded.usuario_id, rol=excluded.rol, ultima_conexion=excluded.ultima_conexion");
        $stmt->execute([
            ':caja_id' => $cajaId,
            ':nombre' => trim((string)($input['nombre'] ?? 'CAJA')) ?: 'CAJA',
            ':modo' => trim((string)($input['modo'] ?? 'portable')) ?: 'portable',
            ':ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            ':puerto' => (int)($input['puerto'] ?? 0),
            ':usuario_id' => !empty($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null,
            ':rol' => (string)($_SESSION['rol'] ?? ''),
            ':ultima_conexion' => date('Y-m-d H:i:s')
        ]);
        api_response(true, ['caja_id' => $cajaId], 'Caja registrada');
    }

    if (($ruta === '/presence' || $accionApi === 'presence') && $metodo === 'GET') {
        $db = Database::connect();
        $db->exec("CREATE TABLE IF NOT EXISTS cajas_activas (
            caja_id TEXT PRIMARY KEY, nombre TEXT NOT NULL DEFAULT 'CAJA', modo TEXT NOT NULL DEFAULT 'portable',
            ip TEXT NOT NULL DEFAULT '', puerto INTEGER NOT NULL DEFAULT 0, usuario_id INTEGER NULL, rol TEXT NULL,
            ultima_conexion TEXT NOT NULL
        )");
        $stmt = $db->prepare("SELECT caja_id, nombre, modo, ip, puerto, rol, ultima_conexion FROM cajas_activas
            WHERE datetime(ultima_conexion) >= datetime('now', '-45 seconds') ORDER BY nombre, caja_id");
        $stmt->execute();
        api_response(true, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Cajas activas');
    }
    
    // ===== ENDPOINT: Login =====
    if ($ruta === '/auth/login' && $metodo === 'POST') {
        $input = obtener_json_input();
        $correo = $input['email'] ?? $input['correo'] ?? '';
        $contrasena = $input['password'] ?? $input['contrasena'] ?? '';
        
        if (empty($correo) || empty($contrasena)) {
            api_response(false, null, 'Email y contraseña requeridos', 400);
        }
        
        $db = Database::connect();
        $usuarioModel = new Usuario($db);
        $resultado = $usuarioModel->verificarCredenciales($correo, $contrasena);
        
        if (!$resultado || !isset($resultado['id'])) {
            api_response(false, null, 'Credenciales inválidas', 401);
        }
        
        // Establecer sesión
        $_SESSION['usuario_id'] = $resultado['id'];
        $_SESSION['email'] = $resultado['correo'] ?? '';
        $_SESSION['nombre'] = $resultado['nombre'] ?? 'Usuario';
        $_SESSION['rol'] = $resultado['rol'] ?? 'usuario';
        $_SESSION['empresa_id'] = $resultado['empresa_id'] ?? 1;
        $_SESSION['userData'] = $resultado;
        
        api_response(true, [
            'usuario_id' => $resultado['id'],
            'nombre' => $resultado['nombre'] ?? '',
            'email' => $resultado['correo'] ?? '',
            'rol' => $resultado['rol'] ?? '',
            'empresa_id' => $resultado['empresa_id'] ?? 1,
            'token' => session_id()
        ], 'Login exitoso');
    }
    
    // ===== ENDPOINT: Registrar Entrada =====
    if ($ruta === '/inventario/entrada' && $metodo === 'POST') {
        validar_sesion();
        
        $input = obtener_json_input();
        $db = Database::connect();
        $inventario = new Inventario($db);
        
        $datos = [
            'producto_id' => (int)($input['producto_id'] ?? 0),
            'proveedor_id' => (int)($input['proveedor_id'] ?? 0),
            'cantidad' => (int)($input['cantidad'] ?? 0),
            'precio_compra' => (float)($input['precio_compra'] ?? 0),
            'usuario_id' => (int)$_SESSION['usuario_id'],
            'porcentaje_ganancia' => (float)($input['porcentaje_ganancia'] ?? 0),
            'notas' => $input['notas'] ?? ''
        ];
        
        // Validar datos
        if ($datos['producto_id'] <= 0 || $datos['cantidad'] <= 0 || $datos['precio_compra'] <= 0) {
            api_response(false, null, 'Datos inválidos: producto_id, cantidad, precio_compra son requeridos', 400);
        }
        
        $resultado = $inventario->registrarEntrada($datos);
        
        if (!$resultado['success']) {
            api_response(false, null, $resultado['message'], 400);
        }
        
        api_response(true, [
            'entrada_id' => $resultado['id'],
            'mensaje' => $resultado['message'],
            'producto_id' => $datos['producto_id'],
            'cantidad' => $datos['cantidad'],
            'precio_compra' => $datos['precio_compra']
        ], 'Entrada registrada correctamente', 201);
    }
    
    // ===== ENDPOINT: Registrar Salida =====
    if ($ruta === '/inventario/salida' && $metodo === 'POST') {
        validar_sesion();
        
        $input = obtener_json_input();
        $db = Database::connect();
        $inventario = new Inventario($db);
        
        $datos = [
            'producto_id' => (int)($input['producto_id'] ?? 0),
            'cantidad' => (int)($input['cantidad'] ?? 0),
            'tipo_salida' => $input['tipo_salida'] ?? 'venta',
            'usuario_id' => (int)$_SESSION['usuario_id'],
            'referencia' => $input['referencia'] ?? 'API_' . time(),
            'precio_venta' => (float)($input['precio_venta'] ?? 0),
            'notas' => $input['notas'] ?? ''
        ];
        
        // Validar datos
        if ($datos['producto_id'] <= 0 || $datos['cantidad'] <= 0) {
            api_response(false, null, 'Datos inválidos: producto_id y cantidad son requeridos', 400);
        }
        
        $resultado = $inventario->registrarSalida($datos);
        
        if (!$resultado['success']) {
            api_response(false, null, $resultado['message'], 400);
        }
        
        api_response(true, [
            'salida_id' => $resultado['id'],
            'mensaje' => $resultado['message'],
            'producto_id' => $datos['producto_id'],
            'cantidad' => $datos['cantidad'],
            'tipo_salida' => $datos['tipo_salida']
        ], 'Salida registrada correctamente', 201);
    }
    
    // ===== ENDPOINT: Consultar Stock =====
    if (preg_match('/^\/inventario\/stock\/(\d+)$/', $ruta, $matches) && $metodo === 'GET') {
        validar_sesion();
        
        $producto_id = (int)$matches[1];
        $db = Database::connect();
        
        $stmt = $db->prepare("
            SELECT id, nombre, codigo, precio, stock, descripcion
            FROM productos
            WHERE id = :id AND estado = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $producto_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            api_response(false, null, 'Producto no encontrado', 404);
        }
        
        api_response(true, [
            'producto_id' => (int)$producto['id'],
            'nombre' => $producto['nombre'],
            'codigo' => $producto['codigo'],
            'precio' => (float)$producto['precio'],
            'stock' => (int)$producto['stock'],
            'disponible' => (int)$producto['stock'] > 0
        ], 'Stock obtenido correctamente');
    }
    
    // ===== ENDPOINT: Listar Movimientos =====
    if ($ruta === '/inventario/movimientos' && $metodo === 'GET') {
        validar_sesion();
        
        $producto_id = $_GET['producto_id'] ?? null;
        $tipo = $_GET['tipo'] ?? null;
        $limite = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        if ($limite > 500) $limite = 500; // Límite máximo
        if ($limite < 1) $limite = 10;
        
        $db = Database::connect();
        $sql = "
            SELECT 
                id, producto_id, tipo_movimiento, cantidad, 
                stock_anterior, stock_nuevo, usuario_id, 
                descripcion, fecha_movimiento
            FROM movimientos_inventario
            WHERE 1=1
        ";
        $params = [];
        
        if ($producto_id) {
            $sql .= " AND producto_id = :producto_id";
            $params[':producto_id'] = (int)$producto_id;
        }
        
        if ($tipo) {
            $sql .= " AND tipo_movimiento = :tipo";
            $params[':tipo'] = $tipo;
        }
        
        $sql .= " ORDER BY fecha_movimiento DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $sqlCount = "SELECT COUNT(*) as total FROM movimientos_inventario WHERE 1=1";
        $paramsCount = [];
        
        if ($producto_id) {
            $sqlCount .= " AND producto_id = :producto_id";
            $paramsCount[':producto_id'] = (int)$producto_id;
        }
        
        if ($tipo) {
            $sqlCount .= " AND tipo_movimiento = :tipo";
            $paramsCount[':tipo'] = $tipo;
        }
        
        $stmtCount = $db->prepare($sqlCount);
        foreach ($paramsCount as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();
        
        api_response(true, [
            'movimientos' => $movimientos,
            'total' => (int)$total,
            'limit' => $limite,
            'offset' => $offset,
            'paginas' => ceil($total / $limite)
        ], 'Movimientos obtenidos correctamente');
    }
    
    // ===== ENDPOINT: No encontrado =====
    api_response(false, null, 'Endpoint no encontrado', 404);
    
} catch (PDOException $e) {
    error_log('API Database Error: ' . $e->getMessage());
    api_response(false, null, 'Error de base de datos', 500);
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    api_response(false, null, 'Error interno del servidor', 500);
}

?>
