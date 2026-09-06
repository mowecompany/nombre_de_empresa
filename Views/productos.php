<?php
// Si se recibió PHPSESSID como parámetro (desde iframe), usarlo para la sesión
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}
session_start();

require_once '../Helpers/Helpers.php';
require_once '../Config/Config.php';
require_once '../Config/database.php';

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

// Obtener información del rol
$rol = $_SESSION['rol'] ?? '';
$rolNorm = strtolower(trim($rol));
$esSuperAdmin = $rolNorm === 'super administrador';
$esAdmin = $rolNorm === 'administrador';
$puedeAcceder = $esAdmin || $esSuperAdmin;

if (!$puedeAcceder) {
    header('Location: dashboard.php');
    exit();
}

// Permisos simplificados: administradores tienen acceso total
$tienePermisoVer = true;
$tienePermisoEditar = true;
$tienePermisoEliminar = true;
$tienePermisoCrear = true;
$tieneAlgunPermiso = true;

// Detectar si estamos en iframe (cargado desde dashboard)
$esEnIframe = isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe' || 
              (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false);

// Obtener el rol actual del usuario
$rolActual = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
$rolActualNorm = trim(mb_strtolower((string)$rolActual, 'UTF-8'));
$rolActualNorm = strtr($rolActualNorm, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
$esSuperAdmin = ($rolActualNorm === 'super administrador');
$esAdministradorContexto = ($rolActualNorm === 'administrador') || ($esSuperAdmin && !empty($_SESSION['superadmin_modo_empresa']));
$filtrarCategoriasPorUsuario = false;
$mostrarColumnaId = $esSuperAdmin;

// Obtener categorías para el select
try {
    $db = Database::connect();
    $sqlCategorias = "SELECT id, nombre FROM categorias WHERE estado = 1 ORDER BY nombre";
    $stmt = $db->prepare($sqlCategorias);
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch(Exception $e) {
    error_log("Error al cargar categorías: " . $e->getMessage());
    $categorias = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?= NOMBRE_EMPRESA ?></title>
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/@zxing/browser@0.1.5/umd/zxing-browser.min.js"></script>
    
    <style>
        :root {
            --primary-blue: #203864;
            --secondary-blue: #3591CA;
            --bg: #f8f9fa;
            --text: #2f4a5a;
            --border: #2f4a5a;
            --button: #2f4a5a;
            --white: #FFFFFF;
            --black: #000000;
            --light-blue: rgba(53, 145, 202, 0.1);
            --font-saira: 'Saira Condensed', sans-serif;
        }

        .swal2-deny {
            display: none !important;
        }

        .venta-kilo-input {
            appearance: none;
            -webkit-appearance: none;
            border: 3px solid #203864 !important;
            border-radius: 50% !important;
            background: #ffffff;
            position: relative;
            width: 30px !important;
            height: 30px !important;
            flex: 0 0 30px;
            margin: 0 0 5px !important;
            box-sizing: border-box;
            display: inline-block !important;
            padding: 0 !important;
            line-height: 1 !important;
        }

        .venta-kilo-input:checked {
            border-color: #203864;
            background: #203864;
        }

        .producto-codigo-config {
            display: grid;
            grid-template-columns: minmax(0, 50%) 42px minmax(max-content, 1fr);
            grid-template-rows: 42px;
            align-items: center;
            gap: 8px;
            justify-content: start;
        }

        .producto-codigo-barras {
            margin-top: 22px;
        }

        .producto-codigo-config .btn-editar {
            background: #203864;
            height: 42px;
            box-sizing: border-box;
            grid-column: 2;
            grid-row: 1;
        }

        .producto-codigo-config .btn-editar:hover {
            background: #3591ca;
        }

        .producto-kilo-option {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin: 0;
            padding: 2px 4px;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            grid-column: 3;
            grid-row: 1;
            justify-self: end;
            width: max-content;
            transform: translateX(-45px);
            text-align: center;
            place-items: center;
        }

        .producto-kilo-option:has(.venta-kilo-input:checked) {
            color: #203864;
        }

        .producto-kilo-text {
            display: block;
            text-align: center;
            line-height: 1;
            width: 100%;
        }

        body {
            padding-top: <?php echo $esEnIframe ? '0' : '80px'; ?>;
            background-color: #f8f9fa;
            font-family: var(--font-saira);
            text-transform: uppercase;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 4px;
            transition: all 0.3s ease;
            color: white;
            min-width: 40px;
            height: 38px;
        }

        .btn-info {
            background-color: #17a2b8;
        }
        
        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }

        .btn-warning {
            background-color: #ffc107;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
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

        /* Estilos para el botón nuevo */
        .btn-nuevo {
            width: auto;
            min-width: 220px;
            margin: 0;
            background: transparent;
            color: #2f4a5a;
            padding: 14px 28px;
            border: 2px solid #2f4a5a;
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
            background: #2f4a5a;
            color: white;
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

        .btn-save {
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

        .btn-save:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        /* Título y botón volver */
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
            color: #2f4a5a;
            font-family: var(--font-saira);
            margin-top: 0;
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .title_equipo h1 i {
            color: #2f4a5a;
            margin-right: 15px;
        }

        .button_Volver_Atras {
            position: absolute;
            left: 20px;
            top: 45px;
            background-color: transparent;
            border: 2px solid #2f4a5a;
            color: #2f4a5a;
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
            background-color: #2f4a5a;
            color: var(--white);
            border-color: #2f4a5a;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.2);
            transform: translateX(-3px);
        }

        .button_Volver_Atras i {
            font-size: 16px;
        }

        /* Estilos para la tabla */
        .estadistica-card {
            background: var(--white);
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.08), 0 0 0 1px rgba(47, 74, 90, 0.04);
            padding: 20px;
            margin: 60px auto;
            width: 100%;
            max-height: calc(100vh - 280px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 150px);
            -webkit-overflow-scrolling: touch;
        }

        .table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #2f4a5a;
            border-radius: 5px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #3d6177;
        }

        /* Estilos para los switches */
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
        }

        .toggle-switch input:checked + .slider {
            background-color: #2f4a5a;
        }

        .toggle-switch input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Eliminar estilos que interfieren */
        .toggle-switch label {
            margin: 0;
            display: block;
        }

        /* Prevenir desplazamiento cuando se abre modal */
        body.modal-open {
            padding-right: 0 !important;
            overflow: hidden !important;
        }

        .modal-open .table-wrapper {
            overflow: visible;
        }

        /* Ocultar encabezados sticky cuando modal está abierto */
        body.modal-open .table thead th,
        body.modal-open .roles-table thead th {
            position: relative;
            z-index: 0;
        }

        /* Asegurar que los modales estén por encima de todo */
        .modal {
            z-index: 12000 !important;
            display: none;
            position: fixed;
        }

        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            margin: auto;
            max-width: 800px;
        }

        .modal-backdrop {
            z-index: 11999 !important;
        }

        /* Centrar modal */
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        /* ajustar ancho de los modales de productos para que el formulario luzca mejor */
        #editModal .modal-dialog,
        #registroModal .modal-dialog,
        #viewModal .modal-dialog {
            max-width: 1000px;
        }
        #editModal #imagenPreview,
        #registroModal #imagenPreview {
            max-width: 80px;
            max-height: 80px;
        }

        .modal-header {
            background-color: var(--primary-blue);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
        }

        .modal-header .modal-title {
            font-family: var(--font-saira);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .modal-header .close {
            color: white;
            opacity: 1;
            text-shadow: none;
        }

        .modal-header .close:hover {
            color: white;
            opacity: 0.8;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        /* Mantener ancho fijo de la tabla */
        .table-wrapper table {
            table-layout: fixed;
            width: 100%;
        }
        .table-wrapper table {
            table-layout: fixed;
            width: 100%;
        }
        
        /* Fijar ancho de tabla cuando modal está abierto */
        .estadistica-card {
            position: relative;
        }
        
        .table-wrapper {
            position: relative;
        }
        
        /* Asegurar que thead se mantenga en posición pero bajo modales */
        .table thead {
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        /* Cuando modal está abierto, bajar z-index de encabezados */
        body.modal-open .table thead,
        body.modal-open .roles-table thead,
        body.swal2-shown .table thead,
        body.swal2-shown .roles-table thead {
            position: relative !important;
            z-index: 0 !important;
        }
        
        body.modal-open .table thead th,
        body.modal-open .roles-table thead th,
        body.swal2-shown .table thead th,
        body.swal2-shown .roles-table thead th {
            position: relative !important;
            z-index: 0 !important;
        }

        /* Estilos adicionales para SweetAlert2 */
        .swal2-container {
            z-index: 10000 !important;
        }

        /* Cuando SweetAlert está abierto, ocultar headers sticky */
        body.swal2-shown .table thead,
        body.swal2-shown .roles-table thead {
            position: relative !important;
            z-index: -1 !important;
        }
        
        body.swal2-shown .table thead th,
        body.swal2-shown .roles-table thead th {
            position: relative !important;
            z-index: -1 !important;
        }

        .swal2-popup {
            z-index: 10001 !important;
        }

        .swal2-detail-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }

        .swal2-detail-item:last-child {
            border-bottom: none;
        }

        .swal2-detail-item strong {
            display: block;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
            font-family: var(--font-saira);
            text-transform: uppercase;
        }

        .swal2-detail-item p {
            margin: 0;
            padding-left: 1rem;
        }

        .container {
            width: 100%;
            max-width: 1600px;
            padding: 0 15px;
            margin: 0 auto;
            box-sizing: border-box;
        }

     

        .main-scroll-panel::-webkit-scrollbar {
            width: 12px;
        }

        .main-scroll-panel::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 8px;
        }

        .main-scroll-panel::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 8px;
            border: 2px solid #f4f6f8;
        }

        .main-scroll-panel::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .main-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 0;
        }

        .table-wrapper {
            overflow-x: auto;
            overflow-y: scroll;
            flex: 1;
            scrollbar-width: thin;
            min-height: 0;
            height: 100%;
            position: relative;
        }

        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            box-sizing: border-box;
            text-transform: uppercase;
            table-layout: fixed;
            border-radius: 8px;
            font-family: var(--font-saira);
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

        /* Anchos de columnas específicas para productos */
        <?php if ($mostrarColumnaId): ?>
        th:nth-child(1), td:nth-child(1) { width: 4%; }      /* ID */
        th:nth-child(2), td:nth-child(2) { width: 7%; }      /* CÓDIGO */
        th:nth-child(3), td:nth-child(3) { width: 9%; }      /* CÓDIGO DE BARRAS */
        th:nth-child(4), td:nth-child(4) { width: 9%; }      /* NOMBRE */
        th:nth-child(5), td:nth-child(5) { width: 9%; min-width: 100px; } /* DESCRIPCIÓN */
        th:nth-child(6), td:nth-child(6) { width: 7%; }      /* PRECIO */
        th:nth-child(7), td:nth-child(7) { width: 6%; }      /* STOCK */
        th:nth-child(8), td:nth-child(8) { width: 6%; }      /* VENTA */
        th:nth-child(9), td:nth-child(9) { width: 7%; }      /* CATEGORÍA */
        th:nth-child(10), td:nth-child(10) { width: 6%; }    /* COLOR */
        th:nth-child(11), td:nth-child(11) { width: 8%; }    /* IMAGEN */
        th:nth-child(12), td:nth-child(12) { width: 8%; }    /* ESTADO */
        th:nth-child(13), td:nth-child(13) { width: 10%; }   /* ACCIONES */
        <?php else: ?>
        th:nth-child(1), td:nth-child(1) { width: 8%; }      /* CÓDIGO */
        th:nth-child(2), td:nth-child(2) { width: 9%; }      /* CÓDIGO DE BARRAS */
        th:nth-child(3), td:nth-child(3) { width: 10%; }     /* NOMBRE */
        th:nth-child(4), td:nth-child(4) { width: 11%; min-width: 100px; } /* DESCRIPCIÓN */
        th:nth-child(5), td:nth-child(5) { width: 8%; }      /* PRECIO */
        th:nth-child(6), td:nth-child(6) { width: 6%; }      /* STOCK */
        th:nth-child(7), td:nth-child(7) { width: 7%; }      /* VENTA */
        th:nth-child(8), td:nth-child(8) { width: 6%; }      /* CATEGORÍA */
        th:nth-child(9), td:nth-child(9) { width: 9%; }      /* COLOR */
        th:nth-child(10), td:nth-child(10) { width: 8%; }    /* IMAGEN */
        th:nth-child(11), td:nth-child(11) { width: 8%; }    /* ESTADO */
        th:nth-child(12), td:nth-child(12) { width: 10%; }   /* ACCIONES */
        <?php endif; ?>

        th {
            background: white;
            color: #2f4a5a;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 2px solid #2f4a5a;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(47, 74, 90, 0.08);
        }

        th i, td i {
            margin-right: 5px;
            vertical-align: middle;
        }

        tr:hover {
            background-color: rgba(47, 74, 90, 0.04);
            transition: background-color 0.2s ease;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }

            .estadistica-card {
                padding: 15px;
                max-height: calc(100vh - 250px);
            }
            
            table {
                min-width: 900px;
            }

            th, td {
                padding: 10px 8px;
                font-size: 0.85rem;
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
            color: #6b7280;
            font-size: 16px;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #2f4a5a;
        }

        .toggle-password:focus {
            outline: none;
        }

        h2 {
            margin: 0 0 20px;
            font-size: 24px;
            color: var(--text-color);
            text-align: center;
        }

        .modal-content h2 {
            background: transparent;
            color: #2f4a5a;
            margin: -25px -25px 16px -25px;
            padding: 16px 20px;
            border-radius: 12px 12px 0 0;
            border-bottom: 2px solid #e6e9ee;
            font-size: 18px;
            letter-spacing: 0.5px;
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

        @keyframes barcodeScanMove {
            from { left: -30%; }
            to { left: 100%; }
        }

        #error-message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        #error-message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .button-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }

        .producto-thumb {
            width: 72px;
            height: 72px;
            margin: 0 auto;
            border-radius: 10px;
            border: 1px solid #dfe5ec;
            background: #f7f9fb;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .producto-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .imagen-actual-box {
            width: 220px;
            height: 220px;
            margin: 0 auto 15px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #dfe5ec;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .imagen-actual-preview {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        .button-edit-delete {
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

        .button-primary.button-edit-delete {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            color: var(--white);
        }
        
        .button-primary.button-edit-delete:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
            box-shadow: 0 4px 12px rgba(47, 74, 90, 0.3);
            transform: translateY(-2px);
        }

        .button-delete.button-edit-delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: var(--white);
        }
        
        .button-delete.button-edit-delete:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }

        .button-delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
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
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
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
            color: #2f4a5a !important;
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
            color: #dc3545 !important;
            border: 2px solid #dc3545 !important;
        }
        
        .swal2-popup.swal-delete .swal2-confirm:hover {
            background: #dc3545 !important;
            color: white !important;
            box-shadow: 0 6px 18px rgba(220, 53, 69, 0.35) !important;
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

        .swal2-deny,
        .swal2-actions button.swal2-deny {
            display: none !important;
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
            background: linear-gradient(135deg, rgba(47, 74, 90, 0.95) 0%, rgba(26, 45, 79, 0.95) 100%);
            color: white;
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
                transform: translateX(-50%) translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
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
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
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

        /* deshabilitar acciones cuando el botón está inactivo */
        .btn-editar[disabled],
        .btn-eliminar[disabled] {
            opacity: 0.6;
            pointer-events: none;
            cursor: not-allowed;
        }

        .estadistica-card::-webkit-scrollbar,
        .table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .estadistica-card::-webkit-scrollbar-track,
        .table-wrapper::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 6px;
        }

        .estadistica-card::-webkit-scrollbar-thumb,
        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 6px;
            border: 2px solid #f4f6f8;
        }
        
        .estadistica-card::-webkit-scrollbar-thumb:hover,
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }
        
        /* Estilos específicos para modales */
        .modal {
            z-index: 12000 !important;
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(47, 74, 90, 0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .swal2-container {
            z-index: 21000 !important;
        }

        .swal2-popup {
            z-index: 21001 !important;
        }

        .modal-dialog {
            margin: auto;
            max-width: 1000px;
        }

        .modal-backdrop {
            z-index: 11999 !important;
        }

        /* Centrar modal */
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        .modal-content {
            background: var(--white);
            padding: 30px 35px;
            border-radius: 14px;
            box-shadow: 0 12px 36px rgba(20, 30, 40, 0.15), 0 0 0 1px rgba(20, 30, 40, 0.05);
            width: 95%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 12001 !important;
            text-transform: uppercase;
            animation: modalFadeIn 0.3s ease-out;
            display: flex;
            flex-direction: column;
        }
        
        .modal-content form {
            padding: 0;
            flex: 1;
            max-height: calc(90vh - 120px);
            scrollbar-width: thin;
            scrollbar-color: #2f4a5a #f0f0f0;
        }
        
        .modal-content form::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-content form::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }
        
        .modal-content form::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 4px;
        }
        
        .modal-content form::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }
        
        .modal-content button[type="submit"] {
            flex-shrink: 0;
            margin-top: 20px;
        }
        
        .modal-content h2 {
            background: transparent;
            color: #2f4a5a;
            margin: -30px -35px 25px -35px;
            padding: 20px 25px;
            border-radius: 14px 14px 0 0;
            border-bottom: 2px solid #e6e9ee;
            font-size: 22px;
            letter-spacing: 1px;
            flex-shrink: 0;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
            color: #2f4a5a;
            z-index: 10;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            background: transparent;
        }

        .close:hover {
            color: white;
            background: #2f4a5a;
            transform: rotate(90deg);
        }

        .close:focus {
            color: white;
            background: #2f4a5a;
            transform: rotate(90deg);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            color: #2f4a5a;
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #2f4a5a;
            opacity: 0.8;
        }

        .form-group input,
        .form-group select {
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
        .form-group select:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: #2f4a5a;
        }
        
        .form-group input::placeholder {
            text-transform: uppercase;
            opacity: 0.6;
            color: #6b7280;
        }

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
            resize: none;
            min-height: 120px;
            scrollbar-width: thin;
            scrollbar-color: #2f4a5a #f0f0f0;
            font-family: var(--font-saira);
        }

        .form-group textarea:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: #2f4a5a;
        }

        .form-group textarea::-webkit-scrollbar {
            width: 8px;
        }

        .form-group textarea::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }

        .form-group textarea::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 4px;
        }

        .form-group textarea::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #2f4a5a 100%);
        }
        
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .form-group select option {
            padding: 12px;
            background: white;
            color: #242629;
            line-height: 1.4;
        }
        
        .form-group select option:disabled {
            color: #6b7280;
        }
        
        .form-group select:focus {
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.12);
            border-color: #2f4a5a;
        }

        button[type="submit"],
        .btn-save {
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

        button[type="submit"]:hover,
        .btn-save:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
            box-shadow: 0 6px 18px rgba(47, 74, 90, 0.35);
            transform: translateY(-2px);
        }
        
        button[type="submit"]:active,
        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(47, 74, 90, 0.25);
        }

        /* Estilos para la columna de descripción */
        .descripcion-cell {
            padding: 10px !important;
            max-width: 300px;
            max-height: 120px;
            overflow: hidden;
            vertical-align: middle;
        }

        .descripcion-content {
            max-height: 120px;
            overflow-y: auto;
            padding: 0;
            word-break: break-word;
            white-space: normal;
            line-height: 1.5;
            font-size: 0.9rem;
            color: #242629;
        }

        .descripcion-content::-webkit-scrollbar {
            width: 6px;
        }

        .descripcion-content::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 3px;
        }

        .descripcion-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 3px;
        }

        .descripcion-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="file"],
        textarea,
        select,
        .form-control {
            font-family: var(--font-saira);
            text-transform: uppercase;
        }

        textarea.form-control {
            resize: none;
            max-height: 150px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #2f4a5a #f0f0f0;
        }

        textarea.form-control::-webkit-scrollbar {
            width: 8px;
        }

        textarea.form-control::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }

        textarea.form-control::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 4px;
        }

        textarea.form-control::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #2f4a5a 100%);
        }

        /* Estilos responsive */
        @media (max-width: 768px) {
            .modal-content {
                width: 98%;
                margin: 20px auto;
                max-height: 95vh;
                padding: 20px 25px;
            }
            
            .modal-content form {
                max-height: calc(95vh - 100px);
            }

            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .modal-content {
                width: 100%;
                border-radius: 0;
                max-height: 100vh;
                padding: 15px 20px;
            }
            
            .modal-content h2 {
                margin: -15px -20px 15px -20px;
                font-size: 18px;
                padding: 15px 20px;
            }
            
            .modal-content form {
                max-height: calc(100vh - 100px);
            }
        }

        small {
            display: block;
            margin-top: 8px;
            color: #6c757d;
            font-size: 13px;
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
            overflow-y: auto !important;
            max-height: none !important;
            height: auto !important;
            scrollbar-width: thin !important;
            padding-right: 6px !important;
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
            background: #2f4a5a;
            border-radius: 6px;
            border: 1px solid #f4f6f8;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover,
        .table-container::-webkit-scrollbar-thumb:hover,
        .permisos-table-container::-webkit-scrollbar-thumb:hover {
            background: #1a2d4f;
        }

        .producto-color-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            margin: 0 auto;
            padding: 0;
            min-width: 0;
            min-height: 0;
        }

        .producto-color-swatch {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            border: 1px solid rgba(47, 74, 90, 0.2);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.5);
            display: block;
            padding: 0;
            background-color: #d0d7de;
            cursor: pointer;
            transition: transform 0.16s ease, box-shadow 0.16s ease;
        }

        .producto-color-swatch:hover {
            transform: scale(1.04);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.7), 0 0 0 2px rgba(47, 74, 90, 0.12);
        }

        .color-picker-native {
            width: 58px;
            height: 58px;
            border: none;
            background: transparent;
            padding: 0;
            cursor: pointer;
            border-radius: 50%;
            overflow: hidden;
        }

        .color-picker-native::-webkit-color-swatch-wrapper {
            padding: 0;
        }

        .color-picker-native::-webkit-color-swatch {
            border: none;
            border-radius: 50%;
        }

        .color-modal-preview {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 2px solid rgba(47, 74, 90, 0.2);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.6);
            background: #d0d7de;
        }

        .producto-color-code {
            font-size: 12px;
            font-weight: 700;
            color: #2f4a5a;
            letter-spacing: 0.2px;
            word-break: break-all;
        }

    </style>
