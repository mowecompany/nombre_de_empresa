<?php
// cSpell:disable
// Si se recibió PHPSESSID como parámetro (desde iframe), usarlo para la sesión
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Permiso.php';
require_once ROOT_PATH . '/Models/Usuario.php';
require_once ROOT_PATH . '/Controllers/UsuarioController.php';
require_once ROOT_PATH . '/Helpers/Helpers.php';
require_once ROOT_PATH . '/Models/Rol.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si el rol está activo


// Verificar si tiene permiso para ver usuarios
$empresaContextoActivoUsuarios = (!empty($_SESSION['empresa_id']) || !empty($_SESSION['userData']['empresa_id'])) && empty($_SESSION['superadmin_modo_empresa']);
if (!PermisosHelper::esSuperAdminSesion() && !$empresaContextoActivoUsuarios && !PermisosHelper::tienePermiso('Usuarios', 'ver')) {
    header('Location: dashboard.php');
    exit();
}

$esSuperAdminSesion = PermisosHelper::esSuperAdminSesion();
$mostrarColumnaId = true;

// Detectar si estamos en iframe (cargado desde dashboard)
$esEnIframe = isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe' || 
              (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false);

// Obtener conexión
$db = Database::connect();

// Verificar la conexión
if (!$db) {
    error_log("Error: No se pudo establecer la conexión a la base de datos");
    die("Error de conexión a la base de datos");
}

$controller = new UsuarioController($db);
$usuarios = $controller->listarUsuarios();

