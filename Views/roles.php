<?php
// Si se recibió PHPSESSID como parámetro (desde iframe), usarlo para la sesión
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Models/Rol.php';
require_once ROOT_PATH . '/Models/Permiso.php';
require_once ROOT_PATH . '/Helpers/Helpers.php';

// Primero establecer la conexión a la base de datos
try {
    $db = Database::connect();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['rol'])) {
    error_log("No hay rol definido en la sesión");
    header('Location: login.php');
    exit();
}

// Verificar si el rol está activo

// Verificación robusta de permisos
$rolActual = $_SESSION['rol'] ?? '';
$rolActualNormalizado = normalizarNombreRol($rolActual);
$esSuperAdminReal = str_starts_with($rolActualNormalizado, 'superadministrador');
$esSuperAdmin = PermisosHelper::esSuperAdminSesion();
$empresaContextoActivoRoles = (!empty($_SESSION['empresa_id']) || !empty($_SESSION['userData']['empresa_id'])) && empty($_SESSION['superadmin_modo_empresa']);
$tienePermiso = $esSuperAdmin || $empresaContextoActivoRoles || PermisosHelper::tienePermiso('roles', 'ver');

if (!$tienePermiso) {
    error_log("Redirigiendo a dashboard - Sin permiso para rol: " . $rolActual);
    header('Location: dashboard.php');
    exit();
}

// Detectar si estamos en iframe (cargado desde dashboard)
$esEnIframe = isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe' || 
              (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false);

// Agregar después del session_start()
error_log("Sesión actual: " . print_r($_SESSION, true));
error_log("Rol actual: " . ($_SESSION['rol'] ?? 'No definido'));

// Inicializar la conexión a la base de datos

try {
    $rolModel = new Rol($db);
    $roles = $rolModel->obtenerRoles();
    // Agregar log para depuración
    error_log("Roles obtenidos en la vista: " . json_encode($roles));
} catch(PDOException $e) {
    error_log("Error al obtener roles: " . $e->getMessage());
    die("Error de conexión: " . $e->getMessage());
}

// Obtener todos los permisos
$permisoModel = new Permiso($db);

