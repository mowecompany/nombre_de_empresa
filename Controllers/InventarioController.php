<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Inventario.php';

try {
    $db = Database::connect();
    
    class InventarioController {
        private $inventario;
        
        public function __construct($db) {
            $this->inventario = new Inventario($db);
        }

        private function getUsuarioIdSesion(): int {
            if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
                return (int)$_SESSION['usuario_id'];
            }

            $idUserData = (int)($_SESSION['userData']['idusuario'] ?? ($_SESSION['userData']['id'] ?? 0));
            return $idUserData > 0 ? $idUserData : 0;
        }

        private function getEmpresaIdSesion(): int {
            $sessionKeys = ['empresa_id', 'empresaId', 'empresaid'];
            foreach ($sessionKeys as $key) {
                if (isset($_SESSION[$key]) && (int)$_SESSION[$key] > 0) {
                    return (int)$_SESSION[$key];
                }
            }

            $userDataKeys = ['empresa_id', 'empresaId', 'empresaid'];
            foreach ($userDataKeys as $key) {
                $empresaData = (int)($_SESSION['userData'][$key] ?? 0);
                if ($empresaData > 0) {
                    return $empresaData;
                }
            }

            $usuarioId = $this->getUsuarioIdSesion();
            if ($usuarioId <= 0) {
                return 0;
            }

            try {
                $stmt = $this->inventario->getDb()->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $usuarioId]);
                return (int)($stmt->fetchColumn() ?: 0);
            } catch (Exception $e) {
                error_log('InventarioController getEmpresaIdSesion error: ' . $e->getMessage());
                return 0;
            }
        }

        private function esSuperAdminSesion(): bool {
            $rol = trim(mb_strtolower((string)($_SESSION['rol'] ?? ''), 'UTF-8'));
            $rol = strtr($rol, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
            ]);
            return $rol === 'super administrador' && empty($_SESSION['superadmin_modo_empresa']);
        }

        private function columnaExiste(string $tabla, string $columna): bool {
            try {
                $driver = strtolower((string)$this->inventario->getDb()->getAttribute(PDO::ATTR_DRIVER_NAME));
                if ($driver === 'sqlite') {
                    $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
                    $stmt = $this->inventario->getDb()->prepare("PRAGMA table_info(\"{$tablaSegura}\")");
                    $stmt->execute();
                    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($columnas as $col) {
                        if (strcasecmp($col['name'] ?? '', $columna) === 0) {
                            return true;
                        }
                    }
                    return false;
                }

                $stmt = $this->inventario->getDb()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
                $stmt->execute([':tabla' => $tabla, ':columna' => $columna]);
                return ((int)$stmt->fetchColumn()) > 0;
            } catch (Exception $e) {
                error_log('InventarioController columnaExiste error: ' . $e->getMessage());
                return false;
            }
        }
    
        // Obtener todos los movimientos (AJAX)
        public function obtenerMovimientos() {
            try {
                $filtro = [];
                if (!empty($_GET['producto_id'])) {
                    $filtro['producto_id'] = $_GET['producto_id'];
                }
                if (!empty($_GET['tipo'])) {
                    $filtro['tipo'] = $_GET['tipo'];
                }
                if (!empty($_GET['fecha_inicio'])) {
                    $filtro['fecha_inicio'] = $_GET['fecha_inicio'];
                }
                if (!empty($_GET['fecha_fin'])) {
                    $filtro['fecha_fin'] = $_GET['fecha_fin'];
                }
                
                $movimientos = $this->inventario->obtenerMovimientos($filtro);
                
                echo json_encode([
                    'success' => true,
                    'data' => $movimientos
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
    
        // Obtener entradas
        public function obtenerEntradas() {
            try {
                $filtro = [];
                if (!empty($_GET['producto_id'])) {
                    $filtro['producto_id'] = $_GET['producto_id'];
                }
                if (!empty($_GET['proveedor_id'])) {
                    $filtro['proveedor_id'] = $_GET['proveedor_id'];
                }
                if (!empty($_GET['fecha_inicio'])) {
                    $filtro['fecha_inicio'] = $_GET['fecha_inicio'];
                }
                if (!empty($_GET['fecha_fin'])) {
                    $filtro['fecha_fin'] = $_GET['fecha_fin'];
                }
                
                $entradas = $this->inventario->obtenerEntradas($filtro);
                
                echo json_encode([
                    'success' => true,
                    'data' => $entradas
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function obtenerProveedores() {
            try {
                $empresaId = $this->getEmpresaIdSesion();
                $esSuperAdmin = $this->esSuperAdminSesion();
                $tieneEmpresa = $this->columnaExiste('proveedores', 'empresa_id');

                $sql = "SELECT id, nombre FROM proveedores WHERE 1=1";
                $params = [];

                if (!$esSuperAdmin && $tieneEmpresa) {
                    if ($empresaId <= 0) {
                        throw new Exception('No se pudo resolver la empresa de la sesión para listar proveedores.');
                    }
                    $sql .= " AND empresa_id = :empresa_id";
                    $params[':empresa_id'] = $empresaId;
                }

                $sql .= " ORDER BY id DESC";
                $query = $this->inventario->getDb()->prepare($sql);
                $query->execute($params);
                $proveedores = $query->fetchAll(PDO::FETCH_OBJ);

                echo json_encode([
                    'success' => true,
                    'data' => $proveedores
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ]);
            }
        }
    
        // Obtener salidas
        public function obtenerSalidas() {
            try {
                $filtro = [];
                if (!empty($_GET['producto_id'])) {
                    $filtro['producto_id'] = $_GET['producto_id'];
                }
                if (!empty($_GET['tipo_salida'])) {
                    $filtro['tipo_salida'] = $_GET['tipo_salida'];
                }
                if (!empty($_GET['fecha_inicio'])) {
                    $filtro['fecha_inicio'] = $_GET['fecha_inicio'];
                }
                if (!empty($_GET['fecha_fin'])) {
                    $filtro['fecha_fin'] = $_GET['fecha_fin'];
                }
                
                $salidas = $this->inventario->obtenerSalidas($filtro);
                
                echo json_encode([
                    'success' => true,
                    'data' => $salidas
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
    
        // Registrar entrada (POST)
        public function registrarEntrada() {
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Método no permitido');
                }

                $empresaId = $this->getEmpresaIdSesion();
                $usuarioId = $this->getUsuarioIdSesion();
                $esSuperAdmin = $this->esSuperAdminSesion();
                $tieneEmpresaProveedor = $this->columnaExiste('proveedores', 'empresa_id');
                if ($tieneEmpresaProveedor && !$esSuperAdmin && $empresaId <= 0) {
                    throw new Exception('No se pudo resolver empresa_id para registrar la entrada.');
                }
                
                // Procesar proveedor (ID existente o nombre nuevo)
                if (empty($_POST['proveedor'])) {
                    throw new Exception('El proveedor es obligatorio para registrar la entrada');
                }

                $proveedor_id = null;
                $proveedor_valor = trim((string)$_POST['proveedor']);

                if ($proveedor_valor === '__OTRO__') {
                    throw new Exception('Proveedor inválido');
                }

                    if (ctype_digit($proveedor_valor)) {
                        // El frontend envió un proveedor existente por ID
                        $sql = "SELECT id FROM proveedores WHERE id = :id";
                        $params = [':id' => (int)$proveedor_valor];

                        if ($tieneEmpresaProveedor && !$esSuperAdmin) {
                            $sql .= " AND empresa_id = :empresa_id";
                            $params[':empresa_id'] = $empresaId;
                        }

                        $sql .= " LIMIT 1";
                        $query = $this->inventario->getDb()->prepare($sql);
                        $query->execute($params);
                        $resultado = $query->fetch(PDO::FETCH_OBJ);

                        if (!$resultado) {
                            throw new Exception('El proveedor seleccionado no existe');
                        }

                        $proveedor_id = (int)$resultado->id;
                    } else {
                        // Se ingresó nombre manual: buscar por nombre (sin duplicar por mayúsculas/espacios)
                        $sql = "SELECT id FROM proveedores WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre))";
                        $params = [':nombre' => $proveedor_valor];

                        if ($tieneEmpresaProveedor && !$esSuperAdmin) {
                            $sql .= " AND empresa_id = :empresa_id";
                            $params[':empresa_id'] = $empresaId;
                        }

                        $sql .= " LIMIT 1";
                        $query = $this->inventario->getDb()->prepare($sql);
                        $query->execute($params);
                        $resultado = $query->fetch(PDO::FETCH_OBJ);

                        if ($resultado) {
                            $proveedor_id = (int)$resultado->id;
                        } else {
                            // Crear nuevo proveedor solo cuando realmente es uno nuevo
                            $nextProveedorId = (int)$this->inventario->getDb()->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM proveedores")->fetchColumn();
                            if ($tieneEmpresaProveedor) {
                                $sql = "INSERT INTO proveedores (id, nombre, empresa_id) VALUES (:id, :nombre, :empresa_id)";
                                $paramsInsert = [':id' => $nextProveedorId, ':nombre' => $proveedor_valor, ':empresa_id' => $empresaId];
                            } else {
                                $sql = "INSERT INTO proveedores (id, nombre) VALUES (:id, :nombre)";
                                $paramsInsert = [':id' => $nextProveedorId, ':nombre' => $proveedor_valor];
                            }
                            $query = $this->inventario->getDb()->prepare($sql);
                            $query->execute($paramsInsert);
                            $proveedor_id = $nextProveedorId;
                        }
                    }

                $datos = [
                    'producto_id' => $_POST['producto_id'] ?? null,
                    'proveedor_id' => $proveedor_id,
                    'cantidad' => isset($_POST['cantidad']) ? (float)str_replace(',', '.', (string)$_POST['cantidad']) : null,
                    'precio_compra' => $_POST['precio_compra'] ?? null,
                    'porcentaje_ganancia' => $_POST['porcentaje_ganancia'] ?? 0,
                    'porcentaje_descuento' => $_POST['porcentaje_descuento'] ?? 0,
                    'lote' => $_POST['lote'] ?? null,
                    'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?? null,
                    'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                    'notas' => $_POST['notas'] ?? null
                ];
                
                // Validar datos requeridos
                if (empty($datos['producto_id']) || empty($datos['cantidad']) || empty($datos['precio_compra'])) {
                    throw new Exception('Los campos producto, cantidad y precio de compra son requeridos');
                }
                
                $resultado = $this->inventario->registrarEntrada($datos);

                if (!empty($resultado['success'])) {
                    $resultado['proveedor_id'] = $proveedor_id;
                }
                
                echo json_encode($resultado);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
    
        // Registrar salida (POST)
        public function registrarSalida() {
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Método no permitido');
                }
                
                $datos = [
                    'producto_id' => $_POST['producto_id'] ?? null,
                    'cantidad' => isset($_POST['cantidad']) ? (float)str_replace(',', '.', (string)$_POST['cantidad']) : null,
                    'tipo_salida' => $_POST['tipo_salida'] ?? 'venta',
                    'metodo_pago' => $_POST['metodo_pago'] ?? 'efectivo',
                    'referencia' => $_POST['referencia'] ?? null,
                    'usuario_id' => $this->getUsuarioIdSesion() ?: null,
                    'notas' => $_POST['notas'] ?? null,
                    'precio_venta' => isset($_POST['precio_venta']) ? floatval($_POST['precio_venta']) : null
                ];
                
                // Validar datos requeridos
                if (empty($datos['producto_id']) || empty($datos['cantidad'])) {
                    throw new Exception('Los campos producto y cantidad son requeridos');
                }
                
                $resultado = $this->inventario->registrarSalida($datos);
                
                echo json_encode($resultado);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
    
        // Obtener resumen del inventario
        public function obtenerResumen() {
            try {
                error_log("=== Obteniendo resumen de inventario ===");
                $mes = isset($_GET['mes']) ? trim((string)$_GET['mes']) : null;
                if ($mes !== null && !preg_match('/^\d{4}-\d{2}$/', $mes)) {
                    $mes = null;
                }
                if ($mes === null) {
                    $mes = date('Y-m');
                }

                error_log("Mes recibido en obtenerResumen: " . $mes);
                $resumen = $this->inventario->obtenerResumenInventario($mes);
                error_log("Resumen obtenido, cantidad de productos: " . count($resumen));

                $ventasMesActual = $this->inventario->obtenerVentasDelMes($mes);
                $ventas_totales_mes = floatval($ventasMesActual['valor_total_ventas'] ?? 0);

                $ventas_totales_mes_anterior = 0.0;
                $parts = explode('-', $mes);
                if (count($parts) === 2) {
                    $y = (int)$parts[0];
                    $m = (int)$parts[1];
                    $date = DateTime::createFromFormat('!Y-n', "{$y}-{$m}");
                    if ($date) {
                        $date->modify('-1 month');
                        $prevMes = $date->format('Y-m');
                        $ventasMesAnterior = $this->inventario->obtenerVentasDelMes($prevMes);
                        $ventas_totales_mes_anterior = floatval($ventasMesAnterior['valor_total_ventas'] ?? 0);
                    }
                }

                echo json_encode([
                    'success' => true,
                    'requested_mes' => $mes,
                    'ventas_por_mes' => [
                        'mes' => $mes,
                        'ventas' => $ventas_totales_mes,
                        'ventas_mes_anterior' => $ventas_totales_mes_anterior,
                        'diferencia' => $ventas_totales_mes - $ventas_totales_mes_anterior
                    ],
                    'data' => $resumen
                ]);
            } catch(Exception $e) {
                error_log("Error en obtenerResumen: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function obtenerMesesInventario() {
            try {
                $meses = $this->inventario->obtenerMesesInventario();
                echo json_encode([
                    'success' => true,
                    'data' => $meses
                ]);
            } catch(Exception $e) {
                error_log("Error en obtenerMesesInventario: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => []
                ]);
            }
        }

        // Obtener estadísticas
        public function obtenerEstadisticas() {
            try {
                $estadisticas = $this->inventario->obtenerEstadisticas();
                
                echo json_encode([
                    'success' => true,
                    'data' => $estadisticas
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Obtener ganancia total
        public function obtenerGanancia() {
            try {
                $ganancia = $this->inventario->obtenerGananciaTotal();
                
                echo json_encode([
                    'success' => true,
                    'data' => $ganancia
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Obtener productos que necesitan reorden
        public function obtenerProductosReorden() {
            try {
                $limite = $_GET['limite'] ?? 5;
                $soloAgotados = isset($_GET['agotados']) ? ((int)$_GET['agotados'] === 1) : false;
                $productos = $this->inventario->obtenerProductosParaReorden($limite, $soloAgotados);
                
                echo json_encode([
                    'success' => true,
                    'data' => $productos
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Obtener desglose del valor del inventario
        public function obtenerValorInventario() {
            try {
                $valor = $this->inventario->obtenerValorInventario();
                
                echo json_encode([
                    'success' => true,
                    'data' => $valor
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Obtener resumen de ventas del día
        public function obtenerVentasDia() {
            try {
                $ventas = $this->inventario->obtenerVentasDelDia();
                
                echo json_encode([
                    'success' => true,
                    'data' => $ventas
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Obtener resumen de ventas del mes
        public function obtenerVentasMes() {
            try {
                $periodo = isset($_GET['periodo']) ? trim((string)$_GET['periodo']) : null;
                $ventas = $this->inventario->obtenerVentasDelMes($periodo);
                
                echo json_encode([
                    'success' => true,
                    'data' => $ventas
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function obtenerPrimerMesVentas() {
            try {
                $primerMes = $this->inventario->obtenerPrimerMesConVentas();

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'primer_mes' => $primerMes
                    ]
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function reiniciarInventario() {
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Método no permitido');
                }

                if (!$this->esSuperAdminSesion()) {
                    throw new Exception('Solo el super administrador puede limpiar inventario');
                }

                $tabla = trim((string)($_POST['tabla'] ?? ''));
                $tablasPermitidas = ['all', 'entradas_inventario', 'salidas_inventario', 'movimientos_inventario', 'productos'];
                if ($tabla === '') {
                    throw new Exception('No se especificó la tabla a limpiar');
                }
                if (!in_array($tabla, $tablasPermitidas, true)) {
                    throw new Exception('Tabla inválida');
                }

                $tablasAReiniciar = $tabla === 'all'
                    ? ['entradas_inventario', 'salidas_inventario', 'movimientos_inventario', 'productos']
                    : [$tabla];

                $resultado = $this->inventario->reiniciarInventario($tablasAReiniciar);

                echo json_encode($resultado);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        public function deshacerReinicioInventario() {
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Método no permitido');
                }

                if (!$this->esSuperAdminSesion()) {
                    throw new Exception('Solo el super administrador puede restaurar inventario');
                }

                $tabla = trim((string)($_POST['tabla'] ?? 'all'));
                $tablasPermitidas = ['all', 'entradas_inventario', 'salidas_inventario', 'movimientos_inventario', 'productos'];
                if ($tabla === '') {
                    $tabla = 'all';
                }
                if (!in_array($tabla, $tablasPermitidas, true)) {
                    throw new Exception('Tabla inválida');
                }

                $tablasARestaurar = $tabla === 'all'
                    ? ['entradas_inventario', 'salidas_inventario', 'movimientos_inventario', 'productos']
                    : [$tabla];

                $resultados = [];
                foreach ($tablasARestaurar as $tablaActual) {
                    $resultados[] = $this->inventario->deshacerReinicioInventario($tablaActual);
                }

                $exito = array_filter($resultados, static function ($item) {
                    return !empty($item['success']);
                });

                echo json_encode([
                    'success' => !empty($exito),
                    'message' => count($exito) > 0 ? 'Reinicio deshecho correctamente' : 'No se pudo deshacer el reinicio',
                    'data' => $resultados
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Obtener productos con información de ganancia
        public function obtenerProductosConGanancia() {
            try {
                $productos = $this->inventario->obtenerProductosConGanancia();
                
                echo json_encode([
                    'success' => true,
                    'data' => $productos
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        // Obtener movimientos sin orden por fecha (agrupados)
        public function obtenerMovimientosPorFecha() {
            try {
                if (empty($_GET['fecha'])) {
                    throw new Exception('Fecha no especificada');
                }

                $fecha = $_GET['fecha'];
                // Validar formato de fecha
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    throw new Exception('Formato de fecha inválido');
                }

                $db = $this->inventario->getDb();
                $sql = "SELECT m.*, p.nombre as producto_nombre, p.codigo as codigo, p.imagen as producto_imagen,
                        u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, u.rol as usuario_rol
                        FROM movimientos_inventario m
                        INNER JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE DATE(m.fecha_movimiento) = :fecha
                        ORDER BY m.fecha_movimiento DESC";

                $stmt = $db->prepare($sql);
                $stmt->execute([':fecha' => $fecha]);
                $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $movimientos
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        // Obtener movimientos por descripción/referencia
        public function obtenerMovimientosPorDescripcion() {
            try {
                if (empty($_GET['descripcion'])) {
                    throw new Exception('Descripción no especificada');
                }

                $descripcion = $_GET['descripcion'];
                $db = $this->inventario->getDb();
                
                $sql = "SELECT m.*, p.nombre as producto_nombre, p.codigo as codigo, p.imagen as producto_imagen,
                        u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, u.rol as usuario_rol
                        FROM movimientos_inventario m
                        INNER JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE m.descripcion = :descripcion
                        ORDER BY m.fecha_movimiento DESC";

                $stmt = $db->prepare($sql);
                $stmt->execute([':descripcion' => $descripcion]);
                $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $movimientos
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        // Obtener movimientos por referencia_id (transacción/venta)
        public function obtenerMovimientosPorReferencia() {
            try {
                if (empty($_GET['referencia_id'])) {
                    throw new Exception('Referencia no especificada');
                }

                $referencia_id = (int)$_GET['referencia_id'];
                $db = $this->inventario->getDb();
                
                $sql = "SELECT m.*, p.nombre as producto_nombre, p.codigo as codigo, p.imagen as producto_imagen,
                        u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, u.rol as usuario_rol
                        FROM movimientos_inventario m
                        INNER JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE m.referencia_id = :referencia_id
                        ORDER BY m.fecha_movimiento DESC";

                $stmt = $db->prepare($sql);
                $stmt->execute([':referencia_id' => $referencia_id]);
                $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $movimientos
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        // Obtener movimiento por ID
        public function obtenerMovimientoPorId() {
            try {
                if (empty($_GET['id'])) {
                    throw new Exception('ID no especificado');
                }

                $id = (int)$_GET['id'];
                $db = $this->inventario->getDb();
                
                $sql = "SELECT m.*, p.nombre as producto_nombre, p.codigo as codigo, p.imagen as producto_imagen,
                        u.nombre as usuario_nombre, u.apellidos as usuario_apellidos, u.rol as usuario_rol
                        FROM movimientos_inventario m
                        INNER JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE m.id = :id
                        LIMIT 1";

                $stmt = $db->prepare($sql);
                $stmt->execute([':id' => $id]);
                $movimiento = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => (bool)$movimiento,
                    'data' => $movimiento ?: null
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        // Obtener productos críticos y urgentes
        public function obtenerProductosCriticosYUrgentes() {
            try {
                $db = $this->inventario->getDb();
                
                // Críticos: stock = 0
                $sqlCriticos = "SELECT id, codigo, nombre, stock, imagen, 'critico' as tipo 
                                FROM productos 
                                WHERE stock = 0
                                ORDER BY nombre ASC";
                
                // Urgentes: stock > 0 AND stock <= 5
                $sqlUrgentes = "SELECT id, codigo, nombre, stock, imagen, 'urgente' as tipo 
                               FROM productos 
                               WHERE stock > 0 AND stock <= 5
                               ORDER BY stock ASC, nombre ASC";

                $stmtCriticos = $db->prepare($sqlCriticos);
                $stmtCriticos->execute();
                $criticos = $stmtCriticos->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtUrgentes = $db->prepare($sqlUrgentes);
                $stmtUrgentes->execute();
                $urgentes = $stmtUrgentes->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'criticos' => $criticos,
                    'urgentes' => $urgentes,
                    'count_criticos' => count($criticos),
                    'count_urgentes' => count($urgentes),
                    'count_total' => count($criticos) + count($urgentes)
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }

        // Obtener productos con stock cero
        public function obtenerProductosStockCero() {
            try {
                $db = $this->inventario->getDb();
                
                $sql = "SELECT id, codigo, nombre, stock, precio_venta 
                        FROM productos 
                        WHERE stock <= 0
                        ORDER BY nombre ASC";

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $productos,
                    'count' => count($productos)
                ]);
            } catch(Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
    }

// HANDLING AJAX REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['action'])) {
    $controller = new InventarioController($db);
    $action = $_GET['action'];
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no encontrada'
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $controller = new InventarioController($db);
    $action = $_POST['action'];
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no encontrada'
        ]);
    }
}

} catch(Exception $e) {
    http_response_code(500);
    jsonResponse([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ], 500);
}
?>