$existeColumna = function(string $tabla, string $columna) use ($db): bool {
    try {
        if (strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            $tablaSegura = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
            $stmt = $db->prepare("PRAGMA table_info(\"{$tablaSegura}\")");
            $stmt->execute();
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnas as $col) {
                if (strcasecmp(trim((string)($col['name'] ?? '')), $columna) === 0) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna");
        $stmt->execute([
            ':tabla' => $tabla,
            ':columna' => $columna,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
};

$tieneImagenUsuario = $existeColumna('usuarios', 'imagen');
$tieneImagenEmpresa = $existeColumna('empresas', 'imagen');
$mostrarColumnaImagen = $tieneImagenUsuario || $tieneImagenEmpresa;

$normalizarImagenVista = function ($ruta) {
    $valor = trim((string)($ruta ?? ''));
    if ($valor === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $valor) || str_starts_with($valor, 'data:') || str_starts_with($valor, '/')) {
        return $valor;
    }

    if (preg_match('/^[A-Za-z]:\\\\/', $valor)) {
        $raiz = str_replace('\\\\', '/', str_replace('\\', '/', ROOT_PATH));
        $normalizada = str_replace('\\\\', '/', str_replace('\\', '/', $valor));
        if (str_starts_with(strtolower($normalizada), strtolower($raiz))) {
            $relativa = ltrim(substr($normalizada, strlen($raiz)), '/');
            return '../' . $relativa;
        }
        return '';
    }

    return '../' . ltrim(str_replace('\\', '/', $valor), '/');
};

// Colores de la empresa (para aplicar tema en esta vista)
// Paleta consistente con todo el sistema
$coloresEmpresaDefault = [
    // Identidad visual base
    'color_principal' => '#3591CA',
    'color_secundario' => '#3591CA',
    'color_menu_lateral' => '#3591CA',
    'color_botones' => '#3591CA',
    'color_fondo' => '#F4F6F8',
    'color_texto' => '#3591CA',
    'color_bordes' => '#D8DFE5',
    // Navegacion
    'color_navbar' => '#3591CA',
    'color_iconos_menu' => '#FFFFFF',
    'color_hover_menu' => '#3D6175',
    // Contenido
    'color_titulos' => '#3591CA',
    'color_links' => '#3591CA',
    // Tablas
    'color_fondo_tabla' => '#FFFFFF',
    'color_texto_tabla' => '#3591CA',
    'color_encabezado_tabla' => '#3591CA',
    'color_filas_alternas' => '#F4F6F8',
    // Botones especificos
    'color_btn_crear' => '#3591CA',
    'color_btn_editar' => '#3591CA',
    'color_btn_eliminar' => '#DC3545',
    // Formularios
    'color_focus_inputs' => '#3591CA',
];

$coloresEmpresa = $coloresEmpresaDefault;
if (isset($_SESSION['colores_empresa']) && is_array($_SESSION['colores_empresa'])) {
    foreach ($coloresEmpresa as $clave => $defecto) {
        $valor = trim((string)($_SESSION['colores_empresa'][$clave] ?? $defecto));
        if ($valor !== '' && $valor[0] !== '#') {
            $valor = "#" . ltrim($valor, '#');
        }
        $coloresEmpresa[$clave] = $valor;
    }
}

$empresaIdSesion = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
if ($empresaIdSesion > 0) {
    try {
        $stmtTablaColores = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'colores_empresa'");
        $existeTablaColores = false;
        if (strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            $stmtTablaColores->execute();
            $existeTablaColores = (int)$stmtTablaColores->fetchColumn() > 0;
        } else {
            $stmtTablaColores = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colores_empresa'");
            $stmtTablaColores->execute();
            $existeTablaColores = (int)$stmtTablaColores->fetchColumn() > 0;
        }

        if ($existeTablaColores) {
            $columnasDisponibles = [];
            foreach (array_keys($coloresEmpresaDefault) as $columnaColor) {
                if ($existeColumna('colores_empresa', $columnaColor)) {
                    $columnasDisponibles[] = $columnaColor;
                }
            }

            if (!empty($columnasDisponibles)) {
                $sqlColores = "SELECT " . implode(', ', $columnasDisponibles) . " FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1";
                $stmtColores = $db->prepare($sqlColores);
                $stmtColores->bindValue(':empresa_id', $empresaIdSesion, PDO::PARAM_INT);
                $stmtColores->execute();
                $filaColores = $stmtColores->fetch(PDO::FETCH_ASSOC);

                if (is_array($filaColores) && !empty($filaColores)) {
                    foreach ($coloresEmpresa as $clave => $defecto) {
                        if (array_key_exists($clave, $filaColores)) {
                            $valor = trim((string)$filaColores[$clave]);
                            if ($valor !== '' && $valor[0] !== '#') {
                                $valor = '#' . ltrim($valor, '#');
                            }
                            $coloresEmpresa[$clave] = $valor !== '' ? $valor : $defecto;
                        }
                    }
                    $_SESSION['colores_empresa'] = $coloresEmpresa;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Error al cargar colores de empresa en usuarios.php: ' . $e->getMessage());
    }
}

// Logs para depuración
error_log("Tipo de \$usuarios: " . gettype($usuarios));
error_log("Contenido de \$usuarios: " . print_r($usuarios, true));

// Al inicio del archivo, después del include
error_reporting(E_ALL);
ini_set('display_errors', 1); // Desactivar la visualización de errores en el navegador

// Obtener los roles de la base de datos
$rolModel = new Rol($controller->db);
$roles = $rolModel->obtenerRoles();
error_log("Roles obtenidos: " . print_r($roles, true));

$tiposEmpresa = [];
try {
    if (function_exists('dbTableExists') && dbTableExists($db, 'tipos_empresa')) {
        $tiposTieneEstado = $existeColumna('tipos_empresa', 'estado');

        $stmtTiposEmpresa = $db->prepare("SELECT id, nombre FROM tipos_empresa" . ($tiposTieneEstado ? " WHERE estado = 1" : "") . " ORDER BY nombre ASC");
        $stmtTiposEmpresa->execute();
        $tiposEmpresa = $stmtTiposEmpresa->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log('Tabla tipos_empresa no existe en usuarios.php, se omite carga de tipos de empresa');
    }
} catch (Throwable $e) {
    error_log('Error al cargar empresas en usuarios.php: ' . $e->getMessage());
}

// Verificar si es una petición AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Determinar si es una petición JSON o FormData
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $jsonData = file_get_contents('php://input');
            $data = json_decode($jsonData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
            }
        } else {
            $data = $_POST;
        }

        if (!isset($data['action'])) {
            throw new Exception('No se especificó una acción');
        }

        switch ($data['action']) {
            case 'get':
                if (!isset($data['id'])) {
                    throw new Exception('ID de usuario no especificado');
                }
                $usuario = $controller->obtenerUsuarioPorId($data['id']);
                echo json_encode($usuario);
                break;

            case 'update':
                $resultado = $controller->actualizarUsuario($data);
                echo json_encode($resultado);
                break;

            case 'crear':
                $resultado = $controller->registrarUsuario($data);
                echo json_encode($resultado);
                break;

            case 'delete':
                if (!isset($data['id'])) {
                    throw new Exception('ID de usuario no especificado');
                }
                $resultado = $controller->eliminarUsuario($data['id']);
                echo json_encode($resultado);
                break;

            case 'cambiar_contrasena':
                $usuarioId = (int)(trim((string)($data['usuario_id'] ?? '')));
                $contrasenaActual = trim((string)($data['contrasena_actual'] ?? $data['contrasenaa_actual'] ?? $data['password_actual'] ?? ''));
                $contrasenaNueva = trim((string)($data['contrasena_nueva'] ?? ''));

                if ($usuarioId <= 0 || empty($contrasenaNueva)) {
                    throw new Exception('Datos incompletos');
                }

                $resultado = $controller->cambiarContrasena($usuarioId, $contrasenaActual, $contrasenaNueva);
                echo json_encode($resultado);
                break;

            case 'reiniciar':
                $resultado = $controller->reiniciarUsuarios();
                echo json_encode($resultado);
                break;
            case 'deshacer':
                $resultado = $controller->deshacerUsuarios();
                echo json_encode($resultado);
                break;

            default:
                error_log('DEBUG: Acción no válida - Acción recibida: ' . var_export($data['action'] ?? 'NADA', true) . ', Datos completos: ' . var_export($data, true));
                throw new Exception('Acción no válida: ' . ($data['action'] ?? 'sin acción'));
        }
    } catch (Exception $e) {
        error_log('Error en usuarios.php: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Si no es una petición AJAX y es POST, redirigir
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Location: usuarios.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-blue: <?= htmlspecialchars($coloresEmpresa['color_principal']); ?>;
            --secondary-blue: <?= htmlspecialchars($coloresEmpresa['color_secundario']); ?>;
            --bg: <?= htmlspecialchars($coloresEmpresa['color_fondo']); ?>;
            --text: <?= htmlspecialchars($coloresEmpresa['color_texto']); ?>;
            --border: <?= htmlspecialchars($coloresEmpresa['color_texto']); ?>;
            --button: <?= htmlspecialchars($coloresEmpresa['color_botones']); ?>;
            --navbar: <?= htmlspecialchars($coloresEmpresa['color_navbar']); ?>;
            --title-color: <?= htmlspecialchars($coloresEmpresa['color_titulos']); ?>;
            --table-bg: <?= htmlspecialchars($coloresEmpresa['color_fondo_tabla']); ?>;
            --table-text: <?= htmlspecialchars($coloresEmpresa['color_texto_tabla']); ?>;
            --table-head: <?= htmlspecialchars($coloresEmpresa['color_encabezado_tabla']); ?>;
            --table-zebra: <?= htmlspecialchars($coloresEmpresa['color_filas_alternas']); ?>;
            --btn-create: <?= htmlspecialchars($coloresEmpresa['color_btn_crear']); ?>;
            --btn-edit: <?= htmlspecialchars($coloresEmpresa['color_btn_editar']); ?>;
            --btn-delete: <?= htmlspecialchars($coloresEmpresa['color_btn_eliminar']); ?>;
            --focus-input: <?= htmlspecialchars($coloresEmpresa['color_focus_inputs']); ?>;
            --white: #FFFFFF;
            --black: #000000;
            --light-blue: rgba(47, 74, 90, 0.1);
            --font-saira: 'Saira Condensed', sans-serif;
        }

        /* Texto base del tema (sin pisar colores específicos de títulos/tablas) */
        html, body, button, input, select, textarea, label, p, span, a {
            color: var(--text);
        }

        body {
            padding-top: <?php echo $esEnIframe ? '0' : '80px'; ?>;
            background-color: var(--bg);
            color: var(--text);
            font-family: var(--font-saira);
            text-transform: uppercase;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .title_equipo {
            background: transparent;
            padding: 0.5rem 0;
            margin-bottom: 2rem;
            position: relative;
            margin-top: <?php echo $esEnIframe ? '0' : '-80px'; ?>;
            padding-top: <?php echo $esEnIframe ? '0.2rem' : 'calc(80px + 0.2rem)'; ?>;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: <?php echo $esEnIframe ? '100px' : '150px'; ?>;
        }
        .title_equipo h1 {
            color: var(--title-color);
            font-family: var(--font-saira);
            margin-top: <?php echo $esEnIframe ? '0' : '-80px'; ?>;
            font-size: <?php echo $esEnIframe ? '2.5rem' : '4rem'; ?>;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .title_equipo h1 i {
            color: var(--title-color);
            margin-right: 15px;
        }

        .container {
            width: 100%;
            max-width: 1600px;
            padding: 0 15px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .main-scroll-panel {
            max-height: none;
            overflow: visible;
            padding-right: 0;
        }

        .main-scroll-panel::-webkit-scrollbar {
            width: 8px;
        }

        .main-scroll-panel::-webkit-scrollbar-track {
            background: var(--bg);
            border-radius: 8px;
        }

        .main-scroll-panel::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 8px;
            border: 1px solid var(--bg);
        }

        .main-scroll-panel::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-blue);
        }

                * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .estadistica-card {
            background: var(--white);
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.08), 0 0 0 1px rgba(47, 74, 90, 0.04);
            padding: 20px;
            margin: 20px auto;
            width: 100%;
            max-height: calc(100vh - 280px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .table-wrapper {
            overflow-x: auto;
            overflow-y: scroll;
            flex: 1;
            scrollbar-width: thin;
            min-height: 0;
            height: 100%;
            position: relative;
            max-width: 100%;
        }

        table {
            width: 100%;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            box-sizing: border-box;
            text-transform: uppercase;
            table-layout: auto;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            table {
                min-width: 100%;
            }

            th, td {
                padding: 8px 6px;
                font-size: 0.8rem;
            }

            .estadistica-card {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            th, td {
                padding: 6px 4px;
                font-size: 0.7rem;
            }

            .btn-editar,
            .btn-eliminar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }
        
        thead {
            position: sticky;
            top: 0;
            z-index: 5;
        }

        th, td {
            padding: 14px 10px;
            vertical-align: middle;
            text-align: center;
            border-bottom: 1px solid #e6e9ee;
            font-size: 0.95rem;
            line-height: 1.4;
            word-break: break-word;
            white-space: normal;
            height: auto;
            max-height: 100px;
            overflow: hidden;
        }

        th {
            background: white;
            color: #2f4a5a;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 2px solid #2f4a5a;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(47, 74, 90, 0.08);
        }

        table {
            background: white;
        }

        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        tbody tr:hover {
            background-color: rgba(47, 74, 90, 0.04);
            transition: background-color 0.2s ease;
        }

        tbody td {
            color: var(--table-text);
        }

        th i, td i {
            margin-right: 5px;
            vertical-align: middle;
        }

        tr:hover {
            background-color: rgba(47, 74, 90, 0.04);
            transition: background-color 0.2s ease;
        }

        .button-primary {
            background: var(--btn-create);
            color: var(--white);
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
        }

        .button-primary:hover {
            background: var(--primary-blue);
        }

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
            align-items: flex-start;
            justify-content: center;
            padding: 18px 20px 22px;
        }
        
        .modal[style*="display: flex"] {
            display: flex !important;
        }

        .modal-content {
            background: var(--white);
            padding: 40px;
            border-radius: 14px;
            box-shadow: 0 12px 36px rgba(20, 30, 40, 0.15), 0 0 0 1px rgba(20, 30, 40, 0.05);
            max-width: 1000px;
            width: 90%;
            position: relative;
            text-transform: uppercase;
            animation: modalFadeIn 0.3s ease-out;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
            color: var(--text);
            z-index: 10;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: var(--white);
            background: var(--button);
            transform: rotate(90deg);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .section-title {
            margin: 4px 0 12px 0;
            color: var(--text);
            font-size: 15px;
            letter-spacing: 0.7px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
            font-weight: 700;
        }

        .form-group label {
            color: var(--text);
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--text);
            opacity: 0.8;
        }

        .form-group input,
        .form-group select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            width: 100%;
            font-size: 15px;
            text-transform: uppercase;
            background: var(--white);
            color: var(--text);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: var(--focus-input);
        }
        
        .form-group input::placeholder {
            text-transform: uppercase;
            opacity: 0.6;
            color: var(--border);
        }
        
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }
        
        .form-group select option {
            padding: 12px;
            background: var(--white);
            color: var(--text);
        }
        
        .form-group select option:disabled {
            color: var(--border);
        }

        button[type="submit"] {
            background: var(--button);
            color: var(--white);
            border: 2px solid var(--button);
            border-radius: 8px;
            padding: 14px 30px;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            letter-spacing: 0.5px;
        }

        button[type="submit"]:hover {
            background: var(--primary-blue);
            color: var(--white);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .estadistica-card {
                width: calc(100% - 20px);
                padding: 15px;
            }
            
            table {
                min-width: 900px;
            }

            th, td {
                padding: 10px 6px;
                font-size: 0.8rem;
            }
            
            .title_equipo h1 {
                font-size: 2.5rem;
            }
        }
        
        @media (min-width: 1400px) {
            .container {
                max-width: 1800px;
            }
            
            .modal-content {
                max-width: 1100px;
            }
            
            th, td {
                padding: 16px 10px;
                font-size: 1rem;
            }
            
            .estadistica-card {
                padding: 30px;
            }
            
            table {
                min-width: 1300px;
            }
        }

        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-container input {
            width: 100%;
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            color: var(--border);
            font-size: 16px;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: var(--text);
        }

        .toggle-password:focus {
            outline: none;
        }
        
        #cambioContrasenaModal .modal-content {
            max-width: 450px;
            padding: 25px 30px;
            margin: auto;
        }
        
        #cambioContrasenaModal .modal-content h2 {
            margin-bottom: 25px;
            font-size: 17px;
            text-align: center;
        }
        
        #cambioContrasenaModal .form-row {
            grid-template-columns: 1fr;
            gap: 18px;
            margin-bottom: 0;
        }
        
        #cambioContrasenaModal .form-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 18px;
        }
        
        #cambioContrasenaModal .form-group label {
            margin-bottom: 10px;
            font-size: 12px;
            width: 100%;
            text-align: center;
        }
        
        #cambioContrasenaModal .form-group input {
            padding: 11px 13px;
            font-size: 13px;
            width: 100%;
            max-width: 320px;
        }
        
        #cambioContrasenaModal .password-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
            width: 100%;
        }
        
        #cambioContrasenaModal .password-field label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.5px;
        }
        
        #cambioContrasenaModal .password-field label i {
            color: var(--text);
            opacity: 0.7;
        }
        
        #cambioContrasenaModal .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 360px;
            margin: 0 auto;
        }
        
        #cambioContrasenaModal .password-input-wrapper input {
            width: 100%;
            padding: 14px 40px 14px 16px;
            font-size: 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text);
            text-transform: uppercase;
        }
        
        #cambioContrasenaModal .password-input-wrapper input:focus {
            outline: none;
            border-color: var(--focus-input);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
        }
        
        #cambioContrasenaModal .password-input-wrapper input::placeholder {
            text-transform: uppercase;
            opacity: 0.6;
            color: var(--border);
        }
        
        #cambioContrasenaModal .toggle-password {
            position: absolute;
            right: 16px;
            font-size: 15px;
            cursor: pointer;
            color: var(--border);
            transition: color 0.2s ease;
        }
        
        #cambioContrasenaModal .toggle-password:hover {
            color: var(--text);
        }
        
        #cambioContrasenaModal button[type="submit"] {
            width: 320px;
            padding: 11px 20px;
            font-size: 13px;
            margin-top: 15px;
        }
        }

        h2 {
            margin: 0 0 20px;
            font-size: 24px;
            color: var(--text);
            text-align: center;
        }

        .modal-content h2 {
            background: transparent;
            color: var(--text);
            margin: -40px -40px 30px -40px;
            padding: 25px 30px;
            border-radius: 14px 14px 0 0;
            border-bottom: 2px solid var(--border);
            font-size: 22px;
            letter-spacing: 1px;
        }

        #error-message {
            padding: 14px 20px;
            margin: 0 0 20px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #error-message.success {
            background-color: rgba(212, 237, 218, 0.7);
            border: 1px solid rgba(195, 230, 203, 0.9);
            color: var(--text);
        }

        #error-message.error {
            background-color: rgba(248, 215, 218, 0.7);
            border: 1px solid rgba(245, 198, 203, 0.9);
            color: var(--text);
        }

        .button-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
            min-width: 0;
            width: 100%;
        }

        .button-edit-delete {
            width: 34px;
            height: 34px;
            padding: 0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            flex: 0 0 auto;
        }

        .button-edit-delete i {
            margin-right: 0;
            font-size: 12px;
            line-height: 1;
        }

        .user-avatar-thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            object-position: center;
            border-radius: 8px;
            border: 1px solid #dde6ee;
            box-shadow: 0 1px 2px rgba(47, 74, 90, 0.08);
            image-rendering: auto;
            display: block;
            margin: 0 auto;
            background: #ffffff;
            padding: 2px;
        }

        .user-avatar-fallback {
            display: inline-flex;
            width: 28px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border: 1px dashed var(--border);
            border-radius: 8px;
            color: var(--text);
            background: #f7fafc;
            margin: 0 auto;
            font-size: 10px;
        }

        .button-primary.button-edit-delete {
            background: var(--btn-edit);
            color: var(--white);
        }
        
        .button-primary.button-edit-delete:hover {
            background: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
            transform: translateY(-2px);
        }

        .button-delete.button-edit-delete {
            background: var(--btn-delete);
            color: var(--white);
        }
        
        .button-delete.button-edit-delete:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }

        .button-delete {
            background: var(--btn-delete);
            color: var(--white);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            border-radius: 6px;
            padding: 8px 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.2);
        }

        .button-delete:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }

        .button-secondary {
            background: #6c757d;
            color: #ffffff;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 10px 18px;
            transition: all 0.3s ease;
            font-weight: 700;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        }

        .button-secondary:hover {
            background: #5a6268;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
            transform: translateY(-1px);
        }

        .button-secondary:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .button-delete:active {
            transform: translateY(0);
        }

        .swal-popup {
            background: white;
            border-radius: 14px;
            box-shadow: 0 12px 36px rgba(47, 74, 90, 0.15);
            padding: 24px;
            text-align: center;
        }
        
        .swal2-popup {
            border-radius: 14px !important;
            box-shadow: 0 12px 36px rgba(47, 74, 90, 0.15) !important;
        }
        
        .swal2-title {
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: var(--text) !important;
        }
        
        .swal-wide {
            width: 500px !important;
            max-width: 90% !important;
        }
        
        .swal2-html-container {
            line-height: 1.6 !important;
        }

        <?php if ($mostrarColumnaId): ?>
        th:nth-child(1), td:nth-child(1) { width: 4%; min-width: 40px; }
        th:nth-child(2), td:nth-child(2) { width: 12%; min-width: 100px; }
        th:nth-child(3), td:nth-child(3) { width: 12%; min-width: 100px; }
        th:nth-child(4), td:nth-child(4) { 
            width: 18%; 
            min-width: 150px;
            word-break: break-word;
            white-space: normal;
        }
        th:nth-child(5), td:nth-child(5) { 
            width: 9%; 
            min-width: 90px;
            white-space: nowrap;
        }
        th:nth-child(6), td:nth-child(6) { width: 13%; min-width: 120px; }
        th:nth-child(7), td:nth-child(7) { width: 10%; min-width: 90px; }
        th:nth-child(8), td:nth-child(8) { 
            width: 10%; 
            min-width: 84px;
            padding: 12px 6px;
        }
        th:nth-child(9), td:nth-child(9) { width: 12%; min-width: 132px; }
        <?php else: ?>
        th:nth-child(1), td:nth-child(1) { width: 12%; min-width: 100px; }
        th:nth-child(2), td:nth-child(2) { width: 12%; min-width: 100px; }
        th:nth-child(3), td:nth-child(3) { 
            width: 18%; 
            min-width: 150px;
            word-break: break-word;
            white-space: normal;
        }
        th:nth-child(4), td:nth-child(4) { 
            width: 10%; 
            min-width: 90px;
            white-space: nowrap;
        }
        th:nth-child(5), td:nth-child(5) { width: 14%; min-width: 120px; }
        th:nth-child(6), td:nth-child(6) { width: 12%; min-width: 100px; }
        th:nth-child(7), td:nth-child(7) { width: 10%; min-width: 84px; }
        th:nth-child(8), td:nth-child(8) { width: 12%; min-width: 132px; }
        <?php endif; ?>

        th.col-imagen,
        td.col-imagen {
            width: 54px;
            min-width: 54px;
            max-width: 54px;
            padding-left: 4px;
            padding-right: 4px;
        }

        th.col-acciones,
        td.col-acciones {
            width: 148px;
            min-width: 148px;
            max-width: 148px;
            padding-left: 6px;
            padding-right: 6px;
        }

        .roles-table td:hover::after {
            content: attr(title);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: -30px;
            z-index: 1000;
            background: var(--primary-blue);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 6px;
            white-space: nowrap;
            font-size: 11px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            letter-spacing: 0.3px;
            font-weight: 500;
            animation: tooltipFadeIn 0.2s ease-out;
        }

        .roles-table td:hover::before {
            content: '';
            position: absolute;
            left: 50%;
            top: -8px;
            transform: translateX(-50%);
            border-width: 4px;
            border-style: solid;
            border-color: rgba(47, 74, 90, 0.95) transparent transparent transparent;
        }

        .btn-nuevo {
            width: auto;
            min-width: 220px;
            margin: 20px;
            background: var(--btn-create);
            color: var(--white);
            padding: 14px 28px;
            border: 2px solid var(--btn-create);
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            font-weight: 700;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .btn-nuevo i {
            font-size: 16px;
        }

        .btn-nuevo:hover {
            background: var(--primary-blue);
            color: var(--white);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-nuevo:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        .btn-nuevo-reset,
        .btn-nuevo-undo {
            min-width: auto !important;
            width: auto !important;
            margin: 0 !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            gap: 8px !important;
            background: #3b82f6 !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12) !important;
        }

        .btn-nuevo-reset i,
        .btn-nuevo-undo i {
            color: #ffffff !important;
        }

        .btn-nuevo-reset:hover,
        .btn-nuevo-undo:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        .btn-nuevo-undo {
            background: #64748b !important;
            border-color: #64748b !important;
        }

        .btn-editar,
        .btn-eliminar {
            width: 36px;
            height: 36px;
            padding: 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-editar {
            background: var(--btn-edit);
            color: var(--white);
        }
        
        .btn-editar:hover {
            background: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
            transform: translateY(-2px);
        }

        .btn-eliminar {
            background: var(--btn-delete);
            color: var(--white);
        }
        
        .btn-eliminar:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-editar:active,
        .btn-eliminar:active {
            transform: translateY(0);
        }

        .estado-activo {
            color: var(--text);
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(47, 74, 90, 0.08);
            display: inline-block;
        }

        .estado-inactivo {
            color: var(--text);
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(220, 53, 69, 0.1);
            display: inline-block;
        }

        .estadistica-card::-webkit-scrollbar,
        .table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .estadistica-card::-webkit-scrollbar-track,
        .table-wrapper::-webkit-scrollbar-track {
            background: var(--bg);
            border-radius: 6px;
        }

        .estadistica-card::-webkit-scrollbar-thumb,
        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--button);
            border-radius: 6px;
            border: 2px solid var(--bg);
        }
        
        .estadistica-card::-webkit-scrollbar-thumb:hover,
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }
        
        .estadistica-card::-webkit-scrollbar-corner,
        .table-wrapper::-webkit-scrollbar-corner {
            background: var(--bg);
        }

        .btn-save {
            background: var(--btn-create);
            color: var(--white);
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

        .btn-save:hover {
            background: var(--primary-blue);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        .swal2-confirm {
            background: transparent !important;
            color: var(--text) !important;
            border: 2px solid var(--primary-blue) !important;
            border-radius: 5px !important;
            padding: 6px 16px !important;
            font-weight: 700 !important;
            letter-spacing: 0.3px !important;
            transition: all 0.3s ease !important;
            font-size: 13px !important;
            max-width: 120px !important;
            min-width: 100px !important;
        }
        
        .swal2-confirm:hover {
            background: var(--primary-blue) !important;
            color: var(--white) !important;
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35) !important;
            transform: translateY(-2px) !important;
        }
        
        .swal2-cancel {
            background: transparent !important;
            color: var(--text) !important;
            border: 2px solid var(--border) !important;
            border-radius: 5px !important;
            padding: 6px 16px !important;
            font-weight: 700 !important;
            letter-spacing: 0.3px !important;
            transition: all 0.3s ease !important;
            font-size: 13px !important;
            max-width: 120px !important;
            min-width: 100px !important;
        }
        
        .swal2-cancel:hover {
            background: var(--border) !important;
            color: var(--text) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
            transform: translateY(-2px) !important;
        }
        
        /* Estilos específicos para alertas de eliminación */
        .swal2-popup.swal-delete .swal2-confirm {
            background: transparent !important;
            color: var(--text) !important;
            border: 2px solid var(--primary-blue) !important;
        }
        
        .swal2-popup.swal-delete .swal2-confirm:hover {
            background: var(--primary-blue) !important;
            color: var(--white) !important;
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35) !important;
        }

        /* Asegurar que los botones estén lado a lado */
        .swal2-actions {
            display: flex !important;
            flex-direction: row !important;
            gap: 12px !important;
            justify-content: center !important;
            flex-wrap: nowrap !important;
            min-width: 400px !important;
            width: 100% !important;
        }

        /* Forzar estilos de botones */
        .swal2-actions button.swal2-confirm,
        .swal2-actions button.swal2-cancel,
        button.swal2-confirm,
        button.swal2-cancel {
            display: inline-block !important;
            flex: 0 0 auto !important;
        }

        .toggle-switch .slider {
            background-color: #ccc;
        }

        .toggle-switch input:checked + .slider {
            background-color: var(--primary-blue);
        }

        td {
            position: relative;
        }

        td:hover::after {
            content: attr(title);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 100%;
            background: var(--primary-blue);
            color: var(--white);
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: tooltipFadeIn 0.2s ease-out;
        }
        
        @keyframes tooltipFadeIn {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        /* Prevenir que headers sticky aparezcan sobre modales */
        body.modal-open thead,
        body.modal-open thead th,
        body.swal2-shown thead,
        body.swal2-shown thead th {
            position: relative !important;
            z-index: -1 !important;
        }

        /* Asegurar z-index correcto de modales */
        .modal {
            z-index: 9999 !important;
        }

        .swal2-container {
            z-index: 10000 !important;
        }

        .swal2-popup {
            z-index: 10001 !important;
        }

        body.modal-open {
            padding-right: 0 !important;
        }

        .main-scroll-panel {
            height: calc(100vh - 210px) !important;
            max-height: calc(100vh - 210px) !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            scrollbar-width: thin !important;
            scrollbar-color: var(--button) var(--bg) !important;
        }

        .estadistica-card {
            max-height: none !important;
            overflow: visible !important;
        }

        .table-wrapper,
        .table-container,
        .permisos-table-container {
            overflow-x: auto !important;
            overflow-y: auto !important;
            max-height: none !important;
            height: auto !important;
            scrollbar-width: thin !important;
            padding-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .main-scroll-panel .table-wrapper table {
            min-width: 100% !important;
        }

        .table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
        }

        .table-wrapper::-webkit-scrollbar,
        .table-container::-webkit-scrollbar,
        .permisos-table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-wrapper::-webkit-scrollbar-thumb,
        .table-container::-webkit-scrollbar-thumb,
        .permisos-table-container::-webkit-scrollbar-thumb {
            background: var(--button);
            border-radius: 6px;
            border: 1px solid var(--bg);
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover,
        .table-container::-webkit-scrollbar-thumb:hover,
        .permisos-table-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }

        .main-scroll-panel {
            max-height: calc(100vh - 140px) !important;
            overflow-y: auto !important;
            padding-bottom: 25px !important;
        }

        .main-scroll-panel .table-wrapper {
            overflow-y: visible !important;
        }

        /* Tema de colores de empresa */
        th,
        .section-title,
        .form-group label,
        .close {
            color: var(--text) !important;
        }

        .title_equipo h1,
        .title_equipo h1 i {
            color: var(--title-color) !important;
        }

        th {
            background: white !important;
            color: #2f4a5a !important;
            border-bottom-color: #2f4a5a !important;
        }

        td {
            color: #2f4a5a !important;
        }

        .close:hover {
            background: var(--text) !important;
        }

        .form-group input,
        .form-group select {
            border-color: var(--border ) !important;
        }

        /* ===== ESTILOS DE CATEGORÍAS APLICADOS ===== */
        .btn-nuevo {
            width: auto;
            min-width: 220px;
            margin: 20px;
            background: transparent;
            color: var(--primary-blue);
            padding: 14px 28px;
            border: 2px solid var(--primary-blue);
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            font-weight: 700;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .btn-nuevo:hover {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }

        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin: 0 2px;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .estado-activo {
            color: #0B6623;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(11, 102, 35, 0.1);
            display: inline-block;
        }

        .estado-inactivo {
            color: #dc3545;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(220, 53, 69, 0.1);
            display: inline-block;
        }

        .btn-editar,
        .btn-eliminar {
            width: 36px;
            height: 36px;
            padding: 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-editar {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #2575a8 100%);
            color: white;
        }

        .btn-editar:hover {
            background: linear-gradient(135deg, #2575a8 0%, #1e5a8e 100%);
            box-shadow: 0 4px 12px rgba(53, 145, 202, 0.3);
            transform: translateY(-2px);
        }

        .btn-eliminar {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-eliminar:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }

        .btn-editar:active,
        .btn-eliminar:active {
            transform: translateY(0);
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
            color: var(--primary-blue);
            z-index: 10;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: white;
            background: var(--primary-blue);
            transform: rotate(90deg);
        }

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
           
        }

        .modal[style*="display: flex"] {
            display: flex !important;
        }

        .modal-content {
            background: white;
            padding: 30px 35px;
            border-radius: 14px;
            box-shadow: 0 12px 36px rgba(20, 30, 40, 0.15), 0 0 0 1px rgba(20, 30, 40, 0.05);
            max-width: 1000px;
            width: 95%;
            position: relative;
            text-transform: uppercase;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--primary-blue);
            opacity: 0.8;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            width: 100%;
            font-size: 15px;
            text-transform: uppercase;
            background: var(--white);
            color: var(--text);
            outline: none;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: var(--focus-input);
        }

        .form-group input::placeholder {
            text-transform: uppercase;
            opacity: 0.6;
            color: var(--border);
        }

        .btn-save {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #2575a8 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(53, 145, 202, 0.2);
            letter-spacing: 0.5px;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #2575a8 0%, #1e5a8e 100%);
            box-shadow: 0 6px 18px rgba(53, 145, 202, 0.35);
            transform: translateY(-2px);
        }

        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }
    </style>
    <link rel="stylesheet" href="<?= base_url() ?>/Assets/css/responsive.css">
</head>
<body class="page-usuarios">
    <div class="title_equipo">
        <h1><i class="fas fa-users"></i> USUARIOS</h1>
    </div>
    
    <?php if (PermisosHelper::tienePermiso('Usuarios', 'crear')): ?>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
        <div style="display:flex; gap:8px; align-items:center;">
            <button class="btn-nuevo" onclick="toggleModal('crear')">
                <i class="fas fa-user-plus"></i> REGISTRAR USUARIO
            </button>
        </div>
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <?php if (PermisosHelper::esSuperAdminSesion() && empty($_SESSION['superadmin_modo_empresa'])): ?>
            <button type="button" class="btn-nuevo btn-nuevo-reset" onclick="confirmarReinicioUsuarios()">
                <i class="fas fa-broom"></i> REINICIAR
            </button>
            <button type="button" class="btn-nuevo btn-nuevo-undo" onclick="confirmarDeshacerReinicioUsuarios()">
                <i class="fas fa-undo"></i> DESHACER
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div id="registroModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="toggleModal()">&times;</span>
            <h2 style="text-align: center;">
                <i class="fas fa-user-edit"></i> ACTUALIZAR USUARIO
            </h2>
            <div id="error-message" style="display: none;"></div>
            <form id="registroForm" onsubmit="return enviarFormulario(event)" autocomplete="off" enctype="multipart/form-data">
                <input type="hidden" name="id" id="usuarioId">
                <input type="hidden" name="empresa_id" id="usuarioEmpresaId">
                <h3 class="section-title"><i class="fas fa-user-shield"></i> DATOS DEL USUARIO</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> NOMBRE:</label>
                        <input type="text" name="nombre" id="usuarioNombre" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> APELLIDOS:</label>
                        <input type="text" name="apellidos" id="usuarioApellidos" autocomplete="off" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> CORREO ELECTRÓNICO:</label>
                        <input type="email" name="correo" id="usuarioCorreo" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> TELÉFONO:</label>
                        <input type="text" name="telefono" id="usuarioTelefono" pattern="[0-9]{10}" maxlength="10" title="Ingrese un número de teléfono válido (10 dígitos)" autocomplete="off" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> ROL: <span id="rolNoEditableHint" style="display:none; margin-left:8px; color:#c0392b;" title="No se puede cambiar el rol del usuario Super Administrador"><i class="fas fa-ban"></i></span></label>
                        <select name="rol" id="usuarioRol" required>
                            <option value="" data-static="1">SELECCIONE UN ROL</option>
                            <?php
                            $rolActual = $_SESSION['rol'] ?? '';
                            $normalizarRol = function ($valor) {
                                $texto = strtr(mb_strtolower(trim((string)$valor), 'UTF-8'), [
                                    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
                                ]);
                                $texto = preg_replace('/[^a-z0-9]+/u', ' ', $texto);
                                $texto = trim(preg_replace('/\s+/u', ' ', (string)$texto));
                                return str_replace(' ', '', $texto);
                            };
                            $rolActualNormalizado = $normalizarRol($rolActual);
                            
                            // Filtrar roles según el rol del usuario actual
                            $rolesFiltrados = array_filter($roles, function($rol) use ($esSuperAdminSesion, $normalizarRol) {
                                $rolNombreNormalizado = $normalizarRol($rol['nombre'] ?? '');

                                if ($esSuperAdminSesion) {
                                    return true; // El Super Administrador puede ver y asignar cualquier rol
                                }

                                // El Administrador puede asignar cualquier rol salvo los protegidos
                                return !in_array($rolNombreNormalizado, ['superadministrador', 'administrador'], true);
                            });

                            // Ordenar los roles filtrados
                            $rolesOrdenados = array_map(function($rol) {
                                return $rol['nombre'];
                            }, $rolesFiltrados);
                            $rolesOrdenados = array_values(array_unique($rolesOrdenados));
                            sort($rolesOrdenados);

                            // Fallback para Administrador: evitar selector vacio si no quedaron opciones
                            // por configuracion de roles en BD.
                            if ($rolActualNormalizado === 'administrador' && empty($rolesOrdenados)) {
                                $rolesDisponibles = [];
                                foreach ($roles as $rolItem) {
                                    $nombreRol = (string)($rolItem['nombre'] ?? '');
                                    $normalizado = $normalizarRol($nombreRol);
                                    if (!in_array($normalizado, ['superadministrador', 'administrador'], true)) {
                                        $rolesDisponibles[] = $nombreRol;
                                    }
                                }

                                $rolesDisponibles = array_values(array_unique(array_filter($rolesDisponibles)));
                                sort($rolesDisponibles);

                                if (empty($rolesDisponibles)) {
                                    $rolesDisponibles = ['Usuario'];
                                }

                                $rolesOrdenados = $rolesDisponibles;
                            }

                            // Mostrar los roles filtrados
                            foreach ($rolesOrdenados as $rol) {
                                echo '<option value="' . htmlspecialchars($rol) . '" data-static="1">' . 
                                     htmlspecialchars(strtoupper($rol)) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> TIPO DE DOCUMENTO:</label>
                        <select name="tipo_documento" id="usuarioTipoDocumento" required>
                            <option value="" disabled selected>SELECCIONE EL TIPO DE DOCUMENTO</option>
                            <option value="Cédula de Ciudadanía">CÉDULA DE CIUDADANÍA</option>
                            <option value="Cédula de Extranjería Colombiana">CÉDULA DE EXTRANJERÍA COLOMBIANA</option>
                            <option value="Cédula Extranjera">CÉDULA EXTRANJERA</option>
                            <option value="Documento Extranjero">DOCUMENTO EXTRANJERO</option>
                            <option value="Pasaporte">PASAPORTE</option>
                            <option value="Registro Civil">REGISTRO CIVIL</option>
                            <option value="Tarjeta de Identidad">TARJETA DE IDENTIDAD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> NÚMERO DE DOCUMENTO:</label>
                        <input type="text" name="documento" id="usuarioDocumento" pattern="[0-9]{1,10}" maxlength="10" title="Ingrese un número de documento válido de máximo 10 dígitos" autocomplete="off" required>
                    </div>
                </div>

                <div id="registroPasswordRow" class="form-row" style="display:none;">
                    <div class="form-group password-container">
                        <label><i class="fas fa-lock"></i> CONTRASEÑA:</label>
                        <input type="password" name="contrasena" id="usuarioContrasena" autocomplete="new-password" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Contraseña de 6 dígitos">
                        <i class="fas fa-eye toggle-password" title="MOSTRAR CONTRASEÑA" role="button" aria-label="MOSTRAR CONTRASEÑA" onclick="togglePasswordVisibility(this)"></i>
                    </div>
                    <div class="form-group password-container">
                        <label><i class="fas fa-lock"></i> CONFIRMAR CONTRASEÑA:</label>
                        <input type="password" name="contrasena_confirma_registro" id="usuarioContrasenaConfirma" autocomplete="new-password" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Confirmar contraseña">
                        <i class="fas fa-eye toggle-password" role="button" aria-label="MOSTRAR CONTRASEÑA" onclick="togglePasswordVisibility(this)"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> IMAGEN DE USUARIO:</label>
                        <input type="file" name="imagen_archivo" id="imagenArchivo" accept="image/jpeg,image/png,image/webp,image/gif" autocomplete="off">
                        <small style="display:block; margin-top:6px; color:#6b7280;">Opcional. JPG, PNG, WEBP o GIF, máximo 5 MB.</small>
                    </div>
                </div>

                <div id="empresaAdminWrapper" style="display: none;">
                    <h3 class="section-title"><i class="fas fa-building"></i> INFORMACIÓN DE EMPRESA</h3>
                    
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> NOMBRE DE LA EMPRESA:</label>
                        <input type="text" name="empresa_nombre" id="empresaNombre" placeholder="Nombre de la empresa" maxlength="120" autocomplete="off" readonly style="background: var(--bg); cursor: not-allowed;">
                    </div>
                    
                    <input type="hidden" id="usuarioTipoEmpresa" value="">
                    <input type="hidden" id="empresaIdActual" value="">

                    <div class="form-group">
                        <select name="empresa_estado" id="empresaEstado" style="display:none;">
                            <option value="1" selected>ACTIVA</option>
                            <option value="0">INACTIVA</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions" style="margin: 25px auto 0; width: 100%; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
                    
                    <button id="btnCambiarContrasena" type="button" onclick="abrirCambioContrasena()" style="display: none; min-width: 200px;" class="button-primary"><i class="fas fa-key"></i> CAMBIAR CONTRASEÑA</button>
                    <button type="submit" id="btnEnviarRegistro" style="min-width: 280px;" class="button-primary"><i class="fas fa-save"></i> ACTUALIZAR USUARIO</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para cambiar contraseña -->
    <div id="cambioContrasenaModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarCambioContrasena()">&times;</span>
            <h2 style="text-align: center;">
                <i class="fas fa-key"></i> CAMBIAR CONTRASEÑA
            </h2>
            <div id="cambioContrasenaError" style="display: none; padding: 12px; margin-bottom: 15px; border-radius: 8px; background: #fee; color: #c00; border: 1px solid #fcc;"></div>
            <div id="cambioContrasenaExito" style="display: none; padding: 12px; margin-bottom: 15px; border-radius: 8px; background: #efe; color: #0a0; border: 1px solid #cfc;"></div>
            <form id="cambioContrasenaForm" onsubmit="enviarCambioContrasena(event)" autocomplete="off">
                <input type="hidden" id="usuarioIdContrasena">
                <div class="password-field">
                    <label><i class="fas fa-key"></i> CONTRASEÑA ACTUAL:</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="contrasena_actual" id="contrasenaActual" autocomplete="off" placeholder="Ingrese su contraseña actual">
                        <i class="fas fa-eye toggle-password" title="MOSTRAR CONTRASEÑA" role="button" aria-label="MOSTRAR CONTRASEÑA" onclick="togglePasswordVisibility(this)"></i>
                    </div>
                </div>
                <div class="password-field">
                    <label><i class="fas fa-key"></i> NUEVA CONTRASEÑA:</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="contrasena_nueva" id="contrasenanueva" required autocomplete="off" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Ingrese la nueva contraseña">
                        <i class="fas fa-eye toggle-password" title="MOSTRAR CONTRASEÑA" role="button" aria-label="MOSTRAR CONTRASEÑA" onclick="togglePasswordVisibility(this)"></i>
                    </div>
                </div>
                <div class="password-field">
                    <label><i class="fas fa-check-circle"></i> CONFIRMAR CONTRASEÑA:</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="contrasena_confirma" id="contrasenaConfirma" required autocomplete="off" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Confirme la nueva contraseña">
                        <i class="fas fa-eye toggle-password" title="MOSTRAR CONTRASEÑA" role="button" aria-label="MOSTRAR CONTRASEÑA" onclick="togglePasswordVisibility(this)"></i>
                    </div>
                </div>

                <div style="margin: 25px auto 0; width: 100%; display: flex; justify-content: center;">
                    <button type="submit" class="button-primary" style="min-width: 280px;"><i class="fas fa-key"></i> CAMBIAR CONTRASEÑA</button>
                </div>
            </form>
        </div>
    </div>

    <div class="container main-scroll-panel">
        <div class="estadistica-card">
            <div class="table-wrapper">
            <?php
            // Verificar permisos al inicio
            $tienePermisoEditar = PermisosHelper::tienePermiso('Usuarios', 'actualizar');
            $tienePermisoEliminar = PermisosHelper::tienePermiso('Usuarios', 'eliminar');
            $tieneAlgunPermiso = $tienePermisoEditar || $tienePermisoEliminar;
            
            // Ajustar colspan segun columnas visibles
            $numColumnas = 4
                + ($mostrarColumnaId ? 1 : 0)
                + ($mostrarColumnaImagen ? 1 : 0)
                + ($tieneAlgunPermiso ? 1 : 0);
            ?>
            <table>
                <thead>
                    <tr>
                        <?php if ($mostrarColumnaId): ?>
                        <th><i class="fas fa-hashtag"></i>ID</th>
                        <?php endif; ?>
                        <?php if ($mostrarColumnaImagen): ?>
                        <th class="col-imagen"><i class="fas fa-image"></i>Imagen</th>
                        <?php endif; ?>
                        <th><i class="fas fa-user"></i>Nombre</th>
                        <th><i class="fas fa-user"></i>Apellidos</th>
                        <th><i class="fas fa-phone"></i>Teléfono</th>
                        <th><i class="fas fa-user-tag"></i>Rol</th>
                        <?php if ($tieneAlgunPermiso): ?>
                            <th class="col-acciones"><i class="fas fa-edit"></i>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($usuarios)) {
                        echo "<tr><td colspan='" . $numColumnas . "'>NO HAY USUARIOS REGISTRADOS EN EL SISTEMA</td></tr>";
                    } else {
                        foreach ($usuarios as $usuario): 
                            // Ocultar Super Administrador si el usuario actual no es Super Administrador
                            if ($usuario['rol'] === 'Super Administrador' && !$esSuperAdminSesion) {
                                continue;
                            }
                    ?>
                            <tr>
                                <?php if ($mostrarColumnaId): ?>
                                <td title="<?php echo htmlspecialchars($usuario['id'] ?? 'NO DISPONIBLE'); ?>">
                                    <?php echo htmlspecialchars($usuario['id'] ?? 'NO DISPONIBLE'); ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($mostrarColumnaImagen): ?>
                                <td class="col-imagen">
                                    <?php $imgUsuario = trim((string)($usuario['imagen'] ?? '')); ?>
                                    <?php
                                        $imgEmpresa = trim((string)($usuario['empresa_imagen'] ?? ''));
                                        $imgElegida = $imgUsuario !== '' ? $imgUsuario : $imgEmpresa;
                                        $imgUsuarioRender = $normalizarImagenVista($imgElegida);
                                    ?>
                                    <?php if ($imgUsuarioRender !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($imgUsuarioRender, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen" class="user-avatar-thumb">
                                    <?php else: ?>
                                        <span class="user-avatar-fallback"><i class="fas fa-user"></i></span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td title="<?php echo htmlspecialchars($usuario['nombre'] ?? 'NO DISPONIBLE'); ?>">
                                    <?php echo htmlspecialchars($usuario['nombre'] ?? 'NO DISPONIBLE'); ?>
                                </td>
                                <td title="<?php echo htmlspecialchars($usuario['apellidos'] ?? 'NO DISPONIBLE'); ?>">
                                    <?php echo htmlspecialchars($usuario['apellidos'] ?? 'NO DISPONIBLE'); ?>
                                </td>
                                <td title="<?php echo htmlspecialchars($usuario['telefono'] ?? 'NO DISPONIBLE'); ?>">
                                    <?php echo htmlspecialchars($usuario['telefono'] ?? 'NO DISPONIBLE'); ?>
                                </td>
                                <td title="<?php echo htmlspecialchars($usuario['rol'] ?? 'NO DISPONIBLE'); ?>">
                                    <?php echo htmlspecialchars($usuario['rol'] ?? 'NO DISPONIBLE'); ?>
                                </td>
                                <?php if ($tieneAlgunPermiso): ?>
                                <td class="col-acciones">
                                    <div class="button-actions">
                                        <button onclick="verUsuario(<?php echo $usuario['id']; ?>)" class="button-primary button-edit-delete" title="Ver usuario">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($tienePermisoEditar): ?>
                                            <button onclick="editarUsuario(<?php echo (int)$usuario['id']; ?>)" class="button-primary button-edit-delete">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($tienePermisoEliminar): ?>
                                            <?php 
                                            $esSuperAdmin = $usuario['rol'] === 'Super Administrador';
                                            $esAdminViendoAdmin = ($_SESSION['rol'] === 'Administrador' && $usuario['rol'] === 'Administrador');
                                            $esPrimerUsuario = in_array((int)$usuario['id'], [1, 2], true);
                                            $deshabilitarBoton = $esSuperAdmin || $esAdminViendoAdmin || $esPrimerUsuario;
                                            $tituloEliminar = 'Eliminar usuario';
                                            if ($esPrimerUsuario) {
                                                $tituloEliminar = 'No se puede eliminar este usuario';
                                            } elseif ($esSuperAdmin) {
                                                $tituloEliminar = 'No se puede eliminar un usuario Super Administrador';
                                            } elseif ($esAdminViendoAdmin) {
                                                $tituloEliminar = 'No se puede eliminar un Administrador';
                                            }
                                            ?>
                                            <button onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)" 
                                                    class="button-delete button-edit-delete"
                                                    <?php echo $deshabilitarBoton ? 'disabled style="opacity: 0.5; cursor: not-allowed; background-color: #808080;"' : ''; ?>
                                                    title="<?php echo htmlspecialchars($tituloEliminar, ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                    <?php 
                        endforeach; 
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
        const ES_SUPER_ADMIN_SESION = <?= $esSuperAdminSesion ? 'true' : 'false'; ?>;

        // Polyfill ligero para SweetAlert2 cuando el CDN falla (usa confirm/alert nativo)
        if (typeof Swal === 'undefined') {
            console.warn('SweetAlert2 no está disponible — usando diálogos nativos como fallback.');
            window.Swal = {
                fire: function(opts) {
                    return new Promise(function(resolve) {
                        try {
                            var title = (opts && (opts.title || ''));
                            var html = (opts && (opts.html || opts.text || ''));
                            var showCancel = !!(opts && opts.showCancelButton);
                            // Limpiar etiquetas HTML básicas para mostrar en alert/confirm
                            var plain = String(html || '').replace(/<[^>]*>/g, '');
                            if (showCancel) {
                                var ok = confirm(title + '\n\n' + plain);
                                resolve({ isConfirmed: ok, isDenied: !ok, value: ok });
                            } else {
                                alert(title + (plain ? '\n\n' + plain : ''));
                                resolve({ isConfirmed: true, value: true });
                            }
                        } catch (e) {
                            resolve({ isConfirmed: false });
                        }
                    });
                }
            };
        }

        function esRolAdministradorJs(rol) {
            const texto = String(rol || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
            return texto === 'administrador';
        }

        function esRolClienteJs(rol) {
            const texto = String(rol || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
            return texto === 'cliente';
        }

        function renderPreviewImagenEmpresa(valor) {
            const preview = document.getElementById('empresaImagenPreview');
            if (!preview) {
                return;
            }

            const raw = String(valor || '').trim();
            if (!raw) {
                preview.innerHTML = 'SIN IMAGEN';
                return;
            }

            const esAbsoluta = /^(https?:)?\/\//i.test(raw) || raw.startsWith('data:') || raw.startsWith('/');
            const src = esAbsoluta ? raw : '../' + raw.replace(/^\/+/, '');
            preview.innerHTML = `<img src="${src}" alt="Imagen empresa" style="width:100%; height:100%; object-fit:cover;">`;
        }

        function renderPreviewArchivoEmpresa(file) {
            const preview = document.getElementById('empresaImagenPreview');
            if (!preview) {
                return;
            }

            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const src = String(e.target?.result || '');
                if (!src) {
                    return;
                }
                preview.innerHTML = `<img src="${src}" alt="Imagen empresa" style="width:100%; height:100%; object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Inicialización de modal
        });

        // Manejar ocultación de headers sticky cuando se abren modales
        (function() {
            // Función para ocultar headers
            function hideHeaders() {
                const headers = document.querySelectorAll('thead, thead th');
                headers.forEach(header => {
                    header.style.position = 'relative';
                    header.style.zIndex = '-1';
                });
            }
            
            // Función para mostrar headers
            function showHeaders() {
                const headers = document.querySelectorAll('thead, thead th');
                headers.forEach(header => {
                    header.style.position = 'sticky';
                    header.style.zIndex = '5';
                });
            }
            
            // Monitorear cambios en el modal
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        const modal = document.getElementById('registroModal');
                        if (modal && modal.style.display === 'flex') {
                            hideHeaders();
                            document.body.classList.add('modal-open');
                        } else {
                            setTimeout(showHeaders, 100);
                            document.body.classList.remove('modal-open');
                        }
                    }
                });
            });
            
            // Observar el modal cuando el DOM esté listo
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('registroModal');
                if (modal) {
                    observer.observe(modal, { attributes: true });
                }
            });
            
            // Interceptar SweetAlert2 si está disponible
            if (typeof Swal !== 'undefined') {
                const originalSwalFire = Swal.fire;
                Swal.fire = function(...args) {
                    hideHeaders();
                    const result = originalSwalFire.apply(this, args);
                    result.then(() => setTimeout(showHeaders, 100)).catch(() => setTimeout(showHeaders, 100));
                    return result;
                };
            }
        })();
        
        function toggleModal(mode = 'crear') {
            const modal = document.getElementById('registroModal');
            const titulo = document.querySelector('.modal-content h2');
            const botonSubmit = document.querySelector('button[type="submit"]');
            const btnCambiarContrasena = document.getElementById('btnCambiarContrasena');
            const selectRol = document.getElementById('usuarioRol');
            const passwordRow = document.getElementById('registroPasswordRow');
            const passwordInput = document.getElementById('usuarioContrasena');
            const passwordConfirmInput = document.getElementById('usuarioContrasenaConfirma');
            
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
            } else {
                modal.style.display = 'flex';
                modal.scrollTop = 0;
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.scrollTop = 0;
                }
                
                if (mode === 'crear') {
                    // Solo limpiar cuando es modo crear
                    limpiarFormulario();
                    
                    titulo.innerHTML = '<i class="fas fa-user-plus"></i> REGISTRAR USUARIO';
                    botonSubmit.innerHTML = '<i class="fas fa-save"></i> REGISTRAR USUARIO';
                    
                    // Ocultar botón de cambiar contraseña en modo crear
                    if (btnCambiarContrasena) {
                        btnCambiarContrasena.style.display = 'none';
                    }

                    if (passwordRow) {
                        passwordRow.style.display = 'grid';
                    }
                    if (passwordInput) {
                        passwordInput.required = true;
                    }
                    if (passwordConfirmInput) {
                        passwordConfirmInput.required = true;
                    }
                    
                    // Habilitar el select de rol si estaba deshabilitado
                    selectRol.disabled = false;
                    
                    // Remover campos hidden que puedan existir del modo edición
                    const hiddenRol = selectRol.parentElement.querySelector('input[type="hidden"][name="rol"]');
                    if (hiddenRol) {
                        hiddenRol.remove();
                    }
                    
                    // Habilitar todas las opciones del select
                    Array.from(selectRol.options).forEach(option => {
                        option.disabled = false;
                    });
                } else if (mode === 'editar') {
                    titulo.innerHTML = '<i class="fas fa-user-edit"></i> ACTUALIZAR USUARIO';
                    botonSubmit.innerHTML = '<i class="fas fa-save"></i> ACTUALIZAR USUARIO';

                    // Mostrar botón de cambiar contraseña en modo editar
                    if (btnCambiarContrasena) {
                        btnCambiarContrasena.style.display = 'inline-flex';
                    }
                    if (passwordRow) {
                        passwordRow.style.display = 'none';
                    }
                    if (passwordInput) {
                        passwordInput.required = false;
                        passwordInput.value = '';
                    }
                    if (passwordConfirmInput) {
                        passwordConfirmInput.required = false;
                        passwordConfirmInput.value = '';
                    }
                    // En modo editar no es necesario mostrar los controles de reinicio del formulario
                }
                
                document.getElementById('error-message').style.display = 'none';
            }
        }

        function togglePasswordVisibility(element) {
            if (!element) {
                return;
            }
            // Buscar el wrapper de contraseña (nueva estructura) o container (estructura antigua)
            const wrapper = element.closest('.password-input-wrapper') || element.closest('.password-container');
            if (!wrapper) {
                return;
            }
            const input = wrapper.querySelector('input[type="password"], input[type="text"]');
            if (!input) {
                return;
            }
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            element.classList.toggle('fa-eye', !isPassword);
            element.classList.toggle('fa-eye-slash', isPassword);
            element.title = isPassword ? 'OCULTAR CONTRASEÑA' : 'MOSTRAR CONTRASEÑA';
        }
        
        function limpiarFormulario() {
            document.getElementById('registroForm').reset();
            document.getElementById('usuarioId').value = '';
            document.getElementById('usuarioEmpresaId').value = '';
            document.getElementById('usuarioNombre').value = '';
            document.getElementById('usuarioApellidos').value = '';
            document.getElementById('usuarioCorreo').value = '';
            document.getElementById('usuarioTelefono').value = '';
            document.getElementById('usuarioTipoDocumento').value = '';
            document.getElementById('usuarioDocumento').value = '';
            document.getElementById('usuarioRol').value = '';
            const inputEmpresaImagenArchivo = document.getElementById('empresaImagenArchivo');
            const inputImagenArchivo = document.getElementById('imagenArchivo');
            const txtEmpresaImagenArchivoNombre = document.getElementById('empresaImagenArchivoNombre');
            if (inputEmpresaImagenArchivo) {
                inputEmpresaImagenArchivo.value = '';
            }
            if (inputImagenArchivo) {
                inputImagenArchivo.value = '';
            }
            if (txtEmpresaImagenArchivoNombre) {
                txtEmpresaImagenArchivoNombre.textContent = 'Ningun archivo seleccionado';
            }
            renderPreviewImagenEmpresa('');
            const selectTipoEmpresa = document.getElementById('usuarioTipoEmpresa');
            if (selectTipoEmpresa) {
                selectTipoEmpresa.value = '';
            }
            const empresaNombre = document.getElementById('empresaNombre');
            const empresaNit = document.getElementById('empresaNit');
            const empresaTelefono = document.getElementById('empresaTelefono');
            const empresaCorreoElectronico = document.getElementById('empresaCorreoElectronico');
            const empresaEstado = document.getElementById('empresaEstado');
            const passwordRow = document.getElementById('registroPasswordRow');
            const passwordInput = document.getElementById('usuarioContrasena');
            const passwordConfirmInput = document.getElementById('usuarioContrasenaConfirma');
            if (empresaNombre) empresaNombre.value = '';
            if (empresaNit) empresaNit.value = '';
            if (empresaTelefono) empresaTelefono.value = '';
            if (empresaCorreoElectronico) empresaCorreoElectronico.value = '';
            if (empresaEstado) empresaEstado.value = '1';
            if (passwordRow) passwordRow.style.display = 'none';
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.required = false;
            }
            if (passwordConfirmInput) {
                passwordConfirmInput.value = '';
                passwordConfirmInput.required = false;
            }
        }

        function resetearFormularioUsuario() {
            limpiarFormulario();
            const modal = document.getElementById('registroModal');
            if (modal && modal.style.display !== 'flex') {
                toggleModal('crear');
            }
        }

        function deshacerFormularioUsuario() {
            limpiarFormulario();
            const modal = document.getElementById('registroModal');
            if (modal && modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
        }

        // Acción: Reiniciar usuarios (elimina todos excepto IDs 1 y 2)
        async function reiniciarUsuarios() {
            try {
                const confirmResult = await Swal.fire({
                    title: '¿REINICIAR USUARIOS?',
                    html: '<div style="font-size:15px;">ESTA ACCIÓN ELIMINARÁ TODOS LOS USUARIOS MENOS LOS IDs 1 Y 2. ¿DESEA CONTINUAR?</div>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'SÍ, REINICIAR',
                    cancelButtonText: 'CANCELAR',
                    confirmButtonColor: '#3b82f6'
                });

                if (!confirmResult || !confirmResult.isConfirmed) return;

                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ action: 'reiniciar' })
                });

                if (!response.ok) throw new Error('Error en la respuesta del servidor');
                const result = await response.json();
                if (result.success) {
                    await Swal.fire({ title: 'REINICIADO', html: '<div style="font-size:15px;">' + String(result.message || 'Operación completada') + '</div>', icon: 'success', confirmButtonText: 'ACEPTAR' });
                    window.location.reload();
                } else {
                    await Swal.fire({ title: 'ERROR', html: '<div style="font-size:15px;">' + String(result.message || 'No fue posible reiniciar') + '</div>', icon: 'error', confirmButtonText: 'ENTENDIDO' });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({ title: 'ERROR', html: '<div style="font-size:15px;">HUBO UN ERROR AL INTENTAR REINICIAR</div>', icon: 'error' });
            }
        }

        // Confirm dialog identical to inventarios: wrapper that confirma y llama al endpoint
        function confirmarReinicioUsuarios() {
            const nombreTabla = 'usuarios';
            Swal.fire({
                title: '¿Reiniciar ' + nombreTabla + '?',
                text: 'Esta acción limpiará los registros de ' + nombreTabla + ' y dejará el listado vacío. Puede deshacer este cambio con el botón correspondiente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, reiniciar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'reiniciar');

                fetch('usuarios.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo reiniciar usuarios');
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Usuarios reiniciados',
                        text: data.message || 'Los usuarios fueron eliminados correctamente.'
                    }).then(() => location.reload());
                })
                .catch(e => {
                    Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'No se pudo reiniciar usuarios' });
                });
            });
        }

        function confirmarDeshacerReinicioUsuarios() {
            const nombreTabla = 'usuarios';
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
                formData.append('action', 'deshacer');

                fetch('usuarios.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo deshacer el reinicio');
                    }
                    Swal.fire({ icon: 'success', title: 'Restaurado', text: data.message || 'Restauración completada' }).then(() => location.reload());
                })
                .catch(e => {
                    Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'No se pudo restaurar' });
                });
            });
        }

        async function deshacerAccionRapida() {
            try {
                const confirmResult = await Swal.fire({
                    title: '¿Deshacer último reinicio?',
                    html: '<div style="font-size:15px;">Se restaurará el último backup de usuarios.</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, restaurar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#64748b'
                });

                if (!confirmResult || !confirmResult.isConfirmed) return;

                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ action: 'deshacer' })
                });

                if (!response.ok) throw new Error('Error en la respuesta del servidor');
                const result = await response.json();
                if (result.success) {
                    await Swal.fire({ title: 'RESTAURADO', html: '<div style="font-size:15px;">' + String(result.message || 'Restauración completada') + '</div>', icon: 'success', confirmButtonText: 'ACEPTAR' });
                    window.location.reload();
                } else {
                    await Swal.fire({ title: 'ERROR', html: '<div style="font-size:15px;">' + String(result.message || 'No fue posible restaurar') + '</div>', icon: 'error', confirmButtonText: 'ENTENDIDO' });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({ title: 'ERROR', html: '<div style="font-size:15px;">HUBO UN ERROR AL INTENTAR RESTAURAR</div>', icon: 'error' });
            }
        }

        async function verUsuario(id) {
            try {
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: id, action: 'get' })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message || 'No fue posible cargar el usuario.');
                }

                const data = result.data || {};
                const esSuperAdminFila = String(data.rol || '').trim().toLowerCase() === 'super administrador'.toLowerCase();
                const empresa = String(esSuperAdminFila ? 'ACCESO AL SISTEMA GLOBAL' : (data.empresa_nombre || 'N/A')).toUpperCase();
                const tipoEmpresa = String(esSuperAdminFila ? '' : (data.tipo_empresa_nombre || 'N/A')).toUpperCase();
                const usuarioEdita = String(data.administrador_nombre || 'N/A').toUpperCase();

                Swal.fire({
                    title: 'DETALLE DEL USUARIO',
                    html: `
                        <div style="text-align:left; display:grid; gap:10px; font-size:14px; line-height:1.45; text-transform:uppercase;">
                            <div><strong>Nombre:</strong> ${escapeHtml(`${data.nombre || ''} ${data.apellidos || ''}`.trim() || 'N/A')}</div>
                            <div><strong>Correo:</strong> ${escapeHtml(data.correo || 'N/A')}</div>
                            <div><strong>Telefono:</strong> ${escapeHtml(data.telefono || 'N/A')}</div>
                            <div><strong>Documento:</strong> ${escapeHtml(data.tipo_documento || 'N/A')} - ${escapeHtml(data.documento || 'N/A')}</div>
                            <div><strong>Rol:</strong> ${escapeHtml(data.rol || 'N/A')}</div>
                            <div><strong>Empresa:</strong> ${escapeHtml(empresa)}</div>
                            ${!esSuperAdminFila ? `<div><strong>Tipo de empresa:</strong> ${escapeHtml(tipoEmpresa)}</div>` : ''}
                            <div><strong>Usuario que edita:</strong> ${escapeHtml(usuarioEdita)}</div>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'CERRAR',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
            } catch (error) {
                Swal.fire({
                    title: 'ERROR',
                    html: '<div style="font-size: 15px; padding: 10px;">' + String(error.message || 'NO FUE POSIBLE CARGAR EL USUARIO').toUpperCase() + '</div>',
                    icon: 'error',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
            }
        }

        async function editarUsuario(id) {
            try {
                // Primero obtener los datos del usuario
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: id, action: 'get' })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Error al cargar los datos del usuario');
                }

                const data = result.data;

                const setRolSelectValue = (selectElement, rolObjetivo) => {
                    if (!selectElement) return;
                    const objetivo = String(rolObjetivo || '').trim().toLowerCase();
                    const opciones = Array.from(selectElement.options);
                    let encontrado = false;

                    opciones.forEach((option) => {
                        const valor = String(option.value || '').trim().toLowerCase();
                        if (valor === objetivo) {
                            option.selected = true;
                            encontrado = true;
                        } else if (option.selected && valor !== '') {
                            option.selected = false;
                        }
                    });

                    if (!encontrado) {
                        const opcionFallback = opciones.find((option) => String(option.value || '').trim() !== '');
                        if (opcionFallback) {
                            opcionFallback.selected = true;
                        }
                    }
                };

                // Verificar permisos de edición
                const rolActual = '<?php echo $_SESSION['rol']; ?>';
                const usuarioActualId = '<?php echo $_SESSION['usuario_id']; ?>';
                
                // Si es Administrador, solo puede editar su propio perfil o usuarios no administradores
                if (rolActual === 'Administrador') {
                    if (data.rol === 'Administrador' && usuarioActualId !== String(data.id)) {
                        Swal.fire({
                            title: 'ACCESO DENEGADO',
                            html: '<div style="font-size: 15px; padding: 10px;">NO TIENES PERMISO PARA EDITAR A OTROS ADMINISTRADORES</div>',
                            icon: 'error',
                            confirmButtonText: 'ENTENDIDO',
                            customClass: {
                                popup: 'swal-wide',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                        return;
                    }
                }

                // Cargar los datos en el formulario
                document.getElementById('usuarioId').value = data.id;
                document.getElementById('usuarioNombre').value = data.nombre;
                document.getElementById('usuarioApellidos').value = data.apellidos;
                document.getElementById('usuarioCorreo').value = data.correo;
                document.getElementById('usuarioTelefono').value = data.telefono;
                document.getElementById('usuarioTipoDocumento').value = data.tipo_documento;
                document.getElementById('usuarioDocumento').value = data.documento;
                document.getElementById('usuarioEmpresaId').value = data.empresa_id || '';
                
                const inputEmpresaNombre = document.getElementById('empresaNombre');
                const inputEmpresaId = document.getElementById('empresaIdActual');
                
                if (inputEmpresaNombre) {
                    inputEmpresaNombre.value = data.empresa_nombre || '';
                }
                
                if (inputEmpresaId) {
                    inputEmpresaId.value = data.empresa_id || '';
                }
                
                const empresaAdminWrapper = document.getElementById('empresaAdminWrapper');
                const rolSesion = <?= json_encode($_SESSION['rol'] ?? ''); ?>;
                const empresaSesionId = <?= json_encode((int)($_SESSION['empresa_id'] ?? ($_SESSION['userData']['empresa_id'] ?? 0))); ?>;
                const puedeEditarEmpresa = Boolean(empresaAdminWrapper)
                    && (ES_SUPER_ADMIN_SESION || rolSesion === 'Administrador')
                    && esRolAdministradorJs(data.rol)
                    && String(data.empresa_id || '') === String(empresaSesionId);

                if (empresaAdminWrapper) {
                    empresaAdminWrapper.style.display = puedeEditarEmpresa ? 'block' : 'none';
                }
                if (inputEmpresaNombre) {
                    inputEmpresaNombre.readOnly = !puedeEditarEmpresa;
                    inputEmpresaNombre.style.cursor = puedeEditarEmpresa ? 'text' : 'not-allowed';
                }
                
                const selectTipoEmpresa = document.getElementById('usuarioTipoEmpresa');
                if (selectTipoEmpresa) {
                    const tipoEmpresaId = data.id_tipos_empresa
                        ? String(data.id_tipos_empresa)
                        : (data.tipo_empresa_id ? String(data.tipo_empresa_id) : '');
                    selectTipoEmpresa.value = tipoEmpresaId;
                }
                
                const selectRol = document.getElementById('usuarioRol');
                const rolNoEditableHint = document.getElementById('rolNoEditableHint');
                const esUsuarioSuperAdminObjetivo = String(data.rol || '').trim().toLowerCase() === 'super administrador';

                // Limpiar opciones agregadas dinámicamente en aperturas anteriores del modal
                // (solo conservar las generadas por PHP, marcadas con data-static)
                Array.from(selectRol.options).forEach(opt => {
                    if (!opt.hasAttribute('data-static')) {
                        opt.remove();
                    }
                });
                // Re-habilitar todas las opciones estáticas (pueden haber quedado disabled)
                Array.from(selectRol.options).forEach(opt => {
                    opt.disabled = false;
                });

                // Limpiar hidden previo para evitar duplicados
                const hiddenRolPrevio = selectRol.parentElement.querySelector('input[type="hidden"][name="rol"]');
                if (hiddenRolPrevio) {
                    hiddenRolPrevio.remove();
                }

                if (esUsuarioSuperAdminObjetivo) {
                    setRolSelectValue(selectRol, data.rol);
                    selectRol.disabled = true;
                    const hiddenRol = document.createElement('input');
                    hiddenRol.type = 'hidden';
                    hiddenRol.name = 'rol';
                    hiddenRol.value = data.rol;
                    selectRol.parentElement.appendChild(hiddenRol);
                    if (rolNoEditableHint) {
                        rolNoEditableHint.style.display = 'inline-flex';
                    }
                } else if (rolNoEditableHint) {
                    rolNoEditableHint.style.display = 'none';
                }
                
                // Manejar el rol según los permisos
                if (!esUsuarioSuperAdminObjetivo && rolActual === 'Administrador') {
                    // Si es Administrador, solo puede editar su propio perfil
                    if (usuarioActualId === String(data.id)) {
                        setRolSelectValue(selectRol, data.rol);
                        selectRol.disabled = true;
                        // Asegurar que el valor del rol se envíe
                        const hiddenRol = document.createElement('input');
                        hiddenRol.type = 'hidden';
                        hiddenRol.name = 'rol';
                        hiddenRol.value = data.rol;
                        selectRol.parentElement.appendChild(hiddenRol);
                    } else {
                        // Si esta editando otro usuario, puede asignar cualquier rol de su alcance
                        // excepto roles protegidos de administracion.
                        selectRol.disabled = false;
                        setRolSelectValue(selectRol, data.rol);

                        // Filtrar solo roles no asignables para Administrador
                        const normalizarRolCliente = (valor) => String(valor || '')
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .replace(/[^a-z0-9]+/g, '')
                            .trim();

                        Array.from(selectRol.options).forEach(option => {
                            const rolNorm = normalizarRolCliente(option.value);
                            if (rolNorm === 'superadministrador' || rolNorm === 'administrador') {
                                option.disabled = true;
                            } else {
                                option.disabled = false;
                            }
                        });
                    }
                } else if (!esUsuarioSuperAdminObjetivo) {
                    selectRol.disabled = false;
                    setRolSelectValue(selectRol, data.rol);
                    // Remover el input hidden si existe
                    const hiddenRol = selectRol.parentElement.querySelector('input[type="hidden"][name="rol"]');
                    if (hiddenRol) {
                        hiddenRol.remove();
                    }
                }

                // Abrir el modal en modo edición
                toggleModal('editar');

            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'ERROR',
                    html: '<div style="font-size: 15px; padding: 10px;">' + (error.message || 'ERROR AL CARGAR LOS DATOS DEL USUARIO').toUpperCase() + '</div>',
                    icon: 'error',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
            }
        }

        function mostrarAlerta(tipo, mensaje) {
            // Solo cerrar el modal si es una alerta de éxito
            if (tipo === 'success') {
                const modal = document.getElementById('registroModal');
                modal.style.display = 'none';
            }

            Swal.fire({
                title: tipo === 'success' ? '¡ÉXITO!' : 'ERROR',
                html: '<div style="font-size: 15px; padding: 10px;">' + mensaje.toUpperCase() + '</div>',
                icon: tipo,
                confirmButtonText: tipo === 'success' ? 'ACEPTAR' : 'ENTENDIDO',
                backdrop: 'rgba(0, 0, 0, 0.5)',
                position: 'center',
                timer: tipo === 'success' ? 2500 : null,
                timerProgressBar: tipo === 'success',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                },
                customClass: {
                    popup: 'swal-popup swal-wide',
                    confirmButton: 'swal2-confirm'
                }
            }).then(() => {
                if (tipo === 'success') {
                    window.location.reload();
                }
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');
        }

        function mostrarAlertaContrasena(tipo, mensaje) {
            const esExito = tipo === 'success';
            Swal.fire({
                icon: esExito ? 'success' : 'error',
                title: esExito ? '¡LISTO!' : 'CONTRASEÑA INSEGURA',
                html: '<div style="font-size: 15px; padding: 10px 4px; line-height: 1.45;">' + String(mensaje || '').toUpperCase() + '</div>',
                confirmButtonText: esExito ? 'ACEPTAR' : 'ENTENDIDO',
                backdrop: 'rgba(0, 0, 0, 0.5)',
                customClass: {
                    popup: 'swal-popup swal-wide',
                    confirmButton: 'swal2-confirm'
                }
            });
        }

        function validarContrasenaSegura(contrasena, documento = '') {
            const valor = String(contrasena || '').trim();
            if (!valor) {
                return 'LA CONTRASEÑA ES REQUERIDA';
            }
            if (valor.length !== 6) {
                return 'LA CONTRASEÑA DEBE TENER EXACTAMENTE 6 DÍGITOS';
            }
            if (!/^\d{6}$/.test(valor)) {
                return 'LA CONTRASEÑA SOLO PUEDE CONTENER NÚMEROS';
            }
            if (/\s/.test(valor)) {
                return 'LA CONTRASEÑA NO DEBE TENER ESPACIOS';
            }

            const documentoLimpio = String(documento || '').replace(/\D/g, '');
            const valoresDocumento = [documentoLimpio];
            if (documentoLimpio.length >= 4) {
                valoresDocumento.push(documentoLimpio.slice(0, 4), documentoLimpio.slice(-4));
            }
            if (documentoLimpio.length >= 6) {
                valoresDocumento.push(documentoLimpio.slice(0, 6), documentoLimpio.slice(-6));
            }
            if (valoresDocumento.some((valorDoc) => valorDoc && valor.includes(valorDoc))) {
                return 'LA CONTRASEÑA NO PUEDE CONTENER EL NÚMERO DE DOCUMENTO NI SUS PRIMEROS O ÚLTIMOS DÍGITOS';
            }

            const secuencias = ['123456', '654321', '012345', '543210', '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999', '000000'];
            const patronesDebiles = [
                /^((\d{2})\2){2}$/,
                /^(\d{3})\1$/,
                /^(\d)(?:\1){5}$/
            ];
            if (secuencias.includes(valor) || patronesDebiles.some((patron) => patron.test(valor))) {
                return 'LA CONTRASEÑA NO PUEDE SER UNA SECUENCIA O COMBINACIÓN NUMÉRICA SIMPLE';
            }
            if (/(.)\1{2,}/.test(valor)) {
                return 'LA CONTRASEÑA NO PUEDE TENER DÍGITOS REPETIDOS';
            }

            return '';
        }

        async function enviarFormulario(event) {
            event.preventDefault();
            
            try {
                const form = event.target;
                const formData = new FormData(form);
                const usuarioId = document.getElementById('usuarioId').value;
                
                // Determinar si es una actualización o creación
                // Usamos trim() para eliminar espacios y verificamos que no sea vacío
                const action = (usuarioId && usuarioId.trim() !== '') ? 'update' : 'crear';
                
                console.log('ID del usuario:', usuarioId);
                console.log('Acción determinada:', action);
                
                const correo = String(formData.get('correo') || '').trim().toLowerCase();
                const telefono = String(formData.get('telefono') || '').trim();

                formData.set('correo', correo);
                formData.set('telefono', telefono);

                const esCrear = !usuarioId || String(usuarioId).trim() === '';

                if (esCrear) {
                    const contrasena = String(document.getElementById('usuarioContrasena').value || '').trim();
                    const contrasenaConfirma = String(document.getElementById('usuarioContrasenaConfirma').value || '').trim();

                    if (!contrasena) {
                        mostrarAlertaContrasena('error', 'INGRESE UNA CONTRASEÑA PARA EL USUARIO');
                        return;
                    }

                    const documento = String(document.getElementById('usuarioDocumento')?.value || '').trim();
                    const errorContrasena = validarContrasenaSegura(contrasena, documento);
                    if (errorContrasena) {
                        mostrarAlertaContrasena('error', errorContrasena);
                        return;
                    }

                    if (contrasena !== contrasenaConfirma) {
                        mostrarAlertaContrasena('error', 'LAS CONTRASEÑAS NO COINCIDEN');
                        return;
                    }

                    formData.set('contrasena', contrasena);
                } else {
                    formData.delete('contrasena');
                    formData.delete('contrasena_confirma_registro');
                }

                if (ES_SUPER_ADMIN_SESION && esCrear && esRolAdministradorJs(String(formData.get('rol') || ''))) {
                    formData.set('empresa_correo_electronico', correo);
                    formData.set('empresa_telefono', telefono);
                }

                const selectRolActivo = document.getElementById('usuarioRol');
                const hiddenRolActivo = selectRolActivo?.parentElement?.querySelector('input[type="hidden"][name="rol"]');
                let rolSeleccionado = String(selectRolActivo?.value || hiddenRolActivo?.value || formData.get('rol') || '').trim();

                if (!rolSeleccionado && selectRolActivo) {
                    const fallbackOption = Array.from(selectRolActivo.options).find((option) => String(option.value || '').trim() !== '');
                    if (fallbackOption) {
                        rolSeleccionado = String(fallbackOption.value || '').trim();
                        selectRolActivo.value = rolSeleccionado;
                    }
                }

                if (rolSeleccionado) {
                    formData.set('rol', rolSeleccionado);
                }

                const hiddenRolPrevio = form.querySelector('input[type="hidden"][name="rol"]');
                if (hiddenRolPrevio) {
                    hiddenRolPrevio.remove();
                }

                if (rolSeleccionado) {
                    const hiddenRolFinal = document.createElement('input');
                    hiddenRolFinal.type = 'hidden';
                    hiddenRolFinal.name = 'rol';
                    hiddenRolFinal.value = rolSeleccionado;
                    form.appendChild(hiddenRolFinal);
                }

                const formDataActualizada = new FormData(form);
                formDataActualizada.set('action', action);
                if (rolSeleccionado) {
                    formDataActualizada.set('rol', rolSeleccionado);
                }

                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formDataActualizada
                });
                
                console.log('Acción a enviar:', action);
                console.log('Teléfono normalizado:', telefono);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                console.log('Respuesta del servidor:', result);
                
                if (result.success) {
                    const esAdminCreado = Boolean(result.es_administrador);
                    const esClienteCreado = Boolean(result.es_cliente);
                    const correoEnviado = Boolean(result.correo_enviado);
                    const correoDestino = String(result.correo_destino || '').trim();
                    let htmlMensaje = '<div style="font-size: 15px; padding: 10px; line-height:1.45;">' + String(result.message || 'OPERACION EXITOSA').toUpperCase() + '</div>';

                    if (esClienteCreado) {
                        htmlMensaje = '<div style="font-size: 15px; padding: 10px; line-height:1.45;">EL CLIENTE FUE REGISTRADO EN LA TABLA CLIENTES' + (correoDestino ? ' CON EL CORREO ' + correoDestino.toUpperCase() : '') + '.</div>';
                    }

                    if (esAdminCreado && correoEnviado) {
                        htmlMensaje = '<div style="font-size: 15px; padding: 10px; line-height:1.45;">EL CORREO FUE ENVIADO CON EL USUARIO Y LA CONTRASENA' + (correoDestino ? ' A ' + correoDestino.toUpperCase() : '') + '.</div>';
                    }

                    if (esAdminCreado && !correoEnviado) {
                        htmlMensaje = '<div style="font-size: 15px; padding: 10px; line-height:1.45;">USUARIO CREADO, PERO EL CORREO NO SE ENVIO.</div>';
                    }

                    Swal.fire({
                        icon: esAdminCreado && !correoEnviado ? 'warning' : 'success',
                        title: '¡ÉXITO!',
                        html: htmlMensaje,
                        confirmButtonText: 'ACEPTAR',
                        timer: esAdminCreado && !correoEnviado ? null : 3000,
                        showConfirmButton: true,
                        timerProgressBar: !(esAdminCreado && !correoEnviado),
                        customClass: {
                            confirmButton: 'swal2-confirm'
                        }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    // Cerrar el modal solo si no hay error
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        html: '<div style="font-size: 15px; padding: 10px;">' + result.message.toUpperCase() + '</div>',
                        confirmButtonText: 'ENTENDIDO',
                        allowOutsideClick: false,
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'ERROR',
                    html: '<div style="font-size: 15px; padding: 10px;">HUBO UN ERROR AL PROCESAR LA SOLICITUD</div>',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputEmpresaImagenArchivo = document.getElementById('empresaImagenArchivo');
            const btnEmpresaImagenBuscar = document.getElementById('empresaImagenBuscarBtn');
            const txtEmpresaImagenArchivoNombre = document.getElementById('empresaImagenArchivoNombre');

            if (btnEmpresaImagenBuscar && inputEmpresaImagenArchivo) {
                btnEmpresaImagenBuscar.addEventListener('click', function() {
                    inputEmpresaImagenArchivo.click();
                });
            }

            if (inputEmpresaImagenArchivo) {
                inputEmpresaImagenArchivo.addEventListener('change', function() {
                    const file = this.files && this.files[0] ? this.files[0] : null;
                    if (txtEmpresaImagenArchivoNombre) {
                        txtEmpresaImagenArchivoNombre.textContent = file ? file.name : 'Ningun archivo seleccionado';
                    }
                    if (file) {
                        renderPreviewArchivoEmpresa(file);
                    }
                });
            }
            renderPreviewImagenEmpresa('');
        });

        // Cerrar el modal cuando se hace clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('registroModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Función para permitir solo números
        function soloNumeros(evt) {
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                evt.preventDefault();
                return false;
            }
            return true;
        }

        // Aplicar la restricción a los campos
        document.querySelector('input[name="telefono"]').addEventListener('keypress', soloNumeros);
        document.querySelector('input[name="documento"]').addEventListener('keypress', soloNumeros);

        document.querySelectorAll('input[name="contrasena"], input[name="contrasena_confirma_registro"], input[name="contrasena_nueva"], input[name="contrasena_confirma"], input[id="contrasenaActual"]').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        });

        // Limpiar caracteres no numéricos si se pegan
        document.querySelector('input[name="telefono"]').addEventListener('paste', function(e) {
            e.preventDefault();
            var text = (e.originalEvent || e).clipboardData.getData('text/plain');
            this.value = text.replace(/[^0-9]/g, '');
        });

        document.querySelector('input[name="documento"]').addEventListener('paste', function(e) {
            e.preventDefault();
            var text = (e.originalEvent || e).clipboardData.getData('text/plain');
            this.value = text.replace(/[^0-9]/g, '').slice(0, 10);
        });

        // Validación para el campo de teléfono
        document.querySelector('input[name="telefono"]').addEventListener('input', function(e) {
            // Remover cualquier caracter que no sea número
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limitar a 10 dígitos
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });

        document.querySelector('input[name="documento"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });

        function eliminarUsuario(id) {
            const idNumerico = Number(id);
            if (idNumerico === 1 || idNumerico === 2) {
                Swal.fire({
                    title: 'ACCESO DENEGADO',
                    html: '<div style="font-size: 15px; padding: 10px;">NO SE PUEDE ELIMINAR ESTE USUARIO</div>',
                    icon: 'error',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
                return;
            }

            Swal.fire({
                title: '¿ESTÁS SEGURO?',
                html: '<div style="font-size: 15px; padding: 10px;">ESTA ACCIÓN NO SE PUEDE DESHACER</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'SÍ, ELIMINAR',
                cancelButtonText: 'CANCELAR',
                customClass: {
                    popup: 'swal-wide swal-delete',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('usuarios.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ id: id, action: 'delete' })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡ELIMINADO!',
                                html: '<div style="font-size: 15px; padding: 10px;">EL USUARIO HA SIDO ELIMINADO CORRECTAMENTE</div>',
                                icon: 'success',
                                confirmButtonText: 'ACEPTAR',
                                timer: 2500,
                                timerProgressBar: true,
                                customClass: {
                                    popup: 'swal-wide',
                                    confirmButton: 'swal2-confirm'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            mostrarAlerta('error', data.message || 'ERROR AL ELIMINAR EL USUARIO');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'ERROR',
                            html: '<div style="font-size: 15px; padding: 10px;">ERROR AL ELIMINAR EL USUARIO</div>',
                            icon: 'error',
                            confirmButtonText: 'ENTENDIDO',
                            customClass: {
                                popup: 'swal-wide',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                    });
                }
            });
        }

        // Funciones para cambio de contraseña
        function abrirCambioContrasena() {
            const usuarioId = document.getElementById('usuarioId').value;
            if (!usuarioId) {
                Swal.fire({
                    icon: 'error',
                    title: 'ERROR',
                    html: '<div style="font-size: 15px; padding: 10px;">PRIMERO DEBE SELECCIONAR UN USUARIO</div>',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
                return;
            }

            document.getElementById('usuarioIdContrasena').value = usuarioId;
            document.getElementById('cambioContrasenaForm').reset();
            document.getElementById('cambioContrasenaError').style.display = 'none';
            document.getElementById('cambioContrasenaExito').style.display = 'none';
            document.getElementById('cambioContrasenaModal').style.display = 'flex';
        }

        function cerrarCambioContrasena() {
            document.getElementById('cambioContrasenaModal').style.display = 'none';
        }

        async function enviarCambioContrasena(event) {
            event.preventDefault();

            const usuarioId = document.getElementById('usuarioIdContrasena').value;
            const contrasenaActual = document.getElementById('contrasenaActual').value;
            const contrasenanueva = document.getElementById('contrasenanueva').value;
            const contrasenaConfirma = document.getElementById('contrasenaConfirma').value;

            // Solo super administrador puede omitir la contraseña actual
            if (!ES_SUPER_ADMIN_SESION && !contrasenaActual) {
                mostrarAlertaContrasena('error', 'LA CONTRASEÑA ACTUAL ES REQUERIDA');
                return;
            }

            // Validar que las contraseñas coincidan
            if (contrasenanueva !== contrasenaConfirma) {
                mostrarAlertaContrasena('error', 'LAS CONTRASEÑAS NO COINCIDEN');
                return;
            }

            const documento = String(document.getElementById('usuarioDocumento')?.value || '').trim();
            const errorContrasena = validarContrasenaSegura(contrasenanueva, documento);
            if (errorContrasena) {
                mostrarAlertaContrasena('error', errorContrasena);
                return;
            }

            const formData = {
                action: 'cambiar_contrasena',
                usuario_id: usuarioId,
                contrasena_actual: contrasenaActual,
                contrasenaa_actual: contrasenaActual,
                contrasena_nueva: contrasenanueva
            };

            console.log('DEBUG: Enviando cambio de contraseña', {usuarioId, contrasenaActual: '***', contrasenanueva: '***'});

            try {
                const response = await fetch('usuarios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('cambioContrasenaExito').textContent = 'CONTRASEÑA ACTUALIZADA CORRECTAMENTE';
                    document.getElementById('cambioContrasenaExito').style.display = 'block';
                    document.getElementById('cambioContrasenaError').style.display = 'none';

                    Swal.fire({
                        icon: 'success',
                        title: '¡LISTO!',
                        html: '<div style="font-size: 15px; padding: 10px 4px; line-height: 1.45;">LA CONTRASEÑA SE ACTUALIZÓ CORRECTAMENTE.</div>',
                        confirmButtonText: 'ACEPTAR',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal-popup swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });

                    setTimeout(() => {
                        cerrarCambioContrasena();
                    }, 800);
                } else {
                    mostrarAlertaContrasena('error', result.message || 'ERROR AL CAMBIAR LA CONTRASEÑA');
                    document.getElementById('cambioContrasenaExito').style.display = 'none';
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarAlertaContrasena('error', 'ERROR EN LA SOLICITUD AL SERVIDOR');
            }
        }

        // Cerrar modal de cambio de contraseña cuando se hace clic fuera
        window.addEventListener('click', function(event) {
            const modalCambioContrasena = document.getElementById('cambioContrasenaModal');
            if (event.target === modalCambioContrasena) {
                cerrarCambioContrasena();
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
                if (color) {
                    root.style.setProperty(nombre, color);
                }
            };

            const aplicarTema = (tema) => {
                if (!tema || typeof tema !== 'object') return;
                setVar('--primary-blue', tema.color_principal);
                setVar('--secondary-blue', tema.color_secundario);
                setVar('--bg', tema.color_fondo);
                setVar('--text', tema.color_texto);
                setVar('--border', tema.color_bordes);
                setVar('--button', tema.color_botones);
                setVar('--navbar', tema.color_navbar);
                setVar('--title-color', tema.color_titulos);
                setVar('--table-bg', tema.color_fondo_tabla);
                setVar('--table-text', tema.color_texto_tabla);
                setVar('--table-head', tema.color_encabezado_tabla);
                setVar('--table-zebra', tema.color_filas_alternas);
                setVar('--btn-create', tema.color_btn_crear);
                setVar('--btn-edit', tema.color_btn_editar);
                setVar('--btn-delete', tema.color_btn_eliminar);
                setVar('--focus-input', tema.color_focus_inputs);
                setVar('--primary-color', tema.color_principal);
                setVar('--primary-dark', tema.color_menu_lateral || tema.color_principal);
                setVar('--primary-light', tema.color_secundario);
            };

            const aplicarDesdeStorage = () => {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) return;
                    const payload = JSON.parse(raw);
                    if (payload && payload.theme) {
                        aplicarTema(payload.theme);
                    }
                } catch (error) {
                    // No interrumpir la vista por errores de parseo del storage.
                }
            };

            window.addEventListener('storage', (event) => {
                if (event.key === storageKey) {
                    aplicarDesdeStorage();
                }
            });

            window.addEventListener('focus', aplicarDesdeStorage);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) aplicarDesdeStorage();
            });
            document.addEventListener('DOMContentLoaded', aplicarDesdeStorage);
            aplicarDesdeStorage();
        })();
    </script>
</body>
</html>