try {
    // Obtener los módulos directamente de la base de datos
    $queryModulos = "SELECT m.id, m.nombre 
                     FROM modulos m 
                     WHERE m.estado = 1 
                     ORDER BY m.id DESC";
    $stmtModulos = $db->prepare($queryModulos);
    $stmtModulos->execute();
    $modulos = $stmtModulos->fetchAll(PDO::FETCH_ASSOC);

    $modulosVirtuales = ['Empresas', 'Módulos_Tipos_de_Empresa', 'creditos', 'tiendas'];
    foreach ($modulosVirtuales as $moduloVirtual) {
        $existeModuloVirtual = false;
        foreach ($modulos as $moduloItem) {
            if (mb_strtolower(trim((string)$moduloItem['nombre']), 'UTF-8') === mb_strtolower($moduloVirtual, 'UTF-8')) {
                $existeModuloVirtual = true;
                break;
            }
        }

        if (!$existeModuloVirtual) {
            $modulos[] = [
                'id' => 0,
                'nombre' => $moduloVirtual
            ];
        }
    }

    // La creación de roles y permisos base se gestiona en RolController.php

    // Obtener los permisos del rol seleccionado
    if (isset($_GET['id'])) {
        $rolId = $_GET['id'];
        $queryPermisos = "SELECT pr.modulo, pr.ver, pr.crear, pr.actualizar, pr.eliminar
                          FROM permisos_roles pr
                          WHERE pr.rol_id = ?";
        $stmt = $db->prepare($queryPermisos);
        $stmt->execute([$rolId]);
        $permisosActuales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permisosActuales[$row['modulo']] = [
                'ver' => $row['ver'],
                'crear' => $row['crear'],
                'actualizar' => $row['actualizar'],
                'eliminar' => $row['eliminar']
            ];
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener módulos: " . $e->getMessage());
}

// Verificar permisos al inicio
$tienePermisoEditar = $esSuperAdminReal || $empresaContextoActivoRoles || PermisosHelper::tienePermiso('roles', 'actualizar');
$tienePermisoEliminar = $esSuperAdminReal || $empresaContextoActivoRoles || PermisosHelper::tienePermiso('roles', 'eliminar');
$tienePermisoCrear = $esSuperAdminReal || $empresaContextoActivoRoles || PermisosHelper::tienePermiso('roles', 'crear');
$tieneAlgunPermiso = $tienePermisoEditar || $tienePermisoEliminar;

// Mostrar columna ID siempre para consistencia
$mostrarColumnaId = true;

// Colores de la empresa (paleta completa, uniforme con usuarios.php)
$coloresEmpresaDefault = [
    'color_principal'        => '#3591CA',
    'color_secundario'       => '#3591CA',
    'color_menu_lateral'     => '#3591CA',
    'color_botones'          => '#3591CA',
    'color_fondo'            => '#F8F9FA',
    'color_texto'            => '#3591CA',
    'color_bordes'           => '#D8DFE5',
    'color_navbar'           => '#3591CA',
    'color_iconos_menu'      => '#FFFFFF',
    'color_hover_menu'       => '#3D6175',
    'color_titulos'          => '#3591CA',
    'color_links'            => '#3591CA',
    'color_fondo_tabla'      => '#FFFFFF',
    'color_texto_tabla'      => '#3591CA',
    'color_encabezado_tabla' => '#3591CA',
    'color_filas_alternas'   => '#F8F9FA',
    'color_btn_crear'        => '#3591CA',
    'color_btn_editar'       => '#3591CA',
    'color_btn_eliminar'     => '#DC3545',
    'color_focus_inputs'     => '#3591CA',
];

$coloresEmpresa = $coloresEmpresaDefault;
if (isset($_SESSION['colores_empresa']) && is_array($_SESSION['colores_empresa'])) {
    foreach ($coloresEmpresa as $clave => $defecto) {
        $valor = trim((string)($_SESSION['colores_empresa'][$clave] ?? $defecto));
        if ($valor !== '' && $valor[0] !== '#') {
            $valor = '#' . ltrim($valor, '#');
        }
        $coloresEmpresa[$clave] = $valor;
    }
}

$empresaIdSesionRoles = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
if ($empresaIdSesionRoles > 0) {
    try {
        $existeTablaColoresR = dbTableExists($db, 'colores_empresa');
        if ($existeTablaColoresR) {
            $colsDisponiblesR = [];
            foreach (array_keys($coloresEmpresaDefault) as $col) {
                if (dbColumnExists($db, 'colores_empresa', $col)) {
                    $colsDisponiblesR[] = $col;
                }
            }
            if (!empty($colsDisponiblesR)) {
                $sqlColoresR = "SELECT " . implode(', ', $colsDisponiblesR) . " FROM colores_empresa WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 1";
                $stmtColoresR = $db->prepare($sqlColoresR);
                $stmtColoresR->bindValue(':empresa_id', $empresaIdSesionRoles, PDO::PARAM_INT);
                $stmtColoresR->execute();
                $filaColoresR = $stmtColoresR->fetch(PDO::FETCH_ASSOC);
                if (is_array($filaColoresR) && !empty($filaColoresR)) {
                    foreach ($coloresEmpresa as $clave => $defecto) {
                        if (array_key_exists($clave, $filaColoresR)) {
                            $valor = trim((string)$filaColoresR[$clave]);
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
        error_log('Error al cargar colores de empresa en roles.php: ' . $e->getMessage());
    }
}

// Ajustar el número de columnas según permisos y visibilidad de ID
$numColumnas = ($tieneAlgunPermiso ? 4 : 3) + ($mostrarColumnaId ? 1 : 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
    <style>
        /* Estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-blue: <?= htmlspecialchars($coloresEmpresa['color_principal']); ?>;
            --secondary-blue: <?= htmlspecialchars($coloresEmpresa['color_secundario']); ?>;
            --bg: <?= htmlspecialchars($coloresEmpresa['color_fondo']); ?>;
            --text: <?= htmlspecialchars($coloresEmpresa['color_texto']); ?>;
            --border: <?= htmlspecialchars($coloresEmpresa['color_bordes']); ?>;
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

        body {
            padding-top: <?php echo $esEnIframe ? '0' : '80px'; ?>;
            background-color: var(--bg);
            color: var(--text);
            font-family: var(--font-saira);
            box-sizing: border-box;
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
            width: 12px;
        }

        .main-scroll-panel::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 8px;
        }

        .main-scroll-panel::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3591CA 0%, #2575a8 100%);
            border-radius: 8px;
            border: 2px solid #f4f6f8;
        }

        .main-scroll-panel::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2575a8 0%, #1e5a8e 100%);
        }

        .estadistica-card {
            background: white;
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

        /* Asegurar que todos los elementos internos hereden el box-sizing */
        .estadistica-card *,
        .modal-content *,
        .form-group * {
            box-sizing: inherit;
        }

        /* Ajustar inputs y elementos de formulario */
        input,
        select,
        textarea,
        button {
            box-sizing: border-box;
            width: 100%;
        }

        /* Estilos para la tabla principal de roles */
        .table-wrapper {
            overflow-x: auto;
            overflow-y: scroll;
            flex: 1;
            min-height: 0;
            height: 100%;
            scrollbar-width: thin;
            position: relative;
            scrollbar-color: linear-gradient(135deg, #3591CA 0%, #2575a8 100%) #f4f6f8;
            border-radius: 8px;
        }

        .table-wrapper::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 6px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3591CA 0%, #2575a8 100%);
            border-radius: 6px;
            border: 2px solid #f4f6f8;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2575a8 0%, #1e5a8e 100%);
        }

        .table-wrapper::-webkit-scrollbar-corner {
            background: #f4f6f8;
        }

        .roles-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            box-sizing: border-box;
            text-transform: uppercase;
            table-layout: fixed;
            border-radius: 8px;
        }
        
        .roles-table thead {
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .roles-table th, 
        .roles-table td {
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

        .roles-table th {
            background: white;
            color: #2f4a5a;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 2px solid #2f4a5a;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(47, 74, 90, 0.08);
        }

        .roles-table tr:hover {
            background-color: rgba(47, 74, 90, 0.04);
            transition: background-color 0.2s ease;
        }

        .roles-table tbody tr:nth-child(even) {
            background: var(--table-zebra);
        }

        /* Tamaños de columnas */
        .roles-table td:nth-child(2) {
            width: 25%;
            font-size: 14px;
            padding: 12px 15px;
            color: #333;
        }
        
        .roles-table td:nth-child(3) {
            width: 40%;
            font-size: 14px;
            padding: 12px 15px;
            color: #333;
        }
        
        .roles-table td:nth-child(4) {
            width: 10%;
            font-size: 13px;
            padding: 12px 8px;
            color: #333;
        }
        
        .roles-table td:nth-child(5) {
            width: 15%;
            font-size: 12px;
            padding: 12px 8px;
            color: #333;
        }
        
        .roles-table th:nth-child(2) {
            width: 25%;
            padding: 15px 15px;
        }
        
        .roles-table th:nth-child(3) {
            width: 40%;
            padding: 15px 15px;
        }
        
        .roles-table th:nth-child(4) {
            width: 10%;
            padding: 15px 8px;
        }
        
        .roles-table th:nth-child(5) {
            width: 15%;
            padding: 15px 8px;
        }

       

        /* Estilos para el scroll */
        .table-container::-webkit-scrollbar,
        .table-wrapper::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        .table-container::-webkit-scrollbar-track,
        .table-wrapper::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 6px;
        }

        .table-container::-webkit-scrollbar-thumb,
        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3591CA 0%, #2575a8 100%);
            border-radius: 6px;
            border: 2px solid #f4f6f8;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover,
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2575a8 0%, #1e5a8e 100%);
        }

        /* Ajustar los botones de acción */
        .button-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            min-width: 200px;
            margin: 0 auto;
        }

        .btn-editar,
        .btn-eliminar {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-width: 85px;
            height: 36px;
            text-transform: uppercase;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        /* Ajustar el modal - Centrado perfecto */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(47, 74, 90, 0.5);
            backdrop-filter: blur(4px);
            overflow-y: auto;
        }
        
        /* Cuando el modal está visible */
        .modal[style*="display: block"],
        .modal[style*="display: flex"] {
            display: flex !important;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 14px;
            box-shadow: 0 12px 36px rgba(20, 30, 40, 0.15), 0 0 0 1px rgba(20, 30, 40, 0.05);
            max-width: 1000px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            text-transform: uppercase;
            animation: modalFadeIn 0.3s ease-out;
            margin: auto;
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

        .button_Volver_Atras{
            position: absolute;
            left: 20px;
            top: 45px;
            background-color: transparent;
            border: 2px solid var(--navbar);
            color: var(--navbar);
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
            background-color: var(--navbar);
            color: white;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
            transform: translateX(-3px);
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
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            font-weight: 700;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
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

        /* Contenedor del botón */
        .container-button {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            box-sizing: border-box;
        }

        /* Modificar los estilos de los botones */
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
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-editar i,
        .btn-eliminar i {
            font-size: 14px;
        }

        .btn-editar {
            background: var(--btn-edit);
            color: white;
        }
        
        .btn-editar:hover {
            background: var(--btn-edit);
            filter: brightness(0.85);
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
            transform: translateY(-2px);
        }

        .btn-eliminar {
            background: var(--btn-delete);
            color: white;
        }
        
        .btn-eliminar:hover {
            background: var(--btn-delete);
            filter: brightness(0.85);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-editar:disabled,
        .btn-eliminar:disabled {
            background: #b0b0b0 !important;
            color: #ffffff !important;
            cursor: not-allowed;
            opacity: 0.65;
            box-shadow: none;
            filter: none;
            pointer-events: none;
        }

        .toggle-switch input:disabled + .slider {
            background: #c6c6c6 !important;
            cursor: not-allowed;
        }

        .toggle-switch input:disabled + .slider:before {
            background: #ffffff !important;
        }

        .btn-editar:active,
        .btn-eliminar:active {
            transform: translateY(0);
        }

        /* Ajustar el espaciado entre botones */
        .button-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }

        /* Agregar tooltips para los botones */
        .btn-editar[title],
        .btn-eliminar[title] {
            position: relative;
        }

        /* Contenedor de tabla de permisos */
        .permisos-table-container {
            max-height: 50vh;
            overflow-y: auto;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
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
            color: white;
            background: var(--navbar);
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 20px;
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
        .form-group textarea {
            border: 1px solid #e6e9ee;
            border-radius: 8px;
            padding: 14px 16px;
            width: 100%;
            font-size: 15px;
            text-transform: uppercase;
            background: #ffffff;
            color: #242629;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
            resize: vertical;
            min-height: 50px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: var(--focus-input);
        }

        .btn-save {
            background: transparent;
            color: var(--btn-create);
            padding: 14px 30px;
            border: 2px solid var(--btn-create);
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            width: 100%;
        }

        .btn-save:hover {
            background: var(--btn-create);
            color: white;
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        /* Centrar los botones en la columna de acciones */
        .button-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        /* Ajustar el ancho de las columnas */
        .roles-table th:nth-child(1) { width: 5%; }  /* ID */
        .roles-table th:nth-child(2) { width: 18%; text-align: center; } /* Nombre */
        .roles-table th:nth-child(3) { width: 40%; text-align: center; } /* Descripción - más grande */
        .roles-table th:nth-child(4) { width: 12%; text-align: center; } /* Estado */
        .roles-table th:nth-child(5) { width: 25%; text-align: center; } /* Acciones */

        /* Centrar el contenido de las celdas */
        .roles-table th:nth-child(1),
        .roles-table th:nth-child(2),
        .roles-table th:nth-child(3),
        .roles-table th:nth-child(4),
        .roles-table th:nth-child(5),
        .roles-table td:nth-child(1),
        .roles-table td:nth-child(2),
        .roles-table td:nth-child(3),
        .roles-table td:nth-child(4),
        .roles-table td:nth-child(5) {
            text-align: center;
            vertical-align: middle;
        }

        /* Ajustar el botón de estado */
        .btn-estado {
            min-width: 100px;
            justify-content: center;
            margin: 0 auto;
            display: flex;
            padding: 8px 15px;
        }

        /* Ajustar el contenedor de acciones */
        .button-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
        }

        /* Agregar estos estilos */
        .modal-content h2 {
            background: transparent;
            color: #2f4a5a;
            text-align: center;
            margin: -30px -30px 20px -30px;
            padding: 20px;
            border-bottom: 2px solid #2f4a5a;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 24px;
            text-transform: uppercase;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .permisos-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1rem;
        }

        .permisos-table th {
            background-color: white;
            color: #2f4a5a;
            font-weight: 600;
            padding: 1rem;
            text-align: center;
            font-size: 0.9rem;
            text-transform: uppercase;
            border-bottom: 2px solid #2f4a5a;
            box-shadow: 0 2px 4px rgba(47, 74, 90, 0.08);
        }

        .permisos-table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.9rem;
        }

        .permisos-table tbody tr {
            background-color: var(--white);
            transition: background-color 0.3s ease;
        }

        .permisos-table tbody tr:hover {
            background-color: rgba(32, 56, 100, 0.05);
        }

        /* Estilos para los switches de permisos */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-switch .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .toggle-switch .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .toggle-switch input:checked + .slider {
            background-color: var(--primary-blue);
        }

        .toggle-switch input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Contenedor de la tabla de permisos */
        .permisos-table-container {
            max-height: 60vh;
            overflow-y: auto;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Mantener el encabezado fijo */
        .permisos-table thead {
            position: sticky;
            top: 0;
            z-index: 50 !important;
            background-color: white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Estilos para la barra de desplazamiento */
        .permisos-table-container::-webkit-scrollbar {
            width: 8px;
        }

        .permisos-table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .permisos-table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3591CA 0%, #2575a8 100%);
            border-radius: 6px;
        }

        .permisos-table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2575a8 0%, #3591CA 100%);
        }

        .swal-wide {
            width: 500px !important;
            max-width: 90% !important;
        }
        
        .swal2-html-container {
            line-height: 1.6 !important;
        }

        .swal2-confirm {
            background: transparent !important;
            color: var(--primary-blue) !important;
            border: 2px solid var(--primary-blue) !important;
            border-radius: 8px !important;
            padding: 12px 28px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-confirm:hover {
            background: var(--primary-blue) !important;
            color: white !important;
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35) !important;
            transform: translateY(-2px) !important;
        }
        
        .swal2-confirm:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25) !important;
        }
        
        .swal2-cancel {
            background: transparent !important;
            color: #6c757d !important;
            border: 2px solid #6c757d !important;
            border-radius: 8px !important;
            padding: 12px 28px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-cancel:hover {
            background: #6c757d !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3) !important;
            transform: translateY(-2px) !important;
        }
        
        .swal2-cancel:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 6px rgba(108, 117, 125, 0.2) !important;
        }

        /* Asegurar que los botones estén lado a lado */
        .swal2-actions {
            display: flex !important;
            flex-direction: row !important;
            gap: 12px !important;
            justify-content: center !important;
            flex-wrap: wrap !important;
        }

        .swal2-actions button,
        .swal2-actions .swal2-confirm,
        .swal2-actions .swal2-cancel {
            width: auto !important;
            min-width: 120px !important;
            display: inline-flex !important;
        }

        .swal2-deny,
        .swal2-actions button.swal2-deny {
            display: none !important;
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
            z-index: 10001 !important;
        }
        
        .swal2-title {
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: var(--title-color) !important;
        }

        .swal2-html-container {
            line-height: 1.6 !important;
        }

        .swal-wide {
            width: 500px !important;
            max-width: 90% !important;
        }
        
        /* Estilos específicos para alertas de eliminación */
        .swal2-popup.swal-delete .swal2-confirm {
            background: transparent !important;
            color: #dc3545 !important;
            border: 2px solid #dc3545 !important;
        }
        
        .swal2-popup.swal-delete .swal2-confirm:hover {
            background: #dc3545 !important;
            color: white !important;
            box-shadow: 0 6px 18px rgba(220, 53, 69, 0.35) !important;
            transform: translateY(-2px) !important;
        }
        
        .swal2-popup.swal-delete .swal2-confirm:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.25) !important;
        }

        /* Estilos para los iconos en los encabezados */
        .roles-table th i,
        .permisos-table th i {
            margin-right: 8px;
        }

        /* Estilos para los iconos en los labels */
        .form-group label i {
            margin-right: 8px;
        }

        /* Estilos para los iconos en los botones */
        .btn-save i {
            margin-right: 8px;
        }

        /* Ajustar el espaciado de los iconos en los encabezados */
        th i {
            margin-right: 8px;
        }

        /* Agregar estos estilos dentro de la sección de estilos existente */
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

        /* Agregar dentro de la sección de estilos existente */
        .advertencia-nombre {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .advertencia-nombre i {
            color: #dc3545;
        }

        input[readonly] {
            background-color: #ffebee !important;
            color: #dc3545 !important;
            cursor: not-allowed !important;
        }

        /* Estilos para el botón de guardar permisos */
        .buttons {
            margin-top: 20px;
            text-align: center;
        }

        .buttons .btn-save {
            background: var(--btn-create);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            max-width: 300px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.2);
        }

        .buttons .btn-save:hover {
            background: var(--btn-create);
            filter: brightness(0.85);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        .buttons .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
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

        body.modal-open {
            padding-right: 0 !important;
        }

        /* Asegurar que los modales tengan scroll interno si es necesario */
        .modal-content {
            scrollbar-width: thin;
            scrollbar-color: #2f4a5a #f0f0f0;
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #2f4a5a 100%);
        }

        .main-scroll-panel {
            height: calc(100vh - 210px) !important;
            max-height: calc(100vh - 210px) !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            scrollbar-width: thin !important;
            scrollbar-color: #2f4a5a #f4f6f8 !important;
        }

        .estadistica-card {
            max-height: none !important;
            overflow: visible !important;
        }

        .table-wrapper,
        .table-container,
        .permisos-table-container {
            overflow: visible !important;
            overflow-x: visible !important;
            overflow-y: visible !important;
            max-height: none !important;
            height: auto !important;
            scrollbar-width: none !important;
            padding-right: 0 !important;
        }

        .main-scroll-panel .roles-table {
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
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 6px;
            border: 1px solid #f4f6f8;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover,
        .table-container::-webkit-scrollbar-thumb:hover,
        .permisos-table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
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
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1a2d4f 100%);
            color: white;
        }

        .btn-editar:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
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
            padding: 40px 20px;
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
            border: 1px solid #e6e9ee;
            border-radius: 8px;
            padding: 14px 16px;
            width: 100%;
            font-size: 15px;
            text-transform: uppercase;
            background: #ffffff;
            color: #242629;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: var(--primary-blue);
        }

        .form-group input::placeholder {
            text-transform: uppercase;
            opacity: 0.6;
            color: #6b7280;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1a2d4f 100%);
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
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.2);
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
</head>
<body>
    <div class="title_equipo">
        <h1><i class="fas fa-user-shield"></i> ROLES</h1>
    </div>

    <div class="container main-scroll-panel">
        <?php if ($tienePermisoCrear): ?>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; margin-bottom: 18px;">
            <div style="display:flex; gap:8px; align-items:center;">
                <button class="btn-nuevo" onclick="mostrarModalRol()">
                    <i class="fas fa-plus"></i> Nuevo Rol
                </button>
            </div>
            <?php if (PermisosHelper::esSuperAdminSesion() && empty($_SESSION['superadmin_modo_empresa'])): ?>
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <button type="button" class="btn-nuevo btn-nuevo-reset" onclick="confirmarReinicioRoles()">
                    <i class="fas fa-broom"></i> REINICIAR
                </button>
                <button type="button" class="btn-nuevo btn-nuevo-undo" onclick="confirmarDeshacerReinicioRoles()">
                    <i class="fas fa-undo"></i> DESHACER
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="estadistica-card">
            <div class="table-wrapper">
                <table class="roles-table">
                    <thead>
                    <tr>
                        <?php if ($mostrarColumnaId): ?>
                        <th><i class="fas fa-hashtag"></i>ID</th>
                        <?php endif; ?>
                        <th><i class="fas fa-tag"></i> Nombre</th>
                        <th><i class="fas fa-file-alt"></i> Descripción</th>
                        <th><i class="fas fa-toggle-on"></i> Estado</th>
                        <?php if ($tieneAlgunPermiso): ?>
                            <th><i class="fas fa-tools"></i> Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($roles)) {
                        echo "<tr><td colspan='" . $numColumnas . "'>No hay roles registrados</td></tr>";
                    } else {
                        foreach ($roles as $rol): 
                            $nombreRolNormalizado = normalizarNombreRol($rol['nombre'] ?? '');
                            if (!$esSuperAdminReal && $nombreRolNormalizado === 'superadministrador') {
                                continue;
                            }
                    ?>
                    <tr data-rol-id="<?php echo $rol['id']; ?>">
                        <?php if ($mostrarColumnaId): ?>
                        <td><?php echo htmlspecialchars($rol['id']); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($rol['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($rol['descripcion'] ?? 'Sin Descripción'); ?></td>
                        <td>
                            <?php 
                            // Verificar si el rol actual es Super Administrador o Administrador
                            $nombreRolNormalizado = normalizarNombreRol($rol['nombre'] ?? '');
                            $esSuperAdmin = $nombreRolNormalizado === 'superadministrador';
                            $esAdmin = $nombreRolNormalizado === 'administrador';
                            $usuarioEsAdmin = $rolActualNormalizado === 'administrador';
                            
                            // Proteger tanto Super Administrador como Administrador
                            $esRolProtegido = $esSuperAdmin || ($esAdmin && $usuarioEsAdmin);
                            
                            // Verificar si el usuario tiene permiso para editar roles
                            $puedeEditarEstado = $tienePermisoEditar && !$esRolProtegido;
                            ?>
                            <div class="toggle-switch">
                                <input type="checkbox" 
                                       id="estado_<?php echo $rol['id']; ?>" 
                                       <?php echo ($rol['estado'] ?? false) ? 'checked' : ''; ?>
                                       <?php echo $puedeEditarEstado ? 'onchange="toggleEstadoRol(' . $rol['id'] . ', this.checked)"' : 'disabled'; ?>
                                       <?php echo !$puedeEditarEstado ? 'title="No puedes cambiar el estado de este rol"' : ''; ?>>
                                <label for="estado_<?php echo $rol['id']; ?>" class="slider"></label>
                            </div>
                        </td>
                        <?php if ($tieneAlgunPermiso): ?>
                        <td>
                            <div class="button-actions">
                                <?php if ($tienePermisoEditar && ($rol['estado'] ?? false)): ?>
                                    <?php
                                    $nombreRolNormalizado = normalizarNombreRol($rol['nombre'] ?? '');
                                    $esRolProtegido = $nombreRolNormalizado === 'superadministrador';
                                    $esRolAdmin = $nombreRolNormalizado === 'administrador';
                                    $esAdminSesion = $rolActualNormalizado === 'administrador';
                                    $esSuperAdminSesion = $rolActualNormalizado === 'superadministrador';
                                    $mensajeTooltip = $esRolProtegido ? 'Solo se puede editar la Descripción' : 'Editar';
                                    $bloquearAccionesSuper = ($rol['nombre'] === 'Super Administrador' && !$esSuperAdminReal); 
                                    ?>
                                    <button onclick="editarRol(<?php echo htmlspecialchars(json_encode($rol, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>)" 
                                            class="btn-editar" 
                                            title="<?php echo $bloquearAccionesSuper ? 'No se puede editar el rol Super Administrador' : $mensajeTooltip; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if ($tienePermisoEliminar): ?>
                                    <?php 
                                    // Verificar las condiciones para deshabilitar el botón
                                    $nombreRolNormalizado = $nombreRolNormalizado ?? normalizarNombreRol($rol['nombre'] ?? '');
                                    $esSuperAdmin = $nombreRolNormalizado === 'superadministrador';
                                    $esAdmin = $nombreRolNormalizado === 'administrador';
                                    $usuarioEsAdmin = $rolActualNormalizado === 'administrador';
                                    $usuarioEsSuperAdmin = $rolActualNormalizado === 'superadministrador';
                                    
                                    // El botón se deshabilita para Super Administrador y Administrador en todos los casos, ya que no pueden eliminarse.
                                    $deshabilitarEliminar = $esSuperAdmin || $esAdmin;
                                    
                                    $mensajeTooltip = '';
                                    if ($esSuperAdmin) {
                                        $mensajeTooltip = 'No se puede eliminar el rol Super Administrador';
                                    } elseif ($esAdmin) {
                                        $mensajeTooltip = 'No se puede eliminar el rol Administrador';
                                    }
                                    ?>
                                    <button type="button" onclick="eliminarRol(event, <?php echo $rol['id']; ?>)" 
                                            class="btn-eliminar" 
                                            <?php echo $deshabilitarEliminar ? 'disabled' : ''; ?>
                                            title="<?php echo $mensajeTooltip; ?>">
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

    <!-- Modal para crear/editar rol -->
    <div id="modalRol" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModalRol()">&times;</span>
            <h2 id="modalTitle">Nuevo Rol</h2>
            <form id="rolForm" autocomplete="off">
                <input type="hidden" id="rolId" name="id">
                <input type="hidden" id="idOriginal" name="idOriginal">
                <div class="form-group">
                    <label for="idDisplay"><i class="fas fa-hashtag"></i> ID:</label>
                    <input type="text" id="idDisplay" name="idDisplay" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="nombre"><i class="fas fa-tag"></i> Nombre:</label>
                    <input type="text" id="nombre" name="nombre" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="descripcion"><i class="fas fa-file-alt"></i> Descripción:</label>
                    <textarea id="descripcion" name="descripcion" rows="5" placeholder="Ingrese una Descripción detallada del rol..."></textarea>
                </div>
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> ACTUALIZAR ROL
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.all.min.js"></script>
    <script>
        const base_url = <?= json_encode(base_url()) ?>;
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

            const contrastColor = (hex) => {
                const limpio = normalizarHex(hex).replace('#', '');
                if (!limpio) return '#FFFFFF';
                const r = parseInt(limpio.substring(0, 2), 16);
                const g = parseInt(limpio.substring(2, 4), 16);
                const b = parseInt(limpio.substring(4, 6), 16);
                const luminancia = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminancia > 0.56 ? '#1A1A1A' : '#FFFFFF';
            };

            const ensureOverrideStyle = () => {
                let style = document.getElementById('companyThemeLiveOverrides');
                if (!style) {
                    style = document.createElement('style');
                    style.id = 'companyThemeLiveOverrides';
                    document.head.appendChild(style);
                }
                return style;
            };

            const aplicarTema = (tema) => {
                if (!tema || typeof tema !== 'object') return;
                setVar('--company-primary', tema.color_principal);
                setVar('--company-secondary', tema.color_secundario);
                setVar('--company-sidebar', tema.color_menu_lateral || tema.color_principal);
                setVar('--company-button', tema.color_botones || tema.color_principal);
                setVar('--company-background', tema.color_fondo);
                setVar('--company-text', tema.color_texto);
                setVar('--company-border', tema.color_bordes);
                setVar('--company-navbar', tema.color_navbar || tema.color_principal);
                setVar('--company-icon-color', tema.color_iconos_menu);
                setVar('--company-hover-menu', tema.color_hover_menu || tema.color_secundario);
                setVar('--company-title-color', tema.color_titulos || tema.color_texto);
                setVar('--company-link-color', tema.color_links || tema.color_principal);
                setVar('--company-table-bg', tema.color_fondo_tabla);
                setVar('--company-table-text', tema.color_texto_tabla || tema.color_texto);
                setVar('--company-table-header', tema.color_encabezado_tabla || tema.color_principal);
                setVar('--company-table-row-alt', tema.color_filas_alternas || tema.color_fondo);
                setVar('--company-btn-crear', tema.color_btn_crear || tema.color_botones || tema.color_principal);
                setVar('--company-btn-editar', tema.color_btn_editar || tema.color_botones || tema.color_principal);
                setVar('--company-btn-eliminar', tema.color_btn_eliminar || '#DC3545');
                setVar('--company-focus', tema.color_focus_inputs || tema.color_principal);
                setVar('--primary-blue', tema.color_principal);
                setVar('--secondary-blue', tema.color_secundario);
                setVar('--primary-color', tema.color_principal);
                setVar('--primary-dark', tema.color_menu_lateral || tema.color_principal);
                setVar('--primary-light', tema.color_secundario);
                setVar('--text-color', tema.color_texto);
                setVar('--bg', tema.color_fondo);
                setVar('--border', tema.color_bordes);
                setVar('--btn-edit', tema.color_btn_editar);
                setVar('--btn-delete', tema.color_btn_eliminar);
                setVar('--focus-input', tema.color_focus_inputs);
            };

            const aplicarOverrides = (tema) => {
                if (!tema || typeof tema !== 'object') return;
                const style = ensureOverrideStyle();
                const c = {
                    bg: normalizarHex(tema.color_fondo) || '#F4F6F8',
                    text: normalizarHex(tema.color_texto) || '#2F4A5A',
                    border: normalizarHex(tema.color_bordes) || '#DFF3DE',
                    navbar: normalizarHex(tema.color_navbar) || normalizarHex(tema.color_principal) || '#2F4A5A',
                    sidebar: normalizarHex(tema.color_menu_lateral) || normalizarHex(tema.color_principal) || '#2F4A5A',
                    icon: normalizarHex(tema.color_iconos_menu) || '#FFFFFF',
                    hover: normalizarHex(tema.color_hover_menu) || normalizarHex(tema.color_secundario) || '#3D6175',
                    title: normalizarHex(tema.color_titulos) || normalizarHex(tema.color_texto) || '#2F4A5A',
                    link: normalizarHex(tema.color_links) || normalizarHex(tema.color_principal) || '#2F4A5A',
                    tableBg: normalizarHex(tema.color_fondo_tabla) || '#FFFFFF',
                    tableText: normalizarHex(tema.color_texto_tabla) || normalizarHex(tema.color_texto) || '#2F4A5A',
                    tableHead: normalizarHex(tema.color_encabezado_tabla) || normalizarHex(tema.color_principal) || '#2F4A5A',
                    tableZebra: normalizarHex(tema.color_filas_alternas) || normalizarHex(tema.color_fondo) || '#F4F6F8',
                    btn: normalizarHex(tema.color_botones) || normalizarHex(tema.color_principal) || '#2F4A5A',
                    btnCreate: normalizarHex(tema.color_btn_crear) || normalizarHex(tema.color_botones) || '#2F4A5A',
                    btnEdit: normalizarHex(tema.color_btn_editar) || normalizarHex(tema.color_botones) || '#2F4A5A',
                    btnDelete: normalizarHex(tema.color_btn_eliminar) || '#DC3545',
                    focus: normalizarHex(tema.color_focus_inputs) || normalizarHex(tema.color_principal) || '#2F4A5A',
                };

                style.textContent = `
                    body { background: ${c.bg} !important; color: ${c.text} !important; }
                    h1, h2, h3, h4, h5, h6, .title_equipo, .main-title, .section-title, .modal-title, .swal2-title { color: ${c.title} !important; }
                    a, .link, .company-link { color: ${c.link} !important; }
                    .topbar, .navbar, .header, .header-bar, .table-header { background: ${c.navbar} !important; color: ${contrastColor(c.navbar)} !important; }
                    .sidebar, .menu, .menu-lateral, .left-menu { background: ${c.sidebar} !important; }
                    .menu a, .menu-item, .menu-item a, .sidebar a { color: ${c.icon} !important; }
                    .menu-item.active, .menu a.active, .sidebar .active { background: ${c.hover} !important; color: ${contrastColor(c.hover)} !important; }
                    .container, .main-container, .card, .panel, .box, .modal-content, .swal2-popup { border-color: ${c.border} !important; }
                    table, .table, .dataTable { background: ${c.tableBg} !important; color: ${c.tableText} !important; }
                    tbody tr:nth-child(even), .table-striped tbody tr:nth-child(even) { background: ${c.tableZebra} !important; }
                    tbody td, .table tbody td { color: ${c.tableText} !important; }
                    btn, .btn-primary, .btn-save, .save-btn { background: ${c.btn} !important; border-color: ${c.btn} !important; color: ${contrastColor(c.btn)} !important; }
                    .btn-back, .button_Volver_Atras { background: transparent !important; border-color: ${c.btn} !important; color: ${c.btn} !important; }
                    .btn-back:hover, .button_Volver_Atras:hover { background: ${c.btn} !important; color: ${contrastColor(c.btn)} !important; }
                    .btn-create, .btn-add, .btn-agregar, .btn-success { background: ${c.btnCreate} !important; border-color: ${c.btnCreate} !important; color: ${contrastColor(c.btnCreate)} !important; }
                    .btn-edit, .btn-warning, .btn-icon.edit, .edit-btn { background: ${c.btnEdit} !important; border-color: ${c.btnEdit} !important; color: ${contrastColor(c.btnEdit)} !important; }
                    .btn-delete, .btn-danger, .btn-icon.delete, .delete-btn { background: ${c.btnDelete} !important; border-color: ${c.btnDelete} !important; color: ${contrastColor(c.btnDelete)} !important; }
                    input, select, textarea { border-color: ${c.border} !important; }
                    input:focus, select:focus, textarea:focus { border-color: ${c.focus} !important; box-shadow: 0 0 0 2px ${c.focus}33 !important; outline: none !important; }
                `;
            };

            const aplicarDesdeStorage = () => {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) return;
                    const payload = JSON.parse(raw);
                    if (!payload || !payload.theme) return;
                    aplicarTema(payload.theme);
                    aplicarOverrides(payload.theme);
                } catch (error) {
                    // Ignorar errores del storage para no afectar el modulo.
                }
            };

            window.addEventListener('storage', (event) => {
                if (event.key === storageKey) aplicarDesdeStorage();
            });
            window.addEventListener('focus', aplicarDesdeStorage);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) aplicarDesdeStorage();
            });
            document.addEventListener('DOMContentLoaded', aplicarDesdeStorage);
            aplicarDesdeStorage();
        })();

        // Manejar ocultación de headers sticky cuando se abren modales
        (function() {
            // Función para ocultar headers (solo roles-table)
            function hideHeaders() {
                const headers = document.querySelectorAll('.roles-table thead, .roles-table thead th');
                headers.forEach(header => {
                    header.style.position = 'relative';
                    header.style.zIndex = '-1';
                });
            }
            
            // Función para mostrar headers
            function showHeaders() {
                const headers = document.querySelectorAll('.roles-table thead, .roles-table thead th');
                headers.forEach(header => {
                    header.style.position = 'sticky';
                    header.style.zIndex = '5';
                });
            }
            
            // Interceptar SweetAlert2
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

        // Definir funciones globales
        const rolActualSesion = <?= json_encode($_SESSION['rol'] ?? '') ?>;
        const normalizarTextoRol = (valor) => {
            const texto = String(valor || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
            return texto === 'super administrador' ? 'superadministrador' : texto;
        };
        const getRolActualNormalizado = () => normalizarTextoRol(rolActualSesion);
        const parseJsonResponse = async (response) => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (error) {
                console.error('Invalid JSON response from server:', text);
                return {
                    success: false,
                    message: 'Respuesta inválida del servidor. Revisa la consola para más detalles.'
                };
            }
        };
        const esRolProtegidoParaSesion = (nombreRol) => {
            const nombre = normalizarTextoRol(nombreRol);
            return nombre === 'superadministrador' || (nombre === 'administrador' && getRolActualNormalizado() === 'administrador');
        };
        const puedeEditarNombreRol = (nombreRol) => {
            const nombre = normalizarTextoRol(nombreRol);
            const rolActual = getRolActualNormalizado();
            if (rolActual === 'superadministrador') return true;
            if (rolActual === 'administrador') return nombre !== 'administrador';
            return true;
        };

        function mostrarModalRol() {
            document.getElementById('modalTitle').textContent = 'Nuevo Rol';
            const form = document.getElementById('rolForm');
            form.reset();
            
            // Cambiar texto del botón a GUARDAR ROL
            const boton = document.querySelector('#rolForm button[type="submit"]');
            boton.innerHTML = '<i class="fas fa-save"></i> GUARDAR ROL';
            
            // Limpiar completamente todos los inputs
            document.getElementById('rolId').value = '';
            document.getElementById('idOriginal').value = '';
            document.getElementById('idDisplay').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('descripcion').value = '';
            
            // Limpiar atributos
            const inputNombre = document.getElementById('nombre');
            const inputDescripcion = document.getElementById('descripcion');
            const inputId = document.getElementById('idDisplay');
            const formGroupId = inputId.closest('.form-group');
            
            inputId.removeAttribute('readonly');
            inputId.style.backgroundColor = '';
            inputId.style.color = '';
            inputId.style.cursor = '';
            
            inputNombre.removeAttribute('readonly');
            inputNombre.removeAttribute('data-original');
            inputNombre.style.backgroundColor = '';
            inputNombre.style.color = '';
            inputNombre.style.cursor = '';
            
            inputDescripcion.removeAttribute('readonly');
            inputDescripcion.style.backgroundColor = '';
            inputDescripcion.style.color = '';
            inputDescripcion.style.cursor = '';
            
            boton.disabled = false;
            boton.style.opacity = '1';
            boton.style.cursor = 'pointer';
            
            // Remover mensaje de advertencia si existe
            const mensajeExistente = document.querySelector('.advertencia-nombre');
            if (mensajeExistente) {
                mensajeExistente.remove();
            }
            
            // En creación no se muestra ni se usa el ID
            if (formGroupId) {
                formGroupId.style.display = 'none';
            }
            
            document.getElementById('modalRol').style.display = 'flex';
        }

        function editarRol(rol) {
            const rolActualNormalizado = getRolActualNormalizado();
            const nombreRolNormalizado = normalizarTextoRol(rol.nombre || '');
            if (rolActualNormalizado !== 'superadministrador' && nombreRolNormalizado === 'superadministrador') {
                Swal.fire({
                    icon: 'error',
                    title: 'ACCIÓN NO PERMITIDA',
                    html: '<div style="font-size: 15px; padding: 10px;">Solo Super Administrador puede editar el rol Super Administrador.</div>',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
                return;
            }

            document.getElementById('modalTitle').textContent = 'Editar Rol';
            // Cambiar texto del botón a ACTUALIZAR ROL
            const botonEditar = document.querySelector('#rolForm button[type="submit"]');
            botonEditar.innerHTML = '<i class="fas fa-save"></i> ACTUALIZAR ROL';
            
            document.getElementById('rolId').value = rol.id;
            document.getElementById('idOriginal').value = rol.id;
            document.getElementById('idDisplay').value = rol.id;
            document.getElementById('nombre').value = rol.nombre;
            document.getElementById('descripcion').value = rol.descripcion;
            
            // Guardar el nombre original para validación
            document.getElementById('nombre').setAttribute('data-original', rol.nombre);
            
            const inputId = document.getElementById('idDisplay');
            const inputNombre = document.getElementById('nombre');
            const inputDescripcion = document.getElementById('descripcion');
            const boton = document.querySelector('#rolForm button[type="submit"]');
            
            if (rolActualNormalizado !== 'superadministrador') {
                const formGroupId = inputId.closest('.form-group');
                if (formGroupId) {
                    formGroupId.style.display = 'none';
                }
                
                if (rolActualNormalizado === 'administrador' && nombreRolNormalizado === 'administrador') {
                    inputNombre.setAttribute('readonly', true);
                    inputNombre.style.backgroundColor = '#f5f5f5';
                    inputNombre.style.color = '#666';
                    inputNombre.style.cursor = 'not-allowed';
                } else {
                    inputNombre.removeAttribute('readonly');
                    inputNombre.style.backgroundColor = '';
                    inputNombre.style.color = '';
                    inputNombre.style.cursor = 'text';
                }
            } else {
                inputId.removeAttribute('readonly');
                inputId.style.backgroundColor = '';
                inputId.style.color = '';
                inputId.style.cursor = 'text';
                inputNombre.removeAttribute('readonly');
                inputNombre.style.backgroundColor = '';
                inputNombre.style.color = '';
                inputNombre.style.cursor = 'text';
            }
            
            document.getElementById('modalRol').style.display = 'flex';
        }

        function cerrarModalRol() {
            document.getElementById('modalRol').style.display = 'none';
        }

        function toggleEstadoRol(id, estado) {
            // Verificar si es un rol protegido
            const rolNombre = document.querySelector(`tr[data-rol-id="${id}"] td:nth-child(2)`).textContent.trim();
            const rolActualNormalizado = getRolActualNormalizado();
            const nombreRolNormalizado = normalizarTextoRol(rolNombre);
            const esSuperAdminSesion = rolActualNormalizado === 'superadministrador';
            const esRolSuperAdmin = nombreRolNormalizado === 'superadministrador';
            const esRolAdmin = nombreRolNormalizado === 'administrador';

            if (esRolSuperAdmin || (!esSuperAdminSesion && esRolAdmin)) {
                Swal.fire({
                    icon: 'error',
                    title: 'ACCIÓN NO PERMITIDA',
                    html: '<div style="font-size: 15px; padding: 10px;">NO SE PUEDE MODIFICAR EL ESTADO DEL ROL ' + rolNombre.toUpperCase() + '</div>',
                    confirmButtonText: 'ENTENDIDO',
                    customClass: {
                        popup: 'swal-wide',
                        confirmButton: 'swal2-confirm'
                    }
                });
                document.getElementById(`estado_${id}`).checked = !estado;
                return;
            }

            Swal.fire({
                title: '¿ESTÁS SEGURO?',
                html: '<div style="font-size: 15px; padding: 10px;">¿DESEAS ' + (estado ? 'ACTIVAR' : 'DESACTIVAR') + ' EL ROL "' + rolNombre.toUpperCase() + '"?</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'SÍ, ' + (estado ? 'ACTIVAR' : 'DESACTIVAR'),
                cancelButtonText: 'CANCELAR',
                customClass: {
                    popup: 'swal-wide',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`${base_url}/Controllers/RolController.php`, {
                        method: 'POST',
                        body: JSON.stringify({ action: 'toggle_estado', id: id, estado: estado }),
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(parseJsonResponse)
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡ÉXITO!',
                                html: '<div style="font-size: 15px; padding: 10px;">EL ROL HA SIDO ' + (estado ? 'ACTIVADO' : 'DESACTIVADO') + ' CORRECTAMENTE</div>',
                                confirmButtonText: 'ACEPTAR',
                                timer: 2500,
                                showConfirmButton: true,
                                timerProgressBar: true,
                                customClass: {
                                    popup: 'swal-wide',
                                    confirmButton: 'swal2-confirm'
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ERROR',
                                html: '<div style="font-size: 15px; padding: 10px;">' + data.message.toUpperCase() + '</div>',
                                confirmButtonText: 'ENTENDIDO',
                                customClass: {
                                    popup: 'swal-wide',
                                    confirmButton: 'swal2-confirm'
                                }
                            }).then(() => {
                                document.getElementById(`estado_${id}`).checked = !estado;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'ERROR',
                            html: '<div style="font-size: 15px; padding: 10px;">OCURRIÓ UN ERROR AL CAMBIAR EL ESTADO. INTÉNTALO DE NUEVO.</div>',
                            confirmButtonText: 'ENTENDIDO',
                            customClass: {
                                popup: 'swal-wide',
                                confirmButton: 'swal2-confirm'
                            }
                        }).then(() => {
                            document.getElementById(`estado_${id}`).checked = !estado;
                        });
                    });
                } else {
                    document.getElementById(`estado_${id}`).checked = !estado;
                }
            });
        }

        // Modificar el evento submit del formulario
        const rolFormElement = document.getElementById('rolForm');
        if (rolFormElement) {
            rolFormElement.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const id = document.getElementById('rolId').value;
                const idOriginal = document.getElementById('idOriginal').value;
                const idNuevo = document.getElementById('idDisplay').value.trim();
                const nombreOriginal = document.getElementById('nombre').getAttribute('data-original');
                const nombreNuevo = document.getElementById('nombre').value.trim();
                const rolActualNormalizado = getRolActualNormalizado();
                const nombreNuevoNormalizado = normalizarTextoRol(nombreNuevo);
                const nombreOriginalNormalizado = normalizarTextoRol(nombreOriginal || '');

                if (id) {
                    formData.set('id', id);
                }
                formData.set('idOriginal', idOriginal);
                formData.set('idNuevo', idNuevo);
                formData.append('action', id ? 'editar' : 'crear');

                if (!nombreNuevo) {
                    Swal.fire({
                        icon: 'error',
                        title: 'CAMPO REQUERIDO',
                        html: '<div style="font-size: 15px; padding: 10px;">EL NOMBRE DEL ROL NO PUEDE ESTAR VACÍO</div>',
                        confirmButtonText: 'ENTENDIDO',
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                    return;
                }

                if (rolActualNormalizado === 'administrador' && (nombreNuevoNormalizado === 'administrador' || nombreNuevoNormalizado === 'superadministrador')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ACCIÓN NO PERMITIDA',
                        html: '<div style="font-size: 15px; padding: 10px;">UN ADMINISTRADOR NO PUEDE CREAR NI RENOMBRAR ROLES COMO ADMINISTRADOR O SUPER ADMINISTRADOR</div>',
                        confirmButtonText: 'ENTENDIDO',
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                    return;
                }

                if (nombreNuevoNormalizado === 'cliente' && rolActualNormalizado !== 'superadministrador') {
                    Swal.fire({
                        icon: 'error',
                        title: 'ACCIÓN NO PERMITIDA',
                        html: '<div style="font-size: 15px; padding: 10px;">NO PUEDE CREAR NI RENOMBRAR EL ROL CLIENTE PORQUE ESE ROL ESTÁ RESERVADO EXCLUSIVAMENTE PARA SUPER ADMINISTRADOR</div>',
                        confirmButtonText: 'ENTENDIDO',
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                    return;
                }

                if (idNuevo !== idOriginal && rolActualNormalizado !== 'superadministrador') {
                    Swal.fire({
                        icon: 'error',
                        title: 'ACCIÓN NO PERMITIDA',
                        html: '<div style="font-size: 15px; padding: 10px;">SOLO SUPER ADMINISTRADOR PUEDE CAMBIAR EL ID DEL ROL</div>',
                        confirmButtonText: 'ENTENDIDO',
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                    return;
                }

                if ((nombreOriginalNormalizado === 'superadministrador' && nombreNuevoNormalizado !== 'superadministrador') ||
                    (nombreOriginalNormalizado === 'administrador' && rolActualNormalizado === 'administrador' && nombreNuevoNormalizado !== 'administrador')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ACCIÓN NO PERMITIDA',
                        html: '<div style="font-size: 15px; padding: 10px;">NO SE PUEDE MODIFICAR EL NOMBRE DEL ROL ' + nombreOriginal.toUpperCase() + '</div>',
                        confirmButtonText: 'ENTENDIDO',
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                    return;
                }

                const url = `${base_url}/Controllers/RolController.php`;
                const accion = id ? 'actualizado' : 'creado';

                Swal.fire({
                    title: 'PROCESANDO...',
                    text: 'POR FAVOR ESPERE',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(parseJsonResponse)
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡ÉXITO!',
                            html: '<div style="font-size: 15px; padding: 10px;">ROL ' + accion.toUpperCase() + ' CORRECTAMENTE</div>',
                            confirmButtonText: 'ACEPTAR',
                            timer: 2500,
                            showConfirmButton: true,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'swal-wide',
                                confirmButton: 'swal2-confirm'
                            }
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ERROR',
                            html: '<div style="font-size: 15px; padding: 10px;">' + data.message.toUpperCase() + '</div>',
                            confirmButtonText: 'ENTENDIDO',
                            customClass: {
                                popup: 'swal-wide',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'ERROR',
                        html: '<div style="font-size: 15px; padding: 10px;">OCURRIÓ UN ERROR AL PROCESAR LA SOLICITUD. INTÉNTALO DE NUEVO.</div>',
                        confirmButtonText: 'ENTENDIDO',
                        customClass: {
                            popup: 'swal-wide',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                });
            });
        }

        function confirmarReinicioRoles() {
            const nombreTabla = 'roles';
            Swal.fire({
                title: '¿Reiniciar ' + nombreTabla + '?',
                text: 'Esta acción limpiará los registros de ' + nombreTabla + ' y dejará el listado vacío. Puede deshacer este cambio con el botón correspondiente.',
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: false,
                confirmButtonText: 'Sí, reiniciar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'reiniciar');

                fetch(`${base_url}/Controllers/RolController.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo reiniciar roles');
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Roles reiniciados',
                        text: data.message || 'Los roles fueron eliminados correctamente.'
                    }).then(() => location.reload());
                })
                .catch(e => {
                    Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'No se pudo reiniciar roles' });
                });
            });
        }

        function confirmarDeshacerReinicioRoles() {
            const nombreTabla = 'roles';
            Swal.fire({
                title: '¿Deshacer el último reinicio de ' + nombreTabla + '?',
                text: 'Se restaurará el estado anterior de ' + nombreTabla + ' sin afectar las demás tablas.',
                icon: 'question',
                showCancelButton: true,
                showDenyButton: false,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#64748b',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'deshacer');

                fetch(`${base_url}/Controllers/RolController.php`, {
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

    </script>
</body>

</html>  
