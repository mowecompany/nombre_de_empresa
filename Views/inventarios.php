<?php
// Si se recibió PHPSESSID como parámetro (desde iframe), usarlo para la sesión
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../Config/Config.php';
require_once '../Config/database.php';
require_once '../Helpers/Helpers.php';

// Actualizar última actividad
if (isset($_SESSION['usuario_id'])) {
    try {
        require_once '../Config/database.php';
        require_once '../Models/Usuario.php';
        $db = (new Database())->connect();
        $usuarioModel = new Usuario($db);
        $usuarioModel->actualizarUltimaActividad($_SESSION['usuario_id']);
    } catch (Exception $e) {
        error_log("Error al actualizar actividad: " . $e->getMessage());
    }
}

// Verificar sesión activa
if (!isset($_SESSION['rol'])) {
    error_log("No hay rol definido en la sesión");
    header('Location: login.php');
    exit();
}

// Verificar si el rol está activo


// Verificar permiso de ver
$empresaContextoActivoInventarios = (!empty($_SESSION['empresa_id']) || !empty($_SESSION['userData']['empresa_id'])) && empty($_SESSION['superadmin_modo_empresa']);
if (!PermisosHelper::esSuperAdminSesion() && !$empresaContextoActivoInventarios && !PermisosHelper::tienePermiso('Inventario', 'ver')) {
    header('Location: dashboard.php');
    exit();
}

// Verificar permisos para las acciones
$tienePermisoVer = PermisosHelper::tienePermiso('Inventario', 'ver');
$tienePermisoEditar = PermisosHelper::tienePermiso('Inventario', 'actualizar');
$tienePermisoCrear = PermisosHelper::tienePermiso('Inventario', 'crear');

// Obtener el rol actual del usuario
$rolActual = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
$esSuperAdmin = PermisosHelper::esSuperAdminSesion();
$rolActualNorm = trim(mb_strtolower((string)$rolActual, 'UTF-8'));
$rolActualNorm = strtr($rolActualNorm, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
$esAdministradorContexto = ($rolActualNorm === 'administrador') || ($esSuperAdmin && !empty($_SESSION['superadmin_modo_empresa']));
$esSuperAdminGlobalInventario = $esSuperAdmin && empty($_SESSION['superadmin_modo_empresa']);
$filtrarPorUsuarioInventario = false;
$puedeVerID = $esAdministradorContexto || $esSuperAdmin;
if ($esSuperAdmin) {
    $tienePermisoVer = true;
    $tienePermisoEditar = true;
    $tienePermisoCrear = true;
}

// Obtener productos para los selectores
try {
    $db = Database::connect();

    $empresaIdSesion = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : (int)($_SESSION['userData']['empresa_id'] ?? 0);
    $usuarioIdSesion = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : (int)($_SESSION['userData']['idusuario'] ?? ($_SESSION['userData']['id'] ?? 0));

    if ($empresaIdSesion <= 0 && $usuarioIdSesion > 0) {
        try {
            $stmtEmp = $db->prepare("SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1");
            $stmtEmp->execute([':id' => $usuarioIdSesion]);
            $empresaIdSesion = (int)($stmtEmp->fetchColumn() ?: 0);
            if ($empresaIdSesion > 0) {
                $_SESSION['empresa_id'] = $empresaIdSesion;
            }
        } catch (Exception $e) {
            error_log('Inventario vista fallback empresa_id error: ' . $e->getMessage());
        }
    }

    if ($empresaIdSesion <= 0 && $esSuperAdmin) {
        try {
            $stmtEmpFirst = $db->prepare("SELECT id FROM empresas WHERE (estado IS NULL OR estado = 1) ORDER BY id ASC LIMIT 1");
            $stmtEmpFirst->execute();
            $empresaIdSesion = (int)($stmtEmpFirst->fetchColumn() ?: 0);
            if ($empresaIdSesion > 0) {
                $_SESSION['empresa_id'] = $empresaIdSesion;
            }
        } catch (Exception $e) {
            error_log('Inventario vista fallback empresa_id superadmin error: ' . $e->getMessage());
        }
    }

    function esSqliteConnection($db) {
        try {
            return strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Exception $e) {
            return false;
        }
    }

    function tieneColumna($db, $tabla, $columna) {
        if (esSqliteConnection($db)) {
            $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
            $stmt = $db->prepare("PRAGMA table_info(\"{$tablaSegura}\")");
            $stmt->execute();
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnas as $col) {
                if (strcasecmp($col['name'] ?? '', $columna) === 0) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->execute([':tabla' => $tabla, ':columna' => $columna]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    function columnaDescuentoProducto($db) {
        if (tieneColumna($db, 'productos', 'descuento_ganacia')) {
            return 'descuento_ganacia';
        }

        if (tieneColumna($db, 'productos', 'descuento_porcentaje')) {
            return 'descuento_porcentaje';
        }

        return null;
    }

    $tieneProdEmpresaId = tieneColumna($db, 'productos', 'empresa_id');
    $tieneProdUsuarioId = tieneColumna($db, 'productos', 'usuario_id');
    $tieneProdVentaPorKilo = tieneColumna($db, 'productos', 'venta_por_kilo');
    $tieneCatEmpresaId = tieneColumna($db, 'categorias', 'empresa_id');
    $tieneCatUsuarioId = tieneColumna($db, 'categorias', 'usuario_id');
    $tieneCatRequiereVencimiento = tieneColumna($db, 'categorias', 'requiere_vencimiento');

    $productosConImg = [];

    $columnaDescuentoProd = columnaDescuentoProducto($db);
    $tieneProdDescuentoPct = $columnaDescuentoProd !== null;

    $sqlSelectDescuento = $tieneProdDescuentoPct ? ", IFNULL({$columnaDescuentoProd}, 0) as descuento_porcentaje" : ", 0 as descuento_porcentaje";
    $sqlSelectVentaPorKilo = $tieneProdVentaPorKilo ? ", COALESCE(p.venta_por_kilo, 0) AS venta_por_kilo" : ", 0 AS venta_por_kilo";
    $sqlSelectRequiereVencimiento = $tieneCatRequiereVencimiento ? ", COALESCE((SELECT c.requiere_vencimiento FROM categorias c WHERE c.id = p.categoria_id LIMIT 1), 0) AS requiere_vencimiento" : ", 0 AS requiere_vencimiento";
    $sqlProductos = "SELECT p.id, p.nombre, p.imagen, p.codigo, p.codigo_barras, p.precio, p.categoria_id, (SELECT c.nombre FROM categorias c WHERE c.id = p.categoria_id LIMIT 1) AS categoria_nombre{$sqlSelectRequiereVencimiento}{$sqlSelectDescuento}{$sqlSelectVentaPorKilo}, IFNULL(p.stock, 0) AS stock FROM productos p WHERE p.estado = 1";
    $paramsProductos = [];
    if ($tieneProdEmpresaId && $empresaIdSesion > 0) {
        $sqlProductos .= " AND (p.empresa_id IS NULL OR p.empresa_id = 0 OR p.empresa_id = :empresa_id)";
        $paramsProductos[':empresa_id'] = $empresaIdSesion;
    }
    if ($filtrarPorUsuarioInventario && $tieneProdUsuarioId && $usuarioIdSesion > 0) {
        $sqlProductos .= " AND p.usuario_id = :usuario_id";
        $paramsProductos[':usuario_id'] = $usuarioIdSesion;
    }
    $sqlProductos .= " ORDER BY nombre";

    $productosReservados = [];

    $stmt = $db->prepare($sqlProductos);
    $stmt->execute($paramsProductos);
    $productosConImg = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($productosConImg) && $tieneProdEmpresaId && $empresaIdSesion > 0) {
        $fallbackSqlProductos = "SELECT p.id, p.nombre, p.imagen, p.codigo, p.codigo_barras, p.precio, p.categoria_id, (SELECT c.nombre FROM categorias c WHERE c.id = p.categoria_id LIMIT 1) AS categoria_nombre{$sqlSelectRequiereVencimiento}{$sqlSelectDescuento}{$sqlSelectVentaPorKilo}, IFNULL(p.stock, 0) AS stock FROM productos p WHERE p.estado = 1";
        $fallbackParamsProductos = [];
        if ($filtrarPorUsuarioInventario && $tieneProdUsuarioId && $usuarioIdSesion > 0) {
            $fallbackSqlProductos .= " AND p.usuario_id = :usuario_id";
            $fallbackParamsProductos[':usuario_id'] = $usuarioIdSesion;
        }
        $fallbackSqlProductos .= " ORDER BY nombre";
        $stmt = $db->prepare($fallbackSqlProductos);
        $stmt->execute($fallbackParamsProductos);
        $productosConImg = $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    foreach ($productosConImg as $prod) {
        $prod->reservado = $productosReservados[(int)$prod->id] ?? 0;
    }

    $productos = [];
    foreach ($productosConImg as $p) {
        $productos[] = (object)[
            'id' => $p->id,
            'nombre' => $p->nombre,
        ];
    }

    try {
        $stmtProv = $db->prepare("SELECT id, nombre FROM proveedores WHERE 1=1 ORDER BY id DESC");
        $paramsProv = [];
        if ($tieneProdEmpresaId && $empresaIdSesion > 0) {
            $stmtProv = $db->prepare("SELECT id, nombre FROM proveedores WHERE (empresa_id IS NULL OR empresa_id = 0 OR empresa_id = :empresa_id) ORDER BY id DESC");
            $paramsProv[':empresa_id'] = $empresaIdSesion;
        }
        $stmtProv->execute($paramsProv);
        $proveedores = $stmtProv->fetchAll(PDO::FETCH_OBJ);

        if (empty($proveedores) && $empresaIdSesion > 0) {
            $stmtProvFallback = $db->prepare("SELECT id, nombre FROM proveedores ORDER BY nombre");
            $stmtProvFallback->execute();
            $proveedores = $stmtProvFallback->fetchAll(PDO::FETCH_OBJ);
        }
    } catch(PDOException $ep) {
        error_log("Error al cargar proveedores: " . $ep->getMessage());
        $proveedores = [];
    }
    
    // también necesitamos categorías para el modal de edición
    try {
        $sqlCategorias = "SELECT c.id, c.nombre
                          FROM categorias c
                          WHERE (
                                c.estado = 1
                                OR EXISTS (
                                    SELECT 1
                                    FROM productos p
                                    WHERE p.categoria_id = c.id";
        $paramsCategorias = [];
        if ($tieneCatEmpresaId) {
            if ($empresaIdSesion > 0) {
                $sqlCategorias .= " AND p.empresa_id = :empresa_id_p";
                $paramsCategorias[':empresa_id_p'] = $empresaIdSesion;
            } else {
                $sqlCategorias .= " AND 1 = 0";
            }
        }

        $sqlCategorias .= ") )";

        if ($tieneCatEmpresaId) {
            if ($empresaIdSesion > 0) {
                $sqlCategorias .= " AND c.empresa_id = :empresa_id_c";
                $paramsCategorias[':empresa_id_c'] = $empresaIdSesion;
            } else {
                $sqlCategorias .= " AND 1 = 0";
            }
        }

        if ($filtrarPorUsuarioInventario && $tieneCatUsuarioId && $usuarioIdSesion > 0) {
            $sqlCategorias .= " AND c.usuario_id = :usuario_id";
            $paramsCategorias[':usuario_id'] = $usuarioIdSesion;
        }
        $sqlCategorias .= " ORDER BY c.nombre";

        $stmt2 = $db->prepare($sqlCategorias);
        $stmt2->execute($paramsCategorias);
        $categorias = $stmt2->fetchAll(PDO::FETCH_OBJ);
    } catch(PDOException $ee) {
        error_log("Error al cargar categorías: " . $ee->getMessage());
        $categorias = [];
    }
    
    // Obtener nombre del usuario logueado
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $nombreUsuario = 'Sistema';
    if ($usuarioId) {
        $stmt = $db->prepare("SELECT nombre FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch(PDO::FETCH_OBJ);
        $nombreUsuario = $usuario->nombre ?? 'Sistema';
    }

    // Obtener nombre de la empresa
    $nombreEmpresa = 'EMPRESA';
    $empresaLogo = '';
    if ($empresaIdSesion > 0) {
        try {
            $stmtEmpresa = $db->prepare("SELECT nombre FROM empresas WHERE id = :id LIMIT 1");
            $stmtEmpresa->execute([':id' => $empresaIdSesion]);
            $nombreEmpresa = (string)($stmtEmpresa->fetchColumn() ?: 'EMPRESA');
        } catch (Exception $eEmp) {
            // Fallback silencioso
        }
        if (tieneColumna($db, 'empresas', 'imagen')) {
            try {
                $stmtLogo = $db->prepare("SELECT imagen FROM empresas WHERE id = :id LIMIT 1");
                $stmtLogo->execute([':id' => $empresaIdSesion]);
                $empresaLogo = trim((string)($stmtLogo->fetchColumn() ?: ''));
            } catch (Exception $eLogo) {
                // Fallback silencioso
            }
        }
    }
    if ($empresaLogo === '') {
        $empresaLogo = trim((string)($_SESSION['empresa_logo'] ?? ''));
    }
    if ($empresaLogo === '') {
        $empresaLogo = rtrim((string)base_url(), '/') . '/favicon.ico';
    }

    $empresaLogo = (static function (string $ruta): string {
        $ruta = trim(str_replace('\\', '/', $ruta));
        if ($ruta === '') {
            return '';
        }

        if (preg_match('/^(?:https?:)?\\/\\//i', $ruta) === 1 || stripos($ruta, 'data:') === 0) {
            return $ruta;
        }

        $base = rtrim((string)base_url(), '/');
        $ruta = preg_replace('#^https?://[^/]+/#i', '', $ruta) ?: $ruta;
        $posAssets = stripos($ruta, 'Assets/');
        if ($posAssets !== false) {
            return $base . '/' . ltrim(substr($ruta, $posAssets), '/');
        }

        $nombreArchivo = basename($ruta);
        return $base . '/Assets/images/Empresas/' . rawurlencode($nombreArchivo);
    })($empresaLogo);
} catch(Exception $e) {
    error_log("Error al cargar productos: " . $e->getMessage());
    $productos = [];
    $productosConImg = [];
    $proveedores = [];
    $nombreUsuario = 'Sistema';
}

// Detectar si estamos en iframe (cargado desde dashboard)
$esEnIframe = isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe' || 
              (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false);

$logoImpresionDataUri = '';
$logoImpresionPath = __DIR__ . '/../Assets/images/Empresas/mi_estrella_solo_imprimir.png';
if (is_file($logoImpresionPath)) {
    $logoImpresionDataUri = 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoImpresionPath));
}
$logoPdfDataUri = '';
$logoPdfPath = __DIR__ . '/../Assets/images/Empresas/empresa_1_20260901_185734_fe042179.png';
if (is_file($logoPdfPath)) {
    $logoPdfDataUri = 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoPdfPath));
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario - NOMBRE DE EMPRESA</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-blue: #2f4a5a;
            --secondary-blue: #3b82f6;
            --white: #FFFFFF;
            --black: #000000;
            --light-blue: rgba(47, 74, 90, 0.1);
            --font-saira: 'Saira Condensed', sans-serif;
            --danger: #dc3545;
            --success: #0B6623;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
            -webkit-appearance: textfield;
            appearance: textfield;
        }

        /* estilos específicos de acción */
        .btn-view {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--info);
            color: #fff;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-view:hover { background: #138496; }
        .btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            width: 34px;
            height: 34px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(47, 74, 90, 0.2);
        }
        .btn-edit:hover { 
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
            transform: translateY(-2px);
        }
        .btn-discount {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #6f42c1;
            color: #fff;
            border: none;
            border-radius: 8px;
            width: 34px;
            height: 34px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(111, 66, 193, 0.2);
        }
        .btn-discount:hover {
            background: #59369a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
        }
        .btn-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-blue);
            color: #fff;
            border: 2px solid rgba(255,255,255,0.18);
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            gap: 8px;
            text-transform: none;
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-save:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(11,94,215,0.18);
        }

        .modal-content {
            position: relative;
        }

        .modal-content h2 {
            margin: 0 0 10px;
            font-size: 22px;
            letter-spacing: 0.4px;
            color: var(--primary-blue);
        }

        .productos-categoria-salida-grid {
            display: grid;
            grid-template-columns: repeat(8, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
            max-height: 65vh;
            overflow-y: auto;
        }

        .categoria-salida-option {
            box-sizing: border-box;
        }

        .categoria-salida-option i {
            color: var(--primary-blue) !important;
        }

        @media (max-width: 1100px) {
            .productos-categoria-salida-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .productos-categoria-salida-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .modal-content .close {
            position: absolute;
            top: 18px;
            right: 20px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e6e9ee;
            color: var(--primary-blue);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-content .close:hover {
            background: #d1d5db;
            color: #0f1a2e;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .modal-actions .btn-edit,
        .modal-actions .btn-discount,
        .modal-actions .btn-view {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 140px;
            width: auto;
            height: auto;
            padding: 12px 18px;
            font-size: 14px;
            line-height: 1.2;
            border-radius: 8px;
            border: none;
            color: #fff;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .modal-actions .btn-edit i,
        .modal-actions .btn-discount i,
        .modal-actions .btn-view i {
            margin-right: 8px;
        }
        .modal-actions .btn-edit {
            background: var(--primary-blue);
            box-shadow: 0 8px 18px rgba(47, 74, 90, 0.18);
        }
        .modal-actions .btn-edit:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(47, 74, 90, 0.22);
        }
        .modal-actions .btn-discount {
            background: #6f42c1;
            box-shadow: 0 8px 18px rgba(111, 66, 193, 0.18);
        }
        .modal-actions .btn-discount:hover {
            background: #59369a;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(111, 66, 193, 0.22);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-body table {
            width: 100%;
            border-collapse: collapse;
            text-transform: none;
        }

        .modal-body table td {
            padding: 10px 10px;
            border-bottom: 1px solid #e6e9ee;
            vertical-align: top;
        }

        .modal-body table tr:last-child td {
            border-bottom: none;
        }

        .modal-body table td:first-child {
            width: 36%;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .modal-body table td:last-child {
            color: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            padding-top: <?php echo $esEnIframe ? '0' : '80px'; ?>;
            background-color: #f8f9fa;
            font-family: var(--font-saira);
            overflow: auto;
            min-height: 100vh;
        }

        body.modal-open {
            overflow: hidden !important;
        }

        .title_equipo {
            background: transparent;
            padding: 0.5rem 0;
            margin-bottom: 2rem;
            position: relative;
            margin-top: <?php echo $esEnIframe ? '0' : '80px'; ?>;
            padding-top: 0.2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100px;
        }

        .title_equipo h1 {
            color: var(--primary-blue);
            font-family: var(--font-saira);
            margin-top: 0;
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .title_equipo h1 i {
            color: var(--primary-blue);
            margin-right: 15px;
        }

        .button_Volver_Atras {
            position: absolute;
            left: 20px;
            top: 45px;
            background-color: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .button_Volver_Atras:hover {
            background-color: var(--primary-blue);
            color: var(--white);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 15px;
            padding-bottom: 32px;
        }

        .inventario-main-scroll {
            height: calc(100vh - 120px);
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-gutter: stable;
        }

        .inventario-main-scroll::-webkit-scrollbar {
            width: 12px;
        }

        .inventario-main-scroll::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 8px;
        }

        .inventario-main-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 8px;
            border: 2px solid #f4f6f8;
        }

        .inventario-main-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }

        /* Estadísticas */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .stats-container .stat-content p[id^="valorVentas"],
        .stats-container .stat-content p[id^="ganancia"],
        .stats-container .stat-content p[id^="efectivo"],
        .stats-container .stat-content p[id^="transferencia"] {
            font-size: clamp(16px, 2.3vw, 28px);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            line-height: 1.05;
        }

        #ventasDia table th:nth-child(n+6),
        #ventasDia table td:nth-child(n+6),
        #ventasMes table th:nth-child(n+5),
        #ventasMes table td:nth-child(n+5) {
            text-align: center;
            vertical-align: middle;
        }

        .resumen-acumulado {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: .78em;
            font-weight: 600;
            line-height: 1.1;
        }

        .acumulado-mes-resumen {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            gap: 10px;
            margin-left: auto;
        }

        .acumulado-mes-card {
            min-width: 195px;
            padding: 11px 15px;
            border: 1px solid #e4eaf0;
            border-left: 4px solid #1f3f57;
            border-radius: 9px;
            background: linear-gradient(135deg, #f8fbfd 0%, #eef4f7 100%);
            box-shadow: 0 3px 9px rgba(31, 63, 87, 0.08);
        }

        .acumulado-mes-card.ganancia {
            border-left-color: #c69214;
            background: linear-gradient(135deg, #fffdf5 0%, #fff8df 100%);
        }

        .acumulado-mes-card-label {
            display: block;
            margin-bottom: 3px;
            color: #64748b;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .35px;
        }

        .acumulado-mes-card-value {
            display: block;
            color: #1f3f57;
            font-size: 18px;
            font-weight: 900;
            line-height: 1.1;
            white-space: nowrap;
        }

        .acumulado-mes-card.ganancia .acumulado-mes-card-value {
            color: #8a6500;
        }

        @media (max-width: 700px) {
            .acumulado-mes-resumen {
                width: 100%;
                justify-content: stretch;
                margin-top: 10px;
            }

            .acumulado-mes-card {
                min-width: 0;
                flex: 1;
            }
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(47, 74, 90, 0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--primary-blue); /* icon blue */
            background: white; /* circle white */
            border: 2px solid var(--primary-blue);
        }

        /* remove individual border color rules, keep uniform blue */
        .stat-content h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-content {
            min-width: 0;
            flex: 1;
        }

        .stat-content p {
            font-size: clamp(20px, 1.9vw, 32px);
            font-weight: 700;
            color: var(--primary-blue);
        }

        #valorInventarioModal .stat-card {
            padding: 18px 16px;
            gap: 12px;
            min-width: 0;
        }

        #valorInventarioModal .stat-content {
            min-width: 0;
            flex: 1 1 auto;
        }

        #valorInventarioModal .stat-content h3 {
            margin-bottom: 6px;
            line-height: 1.2;
        }

        #valorInventarioModal .stat-content p {
            font-size: clamp(30px, 2vw + 16px, 56px);
            line-height: 1.15;
            white-space: nowrap;
            overflow: visible;
            text-overflow: unset;
            word-break: normal;
        }

        .ganancia-item {
            margin-bottom: 10px;
        }

        .ganancia-item:last-child {
            margin-bottom: 0;
        }

        .ganancia-item h4 {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 3px 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        #valorTotal {
            white-space: nowrap;
            line-height: 1.1;
            transition: font-size 0.2s ease;
        }

        #totalDiaCard {
            white-space: nowrap;
            line-height: 1.1;
            transition: font-size 0.2s ease;
        }

        #gananciaDiaCard {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            line-height: 1.1;
            transition: font-size 0.2s ease;
        }

        #totalDiaCard {
            overflow: hidden;
            text-overflow: clip;
        }

        #efectivoDia,
        #transferenciaDia,
        #gananciaDia,
        #valorVentasDia,
        #efectivoMes,
        #transferenciaMes,
        #gananciaMes,
        #valorVentasMes {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            line-height: 1.1;
            transition: font-size 0.2s ease;
        }

        /* Tabs */
        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e6e9ee;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 14px 28px;
            background: transparent;
            border: 2px solid transparent;
            color: var(--primary-blue);
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }

        .tab-btn.active {
            color: var(--primary-blue);
            border-bottom: 3px solid var(--primary-blue);
        }

        .tab-btn:hover {
            color: var(--primary-blue);
            background: rgba(47, 74, 90, 0.05);
        }

        .tab-content {
            display: none;
            overflow: visible;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        #ventasDiaModal, #ventasDiaModal * {
            text-transform: uppercase !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Cards y Tablas */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.08);
            padding: 25px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e6e9ee;
        }

        .card-header h2 {
            font-size: 22px;
            color: var(--primary-blue);
            margin: 0;
            font-weight: 600;
        }

        /* Botones */
        .btn-nuevo {
            background: var(--primary-blue);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s ease;
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .btn-nuevo:hover {
            background: #1a2d4f;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.2);
            transform: translateY(-2px);
        }

        /* Tabla */

        /* Scroll para todas las tablas (igual que usuarios.php) */
        .table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
            flex: 1;
            scrollbar-width: thin;
            min-height: 0;
            height: auto;
            position: relative;
            max-height: none;
            border-radius: 8px;
        }
        /* sales modal tables should look like usuario tables */
        #ventasDiaModal table, #ventasDiaModal th, #ventasDiaModal td {
            font-size: 12px; /* ensure consistent sizing */
        }
        /* visual separator for date groups */
        .group-date td {
            background: #bfdbfe;
            color: #1e3a8a;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            border-bottom: 1px solid #93c5fd;
        }

        .table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        .table-wrapper::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 6px;
        }
        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 6px;
            border: 2px solid #f4f6f8;
        }
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }
        .table-wrapper::-webkit-scrollbar-corner {
            background: #f4f6f8;
        }

        .table-wrapper-principal {
            height: auto;
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
            overscroll-behavior: contain;
            position: relative;
            -webkit-overflow-scrolling: touch;
            pointer-events: auto;
            touch-action: pan-y pan-x;
            scrollbar-gutter: stable;
        }

        .table-wrapper-principal table {
            min-width: 1100px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            background: var(--primary-blue);
            color: white;
            padding: 14px 10px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e6e9ee;
        }

        tr:hover {
            background-color: rgba(47, 74, 90, 0.04);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Modales */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(47, 74, 90, 0.5);
            backdrop-filter: blur(4px);
            overflow-y: auto;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
            overflow: hidden !important;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 12px 36px rgba(47, 74, 90, 0.15);
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.3s ease-out;
        }

        #entradaModal .modal-content {
            width: min(94vw, 900px);
            max-width: min(94vw, 900px);
        }

        #entradaModal {
            padding: 0 !important;
            align-items: stretch;
            justify-content: stretch;
        }

        #entradaModal .modal-content {
            width: 100% !important;
            max-width: none !important;
            height: 100% !important;
            max-height: 100% !important;
            overflow-y: auto !important;
            border-radius: 0;
        }

        #entradaModal .fila-presentacion-entrada {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        }

        @media (max-width: 700px) {
            #entradaModal .fila-presentacion-entrada {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

>>>>>>> Stashed changes
        /* Modales de métricas: tamaño tipo pantalla + scroll interno */
        #ventasDiaModal .modal-content,
        #todosProductosModal .modal-content,
        #stockTotalModal .modal-content,
        #valorInventarioModal .modal-content,
        #reordenModal .modal-content {
            width: min(96vw, 1500px);
            max-width: min(96vw, 1500px) !important;
            max-height: 90vh;
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #ventasDiaModal,
        #todosProductosModal,
        #stockTotalModal,
        #valorInventarioModal,
        #reordenModal {
            overflow: hidden;
        }

        #ventasDiaModal .modal-content {
            height: min(90vh, 760px);
        }

        #salidaModal .modal-content {
            width: min(96vw, 1020px);
            max-width: min(96vw, 1020px);
            max-height: 92vh;
            padding: 22px 22px 14px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #salidaModal .modal-header {
            margin-bottom: 12px;
            flex-shrink: 0;
        }

        #salidaModal form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            overflow-x: hidden;
            padding-right: 4px;
        }

        #salidaModal .table-wrapper {
            max-height: none !important;
            overflow-y: auto;
            overflow-x: auto;
        }

        #salidaModal .salida-carrito-group {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1 1 auto;
        }

        #salidaModal .salida-carrito-group .table-wrapper {
            flex: 1 1 auto;
            min-height: 120px;
        }

        #salidaModal .salida-pago-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            align-items: stretch;
            flex: 0 0 auto;
        }

        #salidaModal .salida-pago-grid .form-group {
            min-width: 0;
            margin: 0;
        }

        #salidaModal .salida-pago-grid select,
        #salidaModal .salida-pago-grid input {
            width: 100%;
            min-height: 42px;
            box-sizing: border-box;
        }

        #salidaModal .salida-submit {
            flex: 0 0 auto;
            margin-top: 22px;
            margin-bottom: 10px;
        }

        #salidaModal form > .form-row,
        #salidaModal form > #notasSalida {
            flex: 0 0 auto;
        }

        #salidaModal {
            padding: 0 !important;
            align-items: stretch;
            justify-content: stretch;
        }

        #salidaModal .modal-content {
            width: 100% !important;
            max-width: none !important;
            height: 100% !important;
            max-height: none !important;
            border-radius: 0;
        }

        #todosProductosModal .modal-content {
            width: 100vw;
            max-width: 100vw !important;
            height: 100vh !important;
            max-height: 100vh !important;
            border-radius: 0;
        }

        #todosProductosModal .modal-content > div {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto !important;
        }

        #todosProductosModal .table-wrapper {
            max-height: none !important;
            height: calc(100vh - 310px) !important;
        }

        .salida-total-box {
            margin-top: 10px;
            text-align: right;
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 15px;
        }

        #ventasDiaModal .modal-header,
        #todosProductosModal .modal-header,
        #stockTotalModal .modal-header,
        #valorInventarioModal .modal-header,
        #reordenModal .modal-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            padding: 24px 24px 16px 24px;
            margin-bottom: 0;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.08);
        }

        #ventasDiaModal .modal-content > div,
        #todosProductosModal .modal-content > div,
        #stockTotalModal .modal-content > div,
        #valorInventarioModal .modal-content > div,
        #reordenModal .modal-content > div {
            
            overflow: hidden;
            padding: 20px;
        }

        #ventasDiaModal .table-wrapper,
        #todosProductosModal .table-wrapper,
        #stockTotalModal .table-wrapper,
        #valorInventarioModal .table-wrapper,
        #reordenModal .table-wrapper {
            max-height: 45vh !important;
            overflow-y: auto;
            overflow-x: auto;
        }

        #ventasDiaModal,
        #todosProductosModal,
        #stockTotalModal,
        #valorInventarioModal,
        #reordenModal {
            align-items: center;
            overflow: hidden !important;
        }

        #ventasDiaModal .modal-content,
        #todosProductosModal .modal-content,
        #stockTotalModal .modal-content,
        #valorInventarioModal .modal-content,
        #reordenModal .modal-content {
            height: auto !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }

        #ventasDiaModal .modal-content > div,
        #todosProductosModal .modal-content > div,
        #stockTotalModal .modal-content > div,
        #valorInventarioModal .modal-content > div,
        #reordenModal .modal-content > div {
            overflow: visible !important;
        }

        #ventasDiaModal .table-wrapper,
        #ventasDiaModal .table-wrapper-principal,
        #todosProductosModal .table-wrapper,
        #stockTotalModal .table-wrapper,
        #valorInventarioModal .table-wrapper,
        #reordenModal .table-wrapper {
            height: auto !important;
            max-height: none !important;
            overflow-y: visible !important;
            overflow-x: auto;
        }

        #todosProductosModal .modal-content {
            width: 100vw !important;
            max-width: 100vw !important;
            height: 100vh !important;
            max-height: 100vh !important;
            border-radius: 0;
        }

        #todosProductosModal .table-wrapper {
            height: calc(100vh - 310px) !important;
            max-height: none !important;
            overflow-y: auto !important;
        }

        #entradaModal .modal-content {
            width: 100% !important;
            max-width: none !important;
            height: 100% !important;
            max-height: 100% !important;
            overflow-y: auto !important;
            border-radius: 0;
        }

        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e6e9ee;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-blue);
            font-size: 24px;
        }

        .close-btn {
            background: transparent;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .close-btn:hover {
            color: var(--primary-blue);
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 12px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e6e9ee;
            border-radius: 6px;
            font-family: var(--font-saira);
            text-transform: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(47, 74, 90, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.3s ease;
            max-width: 300px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.2);
            letter-spacing: 0.5px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        /* Estado badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-success {
            background: rgba(31, 175, 70, 0.1);
            color: #0B6623;
        }

        .badge-credit {
            background: rgba(29, 78, 216, 0.12);
            color: #1d4ed8;
        }

        .badge-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ff9800;
        }

        .badge-secondary {
            background: rgba(85, 143, 230, 0.12);
            color: #130c81;
        }

        .badge-info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }

        .producto-color-cell {
            display: grid;
            justify-items: center;
            align-content: center;
            gap: 6px;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            text-align: center;
        }

        .producto-color-name {
            display: block;
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            text-align: center;
            line-height: 1.2;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        #salidasTable.resumen-inventario-table {
            table-layout: fixed;
        }

        #resumen #salidasTable.resumen-inventario-table th,
        #resumen #salidasTable.resumen-inventario-table td {
            text-align: center !important;
            vertical-align: middle !important;
        }

        #salidasTable.resumen-inventario-table td.producto-resumen-cell {
            display: table-cell;
            text-align: center !important;
            vertical-align: middle !important;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
            padding-left: 8px;
            padding-right: 8px;
        }

        #salidasTable.resumen-inventario-table td.producto-resumen-cell > .producto-color-cell,
        #salidasTable.resumen-inventario-table td.producto-resumen-cell > .producto-color-cell > .producto-color-name {
            position: static;
            left: auto;
            right: auto;
            transform: none;
        }

        #resumen #salidasTable.resumen-inventario-table .producto-color-cell,
        #resumen #salidasTable.resumen-inventario-table .producto-color-name {
            justify-self: center;
            text-align: center !important;
        }

        .inventario-detalle-table {
            table-layout: fixed;
        }

        .inventario-detalle-table th,
        .inventario-detalle-table td {
            text-align: center !important;
            vertical-align: middle !important;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .inventario-detalle-table td.producto-nombre-cell {
            text-align: center !important;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .inventario-detalle-table td.producto-nombre-cell > strong,
        .inventario-detalle-table td.producto-nombre-cell > span {
            display: block;
            width: 100%;
            text-align: center !important;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .inventario-detalle-table tr.group-date > td {
            padding: 12px 16px;
            text-align: left !important;
            vertical-align: middle !important;
            position: relative;
            z-index: 1;
        }

        .group-date-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: flex-start;
            gap: 8px 24px;
            width: 100%;
            text-align: left;
        }

        .group-date-total,
        .group-date-metric {
            display: flex;
            flex-direction: row;
            align-items: baseline;
            gap: 6px;
            min-width: 0;
            text-align: left;
        }

        .group-date-details {
            display: contents;
        }

        .group-date-total .group-date-label {
            color: #203864;
        }

        .group-date-label {
            color: #203864;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.2;
            flex: 0 0 auto;
        }

        .group-date-value {
            color: #203864;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
            text-align: left;
            overflow-wrap: anywhere;
            flex: 0 1 auto;
        }

        .entrada-imagen-container {
            flex-direction: column;
        }

        .ventas-detalle-table {
            table-layout: fixed;
        }

        .ventas-detalle-table th,
        .ventas-detalle-table td {
            text-align: center !important;
            vertical-align: middle !important;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .ventas-fecha-hora {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            line-height: 1.25;
        }

        .ventas-fecha-hora span {
            display: block;
        }

        @media (max-width: 600px) {
            .group-date-summary {
                gap: 6px 14px;
            }

            .group-date-total,
            .group-date-metric {
                text-align: left;
            }
        }

        #salidasTable.resumen-inventario-table.con-id td.producto-resumen-cell {
            width: 18%;
        }

        #salidasTable.resumen-inventario-table.sin-id td.producto-resumen-cell {
            width: 20%;
        }

        .producto-color-chip {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid rgba(15, 23, 42, 0.15);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
            flex-shrink: 0;
            align-self: center;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .title_equipo h1 {
                font-size: 2.5rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs-container {
                flex-direction: column;
            }

            table {
                font-size: 11px;
            }

            th, td {
                padding: 8px 5px;
            }

            .modal-content {
                padding: 20px;
            }

            #entradaModal .modal-content {
                width: 98vw;
                max-width: 98vw;
            }

            #entradaModal select {
                min-width: 0 !important;
            }

            #ventasDiaModal .modal-content,
            #todosProductosModal .modal-content,
            #stockTotalModal .modal-content,
            #valorInventarioModal .modal-content,
            #reordenModal .modal-content {
                width: 98vw;
                max-width: 98vw !important;
                max-height: 92vh;
            }

            #ventasDiaModal .modal-content {
                height: 92vh;
            }

            #salidaModal .modal-content {
                width: 98vw;
                max-width: 98vw;
                max-height: 94vh;
                padding: 16px 12px 10px;
            }

            #salidaModal .table-wrapper {
                max-height: 220px !important;
            }
        }

        .scrollbar-custom::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .scrollbar-custom::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 6px;
        }

        .scrollbar-custom::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 6px;
        }

        .scrollbar-custom::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }

        /* Estilos para imágenes de productos en tablas */
        .producto-img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #d9dee6;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            background-color: #fff;
            image-rendering: auto;
            transform: translateZ(0);
        }

        .producto-img:hover {
            transform: none;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
            z-index: 10;
        }
        
        /* Contenedor de imagen para mejor alineación */
        .img-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
        }

        /* Mejorar apariencia de stat-card al hacer hover */
        .stat-card[style*="cursor: pointer"]:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* Estilos para columnas de precio y ganancia */
        table td[style*="color: #e74c3c"] {
            font-weight: 600;
        }

        table td[style*="color: #27ae60"] {
            font-weight: 600;
        }

        table td[style*="color: #f39c12"] {
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Badge para porcentaje de ganancia */
        .badge-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        
        /* icono dentro de stat-card debe ser azul para coincidir con borde */
        .inventario .stat-icon i {
            color: var(--primary-blue) !important;
        }
        /* botones de acción: fondo blanco, borde e ícono azul; al pasar, fondo azul con ícono blanco */
        .inventario .btn-action {
            background: white !important;
            color: var(--primary-blue) !important;
            border: 2px solid var(--primary-blue) !important;
            width: 40px !important;
            height: 40px !important;
            padding: 0 !important;
            font-size: 16px !important;
            border-radius: 50% !important;
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        /* Ícono sin margen para carrito */
        .btn-action i {
            margin: 0 !important;
        }
        .inventario .btn-action i {
            color: var(--primary-blue) !important;
            transition: color 0.2s ease, transform 0.2s ease;
        }
        /* encabezados de tablas de inventario: fondo blanco y texto azul */
        .inventario table th {
            background: white !important;
            color: var(--primary-blue) !important;
        }
        .inventario .btn-action:hover {
            background: var(--primary-blue) !important;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(47, 74, 90, 0.25);
        }
        .inventario .btn-action:hover i,
        .inventario .btn-action:focus i {
            color: #fff !important;
            transform: scale(1.05);
        }
        .factura-cantidad-editor .btn-action {
            width: 30px !important;
            height: 30px !important;
            min-width: 30px !important;
            min-height: 30px !important;
            padding: 0 !important;
            background: var(--primary-blue) !important;
            color: #fff !important;
            border: 2px solid var(--primary-blue) !important;
            border-radius: 6px !important;
            font-weight: 700 !important;
            font-size: 16px !important;
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: none !important;
        }
        .factura-cantidad-editor .btn-action:hover,
        .factura-cantidad-editor .btn-action:focus {
            background: var(--primary-blue) !important;
            color: #fff !important;
            transform: none !important;
            box-shadow: none !important;
        }
        /* los botones color específicos (info, warning) quedan para mostrar el color al presionar */
        .inventario .btn-info, .inventario .btn-warning {
            background: white !important;
            color: var(--primary-blue) !important;
            border: 2px solid var(--primary-blue) !important;
        }
        /* ocultar estadísticas cuando se visualiza el mes */
        #ventasMes .stats-container {
            display: none;
        }

        #diagnosticoBalanzaSalida {
            display: none !important;
        }

        .peso-salida-resumen {
            margin: 8px 0 0;
            padding: 8px 12px;
            color: #075985;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 800;
            text-align: center;
        }

        #ventasDiaModal .ventas-periodo-tabs {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }

        #ventasDiaModal .ventas-print-panel {
            width: min(100%, 600px);
            margin: 40px auto;
        }

        #ventasDiaModal .ventas-print-filters {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        #ventasDiaModal .ventas-print-field {
            display: flex;
            flex: 1 1 0;
            min-width: 0;
            flex-direction: column;
            align-items: stretch;
        }

        #ventasDiaModal .ventas-print-field label {
            margin-bottom: 6px;
            text-align: left;
        }

        #ventasDiaModal .ventas-print-filters select {
            flex: 1 1 auto;
            min-width: 0;
            margin: 0 !important;
        }

        #ventasDiaModal .ventas-print-filters select {
            max-width: 220px;
        }

        #ventasDiaModal .ventas-print-field select {
            width: 100%;
            max-width: none;
        }

        #ventasDiaModal .ventas-print-field select.modo-seleccionado,
        #ventasDiaModal .ventas-todas-action.modo-seleccionado {
            border-color: #2563eb !important;
            background-color: #dbeafe !important;
            color: #1d4ed8 !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        #ventasDiaModal .ventas-todas-action {
            flex: 0 0 auto;
            width: auto !important;
            height: auto !important;
            min-height: 44px;
            padding: 11px 20px !important;
            border-radius: 6px !important;
            font-size: 14px !important;
        }

        #ventasDiaModal .ventas-print-action {
            width: auto !important;
            min-width: 190px;
            height: auto !important;
            min-height: 44px;
            margin-top: 14px;
            padding: 11px 20px !important;
            border-radius: 6px !important;
            font-size: 14px !important;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            #ventasDiaModal .ventas-periodo-tabs {
                flex-direction: column;
            }

            #ventasDiaModal .ventas-print-filters {
                align-items: stretch;
                flex-direction: column;
            }
        }

        #ventasDiaModal .modal-content > div {
            overflow-y: auto !important;
            overflow-x: auto !important;
        }

        #ventasMes .table-wrapper-principal {
            max-height: none !important;
            overflow-y: auto !important;
            overflow-x: auto !important;
            scrollbar-width: thin !important;
        }

        #ventasMes .table-wrapper-principal::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        #ventasMes .table-wrapper-principal::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 6px;
            border: 1px solid var(--bg);
        }

        #ventasMes .table-wrapper-principal::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-blue);
        }

        .inventario-main-scroll {
            height: auto !important;
            max-height: none !important;
            overflow-y: visible !important;
            padding-bottom: 25px !important;
        }

        .inventario-main-scroll .table-wrapper,
        .inventario-main-scroll .table-wrapper-principal {
            overflow-y: visible !important;
            max-height: none !important;
            height: auto !important;
        }

        #carritoSalidaBody td,
        #movimientosTableBody td,
        #salidasTableBody td {
            font-size: 14px;
        }

        #carritoSalidaBody td:nth-child(7),
        #salidasTableBody td:first-child,
        #movimientosTableBody td:first-child {
            min-width: 125px;
            max-width: 170px;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.3;
        }

        #salidasTable,
        #movimientosTable {
            table-layout: fixed;
        }

        #salidasTableBody td:nth-child(2),
        #movimientosTableBody td:nth-child(2) {
            width: 22%;
            max-width: 220px;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        #salidasTableBody td:nth-child(3),
        #movimientosTableBody td:nth-child(3) {
            text-align: center;
        }

        /* Estilos de impresión */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .swal2-container {
                display: none !important;
            }
            
            #detallesMovimientosModal {
                position: static;
                background: white;
                border: none;
                box-shadow: none;
            }

            body {
                margin-bottom: 100px !important;
            }

            footer {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                padding: 12px 18px !important;
                background: #f8f9fa !important;
                color: #000 !important;
                border-top: 1px solid #dee2e6 !important;
                box-shadow: none !important;
                z-index: 9999 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            footer {
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 10px !important;
                flex-wrap: wrap !important;
                text-align: center !important;
            }

            footer p,
            footer span,
            footer .footer-wordmark,
            footer .footer-wordmark span {
                color: #000 !important;
            }

            footer .footer-wordmark {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
            }

            footer .footer-wordmark span {
                font-weight: 800 !important;
                letter-spacing: 0.03em !important;
            }

            footer .footer-brand-logo {
                display: inline-block !important;
                width: 16px !important;
                height: 16px !important;
                margin-right: 4px !important;
                vertical-align: middle !important;
            }
        }
        body,
        body * {
            text-transform: uppercase !important;
        }
        input[type="text"],
        input[type="search"],
        textarea,
        select,
        option,
        button {
            text-transform: uppercase !important;
        }
    </style>
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/css/skeletons.css">
    <script src="<?= htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') ?>/Assets/js/skeletons.js"></script>
</head>
<body class="inventario">
    <!-- Header -->
    <div class="title_equipo">
        <h1><i class="fas fa-boxes"></i> INVENTARIO</h1>
    </div>

    <div class="container inventario-main-scroll">

        <!-- Estadísticas -->
        <div class="stats-container">
            <div class="stat-card" onclick="mostrarModalTodosProductos()" style="cursor: pointer;">
                <div class="stat-icon blue">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-content">
                    <h3>TOTAL DE PRODUCTOS</h3>
                    <p id="totalProductos">0</p>
                </div>
            </div>

            <div class="stat-card" onclick="mostrarModalStockTotal()" style="cursor: pointer;">
                <div class="stat-icon green">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="stat-content">
                    <h3>STOCK TOTAL</h3>
                    <p id="stockTotal">0</p>
                </div>
            </div>

            <div class="stat-card" onclick="mostrarModalValorInventario()" style="cursor: pointer;">
                <div class="stat-icon purple">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>VALOR DEL INVENTARIO</h3>
                    <p id="valorTotal">$0</p>
                </div>
            </div>

            <div class="stat-card" onclick="mostrarModalReorden()" style="cursor: pointer;">
                <div class="stat-icon orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>BAJO STOCK</h3>
                    <p id="bajoStock">0</p>
                </div>
            </div>

            <div class="stat-card" onclick="mostrarResumenVentasDia()" style="cursor: pointer;">
                <div class="stat-icon gold">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-content">
                    <h3>GANANCIAS</h3>
                    <div class="ganancia-item">
                        <h4>VENTA DEL DÍA</h4>
                        <p id="totalDiaCard">$0</p>
                    </div>
                    <div class="ganancia-item">
                        <h4>GANANCIA DEL DÍA</h4>
                        <p id="gananciaDiaCard">$0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search bar for inventory -->
        <div style="margin: 10px 0; display: flex; justify-content: flex-end; gap: 8px; align-items: center; flex-wrap: wrap;">
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--primary-blue); pointer-events: none; font-size: 14px;"></i>
                <input type="text" id="filtroResumen" placeholder="BUSCAR PRODUCTO..." autocomplete="off" style="width:250px; padding:6px 35px 6px 8px; font-size:12px; border-radius: 4px; border: 1px solid #ccc; text-transform:uppercase;">
                <div id="resultadosFiltroInventario" style="display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:6px; background:#fff; box-shadow:0 8px 20px rgba(31,41,55,.12); z-index:100;"></div>
            </div>
        </div>
        <!-- Tabs -->
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:6px;">
            <div class="tabs-container" style="margin:0; flex:1 1 auto; min-width:0;">
                <button class="tab-btn active" onclick="cambiarTab(this, 'resumen')">
                    <i class="fas fa-chart-bar"></i> RESUMEN
                </button>
                <button class="tab-btn" onclick="cambiarTab(this, 'entradas')">
                    <i class="fas fa-arrow-down"></i> ENTRADAS
                </button>
                <button class="tab-btn" onclick="cambiarTab(this, 'salidas')">
                    <i class="fas fa-arrow-up"></i> SALIDAS
                </button>
                <button class="tab-btn" onclick="cambiarTab(this, 'movimientos')">
                    <i class="fas fa-exchange-alt"></i> MOVIMIENTOS
                </button>
            </div>
        </div>

        <!-- TAB: RESUMEN -->
        <div id="resumen" class="tab-content active">
            <div class="card">
                <div class="inventory-list-toolbar" data-inventory-toolbar="resumen" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <input type="search" class="inventory-list-search" placeholder="BUSCAR PRODUCTO..." style="width:min(100%,260px);padding:6px 9px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;">
                    <select class="inventory-page-size" aria-label="Registros por página" style="width:auto;padding:5px 7px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;background:#fff;color:#2f4a5a;"><option>25</option><option selected>50</option><option>100</option><option>200</option></select>
                    <div class="inventory-pagination" style="display:flex;gap:6px;"></div>
                </div>
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <h2><i class="fas fa-list"></i> RESUMEN DE INVENTARIO</h2>
                    <?php if ($esSuperAdminGlobalInventario || $esAdministradorContexto): ?>
                        <button type="button" onclick="verificarYAlertarProductosCriticosYUrgentes()" title="Mostrar alerta de stock cero" aria-label="Mostrar alerta de stock cero" style="width:34px;height:34px;flex:0 0 34px;border:0;border-radius:50%;background:#d33;color:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(211,51,51,.25);">
                            <i class="fas fa-triangle-exclamation"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-wrapper scrollbar-custom">
                    <table id="salidasTable" class="resumen-inventario-table <?= $puedeVerID ? 'con-id' : 'sin-id' ?>" style="min-width: 900px;">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><i class="fas fa-image"></i></th>
                                <?php if ($puedeVerID): ?><th style="width: 58px;"><i class="fas fa-hashtag"></i> ID</th><?php endif; ?>
                                <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                <th><i class="fas fa-box"></i> PRODUCTO</th>
                                <th><i class="fas fa-tags"></i> CATEGORÍA</th>
                                <th><i class="fas fa-boxes"></i> STOCK</th>
                                <th><i class="fas fa-dollar-sign"></i> PRECIO</th>
                                <th><i class="fas fa-percent"></i> % GANANCIA</th>
                                <th><i class="fas fa-chart-line"></i> VENTAS 30D</th>
                                <th><i class="fas fa-arrow-alt-circle-up"></i> ENTRADAS 30D</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="resumenTableBody">
                            <tr><td colspan="<?= $puedeVerID ? 11 : 10 ?>" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: ENTRADAS -->
        <div id="entradas" class="tab-content">
            <div class="card">
                <div class="inventory-list-toolbar" data-inventory-toolbar="entradas" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <input type="search" class="inventory-list-search" placeholder="BUSCAR ENTRADA..." style="width:min(100%,260px);padding:6px 9px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;">
                    <select class="inventory-page-size" aria-label="Registros por página" style="width:auto;padding:5px 7px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;background:#fff;color:#2f4a5a;"><option>25</option><option selected>50</option><option>100</option><option>200</option></select>
                    <div class="inventory-pagination" style="display:flex;gap:6px;"></div>
                </div>
                <div class="card-header">
                    <h2><i class="fas fa-arrow-down"></i> ENTRADAS DE INVENTARIO</h2>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <?php if ($esSuperAdminGlobalInventario): ?>
                        <button type="button" class="btn-nuevo" style="padding: 8px 12px; background: #3b82f6; border-color: #3b82f6;" onclick="confirmarReinicioInventario('entradas_inventario')">
                            <i class="fas fa-broom"></i> REINICIAR
                        </button>
                        <button type="button" class="btn-nuevo" style="padding: 8px 12px; background: #64748b; border-color: #64748b;" onclick="confirmarDeshacerReinicioInventario('entradas_inventario')">
                            <i class="fas fa-undo"></i> DESHACER
                        </button>
                        <?php endif; ?>
                        <?php if ($tienePermisoCrear): ?>
                        <button class="btn-nuevo" onclick="abrirModalEntrada()">
                            <i class="fas fa-plus"></i> REGISTRAR ENTRADA
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-wrapper scrollbar-custom">
                    <table id="movimientosTable" class="inventario-detalle-table" style="min-width: 900px;">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><i class="fas fa-image"></i></th>
                                <?php if ($puedeVerID): ?><th style="width: 58px;"><i class="fas fa-hashtag"></i> ID</th><?php endif; ?>
                                <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                <th><i class="fas fa-box"></i> PRODUCTO</th>
                                <th><i class="fas fa-sort-amount-up"></i> CANTIDAD</th>
                                <th><i class="fas fa-dollar-sign"></i> PRECIO COMPRA</th>
                                <th><i class="fas fa-truck"></i> PROVEEDOR</th>
                                <th><i class="fas fa-calendar-check"></i> FECHA VENCIMIENTO</th>
                                <th><i class="fas fa-calendar-alt"></i> FECHA/HORA</th>
                                <th><i class="fas fa-user"></i> USUARIO</th>
                            </tr>
                        </thead>
                        <tbody id="entradasTableBody">
                            <tr><td colspan="<?= $puedeVerID ? 10 : 9 ?>" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: SALIDAS -->
        <div id="salidas" class="tab-content">
            <div class="card">
                <div class="inventory-list-toolbar" data-inventory-toolbar="salidas" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <input type="search" class="inventory-list-search" placeholder="BUSCAR SALIDA..." style="width:min(100%,260px);padding:6px 9px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;">
                    <select class="inventory-page-size" aria-label="Registros por página" style="width:auto;padding:5px 7px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;background:#fff;color:#2f4a5a;"><option>25</option><option selected>50</option><option>100</option><option>200</option></select>
                    <div class="inventory-pagination" style="display:flex;gap:6px;"></div>
                </div>
                <div class="card-header">
                    <h2><i class="fas fa-arrow-up"></i> SALIDAS DE INVENTARIO</h2>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <?php if ($esSuperAdminGlobalInventario): ?>
                        <button type="button" class="btn-nuevo" style="padding: 8px 12px; background: #3b82f6; border-color: #3b82f6;" onclick="confirmarReinicioInventario('salidas_inventario')">
                            <i class="fas fa-broom"></i> REINICIAR
                        </button>
                        <button type="button" class="btn-nuevo" style="padding: 8px 12px; background: #64748b; border-color: #64748b;" onclick="confirmarDeshacerReinicioInventario('salidas_inventario')">
                            <i class="fas fa-undo"></i> DESHACER
                        </button>
                        <?php endif; ?>
                        <?php if ($tienePermisoCrear): ?>
                        <button class="btn-nuevo" type="button" onclick="abrirModalProductoDanado()">
                            <i class="fas fa-apple-whole"></i> PRODUCTOS DAÑADOS
                        </button>
                        <button class="btn-nuevo" type="button" style="font-size:12px; padding:12px 14px; min-height:40px;" onclick="abrirModalProductoDanado('no_perecederos')">
                            <i class="fas fa-lightbulb"></i> PRODUCTOS DAÑADOS NO PERECEDEROS
                        </button>
                        <button class="btn-nuevo" onclick="abrirModal('salidaModal')">
                            <i class="fas fa-plus"></i> REGISTRAR SALIDA
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-wrapper scrollbar-custom">
                    <table class="inventario-detalle-table tabla-salidas-inventario" style="min-width: 900px;">
                        <thead>
                            <tr>
                                <th><i class="fas fa-receipt"></i> VENTA</th>
                                <th><i class="fas fa-box-open"></i> PRODUCTOS</th>
                                <th><i class="fas fa-sort-amount-down"></i> UNIDADES</th>
                                <th><i class="fas fa-dollar-sign"></i> TOTAL</th>
                                <th><i class="fas fa-tags"></i> TIPO</th>
                                <th><i class="fas fa-credit-card"></i> TIPO DE PAGO</th>
                                <th><i class="fas fa-map-marker-alt"></i> ORIGEN</th>
                                <th><i class="fas fa-calendar-alt"></i> FECHA/HORA</th>
                                <th><i class="fas fa-user"></i> USUARIO</th>
                                <th><i class="fas fa-print"></i> IMPRIMIR</th>
                            </tr>
                        </thead>
                        <tbody id="salidasTableBody">
                            <tr><td colspan="10" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: MOVIMIENTOS -->
        <div id="movimientos" class="tab-content">
            <div class="card">
                <div class="inventory-list-toolbar" data-inventory-toolbar="movimientos" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <input type="search" class="inventory-list-search" placeholder="BUSCAR MOVIMIENTO..." style="width:min(100%,260px);padding:6px 9px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;">
                    <select class="inventory-page-size" aria-label="Registros por página" style="width:auto;padding:5px 7px;font-size:11px;border:1px solid #2f4a5a;border-radius:8px;background:#fff;color:#2f4a5a;"><option>25</option><option selected>50</option><option>100</option><option>200</option></select>
                    <div class="inventory-pagination" style="display:flex;gap:6px;"></div>
                </div>
                <div class="card-header">
                    <h2><i class="fas fa-exchange-alt"></i> HISTORIAL DE MOVIMIENTOS</h2>
                    <?php if ($esSuperAdminGlobalInventario): ?>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn-nuevo" style="padding: 8px 12px; background: #3b82f6; border-color: #3b82f6;" onclick="confirmarReinicioInventario('movimientos_inventario')">
                            <i class="fas fa-broom"></i> REINICIAR
                        </button>
                        <button type="button" class="btn-nuevo" style="padding: 8px 12px; background: #64748b; border-color: #64748b;" onclick="confirmarDeshacerReinicioInventario('movimientos_inventario')">
                            <i class="fas fa-undo"></i> DESHACER
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="table-wrapper scrollbar-custom">
                    <table class="inventario-detalle-table tabla-movimientos-inventario" style="min-width: 900px;">
                        <thead>
                            <tr>
                                <th><i class="fas fa-receipt"></i> VENTA</th>
                                <th><i class="fas fa-box-open"></i> PRODUCTOS</th>
                                <th><i class="fas fa-sort-amount-down"></i> UNIDADES</th>
                                <th><i class="fas fa-tags"></i> TIPO</th>
                                <th><i class="fas fa-credit-card"></i> TIPO DE PAGO</th>
                                <th><i class="fas fa-map-marker-alt"></i> ORIGEN</th>
                                <th><i class="fas fa-calendar-alt"></i> FECHA/HORA</th>
                                <th><i class="fas fa-user"></i> USUARIO</th>
                                <th><i class="fas fa-print"></i> IMPRIMIR</th>
                            </tr>
                        </thead>
                        <tbody id="movimientosTableBody">
                            <tr><td colspan="9" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: NECESIDAD DE REORDEN -->
        </div>

    </div>

    <!-- MODAL: DETALLES DE MOVIMIENTOS AGRUPADOS -->
    <div id="detallesMovimientosModal" class="modal">
        <div class="modal-content" style="width: min(95vw, 1100px); max-width: 1100px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h2 id="detallesModalTitle"><i class="fas fa-file-invoice"></i> DETALLES DE MOVIMIENTOS</h2>
                <button class="close-btn" onclick="cerrarModal('detallesMovimientosModal')">&times;</button>
            </div>
            <div class="modal-body" id="detallesMovimientosContent" style="padding: 20px;">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>

    <!-- MODAL: NUEVA ENTRADA -->
    <div id="entradaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-inbox"></i> NUEVA ENTRADA DE INVENTARIO</h2>
                <button class="close-btn" onclick="cerrarModal('entradaModal')">&times;</button>
            </div>
            <form autocomplete="off" onsubmit="registrarEntrada(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="productoEntrada"><i class="fas fa-box"></i> PRODUCTO *</label>
                        <div style="position:relative;z-index:30;width:100%;">
                            <div style="position:relative; display:flex; align-items:center; width:100%; min-height:42px; border:1px solid #d0d7de; border-radius:8px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(15, 23, 42, 0.04);">
                                <input type="text" id="buscarProductoEntrada" placeholder="BUSCAR PRODUCTO..." autocomplete="off" style="flex:1; border:none; outline:none; background:transparent; padding:11px 12px; font-size:14px; color:#1f2937; min-width:0; text-transform:uppercase;">
                                <span style="display:flex; align-items:center; justify-content:center; width:42px; min-width:42px; height:100%; color:#667085; background:#f8fafc; border-left:1px solid #e6e9ee; font-size:15px;"><i class="fas fa-search"></i></span>
                            </div>
                            <div id="productoEntradaSearchResults" style="display:none; position:absolute; left:0; top:calc(100% + 6px); width:100%; max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:8px; background:#fff; box-shadow:0 10px 24px rgba(31,41,55,0.08); z-index:60;">
                                <?php foreach ($productosConImg as $prod): 
                                    $imgFileEntrada = htmlspecialchars($prod->imagen ?? '');
                                    $codigoDisplay = $prod->codigo ? " [{$prod->codigo}]" : "";
                                ?>
                                    <button type="button" class="producto-search-option" data-select-id="productoEntrada" data-id="<?= $prod->id ?>" data-name="<?= htmlspecialchars(strtoupper($prod->nombre)) ?><?= htmlspecialchars($codigoDisplay) ?>" data-imagen="<?= $imgFileEntrada ?>" data-codigo="<?= $prod->codigo ?>" data-barcode="<?= htmlspecialchars((string)($prod->codigo_barras ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-venta-por-kilo="<?= (int)($prod->venta_por_kilo ?? 0) ?>" data-requiere-vencimiento="<?= (int)($prod->requiere_vencimiento ?? 0) ?>" data-categoria-nombre="<?= htmlspecialchars((string)($prod->categoria_nombre ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="display:block; width:100%; text-align:left; border:none; background:#fff; padding:10px 12px; font-size:14px; color:#1f2937; cursor:pointer; border-bottom:1px solid #f1f5f9; text-transform:uppercase;">
                                        <?= strtoupper($prod->nombre) ?><?= $codigoDisplay ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <select id="productoEntrada" style="display:none; width:100%; min-width:290px; background-image: url(); background-repeat: no-repeat; background-position: 10px center; background-size: 30px 30px; padding-left: 50px;">
                                <option value="" data-imagen="" data-codigo="">-- SELECCIONAR PRODUCTO --</option>
                                <?php foreach ($productosConImg as $prod): 
                                    $imgFileEntrada = htmlspecialchars($prod->imagen ?? '');
                                    $codigoDisplay = $prod->codigo ? " [{$prod->codigo}]" : "";
                                ?>
                                <option value="<?= $prod->id ?>" data-imagen="<?= $imgFileEntrada ?>" data-codigo="<?= $prod->codigo ?>" data-barcode="<?= htmlspecialchars((string)($prod->codigo_barras ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-venta-por-kilo="<?= (int)($prod->venta_por_kilo ?? 0) ?>" data-requiere-vencimiento="<?= (int)($prod->requiere_vencimiento ?? 0) ?>" data-categoria-nombre="<?= htmlspecialchars((string)($prod->categoria_nombre ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= strtoupper($prod->nombre) ?><?= $codigoDisplay ?></option>
                                <?php 
                                    endforeach;
                                ?>
                            </select>
                        </div>
                        <div id="productoEntradaPreview" style="margin-top: 10px; text-align: center;"></div>
                        <input type="hidden" id="codigoProductoEntrada">
                    </div>
                    <div class="form-group">
                        <label for="proveedorEntrada"><i class="fas fa-truck"></i> PROVEEDOR *</label>
                        <div style="position:relative;z-index:30;width:100%;">
                            <div style="position:relative; display:flex; align-items:center; width:100%; min-height:42px; border:1px solid #d0d7de; border-radius:8px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(15, 23, 42, 0.04);">
                                <input type="text" id="buscarProveedorEntrada" placeholder="BUSCAR PROVEEDOR..." autocomplete="off" style="flex:1; border:none; outline:none; background:transparent; padding:11px 12px; font-size:14px; color:#1f2937; min-width:0; text-transform:uppercase;">
                                <span style="display:flex; align-items:center; justify-content:center; width:42px; min-width:42px; height:100%; color:#667085; background:#f8fafc; border-left:1px solid #e6e9ee; font-size:15px;"><i class="fas fa-search"></i></span>
                            </div>
                            <div id="proveedorEntradaSearchResults" style="display:none; position:absolute; left:0; top:calc(100% + 6px); width:100%; max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:8px; background:#fff; box-shadow:0 10px 24px rgba(31,41,55,0.08); z-index:60;">
                                                                <button type="button" class="proveedor-search-option" data-select-id="proveedorEntrada" data-id="GENERAL" data-name="GENERAL" style="display:block; width:100%; text-align:left; border:none; background:#fff; padding:10px 12px; font-size:14px; color:#1f2937; cursor:pointer; border-bottom:1px solid #f1f5f9; text-transform:uppercase;">GENERAL</button>
                                <button type="button" class="proveedor-search-option" data-select-id="proveedorEntrada" data-id="__OTRO__" data-name="NUEVO PROVEEDOR" style="display:block; width:100%; text-align:left; border:none; background:#fff; padding:10px 12px; font-size:14px; color:#1f2937; cursor:pointer; border-bottom:1px solid #f1f5f9; text-transform:uppercase;">
                                    + NUEVO PROVEEDOR
                                </button>
                            </div>
                            <select id="proveedorEntrada" style="display:none; width:100%; min-width:290px;" aria-hidden="true" tabindex="-1">
                              <option value="">-- SELECCIONAR PROVEEDOR --</option>
                                                            <option value="GENERAL">GENERAL</option>
                              <option value="__OTRO__">+ NUEVO PROVEEDOR</option>
                            </select>
                        </div>
                        <input type="text" id="proveedorEntradaOtro" placeholder="Escribe el nuevo proveedor" autocomplete="off" style="display:none; margin-top:10px;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cantidadEntrada"><i class="fas fa-cubes"></i> <span id="unidadEntradaLabel">CANTIDAD</span> *</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" onclick="decrementarCantidad('cantidadEntrada')" style="padding: 6px 12px; background: #2c3e50; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">−</button>
                            <input type="number" id="cantidadEntrada" min="1" step="1" value="1" autocomplete="off" required style="width: 80px; text-align: center; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <button type="button" onclick="incrementarCantidad('cantidadEntrada')" style="padding: 6px 12px; background: #2c3e50; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">+</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="precioCompra"><i class="fas fa-tag"></i> PRECIO COMPRA *</label>
                        <input type="number" id="precioCompra" step="0.01" min="0" autocomplete="off" required onchange="calcularPrecioVenta()" oninput="calcularPrecioVenta()">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="porcentajeGanancia"><i class="fas fa-percent"></i> % GANANCIA *</label>
                        <input type="number" id="porcentajeGanancia" step="0.01" min="0" value="25" autocomplete="off" required onchange="calcularPrecioVenta()" oninput="calcularPrecioVenta()">
                    </div>
                    <div class="form-group">
                        <label for="precioVentaMostrado"><i class="fas fa-dollar-sign"></i> PRECIO VENTA (CALCULADO)</label>
                        <input type="number" id="precioVentaMostrado" step="0.01" min="0" readonly autocomplete="off" style="background: #f8f9fa; cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="lote"><i class="fas fa-barcode"></i> LOTE</label>
                        <input type="text" id="lote" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="fechaVencimiento"><i class="fas fa-calendar"></i> FECHA VENCIMIENTO</label>
                        <input type="date" id="fechaVencimiento">
                    </div>
                </div>

                <div class="form-row" id="grupoPresentacionEntrada" style="display:none;">
                    <div class="form-group">
                        <label for="presentacionEntrada"><i class="fas fa-boxes-stacked"></i> PRESENTACIÓN *</label>
                        <select id="presentacionEntrada" style="width:100%;"></select>
                        <small id="ayudaPresentacionEntrada" style="display:block; margin-top:6px; color:#475569; font-weight:700; text-transform:uppercase;"></small>
                    </div>
                </div>

                <div id="editorPresentacionesEntrada" style="display:none; margin:10px 0; padding:12px; border:1px solid #dbe4ec; border-radius:8px; background:#fafcff; overflow:hidden;">
                    <strong style="display:block; margin-bottom:8px; color:#2f4a5a;"><i class="fas fa-boxes-stacked"></i> PRESENTACIONES DEL PRODUCTO</strong>
                    <div id="filasPresentacionesEntrada" style="display:flex; flex-direction:column; gap:8px; min-width:0;"></div>
                    <button type="button" onclick="guardarPresentacionesEntrada()" style="margin-top:10px; padding:8px 12px; border:0; background:#1d4ed8; color:#fff; border-radius:6px; font-weight:700; cursor:pointer;"><i class="fas fa-save"></i> GUARDAR PRESENTACIONES</button>
                </div>

                <textarea id="notasEntrada" hidden></textarea>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> GUARDAR ENTRADA</button>
            </form>
        </div>
    </div>

    <!-- MODAL: PRODUCTO DAÑADO -->
    <div id="productoDanadoModal" class="modal">
        <div class="modal-content" style="max-width:620px;">
            <div class="modal-header">
                <h2 id="productoDanadoTitulo"><i class="fas fa-triangle-exclamation"></i> PRODUCTO DAÑADO</h2>
                <button class="close-btn" onclick="cerrarModal('productoDanadoModal')">&times;</button>
            </div>
            <form id="formProductoDanado" autocomplete="off" onsubmit="registrarProductoDanado(event)">
                <div class="form-group">
                    <label for="productoDanado"><i class="fas fa-box"></i> PRODUCTO *</label>
                    <div style="position:relative; z-index:30;">
                        <div style="display:flex; align-items:center; height:42px; border:1px solid #d0d7de; border-radius:8px; background:#fff; overflow:hidden;">
                            <input type="text" id="buscarProductoDanado" placeholder="BUSCAR O ESCANEAR CÓDIGO DE BARRAS..." autocomplete="off" style="flex:1; border:none; outline:none; background:transparent; padding:11px 12px; font-size:14px; color:#1f2937; text-transform:uppercase;">
                            <span style="display:flex; align-items:center; justify-content:center; width:42px; height:100%; color:#667085; background:#f8fafc; border-left:1px solid #e6e9ee;"><i class="fas fa-barcode"></i></span>
                        </div>
                        <div id="productoDanadoSearchResults" style="display:none; position:absolute; left:0; right:0; top:calc(100% + 6px); max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:8px; background:#fff; box-shadow:0 10px 24px rgba(31,41,55,0.08); z-index:60;"></div>
                    </div>
                    <select id="productoDanado" required onchange="actualizarCantidadProductoDanado()" style="display:none;">
                        <option value="">-- SELECCIONAR PRODUCTO --</option>
                        <?php foreach ($productosConImg as $prod):
                            $categoriaDanado = mb_strtolower(trim((string)($prod->categoria_nombre ?? '')), 'UTF-8');
                            $categoriaDanado = strtr($categoriaDanado, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
                            $grupoDanado = in_array($categoriaDanado, ['frutas', 'verduras', 'carnicos y refrigerados'], true)
                                ? 'perecederos'
                                : 'no_perecederos';
                            $stockDanado = max(0, (float)($prod->stock ?? 0));
                            if ($stockDanado <= 0) continue;
                        ?>
                        <option value="<?= (int)$prod->id ?>" data-grupo="<?= $grupoDanado ?>" data-stock="<?= $stockDanado ?>" data-kilo="<?= (int)($prod->venta_por_kilo ?? 0) ?>" data-barcode="<?= htmlspecialchars((string)($prod->codigo_barras ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-codigo="<?= htmlspecialchars((string)($prod->codigo ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-categoria-nombre="<?= htmlspecialchars((string)($prod->categoria_nombre ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(strtoupper((string)$prod->nombre) . ' [' . strtoupper((string)($prod->categoria_nombre ?? '')) . '] — STOCK ' . $stockDanado, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cantidadProductoDanado"><i class="fas fa-scale-balanced"></i> CANTIDAD DAÑADA *</label>
                    <input type="number" id="cantidadProductoDanado" min="1" step="1" required>
                    <small id="stockProductoDanado" style="display:block;margin-top:6px;color:#64748b;font-weight:700;"></small>
                </div>
                <div class="form-group">
                    <label for="notasProductoDanado"><i class="fas fa-sticky-note"></i> NOTA</label>
                    <textarea id="notasProductoDanado" rows="3" placeholder="MOTIVO O DETALLE DEL DAÑO"></textarea>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-box-archive"></i> DESCONTAR PRODUCTO DAÑADO</button>
            </form>
        </div>
    </div>

    <!-- MODAL: NUEVA SALIDA -->
    <div id="salidaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-box-open"></i> NUEVA SALIDA DE INVENTARIO</h2>
                <button class="close-btn" onclick="cerrarModal('salidaModal')">&times;</button>
            </div>
            <form autocomplete="off" onsubmit="registrarSalida(event)">
                <div class="form-group">
                    <div style="display:grid; grid-template-columns:minmax(0,450px) minmax(0,1fr); align-items:start; gap:10px; width:100%; margin:0 0 8px;">
                        <label for="productoSalida" style="display:flex; align-items:center; flex:0 0 auto; margin:0;"><i class="fas fa-box"></i> PRODUCTO *</label>
                        <span style="display:flex; align-items:center; gap:5px; margin:0; transform:translateX(20px); color:#475569; font-size:14px; font-weight:700; text-align:left; text-transform:uppercase;"><i class="fas fa-store"></i> FRUTAS, VERDURAS, CÁRNICOS Y REFRIGERADOS</span>
                    </div>
                    <div style="display:grid; grid-template-columns:minmax(0,450px) minmax(0,1fr); align-items:end; gap:10px; width:100%;">
                    <div style="position:relative; z-index:30; min-width:0;">
                        <div style="position:relative; display:flex; align-items:center; width:100%; height:42px; box-sizing:border-box; border:1px solid #d0d7de; border-radius:8px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(15, 23, 42, 0.04);">
                            <input type="text" id="buscarProductoSalida" placeholder="BUSCAR PRODUCTO..." autocomplete="off" style="flex:1; border:none; outline:none; background:transparent; padding:11px 12px; font-size:14px; color:#1f2937; min-width:0; text-transform:uppercase;">
                            <span style="display:flex; align-items:center; justify-content:center; width:42px; min-width:42px; height:100%; color:#667085; background:#f8fafc; border-left:1px solid #e6e9ee; font-size:15px;"><i class="fas fa-search"></i></span>
                        </div>
                        <div id="productoSalidaSearchResults" style="display:none; position:absolute; left:0; top:calc(100% + 6px); width:100%; max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:8px; background:#fff; box-shadow:0 10px 24px rgba(31,41,55,0.08); z-index:60;">
                            <?php foreach ($productosConImg as $prod): 
                                $imgFileSalida = htmlspecialchars($prod->imagen ?? '');
                                $codigoDisplay = $prod->codigo ? " [{$prod->codigo}]" : "";
                                $descPct = (float)($prod->descuento_porcentaje ?? 0);
                                $precioBas = (float)($prod->precio ?? 0);
                                $precioFinalCalc = $precioBas;
                                $stockActual = (float)($prod->stock ?? 0);
                                $stockReservado = (int)($prod->reservado ?? 0);
                                $stockDisponible = max(0, $stockActual - $stockReservado);
                                if ($stockDisponible <= 0) continue;
                            ?>
                                <button type="button" class="producto-search-option" data-select-id="productoSalida" data-id="<?= $prod->id ?>" data-name="<?= htmlspecialchars(strtoupper($prod->nombre)) ?><?= htmlspecialchars($codigoDisplay) ?>" data-imagen="<?= $imgFileSalida ?>" data-codigo="<?= $prod->codigo ?>" data-barcode="<?= htmlspecialchars((string)($prod->codigo_barras ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-categoria-id="<?= (int)($prod->categoria_id ?? 0) ?>" data-categoria-nombre="<?= htmlspecialchars($prod->categoria_nombre ?? 'SIN CATEGORÍA', ENT_QUOTES, 'UTF-8') ?>" data-venta-por-kilo="<?= (int)($prod->venta_por_kilo ?? 0) ?>" data-precio="<?= $precioBas ?>" data-descuento="<?= $descPct ?>" data-precio-final="<?= $precioFinalCalc ?>" data-stock="<?= $stockDisponible ?>" data-stock-real="<?= $stockActual ?>" data-stock-reservado="<?= $stockReservado ?>" style="display:block; width:100%; text-align:left; border:none; background:#fff; padding:10px 12px; font-size:14px; color:#1f2937; cursor:pointer; border-bottom:1px solid #f1f5f9; text-transform:uppercase;">
                                    <?= strtoupper($prod->nombre) ?><?= $codigoDisplay ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <select id="productoSalida" style="display:none; background-image: url(); background-repeat: no-repeat; background-position: 10px center; background-size: 30px 30px; padding-left: 50px;">
                            <option value="" data-imagen="" data-codigo="">-- SELECCIONAR PRODUCTO --</option>
                            <?php foreach ($productosConImg as $prod): 
                                $imgFileSalida = htmlspecialchars($prod->imagen ?? '');
                                $codigoDisplay = $prod->codigo ? " [{$prod->codigo}]" : "";
                                $descPct = (float)($prod->descuento_porcentaje ?? 0);
                                $precioBas = (float)($prod->precio ?? 0);
                                $precioFinalCalc = $precioBas;
                                $stockActual = (float)($prod->stock ?? 0);
                                $stockReservado = (int)($prod->reservado ?? 0);
                                $stockDisponible = max(0, $stockActual - $stockReservado);
                            ?>
                                <option value="<?= $prod->id ?>" data-nombre="<?= htmlspecialchars(strtoupper($prod->nombre), ENT_QUOTES, 'UTF-8') ?>" data-imagen="<?= $imgFileSalida ?>" data-codigo="<?= $prod->codigo ?>" data-barcode="<?= htmlspecialchars((string)($prod->codigo_barras ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-categoria-id="<?= (int)($prod->categoria_id ?? 0) ?>" data-categoria-nombre="<?= htmlspecialchars($prod->categoria_nombre ?? 'SIN CATEGORÍA', ENT_QUOTES, 'UTF-8') ?>" data-venta-por-kilo="<?= (int)($prod->venta_por_kilo ?? 0) ?>" data-precio="<?= $precioBas ?>" data-descuento="<?= $descPct ?>" data-precio-final="<?= $precioFinalCalc ?>" data-stock="<?= $stockDisponible ?>" data-stock-real="<?= $stockActual ?>" data-stock-reservado="<?= $stockReservado ?>">
                                    <?= strtoupper($prod->nombre) ?><?= $codigoDisplay ?><?= $stockDisponible <= 0 ? ' [SIN STOCK]' : '' ?>
                                </option>
                            <?php 
                                endforeach;
                            ?>
                        </select>
                    </div>
                    <div id="categoriasSalidaPanel" style="display:flex; flex-wrap:wrap; align-items:center; gap:2px; margin:0; min-width:0; transform:translateX(20px);">
                        <button type="button" class="categoria-salida-option" data-categoria-id="frutas" onclick="abrirProductosCategoriaSalida('frutas', 'FRUTAS')" style="height:46px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:5px; background:#fff; color:#1e293b; cursor:pointer; font-size:12px; font-weight:700; text-transform:uppercase;"><i class="fas fa-apple-whole"></i> FRUTAS</button>
                        <button type="button" class="categoria-salida-option" data-categoria-id="verduras" onclick="abrirProductosCategoriaSalida('verduras', 'VERDURAS')" style="height:46px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:5px; background:#fff; color:#1e293b; cursor:pointer; font-size:12px; font-weight:700; text-transform:uppercase;"><i class="fas fa-carrot"></i> VERDURAS</button>
                        <button type="button" class="categoria-salida-option" data-categoria-id="carnicos-refrigerados" onclick="abrirProductosCategoriaSalida('carnicos-refrigerados', 'CÁRNICOS Y REFRIGERADOS')" style="height:46px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:5px; background:#fff; color:#1e293b; cursor:pointer; font-size:12px; font-weight:700; text-transform:uppercase;"><i class="fas fa-drumstick-bite"></i> CÁRNICOS Y REFRIGERADOS</button>
                    </div>
                    </div>
                    <input type="hidden" id="codigoProductoSalida">
                    <div id="grupoPresentacionSalida" style="display:none; margin-top:10px; flex-direction:column; gap:6px; align-items:flex-start;">
                        <label for="presentacionSalida" style="margin:0;"><i class="fas fa-boxes-stacked"></i> PRESENTACIÓN *</label>
                        <select id="presentacionSalida" style="min-width:260px; padding:8px; border:1px solid #ccc; border-radius:4px; text-transform:uppercase;"></select>
                        <small id="ayudaPresentacionSalida" style="color:#475569; font-weight:700; text-transform:uppercase;"></small>
                    </div>
                    <div id="productoSalidaDetalle" style="display:none; align-items:flex-start; gap:12px; margin-top:12px; padding:10px 0; border-top:1px solid #e2e8f0;">
                        <div style="display:flex; flex-direction:column; align-items:flex-start; gap:8px;">
                            <div class="form-group" style="margin:0; min-width:190px;">
                                <label for="cantidadSalida" style="margin-bottom:5px;"><i class="fas fa-scale-balanced"></i> <span id="unidadSalidaLabel">CANTIDAD</span> *</label>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <button type="button" onclick="decrementarCantidad('cantidadSalida')" style="width:32px; height:28px; padding:0; background:#26384d; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">−</button>
                                    <input type="number" id="cantidadSalida" min="1" step="1" value="1" autocomplete="off" required style="width:80px; height:28px; text-align:center; padding:4px; border:1px solid #cbd5e1; border-radius:4px;">
                                    <button type="button" onclick="incrementarCantidad('cantidadSalida')" style="width:32px; height:28px; padding:0; background:#26384d; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">+</button>
                                </div>
                                <small id="pesoSalidaResumen" style="display:none;"> </small>
                            </div>
                            <button type="button" id="agregarProductoSalidaBtn" class="btn-submit" onclick="agregarProductoSalida()" style="width:160px; height:28px; margin:0; padding:0 4px; white-space:nowrap; font-size:11px;"><i class="fas fa-cart-plus"></i> AGREGAR PRODUCTO</button>
                        </div>
                        <div id="productoSalidaPreview" style="width:76px; min-width:76px; height:76px; display:flex; align-items:center; justify-content:center; border:1px solid #dbe4ec; border-radius:8px; background:#f8fafc; overflow:hidden;"></div>
                    </div>
                </div>

                <div class="form-group salida-carrito-group">
                    <label><i class="fas fa-shopping-cart"></i> CARRITO DE SALIDA</label>
                    <div class="table-wrapper scrollbar-custom" style="max-height: 180px;">
                        <table style="min-width: 920px;">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">IMG</th>
                                    <th>PRODUCTO</th>
                                    <th>CÓDIGO</th>
                                    <th>PRECIO</th>
                                    <th>CANTIDAD</th>
                                    <th>TOTAL</th>
                                    <th>REFERENCIA</th>
                                    <th>ACCIÓN</th>
                                </tr>
                            </thead>
                            <tbody id="carritoSalidaBody">
                                <tr><td colspan="8" style="text-align: center; padding: 14px;">Sin productos agregados</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="salida-total-box">TOTAL SALIDA: <span id="totalCarritoSalida">$0</span></div>
                </div>

                <div class="salida-pago-grid">
                    <div class="form-group">
                        <label for="metodoPagoSalida"><i class="fas fa-cash-register"></i> MÉTODO DE PAGO *</label>
                        <select id="metodoPagoSalida" onchange="toggleCamposCreditoSalida()" required>
                            <option value="efectivo">EFECTIVO</option>
                            <option value="transferencia">TRANSFERENCIA</option>
                            <option value="credito">CRÉDITO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tipoSalida"><i class="fas fa-list"></i> TIPO DE SALIDA *</label>
                        <select id="tipoSalida" name="tipo_salida" required>
                            <option value="venta">VENTA</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="referenciaSalida"><i class="fas fa-code"></i> REFERENCIA VENTA</label>
                        <input type="text" id="referenciaSalida" autocomplete="off" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="grupoClienteCreditoSalida" style="display:none;">
                        <label for="clienteCreditoSalida"><i class="fas fa-user-check"></i> CLIENTE *</label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="search" id="buscarClienteCreditoSalida" placeholder="BUSCAR POR NOMBRE, DOCUMENTO O CÓDIGO" autocomplete="off" oninput="filtrarClientesCreditoSalida()" onfocus="filtrarClientesCreditoSalida()" style="flex:1; min-width:0;">
                            <button type="button" class="btn-submit" onclick="seleccionarClienteCreditoDesdeBusqueda()" style="width:auto; min-width:0; padding:12px 14px;" title="Seleccionar cliente"><i class="fas fa-check"></i></button>
                        </div>
                        <input type="hidden" id="clienteCreditoSalida">
                        <div id="clienteCreditoSalidaSeleccionado" style="display:none; margin-top:8px; border:1px solid #dbeafe; background:#eff6ff; color:#1d4ed8; border-radius:8px; padding:10px 12px; font-size:12px; font-weight:700; text-transform:uppercase;"></div>
                        <div id="clientesCreditoSalidaOpciones" style="display:none;max-height:180px;overflow:auto;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;"></div>
                    </div>
                </div>

                <textarea id="notasSalida" hidden></textarea>

                <button type="submit" class="btn-submit salida-submit"><i class="fas fa-save"></i> GUARDAR SALIDA</button>
            </form>
        </div>
    </div>

    <div id="modalClienteCredito" class="modal" style="z-index:1300;">
        <div class="modal-content" style="max-width:720px;">
            <span class="close" onclick="cerrarModalClienteCredito()">&times;</span>
            <h2 style="text-align:center;"><i class="fas fa-user-plus"></i> AGREGAR CLIENTE</h2>
            <form id="formClienteCredito" onsubmit="guardarClienteCredito(event)" autocomplete="off">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="rol" value="Cliente">
                <div class="form-row">
                    <div class="form-group"><label>NOMBRE:</label><input name="nombre" required></div>
                    <div class="form-group"><label>APELLIDOS:</label><input name="apellidos" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>TIPO DE DOCUMENTO:</label><select name="tipo_documento" required><option value="Cédula de Ciudadanía">CÉDULA DE CIUDADANÍA</option><option value="Cédula de Extranjería Colombiana">CÉDULA DE EXTRANJERÍA</option><option value="Pasaporte">PASAPORTE</option></select></div>
                    <div class="form-group"><label>DOCUMENTO:</label><input name="documento" pattern="[0-9]{1,10}" maxlength="10" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>CORREO:</label><input type="email" name="correo" required></div>
                    <div class="form-group"><label>TELÉFONO:</label><input name="telefono" pattern="[0-9]{10}" maxlength="10" required></div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> GUARDAR CLIENTE</button>
            </form>
        </div>
    </div>

    <div id="productosCategoriaSalidaModal" class="modal" style="z-index:1200;">
        <div class="modal-content" style="max-width:1250px; width:calc(100% - 28px);">
            <div class="modal-header">
                <h2 id="productosCategoriaSalidaTitulo"><i class="fas fa-store"></i> PRODUCTOS</h2>
                <div style="display:flex; align-items:center; gap:10px; min-width:0; margin-left:auto;">
                    <div style="display:flex; align-items:center; width:20%; min-width:150px; max-width:220px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; overflow:hidden;">
                        <input type="search" id="buscarProductosCategoriaSalida" placeholder="BUSCAR..." autocomplete="off" style="width:100%; min-width:0; border:0; outline:0; padding:8px 10px; font-size:12px; text-transform:uppercase;">
                        <span style="padding:0 9px; color:#64748b;"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="close-btn" type="button" onclick="cerrarModal('productosCategoriaSalidaModal')">&times;</button>
                </div>
            </div>
            <div id="productosCategoriaSalidaGrid" class="productos-categoria-salida-grid"></div>
        </div>
    </div>

    <!-- MODAL: RESUMEN VENTAS DEL DÍA -->
    <div id="ventasDiaModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h2><i class="fas fa-chart-line"></i> RESUMEN DE VENTAS</h2>
                <button class="close-btn" onclick="cerrarModal('ventasDiaModal')">&times;</button>
            </div>
            
            <div style="padding: 20px;">
                <!-- Tabs para Día/Mes -->
                <div class="tabs-container ventas-periodo-tabs" style="margin-bottom: 20px;">
                    <button class="tab-btn active" onclick="cambiarPeriodoVentas(this, 'dia')">
                        <i class="fas fa-calendar-day"></i> HOY
                    </button>
                    <button class="tab-btn" onclick="cambiarPeriodoVentas(this, 'mes')">
                        <i class="fas fa-calendar-alt"></i> ESTE MES
                    </button>
                    <button class="tab-btn" onclick="cambiarPeriodoVentas(this, 'imprimir')" title="IMPRIMIR VENTAS"><i class="fas fa-print"></i> IMPRIMIR</button>
                </div>

                <div id="ventasImprimir" class="tab-content">
                    <div style="text-align:center;margin-bottom:20px;font-size:18px;color:#666;"><i class="fas fa-print"></i> <strong>IMPRIMIR VENTAS</strong></div>
                    <div class="ventas-print-panel" style="text-align:center;">
                        <div class="ventas-print-filters">
                            <button type="button" class="btn-info btn-action ventas-todas-action" onclick="seleccionarTodasVentas()" title="SELECCIONAR TODAS LAS VENTAS"><i class="fas fa-layer-group"></i> TODOS</button>
                            <div class="ventas-print-field">
                                <label for="reporteVentasAnio">AÑO</label>
                                <select id="reporteVentasAnio" aria-label="AÑO PARA IMPRIMIR" onclick="seleccionarAnioReporte(this)" onchange="seleccionarAnioReporte(this)"></select>
                            </div>
                            <div class="ventas-print-field">
                                <label for="reporteVentasPeriodo">MES</label>
                                <select id="reporteVentasPeriodo" aria-label="MES PARA IMPRIMIR" onclick="seleccionarMesReporte(this)" onchange="seleccionarMesReporte(this)"></select>
                            </div>
                        </div>
                        <button type="button" class="btn-info btn-action ventas-print-action" onclick="confirmarImpresionVentas()" title="IMPRIMIR REPORTE"><i class="fas fa-print"></i> IMPRIMIR REPORTE</button>
                    </div>
                </div>
                
                <!-- Contenido Día -->
                <div id="ventasDia" class="tab-content active">
                    <!-- Fecha -->
                    <div style="text-align: center; margin-bottom: 20px; font-size: 18px; color: #666;">
                        <i class="fas fa-calendar"></i> <strong id="fechaVentasDia"></strong>
                    </div>
                    
                    <!-- Totales -->
                    <div class="stats-container" style="margin-bottom: 30px;">
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon blue">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="stat-content">
                                <h3 style="font-size: 14px;">VENTAS REGISTRADAS</h3>
                                <p id="totalVentasDia" style="font-size: 28px;">0</p>
                            </div>
                        </div>
                        
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="stat-content"><h3 style="font-size: 14px;">EFECTIVO</h3><p id="efectivoDia" style="font-size: 22px;">$0</p></div>
                        </div>
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon blue"><i class="fas fa-exchange-alt"></i></div>
                            <div class="stat-content"><h3 style="font-size: 14px;">TRANSFERENCIA</h3><p id="transferenciaDia" style="font-size: 22px;">$0</p></div>
                        </div>
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon gold">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-content">
                                <h3 style="font-size: 14px;">GANANCIA REAL</h3>
                                <p id="gananciaDia" style="font-size: 28px;">$0</p>
                            </div>
                        </div>
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon purple">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-content">
                                <h3 style="font-size: 14px;">VALOR TOTAL</h3>
                                <p id="valorVentasDia" style="font-size: 28px;">$0</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla de productos vendidos -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> PRODUCTOS VENDIDOS HOY</h3>
                        </div>
                        <div class="table-wrapper scrollbar-custom" style="max-height: 400px;">
                            <table class="ventas-detalle-table ventas-hoy-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-clock"></i> FECHA Y HORA</th>
                                        <th><i class="fas fa-image"></i> IMG</th>
                                        <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                        <th><i class="fas fa-box"></i> PRODUCTO</th>
                                        <th><i class="fas fa-tags"></i> CATEGORÍA</th>
                                        <th><i class="fas fa-sort-amount-down"></i> CANTIDAD</th>
                                        <th><i class="fas fa-dollar-sign"></i> PRECIO UNIT.</th>
                                        <th><i class="fas fa-coins"></i> GANANCIA</th>
                                        <th><i class="fas fa-calculator"></i> TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody id="productosVendidosDia">
                                    <tr><td colspan="9" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Contenido MES -->
                <div id="ventasMes" class="tab-content">
                    <!-- Mes -->
                    <div style="text-align: center; margin-bottom: 20px; font-size: 18px; color: #666;">
                        <i class="fas fa-calendar-alt"></i> <strong id="mesVentasMes"></strong>
                    </div>

                    <div style="display:flex; justify-content:center; margin-bottom:16px;">
                        <select id="selectorMesVentas" onchange="cargarVentasMes(false)" style="max-width:260px; text-transform:none;">
                            <option value="">Mes actual</option>
                        </select>
                    </div>
                    
                    <!-- Totales -->
                    <div class="stats-container" style="margin-bottom: 30px;">
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon blue">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="stat-content">
                                <h3 style="font-size: 14px;">VENTAS REGISTRADAS</h3>
                                <p id="totalVentasMes" style="font-size: 28px;">0</p>
                            </div>
                        </div>
                        
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="stat-content"><h3 style="font-size: 14px;">EFECTIVO</h3><p id="efectivoMes" style="font-size: 22px;">$0</p></div>
                        </div>
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon blue"><i class="fas fa-exchange-alt"></i></div>
                            <div class="stat-content"><h3 style="font-size: 14px;">TRANSFERENCIA</h3><p id="transferenciaMes" style="font-size: 22px;">$0</p></div>
                        </div>
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon gold">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-content">
                                <h3 style="font-size: 14px;">GANANCIA REAL</h3>
                                <p id="gananciaMes" style="font-size: 28px;">$0</p>
                            </div>
                        </div>
                        <div class="stat-card" style="flex: 1;">
                            <div class="stat-icon purple">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-content">
                                <h3 style="font-size: 14px;">VALOR TOTAL</h3>
                                <p id="valorVentasMes" style="font-size: 28px;">$0</p>
                            </div>
                        </div>
                    </div>
                    
                                        <!-- Tabla de productos vendidos -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> PRODUCTOS MÁS VENDIDOS DEL MES</h3>
                            <div class="acumulado-mes-resumen" aria-label="Resumen acumulado del mes">
                                <div class="acumulado-mes-card ganancia">
                                    <span class="acumulado-mes-card-label"><i class="fas fa-coins"></i> GANANCIA REAL</span>
                                    <strong id="acumuladoMesGanancia" class="acumulado-mes-card-value">$0</strong>
                                </div>
                                <div class="acumulado-mes-card">
                                    <span class="acumulado-mes-card-label"><i class="fas fa-chart-line"></i> ACUMULADO DEL MES</span>
                                    <strong id="acumuladoMesValor" class="acumulado-mes-card-value">$0</strong>
                                </div>
                            </div>
                        </div>
                        <div class="table-wrapper-principal scrollbar-custom">
                            <table class="ventas-detalle-table ventas-mes-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-image"></i> IMG</th>
                                        <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                        <th><i class="fas fa-box"></i> PRODUCTO</th>
                                        <th><i class="fas fa-tags"></i> CATEGORÍA</th>
                                        <th><i class="fas fa-sort-amount-down"></i> CANTIDAD</th>
                                        <th><i class="fas fa-dollar-sign"></i> PRECIO UNIT.</th>
                                        <th><i class="fas fa-coins"></i> GANANCIA</th>
                                        <th><i class="fas fa-calculator"></i> TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody id="productosVendidosMes">
                                    <tr><td colspan="8" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: TODOS LOS PRODUCTOS -->
    <div id="todosProductosModal" class="modal">
        <div class="modal-content" style="max-width: 1400px;">
            <div class="modal-header">
                <h2><i class="fas fa-boxes"></i> TODOS LOS PRODUCTOS</h2>
                <button class="close-btn" onclick="cerrarModal('todosProductosModal')">&times;</button>
            </div>
            
            <div style="padding: 20px;">
                <!-- Resumen -->
                <div class="stats-container" style="margin-bottom: 30px;">
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon blue">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">TOTAL PRODUCTOS</h3>
                            <p id="modalTotalProductos" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon green">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">STOCK TOTAL</h3>
                            <p id="modalStockTotal" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon purple">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">VALOR TOTAL</h3>
                            <p id="modalValorTotal" style="font-size: 28px;">$0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon orange">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">CATEGORÍAS</h3>
                            <p id="modalTotalCategorias" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de productos con imágenes -->
                <div class="card" style="padding: 10px 20px;">
                    <!-- filtro rápido -->
                                        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                            <h3><i class="fas fa-list"></i> LISTADO DE PRODUCTOS</h3>
                                            <div style="position:relative; width:min(100%, 280px);">
                                                <input type="text" id="buscarTodosProductosModal" placeholder="BUSCAR PRODUCTO..." autocomplete="off" style="width:100%; padding:8px 12px; border:1px solid #d0d7de; border-radius:6px; background:#fff; text-transform:uppercase;">
                                                <div id="resultadosTodosProductosModal" style="display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:6px; background:#fff; box-shadow:0 8px 20px rgba(31,41,55,.12); z-index:100;"></div>
                                            </div>
                    </div>
                    <div class="table-wrapper scrollbar-custom" style="max-height: 500px;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 80px;"><i class="fas fa-image"></i></th>
                                    <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                    <th><i class="fas fa-box"></i> PRODUCTO</th>
                                    <th><i class="fas fa-tags"></i> CATEGORÍA</th>
                                    <th><i class="fas fa-boxes"></i> STOCK</th>
                                    <th><i class="fas fa-dollar-sign"></i> P. COMPRA</th>
                                    <th><i class="fas fa-dollar-sign"></i> P. VENTA</th>
                                    <th><i class="fas fa-coins"></i> GANANCIA</th>
                                    <th><i class="fas fa-percent"></i> % GANANCIA</th>
                                    <th><i class="fas fa-calculator"></i> VALOR TOTAL</th>
                                    <th><i class="fas fa-info-circle"></i> ESTADO</th>
                                    <th><i class="fas fa-cogs"></i> ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody id="todosProductosBody">
                                <tr><td colspan="12" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: VER PRODUCTO -->
    <div id="verProductoModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> DETALLES DEL PRODUCTO</h2>
                <button class="close-btn" onclick="cerrarModal('verProductoModal')">&times;</button>
            </div>
            <div id="verProductoBody" class="modal-body">
                <!-- se llenará con JS -->
            </div>
            <div class="modal-actions" style="padding: 0 20px 20px;">
                <?php if($tienePermisoEditar): ?>
                    <button type="button" class="btn-edit" onclick="(function(){ cerrarModal('verProductoModal'); editarProducto(document.getElementById('verProdId').value); })()" title="Editar"><i class="fas fa-edit"></i> Editar</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR PRODUCTO -->
    <div id="editarProductoModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('editarProductoModal')">&times;</span>
            <h2 style="text-align: center;">
                <i class="fas fa-edit"></i> EDITAR PRODUCTO
            </h2>
            <form id="formEditarProducto" autocomplete="off" onsubmit="submitEditarProducto(event)">
                <input type="hidden" id="editProdId" name="id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="editProdNombre"><i class="fas fa-box"></i> NOMBRE *</label>
                        <input type="text" id="editProdNombre" name="nombre" autocomplete="off" required style="text-transform: none;">
                    </div>
                    <div class="form-group">
                        <label for="editProdCategoria"><i class="fas fa-tag"></i> CATEGORÍA *</label>
                        <select id="editProdCategoria" name="categoria_id" required>
                            <option value="">Seleccione categoría</option>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?= $cat->id ?>"><?= $cat->nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editProdDescripcion"><i class="fas fa-align-left"></i> DESCRIPCIÓN</label>
                    <textarea id="editProdDescripcion" name="descripcion" rows="3" style="text-transform:none;"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editProdPrecioCompra"><i class="fas fa-dollar-sign"></i> PRECIO DE COMPRA</label>
                        <input type="number" step="0.01" id="editProdPrecioCompra" name="precio_compra" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editProdPrecio"><i class="fas fa-dollar-sign"></i> PRECIO DE VENTA *</label>
                        <input type="number" step="0.01" id="editProdPrecio" name="precio" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="editProdStock"><i class="fas fa-cubes"></i> STOCK *</label>
                        <input type="number" id="editProdStock" name="stock" min="0" step="1" autocomplete="off" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editProdPorcentaje"><i class="fas fa-percent"></i> PORCENTAJE DE GANANCIA</label>
                        <input type="number" step="0.1" id="editProdPorcentaje" name="porcentaje_ganancia" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editProdCodigo"><i class="fas fa-barcode"></i> CÓDIGO INTERNO</label>
                        <input type="text" id="editProdCodigo" name="codigo" disabled autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editProdCodigoBarras"><i class="fas fa-barcode"></i> CÓDIGO DE BARRAS</label>
                        <input type="text" id="editProdCodigoBarras" name="codigo_barras" autocomplete="off" style="text-transform:none;">
                    </div>
                </div>
                <div class="form-row" style="margin-top: 20px;">
                    <button type="submit" class="btn-save" style="width: 100%;"><i class="fas fa-save"></i> GUARDAR CAMBIOS</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: STOCK TOTAL -->
    <div id="stockTotalModal" class="modal">
        <div class="modal-content" style="max-width: 1400px;">
            <div class="modal-header">
                <h2><i class="fas fa-cubes"></i> DESGLOSE DE STOCK TOTAL</h2>
                <button class="close-btn" onclick="cerrarModal('stockTotalModal')">&times;</button>
            </div>
            
            <div style="padding: 20px;">
                <!-- Resumen -->
                <div class="stats-container" style="margin-bottom: 30px;">
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon green">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">UNIDADES TOTALES</h3>
                            <p id="modalStockTotalUnidades" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon blue">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">PRODUCTOS</h3>
                            <p id="modalStockProductos" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon orange">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">CATEGORÍAS</h3>
                            <p id="modalStockCategorias" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon purple">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">VALOR TOTAL</h3>
                            <p id="modalStockValor" style="font-size: 28px;">$0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de stock por producto -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> STOCK POR PRODUCTO</h3>
                    </div>
                    <div class="table-wrapper scrollbar-custom" style="max-height: 500px;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 80px;"><i class="fas fa-image"></i></th>
                                    <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                    <th><i class="fas fa-box"></i> PRODUCTO</th>
                                    <th><i class="fas fa-tags"></i> CATEGORÍA</th>
                                    <th><i class="fas fa-cubes"></i> STOCK ACTUAL</th>
                                    <th><i class="fas fa-dollar-sign"></i> PRECIO UNIT.</th>
                                </tr>
                            </thead>
                            <tbody id="stockTotalBody">
                                <tr><td colspan="6" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: NECESIDAD DE REORDEN -->
    <!-- MODAL: VALOR DEL INVENTARIO -->
    <div id="valorInventarioModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h2><i class="fas fa-chart-pie"></i> DESGLOSE DE VALOR DEL INVENTARIO</h2>
                <button class="close-btn" onclick="cerrarModal('valorInventarioModal')">&times;</button>
            </div>
            
            <div style="padding: 20px;">
                <!-- Resumen -->
                <div class="stats-container" style="margin-bottom: 30px;">
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon purple">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">VALOR TOTAL</h3>
                            <p id="valorTotalDetalle" style="font-size: 28px;">$0</p>
                        </div>
                    </div>

                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon red">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">VALOR DE COMPRA</h3>
                            <p id="valorCompraDetalle" style="font-size: 28px;">$0</p>
                        </div>
                    </div>

                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon green">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">VALOR DE GANANCIA</h3>
                            <p id="valorGananciaDetalle" style="font-size: 28px;">$0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon blue">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">TOTAL PRODUCTOS</h3>
                            <p id="totalProductosDetalle" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon green">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">UNIDADES TOTALES</h3>
                            <p id="unidadesTotalesDetalle" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de valor por producto -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> VALOR POR PRODUCTO</h3>
                    </div>
                    <div class="table-wrapper scrollbar-custom" style="max-height: 500px;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 80px;"><i class="fas fa-image"></i></th>
                                    <th><i class="fas fa-barcode"></i> CODIGO</th>
                                    <th><i class="fas fa-box"></i> PRODUCTO</th>
                                    <th><i class="fas fa-tags"></i> CATEGORIA</th>
                                    <th><i class="fas fa-sort-amount-up"></i> CANTIDAD</th>
                                    <th><i class="fas fa-dollar-sign"></i> PRECIO UNIT.</th>
                                    <th><i class="fas fa-dollar-sign"></i> VALOR TOTAL</th>
                                    <th><i class="fas fa-percentage"></i> % DEL VALOR TOTAL</th>
                                </tr>
                            </thead>
                            <tbody id="detalleValorBody">
                                <tr><td colspan="8" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="reordenModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h2><i class="fas fa-shopping-cart"></i> NECESIDAD DE REORDEN</h2>
                <button class="close-btn" onclick="cerrarModal('reordenModal')">&times;</button>
            </div>
            
            <div style="padding: 20px;">
                <!-- Resumen -->
                <div class="stats-container" style="margin-bottom: 30px;">
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon orange">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">PRODUCTOS</h3>
                            <p id="productosReordenCount" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon red">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">CRÍTICOS</h3>
                            <p id="productosCriticos" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon yellow">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">URGENTES</h3>
                            <p id="productosUrgentes" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="flex: 1;">
                        <div class="stat-icon blue">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 14px;">NORMALES</h3>
                            <p id="productosNormales" style="font-size: 28px;">0</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de productos para reorden -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> PRODUCTOS CON BAJO STOCK</h3>
                    </div>
                    <div class="table-wrapper scrollbar-custom" style="max-height: 500px;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 80px;"><i class="fas fa-image"></i></th>
                                    <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                                    <th><i class="fas fa-box"></i> PRODUCTO</th>
                                    <th><i class="fas fa-tags"></i> CATEGORÍA</th>
                                    <th><i class="fas fa-cubes"></i> STOCK ACTUAL</th>
                                    <th><i class="fas fa-exclamation-circle"></i> URGENCIA</th>
                                </tr>
                            </thead>
                            <tbody id="productosReordenBody">
                                <tr><td colspan="6" style="text-align: center; padding: 20px;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // URL base de la aplicación
        const base_url = <?= json_encode(base_url()) ?>;
        const inventarioControllerUrl = base_url + '/Controllers/InventarioController.php';
        const productoControllerUrl = base_url + '/Controllers/ProductoController.php';

        const inventarioPaginas = new Map();
        const inventarioTamanosPagina = new Map();
        const inventarioTablaPorClave = {
            resumen: 'resumenTableBody',
            entradas: 'entradasTableBody',
            salidas: 'salidasTableBody',
            movimientos: 'movimientosTableBody'
        };

        function actualizarPaginacionInventario(clave) {
            const toolbar = document.querySelector(`[data-inventory-toolbar="${clave}"]`);
            const tbody = document.getElementById(inventarioTablaPorClave[clave]);
            if (!toolbar || !tbody) return;
            const textoBusqueda = String(toolbar.querySelector('.inventory-list-search')?.value || '').trim().toLowerCase();
            const todasLasFilas = Array.from(tbody.children).filter(fila => fila.tagName === 'TR');
            // Los separadores de fecha no son registros: no se cuentan ni se paginan
            const esSeparador = (fila) => fila.classList.contains('group-date');
            const filasDatos = todasLasFilas.filter(fila => !esSeparador(fila));
            const filas = filasDatos.filter(fila => !textoBusqueda || String(fila.textContent || '').toLowerCase().includes(textoBusqueda));
            const selector = toolbar.querySelector('.inventory-page-size');
            const porPagina = Math.max(1, Number(selector?.value || 50));
            const totalPaginas = Math.max(1, Math.ceil(filas.length / porPagina));
            const pagina = Math.min(inventarioPaginas.get(clave) || 0, totalPaginas - 1);
            inventarioPaginas.set(clave, pagina);
            todasLasFilas.forEach(fila => { fila.style.display = 'none'; });
            filas.forEach((fila, indice) => {
                fila.style.display = indice >= pagina * porPagina && indice < (pagina + 1) * porPagina ? '' : 'none';
            });
            // Mostrar cada separador de fecha solo si su grupo tiene filas visibles debajo
            todasLasFilas.forEach((fila, indice) => {
                if (!esSeparador(fila)) return;
                let visible = false;
                for (let i = indice + 1; i < todasLasFilas.length; i++) {
                    const siguiente = todasLasFilas[i];
                    if (esSeparador(siguiente)) break;
                    if (siguiente.style.display !== 'none') { visible = true; break; }
                }
                fila.style.display = visible ? '' : 'none';
            });
            toolbar.querySelector('.inventory-pagination').innerHTML = `
                <button type="button" class="btn-save inventory-page-prev" title="Página anterior" aria-label="Página anterior" style="padding:4px 7px;min-height:26px;width:28px;font-size:10px;" ${pagina === 0 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>
                <span style="min-width:90px;text-align:center;color:#667085;font-weight:600;font-size:11px;">PÁGINA ${pagina + 1} / ${totalPaginas}</span>
                <button type="button" class="btn-save inventory-page-next" title="Página siguiente" aria-label="Página siguiente" style="padding:4px 7px;min-height:26px;width:28px;font-size:10px;" ${pagina >= totalPaginas - 1 ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
        }

        function inicializarPaginacionInventario() {
            Object.keys(inventarioTablaPorClave).forEach((clave) => {
                const toolbar = document.querySelector(`[data-inventory-toolbar="${clave}"]`);
                const tbody = document.getElementById(inventarioTablaPorClave[clave]);
                if (!toolbar || !tbody) return;
                toolbar.querySelector('.inventory-page-size')?.addEventListener('change', () => {
                    const tamanoAnterior = inventarioTamanosPagina.get(clave) || 50;
                    const paginaActual = inventarioPaginas.get(clave) || 0;
                    const nuevoTamano = Number(toolbar.querySelector('.inventory-page-size')?.value || 50);
                    inventarioPaginas.set(clave, Math.floor((paginaActual * tamanoAnterior) / nuevoTamano));
                    inventarioTamanosPagina.set(clave, nuevoTamano);
                    actualizarPaginacionInventario(clave);
                });
                toolbar.querySelector('.inventory-list-search')?.addEventListener('input', () => {
                    inventarioPaginas.set(clave, 0);
                    actualizarPaginacionInventario(clave);
                });
                toolbar.addEventListener('click', (event) => {
                    const paginaActual = inventarioPaginas.get(clave) || 0;
                    if (event.target.closest('.inventory-page-prev')) inventarioPaginas.set(clave, Math.max(0, paginaActual - 1));
                    if (event.target.closest('.inventory-page-next')) inventarioPaginas.set(clave, paginaActual + 1);
                    actualizarPaginacionInventario(clave);
                });
                new MutationObserver(() => actualizarPaginacionInventario(clave)).observe(tbody, { childList: true });
                inventarioTamanosPagina.set(clave, Number(toolbar.querySelector('.inventory-page-size')?.value || 50));
                actualizarPaginacionInventario(clave);
            });
        }

        document.addEventListener('DOMContentLoaded', inicializarPaginacionInventario, { once: true });

        let productosConGananciaCache = null;
        let productosConGananciaPromise = null;

        function obtenerProductosConGanancia(force = false) {
            if (!force && Array.isArray(productosConGananciaCache)) {
                return Promise.resolve({ success: true, data: productosConGananciaCache });
            }

            if (!force && productosConGananciaPromise) {
                return productosConGananciaPromise;
            }

            productosConGananciaPromise = fetch(inventarioControllerUrl + '?action=obtenerProductosConGanancia')
                .then(response => response.json())
                .then(data => {
                    productosConGananciaCache = Array.isArray(data?.data) ? data.data : [];
                    return data;
                })
                .finally(() => {
                    productosConGananciaPromise = null;
                });

            return productosConGananciaPromise;
        }

        function invalidarCacheProductosConGanancia() {
            productosConGananciaCache = null;
            productosConGananciaPromise = null;
        }

        const resolveAppUrl = (path) => {
            const value = String(path || '').trim();
            if (!value) return base_url;
            if (/^https?:\/\//i.test(value) || value.startsWith('data:')) return value;
            if (value.startsWith('/')) return base_url + value;
            if (value.startsWith('../')) return `${base_url}${value.replace(/^\.\.\//, '/')}`;
            return `${base_url}/${value.replace(/^\/+/, '')}`;
        };

        const usuarioLogueado = <?= json_encode($nombreUsuario) ?>;
        // Nombre de la empresa para impresiones
        const nombreEmpresa = <?= json_encode($nombreEmpresa) ?>;
        // Logo de la empresa para impresión
        const logoEmpresaUrlRaw = <?= json_encode($empresaLogo) ?>;
        const logoEmpresaUrl = logoEmpresaUrlRaw ? (/^https?:\/\//i.test(logoEmpresaUrlRaw) ? logoEmpresaUrlRaw : base_url + (logoEmpresaUrlRaw.startsWith('/') ? '' : '/') + logoEmpresaUrlRaw) : '';
        const logoImpresionDataUrl = <?= json_encode($logoImpresionDataUri, JSON_UNESCAPED_SLASHES) ?>;
        const logoImpresionFileUrl = base_url + '/Assets/images/Empresas/mi_estrella_solo_imprimir.png';
        const logoImpresionUrl = logoImpresionDataUrl || logoImpresionFileUrl;
        const logoPdfUrl = <?= json_encode($logoPdfDataUri) ?>;
        const logoImpresionFallbackUrl = logoImpresionFileUrl;
        const logoPdfFallbackUrl = base_url + '/Assets/images/Empresas/empresa_1_20260901_185734_fe042179.png';
        // Permisos en JS
        const permisoVer = <?= json_encode((bool)$tienePermisoVer) ?>;
        const permisoEditar = <?= json_encode((bool)$tienePermisoEditar) ?>;
        const permisoCrear = <?= json_encode((bool)$tienePermisoCrear) ?>;
        const puedeVerID  = <?= json_encode((bool)$puedeVerID) ?>;
        const phpSessionId = (new URLSearchParams(window.location.search)).get('PHPSESSID') || '';
        const accionInventarioDesdeUrl = (new URLSearchParams(window.location.search)).get('accion') || '';

        function abrirAccionInventarioDesdeUrl() {
            const accion = String(accionInventarioDesdeUrl || '').trim().toLowerCase();
            if (!accion) return;

            const activarTabEntradas = () => {
                const botonEntradas = document.querySelector('.tab-btn[data-tab="entradas"]') || document.querySelector('.tab-btn:nth-child(2)');
                if (botonEntradas && typeof cambiarTab === 'function') {
                    cambiarTab(botonEntradas, 'entradas');
                }
            };

            const activarTabSalidas = () => {
                const botonSalidas = document.querySelector('.tab-btn[data-tab="salidas"]') || document.querySelector('.tab-btn:nth-child(3)');
                if (botonSalidas && typeof cambiarTab === 'function') {
                    cambiarTab(botonSalidas, 'salidas');
                }
            };

            setTimeout(() => {
                if (accion === 'entrada' || accion === 'entradas') {
                    activarTabEntradas();
                    if (typeof abrirModalEntrada === 'function') {
                        abrirModalEntrada();
                    } else {
                        abrirModal('entradaModal');
                    }
                    return;
                }

                if (accion === 'salida' || accion === 'salidas') {
                    activarTabSalidas();
                    if (typeof abrirModal === 'function') {
                        abrirModal('salidaModal');
                    }
                }
            }, 150);
        }


        document.addEventListener('DOMContentLoaded', abrirAccionInventarioDesdeUrl);

        function preserveFetchUrl(resource) {
            if (!phpSessionId) {
                return resource;
            }
            try {
                const url = new URL(resource, window.location.href);
                if (url.origin !== window.location.origin) {
                    return resource;
                }
                if (!url.pathname.includes('/Controllers/')) {
                    return resource;
                }
                url.searchParams.set('PHPSESSID', phpSessionId);
                return url.toString();
            } catch (error) {
                return resource;
            }
        }

        const originalFetch = window.fetch.bind(window);
        window.fetch = function(resource, init) {
            if (typeof resource === 'string') {
                const normalized = resource.trim();
                if (normalized.startsWith('../Controllers/') || normalized.startsWith('../Assets/') || normalized.startsWith('Controllers/') || normalized.startsWith('Assets/')) {
                    resource = resolveAppUrl(normalized);
                }
            }
            return originalFetch(preserveFetchUrl(resource), init);
        };

        function obtenerColspanResumen() {
            return puedeVerID ? 11 : 10;
        }

        function obtenerColspanEntradas() {
            return puedeVerID ? 10 : 9;
        }

        function actualizarSugerenciasResumen(items) {
            const valores = new Map();
            (items || []).forEach(item => {
                if (item && item.nombre) valores.set(String(item.nombre).trim(), String(item.nombre).trim());
                if (item && item.codigo) valores.set(String(item.codigo).trim(), String(item.codigo).trim());
            });
            window.opcionesFiltroInventario = Array.from(valores.values()).filter(Boolean);
            renderResultadosFiltroInventario();
        }

        function normalizarFiltroInventario(valor) {
            return String(valor || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        function renderResultadosFiltroInventario() {
            const input = document.getElementById('filtroResumen');
            const resultados = document.getElementById('resultadosFiltroInventario');
            if (!input || !resultados) return;
            const texto = normalizarFiltroInventario(input.value);
            const opciones = Array.isArray(window.opcionesFiltroInventario) ? window.opcionesFiltroInventario : [];
            const coincidencias = opciones.filter(opcion => normalizarFiltroInventario(opcion).includes(texto));
            resultados.innerHTML = coincidencias.map(opcion => `<button type="button" data-valor="${escapeHtmlInventario(opcion)}" style="display:block;width:100%;padding:9px 12px;border:0;border-bottom:1px solid #f1f5f9;background:#fff;text-align:left;cursor:pointer;text-transform:uppercase;">${escapeHtmlInventario(opcion)}</button>`).join('');
            resultados.style.display = coincidencias.length && texto ? 'block' : 'none';
        }

        function filtrarTablasInventario() {
            const texto = normalizarFiltroInventario(document.getElementById('filtroResumen')?.value);
            ['resumenTableBody', 'entradasTableBody', 'salidasTableBody', 'movimientosTableBody'].forEach(id => {
                const tbody = document.getElementById(id);
                if (!tbody) return;
                const filas = Array.from(tbody.querySelectorAll('tr'));
                filas.forEach(fila => {
                    if (fila.classList.contains('group-date')) return;
                    fila.style.display = !texto || normalizarFiltroInventario(fila.textContent).includes(texto) ? '' : 'none';
                });
                filas.filter(fila => fila.classList.contains('group-date')).forEach(encabezado => {
                    let siguiente = encabezado.nextElementSibling;
                    let visible = false;
                    while (siguiente && !siguiente.classList.contains('group-date')) {
                        if (siguiente.style.display !== 'none') visible = true;
                        siguiente = siguiente.nextElementSibling;
                    }
                    encabezado.style.display = visible ? '' : 'none';
                });
            });
        }

        let filtroInventarioFrame = null;
        function programarFiltradoTablasInventario() {
            if (filtroInventarioFrame !== null) return;
            const ejecutar = () => {
                filtroInventarioFrame = null;
                filtrarTablasInventario();
            };
            filtroInventarioFrame = typeof requestAnimationFrame === 'function'
                ? requestAnimationFrame(ejecutar)
                : setTimeout(ejecutar, 16);
        }
        
        // Cambiar tab
        function cambiarTab(btn, tabName) {
            // Desactivar todos los tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            // Activar el tab seleccionado
            document.getElementById(tabName).classList.add('active');
            btn.classList.add('active');
            
            // Cargar datos según el tab
            if (tabName === 'resumen') cargarResumen();
            if (tabName === 'entradas') cargarEntradas();
            if (tabName === 'salidas') cargarSalidas();
            if (tabName === 'movimientos') cargarMovimientos();
        }

        // Abrir modal
        function abrirModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            const yaEstabaActivo = modal.classList.contains('active');
            modal.classList.add('active');
            document.body.classList.add('modal-open');
            if (modalId === 'salidaModal') {
                if (!yaEstabaActivo) {
                    const referenciaField = document.getElementById('referenciaSalida');
                    if (referenciaField) referenciaField.value = '';
                    inicializarReferenciaSalida();
                }
                inicializarCategoriasSalida();
                try { attachSalidaListeners(); } catch (e) {}
            }
            if (modalId === 'entradaModal' || modalId === 'salidaModal') {
                const inputId = modalId === 'entradaModal' ? 'buscarProductoEntrada' : 'buscarProductoSalida';
                setTimeout(() => {
                    const input = document.getElementById(inputId);
                    const resultsId = modalId === 'entradaModal' ? 'productoEntradaSearchResults' : 'productoSalidaSearchResults';
                    const results = document.getElementById(resultsId);
                    if (input) {
                        input.value = '';
                        input.focus();
                    }
                    if (results) results.style.display = 'none';
                }, 80);
            }
        }

        // Cerrar modal
        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            if (!document.querySelector('.modal.active')) {
                document.body.classList.remove('modal-open');
            }
            if (modalId === 'salidaModal') {
                const referenciaAnterior = document.getElementById('referenciaSalida')?.value?.trim();
                if (referenciaAnterior) referenciasVentaReservadas.delete(referenciaAnterior);
            }
            const form = document.getElementById(modalId).querySelector('form');
            if (form) form.reset();
            if (modalId === 'entradaModal') {
                resetEntradaModalFields();
            }
            if (modalId === 'salidaModal') {
                carritoSalida = [];
                ventaPorPesoCategoriaActiva = false;
                renderCarritoSalida();
                const cantidad = document.getElementById('cantidadSalida');
                if (cantidad) {
                    cantidad.value = 1;
                    cantidad.step = '1';
                    cantidad.min = '1';
                }
                const metodoPago = document.getElementById('metodoPagoSalida');
                if (metodoPago) metodoPago.value = 'efectivo';
                const clienteCredito = document.getElementById('clienteCreditoSalida');
                if (clienteCredito) clienteCredito.value = '';
                const buscarCliente = document.getElementById('buscarClienteCreditoSalida');
                if (buscarCliente) buscarCliente.value = '';
                const opcionesCliente = document.getElementById('clientesCreditoSalidaOpciones');
                if (opcionesCliente) opcionesCliente.style.display = 'none';
                const tipoSalida = document.getElementById('tipoSalida');
                if (tipoSalida) tipoSalida.value = 'venta';
                const referencia = document.getElementById('referenciaSalida');
                if (referencia) referencia.value = '';
                toggleCamposCreditoSalida();
                limpiarSeleccionSalidaUI();
                filtrarProductosSalidaPorCategoria('');
            }
            // Limpiar campo de búsqueda
            const filtroRes = document.getElementById('filtroResumen');
            if (filtroRes) filtroRes.value = '';
        }

        let carritoSalida = [];
        let productoSalidaSeleccionadoPorCategoria = null;
        let salidasAgrupadasCache = {};
        let movimientosAgrupadosCache = {};
        const referenciasVentaReservadas = new Set();
        let clientesCreditoSalida = [];
        let ventaPorPesoCategoriaActiva = false;
        let puertoBalanzaSalida = null;
        let bufferBalanzaSalida = '';
        let basculaNativaConectada = false;
        let basculaNativaListenerRegistrado = false;
        let ultimoDatoBalanzaSalida = 0;
        let ultimoPesoBalanzaSalida = null;

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function esRegistroCredito(valor) {
            return valor === true || valor === 1 || String(valor || '').trim() === '1';
        }

        function formatoTipoSalida(tipo, metodoPago = '', esCredito = false) {
            const valor = String(tipo || '').trim().toLowerCase();
            const metodo = String(metodoPago || '').trim().toLowerCase();
            if (esRegistroCredito(esCredito) || metodo === 'credito' || valor === 'credito' || valor === 'venta_credito_pagada' || valor === 'venta_credito' || valor === 'credito_pagado' || valor === 'pagado') return 'CRÉDITO';
            if (!valor || valor === 'venta' || valor === 'salida') return 'INVENTARIO';
            if (valor === 'dañado') return 'DAÑADO';
            if (valor === 'perdida') return 'PÉRDIDA';
            return 'INVENTARIO';
        }

        function etiquetaTipoSalida(tipo, ordenTallerId = 0, metodoPago = '', esCredito = false) {
            const valor = String(tipo || '').trim().toLowerCase();
            const metodo = String(metodoPago || '').trim().toLowerCase();
            const esCreditoLegacy = esRegistroCredito(esCredito) || metodo === 'credito' || valor === 'credito' || valor === 'venta_credito_pagada' || valor === 'venta_credito' || valor === 'credito_pagado' || valor === 'pagado';
            if (esCreditoLegacy) return 'CRÉDITO';
            if (!valor || valor === 'venta' || valor === 'salida') return 'INVENTARIO';
            if (valor === 'dañado') return 'DAÑADO';
            if (valor === 'perdida') return 'PÉRDIDA';
            return 'INVENTARIO';
        }

        function tipoSinCobro(tipo) {
            const valor = String(tipo || '').trim().toLowerCase();
            return valor === 'dañado' || valor === 'danado' || valor === 'perdida' || valor === 'pérdida';
        }

        function metodoPagoVisible(tipo, metodoPago) {
            if (tipoSinCobro(tipo)) return '-';
            const metodo = String(metodoPago || '').trim();
            return metodo ? metodo.toUpperCase() : '-';
        }

        function origenSalida(item) {
            return esRegistroCredito(item?.es_credito ?? item?.esCredito) ? 'CRÉDITO' : 'INVENTARIO';
        }

        function etiquetaTipoMovimiento(tipo) {
            const valor = String(tipo || '').trim().toLowerCase();
            if (valor === 'entrada') return 'ENTRADA';
            if (valor === 'salida') return 'SALIDA';
            return 'MOVIMIENTO';
        }

        function origenMovimiento(item) {
            const tipoMovimientoNormalizado = String(item.tipo_movimiento || '').trim().toLowerCase();
            if (tipoMovimientoNormalizado === 'entrada') {
                return 'ENTRADA DE INVENTARIO';
            }
            if (tipoMovimientoNormalizado === 'salida') {
                return esRegistroCredito(item?.es_credito ?? item?.esCredito) ? 'CRÉDITO' : 'INVENTARIO';
            }
            return 'MOVIMIENTO';
        }

        function generarReferenciaVenta(referenciasAdicionales = []) {
            const nombreLimpio = String(nombreEmpresa || '').trim().replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            const prefijo = (nombreLimpio.slice(0, 2) || 'AU').toUpperCase();
            let secuencia = 0;
            const referenciasExistentes = new Set([
                ...Object.keys(salidasAgrupadasCache).map((clave) => String(clave || '').split('|')[0].trim()),
                ...referenciasAdicionales.map((valor) => String(valor || '').trim()),
                ...Array.from(referenciasVentaReservadas).map((valor) => String(valor || '').trim())
            ]);

            referenciasExistentes.forEach((ref) => {
                const valor = String(ref || '').trim();
                if (!valor) return;
                const match = valor.match(new RegExp('^' + prefijo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[ -]?(\\d+)$', 'i'));
                if (match) secuencia = Math.max(secuencia, parseInt(match[1], 10) || 0);
            });

            secuencia += 1;
            const referencia = `${prefijo}-${String(secuencia).padStart(2, '0')}`;
            referenciasVentaReservadas.add(referencia);
            return referencia;
        }

        async function inicializarReferenciaSalida() {
            const referenciaField = document.getElementById('referenciaSalida');
            if (!referenciaField) return;

            if (referenciaField.value && String(referenciaField.value).trim()) {
                return;
            }

            // Al recargar el iframe, el modal puede abrir antes de que termine
            // cargarSalidas(); consulta la BD para no volver temporalmente a AU-01.
            if (Object.keys(salidasAgrupadasCache).length === 0) {
                try {
                    const respuesta = await fetch(inventarioControllerUrl + '?action=obtenerSalidas', { cache: 'no-store' });
                    const datos = await respuesta.json();
                    if (datos?.success && Array.isArray(datos.data)) {
                        referenciaField.value = generarReferenciaVenta(datos.data.map((item) => item?.referencia));
                        return;
                    }
                } catch (error) {
                    console.warn('No se pudo actualizar la secuencia de ventas:', error);
                }
            }

            referenciaField.value = generarReferenciaVenta();
        }

        function limpiarNombreArchivo(referencia) {
            return String(referencia || 'documento')
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-zA-Z0-9._\-]/g, '_')
                .trim()
                .replace(/_+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function fechaHoraImpresion(valor) {
            const fecha = convertirFechaLocalInventario(valor);
            if (Number.isNaN(fecha.getTime())) {
                return { fecha: 'SIN FECHA', hora: 'SIN HORA' };
            }
            return {
                fecha: `${String(fecha.getDate()).padStart(2, '0')}/${String(fecha.getMonth() + 1).padStart(2, '0')}/${fecha.getFullYear()}`,
                hora: fecha.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
            };
        }

        function convertirFechaLocalInventario(valor) {
            const texto = String(valor || '').trim();
            if (!texto) return new Date(NaN);
            const textoNormalizado = texto.includes('T') ? texto : texto.replace(' ', 'T');
            const fecha = new Date(textoNormalizado);
            if (!Number.isNaN(fecha.getTime())) return fecha;
            const partes = texto.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
            if (partes) {
                const [, anio, mes, dia, hora, minuto, segundo] = partes;
                return new Date(Number(anio), Number(mes) - 1, Number(dia), Number(hora), Number(minuto), Number(segundo || 0));
            }
            return new Date(valor || 0);
        }

        function referenciaParaImpresion(referencia) {
            const valor = String(referencia || '').trim();
            const separador = valor.indexOf('-');
            if (separador < 0) return escapeHtml(valor);
            const prefijo = valor.slice(0, separador + 1);
            const contenido = valor.slice(separador + 1);
            const bloques = contenido.match(/.{1,8}/g) || [];
            const primerBloque = bloques.shift() || '';
            return `<span class="ref-value"><span class="ref-first-line"><span class="ref-prefix">${escapeHtml(prefijo)}</span>${escapeHtml(primerBloque)}</span>${bloques.map(bloque => `<span class="ref-line"><span class="ref-prefix-spacer">${escapeHtml(prefijo)}</span>${escapeHtml(bloque)}</span>`).join('')}</span>`;
        }

        async function imprimirVentaSalida(referenciaVenta, generarPdf = false) {
            const key = String(referenciaVenta || '');
            const grupo = salidasAgrupadasCache[key];
            if (!grupo) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se encontró la venta a imprimir.' });
                return;
            }

            const nombreArchivoLimpio = limpiarNombreArchivo(grupo.referencia);
            document.title = nombreArchivoLimpio;
            const fechaHoraVenta = fechaHoraImpresion(grupo.fechaRaw || grupo.fecha);
            const logoFacturaUrl = generarPdf ? logoPdfUrl : logoImpresionUrl;
            const mostrarImagenesImpresion = generarPdf;
            const filas = (grupo.items || []).map(item => {
                const cantidad = parseFloat(item.cantidad || 0) || 0;
                const precio = obtenerPrecioUnitarioSalidaItem(item);
                const subtotal = obtenerSubtotalSalidaItem(item);
                const imagenSrc = resolverImagenProductoInventario(item.producto_imagen);
                return `
                    <tr>
                        ${mostrarImagenesImpresion ? `<td style="width:60px; text-align:center;">
                            <img src="${imagenSrc}" alt="${escapeHtml(item.producto_nombre || 'N/A')}" style="display:block; max-width:100%; max-height:42px; width:42px; height:42px; margin:0 auto; box-sizing:border-box; object-fit:contain; border:1px solid #d1d5db; border-radius:3px;" />
                        </td>` : ''}
                        <td>${escapeHtml(item.producto_nombre || 'N/A')}</td>
                        <td class="cell-cantidad">${cantidad.toLocaleString('es-CO')}</td>
                        <td class="cell-precio">${formatoPrecioUnitarioInventario(precio)}</td>
                        <td class="cell-subtotal"><strong>${formatoPrecioUnitarioInventario(subtotal)}</strong></td>
                    </tr>
                `;
            }).join('');

            const html = `
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <title>${escapeHtml(grupo.referencia)}</title>
                    <meta name="title" content="${escapeHtml(grupo.referencia)}">
                    <meta name="application-name" content="${escapeHtml(grupo.referencia)}">
                    <style>
                        @page { size: auto; margin: 0; }
                        html, body { margin: 0; padding: 0; width: 100%; background: #fff; color: #000; }
                        body { font-family: Arial, sans-serif; text-transform: uppercase; font-weight: 700; color: #000; text-shadow: none; -webkit-text-stroke: 0; -webkit-font-smoothing: none; text-rendering: geometricPrecision; }
                        img { image-rendering: auto; -webkit-transform: translateZ(0); transform: translateZ(0); }
                        .print-wrapper { position: relative; width: 100%; margin: 0; padding: 4mm 3mm 3mm; box-sizing: border-box; }
                        .header { display: grid; grid-template-columns: minmax(0, 1fr) 125px; column-gap: 8px; align-items: flex-start; margin-bottom: 20px; }
                        .header .info { min-width: 0; max-width: none; overflow: visible; }
                        .header h1 { margin: 0 0 3px; font-size: 16px; text-align: left; font-weight: 800; white-space: nowrap; line-height: 1.05; color: #000; }
                        .empresa { margin: 0 0 3px; font-size: 13px; color: #000; font-weight: 800; }
                        .meta { position: relative; left: -6px; margin-bottom: 3px; font-size: 10.5px; font-weight: 800; overflow-wrap: anywhere; white-space: nowrap; color: #000; }
                        .meta-referencia { display: flex; align-items: flex-start; font-size: 10px; white-space: normal; overflow: visible; font-weight: 800; line-height: 1.2; }
                        .meta-referencia > strong { flex: 0 0 48px; }
                        .ref-value { display: block; flex: 0 0 auto; vertical-align: top; white-space: nowrap; }
                        .ref-first-line, .ref-line { display: block; width: max-content; white-space: nowrap; }
                        .ref-prefix-spacer { visibility: hidden; }
                        .ref-line { display: block; white-space: nowrap; }
                        .meta strong { display: inline-block; width: 48px; }
                        .logo-empresa { position: relative; width: 125px; justify-self: end; display: flex; flex-direction: column; align-items: center; text-align: center; }
                        .logo-empresa img { width: 125px; height: 96px; object-fit: contain; filter: grayscale(100%) brightness(0.8) contrast(1.18); }
                        .logo-word-print { position: absolute; top: 58px; left: 55px; color: #000; font-family: "Brush Script MT", "Segoe Script", cursive; font-size: 19px; font-style: italic; font-weight: 900; line-height: 1; letter-spacing: -0.8px; text-transform: none; transform: rotate(-7deg); white-space: nowrap; pointer-events: none; }
                        .logo-empresa .empresa { margin: 3px 0 0; font-size: 10px; line-height: 1.1; color: #000; font-weight: 900; }
                        table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 8px; }
                        th, td { border: 1px solid rgba(0, 0, 0, 0.18); padding: 3px 2px; font-size: 10.5px; line-height: 1.15; font-weight: 700; overflow-wrap: anywhere; word-break: break-word; color: #000; background: #fff; }
                        th { background: #fff; text-align: center; color: #000; font-size: 11px; }
                        .cell-cantidad, .cell-precio, .cell-subtotal { white-space: nowrap; overflow-wrap: normal; word-break: normal; text-align: center; }
                        .cell-subtotal { font-size: 9.5px; font-weight: 800; }
                        th:nth-child(1), td:nth-child(1) { width: 15%; text-align: center; }
                        th:nth-child(2), td:nth-child(2) { width: 37%; text-align: center; }
                        th:nth-child(3), td:nth-child(3) { width: 11%; text-align: center; }
                        th:nth-child(4), td:nth-child(4) { width: 17%; text-align: center; }
                        th:nth-child(5), td:nth-child(5) { width: 20%; text-align: center; }
                        th { background: #fff; text-align: center; color: #000; }
                        .sin-imagenes th:nth-child(1), .sin-imagenes td:nth-child(1) { width: 37%; }
                        .sin-imagenes th:nth-child(2), .sin-imagenes td:nth-child(2) { width: 11%; }
                        .sin-imagenes th:nth-child(3), .sin-imagenes td:nth-child(3) { width: 17%; }
                        .sin-imagenes th:nth-child(4), .sin-imagenes td:nth-child(4) { width: 35%; }
                        .total { margin-top: 8px; text-align: right; font-size: 14px; font-weight: 800; }
                        footer { width: 100%; background: #ffffff; color: #000; padding: 6px 0 0; display: flex; justify-content: center; align-items: center; gap: 5px; flex-wrap: wrap; font-size: 10px; font-weight: 800; text-align: center; box-sizing: border-box; border-top: 1px solid rgba(0, 0, 0, 0.3); }
                        .footer-thanks { display: block; width: 100%; line-height: 1.25; }
                        footer span { display: inline-flex; align-items: center; justify-content: center; gap: 4px; line-height: 1; color: #000; font-weight: 800; }
                        footer .footer-wordmark { display: inline-flex; align-items: center; justify-content: center; gap: 4px; color: #000; font-weight: 900; }
                        footer .footer-brand-logo { width: 28px; height: 28px; object-fit: contain; display: inline-flex; vertical-align: middle; filter: brightness(0) contrast(1.5); }
                        @media print { body { margin: 0; } .header, table, .total, footer { page-break-inside: avoid; } }
                    </style>
                </head>
                <body class="${mostrarImagenesImpresion ? 'con-imagenes' : 'sin-imagenes'}">
                    <div class="print-wrapper">
                        <div class="header">
                            <div class="info">
                                <h1>RESUMEN DE<br>VENTAS</h1>
                                <div class="meta meta-referencia"><strong>REF:</strong> ${referenciaParaImpresion(grupo.referencia)}</div>
                                <div class="meta meta-fecha"><strong>FECHA:</strong> <span class="valor-fecha">${escapeHtml(fechaHoraVenta.fecha)}</span></div>
                                <div class="meta meta-hora"><strong>HORA:</strong> <span class="valor-hora">${escapeHtml(fechaHoraVenta.hora)}</span></div>
                            </div>
                            ${logoFacturaUrl ? `<div class="logo-empresa"><img src="${escapeHtml(logoFacturaUrl)}" alt="Logo de la empresa" onerror="this.onerror=null;this.src='${escapeHtml(generarPdf ? logoPdfFallbackUrl : logoImpresionFallbackUrl)}';">${generarPdf ? '<span class="logo-word-print" aria-hidden="true"></span>' : ''}<div class="empresa">AUTOSERVICIO MI ESTRELLA</div></div>` : ''}
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    ${mostrarImagenesImpresion ? '<th>IMG</th>' : ''}
                                    <th>Producto</th>
                                    <th>CAN</th>
                                    <th>PRE</th>
                                    <th>SUBT</th>
                                </tr>
                            </thead>
                            <tbody>${filas}</tbody>
                        </table>
                        <div class="total">TOTAL: ${formatoPrecioUnitarioInventario(grupo.total)}</div>
                    </div>
                    <footer>
                        <span class="footer-thanks">¡ GRACIAS POR TU COMPRA !</span>
                        <span class="footer-thanks">¡ GRACIAS POR PREFERIRNOS !</span>
                        <span class="footer-thanks">¡ VUELVE PRONTO !</span>
                        <span>&copy; ${new Date().getFullYear()}</span>
                        <span class="footer-wordmark">
                            <img src="${base_url + '/favicon.ico'}" alt="Favicon" class="footer-brand-logo">
                            <span>OWE COMPANY</span>
                        </span>
                        <span>TODOS LOS DERECHOS RESERVADOS</span>
                    </footer>
                    <script>
                        window.__electronPdfHtml = function() {
                            document.body.classList.add('pdf-export');
                            return document.documentElement.outerHTML;
                        };
                        window.onload = function() {
                            document.title = '${nombreArchivoLimpio}';
                            window.print();
                        };
                    <\/script>
                </body>
                </html>
            `;

            if (generarPdf && typeof window.electronAPI?.saveHtmlPdf === 'function') {
                const htmlPdf = html.replace('<body class="con-imagenes">', '<body class="con-imagenes pdf-export">');
                const resultadoPdf = await window.electronAPI.saveHtmlPdf({ html: htmlPdf, title: nombreArchivoLimpio });
                if (!resultadoPdf?.success && !resultadoPdf?.canceled) {
                    Swal.fire({ icon: 'error', title: 'ERROR AL GUARDAR PDF', text: resultadoPdf?.error || 'No se pudo guardar el PDF.' });
                }
                return;
            }
            if (window.electronAPI && typeof window.electronAPI.printHtml === 'function') {
                window.electronAPI.printHtml({ html, title: nombreArchivoLimpio, preview: true });
                return;
            }

            const win = window.open('about:blank', '_blank', 'toolbar=0,menubar=0,scrollbars=1,resizable=1,width=900,height=700');
            if (!win) {
                Swal.fire({ icon: 'warning', title: 'Ventana bloqueada', text: 'Permite ventanas emergentes para imprimir la venta.' });
                return;
            }
            win.document.open();
            win.document.write(html);
            win.document.close();
            win.document.title = nombreArchivoLimpio;
            win.focus();
        }

        function productoSalidaSeleccionado() {
            const select = document.getElementById('productoSalida');
            if (!select || !select.value) return productoSalidaSeleccionadoPorCategoria;

            const option = select.options[select.selectedIndex];
            const precioBase = parseFloat(option?.getAttribute('data-precio') || 0) || 0;
            const descuentoPct = parseFloat(option?.getAttribute('data-descuento') || 0) || 0;
            const stockActual = parseFloat(option?.getAttribute('data-stock') || 0) || 0;
            const categoriaPeso = String(select.dataset.categoriaPeso || '').toLowerCase();
            const esPorKilo = esProductoPorKilosSalida(option) || ['frutas', 'verduras', 'carnicos-refrigerados'].includes(categoriaPeso);
            const cantidadSeleccionada = normalizarCantidadSalida(document.getElementById('cantidadSalida')?.value || '1', esPorKilo);
            const precioFinalAtributo = parseFloat(option?.getAttribute('data-precio-final') || 0) || 0;
            const precioFinal = (precioFinalAtributo > 0 && precioBase > 0 && precioFinalAtributo <= precioBase)
                ? precioFinalAtributo
                : calcularPrecioFinalConDescuento(precioBase, descuentoPct, stockActual);
            const precioVenta = precioBase;
            return {
                producto_id: parseInt(select.value, 10),
                nombre: (option?.textContent || '').replace(/\s*\[[^\]]*\]\s*$/, '').trim(),
                codigo: (option?.getAttribute('data-codigo') || '').trim(),
                imagen: (option?.getAttribute('data-imagen') || '').trim(),
                precio: precioVenta,
                precio_original: precioBase,
                descuento_porcentaje: descuentoPct,
                stock: stockActual,
                venta_por_kilo: esPorKilo,
                cantidad: cantidadSeleccionada,
                referencia: (option?.getAttribute('data-codigo') || '').trim()
            };
        }

        function normalizarCantidadSalida(valor, esPorKilo = ventaPorPesoCategoriaActiva) {
            const numero = parseFloat(String(valor).replace(',', '.'));
            if (!Number.isFinite(numero)) return 1;
            if (esPorKilo && numero >= 0) return Math.round(numero * 1000) / 1000;
            if (numero <= 0) return 1;
            return Math.max(1, Math.floor(numero));
        }

        function esProductoPorKilosSalida(option) {
            const valor = option?.dataset?.ventaPorKilo ?? option?.getAttribute?.('data-venta-por-kilo') ?? '0';
            const porVentaPorKilo = ['1', 'true', 'si', 'sí'].includes(String(valor).trim().toLowerCase());
            const categoria = normalizarTextoBusquedaInventario(option?.dataset?.categoriaNombre || '');
            const porCategoria = ['frutas', 'verduras', 'carnicos y refrigerados'].includes(categoria);
            return porVentaPorKilo || porCategoria;
        }

        function esCategoriaEspecialSalida(option) {
            const categoria = normalizarTextoBusquedaInventario(option?.dataset?.categoriaNombre || '');
            return ['frutas', 'verduras', 'carnicos y refrigerados'].includes(categoria);
        }

        function iconoCategoriaSalida(nombre) {
            const categoria = normalizarTextoBusquedaInventario(nombre);
            if (categoria.includes('frut')) return 'fa-apple-whole';
            if (categoria.includes('verd')) return 'fa-carrot';
            if (categoria.includes('ques')) return 'fa-cheese';
            if (categoria.includes('pesc')) return 'fa-fish';
            if (categoria.includes('carn')) return 'fa-drumstick-bite';
            return 'fa-tag';
        }

        function perteneceGrupoCategoriaSalida(nombre, grupo) {
            const categoria = normalizarTextoBusquedaInventario(nombre);
            if (grupo === 'frutas') return categoria === 'frutas';
            if (grupo === 'verduras') return categoria === 'verduras';
            if (grupo === 'carnicos' || grupo === 'carnicos-refrigerados') return categoria === 'carnicos y refrigerados';
            return false;
        }

        function actualizarModoBalanzaPorProducto() {
            const select = document.getElementById('productoSalida');
            const opcion = select?.options[select.selectedIndex];
            const categoriaPeso = String(select?.dataset.categoriaPeso || '').toLowerCase();
            const esPorKilo = esProductoPorKilosSalida(opcion) || ['frutas', 'verduras', 'carnicos-refrigerados'].includes(categoriaPeso);
            const pesoBarra = document.getElementById('pesoCategoriaSalidaBarra');
            const cantidad = document.getElementById('cantidadSalida');
            const etiqueta = document.getElementById('unidadSalidaLabel');
            const detalle = document.getElementById('productoSalidaDetalle');
            const botonAgregar = document.getElementById('agregarProductoSalidaBtn');
            const esCategoriaEspecial = esCategoriaEspecialSalida(opcion);
            const categoriaSeleccionada = normalizarTextoBusquedaInventario(opcion?.dataset?.categoriaNombre || '');
            const textoAgregar = categoriaSeleccionada === 'frutas'
                ? 'AGREGAR FRUTA'
                : (categoriaSeleccionada === 'verduras' ? 'AGREGAR VERDURA' : 'AGREGAR CÁRNICOS Y REFRIGERADOS');
            ventaPorPesoCategoriaActiva = esPorKilo;
            if (detalle) detalle.style.display = esCategoriaEspecial ? 'flex' : 'none';
            if (etiqueta) etiqueta.textContent = esPorKilo ? 'X KILOS' : 'CANTIDAD';
            if (botonAgregar) botonAgregar.innerHTML = `<i class="fas fa-cart-plus"></i> ${esCategoriaEspecial ? textoAgregar : 'AGREGAR PRODUCTO'}`;
            actualizarEstadoBalanzaSalida(esPorKilo ? 'conectando' : 'oculta');
            if (pesoBarra) pesoBarra.style.display = 'block';
            if (cantidad) {
                cantidad.min = esPorKilo ? '0.001' : '1';
                cantidad.step = esPorKilo ? '0.001' : '1';
                cantidad.value = esPorKilo ? '0.001' : '1';
            }
            if (esPorKilo) {
                const peso = document.getElementById('pesoCategoriaSalidaValor');
                if (peso) peso.textContent = '0.000 kg';
                reaplicarUltimoPesoBalanzaSalida();
                conectarBalanzaSalida(false);
            }
        }

        function inicializarCategoriasSalida() {
            const panel = document.getElementById('categoriasSalidaPanel');
            const select = document.getElementById('productoSalida');
            if (!panel || !select || panel.dataset.inicializado === '1') return;
            panel.dataset.inicializado = '1';
            select.addEventListener('change', actualizarModoBalanzaPorProducto);
        }

        function abrirProductosCategoriaSalida(categoria, titulo) {
            const select = document.getElementById('productoSalida');
            const grid = document.getElementById('productosCategoriaSalidaGrid');
            const tituloEl = document.getElementById('productosCategoriaSalidaTitulo');
            const buscador = document.getElementById('buscarProductosCategoriaSalida');
            const pesoBarra = document.getElementById('pesoCategoriaSalidaBarra');
            if (!select || !grid) return;

            ventaPorPesoCategoriaActiva = categoria === 'frutas' || categoria === 'verduras';
            select.dataset.categoriaPeso = categoria === 'frutas' || categoria === 'verduras' ? categoria : '';
            actualizarEstadoBalanzaSalida('oculta');
            if (pesoBarra) pesoBarra.style.display = 'block';
            if (pesoBarra) {
                const pesoInicial = document.getElementById('pesoCategoriaSalidaValor');
                if (pesoInicial) pesoInicial.textContent = '0.000 kg';
            }
            reaplicarUltimoPesoBalanzaSalida();
            const opciones = Array.from(select.options).filter(option => {
                if (!option.value) return false;
                if ((parseFloat(option.dataset.stock || '0') || 0) <= 0) return false;
                return perteneceGrupoCategoriaSalida(option.dataset.categoriaNombre || '', categoria);
            });
            opciones.sort((a, b) => (parseInt(b.value, 10) || 0) - (parseInt(a.value, 10) || 0));

            const iconoTitulo = categoria === 'frutas'
                ? 'fa-apple-whole'
                : (categoria === 'verduras' ? 'fa-carrot' : 'fa-drumstick-bite');
            if (tituloEl) tituloEl.innerHTML = `<i class="fas ${iconoTitulo}"></i> ${titulo}`;
            if (buscador) buscador.value = '';
            grid.innerHTML = '';
            if (!opciones.length) {
                grid.innerHTML = '<div style="grid-column:1/-1; padding:32px; text-align:center; color:#64748b; font-weight:700;">NO HAY PRODUCTOS DISPONIBLES EN ESTA CATEGORÍA</div>';
            } else {
                opciones.forEach(option => {
                    const tarjeta = document.createElement('button');
                    tarjeta.type = 'button';
                    tarjeta.style.cssText = 'display:flex; flex-direction:column; align-items:center; gap:9px; min-height:190px; padding:14px 10px; border:1px solid #dbe4ec; border-radius:10px; background:#fff; color:#1e293b; cursor:pointer; box-shadow:0 3px 10px rgba(15,23,42,.07); text-align:center;';
                    const imagen = resolverImagenProductoInventario(option.dataset.imagen || '');
                    const nombre = (option.dataset.nombre || option.textContent || '').trim();
                    const codigo = String(option.dataset.codigo || '').replace(/[/*()]/g, '').trim();
                    tarjeta.dataset.searchText = normalizarTextoBusquedaInventario(`${nombre} ${codigo} ${option.dataset.barcode || ''}`);
                    const esPorKiloTarjeta = esProductoPorKilosSalida(option) || ['frutas', 'verduras', 'carnicos-refrigerados'].includes(categoria);
                    const precio = parseFloat(option.dataset.precio || 0) || 0;
                    const stock = parseFloat(option.dataset.stock || 0) || 0;
                    tarjeta.disabled = stock <= 0;
                    tarjeta.style.opacity = stock > 0 ? '1' : '0.55';
                    tarjeta.innerHTML = `<span style="min-height:18px; color:#2563eb; font-size:11px; font-weight:800; letter-spacing:.3px;">${escapeHtmlInventario(codigo)}</span><img src="${escapeHtmlInventario(imagen)}" alt="${escapeHtmlInventario(nombre)}" style="width:116px; height:116px; object-fit:contain; border-radius:8px; background:#f8fafc;" onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"><strong style="font-size:13px; text-transform:uppercase; line-height:1.2;">${escapeHtmlInventario(nombre)}</strong><span style="font-size:12px; color:#2563eb; font-weight:700;">${formatoMonedaInventario(precio)} / KG</span>`;
                    tarjeta.addEventListener('click', () => {
                        select.value = option.value;
                        productoSalidaSeleccionadoPorCategoria = {
                            producto_id: parseInt(option.value, 10),
                            nombre: (option.dataset.nombre || option.textContent || '').trim(),
                            codigo: (option.dataset.codigo || '').trim(),
                            imagen: (option.dataset.imagen || '').trim(),
                            precio: parseFloat(option.dataset.precio || 0) || 0,
                            precio_original: parseFloat(option.dataset.precio || 0) || 0,
                            descuento_porcentaje: parseFloat(option.dataset.descuento || 0) || 0,
                            stock: parseFloat(option.dataset.stock || 0) || 0,
                            venta_por_kilo: esPorKiloTarjeta,
                            cantidad: ventaPorPesoCategoriaActiva ? 0.001 : 1,
                            referencia: (option.dataset.codigo || '').trim()
                        };
                        const buscar = document.getElementById('buscarProductoSalida');
                        if (buscar) buscar.value = nombre;
                        const cantidad = document.getElementById('cantidadSalida');
                        if (cantidad) {
                            cantidad.min = ventaPorPesoCategoriaActiva ? '0.001' : '1';
                            cantidad.step = ventaPorPesoCategoriaActiva ? '0.001' : '1';
                            if (ventaPorPesoCategoriaActiva) cantidad.value = '0.001';
                        }
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        cerrarModal('productosCategoriaSalidaModal');
                    });
                    grid.appendChild(tarjeta);
                });
            }
            if (buscador) {
                buscador.oninput = () => {
                    const termino = normalizarTextoBusquedaInventario(buscador.value);
                    grid.querySelectorAll('button[data-search-text]').forEach((tarjeta) => {
                        tarjeta.style.display = coincideBusquedaInventario(tarjeta.dataset.searchText, termino) ? 'flex' : 'none';
                    });
                };
                buscador.onkeydown = (event) => {
                    if (event.key === 'Escape') {
                        buscador.value = '';
                        buscador.dispatchEvent(new Event('input'));
                    }
                };
            }
            abrirModal('productosCategoriaSalidaModal');
            setTimeout(() => buscador?.focus(), 80);
        }

        function actualizarEstadoBalanzaSalida(estado) {
            const indicador = document.getElementById('estadoBalanzaSalida');
            if (!indicador) return;
            if (estado === 'oculta') {
                return;
            }
            const conectada = estado === 'conectada';
            const conectando = estado === 'conectando';
            indicador.style.display = 'inline-flex';
            indicador.style.color = conectada ? '#15803d' : (conectando ? '#a16207' : '#b91c1c');
            indicador.innerHTML = conectada
                ? `<i class="fas fa-circle-check"></i> BÁSCULA CONECTADA${puertoBalanzaSalida ? ` · ${escapeHtmlInventario(puertoBalanzaSalida)}` : ''}`
                : (conectando
                    ? 'CONECTANDO BÁSCULA…'
                    : '<i class="fas fa-circle-xmark"></i> BÁSCULA NO CONECTADA');
        }

        // -------------------------------------------------------------------
        // Báscula ACS-30: la vista SOLO escucha el peso ya procesado que envía
        // el proceso principal de Electron (BasculaService). Aquí no se abre
        // ningún puerto COM, por lo que recargar o cambiar de página nunca
        // corta la comunicación serial.
        // -------------------------------------------------------------------
        function mostrarDiagnosticoBalanzaSalida(mensaje) {
            const destino = document.getElementById('diagnosticoBalanzaSalida');
            if (destino) destino.textContent = mensaje;
            console.info('[BASCULA]', mensaje);
        }

        function extraerPesoBalanza(texto) {
            const textoNormalizado = String(texto || '').replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, ' ');
            const coincidencias = textoNormalizado.match(/(?:^|[^\d])([+-]?\s*\d+(?:[.,]\d+)?)\s*(kg|kgs|g|gr)?\b/gi);
            if (!coincidencias || !coincidencias.length) return null;
            const coincidencia = coincidencias[coincidencias.length - 1].match(/([+-]?\s*\d+(?:[.,]\d+)?)\s*(kg|kgs|g|gr)?/i);
            const valor = coincidencia ? parseFloat(coincidencia[1].replace(/\s+/g, '').replace(',', '.')) : 0;
            if (!Number.isFinite(valor) || valor < 0) return null;
            return /^(g|gr)$/i.test(coincidencia[2] || '') ? valor / 1000 : valor;
        }

        function aplicarPesoBalanzaSalida(pesoKg) {
            if (pesoKg === null || !Number.isFinite(pesoKg) || pesoKg <= 0) {
                return;
            }
            ultimoPesoBalanzaSalida = pesoKg;

            const select = document.getElementById('productoSalida');
            const opcionActiva = select?.options[select.selectedIndex];
            const stockDisponible = parseFloat(opcionActiva?.getAttribute('data-stock') || opcionActiva?.dataset.stock || '0') || 0;
            const valorMaximoAceptable = stockDisponible > 0 ? Math.max(1, stockDisponible * 1.15) : 200;

            if (ventaPorPesoCategoriaActiva && pesoKg > valorMaximoAceptable) {
                mostrarDiagnosticoBalanzaSalida(`PESO DESCARTADO: ${pesoKg.toFixed(3)} kg\nSupera el stock disponible actual (${stockDisponible > 0 ? stockDisponible.toFixed(3) : 'sin stock'} kg).`);
                return;
            }

            const cantidad = document.getElementById('cantidadSalida');
            const etiqueta = document.getElementById('pesoCategoriaSalidaValor');
            if (cantidad && ventaPorPesoCategoriaActiva) {
                cantidad.value = Math.round(pesoKg * 1000) / 1000;
                cantidad.dispatchEvent(new Event('input', { bubbles: true }));
                cantidad.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (etiqueta) etiqueta.textContent = `${pesoKg.toFixed(3)} kg`;
            const resumen = document.getElementById('pesoSalidaResumen');
            if (resumen) resumen.textContent = `PESO: ${pesoKg.toFixed(3)} KG`;
        }

        function procesarDatosBalanzaSalida(recibido) {
            bufferBalanzaSalida += String(recibido || '');
            const partes = bufferBalanzaSalida.split(/[\r\n]+/);
            bufferBalanzaSalida = partes.pop() || '';
            partes.forEach(parte => aplicarPesoBalanzaSalida(extraerPesoBalanza(parte)));
            if (partes.length === 0) {
                const pesoDirecto = extraerPesoBalanza(bufferBalanzaSalida);
                if (pesoDirecto !== null) {
                    aplicarPesoBalanzaSalida(pesoDirecto);
                    bufferBalanzaSalida = '';
                }
            }
        }

        function reaplicarUltimoPesoBalanzaSalida() {
            if (Number.isFinite(ultimoPesoBalanzaSalida)) {
                aplicarPesoBalanzaSalida(ultimoPesoBalanzaSalida);
            }
        }

        function aplicarEstadoBasculaEscritorio(estado) {
            const conectada = Boolean(estado?.conectado);
            basculaNativaConectada = conectada;
            puertoBalanzaSalida = estado?.puerto || null;
            actualizarEstadoBalanzaSalida(conectada ? 'conectada' : 'desconectada');
            if (conectada) {
                mostrarDiagnosticoBalanzaSalida(`PUERTO ABIERTO: ${puertoBalanzaSalida || 'COM'}\nLEYENDO PESO REAL...`);
            } else if (estado && estado.disponible === false) {
                mostrarDiagnosticoBalanzaSalida('La librería serial no está instalada. Ejecute npm install en la carpeta del programa.');
            } else if (estado && estado.ultimoError && estado.ultimoError.mensaje) {
                mostrarDiagnosticoBalanzaSalida(`${estado.ultimoError.mensaje}\n\nPulse "Reintentar báscula" cuando lo haya cerrado.`);
            } else {
                mostrarDiagnosticoBalanzaSalida('BÁSCULA NO DETECTADA. Revise el cable USB del adaptador CH340.');
            }
        }

        function iniciarPuenteBasculaEscritorio() {
            if (basculaNativaListenerRegistrado) return true;
            const api = window.basculaAPI;
            if (!api) {
                actualizarEstadoBalanzaSalida('desconectada');
                mostrarDiagnosticoBalanzaSalida('La báscula solo está disponible dentro de la aplicación de escritorio.');
                return false;
            }
            basculaNativaListenerRegistrado = true;
            api.onPeso(peso => {
                if (!peso || !Number.isFinite(peso.peso)) return;
                ultimoDatoBalanzaSalida = Date.now();
                aplicarPesoBalanzaSalida(peso.peso);
            });
            api.onEstado(aplicarEstadoBasculaEscritorio);
            api.estado().then(aplicarEstadoBasculaEscritorio).catch(() => {});
            return true;
        }

        async function sincronizarEstadoBalanzaSalida() {
            if (!iniciarPuenteBasculaEscritorio()) return;
            try {
                aplicarEstadoBasculaEscritorio(await window.basculaAPI.estado());
            } catch (error) {
                actualizarEstadoBalanzaSalida('desconectada');
            }
        }

        async function conectarBalanzaSalida() {
            if (!iniciarPuenteBasculaEscritorio()) return null;
            return sincronizarEstadoBalanzaSalida();
        }

        async function tararBalanzaSalida() {
            if (!window.basculaAPI) return;
            await window.basculaAPI.tarar().catch(() => {});
        }

        async function diagnosticarBalanzaSalida() {
            if (!window.basculaAPI) {
                mostrarDiagnosticoBalanzaSalida('La báscula solo está disponible dentro de la aplicación de escritorio.');
                return;
            }
            await window.basculaAPI.reconectar().catch(() => {});
            await window.basculaAPI.abrirDiagnostico().catch(() => {});
            await sincronizarEstadoBalanzaSalida();
        }

        document.addEventListener('DOMContentLoaded', () => { iniciarPuenteBasculaEscritorio(); });

        function filtrarProductosSalidaPorCategoria(categoriaId) {
            const select = document.getElementById('productoSalida');
            const input = document.getElementById('buscarProductoSalida');
            const results = document.getElementById('productoSalidaSearchResults');
            const etiqueta = document.getElementById('categoriaSalidaSeleccionada');
            if (!select) return;

            select.dataset.categoriaFiltro = String(categoriaId || '');
            const botonActivo = document.querySelector(`#categoriasSalidaPanel .categoria-salida-option[data-categoria-id="${String(categoriaId || '')}"]`);
            document.querySelectorAll('#categoriasSalidaPanel .categoria-salida-option').forEach(boton => {
                const activo = boton === botonActivo;
                boton.style.borderColor = activo ? '#2563eb' : '#cbd5e1';
                boton.style.background = activo ? '#eff6ff' : '#fff';
                boton.style.color = activo ? '#1d4ed8' : '#1e293b';
            });
            const nombreActivo = botonActivo ? botonActivo.textContent.trim() : 'TODAS';
            if (etiqueta) etiqueta.textContent = `Categoría: ${nombreActivo}`;
            if (select.value && categoriaId && select.options[select.selectedIndex]?.dataset.categoriaId !== String(categoriaId)) {
                limpiarSeleccionSalidaUI();
            }
            if (input) input.value = '';
            if (results) results.style.display = 'none';
        }

        function validarCantidadStock(item, cantidad) {
            const stock = parseFloat(item.stock || 0) || 0;
            const esPorKilo = item.venta_por_kilo === true || Number(item.venta_por_kilo) === 1;
            const qty = normalizarCantidadSalida(cantidad, esPorKilo);
            if (stock <= 0) {
                return { valido: false, cantidad: qty, stock, mensaje: 'El producto no tiene stock disponible.' };
            }
            if (qty > stock) {
                return { valido: false, cantidad: stock, stock, mensaje: `Has alcanzado el límite disponible. Stock máximo: ${stock}.` };
            }
            return { valido: true, cantidad: qty, stock };
        }

        function limpiarSeleccionSalidaUI(preservarReferencia = true) {
            const select = document.getElementById('productoSalida');
            const buscar = document.getElementById('buscarProductoSalida');
            const preview = document.getElementById('productoSalidaPreview');
            const codigo = document.getElementById('codigoProductoSalida');
            const referencia = document.getElementById('referenciaSalida');
            const cantidad = document.getElementById('cantidadSalida');

            if (temporizadorAgregarSalidaAutomatico) {
                clearTimeout(temporizadorAgregarSalidaAutomatico);
                temporizadorAgregarSalidaAutomatico = null;
            }
            if (buscar) buscar.dataset.agregandoAutomatico = '0';

            if (select) {
                select.value = '';
                productoSalidaSeleccionadoPorCategoria = null;
                delete select.dataset.categoriaPeso;
                select.style.backgroundImage = '';
            }
            if (buscar) {
                buscar.value = '';
                buscar.dataset.lectorPreparado = '0';
                if (buscar.id === 'buscarProductoSalida') {
                    buscar.readOnly = true;
                    buscar.dataset.selectorActivo = '0';
                }
            }
            if (preview) preview.innerHTML = '';
            if (codigo) codigo.value = '';
            if (referencia && !preservarReferencia) referencia.value = '';
            ventaPorPesoCategoriaActiva = false;
            actualizarEstadoBalanzaSalida('oculta');
            const etiquetaUnidad = document.getElementById('unidadSalidaLabel');
            if (etiquetaUnidad) etiquetaUnidad.textContent = 'CANTIDAD';
            if (cantidad) {
                cantidad.value = 1;
                cantidad.min = '1';
                cantidad.step = '1';
            }
            const resumenPeso = document.getElementById('pesoSalidaResumen');
            if (resumenPeso) resumenPeso.textContent = 'PESO: --.--- KG';
            const detalle = document.getElementById('productoSalidaDetalle');
            if (detalle) detalle.style.display = 'none';

            if (!preservarReferencia) {
                inicializarReferenciaSalida();
            }
            if (buscar) buscar.focus();
        }

        function toggleCamposCreditoSalida() {
            const metodo = document.getElementById('metodoPagoSalida');
            const grupoCliente = document.getElementById('grupoClienteCreditoSalida');
            const selectCliente = document.getElementById('clienteCreditoSalida');

            const esCredito = metodo && metodo.value === 'credito';
            if (grupoCliente) {
                grupoCliente.style.display = esCredito ? '' : 'none';
            }
            if (selectCliente) {
                selectCliente.required = !!esCredito;
            }
        }

        function cargarClientesCreditoSalida(seleccionado = '') {
            return fetch(base_url + '/Controllers/CreditosController.php?action=obtenerClientes')
                .then(r => r.json())
                .then(data => {
                    const inputCliente = document.getElementById('clienteCreditoSalida');
                    if (!inputCliente) return data;

                    clientesCreditoSalida = Array.isArray(data?.data) ? data.data : [];
                    if (seleccionado) {
                        const cliente = clientesCreditoSalida.find(item => String(item.id) === String(seleccionado));
                        if (cliente) {
                            inputCliente.value = String(cliente.id);
                            const buscar = document.getElementById('buscarClienteCreditoSalida');
                            if (buscar) buscar.value = formatearClienteCredito(cliente);
                        }
                    }
                    filtrarClientesCreditoSalida();
                    return data;
                })
                .catch(() => {
                    // No bloquear modal si falla la carga de clientes.
                    return null;
                });
        }

        function filtrarClientesCreditoSalida() {
            const filtro = String(document.getElementById('buscarClienteCreditoSalida')?.value || '').trim().toLowerCase();
            const opciones = document.getElementById('clientesCreditoSalidaOpciones');
            if (!opciones) return;
            const resultados = clientesCreditoSalida
                .filter(cliente => `${cliente.nombre || ''} ${cliente.apellidos || ''} ${cliente.documento || ''} ${cliente.codigo || ''}`.toLowerCase().includes(filtro))
                .slice(0, 30);
            opciones.innerHTML = resultados.length
                ? resultados.map(cliente => `<button type="button" onclick="seleccionarClienteCreditoSalida('${String(cliente.id || '').replace(/'/g, '')}')" style="display:block;width:100%;padding:9px 10px;border:0;border-bottom:1px solid #e2e8f0;background:#fff;text-align:left;cursor:pointer;">${formatearClienteCredito(cliente)}</button>`).join('')
                : '<div style="padding:10px;color:#64748b;">NO HAY CLIENTES QUE COINCIDAN</div>';
            opciones.style.display = filtro ? 'block' : 'none';
        }

        function mostrarClienteSeleccionadoCreditoSalida(cliente) {
            if (!cliente) return;
            const chip = document.getElementById('clienteCreditoSalidaSeleccionado');
            if (chip) {
                chip.textContent = `${cliente.nombre || ''} ${cliente.apellidos || ''}`.trim().toUpperCase() + ` | DOC: ${cliente.documento || 'N/D'} | CÓD: ${cliente.codigo || 'N/D'}`;
                chip.style.display = 'block';
            }
        }

        function formatearClienteCredito(cliente) {
            const nombre = `${cliente.nombre || ''} ${cliente.apellidos || ''}`.trim() || `CLIENTE ${cliente.id || ''}`;
            return `${nombre.toUpperCase()} | DOC: ${cliente.documento || 'N/D'} | CÓD: ${cliente.codigo || 'N/D'}`;
        }

        function seleccionarClienteCreditoDesdeBusqueda() {
            const buscar = document.getElementById('buscarClienteCreditoSalida');
            const idSeleccionado = document.getElementById('clienteCreditoSalida')?.value || '';
            if (!buscar) return;
            const filtro = String(buscar.value || '').trim().toLowerCase();
            let cliente = null;
            if (idSeleccionado) {
                cliente = clientesCreditoSalida.find(item => String(item.id) === String(idSeleccionado));
            }
            if (!cliente && filtro) {
                cliente = clientesCreditoSalida.find(item => {
                    const nombreCompleto = `${item.nombre || ''} ${item.apellidos || ''}`.trim().toLowerCase();
                    const documento = String(item.documento || '').trim().toLowerCase();
                    const codigo = String(item.codigo || '').trim().toLowerCase();
                    return nombreCompleto.includes(filtro) || documento.includes(filtro) || codigo.includes(filtro);
                });
            }
            if (!cliente) {
                const opciones = document.getElementById('clientesCreditoSalidaOpciones');
                if (opciones) opciones.style.display = 'block';
                filtrarClientesCreditoSalida();
                Swal.fire({ icon: 'info', title: 'CLIENTE NO SELECCIONADO', text: 'Busca y selecciona un cliente antes de continuar.' });
                return;
            }
            seleccionarClienteCreditoSalida(String(cliente.id));
        }

        function seleccionarClienteCreditoSalida(id) {
            const cliente = clientesCreditoSalida.find(item => String(item.id) === String(id));
            if (!cliente) return;
            document.getElementById('clienteCreditoSalida').value = String(cliente.id);
            document.getElementById('buscarClienteCreditoSalida').value = formatearClienteCredito(cliente);
            const opciones = document.getElementById('clientesCreditoSalidaOpciones');
            if (opciones) opciones.style.display = 'none';
            mostrarClienteSeleccionadoCreditoSalida(cliente);
        }

        function abrirModalClienteCredito() {
            const modal = document.getElementById('modalClienteCredito');
            if (modal) {
                modal.classList.add('active');
                document.body.classList.add('modal-open');
                modal.querySelector('input[name="nombre"]')?.focus();
            }
        }

        function cerrarModalClienteCredito() {
            const modal = document.getElementById('modalClienteCredito');
            if (modal) modal.classList.remove('active');
            if (!document.querySelector('.modal.active')) {
                document.body.classList.remove('modal-open');
            }
        }

        async function guardarClienteCredito(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const datos = new FormData(form);
            const documento = String(datos.get('documento') || '').trim();
            try {
                const response = await fetch(base_url + '/Views/usuarios.php', { method: 'POST', body: datos });
                const resultado = await response.json();
                if (!resultado.success) throw new Error(resultado.message || 'No se pudo guardar el cliente');
                const nombreBuscado = `${datos.get('nombre') || ''} ${datos.get('apellidos') || ''}`.trim().toLowerCase();
                cerrarModalClienteCredito();
                form.reset();
                await cargarClientesCreditoSalida();
                const clienteEncontrado = clientesCreditoSalida.find(cliente =>
                    String(cliente.documento || '').trim() === documento ||
                    `${cliente.nombre || ''} ${cliente.apellidos || ''}`.trim().toLowerCase() === nombreBuscado
                );
                if (clienteEncontrado) {
                    document.getElementById('clienteCreditoSalida').value = String(clienteEncontrado.id);
                    document.getElementById('buscarClienteCreditoSalida').value = formatearClienteCredito(clienteEncontrado);
                    mostrarClienteSeleccionadoCreditoSalida(clienteEncontrado);
                }
                Swal.fire({ icon: 'success', title: 'CLIENTE AGREGADO', text: 'El cliente quedó disponible para la salida a crédito.', timer: 1800, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'ERROR', text: error.message || 'No se pudo agregar el cliente' });
            }
        }

        function agregarProductoSalida() {
            const producto = productoSalidaSeleccionado();
            try { console.log('agregarProductoSalida - producto:', producto); } catch(e) {}
            if (!producto || !producto.producto_id) {
                if (document.getElementById('buscarProductoSalida')?.dataset.agregandoAutomatico === '1') return;
                aseguraryMostrarSwalEstilizado('Selecciona un producto para agregar al carrito de salida.');
                return;
            }

            if (!producto || (producto.stock ?? 0) <= 0) {
                limpiarSeleccionSalidaUI();
                aseguraryMostrarSwalEstilizado('No se puede agregar un producto sin stock disponible a la salida.');
                return;
            }

            const validacionInicial = validarCantidadStock(producto, producto.cantidad);
            if (!validacionInicial.valido) {
                aseguraryMostrarSwalEstilizado(validacionInicial.mensaje || 'Stock insuficiente');
                return;
            }

            const existente = carritoSalida.find(item => item.producto_id === producto.producto_id);
            const mismaPresentacion = existente
                ? Number(existente.presentacion_id || 0) === Number(producto.presentacion_id || 0)
                : true;
            if (existente && mismaPresentacion) {
                const cantidadBase = 1;
                const nuevaCantidad = normalizarCantidadSalida((parseFloat(existente.cantidad) || cantidadBase) + (parseFloat(producto.cantidad) || cantidadBase), existente.venta_por_kilo);
                const validacion = validarCantidadStock(producto, nuevaCantidad);
                if (!validacion.valido) {
                    aseguraryMostrarSwalEstilizado(validacion.mensaje || `Solo hay ${validacion.stock} unidad(es) disponibles.`);
                    return;
                }
                existente.cantidad = nuevaCantidad;
                existente.precio = producto.precio;
                existente.imagen = producto.imagen;
                carritoSalida = [existente, ...carritoSalida.filter(item => item.producto_id !== producto.producto_id)];
            } else if (existente) {
                // Cambió la presentación elegida: se reemplaza la línea del producto en el carrito.
                carritoSalida = [producto, ...carritoSalida.filter(item => item.producto_id !== producto.producto_id)];
            } else {
                carritoSalida.unshift(producto);
            }

            renderCarritoSalida();
            limpiarSeleccionSalidaUI();
            const buscarProducto = document.getElementById('buscarProductoSalida');
            if (buscarProducto) {
                buscarProducto.value = '';
                buscarProducto.dataset.lectorPreparado = '0';
                buscarProducto.focus();
            }
        }

        function actualizarCantidadSalidaItem(productoId, cantidad) {
            const item = carritoSalida.find(prod => prod.producto_id === productoId);
            const qty = normalizarCantidadSalida(cantidad, item?.venta_por_kilo === true || Number(item?.venta_por_kilo) === 1);
            if (item) {
                const validacion = validarCantidadStock(item, qty);
                if (!validacion.valido) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stock insuficiente',
                        text: validacion.mensaje
                    });
                    item.cantidad = validacion.cantidad;
                } else {
                    item.cantidad = validacion.cantidad;
                }
            }
            renderCarritoSalida();
        }

        function incrementarItemCarritoSalida(productoId) {
            const item = carritoSalida.find(prod => prod.producto_id === productoId);
            if (item) {
                const incremento = item.venta_por_kilo === true || Number(item.venta_por_kilo) === 1 ? 0.001 : 1;
                const newQty = (parseFloat(item.cantidad) || incremento) + incremento;
                actualizarCantidadSalidaItem(productoId, newQty);
            }
        }

        function decrementarItemCarritoSalida(productoId) {
            const item = carritoSalida.find(prod => prod.producto_id === productoId);
            if (item) {
                const decremento = item.venta_por_kilo === true || Number(item.venta_por_kilo) === 1 ? 0.001 : 1;
                const newQty = Math.max(decremento, (parseFloat(item.cantidad) || decremento) - decremento);
                actualizarCantidadSalidaItem(productoId, newQty);
            }
        }

        function eliminarProductoSalida(productoId) {
            carritoSalida = carritoSalida.filter(item => item.producto_id !== productoId);
            renderCarritoSalida();
        }

        function redondearTotalSalida(valor) {
            const total = Math.max(0, Number(valor) || 0);
            const baseRedondeo = Math.floor(total / 100) * 100;
            return baseRedondeo + (total % 100 > 20 ? 100 : 0);
        }

        function calcularTotalItemsSalida(items) {
            return items.reduce((total, item) => {
                return total + ((Number(item.precio_venta) || 0) * (Number(item.cantidad) || 0));
            }, 0);
        }

        function renderCarritoSalida() {
            const tbody = document.getElementById('carritoSalidaBody');
            const totalEl = document.getElementById('totalCarritoSalida');
            if (!tbody) return;

            if (!carritoSalida.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 14px;">Sin productos agregados</td></tr>';
                if (totalEl) totalEl.textContent = formatoMonedaInventario(0);
                return;
            }

            tbody.innerHTML = '';
            let totalGeneral = 0;

            carritoSalida.forEach(item => {
                const row = document.createElement('tr');
                const codigo = item.codigo || 'N/A';
                const referencia = item.referencia || item.codigo || 'N/A';
                const stock = parseFloat(item.stock || 0) || 0;
                const precio = stock <= 0 ? 0 : parseFloat(item.precio) || 0;
                const precioOriginal = stock <= 0 ? 0 : parseFloat(item.precio_original) || 0;
                const descuentoPct = stock <= 0 ? 0 : parseFloat(item.descuento_porcentaje) || 0;
                const esPorKilo = item.venta_por_kilo === true || Number(item.venta_por_kilo) === 1;
                const cantidad = normalizarCantidadSalida(item.cantidad, esPorKilo);
                const pesoEnGramos = esPorKilo ? cantidad * 1000 : cantidad;
                const subtotal = esPorKilo ? (pesoEnGramos / 1000) * precio : precio * cantidad;
                const imgSrc = resolverImagenProductoInventario(item.imagen);
                totalGeneral += subtotal;
                const presentaciones = Array.isArray(item.presentaciones) ? item.presentaciones : [];
                const presentacionesHtml = presentaciones.length > 1
                    ? `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:7px;" role="group" aria-label="Presentación de ${escapeHtmlInventario(item.nombre || 'producto')}">
                        ${presentaciones.map(pres => {
                            const activa = Number(pres.id) === Number(item.presentacion_id || 0);
                            const estilo = activa
                                ? 'min-width:78px;min-height:32px;padding:6px 10px;border:2px solid #1e3a8a;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;font-size:12px;font-weight:800;text-transform:uppercase;box-shadow:0 0 0 3px rgba(37,99,235,.22);'
                                : 'min-width:78px;min-height:32px;padding:6px 10px;border:2px solid #bfdbfe;border-radius:6px;background:#eff6ff;color:#1d4ed8;cursor:pointer;font-size:12px;font-weight:800;text-transform:uppercase;';
                            return `<button type="button" aria-pressed="${activa ? 'true' : 'false'}" onclick="seleccionarPresentacionCarrito(${item.producto_id}, ${Number(pres.id)})" style="${estilo}">${escapeHtmlInventario(String(pres.nombre || '').toUpperCase())}${activa ? ' <i class="fas fa-check"></i>' : ''}</button>`;
                        }).join('')}
                    </div>`
                    : '';

                const precioCell = descuentoPct > 0 && precioOriginal > precio
                    ? `<div style="display:flex;flex-direction:column;gap:2px;align-items:flex-start;">
                         <span style="text-decoration:line-through;color:#94a3b8;font-size:11px;">${formatoMonedaInventario(precioOriginal)}</span>
                         <span style="color:#16a34a;font-weight:700;">${formatoMonedaInventario(precio)}</span>
                         <span class="badge badge-success" style="font-size:10px;">-${descuentoPct.toFixed(0)}%</span>
                       </div>`
                    : formatoMonedaInventario(precio);

                row.innerHTML = `
                    <td style="text-align:center;"><img src="${imgSrc}" alt="${escapeHtmlInventario(item.nombre || 'Producto')}" style="width:44px;height:44px;object-fit:contain;border-radius:8px;background:#fff;border:1px solid #e2e8f0;" onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"></td>
                    <td><div>${item.nombre || 'Producto'}</div>${presentacionesHtml}</td>
                    <td>${codigo}</td>
                    <td>${precioCell}</td>
                    <td style="min-width: 130px;">
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <button type="button" onclick="decrementarItemCarritoSalida(${item.producto_id})" style="padding: 4px 8px; background: #2c3e50; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">−</button>
                            <input type="number" min="${esPorKilo ? '0.001' : '1'}" step="${esPorKilo ? '0.001' : '1'}" max="${stock}" value="${cantidad}"
                                   style="width: 60px; text-align: center; padding: 4px; border: 1px solid #ccc; border-radius: 4px;"
                                   onchange="actualizarCantidadSalidaItem(${item.producto_id}, this.value)"
                                   oninput="actualizarCantidadSalidaItem(${item.producto_id}, this.value)"
                                   onblur="actualizarCantidadSalidaItem(${item.producto_id}, this.value)">
                            ${esPorKilo ? '<span style="font-weight:700;">KG</span>' : ''}
                            <button type="button" onclick="incrementarItemCarritoSalida(${item.producto_id})" style="padding: 4px 8px; background: #2c3e50; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">+</button>
                        </div>
                    </td>
                    <td><strong>${formatoMonedaInventario(subtotal)}</strong></td>
                    <td>${referencia}</td>
                    <td>
                        <button type="button" class="btn-delete btn-action" title="Eliminar" onclick="eliminarProductoSalida(${item.producto_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
                    totalEl.textContent = formatoMonedaInventario(redondearTotalSalida(totalGeneral));
            if (totalEl) {
                totalEl.textContent = formatoMonedaInventario(totalGeneral);
            }
        }

        function seleccionarPresentacionCarrito(productoId, presentacionId) {
            const item = carritoSalida.find(producto => Number(producto.producto_id) === Number(productoId));
            const presentaciones = Array.isArray(item?.presentaciones) ? item.presentaciones : [];
            const seleccion = presentaciones.find(pres => Number(pres.id) === Number(presentacionId));
            if (!item || !seleccion) return;

            const factor = Number(seleccion.factor_base || 1) > 0 ? Number(seleccion.factor_base) : 1;
            const stockBase = Number(item.stock_base ?? item.stock ?? 0) || 0;
            item.presentacion_id = Number(seleccion.id);
            item.presentacion_nombre = String(seleccion.nombre || '').toUpperCase();
            item.presentacion_factor = factor;
            item.stock = factor > 1 ? Math.floor(stockBase / factor) : stockBase;
            item.precio = Number(seleccion.precio_venta || 0) || Number(item.precio_producto || item.precio || 0);
            item.precio_original = item.precio;
            item.precio_venta = item.precio;
            item.cantidad = 1;
            item.nombre_base = item.nombre_base || String(item.nombre || '').replace(/\s·\s[^·]+$/, '');
            item.nombre = `${item.nombre_base} · ${item.presentacion_nombre}`;
            renderCarritoSalida();
        }

        // Cerrar modal al hacer clic fuera: bloqueado para entrada/salida para no perder productos cargados
        window.onclick = function(event) {
            const modal = event.target instanceof Element ? event.target.closest('.modal') : null;
            if (!modal) return;
            if (event.target !== modal) return;
            const modalId = modal.id;
            if (modalId === 'entradaModal' || modalId === 'salidaModal') {
                return;
            }
            cerrarModal(modalId);
        }

        // Cargar estadísticas al iniciar
        function ajustarTamanoTextoStat(elementoId) {
            const valorEl = document.getElementById(elementoId);
            if (!valorEl) return;

            const texto = (valorEl.textContent || '').replace(/\s/g, '');
            const longitud = texto.length;
            const esResumenVentas = /^(efectivo|transferencia|ganancia|valorVentas)(Dia|Mes)$/.test(elementoId)
                || elementoId === 'totalDiaCard'
                || elementoId === 'gananciaDiaCard';
            const esValorInventario = elementoId === 'valorTotal' || ['valorTotalDetalle', 'valorCompraDetalle', 'valorGananciaDetalle'].includes(elementoId);

            let tamano = esResumenVentas ? 22 : 38;
            let tamanoMinimo = esValorInventario ? 19 : 12;
            let tamanoMaximo = esValorInventario ? 56 : 34;

            if (!esResumenVentas) {
                if (longitud >= 14) {
                    tamano = esValorInventario ? 40 : 22;
                } else if (longitud >= 12) {
                    tamano = esValorInventario ? 44 : 24;
                } else if (longitud >= 10) {
                    tamano = esValorInventario ? 48 : 28;
                } else if (longitud >= 8) {
                    tamano = esValorInventario ? 52 : 30;
                }
            }

            valorEl.style.fontSize = tamano + 'px';

            if (!esResumenVentas) {
                while (valorEl.scrollWidth < valorEl.clientWidth && tamano < tamanoMaximo) {
                    tamano += 1;
                    valorEl.style.fontSize = tamano + 'px';
                }

                while (valorEl.scrollWidth > valorEl.clientWidth && tamano > tamanoMinimo) {
                    tamano -= 1;
                    valorEl.style.fontSize = tamano + 'px';
                }
            }
        }

        function ajustarTamanoResumenVentas(periodo) {
            ['efectivo', 'transferencia', 'ganancia', 'valorVentas'].forEach(tipo => {
                ajustarTamanoTextoStat(`${tipo}${periodo}`);
            });
        }

        function ajustarTamanoValorInventario() {
            ajustarTamanoTextoStat('valorTotal');
        }

        function ajustarTamanoGananciaTotal() {
            ajustarTamanoTextoStat('totalDiaCard');
            ajustarTamanoTextoStat('gananciaDiaCard');
        }

        function normalizarValorMonedaVenta(valor) {
            return parseFloat(valor) || 0;
        }

        function formatoMonedaCompleta(valor) {
            const normalizado = normalizarValorMonedaVenta(valor);
            return '$' + normalizado.toLocaleString('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }

        function formatoMonedaInventario(valor) {
            const numero = parseFloat(valor) || 0;
            return '$' + numero.toLocaleString('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }

        function formatoPrecioUnitarioInventario(valor) {
            const numero = parseFloat(valor) || 0;
            return '$' + numero.toLocaleString('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }

        function escapeHtmlInventario(valor) {
            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function resolverImagenProductoInventario(nombreImagen) {
            const nombre = String(nombreImagen || '').trim();
            if (!nombre || nombre === 'favicon.ico') {
                return base_url + '/favicon.ico';
            }
            return base_url + '/Assets/images/productos/' + nombre;
        }

        function formatoPorcentajeInventario(valor) {
            const numero = parseFloat(valor) || 0;
            return numero.toLocaleString('es-CO', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%';
        }

        function obtenerPrecioUnitarioSalidaItem(item) {
            const cantidad = parseFloat(item?.cantidad || 0) || 0;
            const precios = [
                item?.precio_venta_unitario,
                item?.precio_unitario,
                item?.precio_venta,
                item?.precio_final,
                item?.precio
            ];
            for (const valor of precios) {
                const precio = parseFloat(valor);
                if (Number.isFinite(precio) && precio > 0) {
                    return precio;
                }
            }

            const subtotal = obtenerSubtotalRegistradoSalidaItem(item);
            return cantidad > 0 && subtotal > 0 ? subtotal / cantidad : 0;
        }

        function obtenerSubtotalRegistradoSalidaItem(item) {
            const totales = [item?.total_venta, item?.total_movimiento, item?.subtotal];
            for (const valor of totales) {
                const total = parseFloat(valor);
                if (Number.isFinite(total) && total >= 0 && valor !== null && valor !== undefined && valor !== '') {
                    return total;
                }
            }
            return 0;
        }

        function obtenerSubtotalSalidaItem(item) {
            const cantidad = parseFloat(item?.cantidad || 0) || 0;
            const subtotalRegistrado = obtenerSubtotalRegistradoSalidaItem(item);
            if (subtotalRegistrado > 0) {
                return subtotalRegistrado;
            }
            return obtenerPrecioUnitarioSalidaItem(item) * cantidad;
        }

        function confirmarReinicioInventario(tabla) {
            const nombreTabla = {
                entradas_inventario: 'entradas',
                salidas_inventario: 'salidas',
                movimientos_inventario: 'movimientos'
            }[tabla] || 'inventario';

            Swal.fire({
                title: '¿Reiniciar ' + nombreTabla + '?',
                text: 'Esta acción limpiará los registros de ' + nombreTabla + ' y dejará el stock actual del inventario en cero para que el resumen quede vacío. Puede deshacer este cambio con el botón correspondiente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, reiniciar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'reiniciarInventario');
                formData.append('tabla', tabla);

                fetch(inventarioControllerUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo reiniciar la tabla');
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Tabla reiniciada',
                        text: data.message || 'La tabla se limpió correctamente.'
                    });
                    if (tabla === 'salidas_inventario' || tabla === 'all') {
                        salidasAgrupadasCache = {};
                        referenciasVentaReservadas.clear();
                        const nombreLimpio = String(nombreEmpresa || '').trim().replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                        const prefijo = nombreLimpio.slice(0, 2).padEnd(2, 'X');
                        try {
                            localStorage.removeItem(`inventarioUltimaFactura_${prefijo}`);
                        } catch (error) {}
                        const referenciaSalida = document.getElementById('referenciaSalida');
                        if (referenciaSalida) referenciaSalida.value = '';
                    }
                    if (tabla === 'entradas_inventario') {
                        cargarEntradas();
                    } else if (tabla === 'salidas_inventario') {
                        cargarSalidas();
                    } else if (tabla === 'movimientos_inventario') {
                        cargarMovimientos();
                    }
                    cargarEstadisticas();
                    cargarResumen();
                })
                .catch(e => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: e.message || 'No se pudo reiniciar la tabla'
                    });
                });
            });
        }

        function confirmarDeshacerReinicioInventario(tabla) {
            const nombreTabla = {
                entradas_inventario: 'entradas',
                salidas_inventario: 'salidas',
                movimientos_inventario: 'movimientos'
            }[tabla] || 'tabla';

            Swal.fire({
                title: '¿Deshacer el último reinicio de ' + nombreTabla + '?',
                text: 'Se restaurará el estado anterior de ' + nombreTabla + ' sin afectar las demás tablas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#64748b'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'deshacerReinicioInventario');
                formData.append('tabla', tabla || 'all');

                fetch(inventarioControllerUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo deshacer el reinicio');
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Reinicio deshecho',
                        text: data.message || 'El estado anterior se restauró correctamente.'
                    });
                    if (tabla === 'entradas_inventario') {
                        cargarEntradas();
                    } else if (tabla === 'salidas_inventario') {
                        cargarSalidas();
                    } else if (tabla === 'movimientos_inventario') {
                        cargarMovimientos();
                    }
                    cargarEstadisticas();
                    cargarResumen();
                })
                .catch(e => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: e.message || 'No se pudo deshacer el reinicio'
                    });
                });
            });
        }

        function calcularPrecioFinalConDescuento(precioBase, descuentoPorcentaje, stock) {
            const original = parseFloat(precioBase) || 0;
            const descuento = parseFloat(descuentoPorcentaje) || 0;
            const stockActual = parseFloat(stock) || 0;

            if (stockActual <= 0 || original <= 0 || descuento <= 0) {
                return original;
            }

            return Math.max(0, original * (1 - descuento / 100));
        }

        function renderResumenPrecioDescuento(precioOriginal, precioFinal, descuentoPorcentaje) {
            const original = parseFloat(precioOriginal) || 0;
            const descuento = parseFloat(descuentoPorcentaje) || 0;
            let final = parseFloat(precioFinal) || 0;

            if ((final <= 0 || final > original) && descuento > 0 && original > 0) {
                final = calcularPrecioFinalConDescuento(original, descuento, 1);
            }

            if (descuento > 0 && original > final) {
                return `
                    <div style="display:flex; flex-direction:column; align-items:flex-start; gap:4px; line-height:1.1;">
                        <span style="text-decoration:line-through; color:#94a3b8; font-size:12px;">${formatoMonedaInventario(original)}</span>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <strong style="color:#16a34a;">${formatoMonedaInventario(final)}</strong>
                            <span class="badge badge-info">-${descuento.toFixed(1)}%</span>
                        </div>
                    </div>
                `;
            }

            return `<strong style="color:#16a34a;">${formatoMonedaInventario(final || original)}</strong>`;
        }

        function calcularPorcentajeGananciaInventario(precioVenta, precioCompra, porcentajeBase) {
            const precioVentaNum = parseFloat(precioVenta) || 0;
            const precioCompraNum = parseFloat(precioCompra) || 0;
            const porcentajeBaseNum = parseFloat(porcentajeBase);

            if (!Number.isNaN(porcentajeBaseNum) && porcentajeBaseNum !== null && porcentajeBaseNum !== undefined && porcentajeBaseNum > 0) {
                return porcentajeBaseNum;
            }

            if (precioCompraNum > 0) {
                return ((precioVentaNum - precioCompraNum) / precioCompraNum) * 100;
            }

            return 0;
        }

        function cargarEstadisticas() {
            console.log('Iniciando carga de estadísticas...');
            fetch(inventarioControllerUrl + '?action=obtenerEstadisticas')
                .then(r => {
                    if (!r.ok) throw new Error('Error en respuesta del servidor');
                    return r.json();
                })
                .then(data => {
                    console.log('Datos de estadísticas:', data);
                    if (data.success && data.data) {
                        const totalProductos = parseInt(data.data.total_productos) || 0;
                        const stockTotal = parseFloat(data.data.stock_total) || 0;
                        const valorTotal = parseFloat(data.data.valor_total) || 0;
                        const valorCompraTotal = parseFloat(data.data.valor_compra_total) || 0;
                        const bajoStock = parseInt(data.data.bajo_stock) || 0;

                        if (document.getElementById('totalProductos')) {
                            document.getElementById('totalProductos').textContent = totalProductos;
                        }
                        if (document.getElementById('stockTotal')) {
                            document.getElementById('stockTotal').textContent = formatoStockTotalVisible(stockTotal);
                        }
                        if (document.getElementById('valorTotal')) {
                            document.getElementById('valorTotal').textContent = formatoMonedaCompleta(valorTotal);
                            ajustarTamanoValorInventario();
                        }
                        if (document.getElementById('valorCompraTotal')) {
                            document.getElementById('valorCompraTotal').textContent = formatoMonedaCompleta(valorCompraTotal);
                        }
                        if (document.getElementById('bajoStock')) {
                            document.getElementById('bajoStock').textContent = bajoStock;
                        }
                    } else {
                        console.warn('Respuesta sin datos:', data);
                    }
                })
                .catch(e => console.error('Error al cargar estadísticas:', e));

            fetch(inventarioControllerUrl + '?action=obtenerVentasDia')
                .then(r => {
                    if (!r.ok) throw new Error('Error en respuesta del servidor para ventas del día');
                    return r.json();
                })
                .then(data => {
                    console.log('Datos de ventas del día:', data);
                    if (data.success && data.data) {
                        const totalVendidoDia = parseFloat(data.data.valor_total_ventas ?? 0) || 0;
                        const gananciaTotalDia = parseFloat(data.data.ganancia_total_dia ?? data.data.ganancia_dia ?? 0) || 0;

                        if (document.getElementById('totalDiaCard')) {
                            document.getElementById('totalDiaCard').textContent = formatoMonedaCompleta(totalVendidoDia);
                        }

                        if (document.getElementById('gananciaDiaCard')) {
                            document.getElementById('gananciaDiaCard').textContent = formatoMonedaCompleta(gananciaTotalDia);
                            ajustarTamanoGananciaTotal();
                        }
                    } else {
                        console.warn('Respuesta sin datos ventas del día:', data);
                    }
                })
                .catch(e => console.error('Error al cargar métricas del día:', e));
        }

        // Cargar resumen
        function cargarResumen() {
            console.log('=== INICIANDO CARGA DE RESUMEN ===');
            const tbody = document.getElementById('resumenTableBody');
            console.log('Elemento tbody encontrado:', tbody);
            
            if (!tbody) {
                console.error('ERROR: No se encontró el elemento resumenTableBody');
                return;
            }
            
            fetch(inventarioControllerUrl + '?action=obtenerResumen')
                .then(r => {
                    console.log('Respuesta recibida. Status:', r.status);
                    if (!r.ok) {
                        throw new Error('HTTP error! status: ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        tbody.innerHTML = '';
                        
                        if (!data.data || data.data.length === 0) {
                            console.warn('No hay productos para mostrar');
                            tbody.innerHTML = '<tr><td colspan="' + obtenerColspanResumen() + '" style="text-align: center; padding: 20px; color: #ff6b6b; font-weight: bold;">NO HAY PRODUCTOS ACTIVOS EN LA BASE DE DATOS</td></tr>';
                            return;
                        }
                        
                        actualizarSugerenciasResumen(data.data);
                        
                        const productosOrdenados = [...data.data].sort((a, b) => (Number(a.id) || 0) - (Number(b.id) || 0));
                        const filas = document.createDocumentFragment();

                        productosOrdenados.forEach((item) => {
                            const row = document.createElement('tr');
                            
                            // Preparar imagen del producto
                            const imgSrc = resolverImagenProductoInventario(item.imagen);
                            const stock = parseFloat(item.stock || 0) || 0;
                            const precioBase = stock <= 0 ? 0 : (parseFloat(item.precio_final || item.precio || 0) || 0);
                            const precioCompra = stock <= 0 ? 0 : (parseFloat(item.ultimo_precio_compra || item.precio_compra_promedio || item.precio_compra || 0) || 0);
                            const porcentajeBase = stock <= 0 ? 0 : (!isNaN(parseFloat(item.porcentaje_ganancia)) ? parseFloat(item.porcentaje_ganancia) : 0);
                            const porcentajeGanancia = stock <= 0 ? 0 : calcularPorcentajeGananciaInventario(precioBase, precioCompra, porcentajeBase);
                            const ventas30Dias = stock <= 0 ? 0 : (parseInt(item.ventas_30dias || 0, 10) || 0);
                            const entradas30Dias = stock <= 0 ? 0 : (parseInt(item.entradas_30dias || 0, 10) || 0);
                            const colorHexRaw = String(item.color || '').trim();
                            const colorHex = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(colorHexRaw) ? colorHexRaw.toUpperCase() : '';
                            const colorDisplay = colorHex || 'SIN COLOR';
                            
                            let gananciaBadge = 'badge-success';
                            if (porcentajeGanancia < 10) {
                                gananciaBadge = 'badge-danger';
                            } else if (porcentajeGanancia < 25) {
                                gananciaBadge = 'badge-warning';
                            }
                            
                            row.innerHTML = `
                                <td style="text-align: center;">
                                    <div class="img-container">
                                        <img src="${imgSrc}" alt="${escapeHtmlInventario(item.nombre)}" class="producto-img" 
                                             onerror="this.onerror=null;this.src=base_url+'/favicon.ico'" 
                                             loading="lazy">
                                    </div>
                                </td>
                                ${puedeVerID ? `<td>${item.id || 'N/A'}</td>` : ''}
                                <td>${(item.codigo || 'N/A').toUpperCase()}</td>
                                <td class="producto-resumen-cell">
                                    <div class="producto-color-cell">
                                        <strong class="producto-color-name">${escapeHtmlInventario(String(item.nombre || 'N/A').toUpperCase())}</strong>
                                        <span class="producto-color-chip" style="background:${escapeHtmlInventario(colorHex || '#d1d5db')}"></span>
                                    </div>
                                </td>
                                <td>${(item.categoria || 'N/A').toUpperCase()}</td>
                                <td><span class="badge ${item.stock < 5 ? 'badge-danger' : 'badge-success'}">${formatoStockVisible(item.stock, item.categoria, item.venta_por_kilo)}</span></td>
                                <td>${formatoMonedaInventario(precioBase)}</td>
                                <td><span class="badge ${gananciaBadge}">${porcentajeGanancia.toFixed(1)}%</span></td>
                                <td>${ventas30Dias}</td>
                                <td>${entradas30Dias}</td>
                                <td style="text-align:center;"><button class="btn-info btn-action" title="Salida" onclick="prepararSalida(${item.id})"><i class="fas fa-shopping-cart"></i></button></td>
                            `;
                            filas.appendChild(row);
                        });
                        tbody.appendChild(filas);
                    } else {
                        console.error('Error en la respuesta del servidor:', data.message);
                        tbody.innerHTML = '<tr><td colspan="' + obtenerColspanResumen() + '" style="text-align: center; padding: 20px; color: #ff6b6b;">ERROR: ' + (data.message || 'Error desconocido') + '</td></tr>';
                    }
                })
                .catch(e => {
                    console.error('=== ERROR CAPTURADO ===');
                    console.error('Error completo:', e);
                    console.error('Mensaje:', e.message);
                    tbody.innerHTML = '<tr><td colspan="' + obtenerColspanResumen() + '" style="text-align: center; padding: 20px; color: #ff6b6b;">ERROR DE CONEXIÍ“N: ' + e.message + '</td></tr>';
                });
                
                // Ensure UI uppercase normalization after resumen load finishes
                try {
                    if (typeof Promise !== 'undefined') {
                        Promise.resolve().then(() => document.dispatchEvent(new Event('inventory:refreshed'))).catch(()=>{});
                    } else {
                        document.dispatchEvent(new Event('inventory:refreshed'));
                    }
                } catch (e) {}
        }

        // Cargar entradas
        function cargarEntradas() {
            fetch(inventarioControllerUrl + '?action=obtenerEntradas')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('entradasTableBody');
                        if (!tbody) return; // Elemento no existe en la página actual
                        tbody.innerHTML = '';

                        if (!Array.isArray(data.data) || data.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="' + obtenerColspanEntradas() + '" style="text-align: center; padding: 20px; color: #64748b;">No hay entradas registradas aún.</td></tr>';
                            return;
                        }

                        const parsearFechaEntrada = (valorFecha) => {
                            const valor = String(valorFecha || '').trim();
                            if (!valor) return new Date(NaN);
                            const normalizado = valor.includes('T') ? valor : valor.replace(' ', 'T');
                            return new Date(/[zZ]|[+-]\d{2}:?\d{2}$/.test(normalizado) ? normalizado : `${normalizado}Z`);
                        };

                        const formatarFechaDia = (valorFecha) => {
                            const fechaObj = parsearFechaEntrada(valorFecha);
                            if (isNaN(fechaObj.getTime())) {
                                return 'SIN FECHA';
                            }
                            return fechaObj.toLocaleDateString('es-CO', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit'
                            });
                        };

                        const entradasOrdenadas = [...data.data].sort((a, b) => {
                            const fechaA = parsearFechaEntrada(a.fecha_entrada).getTime() || 0;
                            const fechaB = parsearFechaEntrada(b.fecha_entrada).getTime() || 0;
                            if (fechaA !== fechaB) return fechaA - fechaB;
                            return (Number(a.id) || 0) - (Number(b.id) || 0);
                        });

                        const gruposPorFecha = {};

                        entradasOrdenadas.forEach(item => {
                            const fechaDia = formatarFechaDia(item.fecha_entrada);
                            if (!gruposPorFecha[fechaDia]) {
                                gruposPorFecha[fechaDia] = {
                                    items: [],
                                    totalCantidad: 0,
                                    totalValorCompra: 0
                                };
                            }

                            const cantidad = parseFloat(item.cantidad || 0) || 0;
                            const precioCompra = parseFloat(item.precio_compra || 0) || 0;

                            gruposPorFecha[fechaDia].items.push({
                                item,
                                cantidad,
                                precioCompra,
                                fechaTexto: !isNaN(parsearFechaEntrada(item.fecha_entrada).getTime()) ? parsearFechaEntrada(item.fecha_entrada).toLocaleString('es-CO') : 'N/A',
                                usuarioCompleto: item.usuario_nombre ?
                                    (`${item.usuario_nombre} ${item.usuario_apellidos || ''}`.trim() +
                                    (item.usuario_rol ? ` (${item.usuario_rol})` : '')).toUpperCase() :
                                    'N/A'
                            });
                            gruposPorFecha[fechaDia].totalCantidad += cantidad;
                            gruposPorFecha[fechaDia].totalValorCompra += cantidad * precioCompra;
                        });

                        Object.keys(gruposPorFecha)
                            .sort((a, b) => {
                                const fechaA = new Date(a.split('/').reverse().join('-'));
                                const fechaB = new Date(b.split('/').reverse().join('-'));
                                return fechaA - fechaB;
                            })
                            .forEach(fechaDia => {
                                const grupoFecha = gruposPorFecha[fechaDia];
                                const headerRow = document.createElement('tr');
                                headerRow.className = 'group-date';
                                headerRow.innerHTML = `
                                    <td colspan="${obtenerColspanEntradas()}">
                                        <div class="group-date-summary">
                                            <div class="group-date-total">
                                                <span class="group-date-label">TOTAL DE PRODUCTOS:</span>
                                                <strong class="group-date-value">${grupoFecha.items.length}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">FECHA:</span>
                                                <strong class="group-date-value">${escapeHtmlInventario(fechaDia)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">CANTIDAD:</span>
                                                <strong class="group-date-value">${grupoFecha.totalCantidad.toLocaleString('es-CO')}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">VALOR DE COMPRA:</span>
                                                <strong class="group-date-value">${formatoMonedaCompleta(grupoFecha.totalValorCompra)}</strong>
                                            </div>
                                        </div>
                                    </td>`;
                                tbody.appendChild(headerRow);

                                grupoFecha.items.forEach(datos => {
                                    const item = datos.item;
                                    const imgSrc = resolverImagenProductoInventario(item.producto_imagen);
                                    const fechaVencimiento = String(item.fecha_vencimiento || '').trim();
                                    const fechaVencimientoTexto = fechaVencimiento
                                        ? fechaVencimiento.split('-').reverse().join('/')
                                        : 'NO APLICA';
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td style="text-align: center;">
                                            <div class="img-container entrada-imagen-container">
                                                <img src="${imgSrc}" alt="${escapeHtmlInventario(item.producto_nombre)}" class="producto-img" 
                                                     onerror="this.onerror=null;this.src=base_url+'/favicon.ico'" 
                                                     loading="lazy">
                                            </div>
                                        </td>
                                        ${puedeVerID ? `<td style="width:58px; max-width:58px; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.id}</td>` : ''}
                                        <td>${(item.codigo || 'N/A').toUpperCase()}</td>
                                        <td>${(item.producto_nombre || '').toUpperCase()}</td>
                                        <td>${datos.cantidad.toLocaleString('es-CO')}</td>
                                        <td>$${datos.precioCompra.toLocaleString('es-CO', {maximumFractionDigits: 2})}</td>
                                        <td>${(item.proveedor_nombre || 'N/A').toUpperCase()}</td>
                                        <td>${escapeHtmlInventario(fechaVencimientoTexto)}</td>
                                        <td>${datos.fechaTexto}</td>
                                        <td>${datos.usuarioCompleto}</td>
                                    `;
                                    tbody.appendChild(row);
                                });
                            });

                        try { document.dispatchEvent(new Event('inventory:refreshed')); } catch(e) {}
                    }
                })
                .catch(e => console.error('Error:', e));
        }

        // Cargar salidas
        async function cargarSelectorImpresionVentas() {
            const selector = document.getElementById('reporteVentasPeriodo');
            const selectorAnio = document.getElementById('reporteVentasAnio');
            if (!selector || !selectorAnio) return;
            const respuesta = await fetch(inventarioControllerUrl + '?action=obtenerSalidas').then(response => response.json()).catch(() => null);
            const ventas = (Array.isArray(respuesta?.data) ? respuesta.data : []).filter(item => !item.tipo_salida || String(item.tipo_salida).trim().toLowerCase() === 'venta');
            const meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
            const fechaActual = new Date();
            const anioActual = String(fechaActual.getFullYear());
            const anios = [...new Set(ventas.map(item => String(item.fecha_salida || '').slice(0, 4)).filter(anio => /^\d{4}$/.test(anio)))].sort((a, b) => b.localeCompare(a));
            if (!anios.includes(anioActual)) anios.unshift(anioActual);
            selectorAnio.innerHTML = anios.map(anio => `<option value="${anio}"${anio === anioActual ? ' selected' : ''}>${anio}</option>`).join('');
            selector.dataset.ventas = JSON.stringify(ventas);
            actualizarMesesReporteVentas();
        }

        function actualizarMesesReporteVentas(limpiarMes = false) {
            const selector = document.getElementById('reporteVentasPeriodo');
            const selectorAnio = document.getElementById('reporteVentasAnio');
            if (!selector || !selectorAnio) return;
            let ventas = [];
            try { ventas = JSON.parse(selector.dataset.ventas || '[]'); } catch (error) {}
            const anio = selectorAnio.value;
            const meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
            const fechaActual = new Date();
            const periodoActual = `${fechaActual.getFullYear()}-${String(fechaActual.getMonth() + 1).padStart(2, '0')}`;
            const periodos = [...new Set(ventas.map(item => String(item.fecha_salida || '').slice(0, 7)).filter(periodo => periodo.startsWith(`${anio}-`) && /^\d{4}-\d{2}$/.test(periodo)))].sort((a, b) => b.localeCompare(a));
            const opcionesMeses = periodos.map(periodo => {
                const [anioMes, mes] = periodo.split('-');
                return `<option value="${periodo}"${periodo === periodoActual ? ' selected' : ''}>${meses[Number(mes) - 1]} ${anioMes}</option>`;
            });
            selector.innerHTML = `<option value="">SELECCIONE MES</option>${opcionesMeses.join('')}`;
            if (limpiarMes) selector.value = '';
        }

        function seleccionarAnioReporte(selectorAnio) {
            const selectorMes = document.getElementById('reporteVentasPeriodo');
            const botonTodas = document.querySelector('#ventasDiaModal .ventas-todas-action');
            if (selectorMes) {
                selectorMes.value = '';
                delete selectorMes.dataset.periodoSeleccionado;
                selectorMes.dataset.modoReporte = 'anio';
                selectorMes.classList.remove('modo-seleccionado');
            }
            if (botonTodas) botonTodas.classList.remove('modo-seleccionado');
            selectorAnio.classList.add('modo-seleccionado');
            selectorAnio.dataset.modoReporte = 'anio';
            actualizarMesesReporteVentas(true);
        }

        function seleccionarMesReporte(selectorMes) {
            const selectorAnio = document.getElementById('reporteVentasAnio');
            const botonTodas = document.querySelector('#ventasDiaModal .ventas-todas-action');
            delete selectorMes.dataset.periodoSeleccionado;
            selectorMes.dataset.modoReporte = 'mes';
            selectorMes.classList.add('modo-seleccionado');
            if (selectorAnio) {
                selectorAnio.classList.remove('modo-seleccionado');
                delete selectorAnio.dataset.modoReporte;
            }
            if (botonTodas) botonTodas.classList.remove('modo-seleccionado');
        }

        function seleccionarTodasVentas() {
            const selector = document.getElementById('reporteVentasPeriodo');
            const selectorAnio = document.getElementById('reporteVentasAnio');
            const botonTodas = document.querySelector('#ventasDiaModal .ventas-todas-action');
            if (selector) {
                selector.dataset.periodoSeleccionado = 'TODOS';
                selector.dataset.modoReporte = 'todos';
            }
            if (selectorAnio) {
                selectorAnio.selectedIndex = -1;
                delete selectorAnio.dataset.modoReporte;
            }
            if (selector) selector.classList.remove('modo-seleccionado');
            if (selectorAnio) selectorAnio.classList.remove('modo-seleccionado');
            if (botonTodas) botonTodas.classList.add('modo-seleccionado');
        }

        async function abrirSelectorImpresionVentas() {
            const boton = document.querySelector('#ventasDiaModal .tab-btn[title="IMPRIMIR VENTAS"]');
            if (boton) cambiarPeriodoVentas(boton, 'imprimir');
        }

        async function confirmarImpresionVentas() {
            const selector = document.getElementById('reporteVentasPeriodo');
            const selectorAnio = document.getElementById('reporteVentasAnio');
            if (!selector) return;
            let ventas = [];
            try { ventas = JSON.parse(selector.dataset.ventas || '[]'); } catch (error) {}
            const periodo = selector.dataset.modoReporte === 'todos' ? 'TODOS' : selectorAnio?.dataset.modoReporte === 'anio' && selectorAnio.value ? `ANIO-${selectorAnio.value}` : selector.value || 'TODOS';
            delete selector.dataset.periodoSeleccionado;
            await imprimirReporteVentas(ventas, periodo || 'TODOS');
        }

        async function imprimirReporteVentas(ventas, periodoSeleccionado) {
            const ventasFiltradas = ventas.filter(item => {
                const fecha = String(item.fecha_salida || '');
                return periodoSeleccionado === 'TODOS' || fecha.slice(0, 7) === periodoSeleccionado || fecha.slice(0, 4) === periodoSeleccionado.replace('ANIO-', '');
            });
            if (!ventasFiltradas.length) {
                Swal.fire({ icon: 'info', title: 'SIN VENTAS', text: 'NO HAY VENTAS PARA EL PERIODO SELECCIONADO.' });
                return;
            }
            const mesesNombre = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
            const encabezados = '<tr><th>FECHA</th><th>IMAGEN</th><th>REFERENCIA</th><th>CÓDIGO</th><th>PRODUCTO</th><th>CANTIDAD</th><th>PRECIO</th><th>SUBTOTAL</th></tr>';
            const formatoImporteReporte = valor => '$' + (parseFloat(valor) || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            const grupos = {};
            ventasFiltradas.forEach(item => {
                const fecha = String(item.fecha_salida || '');
                const clave = fecha.slice(0, 7) || 'SIN FECHA';
                if (!grupos[clave]) grupos[clave] = [];
                grupos[clave].push(item);
            });
            const filas = Object.keys(grupos).sort().map(clave => {
                const fecha = clave.match(/^\d{4}-(\d{2})$/);
                const titulo = fecha ? `${mesesNombre[Number(fecha[1]) - 1]} ${fecha[0].slice(0, 4)}` : clave;
                const productos = new Map();
                grupos[clave].forEach(item => {
                    const productoClave = String(item.producto_id || item.codigo || item.producto_nombre || '').trim();
                    const cantidad = parseFloat(item.cantidad || 0) || 0;
                    const subtotal = obtenerSubtotalSalidaItem(item);
                    const producto = productos.get(productoClave) || {
                        item,
                        cantidad: 0,
                        subtotal: 0
                    };
                    producto.cantidad += cantidad;
                    producto.subtotal += subtotal;
                    productos.set(productoClave, producto);
                });
                const items = Array.from(productos.values()).map(producto => {
                    const item = producto.item;
                    const imagen = resolverImagenProductoInventario(item.producto_imagen || item.imagen || '');
                    const precioPromedio = producto.cantidad > 0 ? producto.subtotal / producto.cantidad : 0;
                    return `<tr><td>${escapeHtml(item.fecha_salida || '')}</td><td style="text-align:center;"><img src="${escapeHtml(imagen)}" alt="${escapeHtml(item.producto_nombre || 'PRODUCTO')}" style="display:block;max-width:100%;max-height:42px;width:42px;height:42px;margin:auto;box-sizing:border-box;object-fit:contain;border:1px solid #d1d5db;border-radius:3px;"></td><td>${escapeHtml(item.referencia || '')}</td><td>${escapeHtml(item.codigo || '')}</td><td>${escapeHtml(item.producto_nombre || '')}</td><td>${producto.cantidad.toLocaleString('es-CO')}</td><td>${formatoImporteReporte(precioPromedio)}</td><td>${formatoImporteReporte(producto.subtotal)}</td></tr>`;
                }).join('');
                return `<tr class="mes"><th colspan="8">${titulo}</th></tr>${encabezados}${items}`;
            }).join('');
            const periodoTexto = periodoSeleccionado === 'TODOS' ? 'TODOS LOS MESES Y AÑOS' : periodoSeleccionado.startsWith('ANIO-') ? `AÑO ${periodoSeleccionado.replace('ANIO-', '')}` : (() => { const [anio, mes] = periodoSeleccionado.split('-'); return `${mesesNombre[Number(mes) - 1]} ${anio}`; })();
            const logoReporteUrl = window.electronAPI ? logoImpresionUrl : logoEmpresaUrl;
            const html = `<!doctype html><html><head><meta charset="UTF-8"><title>REPORTE DE VENTAS</title><style>body{font-family:Arial,sans-serif;text-transform:uppercase;margin:24px;color:#000;text-shadow:none;-webkit-text-stroke:0}.encabezado{display:flex;align-items:center;gap:18px;border-bottom:2px solid #000;padding-bottom:14px;margin-bottom:16px}.encabezado img{width:100px;height:80px;object-fit:contain}.empresa{font-size:18px;font-weight:800;color:#000}.subtitulo{font-size:14px;margin-top:5px;color:#000}table{width:100%;border-collapse:collapse;font-size:11px;color:#000}th,td{border:1px solid #000;padding:6px;text-align:left;vertical-align:middle;color:#000;background:#fff}th:nth-child(3),td:nth-child(3),th:nth-child(4),td:nth-child(4),th:nth-child(6),td:nth-child(6),th:nth-child(7),td:nth-child(7),th:nth-child(8),td:nth-child(8){text-align:center;vertical-align:middle}td img{display:block;margin:auto}.mes th{background:#fff;color:#000;text-align:center;font-size:13px}@media print{.mes{page-break-after:avoid}}</style></head><body><header class="encabezado">${logoReporteUrl ? `<img src="${escapeHtml(logoReporteUrl)}" alt="LOGO DE LA EMPRESA">` : ''}<div><div class="empresa">${escapeHtml(nombreEmpresa)}</div><div class="subtitulo">REPORTE DE VENTAS</div><div class="subtitulo">PERIODO: ${periodoTexto}</div></div></header><table><tbody>${filas}</tbody></table></body></html>`;
            if (typeof window.electronAPI?.printHtml === 'function') {
                const resultado = await window.electronAPI.printHtml({ html, title: 'REPORTE DE VENTAS', silent: false, preview: true, pageSize: 'A4' });
                if (resultado?.success) return;
                if (resultado?.canceled) return;
                Swal.fire({ icon: 'error', title: 'ERROR DE SALIDA', text: resultado?.error || 'No se pudo generar el reporte.' });
                return;
            }
            const ventanaImpresion = window.open('about:blank', '_blank', 'toolbar=0,menubar=0,scrollbars=1,resizable=1,width=1100,height=800');
            if (ventanaImpresion) {
                ventanaImpresion.document.open();
                ventanaImpresion.document.write(html);
                ventanaImpresion.document.close();
                ventanaImpresion.document.title = 'REPORTE DE VENTAS';
                ventanaImpresion.focus();
                setTimeout(() => {
                    try {
                        ventanaImpresion.focus();
                        ventanaImpresion.print();
                    } catch (error) {
                        console.warn('No se pudo abrir el diálogo de impresión del reporte:', error);
                    }
                }, 300);
                return;
            }
            Swal.fire({ icon: 'warning', title: 'VENTANA BLOQUEADA', text: 'Permite ventanas emergentes para imprimir el reporte de ventas.' });
        }

        function parseFechaInventario(valor) {
            const texto = String(valor || '').trim();
            if (!texto) return 0;
            const partesIso = texto.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
            if (partesIso) {
                const [, anio, mes, dia, hora, minuto, segundo] = partesIso;
                return new Date(Number(anio), Number(mes) - 1, Number(dia), Number(hora), Number(minuto), Number(segundo || 0)).getTime();
            }
            const fecha = new Date(texto.includes('T') ? texto : texto.replace(' ', 'T'));
            if (!Number.isNaN(fecha.getTime())) return fecha.getTime();
            const partes = texto.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
            if (!partes) return 0;
            return new Date(
                Number(partes[3]), Number(partes[2]) - 1, Number(partes[1]),
                Number(partes[4] || 0), Number(partes[5] || 0), Number(partes[6] || 0)
            ).getTime();
        }

        function cargarSalidas() {
            fetch(inventarioControllerUrl + '?action=obtenerSalidas')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('salidasTableBody');
                        if (!tbody) return; // Elemento no existe en la página actual
                        tbody.innerHTML = '';
                        salidasAgrupadasCache = {};
                        
                        if (data.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px; color: #64748b;">No hay salidas registradas aún.</td></tr>';
                            return;
                        }

                        const formatarFechaDia = (valorFecha) => {
                            const fechaObj = new Date(valorFecha || null);
                            if (isNaN(fechaObj.getTime())) {
                                return 'SIN FECHA';
                            }
                            return fechaObj.toLocaleDateString('es-CO', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit'
                            });
                        };

                        const numeroReferenciaSalida = (referencia) => {
                            const coincidencia = String(referencia || '').match(/(\d+)\s*$/);
                            return coincidencia ? Number(coincidencia[1]) : -1;
                        };

                        const salidasOrdenadas = [...data.data].sort((a, b) => {
                            const referenciaA = String(a.referencia || '').trim();
                            const referenciaB = String(b.referencia || '').trim();
                            const numeroA = numeroReferenciaSalida(referenciaA);
                            const numeroB = numeroReferenciaSalida(referenciaB);
                            if (numeroA !== numeroB) return numeroA - numeroB;
                            const fechaA = parseFechaInventario(a.fecha_salida || a.fecha_movimiento || a.fecha || a.created_at);
                            const fechaB = parseFechaInventario(b.fecha_salida || b.fecha_movimiento || b.fecha || b.created_at);
                            if (fechaA !== fechaB) return fechaA - fechaB;
                            return (Number(a.id) || 0) - (Number(b.id) || 0);
                        });

                        const grupos = {};
                        salidasOrdenadas.forEach(item => {
                            const referenciaDisplay = (item.referencia && String(item.referencia).trim())
                                ? String(item.referencia).trim()
                                : `SIN-REF-${item.id}`;

                            // Agrupar siempre por referencia para evitar duplicar facturas/ventas
                            // cuando varias filas del mismo documento llegan con marcas de fecha ligeramente distintas.
                            const key = referenciaDisplay;

                            if (!grupos[key]) {
                                const tipo = (item.tipo_salida || 'venta');

                                grupos[key] = {
                                    claveAgrupacion: key,
                                    referencia: referenciaDisplay,
                                    items: [],
                                    fechaRaw: item.fecha_salida || item.fecha_movimiento || item.fecha || item.created_at || null,
                                    tipo: tipo,
                                    origen: origenSalida(item),
                                    usuarioNombre: item.usuario_nombre ? String(item.usuario_nombre).trim() : 'N/A',
                                    usuarioApellidos: String(item.usuario_apellidos || '').trim(),
                                    usuarioRol: String(item.usuario_rol || '').trim(),
                                    usuario: item.usuario_nombre
                                        ? (`${item.usuario_nombre} ${item.usuario_apellidos || ''}`.trim() + (item.usuario_rol ? ` (${item.usuario_rol})` : '')).toUpperCase()
                                        : 'N/A',
                                    totalUnidades: 0,
                                    metodoPago: item.metodo_pago || 'efectivo',
                                    esCredito: esRegistroCredito(item.es_credito),
                                    total: 0
                                };
                            }

                            const cantidad = parseFloat(item.cantidad || 0) || 0;
                            const precio = obtenerPrecioUnitarioSalidaItem(item);
                            const subtotal = parseFloat(item.total_venta || 0) || obtenerSubtotalSalidaItem(item);

                            grupos[key].items.push(item);
                            grupos[key].totalUnidades += cantidad;
                            grupos[key].total += subtotal;
                        });

                        const gruposPorFecha = {};

                        Object.values(grupos)
                            .sort((a, b) => numeroReferenciaSalida(a.referencia) - numeroReferenciaSalida(b.referencia) || parseFechaInventario(a.fechaRaw) - parseFechaInventario(b.fechaRaw) || (Number(a.items?.[0]?.id) || 0) - (Number(b.items?.[0]?.id) || 0))
                            .forEach(grupo => {
                                const fechaDia = formatarFechaDia(grupo.fechaRaw);
                                if (!gruposPorFecha[fechaDia]) {
                                    gruposPorFecha[fechaDia] = {
                                        grupos: [],
                                        ultimaFechaTimestamp: 0,
                                        mayorReferencia: -1,
                                        totalUnidades: 0,
                                        totalValor: 0
                                    };
                                }
                                gruposPorFecha[fechaDia].grupos.push(grupo);
                                gruposPorFecha[fechaDia].mayorReferencia = Math.max(
                                    gruposPorFecha[fechaDia].mayorReferencia,
                                    numeroReferenciaSalida(grupo.referencia)
                                );
                                gruposPorFecha[fechaDia].ultimaFechaTimestamp = Math.max(
                                    gruposPorFecha[fechaDia].ultimaFechaTimestamp,
                                    parseFechaInventario(grupo.fechaRaw) || 0
                                );
                                gruposPorFecha[fechaDia].totalUnidades += grupo.totalUnidades;
                                gruposPorFecha[fechaDia].totalValor += grupo.total || 0;
                            });

                        Object.entries(gruposPorFecha)
                            .sort(([, grupoA], [, grupoB]) => grupoA.mayorReferencia - grupoB.mayorReferencia || grupoA.ultimaFechaTimestamp - grupoB.ultimaFechaTimestamp)
                            .forEach(([fechaDia, grupoFecha]) => {
                                const headerRow = document.createElement('tr');
                                headerRow.className = 'group-date';
                                headerRow.innerHTML = `
                                    <td colspan="${obtenerColspanSalidas()}">
                                        <div class="group-date-summary">
                                            <div class="group-date-total">
                                                <span class="group-date-label">TOTAL DE PRODUCTOS:</span>
                                                <strong class="group-date-value">${grupoFecha.grupos.reduce((total, grupo) => total + grupo.items.length, 0)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">FECHA:</span>
                                                <strong class="group-date-value">${escapeHtmlInventario(fechaDia)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">CANTIDAD:</span>
                                                <strong class="group-date-value">${grupoFecha.totalUnidades.toLocaleString('es-CO')}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">VALOR TOTAL:</span>
                                                <strong class="group-date-value">${formatoMonedaInventario(grupoFecha.totalValor)}</strong>
                                            </div>
                                        </div>
                                    </td>`;
                                tbody.appendChild(headerRow);

                                grupoFecha.grupos.forEach(grupo => {
                                    salidasAgrupadasCache[grupo.claveAgrupacion] = {
                                        ...grupo,
                                        fecha: convertirFechaLocalInventario(grupo.fechaRaw).toLocaleString('es-CO', { hour12: false })
                                    };

                                    const row = document.createElement('tr');
                                    const tipo = grupo.tipo || 'venta';
                                    const origen = grupo.origen || origenSalida({ tipo_salida: tipo, metodo_pago: grupo.metodoPago });
                                    const etiquetaTipo = (etiquetaTipoSalida(tipo, 0, grupo.metodoPago, grupo.esCredito) || '').toUpperCase();
                                    let tipoBadge = 'badge-success';
                                    if (etiquetaTipo === 'CRÉDITO') tipoBadge = 'badge-credit';
                                    if (['DAÑADO', 'PÉRDIDA'].includes(etiquetaTipo)) tipoBadge = 'badge-danger';

                                    const productosPreview = grupo.items
                                        .slice(0, 2)
                                        .map(p => `${(p.producto_nombre || 'N/A').toUpperCase()} (${(p.codigo || 'N/A').toUpperCase()})` )
                                        .join('· ');
                                    const extra = grupo.items.length > 2 ? ` +${grupo.items.length - 2} más` : '';
                                    const fechaHoraGrupo = fechaHoraImpresion(grupo.fechaRaw || grupo.fecha);
                                    const usuarioNombre = String(grupo.usuarioNombre || 'N/A').trim();
                                    const usuarioApellidos = String(grupo.usuarioApellidos || '').trim();
                                    const usuarioRol = String(grupo.usuarioRol || '').trim();
                                    const usuarioHtml = `<div style="display:flex;flex-direction:column;align-items:center;gap:2px;text-align:center;">${usuarioNombre ? `<span>${escapeHtml(usuarioNombre)}</span>` : ''}${usuarioApellidos ? `<span>${escapeHtml(usuarioApellidos)}</span>` : ''}${usuarioRol ? `<span>${escapeHtml(usuarioRol)}</span>` : ''}</div>`;

                                    row.innerHTML = `
                                        <td>
                                            <strong>${grupo.referencia}</strong><br>
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px; margin-top:4px; text-align:center;">
                                                <span>${escapeHtml(fechaHoraGrupo.fecha)}</span>
                                                <span>${escapeHtml(fechaHoraGrupo.hora)}</span>
                                            </div>
                                        </td>
                                        <td>${productosPreview}${extra}</td>
                                        <td>${grupo.totalUnidades.toLocaleString('es-CO')}</td>
                                        <td><strong>${formatoMonedaInventario(grupo.total)}</strong></td>
                                        <td><span class="badge ${tipoBadge}">${escapeHtml(etiquetaTipo)}</span></td>
                                        <td style="white-space: normal; word-break: break-word;">${escapeHtml(metodoPagoVisible(tipo, grupo.metodoPago))}</td>
                                        <td>${(escapeHtml(origen) || 'N/A').toUpperCase()}</td>
                                        <td>
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px; text-align:center;">
                                                <span>${escapeHtml(fechaHoraGrupo.fecha)}</span>
                                                <span>${escapeHtml(fechaHoraGrupo.hora)}</span>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">${usuarioHtml}</td>
                                        <td style="white-space: nowrap;">
                                            <button type="button" class="btn-info btn-action" title="Ver detalle de salida" onclick="mostrarDetallesSalidaVenta('${String(grupo.claveAgrupacion || grupo.referencia).replace(/'/g, "\\'")}')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn-info btn-action" title="EDITAR FACTURA" onclick="mostrarModalEditarFacturaVenta('${String(grupo.claveAgrupacion || grupo.referencia).replace(/'/g, "\\'")}')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn-info btn-action" title="IMPRIMIR VENTA" onclick="imprimirVentaSalida('${String(grupo.claveAgrupacion || grupo.referencia).replace(/'/g, "\\'")}')">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </td>
                                    `;
                                    tbody.appendChild(row);
                                });
                            });

                        try { document.dispatchEvent(new Event('inventory:refreshed')); } catch(e) {}
                    }
                })
                .catch(e => console.error('Error:', e));
        }
        
        function mostrarDetallesSalidaVenta(referencia) {
            const grupo = salidasAgrupadasCache[referencia]
                || Object.values(salidasAgrupadasCache).find(item => String(item.referencia || '') === String(referencia || ''));
            if (!grupo) {
                alert('No se encontraron detalles de la venta');
                return;
            }
            mostrarModalDetallesSalidaVenta(referencia, grupo);
        }

        function mostrarModalEditarFacturaVenta(referencia) {
            const grupo = salidasAgrupadasCache[referencia]
                || Object.values(salidasAgrupadasCache).find(item => String(item.referencia || '') === String(referencia || ''));
            if (!grupo) {
                alert('No se encontraron detalles de la venta');
                return;
            }

            const content = document.getElementById('detallesMovimientosContent');
            const titleElement = document.getElementById('detallesModalTitle');
            titleElement.innerHTML = '<i class="fas fa-edit"></i> EDITAR FACTURA';

            const filas = grupo.items.map(item => {
                const cantidad = parseFloat(item.cantidad || 0) || 0;
                const id = Number(item.id || 0);
                const precio = obtenerPrecioUnitarioSalidaItem(item);
                const subtotal = obtenerSubtotalSalidaItem(item);
                const imagen = item.producto_imagen ? `<img src="${resolveAppUrl('/Assets/images/productos/' + item.producto_imagen)}" style="max-width: 50px; max-height: 50px; object-fit: contain;">` : '<div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">S/img</div>';

                return `
                    <tr data-precio-unitario="${precio}">
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle;">${imagen}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle;">${(escapeHtml(item.producto_nombre) || '').toUpperCase()}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle;">${(item.codigo || 'N/A').toUpperCase()}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle;">
                            <div class="factura-cantidad-editor" style="display: inline-flex; align-items: center; gap: 4px; justify-content: center;">
                                <button type="button" class="btn-action" data-step="-1" onclick="cambiarCantidadFacturaControl(this, -1)" title="Quitar 1" style="width: 30px; height: 30px; padding: 0; background: var(--primary-blue); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 16px; line-height: 1; display: inline-flex; align-items: center; justify-content: center;">−</button>
                                <input type="number" name="itemEditarFactura" value="${cantidad}" min="0" max="${cantidad}" step="1" data-id="${id}" data-original="${cantidad}" style="width: 56px; text-align: center; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px; font-weight: 600; background: #fff; box-sizing: border-box;" oninput="validarCantidadFacturaControl(this)" onchange="validarCantidadFacturaControl(this)" />
                                <button type="button" class="btn-action" data-step="1" onclick="cambiarCantidadFacturaControl(this, 1)" title="Agregar 1" style="width: 30px; height: 30px; padding: 0; background: var(--primary-blue); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 16px; line-height: 1; display: inline-flex; align-items: center; justify-content: center;">+</button>
                            </div>
                        </td>
                        <td class="factura-precio-unitario" style="border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle;">${formatoMonedaInventario(precio)}</td>
                        <td class="factura-subtotal" style="border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle; font-weight: bold;">${formatoMonedaInventario(subtotal)}</td>
                    </tr>
                `;
            }).join('');

            content.innerHTML = `
                <div style="font-family: Arial, sans-serif; color: #333;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3591CA; padding-bottom: 15px;">
                        <h3 style="margin: 0; color: #3591CA;">Editar factura ${escapeHtml(referencia)}</h3>
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">Ajusta la cantidad de cada producto. Si dejas el valor en 0, ese producto se elimina de la factura y regresa al inventario.</p>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                        <thead>
                            <tr style="background-color: #f0f0f0;">
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 60px;">Imagen</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Producto</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Código</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; min-width: 150px;">Cantidad</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Precio Unit.</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>${filas || '<tr><td colspan="6" style="padding: 14px; text-align: center;">No hay productos para editar.</td></tr>'}</tbody>
                    </table>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
                        <button type="button" class="btn-nuevo" onclick="cerrarModal('detallesMovimientosModal')" style="padding: 8px 14px; background: #64748b; border-color: #64748b; font-size: 12px; line-height: 1.1;">
                            <i class="fas fa-times"></i> CANCELAR
                        </button>
                        <button type="button" class="btn-nuevo" onclick="guardarEdicionFacturaVenta('${String(referencia).replace(/'/g, "\\'")}')" style="padding: 8px 14px; font-size: 12px; line-height: 1.1;">
                            <i class="fas fa-save"></i> ACTUALIZAR
                        </button>
                    </div>
                </div>
            `;

            abrirModal('detallesMovimientosModal');
        }

        function cambiarCantidadFacturaControl(button, delta) {
            const editor = button.closest('.factura-cantidad-editor');
            if (!editor) return;
            const input = editor.querySelector('input[name="itemEditarFactura"]');
            if (!input) return;

            const original = Math.max(0, parseFloat(input.dataset.original || input.value || 0) || 0);
            let nuevoValor = Math.max(0, parseFloat(input.value || 0) || 0);
            nuevoValor = Math.min(Math.max(nuevoValor + delta, 0), original);
            input.value = String(nuevoValor);
            input.setAttribute('max', String(original));
            input.dataset.nuevoValor = String(nuevoValor);
        }

        function validarCantidadFacturaControl(input) {
            const original = Math.max(0, parseFloat(input.dataset.original || input.value || 0) || 0);
            const valor = Math.max(0, Math.min(parseFloat(input.value || 0) || 0, original));
            input.value = String(valor);

            const fila = input.closest('tr');
            if (!fila) return;

            const precioUnitario = parseFloat(fila.dataset.precioUnitario || 0) || 0;
            const subtotalCell = fila.querySelector('.factura-subtotal');
            if (subtotalCell) {
                const nuevoSubtotal = precioUnitario * valor;
                subtotalCell.textContent = formatoMonedaInventario(nuevoSubtotal);
            }
        }

        async function guardarEdicionFacturaVenta(referencia) {
            const items = Array.from(document.querySelectorAll('input[name="itemEditarFactura"]'))
                .map(input => {
                    const id = Number(input.dataset.id || 0);
                    const cantidad = Math.max(0, Math.min(parseFloat(input.value || 0) || 0, parseFloat(input.dataset.original || 0) || 0));
                    return {
                        id,
                        cantidad
                    };
                })
                .filter(item => Number.isFinite(item.id) && item.id > 0);

            if (!items.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin cambios',
                    text: 'No hay productos en la factura para actualizar.'
                });
                return;
            }

            const confirmacion = await Swal.fire({
                icon: 'question',
                title: '¿Actualizar factura?',
                text: `Se ajustarán las cantidades de la factura ${referencia}. Si alguna queda en 0, ese producto regresará al inventario.`,
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar'
            });

            if (!confirmacion.isConfirmed) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'editarFactura');
            formData.append('referencia', referencia);
            formData.append('items_actualizar', JSON.stringify(items));

            try {
                const response = await fetch(inventarioControllerUrl, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'No se pudo actualizar la factura');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Factura actualizada',
                    text: data.message || 'La factura se actualizó correctamente.'
                });

                cerrarModal('detallesMovimientosModal');
                cargarSalidas();
                dispararRefreshInventarioGlobal();
                refrescarInventarioInmediato();
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'No se pudo actualizar la factura.'
                });
            }
        }
        
        function mostrarModalDetallesSalidaVenta(titulo, grupo) {
            const modal = document.getElementById('detallesMovimientosModal');
            const content = document.getElementById('detallesMovimientosContent');
            const titleElement = document.getElementById('detallesModalTitle');
            
            // Cambiar título del modal
            titleElement.innerHTML = '<i class="fas fa-file-invoice"></i> DETALLES DE SALIDA';
            
            let html = `
                <div style="font-family: Arial, sans-serif; color: #333;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3591CA; padding-bottom: 15px;">
                        <h3 style="margin: 0; color: #3591CA;">Detalles de Salida ${titulo}</h3>
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">Fecha: ${grupo.fecha}</p>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                        <thead>
                            <tr style="background-color: #f0f0f0;">
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 60px;">Imagen</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Producto</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Código</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Cantidad</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Precio Unit.</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            grupo.items.forEach(item => {
                const cantidad = parseFloat(item.cantidad || 0) || 0;
                const precio = obtenerPrecioUnitarioSalidaItem(item);
                const subtotal = parseFloat(item.total_venta || 0) || obtenerSubtotalSalidaItem(item);
                const imagen = item.producto_imagen ? `<img src="${resolveAppUrl('/Assets/images/productos/' + item.producto_imagen)}" style="max-width: 50px; max-height: 50px; object-fit: contain;">` : '<div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">S/img</div>';
                
                html += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${imagen}</td>
                        <td style="border: 1px solid #ddd; padding: 10px;">${(escapeHtml(item.producto_nombre) || '').toUpperCase()}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${(item.codigo || 'N/A').toUpperCase()}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${cantidad.toLocaleString('es-CO')}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${formatoMonedaInventario(precio)}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right; font-weight: bold;">${formatoMonedaInventario(subtotal)}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #f0f0f0; font-weight: bold;">
                                <td colspan="4" style="border: 1px solid #ddd; padding: 10px; text-align: right;">TOTAL:</td>
                                <td colspan="2" style="border: 1px solid #ddd; padding: 10px; text-align: right;">${formatoMonedaInventario(grupo.total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; text-align: right;">
                        <button onclick="imprimirVentaSalida('${String(titulo).replace(/'/g, "\\'")}')" class="btn-action" style="padding: 10px 20px; background-color: #3591CA; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
            `;
            
            content.innerHTML = html;
            abrirModal('detallesMovimientosModal');
        }

        // Cargar movimientos
        function cargarMovimientos() {
            fetch(inventarioControllerUrl + '?action=obtenerMovimientos')
                .then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP error ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    const tbody = document.getElementById('movimientosTableBody');
                    if (!tbody) return;

                    tbody.innerHTML = '';
                    movimientosAgrupadosCache = {};

                    if (!data.success) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #ff6b6b;">ERROR: ' + escapeHtml(data.message || 'No se pudo cargar los movimientos') + '</td></tr>';
                        return;
                    }

                    if (!Array.isArray(data.data) || data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #64748b;">No hay movimientos registrados aún.</td></tr>';
                        return;
                    }

                        const movimientosOrdenados = [...data.data].sort((a, b) => {
                            const fechaB = parseFechaInventario(b.fecha_movimiento || b.fecha_salida || b.fecha || b.created_at);
                            const fechaA = parseFechaInventario(a.fecha_movimiento || a.fecha_salida || a.fecha || a.created_at);
                            if (fechaB !== fechaA) return fechaA - fechaB;
                            return (Number(a.id) || 0) - (Number(b.id) || 0);
                        });

                        const formatarFechaDia = (valorFecha) => {
                            const fechaObj = new Date(valorFecha || null);
                            if (isNaN(fechaObj.getTime())) {
                                return 'SIN FECHA';
                            }
                            return fechaObj.toLocaleDateString('es-CO', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit'
                            });
                        };

                        const grupos = {};
                        movimientosOrdenados.forEach(item => {
                            // Agrupar por referencia (igual a SALIDA)
                            const key = item.referencia || `MOV-${item.id}`;
                            if (!grupos[key]) {
                                grupos[key] = {
                                    referencia: key,
                                    items: [],
                                    fechaRaw: item.fecha_movimiento || item.fecha_salida || item.fecha || item.created_at || null,
                                    tipo: item.tipo_movimiento || 'movimiento',
                                    origen: origenMovimiento(item),
                                    usuarioNombre: item.usuario_nombre ? String(item.usuario_nombre).trim() : 'N/A',
                                    usuarioApellidos: String(item.usuario_apellidos || '').trim(),
                                    usuarioRol: String(item.usuario_rol || '').trim(),
                                    usuario: item.usuario_nombre
                                        ? (`${item.usuario_nombre} ${item.usuario_apellidos || ''}`.trim() + (item.usuario_rol ? ` (${item.usuario_rol})` : '')).toUpperCase()
                                        : 'N/A',
                                    totalUnidades: 0
                                    ,metodoPago: item.metodo_pago || 'efectivo'
                                    ,tipoSalida: String(item.tipo_salida || '').trim()
                                    ,esCredito: esRegistroCredito(item.es_credito)
                                };
                            }

                            const cantidad = parseFloat(item.cantidad || 0) || 0;
                            grupos[key].items.push(item);
                            grupos[key].totalUnidades += cantidad;
                        });

                        const gruposPorFecha = {};

                        Object.values(grupos)
                            .sort((a, b) => parseFechaInventario(a.fechaRaw) - parseFechaInventario(b.fechaRaw) || (Number(a.items?.[0]?.id) || 0) - (Number(b.items?.[0]?.id) || 0))
                            .forEach(grupo => {
                                const fechaDia = formatarFechaDia(grupo.fechaRaw);
                                if (!gruposPorFecha[fechaDia]) {
                                    gruposPorFecha[fechaDia] = {
                                        grupos: [],
                                        totalUnidades: 0
                                    };
                                }
                                gruposPorFecha[fechaDia].grupos.push(grupo);
                                gruposPorFecha[fechaDia].totalUnidades += grupo.totalUnidades;
                            });

                        Object.entries(gruposPorFecha)
                            .sort(([, grupoA], [, grupoB]) => {
                                const diferencia = parseFechaInventario(grupoA.grupos?.[0]?.fechaRaw) - parseFechaInventario(grupoB.grupos?.[0]?.fechaRaw);
                                return diferencia || (Number(grupoA.grupos?.[0]?.items?.[0]?.id) || 0) - (Number(grupoB.grupos?.[0]?.items?.[0]?.id) || 0);
                            })
                            .forEach(([fechaDia, grupoFecha]) => {
                                const headerRow = document.createElement('tr');
                                headerRow.className = 'group-date';
                                headerRow.innerHTML = `
                                    <td colspan="${obtenerColspanMovimientos()}">
                                        <div class="group-date-summary">
                                            <div class="group-date-total">
                                                <span class="group-date-label">TOTAL DE PRODUCTOS:</span>
                                                <strong class="group-date-value">${grupoFecha.grupos.reduce((total, grupo) => total + grupo.items.length, 0)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">FECHA:</span>
                                                <strong class="group-date-value">${escapeHtmlInventario(fechaDia)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">CANTIDAD:</span>
                                                <strong class="group-date-value">${grupoFecha.totalUnidades.toLocaleString('es-CO')}</strong>
                                            </div>
                                        </div>
                                    </td>`;
                                tbody.appendChild(headerRow);

                                grupoFecha.grupos.forEach(grupo => {
                                    movimientosAgrupadosCache[grupo.referencia] = {
                                        ...grupo,
                                        fecha: new Date(grupo.fechaRaw || 0).toLocaleString('es-CO')
                                    };

                                    const row = document.createElement('tr');
                                    const tipo = grupo.tipo || 'movimiento';
                                    const origen = grupo.origen || 'Movimiento';
                                    const tipoSalidaMov = String(grupo.tipoSalida || '').trim();
                                    const etiquetaMovimiento = tipo === 'salida'
                                        ? (tipoSinCobro(tipoSalidaMov)
                                            ? etiquetaTipoSalida(tipoSalidaMov, 0, '', false)
                                            : (String(origen).toUpperCase() === 'CRÉDITO' ? 'CRÉDITO' : 'INVENTARIO'))
                                        : (etiquetaTipoMovimiento(tipo) || '').toUpperCase();
                                    let tipoBadge = 'badge-info';
                                    if (tipo === 'entrada') tipoBadge = 'badge-success';
                                    if (tipo === 'salida') {
                                        tipoBadge = ['DAÑADO', 'PÉRDIDA'].includes(etiquetaMovimiento)
                                            ? 'badge-danger'
                                            : (etiquetaMovimiento === 'CRÉDITO' ? 'badge-credit' : 'badge-success');
                                    }

                                    const productosPreview = grupo.items
                                        .slice(0, 2)
                                        .map(p => `${p.producto_nombre || 'N/A'} (${p.codigo || 'N/A'})`)
                                        .join('· ');
                                    const extra = grupo.items.length > 2 ? ` +${grupo.items.length - 2} más` : '';
                                    const fechaHoraGrupo = fechaHoraImpresion(grupo.fechaRaw || grupo.fecha);
                                    const usuarioNombre = String(grupo.usuarioNombre || 'N/A').trim();
                                    const usuarioApellidos = String(grupo.usuarioApellidos || '').trim();
                                    const usuarioRol = String(grupo.usuarioRol || '').trim();
                                    const usuarioHtml = `<div style="display:flex;flex-direction:column;align-items:center;gap:2px;text-align:center;">${usuarioNombre ? `<span>${escapeHtml(usuarioNombre)}</span>` : ''}${usuarioApellidos ? `<span>${escapeHtml(usuarioApellidos)}</span>` : ''}${usuarioRol ? `<span>${escapeHtml(usuarioRol)}</span>` : ''}</div>`;

                                    row.innerHTML = `
                                        <td>
                                            <strong>${grupo.referencia}</strong><br>
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px; margin-top:4px; text-align:center;">
                                                <span>${escapeHtml(fechaHoraGrupo.fecha)}</span>
                                                <span>${escapeHtml(fechaHoraGrupo.hora)}</span>
                                            </div>
                                        </td>
                                        <td>${productosPreview}${extra}</td>
                                        <td>${grupo.totalUnidades.toLocaleString('es-CO')}</td>
                                        <td><span class="badge ${tipoBadge}">${escapeHtml(etiquetaMovimiento)}</span></td>
                                        <td>${escapeHtml(metodoPagoVisible(grupo.tipoSalida, grupo.metodoPago))}</td>
                                        <td>${escapeHtml(origen)}</td>
                                        <td style="text-align:center;">
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px;text-align:center;">
                                                <span>${escapeHtml(fechaHoraGrupo.fecha)}</span>
                                                <span>${escapeHtml(fechaHoraGrupo.hora)}</span>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">${usuarioHtml}</td>
                                        <td style="white-space: nowrap;">
                                            <button type="button" class="btn-info btn-action" title="Ver detalle de movimiento" onclick="mostrarDetallesMovimiento('${String(grupo.referencia).replace(/'/g, "\\'")}')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn-info btn-action" title="IMPRIMIR MOVIMIENTO" onclick="imprimirMovimiento('${String(grupo.referencia).replace(/'/g, "\\'")}')">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </td>
                                    `;
                                    tbody.appendChild(row);
                                });
                            });
                })
                .catch(e => console.error('Error:', e));
        }
        
        function mostrarDetallesMovimiento(referencia) {
            const grupo = movimientosAgrupadosCache[referencia];
            if (!grupo) {
                alert('No se encontraron detalles del movimiento');
                return;
            }
            mostrarModalDetallesMovimiento(referencia, grupo);
        }
        
        async function imprimirMovimiento(referenciaMovimiento, generarPdf = false) {
            const key = String(referenciaMovimiento || '');
            const grupo = movimientosAgrupadosCache[key];
            if (!grupo) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se encontró el movimiento a imprimir.' });
                return;
            }
            const claveTemporal = `__movimiento__${key}`;
            salidasAgrupadasCache[claveTemporal] = {
                ...grupo,
                total: (grupo.items || []).reduce((total, item) => total + obtenerSubtotalSalidaItem(item), 0)
            };
            try {
                await imprimirVentaSalida(claveTemporal, generarPdf);
            } finally {
                delete salidasAgrupadasCache[claveTemporal];
            }
        }
        
        function mostrarModalDetallesMovimiento(titulo, grupo) {
            const modal = document.getElementById('detallesMovimientosModal');
            const content = document.getElementById('detallesMovimientosContent');
            const titleElement = document.getElementById('detallesModalTitle');
            
            // Cambiar título del modal
            titleElement.innerHTML = '<i class="fas fa-file-invoice"></i> DETALLES DE MOVIMIENTO';
            
            let html = `
                <div style="font-family: Arial, sans-serif; color: #333;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3591CA; padding-bottom: 15px;">
                        <h3 style="margin: 0; color: #3591CA;">Movimiento ${titulo}</h3>
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">Fecha: ${grupo.fecha}</p>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                        <thead>
                            <tr style="background-color: #f0f0f0;">
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; width: 60px;">Imagen</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Producto</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Código</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Cantidad</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Precio Unit.</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Subtotal</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Stock Anterior</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Stock Nuevo</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let totalCantidad = 0;
            let totalValor = 0;
            grupo.items.forEach(item => {
                totalCantidad += Number(item.cantidad || 0);
                const precio = obtenerPrecioUnitarioSalidaItem(item);
                const subtotal = item.total_venta != null
                    ? parseFloat(item.total_venta) || 0
                    : item.total_movimiento != null
                        ? parseFloat(item.total_movimiento) || 0
                        : obtenerSubtotalSalidaItem(item);
                totalValor += subtotal;
                const imagen = item.producto_imagen ? `<img src="${resolveAppUrl('/Assets/images/productos/' + item.producto_imagen)}" style="max-width: 50px; max-height: 50px; object-fit: contain; border-radius: 4px; border: 1px solid #ddd;">` : '<div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px; border-radius: 4px; border: 1px solid #ddd;">S/img</div>';
                
                html += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${imagen}</td>
                        <td style="border: 1px solid #ddd; padding: 10px;">${(escapeHtml(item.producto_nombre) || '').toUpperCase()}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${(item.codigo || 'N/A').toUpperCase()}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${Number(item.cantidad || 0).toLocaleString('es-CO')}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">${formatoMonedaInventario(precio)}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right; font-weight: bold;">${formatoMonedaInventario(subtotal)}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">${item.stock_anterior || 0}</td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: center; font-weight: bold;">${item.stock_nuevo || 0}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #f0f0f0; font-weight: bold;">
                                <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right;">TOTAL UNIDADES:</td>
                                <td colspan="2" style="border: 1px solid #ddd; padding: 10px; text-align: center;">${totalCantidad.toLocaleString('es-CO')}</td>
                                <td colspan="1" style="border: 1px solid #ddd; padding: 10px; text-align: right;">${formatoMonedaInventario(totalValor)}</td>
                                <td colspan="2" style="border: 1px solid #ddd; padding: 10px; text-align: center;">&nbsp;</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; text-align: right;">
                        <button onclick="imprimirMovimiento('${String(titulo).replace(/'/g, "\\'")}')" class="btn-action" style="padding: 10px 20px; background-color: #3591CA; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
            `;
            
            content.innerHTML = html;
            abrirModal('detallesMovimientosModal');
        }
        
        function obtenerColspanSalidas() {
            return 10;
        }

        function obtenerColspanMovimientos() {
            return 9;
        }

        // Mostrar resumen de ventas del día
        function mostrarResumenVentasDia() {
            abrirModal('ventasDiaModal');
            const btnDia = document.querySelector('#ventasDiaModal .tab-btn:first-of-type');
            if (btnDia) {
                cambiarPeriodoVentas(btnDia, 'dia');
            } else {
                cargarVentasDia();
            }
        }
        
        // Cambiar entre día y mes en el modal de ventas
        function cambiarPeriodoVentas(btn, periodo) {
            // Desactivar todos los tabs del modal
            document.querySelectorAll('#ventasDiaModal .tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('#ventasDiaModal .tab-btn').forEach(b => b.classList.remove('active'));
            
            // Activar el tab seleccionado
            btn.classList.add('active');
            if (periodo === 'dia') {
                document.getElementById('ventasDia').classList.add('active');
                cargarVentasDia();
            } else if (periodo === 'mes') {
                document.getElementById('ventasMes').classList.add('active');
                cargarVentasMes();
            } else if (periodo === 'imprimir') {
                document.getElementById('ventasImprimir').classList.add('active');
                cargarSelectorImpresionVentas();
            }
        }
        
        // Cargar ventas del día
        function cargarVentasDia(mostrarError = true) {
            fetch(inventarioControllerUrl + '?action=obtenerVentasDia')
                .then(async r => {
                    const raw = await r.text();
                    let data = null;

                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        const respuestaCorta = (raw || '').trim().replace(/\s+/g, ' ').slice(0, 220);
                        throw new Error(`Respuesta no JSON (HTTP ${r.status}). ${respuestaCorta || 'Sin contenido'}`);
                    }

                    if (!r.ok) {
                        throw new Error(data.message || `Error HTTP ${r.status}`);
                    }

                    return data;
                })
                .then(data => {
                    if (!data.success) {
                        if (mostrarError) {
                            Swal.fire({
                                icon: 'error',
                                title: 'ERROR',
                                text: data.message || 'Error al cargar ventas del día'
                            });
                        }
                        return;
                    }
                    
                    if (!data.data) return;
                    
                    const ventas = data.data;
                    const fechaVentasDia = document.getElementById('fechaVentasDia');
                    const totalVentasDia = document.getElementById('totalVentasDia');
                    
                    // Si no están los elementos, es desde refrescar (silent)
                    if (!fechaVentasDia || !totalVentasDia) {
                        return;
                    }
                    
                    // Actualizar fecha
                    const fecha = new Date(ventas.fecha);
                    const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    fechaVentasDia.textContent = fecha.toLocaleDateString('es-CO', opciones);
                    
                    // Actualizar totales
                    totalVentasDia.textContent = ventas.total_ventas;
                    
                    if (document.getElementById('unidadesVendidasDia')) {
                        document.getElementById('unidadesVendidasDia').textContent = ventas.unidades_vendidas;
                    }
                    if (document.getElementById('valorVentasDia')) {
                        document.getElementById('valorVentasDia').textContent = formatoMonedaCompleta(ventas.valor_total_ventas);
                    }
                    if (document.getElementById('gananciaDia')) {
                        const totalGanDia = parseFloat(ventas.ganancia_total_dia ?? ventas.ganancia_dia ?? 0) || 0;
                        document.getElementById('gananciaDia').textContent = formatoMonedaCompleta(totalGanDia);
                    }
                    if (document.getElementById('efectivoDia')) document.getElementById('efectivoDia').textContent = formatoMonedaCompleta(ventas.total_efectivo || 0);
                    if (document.getElementById('transferenciaDia')) document.getElementById('transferenciaDia').textContent = formatoMonedaCompleta(ventas.total_transferencia || 0);
                    ajustarTamanoResumenVentas('Dia');
                    
                    // Actualizar tabla de productos vendidos
                    const tbody = document.getElementById('productosVendidosDia');
                    if (tbody) {
                        tbody.innerHTML = '';
                        
                        if (!ventas.productos_vendidos || ventas.productos_vendidos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">No hay ventas registradas hoy</td></tr>';
                        } else {
                            ventas.productos_vendidos.forEach(item => {
                                const row = document.createElement('tr');
                                const gananciaValor = parseFloat(item.total_ganancia ?? item.ganancia_total ?? 0) || 0;
                                const imgSrc = resolverImagenProductoInventario(item.imagen);
                                const fechaHoraVenta = item.ultima_venta
                                    ? fechaHoraImpresion(item.ultima_venta)
                                    : { fecha: '-', hora: '-' };
                                row.innerHTML = `
                                    <td><div class="ventas-fecha-hora"><span>${escapeHtmlInventario(fechaHoraVenta.fecha)}</span><span>${escapeHtmlInventario(fechaHoraVenta.hora)}</span></div></td>
                                    <td style="text-align:center;"><img src="${imgSrc}" alt="${escapeHtmlInventario(item.nombre)}" class="producto-img" style="width:32px;height:32px;object-fit:contain;border-radius:6px;background:#fff;padding:2px;" onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"></td>
                                    <td><strong>${item.codigo}</strong></td>
                                    <td>${item.nombre}</td>
                                    <td>${item.categoria || 'Sin categoría'}</td>
                                    <td><strong>${item.cantidad_vendida}</strong></td>
                                    <td>${formatoMonedaCompleta(item.precio)}</td>
                                    <td>${formatoMonedaCompleta(gananciaValor)}</td>
                                    <td><strong>${formatoMonedaCompleta(item.total_vendido)}</strong></td>
                                `;
                                tbody.appendChild(row);
                            });
                        }
                    }
                })
                .catch(e => {
                    if (mostrarError) {
                        Swal.fire({
                            icon: 'error',
                            title: 'ERROR',
                            text: 'No se pudo cargar el resumen de ventas del día'
                        });
                    }
                });
        }
        
        // Cargar ventas del mes
        function cargarVentasMes(mostrarError = true) {
            const selectorMes = document.getElementById('selectorMesVentas');
            const periodoSeleccionado = selectorMes ? (selectorMes.value || '').trim() : '';
            const queryPeriodo = periodoSeleccionado ? `&periodo=${encodeURIComponent(periodoSeleccionado)}` : '';

            fetch(`${inventarioControllerUrl}?action=obtenerVentasMes${queryPeriodo}`)
                .then(async r => {
                    const raw = await r.text();
                    let data = null;

                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        const respuestaCorta = (raw || '').trim().replace(/\s+/g, ' ').slice(0, 220);
                        throw new Error(`Respuesta no JSON (HTTP ${r.status}). ${respuestaCorta || 'Sin contenido'}`);
                    }

                    if (!r.ok) {
                        throw new Error(data.message || `Error HTTP ${r.status}`);
                    }

                    return data;
                })
                .then(data => {
                    console.log('Respuesta obtenerVentasMes:', data);
                    if (!data.success) {
                        const acumuladoMesValorError = document.getElementById('acumuladoMesValor');
                        const acumuladoMesGananciaError = document.getElementById('acumuladoMesGanancia');
                        if (acumuladoMesValorError) acumuladoMesValorError.textContent = '$0';
                        if (acumuladoMesGananciaError) acumuladoMesGananciaError.textContent = '$0';
                        if (mostrarError) {
                            Swal.fire({
                                icon: 'error',
                                title: 'ERROR',
                                text: data.message || 'Error al cargar ventas del mes'
                            });
                        }
                        return;
                    }
                    
                    if (!data.data) return;
                    
                    const ventas = data.data;
                    const mesVentasMes = document.getElementById('mesVentasMes');
                    const totalVentasMes = document.getElementById('totalVentasMes');
                    
                    // Si no están los elementos, es desde refrescar (silent)
                    if (!mesVentasMes || !totalVentasMes) {
                        return;
                    }
                    
                    // Actualizar mes
                    mesVentasMes.textContent = ventas.mes.toUpperCase();
                    
                    // Actualizar totales
                    totalVentasMes.textContent = ventas.total_ventas;
                    
                    if (document.getElementById('unidadesVendidasMes')) {
                        document.getElementById('unidadesVendidasMes').textContent = ventas.unidades_vendidas;
                    }
                    if (document.getElementById('valorVentasMes')) {
                        document.getElementById('valorVentasMes').textContent = formatoMonedaCompleta(ventas.valor_total_ventas);
                    }
                    if (document.getElementById('gananciaMes')) {
                        const totalGanMes = parseFloat(ventas.ganancia_total_mes ?? ventas.ganancia_mes ?? 0) || 0;
                        document.getElementById('gananciaMes').textContent = formatoMonedaCompleta(totalGanMes);
                    }
                    if (document.getElementById('efectivoMes')) document.getElementById('efectivoMes').textContent = formatoMonedaCompleta(ventas.total_efectivo || 0);
                    if (document.getElementById('transferenciaMes')) document.getElementById('transferenciaMes').textContent = formatoMonedaCompleta(ventas.total_transferencia || 0);
                    ajustarTamanoResumenVentas('Mes');

                    const acumuladoMesValor = document.getElementById('acumuladoMesValor');
                    const acumuladoMesGanancia = document.getElementById('acumuladoMesGanancia');
                    if (acumuladoMesValor) acumuladoMesValor.textContent = formatoMonedaCompleta(ventas.valor_total_ventas || 0);
                    if (acumuladoMesGanancia) acumuladoMesGanancia.textContent = formatoMonedaCompleta(ventas.ganancia_total_mes || ventas.ganancia_mes || 0);
                    
                    // Actualizar desglose por día/producto
                    const ventasPorDiaTabla = document.getElementById('ventasPorDiaTabla');
                    if (ventasPorDiaTabla) {
                        ventasPorDiaTabla.innerHTML = '';
                        if (ventas.ventas_detalle && ventas.ventas_detalle.length > 0) {
                            let lastDate = null;
                            ventas.ventas_detalle.forEach(d => {
                                // group by fecha
                                if (d.fecha !== lastDate) {
                                    const grp = document.createElement('tr');
                                    grp.className = 'group-date';
                                    grp.innerHTML = `<td colspan="8">${d.fecha}</td>`;
                                    ventasPorDiaTabla.appendChild(grp);
                                    lastDate = d.fecha;
                                }
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${d.fecha}</td>
                                    <td>${d.codigo}</td>
                                    <td>${d.nombre}</td>
                                    <td>${d.categoria || ''}</td>
                                    <td><strong>${d.cantidad_vendida}</strong></td>
                                    <td>${formatoMonedaCompleta(d.precio_unitario)}</td>
                                    <td>${formatoMonedaCompleta(d.ganancia_total ?? d.porcentaje_ganancia ?? 0)}</td>
                                    <td><strong>${formatoMonedaCompleta(d.total_vendido)}</strong></td>
                                `;
                                ventasPorDiaTabla.appendChild(row);
                            });
                        } else {
                            ventasPorDiaTabla.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No hay registros por día</td></tr>';
                        }
                    }
                    
                    // Actualizar tabla principal del mes separada por día
                    const productosVendidosMes = document.getElementById('productosVendidosMes');
                    if (productosVendidosMes) {
                        productosVendidosMes.innerHTML = '';

                        if (!ventas.ventas_detalle || ventas.ventas_detalle.length === 0) {
                            productosVendidosMes.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No hay ventas registradas este mes</td></tr>';
                        } else {
                            const resumenPorDia = {};
                            if (ventas.ventas_por_dia && Array.isArray(ventas.ventas_por_dia)) {
                                ventas.ventas_por_dia.forEach(vd => {
                                    resumenPorDia[vd.fecha] = vd;
                                });
                            }

                            const acumuladoProducto = {};
                            let acumuladoMesTotal = 0;
                            let acumuladoMesGanancia = 0;

                            let fechaActual = null;
                            let unidadesDiaAcumuladas = 0;
                            let totalDiaAcumulado = 0;
                            let gananciaDiaAcumulada = 0;

                            const pintarResumenDia = (fechaDia) => {
                                if (!fechaDia) return;
                                const rowTotalDia = document.createElement('tr');
                                rowTotalDia.className = 'group-date';
                                rowTotalDia.innerHTML = `
                                    <td colspan="8">
                                        <div class="group-date-summary">
                                            <div class="group-date-total">
                                                <span class="group-date-label">TOTAL DE PRODUCTOS:</span>
                                                <strong class="group-date-value">${unidadesDiaAcumuladas.toLocaleString('es-CO')}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">FECHA:</span>
                                                <strong class="group-date-value">${escapeHtmlInventario(fechaDia)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">CANTIDAD:</span>
                                                <strong class="group-date-value">${unidadesDiaAcumuladas.toLocaleString('es-CO')}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">TOTAL:</span>
                                                <strong class="group-date-value">${formatoMonedaCompleta(totalDiaAcumulado)}</strong>
                                            </div>
                                            <div class="group-date-metric">
                                                <span class="group-date-label">GANANCIA:</span>
                                                <strong class="group-date-value">${formatoMonedaCompleta(gananciaDiaAcumulada)}</strong>
                                            </div>
                                        </div>
                                    </td>
                                `;
                                productosVendidosMes.appendChild(rowTotalDia);
                            };

                            const ventasDetalleOrdenado = [...ventas.ventas_detalle].sort((a, b) => {
                                const fechaA = new Date(a.ultima_venta || a.fecha || 0).getTime();
                                const fechaB = new Date(b.ultima_venta || b.fecha || 0).getTime();
                                if (fechaB !== fechaA) {
                                    return fechaB - fechaA;
                                }
                                return (Number(b.id) || 0) - (Number(a.id) || 0);
                            });

                            ventasDetalleOrdenado.forEach(item => {
                                const cantidadDiaProducto = parseFloat(item.cantidad_vendida || 0);
                                const totalDiaProducto = parseFloat(item.total_vendido || 0);
                                const gananciaDiaProducto = parseFloat(item.ganancia_total || 0);

                                if (item.fecha !== fechaActual) {
                                    if (fechaActual !== null) {
                                        pintarResumenDia(fechaActual);
                                    }

                                    const grp = document.createElement('tr');
                                    grp.className = 'group-date';
                                    const infoDia = resumenPorDia[item.fecha];
                                    const unidadesDia = infoDia ? parseInt(infoDia.unidades_dia || 0) : 0;
                                    const totalDia = infoDia ? parseFloat(infoDia.total_dia || 0) : 0;
                                    const gananciaDia = infoDia ? parseFloat(infoDia.ganancia_dia || 0) : 0;
                                    grp.innerHTML = `
                                        <td colspan="8">
                                            <div class="group-date-summary">
                                                <div class="group-date-total">
                                                    <span class="group-date-label">TOTAL DE PRODUCTOS:</span>
                                                    <strong class="group-date-value">${unidadesDia.toLocaleString('es-CO')}</strong>
                                                </div>
                                                <div class="group-date-metric">
                                                    <span class="group-date-label">FECHA:</span>
                                                    <strong class="group-date-value">${escapeHtmlInventario(item.fecha)}</strong>
                                                </div>
                                                <div class="group-date-metric">
                                                    <span class="group-date-label">CANTIDAD:</span>
                                                    <strong class="group-date-value">${unidadesDia.toLocaleString('es-CO')}</strong>
                                                </div>
                                                <div class="group-date-metric">
                                                    <span class="group-date-label">TOTAL:</span>
                                                    <strong class="group-date-value">${formatoMonedaCompleta(totalDia)}</strong>
                                                </div>
                                                <div class="group-date-metric">
                                                    <span class="group-date-label">GANANCIA:</span>
                                                    <strong class="group-date-value">${formatoMonedaCompleta(gananciaDia)}</strong>
                                                </div>
                                            </div>
                                        </td>`;
                                    productosVendidosMes.appendChild(grp);

                                    fechaActual = item.fecha;
                                    unidadesDiaAcumuladas = 0;
                                    totalDiaAcumulado = 0;
                                    gananciaDiaAcumulada = 0;
                                }

                                const keyProducto = item.codigo || item.nombre;
                                if (!acumuladoProducto[keyProducto]) {
                                    acumuladoProducto[keyProducto] = { cantidad: 0, total: 0, ganancia: 0 };
                                }
                                acumuladoProducto[keyProducto].cantidad += cantidadDiaProducto;
                                acumuladoProducto[keyProducto].total += totalDiaProducto;
                                acumuladoProducto[keyProducto].ganancia += gananciaDiaProducto;

                                unidadesDiaAcumuladas += cantidadDiaProducto;
                                totalDiaAcumulado += totalDiaProducto;
                                gananciaDiaAcumulada += gananciaDiaProducto;
                                acumuladoMesTotal += totalDiaProducto;
                                acumuladoMesGanancia += gananciaDiaProducto;

                                const row = document.createElement('tr');
                                const acumProd = acumuladoProducto[keyProducto];
                                const imgSrc = resolverImagenProductoInventario(item.imagen);
                                row.innerHTML = `
                                    <td style="text-align:center;"><img src="${imgSrc}" alt="${escapeHtmlInventario(item.nombre)}" class="producto-img" style="width:56px;height:56px;object-fit:contain;border-radius:8px;" onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"></td>
                                    <td><strong>${item.codigo}</strong></td>
                                    <td>${item.nombre}</td>
                                    <td>${item.categoria || 'Sin categoría'}</td>
                                    <td><strong>${item.cantidad_vendida}</strong><small class="resumen-acumulado">ACUM: ${acumProd.cantidad}</small></td>
                                    <td>${formatoMonedaCompleta(item.precio_unitario)}</td>
                                    <td>${formatoMonedaCompleta(item.ganancia_total ?? item.porcentaje_ganancia ?? 0)}<small class="resumen-acumulado">ACUM: ${formatoMonedaCompleta(acumProd.ganancia)}</small></td>
                                    <td><strong>${formatoMonedaCompleta(item.total_vendido)}</strong><small class="resumen-acumulado">ACUM: ${formatoMonedaCompleta(acumProd.total)}</small></td>
                                `;
                                productosVendidosMes.appendChild(row);
                            });

                            pintarResumenDia(fechaActual);

                        }
                    }
                })
                .catch(err => {
                    console.error('Error cargarVentasMes:', err);
                    if (mostrarError) {
                        Swal.fire({
                            icon: 'error',
                            title: 'ERROR',
                            text: err.message || 'No se pudo cargar el resumen de ventas del mes'
                        });
                    }
                });
        }

        async function inicializarSelectorMesVentas() {
            const selector = document.getElementById('selectorMesVentas');
            if (!selector) return;

            const ahora = new Date();
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            selector.innerHTML = '';

            let primerMes = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}`;

            try {
                const respuesta = await fetch(inventarioControllerUrl + '?action=obtenerPrimerMesVentas');
                const json = await respuesta.json();
                if (json && json.success && json.data && json.data.primer_mes) {
                    primerMes = String(json.data.primer_mes).slice(0, 7);
                }
            } catch (e) {
                console.warn('No se pudo obtener el primer mes de ventas, se usará el mes actual.');
            }

            const [anioInicio, mesInicio] = primerMes.split('-').map(v => parseInt(v, 10));
            const inicio = new Date(Number.isFinite(anioInicio) ? anioInicio : ahora.getFullYear(), Number.isFinite(mesInicio) ? (mesInicio - 1) : ahora.getMonth(), 1);

            let cursor = new Date(ahora.getFullYear(), ahora.getMonth(), 1);
            while (cursor >= inicio) {
                const yyyy = cursor.getFullYear();
                const mm = String(cursor.getMonth() + 1).padStart(2, '0');
                const value = `${yyyy}-${mm}`;
                const option = document.createElement('option');
                option.value = value;
                option.textContent = `${meses[cursor.getMonth()]} ${yyyy}`;
                if (cursor.getFullYear() === ahora.getFullYear() && cursor.getMonth() === ahora.getMonth()) {
                    option.selected = true;
                }
                selector.appendChild(option);

                cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
            }
        }

        // Mostrar modal de valor del inventario
        function mostrarModalValorInventario() {
            abrirModal('valorInventarioModal');
            cargarDetalleValor();
        }

        // Cargar desglose de valor del inventario
        function cargarDetalleValor() {
            fetch(inventarioControllerUrl + '?action=obtenerValorInventario')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const inventario = data.data;
                        const totalProductosDetalleEl = document.getElementById('totalProductosDetalle');
                        const valorTotalDetalleEl = document.getElementById('valorTotalDetalle');
                        const valorCompraDetalleEl = document.getElementById('valorCompraDetalle');
                        const valorGananciaDetalleEl = document.getElementById('valorGananciaDetalle');
                        const unidadesTotalesDetalleEl = document.getElementById('unidadesTotalesDetalle');
                        const tbody = document.getElementById('detalleValorBody');

                        if (!totalProductosDetalleEl || !valorTotalDetalleEl || !valorCompraDetalleEl || !valorGananciaDetalleEl || !unidadesTotalesDetalleEl || !tbody) {
                            return;
                        }
                        
                        // Actualizar encabezados
                        totalProductosDetalleEl.textContent = inventario.cantidad_productos || 0;
                        valorTotalDetalleEl.textContent = '$' + parseFloat(inventario.valor_total || 0).toLocaleString('es-CO', {maximumFractionDigits: 2});
                        valorCompraDetalleEl.textContent = '$' + parseFloat(inventario.valor_compra_total || 0).toLocaleString('es-CO', {maximumFractionDigits: 2});
                        valorGananciaDetalleEl.textContent = '$' + parseFloat(inventario.valor_ganancia_total || 0).toLocaleString('es-CO', {maximumFractionDigits: 2});

                        ajustarTamanoTextoStat('valorTotalDetalle');
                        ajustarTamanoTextoStat('valorCompraDetalle');
                        ajustarTamanoTextoStat('valorGananciaDetalle');
                        
                        // Calcular unidades totales
                        let totalUnidades = 0;
                        inventario.productos.forEach(p => {
                            totalUnidades += parseFloat(p.stock) || 0;
                        });
                        unidadesTotalesDetalleEl.textContent = totalUnidades;
                        
                        // Actualizar tabla
                        tbody.innerHTML = '';
                        
                        if (!inventario.productos || inventario.productos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No hay productos</td></tr>';
                            return;
                        }
                        
                        const productosOrdenados = [...inventario.productos].sort((a, b) => {
                            const valorA = parseFloat(a.valor_total) || 0;
                            const valorB = parseFloat(b.valor_total) || 0;
                            return valorB - valorA;
                        });

                        productosOrdenados.forEach(prod => {
                            const row = document.createElement('tr');
                            const imgSrc = resolverImagenProductoInventario(prod.imagen);
                            const precioUnit = parseFloat(prod.precio || 0).toLocaleString('es-CO', {maximumFractionDigits: 2});
                            const valorTotalProd = parseFloat(prod.valor_total || 0).toLocaleString('es-CO', {maximumFractionDigits: 2});
                            const porcentaje = ((parseFloat(prod.valor_total) / parseFloat(inventario.valor_total)) * 100).toFixed(1);
                            
                            row.innerHTML = `
                                <td style="text-align: center;">
                                    <div class="img-container">
                                        <img src="${imgSrc}" alt="${escapeHtmlInventario(prod.nombre)}" class="producto-img"
                                             onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"
                                             loading="lazy">
                                    </div>
                                </td>
                                <td><strong>${prod.codigo}</strong></td>
                                <td>${prod.nombre}</td>
                                <td>${prod.categoria || 'Sin categoría'}</td>
                                <td><span class="badge badge-info">${prod.stock}</span></td>
                                <td>$${precioUnit}</td>
                                <td><strong style="color: #2c3e50;">$${valorTotalProd}</strong></td>
                                <td><span class="badge badge-primary">${porcentaje}%</span></td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                })
                .catch(e => {
                    console.error('Error:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        text: 'No se pudo cargar el desglose del valor del inventario'
                    });
                });
        }

        // Mostrar modal de necesidad de reorden
        function mostrarModalReorden() {
            abrirModal('reordenModal');
            
            fetch(inventarioControllerUrl + '?action=obtenerResumen')
                .then(r => r.json())
                .then(data => {
                    console.log('=== DATOS REORDEN RECIBIDOS ===');
                    console.log('Data:', data);
                    
                    if (data.success && data.data) {
                        const productos = Array.isArray(data.data) ? data.data : [];
                        const productosBajoStock = productos.filter(item => {
                            const stock = Number(item?.stock);
                            const stockNormalizado = Number.isFinite(stock) ? stock : 0;
                            return stockNormalizado <= 5;
                        });
                        console.log('Productos recibidos:', productos.length);
                        console.log('Productos bajo stock (<=5):', productosBajoStock.length);
                        
                        // Contar por urgencia
                        let criticos = 0;
                        let urgentes = 0;
                        let normales = 0;
                        
                        productosBajoStock.forEach(item => {
                            const stock = Number(item?.stock);
                            const stockNormalizado = Number.isFinite(stock) ? stock : 0;
                            if (stockNormalizado <= 0) criticos++;
                            else if (stockNormalizado <= 3) urgentes++;
                            else normales++;
                        });
                        
                        // Actualizar contadores
                        const productosReordenCountEl = document.getElementById('productosReordenCount');
                        const productosCriticosEl = document.getElementById('productosCriticos');
                        const productosUrgentesEl = document.getElementById('productosUrgentes');
                        const productosNormalesEl = document.getElementById('productosNormales');
                        const tbody = document.getElementById('productosReordenBody');

                        if (!productosReordenCountEl || !productosCriticosEl || !productosUrgentesEl || !productosNormalesEl || !tbody) {
                            return;
                        }

                        productosReordenCountEl.textContent = productosBajoStock.length;
                        productosCriticosEl.textContent = criticos;
                        productosUrgentesEl.textContent = urgentes;
                        productosNormalesEl.textContent = normales;
                        
                        // Actualizar tabla
                        tbody.innerHTML = '';
                        
                        if (productosBajoStock.length === 0) {
                            console.log('No hay productos para mostrar');
                            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">- No hay productos con bajo stock</td></tr>';
                            return;
                        }
                        
                        const prioridadNivel = { CRITICO: 0, URGENTE: 1, NORMAL: 2 };
                        const productosOrdenados = [...productosBajoStock].sort((a, b) => {
                            const stockA = Number.isFinite(Number(a?.stock)) ? Number(a.stock) : 0;
                            const stockB = Number.isFinite(Number(b?.stock)) ? Number(b.stock) : 0;
                            const nivelA = stockA <= 0 ? 'CRITICO' : (stockA <= 3 ? 'URGENTE' : 'NORMAL');
                            const nivelB = stockB <= 0 ? 'CRITICO' : (stockB <= 3 ? 'URGENTE' : 'NORMAL');
                            const pA = prioridadNivel[nivelA] ?? 99;
                            const pB = prioridadNivel[nivelB] ?? 99;

                            if (pA !== pB) return pA - pB;
                            if (stockA !== stockB) return stockA - stockB;
                            return String(a?.nombre || '').localeCompare(String(b?.nombre || ''));
                        });

                        const grupos = {
                            CRITICO: [],
                            URGENTE: [],
                            NORMAL: []
                        };

                        productosOrdenados.forEach(item => {
                            const stock = Number.isFinite(Number(item?.stock)) ? Number(item.stock) : 0;
                            const nivel = stock <= 0 ? 'CRITICO' : (stock <= 3 ? 'URGENTE' : 'NORMAL');
                            grupos[nivel].push(item);
                        });

                        const renderGrupoHeader = (titulo, claseBadge, total) => {
                            const headerRow = document.createElement('tr');
                            headerRow.innerHTML = `<td colspan="6" style="text-align:left; font-weight:700; padding:10px 12px; background:#f8f9fb;"><span class="badge ${claseBadge}" style="margin-right:8px;">${titulo}</span>${total} producto(s)</td>`;
                            tbody.appendChild(headerRow);
                        };

                        const renderProducto = (item, nivel) => {
                            console.log('Procesando producto:', item);
                            const row = document.createElement('tr');
                            const stock = Number(item?.stock);
                            const stockNormalizado = Number.isFinite(stock) ? stock : 0;
                            const badgeNivel = nivel === 'CRITICO' ? 'badge-danger' : (nivel === 'URGENTE' ? 'badge-warning' : 'badge-info');
                            const badgeStock = nivel === 'CRITICO' ? 'badge-danger' : (nivel === 'URGENTE' ? 'badge-warning' : 'badge-info');
                            const iconoNivel = nivel === 'CRITICO' ? '●' : (nivel === 'URGENTE' ? '▲' : '◆');
                            
                            // Preparar imagen del producto
                            const imgSrc = resolverImagenProductoInventario(item.imagen);
                            
                            row.innerHTML = `
                                <td style="text-align: center;">
                                    <div class="img-container">
                                        <img src="${imgSrc}" alt="${escapeHtmlInventario(item.nombre)}" class="producto-img" 
                                             onerror="this.onerror=null;this.src=base_url+'/favicon.ico'" 
                                             loading="lazy">
                                    </div>
                                </td>
                                <td><strong>${item.codigo || 'N/A'}</strong></td>
                                <td>${item.nombre || 'N/A'}</td>
                                <td>${item.categoria || 'SIN CATEGORIA'}</td>
                                <td><span class="badge ${badgeStock}" style="font-size: 16px; padding: 8px 12px;">${stockNormalizado}</span></td>
                                <td><span class="badge ${badgeNivel}">${iconoNivel} ${nivel}</span></td>
                            `;
                            tbody.appendChild(row);
                        };

                        if (grupos.CRITICO.length > 0) {
                            renderGrupoHeader('CRITICOS', 'badge-danger', grupos.CRITICO.length);
                            grupos.CRITICO.forEach(item => renderProducto(item, 'CRITICO'));
                        }
                        if (grupos.URGENTE.length > 0) {
                            renderGrupoHeader('URGENTES', 'badge-warning', grupos.URGENTE.length);
                            grupos.URGENTE.forEach(item => renderProducto(item, 'URGENTE'));
                        }
                        if (grupos.NORMAL.length > 0) {
                            renderGrupoHeader('NORMALES', 'badge-info', grupos.NORMAL.length);
                            grupos.NORMAL.forEach(item => renderProducto(item, 'NORMAL'));
                        }
                    } else {
                        console.error('Error en respuesta:', data);
                        Swal.fire({
                            icon: 'error',
                            title: 'ERROR',
                            text: data.message || 'No se pudo cargar la necesidad de reorden'
                        });
                    }
                })
                .catch(e => {
                    console.error('Error en fetch:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        text: 'No se pudo cargar la necesidad de reorden: ' + e.message
                    });
                });
        }

        const STOCK_ALERT_SESSION_KEY = 'stockUrgenteAlertaSession';

        function obtenerResumenStockBajo() {
            return fetch(inventarioControllerUrl + '?action=obtenerResumen')
                .then(r => r.json())
                .catch(error => {
                    console.error('Error obteniendo resumen para alerta de stock:', error);
                    return null;
                });
        }

        function evaluarStockCriticoUrgente(datos) {
            const productos = Array.isArray(datos?.data) ? datos.data : [];
            let criticos = 0;
            let urgentes = 0;
            const grupos = {};

            productos.forEach(item => {
                const stock = Number(item?.stock);
                const stockNormalizado = Number.isFinite(stock) ? stock : 0;
                if (stockNormalizado <= 0) {
                    criticos += 1;
                } else if (stockNormalizado <= 3) {
                    urgentes += 1;
                } else {
                    return;
                }

                const categoria = String(item?.categoria || 'SIN CATEGORÍA').toUpperCase();
                grupos[categoria] = grupos[categoria] || [];
                grupos[categoria].push({
                    nombre: String(item?.nombre || item?.producto_nombre || 'SIN NOMBRE').toUpperCase(),
                    imagen: resolverImagenProductoInventario(item?.imagen || item?.producto_imagen || ''),
                    stock: stockNormalizado,
                    codigo: String(item?.codigo || '').toUpperCase(),
                    nivel: stockNormalizado <= 0 ? 'CRÍTICO' : 'URGENTE'
                });
            });

            return { criticos, urgentes, grupos };
        }

        function obtenerStockAlertSession() {
            try {
                const raw = sessionStorage.getItem(STOCK_ALERT_SESSION_KEY);
                return raw ? JSON.parse(raw) : {};
            } catch (e) {
                return {};
            }
        }

        function guardarStockAlertSession(data) {
            try {
                sessionStorage.setItem(STOCK_ALERT_SESSION_KEY, JSON.stringify(data || {}));
            } catch (e) {
                console.error('No se pudo guardar el estado de la alerta de stock en sessionStorage', e);
            }
        }

        function debeMostrarAlertaStock(clave) {
            const data = obtenerStockAlertSession();
            const hoy = new Date().toISOString().slice(0, 10);
            return data[clave] !== hoy;
        }

        function marcarAlertaStockMostrada(clave) {
            const data = obtenerStockAlertSession();
            data[clave] = new Date().toISOString().slice(0, 10);
            guardarStockAlertSession(data);
        }

        function mostrarAlertaStockUrgenteCritico(clave = 'start') {
            if (!debeMostrarAlertaStock(clave)) return;

            obtenerResumenStockBajo().then(datos => {
                if (!datos || !datos.success) return;
                const { criticos, urgentes, grupos } = evaluarStockCriticoUrgente(datos);
                if (criticos === 0 && urgentes === 0) return;

                const secciones = Object.keys(grupos).sort();
                const contenido = secciones.map(categoria => {
                    const productos = grupos[categoria];
                    const filas = productos.map(prod => `
                        <tr style="border-bottom:1px solid #e5e7eb;">
                            <td style="padding:8px 6px; width: 56px; text-align:center;"><img src="${prod.imagen}" alt="${escapeHtml(prod.nombre)}" style="width:48px;height:48px;object-fit:contain;border-radius:8px;border:1px solid #d1d5db;"></td>
                            <td style="padding:8px 6px;vertical-align:middle;">
                                <div style="font-weight:700;color:#111827;">${escapeHtml(prod.nombre)}</div>
                                <div style="font-size:12px;color:#475569;">${escapeHtml(prod.codigo)}</div>
                            </td>
                            <td style="padding:8px 6px;vertical-align:middle;text-align:right;">
                                <span style="display:inline-flex;align-items:center;gap:6px;font-weight:700;color:${prod.nivel === 'CRÍTICO' ? '#b91c1c' : '#b45309'};}">
                                    <span>${prod.nivel}</span>
                                    <span style="background:${prod.nivel === 'CRÍTICO' ? '#fee2e2' : '#fef3c7'};color:${prod.nivel === 'CRÍTICO' ? '#991b1b' : '#92400e'};padding:4px 8px;border-radius:9999px;font-size:12px;">Stock ${prod.stock}</span>
                                </span>
                            </td>
                        </tr>`).join('');

                    return `
                        <div style="margin-bottom: 18px;">
                            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">${escapeHtml(categoria)}</div>
                            <table style="width:100%;border-collapse:collapse;">
                                <tbody>${filas}</tbody>
                            </table>
                        </div>`;
                }).join('');

                Swal.fire({
                    icon: 'warning',
                    title: 'Stock crítico / urgente detectado',
                    html: `
                        <div style="text-align:left; font-size:14px; color:#0f172a; line-height:1.5;">
                            <p>Se encontraron <strong>${criticos + urgentes}</strong> producto(s) con stock bajo.</p>
                            <p style="margin-bottom:12px;">Revisa los siguientes productos por categoría:</p>
                            ${contenido}
                        </div>
                    `,
                    width: '720px',
                    confirmButtonText: 'OK',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    customClass: {
                        popup: 'stock-alert-popup',
                        title: 'stock-alert-title',
                        content: 'stock-alert-content',
                        confirmButton: 'stock-alert-button'
                    }
                });

                marcarAlertaStockMostrada(clave);
            });
        }

        function programarAlertasStock() {
            const ahora = new Date();
            const horarios = [{ hora: 11, minuto: 30 }, { hora: 16, minuto: 30 }];
            let siguienteAlerta = null;
            let claveSiguiente = null;

            horarios.forEach(horario => {
                const proximo = new Date(ahora);
                proximo.setHours(horario.hora, horario.minuto, 0, 0);
                if (proximo <= ahora) {
                    proximo.setDate(proximo.getDate() + 1);
                }
                if (!siguienteAlerta || proximo < siguienteAlerta) {
                    siguienteAlerta = proximo;
                    claveSiguiente = `${horario.hora}-${horario.minuto}`;
                }
            });

            if (!siguienteAlerta) return;
            const retraso = siguienteAlerta.getTime() - ahora.getTime();

            setTimeout(() => {
                mostrarAlertaStockUrgenteCritico(claveSiguiente);
                programarAlertasStock();
            }, retraso);
        }

        function normalizarTextoBusquedaInventario(valor) {
            return String(valor || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function coincideBusquedaInventario(valor, consulta) {
            const texto = normalizarTextoBusquedaInventario(valor);
            const busqueda = normalizarTextoBusquedaInventario(consulta);
            if (!busqueda) return true;
            const tokens = busqueda.split(' ').filter(Boolean);
            return tokens.every(token => texto.includes(token)) || texto.replace(/\s/g, '').includes(tokens.join(''));
        }

            function esCategoriaGramosInventario(nombre) {
                return ['frutas', 'verduras', 'carnicos y refrigerados'].includes(normalizarTextoBusquedaInventario(nombre));
            }

            function normalizarCodigoBarrasInventario(valor) {
                return String(valor || '').replace(/\D/g, '');
            }

        function inicializarBusquedaSelect(selectId, inputId, resultsId) {
            const select = document.getElementById(selectId);
            const input = document.getElementById(inputId);
            const results = document.getElementById(resultsId);
            if (!select || !input || !results) return;
            if (select.dataset.busquedaInicializada === '1') return;
            select.dataset.busquedaInicializada = '1';

            if (['productoSalida', 'productoEntrada', 'proveedorEntrada'].includes(selectId)) {
                input.readOnly = true;
                input.dataset.selectorActivo = '0';
            }

            const renderResults = () => {
                const texto = normalizarTextoBusquedaInventario(input.value || '');
                const opciones = Array.from(select.options).filter((opt) => {
                    if (opt.value === '') return false;
                    if (selectId === 'productoSalida' && (parseFloat(opt.getAttribute('data-stock') || '0') || 0) <= 0) return false;
                    if (selectId === 'productoDanado' && opt.dataset.grupo !== (select.dataset.grupo || 'perecederos')) return false;
                    return true;
                });
                const categoriaFiltro = select.dataset.categoriaFiltro || '';

                const coincidencias = opciones.filter((option) => {
                    const nombre = [
                        option.textContent || '',
                        option.dataset.codigo || '',
                        option.dataset.barcode || '',
                        option.dataset.categoriaNombre || ''
                    ].join(' ');
                    const categoriaNombre = normalizarTextoBusquedaInventario(option.dataset.categoriaNombre || '');
                    const perteneceCategoria = !categoriaFiltro
                        || perteneceGrupoCategoriaSalida(categoriaNombre, categoriaFiltro);
                    const coincideBusqueda = coincideBusquedaInventario(nombre, texto);
                    return perteneceCategoria && coincideBusqueda;
                });

                results.innerHTML = coincidencias.map((option) => `
                    <button type="button" class="inventario-search-option" data-select-id="${selectId}" data-id="${option.value}" data-name="${(option.textContent || '').trim()}" data-imagen="${option.getAttribute('data-imagen') || ''}" data-codigo="${option.getAttribute('data-codigo') || ''}" data-barcode="${option.getAttribute('data-barcode') || ''}" data-stock="${option.getAttribute('data-stock') || '0'}" data-venta-por-kilo="${option.getAttribute('data-venta-por-kilo') || '0'}" data-requiere-vencimiento="${option.getAttribute('data-requiere-vencimiento') || '0'}" data-categoria-nombre="${option.getAttribute('data-categoria-nombre') || ''}" style="display:block; width:100%; text-align:left; border:none; background:#fff; padding:10px 12px; font-size:14px; color:#1f2937; cursor:pointer; border-bottom:1px solid #f1f5f9;">
                        ${option.textContent || ''}
                    </button>
                `).join('');

                results.style.display = coincidencias.length > 0 && (input.value || '').trim() ? 'block' : 'none';
            };

            const syncFromSelect = () => {
                const selectedOption = select.options[select.selectedIndex];
                const selectedText = selectedOption ? (selectedOption.textContent || '').trim() : '';
                input.value = selectedText;
                renderResults();
            };

            input.addEventListener('input', () => {
                renderResults();
                const codigo = normalizarCodigoBarrasInventario(input.value);
                if (!/^\d{8,14}$/.test(codigo)) return;
                const opcionCodigo = Array.from(select.options).find((option) =>
                    (selectId !== 'productoDanado' || option.dataset.grupo === (select.dataset.grupo || 'perecederos')) &&
                    normalizarCodigoBarrasInventario(option.getAttribute('data-barcode')) === codigo
                );
                if (opcionCodigo && input.dataset.lectorPreparado !== '1') {
                    seleccionarProductoDesdeLector(select, input, results, opcionCodigo);
                }
            });
            input.addEventListener('focus', () => {
                renderResults();
                if (!['productoSalida', 'productoEntrada', 'proveedorEntrada'].includes(selectId) || input.dataset.selectorActivo === '1') {
                    results.style.display = 'block';
                }
            });
            input.addEventListener('keydown', (event) => {
                if (input.dataset.lectorPreparado === '1' && /^\d$/.test(event.key)) {
                    input.value = '';
                    input.dataset.lectorPreparado = '0';
                }
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const codigoEscaneado = normalizarCodigoBarrasInventario(input.value);
                const opcionSeleccionada = select.options[select.selectedIndex];
                const codigoSeleccionado = normalizarCodigoBarrasInventario(opcionSeleccionada?.getAttribute('data-barcode'));
                if (input.dataset.lectorPreparado === '1') {
                    input.value = (opcionSeleccionada?.textContent || '').trim();
                    results.style.display = 'none';
                    return;
                }
                if (codigoEscaneado && codigoEscaneado === codigoSeleccionado) {
                    input.value = (opcionSeleccionada.textContent || '').trim();
                    input.dataset.lectorPreparado = '1';
                    results.style.display = 'none';
                    return;
                }
                const coincidenciaCodigo = Array.from(select.options).find((option) =>
                    (selectId !== 'productoDanado' || option.dataset.grupo === (select.dataset.grupo || 'perecederos')) &&
                    normalizarCodigoBarrasInventario(option.getAttribute('data-barcode')) === codigoEscaneado
                );
                const primeraVisible = results.querySelector('.inventario-search-option');
                const opcion = coincidenciaCodigo || (primeraVisible ? select.querySelector(`option[value="${primeraVisible.dataset.id}"]`) : null);
                if (opcion) {
                    seleccionarProductoDesdeLector(select, input, results, opcion);
                } else if (selectId === 'productoSalida' && /^\d{8,14}$/.test(codigoEscaneado)) {
                    seleccionarSalidaEscaneadaActualizada(codigoEscaneado, select, input, results, null);
                } else if (/^\d{8,14}$/.test(codigoEscaneado)) {
                    input.value = '';
                    input.dataset.lectorPreparado = '0';
                    results.style.display = 'none';
                    if (window.Swal) {
                        const productoEnInventario = selectId === 'productoSalida'
                            ? Array.from(document.getElementById('productoEntrada')?.options || []).find((option) =>
                                normalizarCodigoBarrasInventario(option.getAttribute('data-barcode')) === codigoEscaneado
                            )
                            : null;
                        const stockProducto = productoEnInventario
                            ? parseFloat(productoEnInventario.getAttribute('data-stock') || '0') || 0
                            : null;
                        Swal.fire({
                            icon: 'warning',
                            title: productoEnInventario ? 'Producto sin stock' : 'Producto no registrado',
                            text: productoEnInventario
                                ? `El producto está en stock ${stockProducto}. Registre la entrada en inventario para poder venderlo.`
                                : `El código ${codigoEscaneado} no está registrado en el sistema. Registre primero el producto.`
                        });
                    }
                }
            });
            if (input.dataset.lectorCodigoActivo !== '1') {
                input.dataset.lectorCodigoActivo = '1';
            }
            input.addEventListener('blur', () => setTimeout(() => results.style.display = 'none', 300));
            if (input.parentElement) {
                const activarSelector = (event) => {
                    if (event) event.stopPropagation();
                    if (selectId === 'productoSalida' && input.dataset.lectorPreparado === '1') {
                        limpiarSeleccionSalidaUI();
                        input.dataset.lectorPreparado = '0';
                    }
                    if (['productoSalida', 'productoEntrada', 'proveedorEntrada'].includes(selectId)) {
                        input.readOnly = false;
                        input.dataset.selectorActivo = '1';
                    }
                    input.focus();
                    renderResults();
                    if (['productoSalida', 'productoEntrada', 'proveedorEntrada'].includes(selectId)) results.style.display = 'block';
                };
                input.addEventListener('mousedown', activarSelector);
                input.addEventListener('click', activarSelector);
            }
            const seleccionarResultado = (event) => {
                if (event.__resultadoInventarioProcesado) return;
                const option = event.target.closest('.inventario-search-option, .producto-search-option, .proveedor-search-option');
                if (!option) return;
                event.__resultadoInventarioProcesado = true;
                event.preventDefault();
                const selectedValue = option.dataset.id || '';
                const selectedText = option.dataset.name || '';
                select.value = selectedValue;
                input.value = selectedText;
                input.dataset.selectedValue = selectedValue;
                if (selectId === 'productoSalida' || selectId === 'productoEntrada') {
                    seleccionarProductoDesdeLector(select, input, results, option);
                } else {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    results.style.display = 'none';
                }
            };
            results.addEventListener('mousedown', seleccionarResultado, true);
            results.addEventListener('click', (event) => {
                if (event.defaultPrevented) return;
                seleccionarResultado(event);
            });

            if (select.value) {
                syncFromSelect();
            }
        }

        function seleccionarProductoDesdeLector(select, input, results, option) {
            const productoId = option.value || option.dataset.id || '';
            select.value = productoId;
            input.value = (option.textContent || '').trim();
            input.dataset.lectorPreparado = '1';
            input.dataset.selectedValue = productoId;
            input.dataset.seleccionProductoEnCurso = '1';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.dataset.seleccionProductoEnCurso = '0';
            results.style.display = 'none';

            if (select.id === 'productoSalida') {
                const cantidad = document.getElementById('cantidadSalida');
                if (cantidad) {
                    const esPorKilo = esProductoPorKilosSalida(option);
                    cantidad.min = esPorKilo ? '0.001' : '1';
                    cantidad.step = esPorKilo ? '0.001' : '1';
                    cantidad.value = esPorKilo ? '0.001' : '1';
                }
                programarAgregarSalidaSeleccionada(select, input);
                input.focus();
            } else {
                const porVentaPorKilo = ['1', 'true', 'si', 'sí'].includes(String(option.dataset.ventaPorKilo || '0').trim().toLowerCase());
                const porCategoria = esCategoriaGramosInventario(option.dataset.categoriaNombre || '');
                const esPorKilo = porVentaPorKilo || porCategoria;
                const cantidad = document.getElementById('cantidadEntrada');
                const etiqueta = document.getElementById('unidadEntradaLabel');
                if (etiqueta) etiqueta.textContent = esPorKilo ? 'KILOS' : 'CANTIDAD';
                if (cantidad) {
                    cantidad.min = esPorKilo ? '0.001' : '1';
                    cantidad.step = esPorKilo ? '0.001' : '1';
                    cantidad.value = esPorKilo ? '0.001' : '1';
                }
                input.focus();
            }
        }

        let temporizadorAgregarSalidaAutomatico = null;

        function programarAgregarSalidaSeleccionada(select, input) {
            if (!select || select.id !== 'productoSalida' || !input) return;
            if (temporizadorAgregarSalidaAutomatico) {
                clearTimeout(temporizadorAgregarSalidaAutomatico);
                temporizadorAgregarSalidaAutomatico = null;
            }
            const productoIdSeleccionado = String(select.value || '');
            if (!productoIdSeleccionado) return;
            input.dataset.agregandoAutomatico = '1';
            const inicio = Date.now();
            const esperarPresentacion = () => {
                if (String(select.value || '') !== productoIdSeleccionado) {
                    input.dataset.agregandoAutomatico = '0';
                    temporizadorAgregarSalidaAutomatico = null;
                    return;
                }
                const grupoPresentacion = document.getElementById('grupoPresentacionSalida');
                const presentacion = document.getElementById('presentacionSalida');
                const esperandoPresentacion = grupoPresentacion && grupoPresentacion.style.display !== 'none' && presentacion && !presentacion.value;
                if (esperandoPresentacion && Date.now() - inicio < 4000) {
                    temporizadorAgregarSalidaAutomatico = setTimeout(esperarPresentacion, 50);
                    return;
                }
                try {
                    if (String(select.value || '') === productoIdSeleccionado) agregarProductoSalida();
                } finally {
                    input.dataset.agregandoAutomatico = '0';
                    temporizadorAgregarSalidaAutomatico = null;
                }
            };
            temporizadorAgregarSalidaAutomatico = setTimeout(esperarPresentacion, 120);
        }

        function actualizarUnidadEntrada() {
            const select = document.getElementById('productoEntrada');
            const option = select?.options[select.selectedIndex];
            const cantidad = document.getElementById('cantidadEntrada');
            const etiqueta = document.getElementById('unidadEntradaLabel');
            const porVentaPorKilo = ['1', 'true', 'si', 'sí'].includes(String(option?.dataset.ventaPorKilo || '0').trim().toLowerCase());
            const porCategoria = esCategoriaGramosInventario(option?.dataset.categoriaNombre || '');
            const esPorKilo = porVentaPorKilo || porCategoria;
            if (etiqueta) etiqueta.textContent = esPorKilo ? 'KILOS' : 'CANTIDAD';
            if (cantidad) {
                cantidad.min = esPorKilo ? '0.001' : '1';
                cantidad.step = esPorKilo ? '0.001' : '1';
                cantidad.value = esPorKilo ? '0.001' : '1';
            }
        }

        function configurarLectorEntradaGlobal() {
            if (document.body.dataset.lectorEntradaActivo === '1') return;
            document.body.dataset.lectorEntradaActivo = '1';

            let codigoEnLectura = '';
            let ultimaTecla = 0;

            document.addEventListener('keydown', (event) => {
                const pestañaEntradas = document.getElementById('entradas');
                if (!pestañaEntradas || !pestañaEntradas.classList.contains('active')) return;

                const ahora = Date.now();
                if (ahora - ultimaTecla > 120) codigoEnLectura = '';
                ultimaTecla = ahora;

                if (event.key === 'Enter') {
                    const codigo = codigoEnLectura.trim();
                    codigoEnLectura = '';
                    if (!/^\d{8,14}$/.test(codigo)) return;

                    event.preventDefault();
                    abrirModalEntrada();

                    setTimeout(() => {
                        const select = document.getElementById('productoEntrada');
                        const input = document.getElementById('buscarProductoEntrada');
                        const results = document.getElementById('productoEntradaSearchResults');
                        if (!select || !input || !results) return;

                        const option = Array.from(select.options).find((item) =>
                            normalizarCodigoBarrasInventario(item.getAttribute('data-barcode')) === codigo
                        );

                        if (!option) {
                            input.value = '';
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Producto no encontrado',
                                    text: `No existe un producto registrado con el código ${codigo}.`
                                });
                            }
                            return;
                        }

                        seleccionarProductoDesdeLector(select, input, results, option);
                    }, 100);
                    return;
                }

                if (event.key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) {
                    codigoEnLectura += event.key;
                    if (codigoEnLectura.length > 14) codigoEnLectura = codigoEnLectura.slice(-14);
                }
            });
        }

        function configurarLectorSalidaGlobal() {
            if (document.body.dataset.lectorSalidaActivo === '1') return;
            document.body.dataset.lectorSalidaActivo = '1';

            let codigoEnLectura = '';
            let ultimaTecla = 0;

            document.addEventListener('keydown', (event) => {
                const pestañaSalidas = document.getElementById('salidas');
                if (!pestañaSalidas || !pestañaSalidas.classList.contains('active')) return;
                if (document.getElementById('productoDanadoModal')?.classList.contains('active')) {
                    codigoEnLectura = '';
                    ultimaTecla = 0;
                    return;
                }
                const modalSalidaActivo = document.getElementById('salidaModal')?.classList.contains('active');

                const ahora = Date.now();
                if (ahora - ultimaTecla > 120) codigoEnLectura = '';
                ultimaTecla = ahora;

                if (event.key === 'Enter') {
                    const codigo = codigoEnLectura.trim();
                    codigoEnLectura = '';
                    if (!/^\d{8,14}$/.test(codigo)) return;

                    event.preventDefault();
                    if (modalSalidaActivo) {
                        const select = document.getElementById('productoSalida');
                        const input = document.getElementById('buscarProductoSalida');
                        const results = document.getElementById('productoSalidaSearchResults');
                        const option = Array.from(select?.options || []).find((item) =>
                            normalizarCodigoBarrasInventario(item.getAttribute('data-barcode')) === codigo
                        );
                        if (select && input && results) {
                            seleccionarSalidaEscaneadaActualizada(codigo, select, input, results, option || null);
                        }
                        return;
                    }
                    abrirModal('salidaModal');

                    setTimeout(() => {
                        const select = document.getElementById('productoSalida');
                        const input = document.getElementById('buscarProductoSalida');
                        const results = document.getElementById('productoSalidaSearchResults');
                        if (!select || !input || !results) return;

                        const option = Array.from(select.options).find((item) =>
                            normalizarCodigoBarrasInventario(item.getAttribute('data-barcode')) === codigo
                        );

                        seleccionarSalidaEscaneadaActualizada(codigo, select, input, results, option || null);
                    }, 100);
                    return;
                }

                if (event.key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) {
                    codigoEnLectura += event.key;
                    if (codigoEnLectura.length > 14) codigoEnLectura = codigoEnLectura.slice(-14);
                }
            });
        }

        async function seleccionarSalidaEscaneadaActualizada(codigo, select, input, results, optionInicial = null) {
            try {
                const data = await obtenerProductosConGanancia();
                const productoActual = (Array.isArray(data?.data) ? data.data : []).find(item =>
                    normalizarCodigoBarrasInventario(item.codigo_barras) === codigo
                );
                if (productoActual) {
                    const stockActual = Math.max(0, Number(productoActual.stock) || 0);
                    let option = Array.from(select.options).find(item =>
                        String(item.value) === String(productoActual.id)
                    ) || optionInicial;
                    if (!option && stockActual > 0) {
                        option = document.createElement('option');
                        option.value = productoActual.id;
                        option.textContent = `${productoActual.nombre || 'PRODUCTO'}${productoActual.codigo_producto ? ` [${productoActual.codigo_producto}]` : ''}`.toUpperCase();
                        option.setAttribute('data-codigo', productoActual.codigo_producto || productoActual.codigo || '');
                        option.setAttribute('data-barcode', productoActual.codigo_barras || codigo);
                        option.setAttribute('data-venta-por-kilo', Number(productoActual.venta_por_kilo) === 1 ? '1' : '0');
                        select.appendChild(option);
                    }
                    if (stockActual <= 0) {
                        limpiarSeleccionSalidaUI();
                        results.style.display = 'none';
                        await aseguraryMostrarSwalEstilizado(`El producto ${productoActual.nombre || 'escaneado'} está en stock 0. Registre una entrada para poder venderlo.`);
                        return;
                    }
                    if (option) {
                        option.setAttribute('data-stock', String(stockActual));
                        option.setAttribute('data-venta-por-kilo', Number(productoActual.venta_por_kilo) === 1 ? '1' : '0');
                        seleccionarProductoDesdeLector(select, input, results, option);
                        return;
                    }
                }
            } catch (error) {}
            if (optionInicial) {
                seleccionarProductoDesdeLector(select, input, results, optionInicial);
                return;
            }
            input.value = '';
            results.style.display = 'none';
            if (window.Swal) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'PRODUCTO NO REGISTRADO',
                    text: `EL CÓDIGO ${codigo} NO ESTÁ REGISTRADO EN EL SISTEMA. AGRÉGALO AL INVENTARIO ANTES DE VENDERLO.`,
                    confirmButtonText: 'ENTENDIDO'
                });
            }
        }

        // Cargar datos del listado completo de productos (uso compartido)
        function actualizarSelectProductosInventario() {
            obtenerProductosConGanancia()
                .then(data => {
                    const productos = Array.isArray(data?.data) ? data.data : [];
                    const entradaSelect = document.getElementById('productoEntrada');
                    const salidaSelect = document.getElementById('productoSalida');

                    if (!entradaSelect && !salidaSelect) return;

                    const construirOption = (item, tipo) => {
                        const id = item.id ?? '';
                        const codigo = String(item.codigo_producto || item.codigo || '').trim();
                        const nombre = String(item.nombre || 'PRODUCTO').trim();
                        const imagen = String(item.imagen || '');
                        const precioBase = Number(item.precio ?? item.precio_venta ?? item.precio_original ?? 0) || 0;
                        const descuento = Number(item.descuento_porcentaje ?? 0) || 0;
                        const precioFinal = Number(item.precio_venta ?? 0) || 0;
                        const stock = Number(item.stock ?? 0) || 0;
                        const stockDisponible = Math.max(0, stock);
                        const codigoLabel = codigo ? ` [${codigo}]` : '';

                        const option = document.createElement('option');
                        option.value = id;
                        option.setAttribute('data-imagen', imagen);
                        option.setAttribute('data-codigo', codigo);
                        option.setAttribute('data-barcode', String(item.codigo_barras || '').trim());
                        option.setAttribute('data-categoria-nombre', String(item.categoria_nombre || 'SIN CATEGORÍA').trim());
                        option.setAttribute('data-requiere-vencimiento', Number(item.requiere_vencimiento) === 1 ? '1' : '0');
                        option.setAttribute('data-venta-por-kilo', Number(item.venta_por_kilo) === 1 ? '1' : '0');
                        option.setAttribute('data-precio', String(precioBase));
                        option.setAttribute('data-descuento', String(descuento));
                        option.setAttribute('data-precio-final', String(precioFinal));
                        option.setAttribute('data-stock', String(stockDisponible));
                        option.textContent = `${nombre.toUpperCase()}${codigoLabel}`;

                        if (tipo === 'salida' && stockDisponible <= 0) {
                            option.disabled = true;
                            option.textContent = `${nombre.toUpperCase()}${codigoLabel} [SIN STOCK]`;
                        }

                        return option;
                    };

                    if (entradaSelect) {
                        const selectedValue = entradaSelect.value;
                        entradaSelect.innerHTML = '<option value="" data-imagen="" data-codigo="">-- SELECCIONAR PRODUCTO --</option>';
                        productos.forEach(item => {
                            entradaSelect.appendChild(construirOption(item, 'entrada'));
                        });
                        if (selectedValue && [...entradaSelect.options].some(opt => opt.value === selectedValue)) {
                            entradaSelect.value = selectedValue;
                        }
                    }

                    if (salidaSelect) {
                        const selectedValue = salidaSelect.value;
                        salidaSelect.innerHTML = '<option value="" data-imagen="" data-codigo="">-- SELECCIONAR PRODUCTO --</option>';
                        productos
                            .filter(item => Number(item.stock ?? 0) > 0)
                            .forEach(item => {
                                salidaSelect.appendChild(construirOption(item, 'salida'));
                            });
                        if (selectedValue && [...salidaSelect.options].some(opt => opt.value === selectedValue)) {
                            salidaSelect.value = selectedValue;
                        }
                    }

                    try {
                        const event = new Event('change');
                        if (entradaSelect && entradaSelect.value) entradaSelect.dispatchEvent(event);
                        if (salidaSelect && salidaSelect.value) salidaSelect.dispatchEvent(event);
                    } catch (e) {}

                    const entradaInput = document.getElementById('buscarProductoEntrada');
                    const salidaInput = document.getElementById('buscarProductoSalida');
                    if (entradaInput) entradaInput.value = entradaSelect?.selectedOptions?.[0]?.textContent || entradaInput.value || '';
                    if (salidaInput) salidaInput.value = salidaSelect?.selectedOptions?.[0]?.textContent || salidaInput.value || '';
                    if (entradaSelect) inicializarBusquedaSelect('productoEntrada', 'buscarProductoEntrada', 'productoEntradaSearchResults');
                    if (salidaSelect) inicializarBusquedaSelect('productoSalida', 'buscarProductoSalida', 'productoSalidaSearchResults');
                })
                .catch(() => {
                    console.warn('No se pudo actualizar select de productos del inventario');
                });
        }

        function cargarTodosProductos() {
            obtenerProductosConGanancia()
                .then(data => {
                    if (data.success && data.data) {
                        const productos = data.data;
                        window.todosProductosModalCache = productos;
                        renderResultadosTodosProductosModal();
                        
                        // Calcular estadísticas
                        let stockTotal = 0;
                        let valorTotal = 0;
                        const categorias = new Set();
                        
                        productos.forEach(item => {
                            let stock = Number(item.stock);
                            if (isNaN(stock)) stock = 0;
                            const precioVenta = stock <= 0 ? 0 : parseFloat(item.precio_venta) || 0;
                            stockTotal += stock;
                            valorTotal += stock * precioVenta;
                            if (item.categoria_nombre) {
                                categorias.add(item.categoria_nombre);
                            }
                        });
                        
                        // Actualizar contadores del modal
                        const modalTotalProductosEl = document.getElementById('modalTotalProductos');
                        const modalStockTotalEl = document.getElementById('modalStockTotal');
                        const modalValorTotalEl = document.getElementById('modalValorTotal');
                        const modalTotalCategoriasEl = document.getElementById('modalTotalCategorias');
                        const tbody = document.getElementById('todosProductosBody');

                        if (!modalTotalProductosEl || !modalStockTotalEl || !modalValorTotalEl || !modalTotalCategoriasEl || !tbody) {
                            return;
                        }

                        modalTotalProductosEl.textContent = productos.length;
                        modalStockTotalEl.textContent = formatoStockTotalVisible(stockTotal);
                        modalValorTotalEl.textContent = '$' + valorTotal.toLocaleString('es-CO', {maximumFractionDigits: 2});
                        modalTotalCategoriasEl.textContent = categorias.size;
                        
                        // Actualizar tabla
                        tbody.innerHTML = '';
                        
                        if (productos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 20px;">No hay productos registrados</td></tr>';
                            return;
                        }
                        
                        productos.forEach(item => {
                            const row = document.createElement('tr');
                            const imgSrc = resolverImagenProductoInventario(item.imagen);
                            const stock = parseFloat(item.stock || 0) || 0;
                            const precioCompra = stock <= 0 ? 0 : (parseFloat(item.ultimo_precio_compra || item.precio_compra_promedio || 0) || 0);
                            const descuentoPct = stock <= 0 ? 0 : (parseFloat(item.descuento_porcentaje || 0) || 0);
                            const precioOriginal = stock <= 0 ? 0 : (parseFloat(item.precio_original || item.precio || item.precio_venta || 0) || 0);
                            const precioVenta = stock <= 0 ? 0 : (parseFloat(item.precio_venta || item.precio_final || 0) || calcularPrecioFinalConDescuento(precioOriginal, descuentoPct, stock));
                            const gananciaUnitaria = stock <= 0 ? 0 : (!isNaN(parseFloat(item.ganancia_unitaria)) ? parseFloat(item.ganancia_unitaria) : (precioVenta - precioCompra));
                            const porcentajeGanancia = stock <= 0 ? 0 : calcularPorcentajeGananciaInventario(precioVenta, precioCompra, item.porcentaje_ganancia);
                            const valorProducto = stock <= 0 ? 0 : stock * precioVenta;

                            let stockBadge = 'badge-success';
                            if (item.stock === 0 || item.stock === '0') {
                                stockBadge = 'badge-danger';
                            } else if (parseFloat(item.stock) < 5) {
                                stockBadge = 'badge-warning';
                            }

                            let gananciaBadge = 'badge-success';
                            if (porcentajeGanancia < 10) {
                                gananciaBadge = 'badge-danger';
                            } else if (porcentajeGanancia < 25) {
                                gananciaBadge = 'badge-warning';
                            }

                            const estadoBadge = item.estado == 1 ? 'badge-success' : 'badge-danger';
                            const estadoTexto = item.estado == 1 ? 'ACTIVO' : 'INACTIVO';
                            const nombreSeguro = escapeHtmlInventario(item.nombre || 'Producto');
                            const descripcionSeguro = escapeHtmlInventario(item.descripcion || 'Sin descripción');

                            row.innerHTML = `
                                <td style="text-align: center;">
                                    <div class="img-container">
                                        <img src="${imgSrc}" alt="${nombreSeguro}" 
                                             class="producto-img"
                                             onclick="Swal.fire({
                                                 imageUrl: '${imgSrc}',
                                                 imageAlt: '${nombreSeguro}',
                                                 title: '${nombreSeguro}',
                                                 confirmButtonText: 'Cerrar',
                                                 width: 460,
                                                
                                                 imageHeight: 360
                                                 ,
                                                 padding: '12px 14px'
                                             })"
                                             onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"
                                             loading="lazy">
                                    </div>
                                </td>
                                <td><strong>${item.codigo_producto || item.id}</strong></td>
                                <td><strong>${nombreSeguro}</strong></td>
                                <td>${escapeHtmlInventario(item.categoria_nombre || 'SIN CATEGORÍA')}</td>
                                <td><span class="badge ${stockBadge}">${formatoStockVisible(item.stock, item.categoria_nombre, item.venta_por_kilo)}</span></td>
                                <td style="color: #e74c3c;">${formatoMonedaInventario(precioCompra)}</td>
                                <td>${renderResumenPrecioDescuento(precioOriginal, precioVenta, descuentoPct)}</td>
                                <td style="color: #f39c12; font-weight: bold;">${formatoMonedaInventario(gananciaUnitaria)}</td>
                                <td><span class="badge ${gananciaBadge}">${porcentajeGanancia.toFixed(1)}%</span></td>
                                <td><strong>${formatoMonedaInventario(valorProducto)}</strong></td>
                                <td><span class="badge ${estadoBadge}">${estadoTexto}</span></td>
                                <td>
                                    <div style="display:inline-flex;gap:4px;align-items:center;justify-content:center;flex-wrap:nowrap;">
                                    ${permisoVer ? `<button type="button" class="btn-view btn-action" title="Ver" onclick="verProducto(${item.id})"><i class="fas fa-eye"></i></button>` : ''}
                                    ${permisoEditar ? `<button type="button" class="btn-edit btn-action" title="Editar" onclick="editarProducto(${item.id})"><i class="fas fa-edit"></i></button>` : ''}
                                    </div>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                        filtrarTodosProductosModal();
                    }
                })
                .catch(e => {
                    console.error('Error:', e);
                    const tbody = document.getElementById('todosProductosBody');
                    if (!tbody) return;
                    tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; padding: 20px; color: #ff6b6b;">ERROR: No se pudo cargar los productos</td></tr>';
                });
        }

        function normalizarBusquedaTodosProductosModal(valor) {
            return String(valor || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        function renderResultadosTodosProductosModal() {
            const input = document.getElementById('buscarTodosProductosModal');
            const resultados = document.getElementById('resultadosTodosProductosModal');
            if (!input || !resultados) return;
            const texto = normalizarBusquedaTodosProductosModal(input.value);
            const productos = Array.isArray(window.todosProductosModalCache) ? window.todosProductosModalCache : [];
            const coincidencias = productos.filter(item => normalizarBusquedaTodosProductosModal(`${item.id || ''} ${item.nombre || ''} ${item.codigo_producto || ''}`).includes(texto));
            resultados.innerHTML = coincidencias.map(item => `<button type="button" data-valor="${escapeHtmlInventario(String(item.nombre || ''))}" style="display:block;width:100%;padding:9px 12px;border:0;border-bottom:1px solid #f1f5f9;background:#fff;text-align:left;cursor:pointer;text-transform:uppercase;">${escapeHtmlInventario(String(item.nombre || ''))}</button>`).join('');
            resultados.style.display = coincidencias.length && texto ? 'block' : 'none';
        }

        function filtrarTodosProductosModal() {
            const input = document.getElementById('buscarTodosProductosModal');
            const tbody = document.getElementById('todosProductosBody');
            if (!input || !tbody) return;
            const texto = normalizarBusquedaTodosProductosModal(input.value);
            Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                row.style.display = !texto || normalizarBusquedaTodosProductosModal(row.textContent).includes(texto) ? '' : 'none';
            });
            renderResultadosTodosProductosModal();
        }

        function inicializarBuscadorTodosProductosModal() {
            const input = document.getElementById('buscarTodosProductosModal');
            const resultados = document.getElementById('resultadosTodosProductosModal');
            if (!input || input.dataset.inicializado === '1') return;
            input.dataset.inicializado = '1';
            input.addEventListener('input', filtrarTodosProductosModal);
            input.addEventListener('focus', renderResultadosTodosProductosModal);
            input.addEventListener('blur', () => setTimeout(() => { if (resultados) resultados.style.display = 'none'; }, 200));
            if (resultados) resultados.addEventListener('mousedown', event => {
                const opcion = event.target.closest('button[data-valor]');
                if (!opcion) return;
                input.value = opcion.dataset.valor || '';
                filtrarTodosProductosModal();
                resultados.style.display = 'none';
            });
        }

        // Mostrar modal de todos los productos
        function mostrarModalTodosProductos() {
            abrirModal('todosProductosModal');
            inicializarBuscadorTodosProductosModal();
            cargarTodosProductos();
        }

        // Ver detalles rápidos de un producto
        function verProducto(id) {
            fetch(productoControllerUrl + '?action=getOne&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    if (resp && resp.success && resp.data) {
                        const p = resp.data;
                        const body = document.getElementById('verProductoBody');
                        const stock = parseFloat(p.stock ?? 0) || 0;
                        const precioCompra = stock <= 0 ? 0 : (parseFloat(p.ultimo_precio_compra ?? 0) || 0);
                        const precioOriginal = stock <= 0 ? 0 : (parseFloat(p.precio_original ?? p.precio ?? 0) || parseFloat(p.precio ?? 0) || 0);
                        const descuentoPct = stock <= 0 ? 0 : (parseFloat(p.descuento_porcentaje ?? 0) || 0);
                        const precioVenta = stock <= 0 ? 0 : (parseFloat(p.precio_final ?? 0) || calcularPrecioFinalConDescuento(precioOriginal, descuentoPct, stock));
                        const porcentajeGanancia = stock <= 0 ? 0 : calcularPorcentajeGananciaInventario(precioVenta, precioCompra, p.porcentaje_ganancia);
                        const valorTotal = formatoMonedaInventario(precioVenta * stock);
                        const estado = p.estado == 1 ? 'ACTIVO' : 'INACTIVO';
                        body.innerHTML = `
                            <input type="hidden" id="verProdId" value="${p.id}">
                            <table style="width:100%; text-transform:none;">
                                <tr><td><strong>Código</strong></td><td>${escapeHtmlInventario(p.codigo || '')}</td></tr>
                                <tr><td><strong>Producto</strong></td><td>${escapeHtmlInventario(p.nombre || '')}</td></tr>
                                <tr><td><strong>Categoría</strong></td><td>${escapeHtmlInventario(p.categoria_nombre || 'Sin categoría')}</td></tr>
                                <tr><td><strong>Stock</strong></td><td>${p.stock}</td></tr>
                                <tr><td><strong>Precio de compra</strong></td><td>${formatoMonedaInventario(precioCompra)}</td></tr>
                                <tr><td><strong>Precio original</strong></td><td>${formatoMonedaInventario(precioOriginal)}</td></tr>
                                <tr><td><strong>Precio de venta</strong></td><td>${formatoMonedaInventario(precioVenta)}</td></tr>
                                <tr><td><strong>Ganancia unitaria</strong></td><td>${formatoMonedaInventario(precioVenta - precioCompra)}</td></tr>
                                <tr><td><strong>% Ganancia</strong></td><td>${porcentajeGanancia.toFixed(1)}%</td></tr>
                                <tr><td><strong>Descuento aplicado</strong></td><td>${descuentoPct > 0 ? descuentoPct.toFixed(1) + '%' : 'Sin descuento'}</td></tr>
                                <tr><td><strong>Valor de stock</strong></td><td>${valorTotal}</td></tr>
                                <tr><td><strong>Estado</strong></td><td>${estado}</td></tr>
                            </table>
                        `;
                        abrirModal('verProductoModal');
                    } else {
                        const msg = (resp && resp.message) ? resp.message : 'Producto no encontrado';
                        if (window.Swal) {
                            Swal.fire('Atención', msg, 'warning');
                        } else {
                            alert(msg);
                        }
                    }
                })
                .catch(e => {
                    console.error('Error verProducto:', e);
                    if (window.Swal) {
                        Swal.fire('Error', 'No se pudo cargar el producto', 'error');
                    }
                });
        }

        function cargarDetalleValorCompra() {
            obtenerProductosConGanancia()
                .then(data => {
                    if (!data.success || !Array.isArray(data.data)) {
                        throw new Error(data.message || 'No se pudo cargar el valor de compra');
                    }

                    const productos = data.data.filter(item => Number(item.estado) === 1 && (parseFloat(item.stock) || 0) > 0);
                    const totalProductosDetalleEl = document.getElementById('totalProductosCompraDetalle');
                    const valorTotalDetalleEl = document.getElementById('valorCompraTotalDetalle');
                    const unidadesTotalesDetalleEl = document.getElementById('unidadesTotalesCompraDetalle');
                    const tbody = document.getElementById('detalleValorCompraBody');

                    if (!totalProductosDetalleEl || !valorTotalDetalleEl || !unidadesTotalesDetalleEl || !tbody) {
                        return;
                    }

                    let totalUnidades = 0;
                    let valorCompraTotal = 0;

                    const productosConValores = productos.map(item => {
                        const stock = parseFloat(item.stock) || 0;
                        const precioCompra = parseFloat(item.ultimo_precio_compra ?? item.precio_compra_promedio ?? item.precio_compra ?? 0) || 0;
                        const valorTotalCompra = stock * precioCompra;
                        totalUnidades += stock;
                        valorCompraTotal += valorTotalCompra;
                        return {
                            ...item,
                            stock,
                            precioCompra,
                            valorTotalCompra
                        };
                    }).sort((a, b) => (b.valorTotalCompra || 0) - (a.valorTotalCompra || 0));

                    totalProductosDetalleEl.textContent = productosConValores.length;
                    valorTotalDetalleEl.textContent = '$' + valorCompraTotal.toLocaleString('es-CO', { maximumFractionDigits: 2 });
                    unidadesTotalesDetalleEl.textContent = totalUnidades;
                    tbody.innerHTML = '';

                    if (productosConValores.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No hay productos con stock</td></tr>';
                        return;
                    }

                    productosConValores.forEach(prod => {
                        const row = document.createElement('tr');
                        const imgSrc = resolverImagenProductoInventario(prod.imagen);
                        const porcentaje = valorCompraTotal > 0 ? ((prod.valorTotalCompra / valorCompraTotal) * 100).toFixed(1) : '0.0';
                        row.innerHTML = `
                            <td style="text-align: center;">
                                <div class="img-container">
                                    <img src="${imgSrc}" alt="${escapeHtmlInventario(prod.nombre || 'Producto')}" class="producto-img"
                                         onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"
                                         loading="lazy">
                                </div>
                            </td>
                            <td><strong>${escapeHtmlInventario(prod.codigo_producto || prod.id || 'N/A')}</strong></td>
                            <td>${escapeHtmlInventario(prod.nombre || 'PRODUCTO')}</td>
                            <td>${escapeHtmlInventario(prod.categoria_nombre || 'Sin categoría')}</td>
                            <td><span class="badge badge-info">${prod.stock}</span></td>
                            <td>$${(prod.precioCompra || 0).toLocaleString('es-CO', { maximumFractionDigits: 2 })}</td>
                            <td><strong style="color: #b42318;">$${(prod.valorTotalCompra || 0).toLocaleString('es-CO', { maximumFractionDigits: 2 })}</strong></td>
                            <td><span class="badge badge-primary">${porcentaje}%</span></td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(e => {
                    console.error('Error:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        text: 'No se pudo cargar el desglose del valor de compra'
                    });
                });
        }

        // Cargar formulario de edición para producto
        function configurarStockEdicionPorCategoria() {
            const categoria = document.getElementById('editProdCategoria');
            const stock = document.getElementById('editProdStock');
            if (!stock) return;

            stock.min = '0';
            stock.step = '0.001';

            if (categoria) {
                const nombreCategoria = normalizarTextoBusquedaInventario(categoria.options[categoria.selectedIndex]?.textContent || '');
                const esPorGramos = ['frutas', 'verduras', 'carnicos y refrigerados'].includes(nombreCategoria);
                stock.min = esPorGramos ? '0.001' : '0';
                stock.step = esPorGramos ? '0.001' : '1';
            }
        }

        function editarProducto(id) {
            fetch(productoControllerUrl + '?action=getOne&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    if (resp && resp.success && resp.data) {
                        const p = resp.data;
                        document.getElementById('editProdId').value = p.id ?? '';
                        document.getElementById('editProdNombre').value = p.nombre ?? '';
                        document.getElementById('editProdDescripcion').value = p.descripcion ?? '';
                        document.getElementById('editProdCategoria').value = p.categoria_id != null ? p.categoria_id : '';

                        const precioCompraValue = p.ultimo_precio_compra != null ? parseFloat(p.ultimo_precio_compra).toFixed(2) : '';
                        const precioVentaValue = p.precio != null ? parseFloat(p.precio).toFixed(2) : '';
                        const stockValue = p.stock != null ? Number(p.stock).toFixed(3) : '';
                        const porcentajeValue = p.porcentaje_ganancia != null ? parseFloat(p.porcentaje_ganancia).toFixed(1) : '';

                        document.getElementById('editProdPrecioCompra').value = precioCompraValue;
                        document.getElementById('editProdPrecio').value = precioVentaValue;
                        document.getElementById('editProdStock').value = stockValue;
                        configurarStockEdicionPorCategoria();
                        const categoriaEdicion = document.getElementById('editProdCategoria');
                        if (categoriaEdicion && categoriaEdicion.dataset.stockGramosListener !== '1') {
                            categoriaEdicion.dataset.stockGramosListener = '1';
                            categoriaEdicion.addEventListener('change', configurarStockEdicionPorCategoria);
                        }
                        document.getElementById('editProdPorcentaje').value = porcentajeValue;
                        document.getElementById('editProdCodigo').value = p.codigo ?? '';
                        document.getElementById('editProdCodigoBarras').value = p.codigo_barras ?? '';
                        // helpers to retrieve current purchase-cost input
                        const getPc = () => parseFloat(document.getElementById('editProdPrecioCompra').value) || 0;
                        // cuando cambie porcentaje recalcular precio
                        const pctInput = document.getElementById('editProdPorcentaje');
                        pctInput.oninput = () => {
                            const pc = getPc();
                            const pct = parseFloat(pctInput.value) || 0;
                            if (pc > 0) {
                                document.getElementById('editProdPrecio').value = (pc * (1 + pct/100)).toFixed(2);
                            }
                        };
                        // cuando usuario modifique precio, actualizar porcentaje si hay precio de compra
                        const precioInput = document.getElementById('editProdPrecio');
                        precioInput.oninput = () => {
                            const pc = getPc();
                            const pr = parseFloat(precioInput.value) || 0;
                            if (pc > 0) {
                                document.getElementById('editProdPorcentaje').value = ((pr - pc) / pc * 100).toFixed(1);
                            }
                        };
                        // actualizar porcentaje si el usuario cambia el precio de compra manualmente
                        const pcInput = document.getElementById('editProdPrecioCompra');
                        pcInput.oninput = () => {
                            const pc = getPc();
                            const pr = parseFloat(precioInput.value) || 0;
                            if (pc > 0 && pr > 0) {
                                document.getElementById('editProdPorcentaje').value = ((pr - pc) / pc * 100).toFixed(1);
                            }
                        };
                        abrirModal('editarProductoModal');
                    } else {
                        const msg = (resp && resp.message) ? resp.message : 'Producto no encontrado';
                        if (window.Swal) {
                            Swal.fire('Atención', msg, 'warning');
                        } else {
                            alert(msg);
                        }
                    }
                })
                .catch(e => {
                    console.error('Error editarProducto:', e);
                    if (window.Swal) {
                        Swal.fire('Error', 'No se pudo cargar el formulario de edición', 'error');
                    }
                });
        }

        // Función GLOBAL para mostrar alertas de stock
        function aseguraryMostrarSwalEstilizado(texto) {
            try {
                if (!document.getElementById('swal-stock-style')) {
                    const css = `.swal2-container{z-index:200000 !important}.swal2-popup{border-radius:8px}.swal2-icon{color:#f6a34d}.swal2-confirm{background-color:#6f42c1 !important;border:none;color:#fff}`;
                    const s = document.createElement('style'); s.id = 'swal-stock-style'; s.appendChild(document.createTextNode(css)); document.head.appendChild(s);
                }
            } catch (e) {}
            try { console.log('[ALERTA STOCK] ', texto); } catch (e) {}
            if (window.Swal) {
                return Swal.fire({ icon: 'warning', title: 'STOCK INSUFICIENTE', text: texto, confirmButtonText: 'ENTENDIDO', allowOutsideClick: false, allowEscapeKey: false, target: document.body });
            } else {
                alert(texto);
                return Promise.resolve();
            }
        }

        // filtrar tabla de todosProductos en tiempo real
        const iniciarVerificacionStock = () => {
            const filtroRes = document.getElementById('filtroResumen');
            const resultados = document.getElementById('resultadosFiltroInventario');
            if (filtroRes) {
                filtroRes.addEventListener('input', () => {
                    renderResultadosFiltroInventario();
                    programarFiltradoTablasInventario();
                });
                filtroRes.addEventListener('focus', renderResultadosFiltroInventario);
                filtroRes.addEventListener('blur', () => setTimeout(() => { if (resultados) resultados.style.display = 'none'; }, 200));
            }
            if (resultados) {
                resultados.addEventListener('mousedown', event => {
                    const opcion = event.target.closest('button[data-valor]');
                    if (!opcion || !filtroRes) return;
                    filtroRes.value = opcion.dataset.valor || '';
                    filtrarTablasInventario();
                    resultados.style.display = 'none';
                });
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', iniciarVerificacionStock, { once: true });
        } else {
            iniciarVerificacionStock();
        }

        // Enviar formulario de edición
        function submitEditarProducto(e) {
            e.preventDefault();
            const form = document.getElementById('formEditarProducto');
            const formData = new FormData(form);
            formData.append('action', 'editar');

            fetch(base_url + '/Controllers/ProductoController.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Actualizado',
                        text: resp.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    cerrarModal('editarProductoModal');
                    refrescarInventarioInmediato();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: resp.message
                    });
                }
            })
            .catch(err => console.error(err));
        }

        // si el usuario regresa a la página después de editar/ver un producto, refrescar la lista
        window.addEventListener('focus', () => {
            const modal = document.getElementById('todosProductosModal');
            if (modal && modal.classList.contains('active')) {
                cargarTodosProductos();
            }
        });

        // Mostrar modal de stock total
        function mostrarModalStockTotal() {
            abrirModal('stockTotalModal');
            
            obtenerProductosConGanancia()
                .then(data => {
                    if (data.success && data.data) {
                        const productos = data.data;
                        
                        // Calcular estadísticas
                        let stockTotal = 0;
                        let valorTotal = 0;
                        const categorias = new Set();
                        
                        productos.forEach(item => {
                            const stockValue = parseFloat(item.stock) || 0;
                            const precioCompra = stockValue <= 0 ? 0 : (parseFloat(item.ultimo_precio_compra ?? item.precio_compra_promedio ?? item.precio_compra ?? 0) || 0);
                            if (item.estado == 1 && stockValue > 0) {
                                stockTotal += stockValue;
                                valorTotal += stockValue * precioCompra;
                                if (item.categoria_nombre) {
                                    categorias.add(item.categoria_nombre);
                                }
                            }
                        });
                        
                        // Actualizar contadores del modal
                        document.getElementById('modalStockTotalUnidades').textContent = formatoStockTotalVisible(stockTotal);
                        document.getElementById('modalStockProductos').textContent = productos.filter(p => p.estado == 1 && (parseFloat(p.stock) || 0) > 0).length;
                        document.getElementById('modalStockCategorias').textContent = categorias.size;
                        document.getElementById('modalStockValor').textContent = '$' + valorTotal.toLocaleString('es-CO', {maximumFractionDigits: 2});
                        
                        // Actualizar tabla
                        const tbody = document.getElementById('stockTotalBody');
                        tbody.innerHTML = '';
                        
                        const productosActivos = productos.filter(p => p.estado == 1 && (parseFloat(p.stock) || 0) > 0).sort((a, b) => (parseFloat(b.stock) || 0) - (parseFloat(a.stock) || 0));
                        
                        if (productosActivos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No hay productos con stock disponible</td></tr>';
                            return;
                        }
                        
                        productosActivos.forEach(item => {
                            const row = document.createElement('tr');
                            
                            // Preparar imagen
                            const imgSrc = resolverImagenProductoInventario(item.imagen);
                            
                            // Calcular valores
                            const stockValue = parseFloat(item.stock || 0) || 0;
                            const precioVenta = stockValue <= 0 ? 0 : parseFloat(item.precio_venta) || 0;
                            
                            // Determinar estado del stock
                            let stockBadge = 'badge-success';
                            if (item.stock === 0 || item.stock === '0') {
                                stockBadge = 'badge-danger';
                            } else if (parseFloat(item.stock) < 5) {
                                stockBadge = 'badge-warning';
                            }
                            
                            row.innerHTML = `
                                <td style="text-align: center;">
                                    <div class="img-container">
                                        <img src="${imgSrc}" 
                                             alt="${item.nombre}" 
                                             class="producto-img"
                                             onclick="Swal.fire({
                                                 imageUrl: '${imgSrc}',
                                                 imageAlt: '${item.nombre}',
                                                 title: '${item.nombre}',
                                     /            confirmButtonText: 'Cerrar',
                                                 width: 460,
                                                 
                                                 imageHeight: 360,
                                                 padding: '12px 14px'
                                             })"
                                             onerror="this.onerror=null;this.src=base_url+'/favicon.ico'"
                                             loading="lazy">
                                    </div>
                                </td>
                                <td><strong>${item.codigo_producto || item.id}</strong></td>
                                <td><strong>${item.nombre}</strong></td>
                                <td>${item.categoria_nombre || 'SIN CATEGORÍA'}</td>
                                <td><span class="badge ${stockBadge}" style="font-size: 16px; padding: 8px 12px;">${formatoStockVisible(item.stock, item.categoria_nombre, item.venta_por_kilo)}</span></td>
                                <td style="color: #27ae60; font-weight: bold;">$${precioVenta.toLocaleString('es-CO', {maximumFractionDigits: 2})}</td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                })
                .catch(e => {
                    console.error('Error:', e);
                    const tbody = document.getElementById('stockTotalBody');
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #ff6b6b;">ERROR: No se pudo cargar el desglose de stock</td></tr>';
                });
        }

        // Calcular precio de venta basado en costo y porcentaje
        function calcularPrecioVenta() {
            const precioCompra = parseFloat(document.getElementById('precioCompra').value) || 0;
            const porcentaje = parseFloat(document.getElementById('porcentajeGanancia').value) || 0;
            
            const precioCalculado = precioCompra + (precioCompra * (porcentaje / 100));
            const baseRedondeo = Math.floor(precioCalculado / 50) * 50;
            const precioVenta = baseRedondeo + (precioCalculado % 50 > 25 ? 50 : 0);
            
            document.getElementById('precioVentaMostrado').value = precioVenta.toFixed(2);
            sincronizarPrecioPresentacionUnidad(precioCompra, precioVenta);
        }

        function sincronizarPrecioPresentacionUnidad(precioCompra, precioVenta) {
            const editor = document.getElementById('editorPresentacionesEntrada');
            const filas = editor?.querySelectorAll('.fila-presentacion-entrada') || [];
            const filaUnidad = filas[0];
            if (!filaUnidad) return;
            const compra = filaUnidad.querySelector('.entrada-pres-compra');
            const venta = filaUnidad.querySelector('.entrada-pres-venta');
            if (compra) compra.value = precioCompra > 0 ? precioCompra.toFixed(2) : '';
            if (venta) venta.value = precioVenta > 0 ? precioVenta.toFixed(2) : '';
        }

        function resetEntradaModalFields() {
            conservarPreciosPresentacionEntrada = false;
            const entradaForm = document.querySelector('#entradaModal form');
            if (entradaForm) {
                entradaForm.reset();
            }

            const productoEntrada = document.getElementById('productoEntrada');
            if (productoEntrada) {
                productoEntrada.value = '';
            }

            ['buscarProductoEntrada', 'buscarProveedorEntrada'].forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = '';
                    input.readOnly = true;
                    input.dataset.selectorActivo = '0';
                    input.dataset.lectorPreparado = '0';
                    input.dataset.selectedValue = '';
                }
            });

            const proveedorEntrada = document.getElementById('proveedorEntrada');
            if (proveedorEntrada) {
                proveedorEntrada.value = '';
            }

            const proveedorEntradaOtro = document.getElementById('proveedorEntradaOtro');
            if (proveedorEntradaOtro) {
                proveedorEntradaOtro.value = '';
            }

            const cantidadEntrada = document.getElementById('cantidadEntrada');
            if (cantidadEntrada) {
                cantidadEntrada.value = 1;
            }

            const precioCompra = document.getElementById('precioCompra');
            if (precioCompra) {
                precioCompra.value = '';
            }

            const porcentajeGanancia = document.getElementById('porcentajeGanancia');
            if (porcentajeGanancia) {
                porcentajeGanancia.value = '25';
            }
 
            const precioVentaMostrado = document.getElementById('precioVentaMostrado');
            if (precioVentaMostrado) {
                precioVentaMostrado.value = '';
            }

            const lote = document.getElementById('lote');
            if (lote) {
                lote.value = '';
            }

            const fechaVencimiento = document.getElementById('fechaVencimiento');
            if (fechaVencimiento) {
                fechaVencimiento.value = '';
            }

            const notasEntrada = document.getElementById('notasEntrada');
            if (notasEntrada) {
                notasEntrada.value = '';
            }

            const editorPresentaciones = document.getElementById('editorPresentacionesEntrada');
            const filasPresentaciones = document.getElementById('filasPresentacionesEntrada');
            if (editorPresentaciones) editorPresentaciones.style.display = 'none';
            if (filasPresentaciones) filasPresentaciones.innerHTML = '';

            const previewEntrada = document.getElementById('productoEntradaPreview');
            if (previewEntrada) {
                previewEntrada.innerHTML = '';
            }

            toggleProveedorEntradaOtro();
        }

        function abrirModalEntrada() {
            resetEntradaModalFields();
            abrirModal('entradaModal');
        }

        function toggleProveedorEntradaOtro() {
            const selectProveedor = document.getElementById('proveedorEntrada');
            const inputOtroProveedor = document.getElementById('proveedorEntradaOtro');
            if (!selectProveedor || !inputOtroProveedor) return;

            if (selectProveedor.value === '__OTRO__') {
                inputOtroProveedor.style.display = 'block';
                inputOtroProveedor.required = true;
            } else {
                inputOtroProveedor.style.display = 'none';
                inputOtroProveedor.required = false;
                inputOtroProveedor.value = '';
            }
        }

        function abrirListaProveedores(selectProveedor) {
            if (!selectProveedor || selectProveedor.disabled) return;
            selectProveedor.size = Math.min(18, Math.max(2, selectProveedor.options.length));
            selectProveedor.style.position = 'absolute';
            selectProveedor.style.left = '0';
            selectProveedor.style.top = '0';
            selectProveedor.style.width = '100%';
            selectProveedor.style.zIndex = '50';
        }

        function cerrarListaProveedores(selectProveedor) {
            if (!selectProveedor) return;
            selectProveedor.size = 1;
            selectProveedor.style.position = '';
            selectProveedor.style.left = '';
            selectProveedor.style.top = '';
            selectProveedor.style.width = '';
            selectProveedor.style.zIndex = '';
        }

        function cargarProveedoresEntrada(proveedorSeleccionado = '') {
            const selectProveedor = document.getElementById('proveedorEntrada');
            if (!selectProveedor) return;
            selectProveedor.innerHTML = '<option value="">-- SELECCIONAR PROVEEDOR --</option><option value="GENERAL">GENERAL</option><option value="__OTRO__">+ NUEVO PROVEEDOR</option>';
            if (proveedorSeleccionado) {
                const valor = String(proveedorSeleccionado);
                if ([...selectProveedor.options].some(opt => opt.value === valor)) {
                    selectProveedor.value = valor;
                }
            }
            toggleProveedorEntradaOtro();
        }

        function dispararRefreshInventarioGlobal() {
            try {
                localStorage.setItem('refreshInventario', String(Date.now()));
            } catch (e) {
                console.warn('No se pudo guardar refreshInventario', e);
            }
            try {
                window.dispatchEvent(new Event('refreshInventario'));
            } catch (e) {
                console.warn('No se pudo disparar evento refreshInventario en la misma ventana', e);
            }
            try {
                if (window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
                    window.parent.postMessage({ action: 'refreshInventario' }, '*');
                    window.parent.postMessage({ action: 'refreshDashboard' }, '*');
                }
            } catch (e) {
                console.warn('No se pudo notificar al padre del refresh de inventario', e);
            }
        }

        // Verificar y alertar sobre productos críticos y urgentes
        async function verificarYAlertarProductosCriticosYUrgentes() {
            try {
                const response = await fetch(base_url + '/Controllers/InventarioController.php?action=obtenerProductosCriticosYUrgentes', { cache: 'no-store' });
                const resultado = await response.json();

                if (resultado.success && resultado.count_total > 0) {
                    const construirTabla = (productos, color, mostrarStock) => {
                        if (!productos.length) return '';
                        const filas = productos.map(producto => {
                            const imagen = resolverImagenProductoInventario(producto.imagen);
                            const stock = `<td style="width:62px;padding:6px 7px;text-align:center;color:${color};font-weight:800;font-size:13px;vertical-align:middle;">${producto.stock}</td>`;
                            return `<tr style="border-bottom:1px solid #f0f2f4;text-transform:uppercase;"><td style="width:62px;padding:6px 5px;"><img src="${imagen}" alt="" style="width:46px;height:46px;object-fit:contain;border-radius:7px;border:1px solid #e5e7eb;background:#fff;" onerror="this.src='${base_url}/favicon.ico'"></td><td style="width:125px;padding:6px 7px;text-align:left;color:#263238;font-weight:700;font-size:13px;vertical-align:middle;word-break:break-word;">${producto.codigo}</td><td style="padding:6px 7px;text-align:left;color:#53636d;font-size:13px;vertical-align:middle;overflow-wrap:anywhere;">${producto.nombre}</td>${stock}</tr>`;
                        }).join('');
                        return `<section style="margin:0 0 12px;padding:12px;border:1px solid ${color}55;border-left:5px solid ${color};border-radius:10px;background:#fff;text-transform:uppercase;"><div style="font-size:14px;font-weight:800;color:${color};text-align:left;margin-bottom:7px;">${mostrarStock ? 'URGENTES' : 'CRÍTICOS'}</div><table style="width:100%;table-layout:fixed;border-collapse:collapse;font-size:13px;"><thead><tr style="border-bottom:2px solid #e5e7eb;color:#7a8790;font-size:11px;"><th style="width:62px;padding:5px;text-align:left;position:sticky;top:0;z-index:2;background:#fff;">IMG</th><th style="width:125px;padding:5px 7px;text-align:left;position:sticky;top:0;z-index:2;background:#fff;">CÓDIGO</th><th style="padding:5px 7px;text-align:left;position:sticky;top:0;z-index:2;background:#fff;">NOMBRE</th><th style="width:62px;padding:5px 7px;text-align:center;position:sticky;top:0;z-index:2;background:#fff;">STOCK</th></tr></thead><tbody>${filas}</tbody></table></section>`;
                    };
                    const mensaje = construirTabla(resultado.criticos || [], '#d33', false) + construirTabla(resultado.urgentes || [], '#ef8b00', true);

                    return Swal.fire({
                        title: 'PRODUCTOS CRÍTICOS SIN STOCK',
                        html: `<div style="max-height:380px;overflow-y:auto;padding:2px 4px;">${mensaje}</div>`,
                        icon: resultado.count_criticos > 0 ? 'error' : 'warning',
                        showCloseButton: true,
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: resultado.count_criticos > 0 ? '#d33' : '#ef8b00',
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                        width: 680
                    });
                }
                return Swal.fire({
                    icon: 'success',
                    title: 'STOCK SIN ALERTAS',
                    text: 'No hay productos con stock cero o urgente.',
                    confirmButtonText: 'ENTENDIDO'
                });
            } catch (error) {
                console.error('Error verificando stock de productos:', error);
                return Swal.fire({
                    icon: 'error',
                    title: 'NO SE PUDO CONSULTAR EL STOCK',
                    text: 'Intenta nuevamente.',
                    confirmButtonText: 'ENTENDIDO'
                });
            }
        }

        function abrirModalProductoDanado(grupo = 'perecederos') {
            const form = document.getElementById('formProductoDanado');
            if (form) form.reset();
            const select = document.getElementById('productoDanado');
            const titulo = document.getElementById('productoDanadoTitulo');
            const esNoPerecedero = grupo === 'no_perecederos';
            if (select) {
                Array.from(select.options).forEach(opcion => {
                    opcion.hidden = !opcion.value || opcion.dataset.grupo !== grupo;
                });
                select.dataset.grupo = grupo;
            }
            inicializarBusquedaSelect('productoDanado', 'buscarProductoDanado', 'productoDanadoSearchResults');
            if (titulo) {
                titulo.innerHTML = esNoPerecedero
                    ? '<i class="fas fa-lightbulb"></i> PRODUCTO DAÑADO NO PERECEDERO'
                    : '<i class="fas fa-triangle-exclamation"></i> PRODUCTO DAÑADO';
            }
            actualizarCantidadProductoDanado();
            abrirModal('productoDanadoModal');
        }

        function actualizarCantidadProductoDanado() {
            const select = document.getElementById('productoDanado');
            const cantidad = document.getElementById('cantidadProductoDanado');
            const ayuda = document.getElementById('stockProductoDanado');
            const opcion = select?.options?.[select.selectedIndex];
            const stock = Number(opcion?.dataset?.stock || 0);
            const porKilo = String(opcion?.dataset?.kilo || '0') === '1';
            if (cantidad) {
                cantidad.step = porKilo ? '0.001' : '1';
                cantidad.min = porKilo ? '0.001' : '1';
                cantidad.max = stock > 0 ? String(stock) : '';
            }
            if (ayuda) ayuda.textContent = stock > 0 ? `STOCK DISPONIBLE: ${stock}${porKilo ? ' KG' : ''}` : '';
        }

        async function registrarProductoDanado(evento) {
            evento.preventDefault();
            const form = evento.currentTarget;
            if (!form.reportValidity()) return;
            const select = document.getElementById('productoDanado');
            const cantidad = Number(document.getElementById('cantidadProductoDanado')?.value || 0);
            const opcion = select?.options?.[select.selectedIndex];
            const stock = Number(opcion?.dataset?.stock || 0);
            if (cantidad <= 0 || cantidad > stock) {
                Swal.fire({ icon: 'warning', title: 'CANTIDAD NO VÁLIDA', text: `La cantidad debe estar entre 0 y ${stock}.` });
                return;
            }
            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: '¿DESCONTAR PRODUCTO DAÑADO?',
                text: `Se descontarán ${cantidad} del inventario y quedará registrado como DAÑADO.`,
                showCancelButton: true,
                confirmButtonText: 'SÍ, DESCONTAR',
                cancelButtonText: 'CANCELAR',
                confirmButtonColor: '#b45309'
            });
            if (!confirmacion.isConfirmed) return;

            const datos = new FormData();
            datos.append('action', 'registrarProductoDanado');
            datos.append('producto_id', select.value);
            datos.append('cantidad', String(cantidad));
            datos.append('grupo', select.dataset.grupo || 'perecederos');
            datos.append('notas', document.getElementById('notasProductoDanado')?.value || '');
            try {
                const respuesta = await fetch(inventarioControllerUrl, { method: 'POST', body: datos });
                const resultado = await respuesta.json();
                if (!resultado.success) throw new Error(resultado.message || 'No se pudo registrar el daño.');
                cerrarModal('productoDanadoModal', false);
                dispararRefreshInventarioGlobal();
                refrescarInventarioInmediato();
                await Swal.fire({ icon: 'success', title: 'PRODUCTO DESCONTADO', text: 'La merma quedó registrada como salida por daño.' });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'ERROR', text: error.message || 'No se pudo registrar el daño.' });
            }
        }

        // Legacy: redirigir a la nueva función
        async function verificarYAlertarStockCero() {
            return verificarYAlertarProductosCriticosYUrgentes();
        }

        // Agrupa varias peticiones de refresco seguidas en una sola pasada,
        // para no repetir las mismas consultas al servidor al registrar un movimiento.
        let refrescoInventarioTimer = null;
        function refrescarInventarioInmediato() {
            if (refrescoInventarioTimer) clearTimeout(refrescoInventarioTimer);
            refrescoInventarioTimer = setTimeout(() => {
                refrescoInventarioTimer = null;
                refrescarInventarioAhora();
            }, 200);
        }

        function refrescarInventarioAhora() {
            // Refresco inmediato de la vista sin recargar la página completa.
            // Se actualizan solo las secciones activas para reducir el parpadeo y evitar
            // que la vista actual se salga del contexto del usuario.
            invalidarCacheProductosConGanancia();
            const modalTodosProductosAbierto = document.getElementById('todosProductosModal')?.classList.contains('active');
            const tabResumenActivo = document.getElementById('resumen')?.classList.contains('active');
            const tabEntradasActivo = document.getElementById('entradas')?.classList.contains('active');
            const tabSalidasActivo = document.getElementById('salidas')?.classList.contains('active');
            const tabMovimientosActivo = document.getElementById('movimientos')?.classList.contains('active');

            if (tabResumenActivo) cargarResumen();
            if (tabEntradasActivo) cargarEntradas();
            if (tabSalidasActivo) cargarSalidas();
            if (tabMovimientosActivo) cargarMovimientos();
            cargarEstadisticas();
            if (modalTodosProductosAbierto) cargarTodosProductos();
            actualizarSelectProductosInventario();

            if (typeof cargarVentasDia === 'function') cargarVentasDia(false);
            if (typeof cargarVentasMes === 'function') cargarVentasMes(false);
            if (typeof cargarValorInventario === 'function') cargarValorInventario();
            if (typeof cargarProductosReorden === 'function') cargarProductosReorden();

            // No se disparan alertas de stock crítico/urgente en cada refresco del inventario.
            // Las alertas deben mostrarse solo en los eventos programados de inicio de sesión
            // y en los horarios definidos por el negocio.

            // Refrescar el dashboard si está disponible
            try {
                localStorage.setItem('refreshDashboard', String(Date.now()));
                if (typeof window.refrescarDashboard === 'function') {
                    window.refrescarDashboard();
                } else if (parent && parent !== window && typeof parent.location !== 'undefined') {
                    parent.postMessage({ action: 'refreshDashboard' }, '*');
                }
                window.dispatchEvent(new Event('refreshDashboard'));
            } catch (e) {
                console.log('Dashboard no disponible para refrescar');
            }

            const reorden = document.getElementById('reordenModal');
            if (reorden && reorden.classList.contains('active') && typeof mostrarModalReorden === 'function') {
                mostrarModalReorden();
            }
            try {
                document.dispatchEvent(new Event('inventory:refreshed'));
            } catch (e) {}
        }

        // Preparar datos en modal al seleccionar producto desde listado
        function prepararEntrada(productoId, codigo) {
            resetEntradaModalFields();
            const select = document.getElementById('productoEntrada');
            if (select) {
                select.value = String(productoId || '');
                select.dispatchEvent(new Event('change'));
            }
            const codigoField = document.getElementById('codigoProductoEntrada');
            if (codigoField) {
                codigoField.value = codigo || '';
            }
            const loteField = document.getElementById('lote');
            if (loteField) {
                loteField.value = codigo || '';
            }
            abrirModal('entradaModal');
        }
        
        function prepararSalida(productoId, codigo) {
            const select = document.getElementById('productoSalida');
            if (!select) return;

            select.value = String(productoId || '');
            select.dispatchEvent(new Event('change'));

            abrirModal('salidaModal');
            inicializarReferenciaSalida();
        }
        

        // Registrar entrada
        function registrarEntrada(e) {
            e.preventDefault();
            
            const proveedorSelect = document.getElementById('proveedorEntrada');
            const proveedorOtro = document.getElementById('proveedorEntradaOtro');
            const proveedorSeleccionado = proveedorSelect ? proveedorSelect.value : '';
            const proveedorFinal = proveedorSeleccionado === '__OTRO__'
                ? (proveedorOtro ? proveedorOtro.value.trim().toLocaleUpperCase('es-CO') : '')
                : proveedorSeleccionado;

            if (!proveedorSeleccionado) {
                Swal.fire({icon: 'warning', title: 'PROVEEDOR REQUERIDO', text: 'Selecciona un proveedor para esta entrada.'});
                return;
            }

            const productoEntrada = document.getElementById('productoEntrada');
            const buscarProductoEntrada = document.getElementById('buscarProductoEntrada');
            let productoSeleccionado = productoEntrada?.value || buscarProductoEntrada?.dataset.selectedValue || '';
            if (!productoSeleccionado && productoEntrada && buscarProductoEntrada) {
                const textoProducto = normalizarTextoBusquedaInventario(buscarProductoEntrada.value);
                const opcionPorTexto = Array.from(productoEntrada.options).find(option =>
                    normalizarTextoBusquedaInventario(option.textContent) === textoProducto
                );
                if (opcionPorTexto) {
                    productoSeleccionado = opcionPorTexto.value;
                    productoEntrada.value = productoSeleccionado;
                    buscarProductoEntrada.dataset.selectedValue = productoSeleccionado;
                }
            }
            if (!productoSeleccionado) {
                Swal.fire({icon: 'warning', title: 'PRODUCTO REQUERIDO', text: 'Selecciona un producto para esta entrada.'});
                return;
            }

            if (proveedorSeleccionado === '__OTRO__' && !proveedorFinal) {
                Swal.fire({icon: 'warning', title: 'PROVEEDOR REQUERIDO', text: 'Ingresa el nombre del nuevo proveedor.'});
                return;
            }

            if (typeof window.validarVencimientoEntrada === 'function' && !window.validarVencimientoEntrada()) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'registrarEntrada');
            formData.append('producto_id', productoSeleccionado);
            formData.append('proveedor', proveedorFinal);
            formData.append('cantidad', document.getElementById('cantidadEntrada').value);
            const presentacionEntradaSeleccionada = (typeof presentacionSeleccionadaEntrada === 'function')
                ? presentacionSeleccionadaEntrada()
                : null;
            if (presentacionEntradaSeleccionada && presentacionEntradaSeleccionada.id > 0) {
                formData.append('presentacion_id', String(presentacionEntradaSeleccionada.id));
            }
            formData.append('precio_compra', document.getElementById('precioCompra').value);
            formData.append('porcentaje_ganancia', document.getElementById('porcentajeGanancia').value);
            formData.append('lote', document.getElementById('lote').value);
            formData.append('fecha_vencimiento', document.getElementById('fechaVencimiento').value);
            formData.append('notas', document.getElementById('notasEntrada').value);
            
            fetch(base_url + '/Controllers/InventarioController.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cargarProveedoresEntrada();

                        Swal.fire({
                            icon: 'success',
                            title: '¡ÉXITO!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 2000
                        });
                        cerrarModal('entradaModal');
                        dispararRefreshInventarioGlobal();
                        refrescarInventarioInmediato();
                    } else {
                        Swal.fire({icon: 'error', title: 'ERROR', text: data.message});
                    }
                })
                .catch(() => Swal.fire({icon: 'error', title: 'ERROR', text: 'Error al procesar la solicitud'}));
        }


        async function alertarSiSalidaDejaStockEnCero(items) {
            const idsVendidos = new Set((items || []).map(item => String(item.producto_id || '')).filter(Boolean));
            if (!idsVendidos.size) return;

            try {
                const data = await obtenerProductosConGanancia();
                const productos = Array.isArray(data?.data) ? data.data : [];
                const algunProductoEnCero = productos.some(producto =>
                    idsVendidos.has(String(producto.id || '')) && Number(producto.stock || 0) === 0
                );
                if (algunProductoEnCero) {
                    await verificarYAlertarProductosCriticosYUrgentes(`salida-cero-${Date.now()}`);
                }
            } catch (error) {
                console.warn('No se pudo verificar stock cero después de la salida', error);
            }
        }

        // Registrar salida
        let salidaEnProceso = false;

        async function registrarSalida(e) {
            e.preventDefault();
            if (salidaEnProceso) return;
            salidaEnProceso = true;

            const formularioSalida = e.currentTarget || document.querySelector('#salidaModal form');
            const botonGuardarSalida = formularioSalida?.querySelector('button[type="submit"]');
            if (botonGuardarSalida) {
                botonGuardarSalida.disabled = true;
                botonGuardarSalida.style.opacity = '0.7';
                botonGuardarSalida.style.cursor = 'wait';
            }

            const liberarEnvioSalida = () => {
                salidaEnProceso = false;
                if (botonGuardarSalida) {
                    botonGuardarSalida.disabled = false;
                    botonGuardarSalida.style.opacity = '';
                    botonGuardarSalida.style.cursor = '';
                }
            };

            const tipoSalida = document.getElementById('tipoSalida')?.value || 'venta';
            const notas = document.getElementById('notasSalida').value;
            const metodoPagoSalida = document.getElementById('metodoPagoSalida')?.value || 'contado';
            const clienteCreditoId = parseInt(document.getElementById('clienteCreditoSalida')?.value || '0', 10) || 0;
            const referenciaVenta = (document.getElementById('referenciaSalida')?.value || '').trim() || generarReferenciaVenta();
            const cantidadUnica = normalizarCantidadSalida(document.getElementById('cantidadSalida').value);
            const seleccionActual = productoSalidaSeleccionado();

            let items = carritoSalida.length > 0
                ? carritoSalida.map(item => ({
                    producto_id: item.producto_id,
                    nombre: item.nombre,
                    presentacion_id: Number(item.presentacion_id || 0),
                    cantidad: normalizarCantidadSalida(item.cantidad, item.venta_por_kilo === true || Number(item.venta_por_kilo) === 1),
                    stock: parseFloat(item.stock || 0) || 0,
                    precio_venta: parseFloat(item.precio) || 0
                }))
                : (seleccionActual ? [{
                    producto_id: seleccionActual.producto_id,
                    nombre: seleccionActual.nombre,
                    presentacion_id: Number(seleccionActual.presentacion_id || 0),
                    cantidad: normalizarCantidadSalida(cantidadUnica, seleccionActual.venta_por_kilo),
                    stock: parseFloat(seleccionActual.stock || 0) || 0,
                    precio_venta: parseFloat(seleccionActual.precio) || 0
                }] : []);

            const itemsUnicos = new Map();
            items.forEach(item => {
                const clave = `${Number(item.producto_id)}:${Number(item.presentacion_id || 0)}`;
                const existente = itemsUnicos.get(clave);
                if (existente) {
                    existente.cantidad += item.cantidad;
                    return;
                }
                itemsUnicos.set(clave, { ...item });
            });
            items = Array.from(itemsUnicos.values());

            if (!items.length) {
                liberarEnvioSalida();
                Swal.fire({
                    icon: 'warning',
                    title: 'Producto requerido',
                    text: 'Selecciona o agrega al menos un producto para registrar la salida.'
                });
                return;
            }

            if (metodoPagoSalida === 'credito' && clienteCreditoId <= 0) {
                liberarEnvioSalida();
                Swal.fire({
                    icon: 'warning',
                    title: 'Cliente requerido',
                    text: 'Selecciona un cliente para registrar una venta a crédito.'
                });
                return;
            }

            const stockInvalido = items.find(item => item.cantidad > (parseFloat(item.stock || 0) || 0));
            if (stockInvalido) {
                liberarEnvioSalida();
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock insuficiente',
                    text: `No se puede registrar la salida. La cantidad solicitada de ${stockInvalido.nombre || 'este producto'} supera el stock disponible (${stockInvalido.stock}).`
                });
                return;
            }

            if (metodoPagoSalida === 'credito') {
                const formDataCredito = new FormData();
                formDataCredito.append('action', 'registrarCreditoSalida');
                formDataCredito.append('cliente_id', String(clienteCreditoId));
                formDataCredito.append('referencia', referenciaVenta);
                formDataCredito.append('tipo_salida', tipoSalida);
                formDataCredito.append('notas', notas);
                formDataCredito.append('items', JSON.stringify(items));

                try {
                    const responseCredito = await fetch(base_url + '/Controllers/CreditosController.php', {
                        method: 'POST',
                        body: formDataCredito
                    });
                    const dataCredito = await responseCredito.json();

                    if (!dataCredito.success) {
                        throw new Error(dataCredito.message || 'No se pudo registrar el crédito');
                    }

                    Swal.fire({
                        icon: 'success',
                        title: '¡CRÉDITO REGISTRADO!',
                        text: 'Crédito registrado. La referencia se asignará al momento de pagarlo.',
                        showConfirmButton: false,
                        timer: 2200
                    });

                    const clienteCredito = document.getElementById('clienteCreditoSalida');
                    const buscarCliente = document.getElementById('buscarClienteCreditoSalida');
                    if (clienteCredito) clienteCredito.value = '';
                    if (buscarCliente) buscarCliente.value = '';
                    carritoSalida = [];
                    renderCarritoSalida();
                    liberarEnvioSalida();
                    cerrarModal('salidaModal', false);
                    dispararRefreshInventarioGlobal();
                    refrescarInventarioInmediato();
                    return;
                } catch (error) {
                    liberarEnvioSalida();
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        text: error.message || 'No fue posible registrar el crédito'
                    });
                    return;
                }

                const totalSalidaSinRedondear = calcularTotalItemsSalida(items);
                const totalSalidaRedondeado = redondearTotalSalida(totalSalidaSinRedondear);
                if (Math.abs(totalSalidaRedondeado - totalSalidaSinRedondear) > 0.001) {
                    const confirmacionTotal = await Swal.fire({
                        icon: 'info',
                        title: 'TOTAL AJUSTADO',
                        text: `El total de la salida es ${formatoMonedaInventario(totalSalidaSinRedondear)} y se ajustará a ${formatoMonedaInventario(totalSalidaRedondeado)}.`,
                        showCancelButton: true,
                        confirmButtonText: 'CONTINUAR',
                        cancelButtonText: 'REVISAR'
                    });
                    if (!confirmacionTotal.isConfirmed) {
                        liberarEnvioSalida();
                        return;
                    }
                }
            }

            let procesados = 0;
            for (const item of items) {
                const formData = new FormData();
                formData.append('action', 'registrarSalida');
                formData.append('producto_id', String(item.producto_id));
                formData.append('cantidad', String(item.cantidad));
                if (Number(item.presentacion_id || 0) > 0) {
                    formData.append('presentacion_id', String(item.presentacion_id));
                }
                formData.append('precio_venta', String(item.precio_venta || 0));
                formData.append('tipo_salida', tipoSalida);
                formData.append('metodo_pago', metodoPagoSalida);
                formData.append('referencia', referenciaVenta);
                formData.append('notas', notas);

                try {
                    const response = await fetch(base_url + '/Controllers/InventarioController.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Error al registrar salida');
                    }

                    procesados++;
                } catch (error) {
                    liberarEnvioSalida();
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        text: `No se pudo registrar la salida del producto ${item.producto_id}. ${error.message || ''}`.trim()
                    });
                    return;
                }
            }

            Swal.fire({
                icon: 'success',
                title: '¡ÉXITO!',
                text: procesados > 1 ? `Se registraron ${procesados} salidas correctamente` : 'Salida registrada correctamente',
                showConfirmButton: false,
                timer: 2000
            }).then(async () => {
                await alertarSiSalidaDejaStockEnCero(items);
            });

            liberarEnvioSalida();
            cerrarModal('salidaModal', false);
            dispararRefreshInventarioGlobal();
            refrescarInventarioInmediato();
        }

        // Funciones de incremento/decremento de cantidad
        function incrementarCantidad(inputId) {
            const inputEl = document.getElementById(inputId);
            if (!inputEl) return;
            const paso = parseFloat(inputEl.step) || 1;
            const minimo = parseFloat(inputEl.min) || 1;
            const val = Math.max(minimo, parseFloat(String(inputEl.value).replace(',', '.')) || minimo);
            inputEl.value = (val + paso).toFixed(paso < 1 ? 3 : 0);
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
            inputEl.dispatchEvent(new Event('change', { bubbles: true }));
            try { validarCantidadGenericaInventario(inputEl); } catch (err) {}
        }

        function decrementarCantidad(inputId) {
            const inputEl = document.getElementById(inputId);
            if (!inputEl) return;
            const paso = parseFloat(inputEl.step) || 1;
            const minimo = parseFloat(inputEl.min) || 1;
            const val = Math.max(minimo, parseFloat(String(inputEl.value).replace(',', '.')) || minimo);
            if (val > minimo) {
                inputEl.value = Math.max(minimo, val - paso).toFixed(paso < 1 ? 3 : 0);
                inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                try { clearInlineErrorCantidad(inputEl); } catch (err) {}
            }
        }

        window.mostrarErrorInlineCantidad = function(inputEl, mensaje) {
            try {
                if (!inputEl) return;
                const parent = inputEl.parentNode;
                if (!parent) return;
                const existing = parent.querySelector('.inline-stock-error');
                if (existing) existing.remove();
                const div = document.createElement('div');
                div.className = 'inline-stock-error';
                div.style.color = '#7a1226';
                div.style.background = '#fff0f0';
                div.style.padding = '6px 8px';
                div.style.marginTop = '6px';
                div.style.borderRadius = '6px';
                div.style.fontWeight = '700';
                div.textContent = mensaje;
                parent.appendChild(div);
                try { inputEl.focus(); } catch (e) {}
                setTimeout(() => { try { div.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {} }, 50);
            } catch (e) {}
        };

        window.clearInlineErrorCantidad = function(inputEl) {
            try {
                if (!inputEl) return;
                const parent = inputEl.parentNode;
                if (!parent) return;
                const ex = parent.querySelector('.inline-stock-error');
                if (ex) ex.remove();
            } catch (e) {}
        };

        window.cargarSalidas = cargarSalidas;
        window.ajustarTamanoValorInventario = ajustarTamanoValorInventario;
        window.obtenerPrecioUnitarioSalidaItem = obtenerPrecioUnitarioSalidaItem;

        // Iniciar carga de datos
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando inventario...');

            inicializarBusquedaSelect('productoEntrada', 'buscarProductoEntrada', 'productoEntradaSearchResults');
            inicializarBusquedaSelect('productoSalida', 'buscarProductoSalida', 'productoSalidaSearchResults');
            inicializarBusquedaSelect('proveedorEntrada', 'buscarProveedorEntrada', 'proveedorEntradaSearchResults');
            configurarLectorEntradaGlobal();
            configurarLectorSalidaGlobal();

            inicializarSelectorMesVentas();

            ajustarTamanoValorInventario();
            ajustarTamanoGananciaTotal();
            cargarEstadisticas();
            cargarProveedoresEntrada();
            setTimeout(() => {
                cargarResumen();
            }, 300);

            window.addEventListener('resize', () => {
                ajustarTamanoValorInventario();
                ajustarTamanoGananciaTotal();
            });

            // Ampliar imagen al hacer clic (solo para imágenes que no tienen onclick propio)
            document.addEventListener('click', function(event) {
                const imagen = event.target.closest('img.producto-img');
                if (!imagen) return;
                if (imagen.hasAttribute('onclick')) return;

                const src = imagen.getAttribute('src') || (base_url + '/favicon.ico');
                const nombre = imagen.getAttribute('alt') || 'PRODUCTO';

                Swal.fire({
                    imageUrl: src,
                    imageAlt: nombre,
                    title: nombre,
                    confirmButtonText: 'Cerrar',
                    width: '520px',
                  
                    imageHeight: 360,
                    padding: '12px 14px'
                });
            });
            
            // Agregar funcionalidad de preview de imagen en los selects de productos
            const productoEntradaSelect = document.getElementById('productoEntrada');
            const productoSalidaSelect = document.getElementById('productoSalida');
            const proveedorEntradaSelect = document.getElementById('proveedorEntrada');

            if (proveedorEntradaSelect) {
                proveedorEntradaSelect.addEventListener('change', toggleProveedorEntradaOtro);
            }
            const proveedorEntradaOtro = document.getElementById('proveedorEntradaOtro');
            if (proveedorEntradaOtro) {
                proveedorEntradaOtro.addEventListener('input', function() {
                    const inicio = this.selectionStart;
                    this.value = this.value.toLocaleUpperCase('es-CO');
                    if (inicio !== null) this.setSelectionRange(inicio, inicio);
                });
            }
            
            if (productoEntradaSelect) {
                productoEntradaSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const imagenFilename = selectedOption.getAttribute('data-imagen') || '';
                    const codigo = selectedOption.getAttribute('data-codigo');
                    const previewDiv = document.getElementById('productoEntradaPreview');
                    const codigoField = document.getElementById('codigoProductoEntrada');
                    actualizarUnidadEntrada();
                    
                    // Actualizar campo oculto de código
                    if (codigoField && codigo) {
                        codigoField.value = codigo;
                    }
                    
                    if (this.value && imagenFilename) {
                        const imgSrc = resolverImagenProductoInventario(imagenFilename);
                        previewDiv.innerHTML = `<img src="${imgSrc}" alt="Producto" style="width: 84px; height: 84px; object-fit: contain; border-radius: 8px; border: 2px solid #ddd; background: #fff;" onerror="this.onerror=null;this.src=base_url+'/favicon.ico'">`;
                    } else {
                        previewDiv.innerHTML = '';
                    }
                });
            }

            toggleProveedorEntradaOtro();
            
            if (productoSalidaSelect) {
                productoSalidaSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const codigo = selectedOption ? selectedOption.getAttribute('data-codigo') : '';
                    const codigoField = document.getElementById('codigoProductoSalida');
                    const previewDiv = document.getElementById('productoSalidaPreview');

                    if (!this.value) {
                        if (codigoField) codigoField.value = '';
                        if (previewDiv) previewDiv.innerHTML = '';
                        if (document.getElementById('cantidadSalida')) {
                            document.getElementById('cantidadSalida').value = 1;
                            document.getElementById('cantidadSalida').removeAttribute('max');
                        }
                        try { window.clearInlineErrorCantidad(document.getElementById('cantidadSalida')); } catch (e) {}
                        return;
                    }
                    
                    // Actualizar campo oculto de código
                    if (codigoField) {
                        codigoField.value = codigo || '';
                    }
                    if (previewDiv) {
                        const imagenFilename = selectedOption?.getAttribute('data-imagen') || '';
                        const imgSrc = resolverImagenProductoInventario(imagenFilename);
                        previewDiv.innerHTML = imgSrc
                            ? `<img src="${imgSrc}" alt="Producto" style="width:70px; height:70px; object-fit:contain;" onerror="this.onerror=null;this.src=base_url+'/favicon.ico'">`
                            : '';
                    }

                    const cantidadField = document.getElementById('cantidadSalida');
                    if (cantidadField) {
                        const esPorKilo = esProductoPorKilosSalida(selectedOption);
                        cantidadField.min = esPorKilo ? '0.001' : '1';
                        cantidadField.step = esPorKilo ? '0.001' : '1';
                        cantidadField.value = esPorKilo ? '0.001' : '1';
                        cantidadField.removeAttribute('max');
                    }
                    
                    validarCantidadSalida();
                });
            }

            toggleCamposCreditoSalida();
            cargarClientesCreditoSalida();
            renderCarritoSalida();

            // La alerta de stock crítico/urgente no debe dispararse al registrar una entrada.
            // Solo debe aparecer en flujos reales de salida/orden de taller cuando haya un
            // producto sin stock disponible o con stock crítico en la operación que se está realizando.
            const productoSalida = document.getElementById('productoSalida');
            const cantidadSalida = document.getElementById('cantidadSalida');

            const validarCantidadSalida = () => {
                if (!productoSalida || !cantidadSalida) return;
                const buscarProducto = document.getElementById('buscarProductoSalida');
                if (buscarProducto?.dataset.seleccionProductoEnCurso === '1') return;
                if (!productoSalida.value) {
                    cantidadSalida.max = '';
                    try { window.clearInlineErrorCantidad(cantidadSalida); } catch (e) {}
                    return;
                }
                const option = productoSalida.options[productoSalida.selectedIndex];
                if (!option) return;
                const stockActual = parseFloat(option.getAttribute('data-stock') || '0') || 0;
                cantidadSalida.max = stockActual > 0 ? stockActual : '';
                const cantidadValor = normalizarCantidadSalida(cantidadSalida.value, esProductoPorKilosSalida(option));

                if (cantidadValor > stockActual) {
                    const texto = stockActual > 0 ? `Solo hay ${stockActual} unidad(es) disponibles de ${option.getAttribute('data-codigo') || 'este producto'}.` : `No hay stock disponible para ${option.getAttribute('data-codigo') || 'este producto'}.`;
                    if (stockActual <= 0) {
                        limpiarSeleccionSalidaUI();
                    } else {
                        cantidadSalida.value = stockActual;
                        mostrarAlertaStockInsuficiente(option, stockActual);
                    }
                }
            };

            // Centralizar la alerta para reutilizarla desde varios handlers
            function mostrarAlertaStockInsuficiente(option, stockActual) {
                try {
                    const codigo = option ? (option.getAttribute('data-codigo') || '') : '';
                    const texto = stockActual > 0
                        ? `Solo hay ${stockActual} unidad(es) disponibles de ${codigo || 'este producto'}.`
                        : `No hay stock disponible para ${codigo || 'este producto'}.`;
                    aseguraryMostrarSwalEstilizado(texto);
                } catch (e) {
                    try { aseguraryMostrarSwalEstilizado('Stock insuficiente'); } catch(e){}
                }
            }

            // Re-attach listeners en modal de salida (por si el DOM se resetea)
            function attachSalidaListeners() {
                const producto = document.getElementById('productoSalida');
                const cantidad = document.getElementById('cantidadSalida');
                if (!producto || !cantidad) return;

                // Marcar para no duplicar
                if (cantidad.dataset.stockListeners === '1') return;
                cantidad.dataset.stockListeners = '1';

                const validar = function() {
                    try {
                        if (document.getElementById('buscarProductoSalida')?.dataset.seleccionProductoEnCurso === '1') return;
                        const option = producto.options[producto.selectedIndex];
                        if (!option) return;
                        const stockActual = parseFloat(option.getAttribute('data-stock') || '0') || 0;
                        const esPorKilo = esProductoPorKilosSalida(option);
                        cantidad.min = esPorKilo ? '0.001' : '1';
                        cantidad.step = esPorKilo ? '0.001' : '1';
                        cantidad.max = stockActual > 0 ? stockActual : '';
                        const val = normalizarCantidadSalida(cantidad.value, esPorKilo);
                        if (val > stockActual) {
                            if (stockActual <= 0) {
                                limpiarSeleccionSalidaUI();
                            } else {
                                cantidad.value = stockActual;
                                mostrarAlertaStockInsuficiente(option, stockActual);
                            }
                        }
                    } catch (e) {}
                };

                cantidad.addEventListener('input', validar);
                cantidad.addEventListener('change', validar);
                cantidad.addEventListener('blur', validar);
                cantidad.addEventListener('paste', function() { setTimeout(validar, 0); });
                cantidad.addEventListener('wheel', function(e) {
                    const option = producto.options[producto.selectedIndex];
                    if (!option) return;
                    const stockActual = parseFloat(option.getAttribute('data-stock') || '0') || 0;
                    const val = normalizarCantidadSalida(cantidad.value, esProductoPorKilosSalida(option));
                    if ((e.deltaY < 0) && val >= stockActual) {
                        e.preventDefault();
                        mostrarAlertaStockInsuficiente(option, stockActual);
                    }
                }, { passive: false });

                cantidad.addEventListener('keydown', function(e) {
                    const option = producto.options[producto.selectedIndex];
                    if (!option) return;
                    const stockActual = parseFloat(option.getAttribute('data-stock') || '0') || 0;
                    if (e.key === 'ArrowUp' || e.key === 'ArrowRight') {
                        const val = normalizarCantidadSalida(cantidad.value, esProductoPorKilosSalida(option));
                        if (val >= stockActual) {
                            e.preventDefault();
                            const texto = stockActual > 0 ? `Solo hay ${stockActual} unidad(es) disponibles de ${option.getAttribute('data-codigo') || 'este producto'}.` : `No hay stock disponible para ${option.getAttribute('data-codigo') || 'este producto'}.`;
                            mostrarAlertaStockInsuficiente(option, stockActual);
                        }
                    }
                });

                producto.addEventListener('change', function() {
                    validar();
                });
            }

            // Observador para detectar cambios programáticos en el input
            try {
                const mo = new MutationObserver(() => {
                    validarCantidadSalida();
                });
                mo.observe(cantidadSalida, { attributes: true, attributeFilter: ['value'] });
            } catch (e) {}

            // Manejar scroll del mouse y paste para evitar aumentar más del stock sin alerta
            if (cantidadSalida) {
                cantidadSalida.addEventListener('wheel', function(e) {
                    const option = productoSalida.options[productoSalida.selectedIndex];
                    if (!option) return;
                    const stockActual = parseFloat(option.getAttribute('data-stock') || '0') || 0;
                    const val = normalizarCantidadSalida(cantidadSalida.value, esProductoPorKilosSalida(option));
                    // si la rueda intenta aumentar cuando ya está en tope
                    if ((e.deltaY < 0) && val >= stockActual) {
                        e.preventDefault();
                        mostrarAlertaStockInsuficiente(option, stockActual);
                    }
                }, { passive: false });

                cantidadSalida.addEventListener('paste', function() {
                    setTimeout(validarCantidadSalida, 0);
                });
            }

            if (productoSalida && cantidadSalida) {
                productoSalida.addEventListener('change', validarCantidadSalida);
                cantidadSalida.addEventListener('change', validarCantidadSalida);
                cantidadSalida.addEventListener('input', validarCantidadSalida);
                cantidadSalida.addEventListener('blur', validarCantidadSalida);
                cantidadSalida.addEventListener('keydown', function(event) {
                    const option = productoSalida.options[productoSalida.selectedIndex];
                    if (!option) return;
                    const stockActual = parseFloat(option.getAttribute('data-stock') || '0') || 0;
                    if (stockActual <= 0) return;
                    if (event.key === 'ArrowUp' || event.key === 'ArrowRight') {
                        const cantidadValor = normalizarCantidadSalida(cantidadSalida.value, esProductoPorKilosSalida(option));
                        if (cantidadValor >= stockActual) {
                            event.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Stock insuficiente',
                                text: `Solo hay ${stockActual} unidad(es) disponibles de ${option.getAttribute('data-codigo') || 'este producto'}.`,
                                confirmButtonText: 'OK'
                            });
                        }
                    }
                });
            }

            function obtenerSelectStockPorInputInventario(inputEl) {
                if (!inputEl) return null;
                const form = inputEl.closest('form') || document;
                const selects = Array.from(form.querySelectorAll('select'));
                const selectProducto = selects.find(sel => {
                    const idName = `${sel.id || ''} ${sel.name || ''}`.toLowerCase();
                    const option = sel.options[sel.selectedIndex];
                    const stockAttr = option ? (option.getAttribute('data-stock') || option.dataset.stock || '') : '';
                    return idName.includes('producto') && (stockAttr !== '' || sel.options.length > 0);
                });
                if (selectProducto) return selectProducto;
                const selectStock = selects.find(sel => {
                    const option = sel.options[sel.selectedIndex];
                    const stockAttr = option ? (option.getAttribute('data-stock') || option.dataset.stock || '') : '';
                    return stockAttr !== '';
                });
                return selectStock || (document.getElementById('productoSalida') || form.querySelector('select') || null);
            }

            // Validador global para inputs que contengan 'cantidad' en id/name.
            // Debe aplicarse solo dentro del flujo real de salida; no en entradas ni en otras vistas.
            function validarCantidadGenericaInventario(inputEl) {
                if (!inputEl) return;
                const enSalida = !!(inputEl.closest('#salidaModal') || inputEl.id === 'cantidadSalida' || inputEl.name === 'cantidadSalida');
                if (!enSalida || inputEl.id === 'cantidadEntrada' || inputEl.closest('#entradaModal')) return;

                const select = obtenerSelectStockPorInputInventario(inputEl);
                if (!select) return;
                let option = select.options[select.selectedIndex];
                if (!option && select.value) option = select.querySelector(`option[value="${select.value}"]`);
                if (!option) return;
                const stockAttr = option.getAttribute('data-stock') || option.dataset.stock || '0';
                const stockActual = parseFloat(String(stockAttr).replace(',', '.')) || 0;
                const val = normalizarCantidadSalida(inputEl.value, esProductoPorKilosSalida(option));
                if (stockActual <= 0) {
                    const texto = `No hay stock disponible para ${option.getAttribute('data-codigo') || 'este producto'}.`;
                    limpiarSeleccionSalidaUI();
                    aseguraryMostrarSwalEstilizado(texto);
                    return;
                }
                if (val > stockActual) {
                    inputEl.value = String(stockActual);
                    const texto = `Solo hay ${stockActual} unidad(es) disponibles de ${option.getAttribute('data-codigo') || 'este producto'}.`;
                    aseguraryMostrarSwalEstilizado(texto);
                    return;
                }
                try { clearInlineErrorCantidad(inputEl); } catch (err) {}
            }

            // No se activa una validación global de stock en cualquier input del sistema.
            // Las alertas de stock insuficiente solo deben dispararse cuando el usuario
            // esté realmente ejecutando una salida o una operación de taller con stock real.

            const productoEntrada = document.getElementById('productoEntrada');
            if (productoEntrada) {
                productoEntrada.addEventListener('change', function() {
                    const selected = this.options[this.selectedIndex];
                    const codigo = selected ? selected.getAttribute('data-codigo') || '' : '';
                    const codigoField = document.getElementById('codigoProductoEntrada');
                    if (codigoField) {
                        codigoField.value = codigo;
                    }
                    const loteField = document.getElementById('lote');
                    if (loteField) {
                        loteField.value = codigo;
                    }
                });
            }
        });
    </script>
<script>
    (function() {
        const empresaId = <?= (int)($_SESSION['empresa_id'] ?? 0); ?>;
        if (!empresaId) return;
        const storageKey = 'company_theme_live_' + empresaId;
        const root = document.documentElement;

        const normalizarHex = (valor) => {
            const limpio = String(valor || '').trim();
            if (!limpio) return '';
            const conHash = limpio.startsWith('#') ? limpio : ('#' + limpio);
            return /^#[0-9A-Fa-f]{6}$/.test(conHash) ? conHash.toUpperCase() : '';
        };

        const setVar = (nombre, valor) => {
            const color = normalizarHex(valor);
            if (color) root.style.setProperty(nombre, color);
        };

        const aplicarTema = (tema) => {
            if (!tema || typeof tema !== 'object') return;
            setVar('--primary-blue', tema.color_principal);
            setVar('--secondary-blue', tema.color_secundario);
            setVar('--primary-color', tema.color_principal);
            setVar('--primary-dark', tema.color_menu_lateral || tema.color_principal);
            setVar('--primary-light', tema.color_secundario);
            setVar('--focus-input', tema.color_focus_inputs);
            setVar('--btn-edit', tema.color_btn_editar);
            setVar('--btn-delete', tema.color_btn_eliminar);
        };

        const sync = () => {
            try {
                const raw = localStorage.getItem(storageKey);
                if (!raw) return;
                const payload = JSON.parse(raw);
                if (payload && payload.theme) aplicarTema(payload.theme);
            } catch (error) {}
        };

        const INVENTARIO_REFRESH_KEY = 'refreshInventario';
        window.addEventListener('storage', (event) => { if (event.key === storageKey) sync(); });
        window.addEventListener('storage', (event) => {
            if (event.key === INVENTARIO_REFRESH_KEY) {
                try {
                    refrescarInventarioInmediato();
                } catch (e) {
                    console.warn('No se pudo refrescar inventario desde storage event', e);
                }
            }
        });
        window.addEventListener('message', (event) => {
            if (!event || !event.data || event.data.action !== 'refreshInventario') return;
            try {
                refrescarInventarioInmediato();
            } catch (e) {
                console.warn('No se pudo refrescar inventario desde postMessage', e);
            }
        });
        window.addEventListener('focus', sync);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
        document.addEventListener('DOMContentLoaded', sync);
        sync();
    })();
</script>

<!-- Script: Forzar MAYÚSCULAS en vistas de inventario -->
<script>
    (function() {
        function upperizeTextNode(node) {
            if (!node) return;
            if (node.nodeType === Node.TEXT_NODE) {
                const txt = node.nodeValue || '';
                if (txt.trim() !== '') node.nodeValue = txt.toUpperCase();
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                const tag = node.tagName.toLowerCase();
                if (tag === 'input' || tag === 'textarea') {
                    try {
                        if (node.value) node.value = node.value.toString().toUpperCase();
                        if (node.placeholder) node.placeholder = node.placeholder.toString().toUpperCase();
                    } catch (e) {}
                } else if (tag === 'option') {
                    try { node.text = (node.text || '').toUpperCase(); } catch(e){}
                } else if (tag === 'img' || tag === 'svg') {
                    // skip images/icons
                } else {
                    for (const child of Array.from(node.childNodes)) {
                        upperizeTextNode(child);
                    }
                }
            }
        }

            function mostrarAlertaStockTexto(texto) {
                aseguraryMostrarSwalEstilizado(texto);
            }

                function mostrarErrorInlineCantidad(inputEl, mensaje) {
                    try {
                        if (!inputEl) return;
                        // remover existente
                        const existing = inputEl.parentNode.querySelector('.inline-stock-error');
                        if (existing) existing.remove();
                        const div = document.createElement('div');
                        div.className = 'inline-stock-error';
                        div.style.color = '#7a1226';
                        div.style.background = '#fff0f0';
                        div.style.padding = '6px 8px';
                        div.style.marginTop = '6px';
                        div.style.borderRadius = '6px';
                        div.style.fontWeight = '700';
                        div.textContent = mensaje;
                        inputEl.parentNode.appendChild(div);
                        try { inputEl.focus(); } catch (e) {}
                        setTimeout(() => { try { div.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {} }, 50);
                    } catch (e) {}
                }

                function clearInlineErrorCantidad(inputEl) {
                    try { if (!inputEl) return; const ex = inputEl.parentNode.querySelector('.inline-stock-error'); if (ex) ex.remove(); } catch(e) {}
                }

        function upperizeContainer(selector) {
            try {
                const el = document.querySelector(selector);
                if (!el) return;
                upperizeTextNode(el);
            } catch (e) {}
        }

        function upperizeAllInventory() {
            // Main inventory containers and modals
            const selectors = [
                '#resumenTableBody',
                '#todosProductosBody',
                '#verProductoBody',
                '#editarProductoModal',
                '#todosProductosModal',
                '#entradaModal',
                '#salidaModal',
                '#editarProductoModal',
                '#todosProductosModal',
                '.resumen-container',
                '.inventario-container'
            ];
            selectors.forEach(s => upperizeContainer(s));

            // Also uppercase option texts in product selects
            document.querySelectorAll('select').forEach(sel => {
                sel.querySelectorAll('option').forEach(opt => {
                    try { opt.text = (opt.text || '').toUpperCase(); } catch(e){}
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            try { upperizeAllInventory(); } catch(e){}
            document.addEventListener('input', function(event) {
                const campo = event.target;
                if (!campo || !['INPUT', 'TEXTAREA'].includes(campo.tagName)) return;
                if (['number', 'date', 'time', 'month'].includes(campo.type)) return;
                const inicio = campo.selectionStart;
                campo.value = campo.value.toLocaleUpperCase('es-CO');
                if (inicio !== null && document.activeElement === campo) {
                    campo.setSelectionRange(inicio, inicio);
                }
            });
        });

        // If inventory is refreshed dynamically, run again on custom event
        document.addEventListener('inventory:refreshed', () => {
            try { upperizeAllInventory(); } catch(e){}
        });
    })();
</script>

<script>
    /* ===================================================================
       Funciones globales de formato de inventario
       =================================================================== */
    function numero(valor, porDefecto = 0) {
        const n = parseFloat(String(valor).replace(',', '.'));
        return Number.isFinite(n) ? n : porDefecto;
    }

    function formatoCantidad(valor) {
        const n = Math.round(numero(valor, 0) * 1000) / 1000;
        return n.toFixed(3);
    }

    function esCategoriaGramosInventario(nombre) {
        const normalizado = String(nombre || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim()
            .replace(/\s+/g, ' ');
        return ['frutas', 'verduras', 'carnicos y refrigerados'].includes(normalizado);
    }

    function formatoStockVisible(valor, categoria = '', ventaPorKilo = false) {
        const n = Math.round(numero(valor, 0) * 1000) / 1000;
        const esPorGramos = ventaPorKilo || esCategoriaGramosInventario(categoria);
        if (esPorGramos) {
            return n.toLocaleString('es-CO', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        }
        return String(n);
    }

    function formatoStockTotalVisible(valor) {
        const n = Math.round(numero(valor, 0) * 1000) / 1000;
        return Number.isInteger(n)
            ? String(n)
            : n.toLocaleString('es-CO', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
    }

    window.formatoCantidad = formatoCantidad;
    window.formatoStockVisible = formatoStockVisible;
    window.formatoStockTotalVisible = formatoStockTotalVisible;
</script>

<script>
    /* ===================================================================
       Presentaciones (UNIDAD / PAQUETE / CAJA) en Entradas y Salidas
       =================================================================== */
    (function () {
        const cachePresentaciones = new Map();
        const estado = {
            entrada: { productoId: 0, datos: null },
            salida: { productoId: 0, datos: null }
        };

        function urlControlador() {
            if (typeof inventarioControllerUrl === 'string' && inventarioControllerUrl) return inventarioControllerUrl;
            return (typeof base_url !== 'undefined' ? base_url : '') + '/Controllers/InventarioController.php';
        }

        async function obtenerPresentaciones(productoId) {
            const id = parseInt(productoId, 10) || 0;
            if (id <= 0) return null;
            if (cachePresentaciones.has(id)) return cachePresentaciones.get(id);
            try {
                const respuesta = await fetch(`${urlControlador()}?action=obtenerPresentaciones&producto_id=${id}`);
                const datos = await respuesta.json();
                const resultado = datos && datos.success
                    ? {
                        maneja_presentaciones: datos.maneja_presentaciones === true || Number(datos.maneja_presentaciones || 0) === 1,
                        presentaciones: Array.isArray(datos.presentaciones) ? datos.presentaciones : []
                    }
                    : null;
                cachePresentaciones.set(id, resultado);
                return resultado;
            } catch (error) {
                console.warn('No se pudieron cargar las presentaciones del producto', error);
                return null;
            }
        }

        function limpiarCachePresentaciones() {
            cachePresentaciones.clear();
        }

        function opcionesFactorPresentacion(valor) {
            const estandares = [2, 3, 4, 6, 8, 10, 12, 15, 20, 24, 30, 36, 48, 50, 60, 100];
            const factor = Math.max(2, Math.round(numero(valor, 12)));
            if (!estandares.includes(factor)) estandares.push(factor);
            return estandares.sort((a, b) => a - b);
        }

        let conservarPreciosPresentacionEntrada = false;

        function pintarEditorPresentacionesEntrada(presentaciones) {
            const editor = document.getElementById('editorPresentacionesEntrada');
            const filas = document.getElementById('filasPresentacionesEntrada');
            if (!editor || !filas) return;
            const datos = Array.isArray(presentaciones) && presentaciones.length
                ? presentaciones.slice(0, 2)
                : [{ nombre: 'UNIDAD', factor_padre: 1 }, { nombre: 'PAQUETE', factor_padre: 12 }];
            while (datos.length < 2) datos.push({ nombre: 'PAQUETE', factor_padre: 12 });
            filas.innerHTML = datos.map((pres, indice) => {
                const base = indice === 0;
                const opciones = opcionesFactorPresentacion(pres.factor_padre).map(factor =>
                    `<option value="${factor}" ${Number(pres.factor_padre) === factor ? 'selected' : ''}>${factor}</option>`
                ).join('');
                return `<div class="fila-presentacion-entrada" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;align-items:end;min-width:0;">
                    <div><small style="display:block;color:#667085;font-weight:700;">${base ? 'NOMBRE BASE' : 'NOMBRE'}</small><input type="text" class="entrada-pres-nombre" value="${escapeHtmlInventario(String(pres.nombre || (base ? 'UNIDAD' : 'PAQUETE')).toUpperCase())}" ${base ? 'readonly' : ''} style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe4ec;border-radius:6px;text-transform:uppercase;"></div>
                    <div><small style="display:block;color:#667085;font-weight:700;">${base ? 'EQUIVALENCIA' : 'CONTIENE'}</small>${base ? '<input class="entrada-pres-factor" value="1" readonly style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe4ec;border-radius:6px;background:#f1f5f9;">' : `<select class="entrada-pres-factor" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe4ec;border-radius:6px;">${opciones}</select>`}</div>
                    <div><small style="display:block;color:#667085;font-weight:700;">PRECIO COMPRA</small><input type="number" class="entrada-pres-compra" min="0" step="0.01" value="${conservarPreciosPresentacionEntrada && numero(pres.precio_compra, 0) > 0 ? numero(pres.precio_compra, 0).toFixed(2) : ''}" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe4ec;border-radius:6px;"></div>
                    <div><small style="display:block;color:#667085;font-weight:700;">PRECIO VENTA</small><input type="number" class="entrada-pres-venta" min="0" step="0.01" value="${conservarPreciosPresentacionEntrada && numero(pres.precio_venta, 0) > 0 ? numero(pres.precio_venta, 0).toFixed(2) : ''}" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe4ec;border-radius:6px;"></div>
                </div>`;
            }).join('');
            editor.style.display = 'block';
            sincronizarPrecioPresentacionUnidad(
                numero(document.getElementById('precioCompra')?.value, 0),
                numero(document.getElementById('precioVentaMostrado')?.value, 0)
            );
        }

        async function guardarPresentacionesEntrada() {
            const productoId = parseInt(document.getElementById('productoEntrada')?.value || '0', 10) || 0;
            const filas = Array.from(document.querySelectorAll('#filasPresentacionesEntrada .fila-presentacion-entrada'));
            if (productoId <= 0 || filas.length < 2) {
                Swal.fire({ icon: 'warning', title: 'PRESENTACIONES', text: 'Selecciona un producto y define UNIDAD y PAQUETE.' });
                return;
            }
            const presentaciones = filas.slice(0, 2).map((fila, indice) => ({
                nombre: String(fila.querySelector('.entrada-pres-nombre')?.value || '').trim().toUpperCase(),
                factor_padre: indice === 0 ? 1 : numero(fila.querySelector('.entrada-pres-factor')?.value, 12),
                precio_compra: numero(fila.querySelector('.entrada-pres-compra')?.value, 0),
                precio_venta: numero(fila.querySelector('.entrada-pres-venta')?.value, 0)
            }));
            const body = new FormData();
            body.append('action', 'guardarPresentaciones');
            body.append('producto_id', String(productoId));
            body.append('maneja_presentaciones', '1');
            body.append('presentaciones', JSON.stringify(presentaciones));
            try {
                const respuesta = await fetch(urlControlador(), { method: 'POST', body });
                const resultado = await respuesta.json();
                if (!resultado.success) throw new Error(resultado.message || 'No se pudieron guardar las presentaciones');
                limpiarCachePresentaciones();
                conservarPreciosPresentacionEntrada = true;
                await sincronizar('entrada');
                Swal.fire({ icon: 'success', title: 'PRESENTACIONES GUARDADAS', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'ERROR', text: error.message || 'No se pudieron guardar las presentaciones' });
            }
        }
        window.guardarPresentacionesEntrada = guardarPresentacionesEntrada;

        function pintarSelector(modo, datos) {
            const grupo = document.getElementById(modo === 'entrada' ? 'grupoPresentacionEntrada' : 'grupoPresentacionSalida');
            const select = document.getElementById(modo === 'entrada' ? 'presentacionEntrada' : 'presentacionSalida');
            const ayuda = document.getElementById(modo === 'entrada' ? 'ayudaPresentacionEntrada' : 'ayudaPresentacionSalida');
            const productoSeleccionado = document.getElementById('productoEntrada')?.value;
            const presentacionesActivas = Boolean(datos?.maneja_presentaciones);
            if (modo === 'entrada') {
                if (productoSeleccionado && presentacionesActivas) {
                    pintarEditorPresentacionesEntrada(datos?.presentaciones || []);
                } else {
                    const editor = document.getElementById('editorPresentacionesEntrada');
                    if (editor) editor.style.display = 'none';
                    const filas = document.getElementById('filasPresentacionesEntrada');
                    if (filas) filas.innerHTML = '';
                    conservarPreciosPresentacionEntrada = false;
                }
            }
            if (!grupo || !select) return;

            if (!presentacionesActivas || !datos.presentaciones.length) {
                grupo.style.display = 'none';
                select.innerHTML = '';
                select.value = '';
                if (ayuda) ayuda.textContent = '';
                return;
            }

            select.innerHTML = '';
            const productoSelect = document.getElementById(modo === 'entrada' ? 'productoEntrada' : 'productoSalida');
            const productoEsKilo = Number(productoSelect?.selectedOptions?.[0]?.dataset?.ventaPorKilo || 0) === 1;
            const stockBase = numero(datos.total_base, 0);
            datos.presentaciones.forEach((pres) => {
                const opcion = document.createElement('option');
                opcion.value = String(pres.id);
                const factor = numero(pres.factor_base, 1) || 1;
                const disponibleBase = stockBase > 0 ? stockBase : numero(pres.cantidad, 0);
                const disponible = productoEsKilo
                    ? numero(factor > 1 ? disponibleBase / factor : disponibleBase, 0).toFixed(3)
                    : String(Math.floor(factor > 1 ? disponibleBase / factor : disponibleBase));
                opcion.textContent = `${String(pres.nombre || '').toUpperCase()} (DISPONIBLES: ${disponible})`;
                opcion.dataset.nombre = String(pres.nombre || '').toUpperCase();
                opcion.dataset.factor = String(numero(pres.factor_base, 1) || 1);
                opcion.dataset.precioVenta = String(numero(pres.precio_venta, 0));
                opcion.dataset.precioCompra = String(numero(pres.precio_compra, 0));
                opcion.dataset.cantidad = String(factor > 1 ? Math.floor(disponibleBase / factor) : disponibleBase);
                select.appendChild(opcion);
            });

            // Por defecto la presentación más pequeña (UNIDAD).
            select.selectedIndex = 0;
            grupo.style.display = modo === 'entrada' ? 'flex' : 'none';
            aplicarSeleccion(modo);
        }

        function seleccionActual(modo) {
            const select = document.getElementById(modo === 'entrada' ? 'presentacionEntrada' : 'presentacionSalida');
            const grupo = document.getElementById(modo === 'entrada' ? 'grupoPresentacionEntrada' : 'grupoPresentacionSalida');
            if (!select || !grupo || grupo.style.display === 'none' || !select.value) return null;
            const opcion = select.options[select.selectedIndex];
            if (!opcion) return null;
            return {
                id: parseInt(select.value, 10) || 0,
                nombre: opcion.dataset.nombre || '',
                factor: numero(opcion.dataset.factor, 1) || 1,
                precio_venta: numero(opcion.dataset.precioVenta, 0),
                precio_compra: numero(opcion.dataset.precioCompra, 0),
                cantidad: numero(opcion.dataset.cantidad, 0)
            };
        }

        function aplicarSeleccion(modo) {
            const seleccion = seleccionActual(modo);
            if (modo === 'entrada') {
                const etiqueta = document.getElementById('unidadEntradaLabel');
                const ayuda = document.getElementById('ayudaPresentacionEntrada');
                if (etiqueta) etiqueta.textContent = seleccion ? `CANTIDAD DE ${seleccion.nombre}` : 'CANTIDAD';
                if (ayuda) {
                    ayuda.textContent = seleccion
                        ? `1 ${seleccion.nombre} = ${formatoCantidad(seleccion.factor)} UNIDAD(ES) BASE`
                        : '';
                }
                return;
            }

            const etiqueta = document.getElementById('unidadSalidaLabel');
            const ayuda = document.getElementById('ayudaPresentacionSalida');
            if (etiqueta && seleccion) etiqueta.textContent = `CANTIDAD DE ${seleccion.nombre}`;
            if (ayuda) {
                ayuda.textContent = seleccion
                    ? `1 ${seleccion.nombre} = ${formatoCantidad(seleccion.factor)} UNIDAD(ES) · DISPONIBLES: ${formatoCantidad(seleccion.cantidad)}`
                    : '';
            }
        }

        async function sincronizar(modo) {
            const select = document.getElementById(modo === 'entrada' ? 'productoEntrada' : 'productoSalida');
            const productoId = parseInt(select?.value || '0', 10) || 0;
            if (estado[modo].productoId === productoId && estado[modo].datos !== undefined) {
                // Puede haber cambiado el stock: se vuelve a consultar igualmente.
            }
            estado[modo].productoId = productoId;
            if (productoId <= 0) {
                estado[modo].datos = null;
                pintarSelector(modo, null);
                return;
            }
            const datos = await obtenerPresentaciones(productoId);
            if ((parseInt(select?.value || '0', 10) || 0) !== productoId) return;
            estado[modo].datos = datos;
            pintarSelector(modo, datos);
        }

        // Expuesto para el envío de la entrada.
        window.presentacionSeleccionadaEntrada = function () {
            return seleccionActual('entrada');
        };

        // El carrito de salida inicia siempre en UNIDAD y cambia de presentación dentro del carrito.
        if (typeof window.productoSalidaSeleccionado === 'function') {
            const original = window.productoSalidaSeleccionado;
            window.productoSalidaSeleccionado = function () {
                const producto = original.apply(this, arguments);
                if (!producto || !producto.producto_id) return producto;
                const presentaciones = Array.isArray(estado.salida.datos?.presentaciones)
                    ? estado.salida.datos.presentaciones
                    : [];
                if (!presentaciones.length) return producto;
                const seleccion = presentaciones[0];

                const factor = Number(seleccion.factor_base || 1) > 0 ? Number(seleccion.factor_base) : 1;
                const stockBase = numero(producto.stock, 0);
                const disponibles = factor > 1 ? Math.floor(stockBase / factor) : stockBase;
                const precio = seleccion.precio_venta > 0
                    ? seleccion.precio_venta
                    : numero(producto.precio, 0) * factor;

                producto.presentacion_id = seleccion.id;
                producto.presentacion_nombre = seleccion.nombre;
                producto.presentacion_factor = factor;
                producto.presentaciones = presentaciones;
                producto.precio_producto = numero(producto.precio, 0);
                producto.cantidad = 1;
                producto.stock = disponibles;
                producto.stock_base = stockBase;
                producto.precio = precio;
                producto.precio_original = precio;
                producto.precio_venta = precio;
                producto.descuento_porcentaje = 0;
                producto.venta_por_kilo = producto.venta_por_kilo === true || Number(producto.venta_por_kilo) === 1;
                if (factor > 1 || String(seleccion.nombre || '').length) {
                    producto.nombre = `${producto.nombre} · ${seleccion.nombre}`;
                }
                return producto;
            };
        }

        document.addEventListener('change', (evento) => {
            const id = evento.target?.id;
            if (id === 'productoEntrada') sincronizar('entrada');
            if (id === 'productoSalida') sincronizar('salida');
            if (id === 'presentacionEntrada') aplicarSeleccion('entrada');
            if (id === 'presentacionSalida') aplicarSeleccion('salida');
        });

        // El stock cambia tras cada movimiento: se refresca la información.
        document.addEventListener('inventory:refreshed', () => {
            limpiarCachePresentaciones();
            sincronizar('entrada');
            sincronizar('salida');
        });

        document.addEventListener('DOMContentLoaded', () => {
            sincronizar('entrada');
            sincronizar('salida');
        });
        if (document.readyState !== 'loading') {
            sincronizar('entrada');
            sincronizar('salida');
        }
    })();

    /* ============ FECHAS DE VENCIMIENTO EN LA ENTRADA ============ */
    (function () {
        let estadoActual = { requiere: false, categoria: '' };

        async function consultar(productoId) {
            try {
                const url = base_url + '/Controllers/InventarioController.php?action=requiereVencimiento&producto_id=' + encodeURIComponent(productoId);
                const respuesta = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const datos = await respuesta.json();
                return {
                    requiere: !!(datos && datos.success && datos.requiere),
                    categoria: String((datos && datos.categoria) || '')
                };
            } catch (error) {
                console.warn('No se pudo consultar el vencimiento del producto', error);
                return null;
            }
        }

        function pintar(info) {
            estadoActual = info;
            const campo = document.getElementById('fechaVencimiento');
            const grupo = campo ? campo.closest('.form-group') : null;
            if (!campo || !grupo) return;

            let etiqueta = grupo.querySelector('label');
            let aviso = document.getElementById('avisoVencimientoEntrada');
            if (!aviso) {
                aviso = document.createElement('small');
                aviso.id = 'avisoVencimientoEntrada';
                aviso.style.display = 'block';
                aviso.style.marginTop = '6px';
                aviso.style.fontWeight = '700';
                grupo.appendChild(aviso);
            }

            const hoy = new Date();
            hoy.setDate(hoy.getDate() + 1);
            campo.min = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;

            if (info.requiere) {
                campo.required = true;
                campo.disabled = false;
                campo.style.border = '2px solid #d93025';
                campo.style.background = '#fff';
                if (etiqueta) etiqueta.innerHTML = '<i class="fas fa-calendar-times"></i> FECHA VENCIMIENTO <span style="color:#d93025;">*</span>';
                aviso.textContent = '';
                aviso.style.display = 'none';
            } else {
                campo.required = false;
                campo.disabled = true;
                campo.value = '';
                campo.style.border = '';
                campo.style.background = '#eef1f4';
                if (etiqueta) etiqueta.innerHTML = '<i class="fas fa-calendar"></i> FECHA VENCIMIENTO';
                aviso.style.color = '#667085';
                aviso.style.fontWeight = '400';
                aviso.style.display = 'block';
                aviso.textContent = 'ESTE PRODUCTO NO VENCE. LA FECHA NO APLICA.';
            }
        }

        async function sincronizar() {
            const select = document.getElementById('productoEntrada');
            const productoId = parseInt(select?.value || '0', 10) || 0;
            if (productoId <= 0) {
                pintar({ requiere: false, categoria: '' });
                return;
            }
            const opcion = select?.options?.[select.selectedIndex];
            const infoLocal = {
                requiere: String(opcion?.dataset?.requiereVencimiento || '0') === '1',
                categoria: String(opcion?.dataset?.categoriaNombre || '')
            };
            pintar(infoLocal);
            const infoServidor = await consultar(productoId);
            if ((parseInt(select?.value || '0', 10) || 0) !== productoId) return;
            if (infoServidor) pintar(infoServidor);
        }

        // Validación antes de enviar la entrada.
        window.validarVencimientoEntrada = function () {
            if (!estadoActual.requiere) return true;
            const campo = document.getElementById('fechaVencimiento');
            const valor = (campo?.value || '').trim();
            if (!valor) return false;
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const fecha = new Date(valor + 'T00:00:00');
            if (isNaN(fecha.getTime()) || fecha <= hoy) {
                Swal.fire({
                    icon: 'warning',
                    title: 'FECHA NO VÁLIDA',
                    text: 'La fecha de vencimiento debe ser posterior al día de hoy.'
                });
                campo?.focus();
                return false;
            }
            return true;
        };

        document.addEventListener('change', (evento) => {
            if (evento.target?.id === 'productoEntrada') sincronizar();
        });
        document.addEventListener('DOMContentLoaded', sincronizar);
        if (document.readyState !== 'loading') sincronizar();
    })();
</script>

    <!-- FOOTER -->
    <footer style="display: flex; justify-content: center; align-items: center; gap: 16px; flex-wrap: wrap; text-align: center; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-top: 40px; color: #000;">
        <p style="margin: 0; display: inline-flex; align-items: center; justify-content: center; gap: 16px; flex-wrap: wrap; line-height: 1; font-size: 14px; font-weight: 700; color: #000;">
            <span style="display: inline-flex; align-items: center; justify-content: center; line-height: 1; font-size: 14px; font-weight: 700;">&copy; <?php echo date('Y'); ?></span>
            <span class="footer-wordmark" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; line-height: 1; font-size: 14px; font-weight: 700;">
                <img src="<?= htmlspecialchars(rtrim((string)base_url(), '/') . '/favicon.ico', ENT_QUOTES, 'UTF-8'); ?>" alt="Favicon" class="footer-brand-logo" style="width:18px; height:18px; object-fit: contain; display: inline-flex; vertical-align: middle;" onerror="this.style.display='none';">
                <span style="font-size: 14px; font-weight: 700; line-height: 1;">OWE COMPANY</span>
            </span>
            <span style="display: inline-flex; align-items: center; justify-content: center; line-height: 1; font-size: 14px; font-weight: 700;">TODOS LOS DERECHOS RESERVADOS</span>
        </p>
    </footer>

</body>
</html>