</head>
<body>
    <!-- Reemplazar la sección hero-section actual por esto -->
    <!-- Reemplazar la sección hero-section actual por esto -->
    <div class="title_equipo">
        <h1><i class="fas fa-boxes"></i> PRODUCTOS</h1>
    </div>

    <?php if ($tienePermisoCrear): ?>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; margin: 20px 0; padding-left: 10px;">
        <div style="display:flex; gap:8px; align-items:center; margin-left: 14px;">
            <button class="btn-nuevo" onclick="toggleModal('crear')">
                <i class="fas fa-plus"></i> NUEVO PRODUCTO
            </button>
        </div>
        <?php if (PermisosHelper::esSuperAdminSesion() && empty($_SESSION['superadmin_modo_empresa'])): ?>
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <button type="button" class="btn-nuevo btn-nuevo-reset" onclick="confirmarReinicioProductos()">
                <i class="fas fa-broom"></i> REINICIAR
            </button>
            <button type="button" class="btn-nuevo btn-nuevo-undo" onclick="confirmarDeshacerReinicioProductos()">
                <i class="fas fa-undo"></i> DESHACER
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div id="registroModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="toggleModal()">&times;</span>
            <h2 style="text-align: center;">NUEVO PRODUCTO</h2>
            <div id="error-message" style="display: none;"></div>
            <form id="registroForm" onsubmit="return enviarFormulario(event)" autocomplete="off" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre"><i class="fas fa-box"></i> NOMBRE PRODUCTO</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ingrese el nombre" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="categoria_id"><i class="fas fa-tag"></i> CATEGORÍA</label>
                        <div style="position:relative;z-index:30;width:100%;">
                            <div style="position:relative; display:flex; align-items:center; width:100%; min-height:42px; border:1px solid #d0d7de; border-radius:8px; background:#fff; overflow:hidden;">
                                <input type="text" id="buscarCategoriaProducto" placeholder="BUSCAR CATEGORÍA..." autocomplete="off" style="flex:1; border:none; outline:none; background:transparent; padding:11px 12px; font-size:14px; color:#1f2937; min-width:0; text-transform:uppercase;">
                                <span style="display:flex; align-items:center; justify-content:center; width:42px; min-width:42px; height:100%; color:#667085; background:#f8fafc; border-left:1px solid #e6e9ee; font-size:15px;"><i class="fas fa-search"></i></span>
                            </div>
                            <div id="categoriaSearchResults" style="display:none; position:absolute; left:0; top:calc(100% + 6px); width:100%; max-height:220px; overflow-y:auto; border:1px solid #d0d7de; border-radius:8px; background:#fff; box-shadow:0 10px 24px rgba(31,41,55,0.08); z-index:60;">
                                <?php foreach($categorias as $cat): ?>
                                    <button type="button" class="categoria-search-option" data-id="<?= $cat->id ?>" data-name="<?= htmlspecialchars(strtoupper($cat->nombre)) ?>" style="display:block; width:100%; text-align:left; border:none; background:#fff; padding:10px 12px; font-size:14px; color:#1f2937; cursor:pointer; border-bottom:1px solid #f1f5f9; text-transform:uppercase;">
                                        <?= strtoupper($cat->nombre) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <select id="categoria_id" name="categoria_id" required onchange="generarCodigoAutomatico(); cerrarListaCategoriasProducto(this)" onfocus="abrirListaCategoriasProducto(this)" onblur="cerrarListaCategoriasProducto(this)" style="display:none; width:100%; min-height:42px;">
                                <option value="">SELECCIONE UNA CATEGORÍA</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?= $cat->id ?>"><?= strtoupper($cat->nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="descripcion"><i class="fas fa-file-alt"></i> DESCRIPCIÓN PRODUCTO</label>
                    <textarea id="descripcion" name="descripcion" placeholder="Describe características, materiales, talla, color, etc." rows="4" style="border: 1px solid #e6e9ee; border-radius: 8px; padding: 14px 16px; width: 100%; font-size: 15px; text-transform: none; background: #ffffff; color: #242629; outline: none; transition: all 0.3s ease; resize: none; max-height: 150px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #2f4a5a #f0f0f0;"></textarea>
                </div>
                <div class="form-group producto-codigo-barras">
                    <label for="codigo_barras"><i class="fas fa-barcode"></i> CÓDIGO DE BARRAS</label>
                    <div class="producto-codigo-config">
                        <input type="text" id="codigo_barras" name="codigo_barras" inputmode="numeric" pattern="[0-9]{8,14}" maxlength="14" placeholder="Escanee o escriba el código" autocomplete="off" style="flex:1;text-transform:none;">
                        <button type="button" class="btn-editar" title="Escanear con cámara" onclick="abrirEscanerCodigoBarras('codigo_barras')" style="width:38px; min-width:38px; padding:0;"><i class="fas fa-camera"></i></button>
                        <label for="venta_por_kilo" class="producto-kilo-option" title="Marcar producto vendido por kilos" style="grid-column:3; grid-row:1; justify-self:end;">
                            <input type="checkbox" class="venta-kilo-input" id="venta_por_kilo" name="venta_por_kilo" value="1" style="width:18px; height:18px; margin:0; cursor:pointer;">
                            <span class="producto-kilo-text">X KILOS</span>
                        </label>
                    </div>
                        <small style="display:block;margin-top:6px;color:#667085;">Ingrese el código de barras del producto o escanéelo con la cámara.</small>
                </div>
                <div class="form-group" style="margin-top: 28px;">
                    <label for="imagen"><i class="fas fa-image"></i> AGREGAR FOTO</label>
                    <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div style="flex:1; min-width:240px;">
                            <input type="file" id="imagen" name="imagen" accept="image/png,image/jpeg,image/jpg">
                            <small style="display:block; margin-top:6px; color:#667085;">PNG, JPG o JPEG. Exactamente 600 × 1050 px, máximo 500 KB.</small>
                            <div id="imagenFeedback" class="image-feedback" style="display:none;"></div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:center; min-width:180px; min-height:140px; border:1px dashed #d0d7de; border-radius:8px; padding:8px; background:#fafafa;">
                            <img id="imagenPreviewCrear" class="imagen-preview" src="" alt="Preview" style="display:none; max-width:160px; max-height:160px; object-fit:contain; border-radius:6px; border:1px solid #e6e9ee;">
                            <span id="imagenPreviewCrearPlaceholder" style="color:#667085; font-size:12px;">PREVIEW</span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> GUARDAR PRODUCTO</button>
            </form>
        </div>
    </div>

    <!-- Modal de Edición de Producto -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarEditModal()">&times;</span>
            <h2 style="text-align: center;">ACTUALIZAR PRODUCTO</h2>
            <form id="productoForm" autocomplete="off" style="text-align: left;">
                <input type="hidden" id="productoId" name="id">
                <input type="hidden" id="idOriginal" name="idOriginal">
                
                <div style="margin-bottom: 20px; text-align: center;">
                    <label style="display: block; margin-bottom: 8px; color: #2f4a5a; font-weight: 600;">IMAGEN ACTUAL</label>
                    <div id="imagenActualDiv" class="imagen-actual-box">
                        <img id="imagenPreview" class="imagen-actual-preview" src="" alt="Producto" onerror="this.style.display='none'">
                        <p id="sinImagenText" style="margin: 0; color: #999;">SIN IMAGEN</p>
                    </div>
                </div>
                
                <?php if ($mostrarColumnaId): ?>
                <div class="form-group">
                    <label for="idDisplay"><i class="fas fa-hashtag"></i> ID PRODUCTO</label>
                    <input id="idDisplay" name="idDisplay" type="text" autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; font-size: 14px;" required>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombreEdit"><i class="fas fa-box"></i> NOMBRE PRODUCTO</label>
                        <input id="nombreEdit" name="nombreEdit" type="text" autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; font-size: 14px; text-transform: uppercase;" required>
                    </div>
                    <div class="form-group">
                        <label for="categoriaEdit"><i class="fas fa-tag"></i> CATEGORÍA</label>
                        <select id="categoriaEdit" name="categoriaEdit" required style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; font-size: 14px; text-transform: none;">
                            <option value="">SELECCIONE UNA CATEGORÍA</option>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?= $cat->id ?>"><?= strtoupper($cat->nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descripcionEdit"><i class="fas fa-file-alt"></i> DESCRIPCIÓN PRODUCTO</label>
                    <textarea id="descripcionEdit" name="descripcionEdit" style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; text-transform: uppercase; resize: none; max-height: 150px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #2f4a5a #f0f0f0; font-size: 14px;" rows="5"></textarea>
                </div>

                <div class="form-group producto-codigo-barras">
                    <label for="editProdCodigoBarras"><i class="fas fa-barcode"></i> CÓDIGO DE BARRAS</label>
                    <div class="producto-codigo-config">
                        <input type="text" id="editProdCodigoBarras" name="codigo_barras" inputmode="numeric" pattern="[0-9]{8,14}" maxlength="14" autocomplete="off" style="flex:1;text-transform:none;">
                        <button type="button" class="btn-editar" title="Escanear con cámara" onclick="abrirEscanerCodigoBarras('editProdCodigoBarras')" style="width:38px; min-width:38px; padding:0;"><i class="fas fa-camera"></i></button>
                        <label for="ventaPorKiloEdit" class="producto-kilo-option" title="Marcar producto vendido por kilos" style="grid-column:3; grid-row:1; justify-self:end;">
                            <input type="checkbox" class="venta-kilo-input" id="ventaPorKiloEdit" name="venta_por_kilo" value="1" style="width:18px; height:18px; margin:0; cursor:pointer;">
                            <span class="producto-kilo-text">X KILOS</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label for="imagenEdit"><i class="fas fa-image"></i> NUEVA IMAGEN</label>
                    <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div style="flex:1; min-width:240px;">
                            <input type="file" id="imagenEdit" name="imagenEdit" accept="image/png,image/jpeg,image/jpg">
                            <small style="display: block; margin-top: 6px; color: #999;">La imagen anterior se eliminará automáticamente al subir una nueva</small>
                            <small style="display:block; margin-top:6px; color:#667085;">PNG, JPG o JPEG. Exactamente 600 × 1050 px, máximo 500 KB.</small>
                            <div id="imagenEditFeedback" class="image-feedback" style="display:none;"></div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:center; min-width:180px; min-height:140px; border:1px dashed #d0d7de; border-radius:8px; padding:8px; background:#fafafa;">
                            <img id="imagenPreviewNueva" class="imagen-preview" src="" alt="Preview nueva imagen" style="display:none; max-width:160px; max-height:160px; object-fit:contain; border-radius:6px; border:1px solid #e6e9ee;">
                            <span id="imagenPreviewNuevaPlaceholder" style="color:#667085; font-size:12px;">PREVIEW</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save" id="btnSaveEdit"><i class="fas fa-save"></i> ACTUALIZAR PRODUCTO</button>
            </form>
        </div>
    </div>

    <div id="barcodeScannerModal" class="modal" style="display:none;z-index:10050;">
        <div class="modal-content" style="max-width:520px;text-align:center;">
            <span class="close" onclick="cerrarEscanerCodigoBarras()">&times;</span>
            <h2><i class="fas fa-camera"></i> ESCANEAR CÓDIGO DE BARRAS</h2>
            <video id="barcodeScannerVideo" autoplay muted playsinline style="width:100%;max-height:320px;object-fit:cover;background:#111;border-radius:8px;"></video>
            <img id="barcodeScannerSnapshot" alt="Fotograma capturado para leer el código" style="display:none;width:100%;max-height:320px;object-fit:contain;background:#111;border-radius:8px;">
            <p id="barcodeScannerStatus" style="color:#667085;margin:12px 0;">Apunte la cámara al código.</p>
            <div id="barcodeScanAnimation" style="display:none;position:relative;height:6px;margin:-4px 12px 12px;overflow:hidden;border-radius:6px;background:#fecdca;"><span style="position:absolute;top:0;left:-30%;width:30%;height:100%;background:#d92d20;animation:barcodeScanMove 1.1s linear infinite;"></span></div>
            <div id="barcodeScannerIndicator" style="display:inline-flex;align-items:center;gap:8px;margin-bottom:12px;color:#b42318;font-size:13px;font-weight:600;"><span style="width:12px;height:12px;border-radius:50%;background:#d92d20;display:inline-block;"></span><span>Esperando código</span></div>
            <div id="barcodeDetectedValue" style="display:none;margin:4px 0 12px;color:#027a48;font-weight:700;"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:12px;">
                <button type="button" class="btn-save" title="Tomar código" aria-label="Tomar código" onclick="tomarCodigoBarras()" style="width:46px;height:42px;min-width:46px;padding:0;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-camera" style="font-size:16px;"></i></button>
                <button type="button" id="volverTomarFotoBtn" class="btn-save" title="Volver a tomar foto" aria-label="Volver a tomar foto" onclick="volverATomarFoto()" style="display:none;height:42px;padding:0 14px;align-items:center;justify-content:center;">VOLVER A TOMAR FOTO</button>
                <button type="button" id="guardarCodigoBarrasBtn" class="btn-save" title="Guardar código" aria-label="Guardar código" onclick="guardarCodigoBarrasDetectado()" style="display:none;height:42px;padding:0 16px;align-items:center;justify-content:center;">GUARDAR CÓDIGO</button>
                <button type="button" class="btn-save" title="Cerrar cámara" aria-label="Cerrar cámara" onclick="cerrarEscanerCodigoBarras()" style="width:46px;height:42px;min-width:46px;padding:0;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-stop" style="font-size:16px;"></i></button>
            </div>
        </div>
    </div>

    <!-- Modal de Selección de Color -->
    <div id="colorModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 420px; text-align: center; padding: 24px 28px;">
            <span class="close" onclick="cerrarColorModal()">&times;</span>
            <h2 style="margin-bottom: 18px; color: #2f4a5a;">SELECCIONAR COLOR</h2>
            <div style="display: flex; align-items: center; justify-content: center; gap: 18px; width: 100%; flex-wrap: nowrap;">
                <input id="colorPickerInput" type="color" class="color-picker-native" value="#D0D7DE" onchange="seleccionarColorModal(this.value)">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 92px;">
                    <div id="colorModalPreview" class="color-modal-preview" style="background:#D0D7DE;"></div>
                    <small style="color:#667085;">COLOR SELECCIONADO</small>
                    <button type="button" class="btn-save" onclick="confirmarColorProducto()">ACTUALIZAR COLOR</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Visualización de Producto -->
    <div id="viewModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarViewModal()">&times;</span>
            <h2 style="text-align: center;">DETALLES DEL PRODUCTO</h2>
            <div id="viewProductoContent" style="text-align: left;">
                <!-- Contenido dinámico del producto -->
            </div>
        </div>
    </div>

    <div class="container main-scroll-panel">
        <div class="estadistica-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <?php if ($mostrarColumnaId): ?>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <?php endif; ?>
                            <th><i class="fas fa-barcode"></i> CÓDIGO</th>
                            <th><i class="fas fa-qrcode"></i> CÓDIGO DE BARRAS</th>
                            <th><i class="fas fa-box"></i> NOMBRE</th>
                            <th><i class="fas fa-file-alt"></i> DESCRIPCIÓN</th>
                            <th><i class="fas fa-dollar-sign"></i> PRECIO</th>
                            <th><i class="fas fa-cubes"></i> STOCK</th>
                            <th><i class="fas fa-weight-scale"></i> VENTA</th>
                            <th><i class="fas fa-tag"></i> CATEGORÍA</th>
                            <th><i class="fas fa-palette"></i> COLOR</th>
                            <th><i class="fas fa-image"></i> IMAGEN</th>
                            <th><i class="fas fa-toggle-on"></i> ESTADO</th>
                            <th><i class="fas fa-tools"></i> ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="productos-tbody">
                        <!-- Los productos se cargarán aquí mediante JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const mostrarColumnaId = <?= json_encode($mostrarColumnaId); ?>;
        // Funciones para ocultar/mostrar headers sticky cuando hay modales o alertas
        const hideHeaders = () => {
            const headers = document.querySelectorAll('thead, [role="rowheader"]');
            headers.forEach(header => {
                header.style.visibility = 'hidden';
                header.style.zIndex = '-1';
            });
        };
        
        const showHeaders = () => {
            const headers = document.querySelectorAll('thead, [role="rowheader"]');
            headers.forEach(header => {
                header.style.visibility = 'visible';
                header.style.zIndex = 'auto';
            });
        };

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

        // Permiso del usuario (variables globales del HTML generado por PHP arriba)
        const tienePermisoEditar = <?= json_encode($tienePermisoEditar) ?>;
        const tienePermisoEliminar = <?= json_encode($tienePermisoEliminar) ?>;
        const esSuperAdmin = <?= json_encode($esSuperAdmin) ?>;
        const PRODUCT_IMAGE_RULES = {
            maxBytes: 500 * 1024,
            mime: ['image/png', 'image/jpeg', 'image/jpg'],
            width: 600,
            height: 1050
        };

        function formatBytesToKb(bytes) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        function renderImageFeedback(targetId, result = null) {
            const box = document.getElementById(targetId);
            if (!box) return;
            if (!result) {
                box.style.display = 'none';
                box.innerHTML = '';
                return;
            }

            const ok = !!result.valid;
            const sizeOk = !!result.sizeValid;
            const dimensionsOk = !!result.dimensionsValid;
            const sizeColor = sizeOk ? '#0f8a4b' : '#c0392b';
            const dimensionsColor = dimensionsOk ? '#0f8a4b' : '#c0392b';
            const requiredText = `${PRODUCT_IMAGE_RULES.width} × ${PRODUCT_IMAGE_RULES.height} px`;
            const requiredColor = dimensionsOk ? '#0f8a4b' : '#c0392b';
            const messageColor = ok ? '#0f8a4b' : '#c0392b';
            const weightRequiredText = `${formatBytesToKb(PRODUCT_IMAGE_RULES.maxBytes)} KB`;
            const weightRequiredColor = sizeOk ? '#0f8a4b' : '#c0392b';

            box.style.display = 'block';
            box.style.marginTop = '8px';
            box.style.padding = '10px 12px';
            box.style.borderRadius = '8px';
            box.style.fontSize = '13px';
            box.style.lineHeight = '1.45';
            box.style.border = ok ? '1px solid rgba(15,138,75,0.18)' : '1px solid rgba(192,57,43,0.18)';
            box.style.background = ok ? 'rgba(15,138,75,0.08)' : 'rgba(192,57,43,0.08)';
            box.style.color = messageColor;
            box.innerHTML = `
                <div><strong style="color:${sizeColor};">Peso actual:</strong> <span style="color:${sizeColor};">${result.sizeLabel}</span></div>
                <div><strong style="color:${weightRequiredColor};">Peso requerido:</strong> <span style="color:${weightRequiredColor};">${weightRequiredText}</span></div>
                <div><strong style="color:${dimensionsColor};">Medidas actuales:</strong> <span style="color:${dimensionsColor};">${result.width} × ${result.height} px</span></div>
                <div><strong style="color:${requiredColor};">Medidas requeridas:</strong> <span style="color:${requiredColor};">${requiredText}</span></div>
                <div style="margin-top:4px; color:${messageColor};">${result.message}</div>
            `;
        }

        function inspectImageFile(file, rules) {
            return new Promise((resolve) => {
                if (!file) {
                    resolve(null);
                    return;
                }

                const sizeLabel = formatBytesToKb(file.size || 0);
                const normalizedType = (file.type || '').toLowerCase();
                const allowedMime = Array.isArray(rules.mime) ? rules.mime : [rules.mime];
                if (!allowedMime.some((mime) => normalizedType === mime)) {
                    resolve({ valid: false, sizeValid: false, dimensionsValid: false, width: 0, height: 0, sizeLabel, message: 'Formato inválido. Debe ser PNG, JPG o JPEG.' });
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                const image = new Image();
                image.onload = () => {
                    const width = Number(image.naturalWidth || 0);
                    const height = Number(image.naturalHeight || 0);
                    URL.revokeObjectURL(objectUrl);

                    const sizeValid = file.size < rules.maxBytes;
                    const requiredWidth = typeof rules.width === 'number' ? rules.width : null;
                    const requiredHeight = typeof rules.height === 'number' ? rules.height : null;
                    const dimensionsValid = (requiredWidth === null || width === requiredWidth) && (requiredHeight === null || height === requiredHeight);

                    if (!sizeValid && !dimensionsValid) {
                        resolve({ valid: false, sizeValid: false, dimensionsValid: false, width, height, sizeLabel, message: 'El peso y las medidas no cumplen con lo requerido.' });
                        return;
                    }

                    if (!sizeValid) {
                        resolve({ valid: false, sizeValid: false, dimensionsValid: true, width, height, sizeLabel, message: 'El peso supera el límite permitido.' });
                        return;
                    }

                    if (!dimensionsValid) {
                        resolve({ valid: false, sizeValid: true, dimensionsValid: false, width, height, sizeLabel, message: 'Las medidas no coinciden con las requeridas.' });
                        return;
                    }

                    resolve({ valid: true, sizeValid: true, dimensionsValid: true, width, height, sizeLabel, message: 'Imagen válida para guardar.' });
                };
                image.onerror = () => {
                    URL.revokeObjectURL(objectUrl);
                    resolve({ valid: false, sizeValid: false, dimensionsValid: false, width: 0, height: 0, sizeLabel, message: 'No se pudo leer la imagen seleccionada.' });
                };
                image.src = objectUrl;
            });
        }

        async function validateImageInput(inputId, feedbackId, rules) {
            const input = document.getElementById(inputId);
            if (!input) return true;
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                renderImageFeedback(feedbackId, null);
                return true;
            }
            const result = await inspectImageFile(file, rules);
            renderImageFeedback(feedbackId, result);
            return !!(result && result.valid);
        }

        function bindImageValidator(inputId, feedbackId, rules, onValidFile = null, onInvalidFile = null) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('change', async function () {
                const isValid = await validateImageInput(inputId, feedbackId, rules);
                if (!isValid) {
                    if (typeof onInvalidFile === 'function') {
                        onInvalidFile();
                    }
                    this.value = '';
                    return;
                }

                if (typeof onValidFile === 'function' && this.files && this.files[0]) {
                    onValidFile(this.files[0]);
                }
            });
        }

        function resetProductoModalState() {
            const registroForm = document.getElementById('registroForm');
            if (registroForm) {
                registroForm.reset();
            }
            const productoForm = document.getElementById('productoForm');
            if (productoForm) {
                productoForm.reset();
            }

            const createInput = document.getElementById('imagen');
            const editInput = document.getElementById('imagenEdit');
            if (createInput) createInput.value = '';
            if (editInput) editInput.value = '';

            const previewCreate = document.getElementById('imagenPreviewCrear');
            const previewCreatePlaceholder = document.getElementById('imagenPreviewCrearPlaceholder');
            if (previewCreate) {
                previewCreate.src = '';
                previewCreate.style.display = 'none';
            }
            if (previewCreatePlaceholder) {
                previewCreatePlaceholder.style.display = 'inline';
            }

            const previewNueva = document.getElementById('imagenPreviewNueva');
            const previewNuevaPlaceholder = document.getElementById('imagenPreviewNuevaPlaceholder');
            if (previewNueva) {
                previewNueva.src = '';
                previewNueva.style.display = 'none';
            }
            if (previewNuevaPlaceholder) {
                previewNuevaPlaceholder.style.display = 'inline';
            }

            const imagenActual = document.getElementById('imagenPreview');
            const sinImagenText = document.getElementById('sinImagenText');
            if (imagenActual) {
                imagenActual.src = '';
                imagenActual.style.display = 'none';
            }
            if (sinImagenText) {
                sinImagenText.style.display = 'block';
            }

            renderImageFeedback('imagenFeedback', null);
            renderImageFeedback('imagenEditFeedback', null);
        }

        // Función para formatear moneda Colombiana
        function formatMonedaColombia(cantidad) {
            return new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(cantidad);
        }

        const base_url = <?= json_encode(base_url()) ?>;

        const resolveAppUrl = (path) => {
            const value = String(path || '').trim();
            if (!value) return base_url;
            if (/^https?:\/\//i.test(value) || value.startsWith('data:')) return value;
            if (value.startsWith('/')) return base_url + value;
            if (value.startsWith('../')) return `${base_url}${value.replace(/^\.\.\//, '/')}`;
            return `${base_url}/${value.replace(/^\/+/, '')}`;
        };

        const originalFetch = window.fetch.bind(window);
        window.fetch = function(resource, init) {
            if (typeof resource === 'string') {
                const normalized = resource.trim();
                if (normalized.startsWith('../Controllers/') || normalized.startsWith('../Assets/') || normalized.startsWith('Controllers/') || normalized.startsWith('Assets/')) {
                    resource = resolveAppUrl(normalized);
                }
            }
            return originalFetch(resource, init);
        };

        function resolverImagenProducto(urlImagen) {
            const nombre = String(urlImagen || '').trim();
            if (!nombre || nombre.toLowerCase() === 'favicon.ico') {
                return base_url + '/favicon.ico';
            }
            return resolveAppUrl('/Assets/images/productos/' + nombre);
        }

        const productosScrollStorageKey = 'productosScrollPosition';

        function obtenerPosicionScrollProductos() {
            const tableWrapper = document.querySelector('.table-wrapper');
            return {
                windowY: window.scrollY || 0,
                tableY: tableWrapper ? tableWrapper.scrollTop : 0
            };
        }

        function guardarPosicionScrollProductos() {
            try {
                sessionStorage.setItem(productosScrollStorageKey, JSON.stringify(obtenerPosicionScrollProductos()));
            } catch (error) {
                console.warn('No se pudo guardar la posición de productos:', error);
            }
        }

        function restaurarPosicionScrollProductos(posicion = null) {
            let posicionGuardada = posicion;
            if (!posicionGuardada) {
                try {
                    posicionGuardada = JSON.parse(sessionStorage.getItem(productosScrollStorageKey) || 'null');
                } catch (error) {
                    posicionGuardada = null;
                }
            }
            if (!posicionGuardada) return;

            const restaurar = () => {
                window.scrollTo({ top: Number(posicionGuardada.windowY) || 0, behavior: 'auto' });
                const tableWrapper = document.querySelector('.table-wrapper');
                if (tableWrapper) {
                    tableWrapper.scrollTop = Number(posicionGuardada.tableY) || 0;
                }
            };
            requestAnimationFrame(() => requestAnimationFrame(restaurar));
        }

        // Cargar todos los productos desde el Controller
        function cargarProductos(callback = null) {
            fetch(base_url + '/Controllers/ProductoController.php?action=getAll')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        const tbody = document.getElementById('productos-tbody');
                        tbody.innerHTML = '';
                        
                        data.data.forEach(producto => {
                            const fila = generarFilaProducto(producto);
                            tbody.appendChild(fila);
                        });
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        console.error('Error al cargar productos:', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function refrescarProductosManteniendoScroll() {
            const posicionScroll = obtenerPosicionScrollProductos();
            guardarPosicionScrollProductos();
            cargarProductos(() => {
                restaurarPosicionScrollProductos(posicionScroll);
            });
        }

        let colorModalProductId = null;
        let colorModalSelectedColor = '#D0D7DE';

        function abrirSelectorColor(productId, currentColor = '#D0D7DE') {
            colorModalProductId = productId;
            colorModalSelectedColor = currentColor && /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(currentColor) ? currentColor : '#D0D7DE';
            const preview = document.getElementById('colorModalPreview');
            const input = document.getElementById('colorPickerInput');
            if (preview) {
                preview.style.background = colorModalSelectedColor;
            }
            if (input) {
                input.value = colorModalSelectedColor;
            }
            document.querySelectorAll('.color-palette-option').forEach(button => {
                button.classList.toggle('active', button.getAttribute('data-color').toUpperCase() === colorModalSelectedColor.toUpperCase());
            });
            document.getElementById('colorModal').style.display = 'flex';
            document.body.classList.add('modal-open');
        }

        function seleccionarColorModal(color) {
            colorModalSelectedColor = color;
            const preview = document.getElementById('colorModalPreview');
            const input = document.getElementById('colorPickerInput');
            if (preview) {
                preview.style.background = color;
            }
            if (input) {
                input.value = color;
            }
            document.querySelectorAll('.color-palette-option').forEach(button => {
                button.classList.toggle('active', button.getAttribute('data-color').toUpperCase() === color.toUpperCase());
            });
        }

        function confirmarColorProducto() {
            const productId = colorModalProductId;
            const input = document.getElementById('colorPickerInput');
            if (!productId || !input) return;
            const nuevoColor = String(input.value || colorModalSelectedColor || '').trim();
            if (!nuevoColor) return;

            const formData = new FormData();
            formData.append('action', 'actualizarColor');
            formData.append('id', productId);
            formData.append('color', nuevoColor);

            fetch(`${base_url}/Controllers/ProductoController.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const swatch = document.getElementById(`color_swatch_${productId}`);
                    if (swatch) {
                        swatch.style.background = nuevoColor;
                    }
                    cerrarColorModal();
                } else {
                    mostrarAlerta('error', data.message || 'Error al actualizar el color');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('error', 'Error al actualizar el color');
            });
        }

        function cerrarColorModal() {
            document.getElementById('colorModal').style.display = 'none';
            document.body.classList.remove('modal-open');
            colorModalProductId = null;
            colorModalSelectedColor = '#D0D7DE';
        }

        // Generar fila de producto
        function generarFilaProducto(prod) {
            const tr = document.createElement('tr');
            
            // Imagen
            let imagenHTML = '';
            const imgSrc = resolverImagenProducto(prod.imagen);
            imagenHTML = `<div class="producto-thumb"><img class="producto-thumb-img" src="${imgSrc}" alt="${prod.nombre}" onerror="this.onerror=null;this.src='../favicon.ico';"></div>`;
            
            // Estado
            let estadoHTML = '';
            if (tienePermisoEditar) {
                const activo = Number(prod.estado) === 1;
                estadoHTML = `
                    <div class="toggle-switch">
                        <input type="checkbox" id="estado_${prod.id}" ${activo ? 'checked' : ''} onchange="cambiarEstado(${prod.id}, this.checked)">
                        <label for="estado_${prod.id}" class="slider"></label>
                    </div>
                `;
            } else {
                const claseEstado = (Number(prod.estado) === 1) ? 'estado-activo' : 'estado-inactivo';
                const textoEstado = (Number(prod.estado) === 1) ? 'ACTIVO' : 'INACTIVO';
                estadoHTML = `<span class="${claseEstado}">${textoEstado}</span>`;
            }
            
            // Acciones
            let botonesHTML = `<button class="btn-editar" onclick="verProducto(${prod.id})" title="Ver"><i class="fas fa-eye"></i></button>`;
            if (tienePermisoEditar) {
                // deshabilitar edición cuando el producto está inactivo
                const activo = Number(prod.estado) === 1;
                const editarDisabled = activo ? '' : 'disabled';
                const editarOnclick = activo ? `onclick="editarProducto(${prod.id})"` : '';
                botonesHTML += `<button class="btn-editar" ${editarDisabled} ${editarOnclick} title="Editar"><i class="fas fa-edit"></i></button>`;
            }
            if (tienePermisoEliminar) {
                botonesHTML += `<button class="btn-eliminar" onclick="eliminarProducto(${prod.id})" title="Eliminar"><i class="fas fa-trash"></i></button>`;
            }
            
            const colorTexto = String(prod.color || '').trim();
            const swatchColor = colorTexto || '#D0D7DE';
            const colorCell = `
                <td>
                    <div class="producto-color-cell">
                        <button type="button" id="color_swatch_${prod.id}" class="producto-color-swatch" style="background:${swatchColor};" title="Haga clic para cambiar el color" onclick="abrirSelectorColor(${prod.id}, '${swatchColor}')"></button>
                    </div>
                </td>
            `;
            tr.innerHTML = `
                ${mostrarColumnaId ? `<td>${prod.id}</td>` : ''}
                <td>${prod.codigo || '-'}</td>
                <td>${prod.codigo_barras || '-'}</td>
                <td>${prod.nombre.toUpperCase()}</td>
                <td class="descripcion-cell">
                    <div class="descripcion-content">${(prod.descripcion || '').toUpperCase()}</div>
                </td>
                <td>${formatMonedaColombia(Number(prod.stock) === 0 ? 0 : parseFloat(prod.precio))}</td>
                <td>${(prod.stock === 0 || prod.stock === '0') ? '0' : (prod.stock || '-')}</td>
                <td>${Number(prod.venta_por_kilo) === 1 ? 'KILO' : 'NORMAL'}</td>
                <td>${(prod.categoria_nombre || '-').toUpperCase()}</td>
                ${colorCell}
                <td>${imagenHTML}</td>
                <td>${estadoHTML}</td>
                <td>
                    <div class="button-actions">
                        ${botonesHTML}
                    </div>
                </td>
            `;
            
            return tr;
        }

        function toggleModal(mode = 'crear') {
            const modal = document.getElementById('registroModal');
            if (modal.style.display === 'none' || modal.style.display === '') {
                if(mode === 'crear') {
                    limpiarFormulario();
                }
                modal.style.display = 'flex';
                document.body.classList.add('modal-open');
            } else {
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
                resetProductoModalState();
            }
        }
        
        function limpiarFormulario() {
            const registroForm = document.getElementById('registroForm');
            if (registroForm) {
                registroForm.reset();
            }
            const inputCodigo = document.getElementById('codigo');
            if (inputCodigo) {
                inputCodigo.value = '';
            }
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.style.display = 'none';
                errorMessage.innerHTML = '';
            }
            resetProductoModalState();
        }

        function normalizarTextoParaPrefijo(texto) {
            return String(texto || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .normalize('NFC')
                .replace(/[^A-Za-z0-9]/g, '');
        }

        function obtenerPrefijoCategoriaUnico(categoriaSelect) {
            const opciones = Array.from(categoriaSelect.options).filter(option => String(option.value || '').trim() !== '');
            const normalizar = (texto) => normalizarTextoParaPrefijo(texto || '').toUpperCase().replace(/[^A-Z0-9]/g, '');

            const categorias = opciones.map(option => ({
                value: option.value,
                nombre: normalizar(option.text || '')
            }));

            const prefijosAsignados = new Map();
            const usados = new Set();

            categorias.forEach(({ value, nombre }) => {
                let prefijo = '';
                if (nombre.length < 2) {
                    prefijo = nombre.padEnd(2, 'X').slice(0, 2);
                } else {
                    prefijo = nombre.slice(0, 2);
                    if (usados.has(prefijo)) {
                        const primeraLetra = nombre[0];
                        for (let idx = 1; idx < nombre.length; idx++) {
                            const candidato = (primeraLetra + nombre[idx]).toUpperCase();
                            if (!usados.has(candidato)) {
                                prefijo = candidato;
                                break;
                            }
                        }
                        if (!prefijo) {
                            prefijo = nombre.slice(0, 2);
                        }
                    }
                }
                prefijosAsignados.set(value, prefijo);
                usados.add(prefijo);
            });

            const selectedValue = String(categoriaSelect.value || '').trim();
            return prefijosAsignados.get(selectedValue) || '';
        }

        function abrirListaCategoriasProducto(categoriaSelect) {
            if (!categoriaSelect || categoriaSelect.disabled) return;
            categoriaSelect.size = Math.min(8, Math.max(2, categoriaSelect.options.length));
            categoriaSelect.style.position = 'absolute';
            categoriaSelect.style.left = '0';
            categoriaSelect.style.top = '0';
            categoriaSelect.style.width = '100%';
            categoriaSelect.style.zIndex = '50';
        }

        function filtrarCategoriasProducto() {
            const buscarCategoria = document.getElementById('buscarCategoriaProducto');
            const results = document.getElementById('categoriaSearchResults');
            if (!buscarCategoria || !results) return;

            const texto = normalizarTextoParaPrefijo(buscarCategoria.value || '').toUpperCase();
            const opciones = Array.from(document.querySelectorAll('.categoria-search-option'));
            let coincidencias = 0;

            opciones.forEach((option) => {
                const nombre = normalizarTextoParaPrefijo(option.dataset.name || '').toUpperCase();
                const coincide = !texto || nombre.includes(texto);
                option.style.display = coincide ? 'block' : 'none';
                if (coincide) coincidencias++;
            });

            results.style.display = coincidencias && buscarCategoria.value.trim() ? 'block' : 'none';
        }

        function cerrarListaCategoriasProducto(categoriaSelect) {
            if (!categoriaSelect) return;
            categoriaSelect.size = 1;
            categoriaSelect.style.position = '';
            categoriaSelect.style.left = '';
            categoriaSelect.style.top = '';
            categoriaSelect.style.width = '';
            categoriaSelect.style.zIndex = '';
            const results = document.getElementById('categoriaSearchResults');
            if (results) results.style.display = 'none';
        }

        function generarCodigoAutomatico() {
            const categoriaSelect = document.getElementById('categoria_id');
            const inputCodigo = document.getElementById('codigo');
            if (!categoriaSelect || !inputCodigo) {
                return;
            }

            const selectedOptionText = categoriaSelect.options[categoriaSelect.selectedIndex]?.text || '';
            if (!selectedOptionText || selectedOptionText === 'SELECCIONE UNA CATEGORÍA') {
                inputCodigo.value = '';
                return;
            }

            const prefijo = obtenerPrefijoCategoriaUnico(categoriaSelect);
            if (!prefijo) {
                inputCodigo.value = '';
                return;
            }

            fetch(base_url + '/Controllers/ProductoController.php?action=obtenerProximoCodigo&prefijo=' + encodeURIComponent(prefijo), {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    inputCodigo.value = data.codigo;
                } else {
                    console.error('Error al generar código:', data.message);
                }
            })
            .catch(error => {
                console.error('Error al obtener el próximo código:', error);
            });
        }

        function notificarCambioDashboard() {
            try {
                localStorage.setItem('refreshDashboard', String(Date.now()));
                localStorage.setItem('refreshInventario', String(Date.now()));
            } catch (e) {
                console.warn('No se pudo guardar el refresh del dashboard:', e);
            }
            try {
                window.dispatchEvent(new Event('refreshDashboard'));
                window.dispatchEvent(new Event('refreshInventario'));
            } catch (e) {
                console.warn('No se pudo disparar eventos de refresh del dashboard:', e);
            }
            try {
                if (window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
                    window.parent.postMessage({ action: 'refreshDashboard' }, '*');
                    window.parent.postMessage({ action: 'refreshInventario' }, '*');
                }
            } catch (e) {
                console.warn('No se pudo informar al dashboard principal:', e);
            }
        }

        function mostrarAlerta(tipo, mensaje) {
            if (tipo === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡ÉXITO!',
                    text: mensaje,
                    showConfirmButton: false,
                    showCancelButton: false,
                    allowOutsideClick: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            } else {
                Swal.fire({
                    icon: tipo,
                    title: 'ERROR',
                    text: mensaje,
                    confirmButtonColor: '#2f4a5a',
                    confirmButtonText: 'ACEPTAR',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    buttonsStyling: false
                });
            }
        }

        let barcodeScannerStream = null;
        let barcodeScannerControls = null;
        let barcodeScannerTarget = '';
        let barcodeCaptureTimeout = null;
        let barcodePendingValue = '';

        function normalizarCodigoBarrasDetectado(valor) {
            if (valor === null || valor === undefined) return '';
            const limpio = String(valor).replace(/\s+/g, '').replace(/[^0-9]/g, '');
            return limpio.slice(0, 14);
        }

        function elegirCodigoBarrasValido(candidatos) {
            if (!Array.isArray(candidatos)) return '';
            const valores = [];
            candidatos.forEach((valor) => {
                const normalizado = normalizarCodigoBarrasDetectado(valor);
                if (/^[0-9]{8,14}$/.test(normalizado) && !valores.includes(normalizado)) {
                    valores.push(normalizado);
                }
            });
            if (!valores.length) return '';
            valores.sort((a, b) => b.length - a.length || b.localeCompare(a));
            return valores[0];
        }

        function actualizarAnimacionEscaneo(activa) {
            const animacion = document.getElementById('barcodeScanAnimation');
            if (animacion) animacion.style.display = activa ? 'block' : 'none';
        }

        function actualizarIndicadorCodigo(detectado) {
            const indicador = document.getElementById('barcodeScannerIndicator');
            if (!indicador) return;
            const punto = indicador.querySelector('span');
            const texto = indicador.querySelector('span:last-child');
            if (punto) punto.style.background = detectado ? '#12b76a' : '#d92d20';
            if (texto) texto.textContent = detectado ? 'Código detectado' : 'Esperando código';
            indicador.style.color = detectado ? '#027a48' : '#b42318';
        }

        function colocarCodigoBarras(codigo) {
            const campo = document.getElementById(barcodeScannerTarget);
            if (!campo || !codigo) return false;
            campo.value = String(codigo).replace(/\D/g, '').slice(0, 14);
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            campo.dispatchEvent(new Event('change', { bubbles: true }));
            actualizarIndicadorCodigo(true);
            const cerrarYEnfocar = () => {
                cerrarEscanerCodigoBarras();
                campo.focus();
            };
            setTimeout(cerrarYEnfocar, 700);
            return true;
        }

        function mostrarCodigoDetectado(codigo) {
            if (barcodePendingValue) return true;
            const valor = elegirCodigoBarrasValido([codigo]);
            const status = document.getElementById('barcodeScannerStatus');
            const resultado = document.getElementById('barcodeDetectedValue');
            const guardar = document.getElementById('guardarCodigoBarrasBtn');
            if (!valor || !resultado || !guardar) {
                if (status) status.textContent = 'No se detectó un código válido. Ajuste la imagen y vuelva a intentarlo.';
                actualizarIndicadorCodigo(false);
                actualizarAnimacionEscaneo(false);
                return false;
            }
            barcodePendingValue = valor;
            actualizarIndicadorCodigo(true);
            actualizarAnimacionEscaneo(false);
            if (status) status.textContent = 'Código detectado correctamente.';
            resultado.textContent = `Código detectado: ${valor}`;
            resultado.style.display = 'block';
            guardar.style.display = 'inline-flex';
            return true;
        }

        function guardarCodigoBarrasDetectado() {
            if (barcodePendingValue) colocarCodigoBarras(barcodePendingValue);
        }

        function volverATomarFoto() {
            const video = document.getElementById('barcodeScannerVideo');
            const instantanea = document.getElementById('barcodeScannerSnapshot');
            const resultado = document.getElementById('barcodeDetectedValue');
            const guardar = document.getElementById('guardarCodigoBarrasBtn');
            const volver = document.getElementById('volverTomarFotoBtn');
            barcodePendingValue = '';
            if (barcodeScannerControls) barcodeScannerControls.stop();
            barcodeScannerControls = null;
            if (instantanea) {
                instantanea.src = '';
                instantanea.style.display = 'none';
            }
            if (video) video.style.display = 'block';
            if (resultado) {
                resultado.textContent = '';
                resultado.style.display = 'none';
            }
            if (guardar) guardar.style.display = 'none';
            if (volver) volver.style.display = 'none';
            actualizarAnimacionEscaneo(false);
            actualizarIndicadorCodigo(false);
            const status = document.getElementById('barcodeScannerStatus');
            if (status) status.textContent = 'Enfoque nuevamente el código y pulse TOMAR CÓDIGO.';
        }

        async function tomarCodigoBarras() {
            const video = document.getElementById('barcodeScannerVideo');
            const status = document.getElementById('barcodeScannerStatus');
            const botonTomar = document.querySelector('[onclick="tomarCodigoBarras()"]');
            if (!video || !status || !barcodeScannerStream) {
                if (status) status.textContent = 'La cámara todavía no está lista. Inténtelo nuevamente.';
                return;
            }
            if (botonTomar) botonTomar.disabled = true;
            status.textContent = 'Capturando fotograma...';
            actualizarAnimacionEscaneo(false);
            if (video.readyState < 2) {
                status.textContent = 'Espere un momento a que la cámara esté lista.';
                if (botonTomar) botonTomar.disabled = false;
                return;
            }
            try {
                if (barcodeScannerControls) {
                    barcodeScannerControls.stop();
                    barcodeScannerControls = null;
                }
                const inicioVideo = Date.now();
                while ((!video.videoWidth || !video.videoHeight) && Date.now() - inicioVideo < 2000) {
                    await new Promise((resolver) => setTimeout(resolver, 100));
                }
                if (!video.videoWidth || !video.videoHeight) {
                    throw new Error('El video no entregó un fotograma válido.');
                }
                const escala = 2;
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth * escala;
                canvas.height = video.videoHeight * escala;
                const contexto = canvas.getContext('2d', { willReadFrequently: true });
                contexto.drawImage(video, 0, 0, canvas.width, canvas.height);
                const instantanea = document.getElementById('barcodeScannerSnapshot');
                const volver = document.getElementById('volverTomarFotoBtn');
                if (instantanea) {
                    instantanea.src = canvas.toDataURL('image/jpeg', 0.92);
                    instantanea.style.display = 'block';
                    video.style.display = 'none';
                }
                if (volver) volver.style.display = 'inline-flex';
                actualizarAnimacionEscaneo(true);
                status.textContent = 'Escaneando código...';
                let codigoDetectado = '';
                const candidatosDetectados = [];
                if ('BarcodeDetector' in window) {
                    try {
                        const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39'] });
                        const resultados = await detector.detect(await createImageBitmap(canvas));
                        resultados.forEach((resultado) => {
                            if (resultado?.rawValue) {
                                candidatosDetectados.push(resultado.rawValue);
                            }
                        });
                    } catch (error) {
                        candidatosDetectados.length = 0;
                    }
                }

                if (!candidatosDetectados.length && window.ZXingBrowser) {
                    const pistas = new Map();
                    if (window.ZXing?.DecodeHintType && window.ZXing?.BarcodeFormat) {
                        pistas.set(ZXing.DecodeHintType.TRY_HARDER, true);
                        pistas.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                            ZXing.BarcodeFormat.EAN_13,
                            ZXing.BarcodeFormat.EAN_8,
                            ZXing.BarcodeFormat.UPC_A,
                            ZXing.BarcodeFormat.UPC_E,
                            ZXing.BarcodeFormat.CODE_128,
                            ZXing.BarcodeFormat.CODE_39
                        ]);
                    }
                    const lector = new ZXingBrowser.BrowserMultiFormatReader(pistas);
                    try {
                        const resultado = await lector.decodeFromImageUrl(canvas.toDataURL('image/png'));
                        if (resultado?.getText) {
                            candidatosDetectados.push(resultado.getText());
                        }
                    } catch (error) {
                        // Se mantiene la captura sin interrumpir el flujo.
                    }
                }

                if (!candidatosDetectados.length && window.ZXingBrowser) {
                    status.textContent = 'Mejorando la imagen y reintentando...';
                    const imagenDatos = contexto.getImageData(0, 0, canvas.width, canvas.height);
                    for (let i = 0; i < imagenDatos.data.length; i += 4) {
                        const gris = (imagenDatos.data[i] * 0.299) + (imagenDatos.data[i + 1] * 0.587) + (imagenDatos.data[i + 2] * 0.114);
                        const contraste = gris > 145 ? 255 : 0;
                        imagenDatos.data[i] = contraste;
                        imagenDatos.data[i + 1] = contraste;
                        imagenDatos.data[i + 2] = contraste;
                    }
                    contexto.putImageData(imagenDatos, 0, 0);
                    const lectorMejorado = new ZXingBrowser.BrowserMultiFormatReader();
                    try {
                        const resultado = await lectorMejorado.decodeFromImageUrl(canvas.toDataURL('image/png'));
                        if (resultado?.getText) {
                            candidatosDetectados.push(resultado.getText());
                        }
                    } catch (error) {
                        // Se mantiene la captura sin interrumpir el flujo.
                    }
                }

                if (!candidatosDetectados.length && window.ZXingBrowser) {
                    status.textContent = 'Probando otra orientación del código...';
                    const rotado = document.createElement('canvas');
                    rotado.width = canvas.height;
                    rotado.height = canvas.width;
                    const contextoRotado = rotado.getContext('2d');
                    contextoRotado.translate(rotado.width / 2, rotado.height / 2);
                    contextoRotado.rotate(Math.PI / 2);
                    contextoRotado.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);
                    const lectorRotado = new ZXingBrowser.BrowserMultiFormatReader();
                    try {
                        const resultado = await lectorRotado.decodeFromImageUrl(rotado.toDataURL('image/png'));
                        if (resultado?.getText) {
                            candidatosDetectados.push(resultado.getText());
                        }
                    } catch (error) {
                        // Se mantiene la captura sin interrumpir el flujo.
                    }
                }

                codigoDetectado = elegirCodigoBarrasValido(candidatosDetectados);
                if (codigoDetectado && mostrarCodigoDetectado(codigoDetectado)) return;
                actualizarIndicadorCodigo(false);
                actualizarAnimacionEscaneo(false);
                status.textContent = 'No se detectó ningún código de barras. Coloque el código frente a la cámara e inténtelo nuevamente.';
            } catch (error) {
                actualizarIndicadorCodigo(false);
                actualizarAnimacionEscaneo(false);
                status.textContent = 'No se pudo leer el código. Acerque las barras, mejore la luz e inténtelo nuevamente.';
            } finally {
                if (botonTomar) botonTomar.disabled = false;
            }
        }

        async function abrirEscanerCodigoBarras(targetId) {
            barcodeScannerTarget = targetId;
            const modal = document.getElementById('barcodeScannerModal');
            const video = document.getElementById('barcodeScannerVideo');
            const status = document.getElementById('barcodeScannerStatus');
            if (!modal || !video || !status) return;
            if (!navigator.mediaDevices?.getUserMedia) {
                mostrarAlerta('error', 'La cámara no está disponible en este navegador.');
                return;
            }
            try {
                barcodeScannerStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                video.srcObject = barcodeScannerStream;
                modal.style.display = 'flex';
                document.body.classList.add('modal-open');
                barcodePendingValue = '';
                const resultado = document.getElementById('barcodeDetectedValue');
                const guardar = document.getElementById('guardarCodigoBarrasBtn');
                const instantanea = document.getElementById('barcodeScannerSnapshot');
                if (instantanea) {
                    instantanea.src = '';
                    instantanea.style.display = 'none';
                }
                video.style.display = 'block';
                actualizarAnimacionEscaneo(false);
                if (resultado) resultado.style.display = 'none';
                if (guardar) guardar.style.display = 'none';
                const volver = document.getElementById('volverTomarFotoBtn');
                if (volver) volver.style.display = 'none';
                actualizarIndicadorCodigo(false);
                status.innerHTML = '<strong>Enfoque el código de barras.</strong><br>Si no se detecta automáticamente, escríbalo en el campo.';

                if (!('BarcodeDetector' in window) && !window.ZXingBrowser) {
                    status.innerHTML = '<strong>Cámara activada.</strong><br>Este navegador no tiene lector automático. Escriba el código manualmente.';
                }

            } catch (error) {
                cerrarEscanerCodigoBarras();
                mostrarAlerta('error', 'No se pudo abrir la cámara. Verifique los permisos.');
            }
        }

        function cerrarEscanerCodigoBarras() {
            if (barcodeCaptureTimeout) clearTimeout(barcodeCaptureTimeout);
            barcodeCaptureTimeout = null;
            if (barcodeScannerControls) barcodeScannerControls.stop();
            barcodeScannerControls = null;
            if (barcodeScannerStream) barcodeScannerStream.getTracks().forEach(track => track.stop());
            barcodeScannerStream = null;
            const video = document.getElementById('barcodeScannerVideo');
            if (video) video.srcObject = null;
            const modal = document.getElementById('barcodeScannerModal');
            if (modal) modal.style.display = 'none';
            const instantanea = document.getElementById('barcodeScannerSnapshot');
            if (instantanea) instantanea.src = '';
            const resultado = document.getElementById('barcodeDetectedValue');
            if (resultado) resultado.textContent = '';
            barcodePendingValue = '';
            actualizarAnimacionEscaneo(false);
            document.body.classList.remove('modal-open');
        }

        async function verificarCodigoBarrasDuplicado(codigoBarras) {
            const valor = (codigoBarras || '').trim();
            if (!valor) {
                return { duplicado: false, message: 'Sin código de barras para validar.' };
            }

            try {
                const response = await fetch(`${base_url}/Controllers/ProductoController.php?action=verificarCodigoBarrasUnico&codigo_barras=${encodeURIComponent(valor)}`);
                const data = await response.json();
                if (!data || data.success === false) {
                    return { duplicado: false, message: 'No se pudo validar el código de barras.' };
                }
                return {
                    duplicado: !!data.duplicado,
                    message: data.message || 'Código de barras disponible.'
                };
            } catch (error) {
                console.error('Error verificando código de barras:', error);
                return { duplicado: false, message: 'No se pudo validar el código de barras.' };
            }
        }

        async function enviarFormulario(event) {
            event.preventDefault();

            const imageValid = await validateImageInput('imagen', 'imagenFeedback', PRODUCT_IMAGE_RULES);
            if (!imageValid) {
                mostrarAlerta('error', 'La imagen del producto debe ser PNG, medir 600 x 1050 px y pesar menos de 500 KB.');
                return false;
            }

            const codigoBarrasInput = document.getElementById('codigo_barras');
            const codigoBarrasValor = codigoBarrasInput ? codigoBarrasInput.value.trim() : '';
            if (codigoBarrasValor) {
                const validacion = await verificarCodigoBarrasDuplicado(codigoBarrasValor);
                if (validacion.duplicado) {
                    mostrarAlerta('error', validacion.message || 'Este código de barras ya está asociado a un producto.');
                    codigoBarrasInput.focus();
                    return false;
                }
            }
            
            const form = document.getElementById('registroForm');
            const formData = new FormData(form);
            formData.append('action', 'crear');

            try {
                const response = await fetch(`${base_url}/Controllers/ProductoController.php`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    toggleModal();
                    mostrarAlerta('success', '¡EL PRODUCTO SE HA CREADO CORRECTAMENTE!');
                    notificarCambioDashboard();
                    setTimeout(() => {
                        refrescarProductosManteniendoScroll();
                    }, 1500);
                } else {
                    mostrarAlerta('error', data.message || 'Error al crear el producto');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarAlerta('error', 'Error al procesar la solicitud');
            }

            return false;
        }

        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            if (event.target == viewModal) {
                viewModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        }

        function eliminarProducto(id) {
            console.log('Iniciando eliminación del producto ID:', id);
            console.log('¿Tiene permiso de eliminar?:', tienePermisoEliminar);
            
            if (!tienePermisoEliminar) {
                mostrarAlerta('error', 'No tienes permiso para eliminar productos');
                return;
            }
            
            Swal.fire({
                title: '¿ESTÁS SEGURO?',
                text: "¡NO PODRÁS REVERTIR ESTO!",
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: false,
                showConfirmButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'SÍ, ELIMINAR',
                cancelButtonText: 'CANCELAR',
                allowOutsideClick: false,
                buttonsStyling: false,
                customClass: {
                    popup: 'swal-delete'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Usuario confirmó la eliminación');
                    const formData = new FormData();
                    formData.append('action', 'eliminar');
                    formData.append('id', id);

                    console.log('Enviando fetch a ProductoController...');
                    fetch(`${base_url}/Controllers/ProductoController.php`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response recibido:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Datos del response:', data);
                        if (data.success) {
                            mostrarAlerta('success', '¡EL PRODUCTO HA SIDO ELIMINADO!');
                            notificarCambioDashboard();
                            setTimeout(() => {
                                refrescarProductosManteniendoScroll();
                            }, 1500);
                        } else {
                            console.error('Error en response:', data.message);
                            mostrarAlerta('error', data.message || 'Error al eliminar');
                        }
                    })
                    .catch(error => {
                        console.error('Error en fetch:', error);
                        mostrarAlerta('error', 'Error al procesar la solicitud: ' + error.message);
                    });
                } else {
                    console.log('Usuario canceló la eliminación');
                }
            });
        }

        function confirmarReinicioProductos() {
            Swal.fire({
                title: '¿REINICIAR PRODUCTOS?',
                text: 'Esta acción eliminará todos los productos y dejará el listado vacío. Puedes deshacer el cambio con el botón DESHACER.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, reiniciar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'reiniciar');

                fetch(`${base_url}/Controllers/ProductoController.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo reiniciar los productos');
                    }
                    notificarCambioDashboard();
                    Swal.fire({ icon: 'success', title: 'Productos reiniciados', text: data.message || 'Se han eliminado todos los productos.' })
                        .then(() => location.reload());
                })
                .catch(error => {
                    console.error('Error reiniciando productos:', error);
                    mostrarAlerta('error', error.message || 'Error al reiniciar los productos');
                });
            });
        }

        function confirmarDeshacerReinicioProductos() {
            Swal.fire({
                title: '¿DESHACER REINICIO?',
                text: 'Se restaurará el último estado de los productos eliminados en el reinicio.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#64748b'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'deshacer');

                fetch(`${base_url}/Controllers/ProductoController.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo restaurar los productos');
                    }
                    notificarCambioDashboard();
                    Swal.fire({ icon: 'success', title: 'Restaurado', text: data.message || 'Los productos han sido restaurados.' })
                        .then(() => location.reload());
                })
                .catch(error => {
                    console.error('Error deshaciendo reinicio de productos:', error);
                    mostrarAlerta('error', error.message || 'Error al restaurar los productos');
                });
            });
        }

        function verProducto(id) {
            fetch(`${base_url}/Controllers/ProductoController.php?action=getOne&id=${id}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Error al obtener los datos');
                }

                const prod = response.data;
                
                // Construir HTML del contenido
                let imagenHtml = '';
                const detalleImgSrc = resolverImagenProducto(prod.imagen);
                imagenHtml = `<img src="${detalleImgSrc}" alt="${prod.nombre}" style="max-height: 120px; width: auto; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" onerror="this.onerror=null;this.src='../favicon.ico';">`;
                
                const estadoText = prod.estado == 1 ? 
                    '<span class="estado-activo" style="padding: 6px 12px;"><i class="fas fa-check-circle"></i> ACTIVO</span>' : 
                    '<span class="estado-inactivo" style="padding: 6px 12px;"><i class="fas fa-times-circle"></i> INACTIVO</span>';

                const bloqueIdHtml = esSuperAdmin ? `
                    <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 15px; padding: 0;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #2f4a5a; border-radius: 4px;">
                            <label style="font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-hashtag"></i> ID</label>
                            <p style="color: #2f4a5a; margin: 0; font-size: 14px; font-weight: 600;">${prod.id}</p>
                        </div>
                    </div>
                ` : '';
                
                const html = `
                    <div style="margin-bottom: 15px; text-align: center; padding: 12px; background: linear-gradient(135deg, rgba(47, 74, 90, 0.05) 0%, rgba(53, 145, 202, 0.05) 100%); border-radius: 8px;">
                        <label style="display: block; margin-bottom: 10px; color: #2f4a5a; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-image"></i> IMAGEN DEL PRODUCTO</label>
                        ${imagenHtml}
                    </div>
                    
                    ${bloqueIdHtml}

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #3591CA; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-barcode"></i> CÓDIGO</label>
                            <p style="color: #2f4a5a; margin: 0; font-size: 13px;">${prod.codigo || '-'}</p>
                        </div>
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #3591CA; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-qrcode"></i> CÓDIGO DE BARRAS</label>
                            <p style="color: #2f4a5a; margin: 0; font-size: 13px;">${prod.codigo_barras || '-'}</p>
                        </div>
                    </div>

                    <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #2f4a5a; border-radius: 4px; margin-bottom: 10px;">
                        <label style="font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-box"></i> NOMBRE PRODUCTO</label>
                        <p style="color: #2f4a5a; margin: 0; font-size: 14px; font-weight: 600;">${prod.nombre.toUpperCase()}</p>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #3591CA; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-tag"></i> CATEGORÍA</label>
                            <p style="color: #2f4a5a; margin: 0; font-size: 13px;">${(prod.categoria_nombre || '-').toUpperCase()}</p>
                        </div>
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #3591CA; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-toggle-on"></i> ESTADO</label>
                            <p style="margin: 0;">${estadoText}</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #0B6623; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-dollar-sign"></i> PRECIO</label>
                            <p style="color: #0B6623; margin: 0; font-size: 15px; font-weight: 700;">${formatMonedaColombia(Number(prod.stock) === 0 ? 0 : parseFloat(prod.precio))}</p>
                        </div>
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #0B6623; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-cubes"></i> STOCK</label>
                            <p style="color: #0B6623; margin: 0; font-size: 15px; font-weight: 700;">${prod.stock} UND</p>
                        </div>
                    </div>

                    <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #2f4a5a; border-radius: 4px;">
                        <label style="font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-file-alt"></i> DESCRIPCIÓN</label>
                        <p style="color: #333; margin: 0; font-size: 13px; line-height: 1.5; text-align: justify;">${(prod.descripcion || '-').toUpperCase()}</p>
                    </div>
                `;
                
                document.getElementById('viewProductoContent').innerHTML = html;
                document.getElementById('viewModal').style.display = 'flex';
                document.body.classList.add('modal-open');
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('error', 'Error al obtener los detalles del producto');
            });
        }
        
        function cerrarViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        function editarProducto(id) {
            fetch(`${base_url}/Controllers/ProductoController.php?action=getOne&id=${id}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Error al obtener los datos');
                }

                const producto = response.data;
                
                // Llenar el formulario
                document.getElementById('productoId').value = producto.id;
                document.getElementById('idOriginal').value = producto.id;
                const idDisplayEl = document.getElementById('idDisplay');
                if (idDisplayEl) {
                    idDisplayEl.value = producto.id;
                }
                document.getElementById('nombreEdit').value = producto.nombre;
                const ventaPorKiloEdit = document.getElementById('ventaPorKiloEdit');
                if (ventaPorKiloEdit) ventaPorKiloEdit.checked = Number(producto.venta_por_kilo) === 1;
                document.getElementById('descripcionEdit').value = producto.descripcion || '';
                const codigoBarrasEdit = document.getElementById('editProdCodigoBarras');
                if (codigoBarrasEdit) {
                    codigoBarrasEdit.value = producto.codigo_barras || '';
                }
                const categoriaEdit = document.getElementById('categoriaEdit');
                if (categoriaEdit) {
                    const categoriaIdStr = String(producto.categoria_id ?? producto.id_categoria ?? '').trim();
                    categoriaEdit.value = categoriaIdStr;
                }
                // Solo Super Administrador puede editar el ID
                const idDisplay = document.getElementById('idDisplay');
                if (idDisplay) {
                    if (!esSuperAdmin) {
                        idDisplay.setAttribute('readonly', 'readonly');
                        idDisplay.style.backgroundColor = '#f5f5f5';
                        idDisplay.style.cursor = 'not-allowed';
                    } else {
                        idDisplay.removeAttribute('readonly');
                        idDisplay.style.backgroundColor = '#ffffff';
                        idDisplay.style.cursor = 'text';
                    }
                }


                
                // Imagen actual del producto
                const imagenPreview = document.getElementById('imagenPreview');
                const sinImagenText = document.getElementById('sinImagenText');
                const imagenPreviewNueva = document.getElementById('imagenPreviewNueva');
                const placeholderNueva = document.getElementById('imagenPreviewNuevaPlaceholder');

                if (imagenPreview) {
                    const urlImagen = resolverImagenProducto(producto.imagen);
                    imagenPreview.src = urlImagen;
                    imagenPreview.style.display = 'block';
                    imagenPreview.onerror = function() {
                        this.onerror = null;
                        this.src = '../favicon.ico';
                    };
                }
                if (sinImagenText) {
                    sinImagenText.style.display = 'none';
                }
                if (imagenPreviewNueva) {
                    imagenPreviewNueva.src = '';
                    imagenPreviewNueva.style.display = 'none';
                }
                if (placeholderNueva) {
                    placeholderNueva.style.display = 'inline';
                }

                // permitir visualizar archivo nuevo si se elige
                const inputImagen = document.getElementById('imagenEdit');
                if (inputImagen) {
                    inputImagen.value = null; // limpiar por si quedaba algo
                }
                
                // Mostrar modal
                document.getElementById('editModal').style.display = 'flex';
                document.body.classList.add('modal-open');
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('error', 'Error al cargar los datos del producto');
            });
        }

        function cerrarEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.classList.remove('modal-open');
            resetProductoModalState();
        }

        function abrirModalCrearProducto() {
            resetProductoModalState();
            document.getElementById('registroModal').style.display = 'flex';
            document.body.classList.add('modal-open');
        }

        function cambiarEstado(id, estado) {
            const switchEl = document.getElementById(`estado_${id}`);
            const row = switchEl ? switchEl.closest('tr') : null;
            const editBtnInRow = row ? row.querySelector('button.btn-editar[title="Editar"]') : null;

            Swal.fire({
                title: '¿CAMBIAR ESTADO?',
                text: `¿DESEAS ${estado ? 'ACTIVAR' : 'DESACTIVAR'} ESTE PRODUCTO?`,
                icon: 'question',
                showCancelButton: false,
                showDenyButton: false,
                showConfirmButton: true,
                confirmButtonColor: '#2f4a5a',
                confirmButtonText: 'SÍ, CAMBIAR',
                allowOutsideClick: false,
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // ajustar el botón de edición en la interfaz inmediatamente
                    if (editBtnInRow) {
                        editBtnInRow.disabled = !estado;
                    }

                    const formData = new FormData();
                    formData.append('action', 'cambiarEstado');
                    formData.append('id', id);
                    formData.append('estado', estado ? 1 : 0);

                    fetch(`${base_url}/Controllers/ProductoController.php`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarAlerta('success', '¡ESTADO ACTUALIZADO!');
                            notificarCambioDashboard();
                            setTimeout(() => {
                                refrescarProductosManteniendoScroll();
                            }, 1500);
                        } else {
                            throw new Error(data.message || 'Error al actualizar el estado');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        mostrarAlerta('error', 'Error al procesar la solicitud');
                        // Revertir el switch si hubo error
                        if (switchEl) switchEl.checked = !estado;
                        if (editBtnInRow) editBtnInRow.disabled = estado; // restore previous
                    });
                } else {
                    // Si el usuario cancela, revertir el switch
                    if (switchEl) switchEl.checked = !estado;
                }
            });
        }

        // Manejar submit del formulario de edición
        document.addEventListener('DOMContentLoaded', function() {
            const buscarCategoria = document.getElementById('buscarCategoriaProducto');
            const categoriaSelect = document.getElementById('categoria_id');
            const camposCodigoBarras = [
                document.getElementById('codigo_barras'),
                document.getElementById('editProdCodigoBarras')
            ].filter(Boolean);

            camposCodigoBarras.forEach((campo) => {
                campo.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                    }
                });
            });

            if (buscarCategoria && categoriaSelect) {
                buscarCategoria.addEventListener('input', filtrarCategoriasProducto);
                buscarCategoria.addEventListener('focus', function () {
                    const results = document.getElementById('categoriaSearchResults');
                    if (results) {
                        results.style.display = 'block';
                    }
                });
                buscarCategoria.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const primeraVisible = Array.from(document.querySelectorAll('.categoria-search-option')).find(option => option.style.display !== 'none');
                        if (primeraVisible) {
                            categoriaSelect.value = primeraVisible.dataset.id;
                            buscarCategoria.value = primeraVisible.dataset.name || '';
                            generarCodigoAutomatico();
                            cerrarListaCategoriasProducto(categoriaSelect);
                        }
                    }
                });
                buscarCategoria.addEventListener('blur', function () {
                    setTimeout(() => cerrarListaCategoriasProducto(categoriaSelect), 120);
                });
            }

            document.addEventListener('click', function (event) {
                const option = event.target.closest('.categoria-search-option');
                if (option) {
                    const select = document.getElementById('categoria_id');
                    const input = document.getElementById('buscarCategoriaProducto');
                    if (select && input) {
                        select.value = option.dataset.id;
                        input.value = option.dataset.name || '';
                        generarCodigoAutomatico();
                        cerrarListaCategoriasProducto(select);
                    }
                }
            });

            // Conservar la posición al recargar o volver a cargar la página.
            window.addEventListener('scroll', guardarPosicionScrollProductos, { passive: true });
            const tableWrapper = document.querySelector('.table-wrapper');
            if (tableWrapper) {
                tableWrapper.addEventListener('scroll', guardarPosicionScrollProductos, { passive: true });
            }
            window.addEventListener('pagehide', guardarPosicionScrollProductos);

            // Cargar productos al iniciar y restaurar la posición después del repintado.
            cargarProductos(() => restaurarPosicionScrollProductos());
            
            const formularioEdicion = document.getElementById('productoForm');
            if (formularioEdicion) {
                formularioEdicion.addEventListener('submit', function(e) {
                    e.preventDefault();
                    validateImageInput('imagenEdit', 'imagenEditFeedback', PRODUCT_IMAGE_RULES).then((imageValid) => {
                        if (!imageValid) {
                            mostrarAlerta('error', 'La imagen del producto debe ser PNG, medir 600 x 1050 px y pesar menos de 500 KB.');
                            return;
                        }
                    
                        const formData = new FormData();
                        formData.append('action', 'editar');
                        formData.append('id_original', document.getElementById('idOriginal').value);
                        const idDisplayEl = document.getElementById('idDisplay');
                        const idEnviar = idDisplayEl ? idDisplayEl.value.trim() : document.getElementById('idOriginal').value;
                        formData.append('id', idEnviar);
                        formData.append('nombre', document.getElementById('nombreEdit').value.trim());
                        formData.append('descripcion', document.getElementById('descripcionEdit').value.trim());
                        formData.append('categoria_id', document.getElementById('categoriaEdit').value);
                        formData.append('codigo_barras', document.getElementById('editProdCodigoBarras').value.trim());
                        formData.append('venta_por_kilo', document.getElementById('ventaPorKiloEdit').checked ? '1' : '0');
                        
                        const imagenFile = document.getElementById('imagenEdit').files[0];
                        if (imagenFile) {
                            formData.append('imagen', imagenFile);
                        }

                        fetch(`${base_url}/Controllers/ProductoController.php`, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                cerrarEditModal();
                                mostrarAlerta('success', '¡EL PRODUCTO HA SIDO ACTUALIZADO!');
                                notificarCambioDashboard();
                                setTimeout(() => {
                                    refrescarProductosManteniendoScroll();
                                }, 1500);
                            } else {
                                mostrarAlerta('error', data.message || 'Error al actualizar');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            mostrarAlerta('error', 'Error al procesar la solicitud');
                        });
                    });
                });
            }

            bindImageValidator('imagen', 'imagenFeedback', PRODUCT_IMAGE_RULES, function(file) {
                const imagenPreview = document.getElementById('imagenPreviewCrear');
                const placeholder = document.getElementById('imagenPreviewCrearPlaceholder');
                if (!imagenPreview || !file) return;
                const objectUrl = URL.createObjectURL(file);
                imagenPreview.src = objectUrl;
                imagenPreview.style.display = 'block';
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                imagenPreview.onload = function() {
                    URL.revokeObjectURL(objectUrl);
                };
            }, function() {
                const imagenPreview = document.getElementById('imagenPreviewCrear');
                const placeholder = document.getElementById('imagenPreviewCrearPlaceholder');
                if (imagenPreview) {
                    imagenPreview.style.display = 'none';
                    imagenPreview.src = '';
                }
                if (placeholder) {
                    placeholder.style.display = 'inline';
                }
            });
            bindImageValidator('imagenEdit', 'imagenEditFeedback', PRODUCT_IMAGE_RULES, function(file) {
                const imagenPreview = document.getElementById('imagenPreviewNueva');
                const placeholder = document.getElementById('imagenPreviewNuevaPlaceholder');
                const sinImagenText = document.getElementById('sinImagenText');
                if (!imagenPreview || !file) return;
                const objectUrl = URL.createObjectURL(file);
                imagenPreview.src = objectUrl;
                imagenPreview.style.display = 'block';
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                if (sinImagenText) {
                    sinImagenText.style.display = 'none';
                }
                imagenPreview.onload = function() {
                    URL.revokeObjectURL(objectUrl);
                };
            }, function() {
                const imagenPreview = document.getElementById('imagenPreviewNueva');
                const placeholder = document.getElementById('imagenPreviewNuevaPlaceholder');
                if (imagenPreview) {
                    imagenPreview.style.display = 'none';
                    imagenPreview.src = '';
                }
                if (placeholder) {
                    placeholder.style.display = 'inline';
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key !== 'Escape') return;
                const registroModal = document.getElementById('registroModal');
                const editModal = document.getElementById('editModal');
                const viewModal = document.getElementById('viewModal');
                if (registroModal && registroModal.style.display === 'flex') {
                    toggleModal();
                } else if (editModal && editModal.style.display === 'flex') {
                    cerrarEditModal();
                } else if (viewModal && viewModal.style.display === 'flex') {
                    cerrarViewModal();
                }
            });
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

        window.addEventListener('storage', (event) => { if (event.key === storageKey) sync(); });
        window.addEventListener('focus', sync);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
        document.addEventListener('DOMContentLoaded', sync);
        sync();
    })();
</script>
</body>
</html>





