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

// Obtener categorías desde el Controller mediante AJAX
$categorias = [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - <?= NOMBRE_EMPRESA ?></title>
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-blue: #2f4a5a;
            --secondary-blue: #3591CA;
            --white: #FFFFFF;
            --black: #000000;
            --light-blue: rgba(47, 74, 90, 0.1);
            --font-saira: 'Saira Condensed', sans-serif;
        }

        body {
            padding-top: <?php echo $esEnIframe ? '0' : '80px'; ?>;
            background-color: #f8f9fa;
            font-family: var(--font-saira);
            text-transform: uppercase;
            overflow: auto;
            height: auto;
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



        /* Estilos para el botón nuevo */
        .btn-nuevo {
            width: auto;
            min-width: 220px;
            margin: 20px;
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
            text-transform: uppercase !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
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

        .swal2-deny,
        .swal2-actions button.swal2-deny {
            display: none !important;
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
            margin: 20px auto;
            width: 100%;
            max-height: calc(100vh - 280px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .table-container {
            overflow-x: auto;
            overflow-y: scroll;
            flex: 1;
            scrollbar-width: thin;
            min-height: 0;
            height: 100%;
            position: relative;
        }

        .table-container::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f4f6f8;
            border-radius: 6px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%);
            border-radius: 6px;
            border: 2px solid #f4f6f8;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a2d4f 0%, #0f1a2e 100%);
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
            padding-right: 0 !important;
        }

        /* Ocultar encabezados sticky cuando modal está abierto */
        body.modal-open .table thead th,
        body.modal-open .roles-table thead th {
            position: relative !important;
            z-index: -1 !important;
        }

        /* Asegurar que los modales están por encima de todo */
        .modal {
            z-index: 9999;
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.35);
        }

        .modal.show {
            display: flex !important;
        }

        .modal-dialog {
            margin: auto;
            width: 100%;
            max-width: 1000px;
        }

        .modal-backdrop {
            z-index: 9998;
        }

        /* Centrar modal */
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 820px;
            overflow: hidden;
            background: #ffffff;
        }

        #imagenActualDiv {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-align: center;
        }

        #imagenPreview {
            max-width: 140px;
            max-height: 140px;
            width: auto;
            height: auto;
            margin: 0 auto;
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

        .main-scroll-panel {
            max-height: calc(100vh - 120px);
            overflow-y: scroll;
            overflow-x: hidden;
            scrollbar-gutter: stable;
            padding-right: 6px;
            scrollbar-width: thin;
            scrollbar-color: #2f4a5a #f4f6f8;
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
            margin: -30px -35px 25px -35px;
            padding: 20px 25px;
            border-radius: 14px 14px 0 0;
            border-bottom: 2px solid #e6e9ee;
            font-size: 22px;
            letter-spacing: 1px;
        }

        .modal-content form {
            padding: 0;
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
            width: 500px !important;
            max-width: 90% !important;
        }

        .swal2-popup.swal-elegant {
            border: 1px solid rgba(47, 74, 90, 0.12) !important;
            padding: 1.4rem !important;
        }

        .swal2-popup.swal-elegant .swal2-icon {
            margin-top: 0.4rem !important;
            margin-bottom: 0.8rem !important;
        }

        .swal2-popup.swal-elegant .swal2-timer-progress-bar {
            background: linear-gradient(135deg, #2f4a5a 0%, #1a2d4f 100%) !important;
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

        .swal2-actions {
            display: flex !important;
            flex-direction: row !important;
            gap: 12px !important;
            justify-content: center !important;
            flex-wrap: nowrap !important;
            min-width: 400px !important;
            width: 100% !important;
            margin-top: 1rem !important;
        }

        .swal2-confirm,
        .swal2-cancel {
            font-family: var(--font-saira) !important;
            border-radius: 8px !important;
            padding: 8px 16px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            transition: all 0.3s ease !important;
            font-size: 13px !important;
            max-width: 140px !important;
            min-width: 110px !important;
            text-transform: uppercase !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12) !important;
        }

        .swal2-confirm {
            background: #3b82f6 !important;
            border: 2px solid #3b82f6 !important;
            color: #ffffff !important;
        }

        .swal2-confirm:hover {
            background: #3b82f6 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-1px) !important;
        }

        .swal2-confirm:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12) !important;
        }
        
        .swal2-cancel {
            background: #64748b !important;
            border: 2px solid #64748b !important;
            color: #ffffff !important;
        }
        
        .swal2-cancel:hover {
            background: #64748b !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-1px) !important;
        }

        .swal2-cancel:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12) !important;
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

        /* Asegurar que los botones están lado a lado */
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
        
        .estadistica-card::-webkit-scrollbar-corner,
        .table-wrapper::-webkit-scrollbar-corner {
            background: #f4f6f8;
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
            background: white;
            color: #242629;
        }
        
        .form-group select option:disabled {
            color: #6b7280;
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

        th:nth-child(1), td:nth-child(1) { width: 4%; min-width: 40px; }
        th:nth-child(2), td:nth-child(2) { width: 10%; min-width: 90px; }
        th:nth-child(3), td:nth-child(3) { width: 12%; min-width: 100px; }
        th:nth-child(4), td:nth-child(4) { 
            width: 15%; 
            min-width: 120px;
            word-break: break-word;
            white-space: normal;
        }
        th:nth-child(5), td:nth-child(5) { 
            width: 8%; 
            min-width: 80px;
            white-space: nowrap;
        }
        th:nth-child(6), td:nth-child(6) { width: 12%; min-width: 110px; }
        th:nth-child(7), td:nth-child(7) { width: 12%; min-width: 110px; }
        th:nth-child(8), td:nth-child(8) { 
            width: 10%; 
            min-width: 90px;
            padding: 14px 8px;
        }
        th:nth-child(9), td:nth-child(9) { width: 10%; min-width: 100px; }

        .roles-table td:hover::after {
            content: attr(title);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: -30px;
            z-index: 1000;
            background: linear-gradient(135deg, rgba(47, 74, 90, 0.95) 0%, rgba(26, 45, 79, 0.95) 100%);
            color: white;
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
            background: var(--white);
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

        .main-scroll-panel {
            overflow-y: scroll !important;
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
            scrollbar-width: thin !important;
            padding-right: 6px !important;
        }

        .table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
            max-height: none !important;
            height: auto !important;
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

        .main-scroll-panel {
            max-height: none !important;
            overflow-y: visible !important;
            padding-bottom: 120px !important;
        }
    </style>
</head>
<body>
    <!-- Reemplazar la sección hero-section actual por esto -->
    <!-- Reemplazar la sección hero-section actual por esto -->
    <div class="title_equipo">
        <h1><i class="fas fa-tags"></i> CATEGORÍAS</h1>
    </div>

    <?php if ($tienePermisoCrear): ?>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; margin: 20px 0;">
        <button class="btn-nuevo" onclick="toggleModal('crear')">
            <i class="fas fa-plus"></i> NUEVA CATEGORÍA
        </button>
        <?php if (PermisosHelper::esSuperAdminSesion() && empty($_SESSION['superadmin_modo_empresa'])): ?>
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <button type="button" class="btn-nuevo btn-nuevo-reset" onclick="confirmarReinicioCategorias()">
                <i class="fas fa-broom"></i> REINICIAR
            </button>
            <button type="button" class="btn-nuevo btn-nuevo-undo" onclick="confirmarDeshacerReinicioCategorias()">
                <i class="fas fa-undo"></i> DESHACER
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div id="registroModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="toggleModal()">&times;</span>
            <h2 style="text-align: center;">NUEVA CATEGORÍA</h2>
            <div id="error-message" style="display: none;"></div>
            <form id="registroForm" onsubmit="return enviarFormulario(event)" autocomplete="off" enctype="multipart/form-data" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre"><i class="fas fa-tag"></i> NOMBRE CATEGORÍA</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ingrese el nombre" autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label for="descripcion"><i class="fas fa-file-alt"></i> DESCRIPCIÓN CATEGORÍA</label>
                    <textarea id="descripcion" name="descripcion" placeholder="Ingrese la descripción" rows="4" style="border: 1px solid #e6e9ee; border-radius: 8px; padding: 14px 16px; width: 100%; font-size: 15px; text-transform: uppercase; background: #ffffff; color: #242629; outline: none; transition: all 0.3s ease; resize: none; max-height: 150px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #2f4a5a #f0f0f0;"></textarea>
                </div>
                <div class="form-group">
                    <label for="imagen"><i class="fas fa-image"></i> AGREGAR FOTO</label>
                    <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div style="flex:1; min-width:240px;">
                            <input type="file" id="imagen" name="imagen" accept="image/png,image/jpeg,image/jpg,image/webp">
                            <small style="display:block; margin-top:6px; color:#667085;">Cualquier imagen PNG, JPG, JPEG o WEBP. El sistema la recorta a un cuadrado de 1000 × 1000 px y la comprime a menos de 500 KB automáticamente.</small>
                            <div id="imagenFeedback" class="image-feedback" style="display:none;"></div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:center; min-width:180px; min-height:140px; border:1px dashed #d0d7de; border-radius:8px; padding:8px; background:#fafafa;">
                            <img id="imagenCreatePreview" src="" alt="Vista previa de la imagen" style="display:none; max-width:160px; max-height:160px; object-fit:contain; border-radius:6px; border:1px solid #e6e9ee;">
                            <span id="imagenCreatePreviewPlaceholder" style="color:#667085; font-size:12px;">PREVIEW</span>
                        </div>
                    </div>
                    <div id="imagenCreatePreviewWrap" style="display:none; margin-top:10px; padding:10px; border:1px dashed #d0d7de; border-radius:8px; background:#f8fafc; text-align:center;">
                        <img id="imagenCreatePreviewSecondary" src="" alt="Vista previa de la imagen" style="max-width:100%; max-height:180px; width:auto; height:auto; object-fit:contain; border-radius:6px; display:none;">
                    </div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> GUARDAR CATEGORÍA</button>
                    <button type="button" id="btnEditarRecorteCrear" onclick="if (window.categoriaCropperCrear) window.categoriaCropperCrear.reopen();" style="display:none; padding:12px 16px; border:1px solid #1d4ed8; background:#ffffff; color:#1d4ed8; border-radius:6px; font-weight:600; cursor:pointer;"><i class="fas fa-crop-alt"></i> EDITAR RECORTE</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Modal de Edición de Categoría -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarEditModal()">&times;</span>
            <h2 style="text-align: center;">ACTUALIZAR CATEGORÍA</h2>
            <form id="categoriaForm" autocomplete="off" style="text-align: left;" novalidate>
                <input type="hidden" id="categoriaId" name="id">
                <input type="hidden" id="idOriginal" name="idOriginal">
                
                <div style="margin-bottom: 20px; text-align: center;">
                    <label style="display: block; margin-bottom: 8px; color: #2f4a5a; font-weight: 600;">IMAGEN ACTUAL</label>
                    <div id="imagenActualDiv" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <img id="imagenPreview" src="" alt="Categoría" style="max-height: 160px; width: auto; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                        <p id="sinImagenText" style="margin: 0; color: #999;">SIN IMAGEN</p>
                        <div style="margin-top:10px;">
                            <button type="button" id="btnRecortarActual" onclick="recortarImagenActualCategoria()" style="display:none; padding:8px 14px; border:1px solid #1d4ed8; background:#ffffff; color:#1d4ed8; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;"><i class="fas fa-crop-alt"></i> RECORTAR IMAGEN ACTUAL</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="idDisplay"><i class="fas fa-hashtag"></i> ID CATEGORÍA</label>

                    <input id="idDisplay" name="idDisplay" type="text" autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="nombreEdit"><i class="fas fa-tag"></i> NOMBRE CATEGORÍA</label>
                    <input id="nombreEdit" name="nombreEdit" type="text" autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; font-size: 14px; text-transform: uppercase;">
                </div>
                
                <div class="form-group">
                    <label for="descripcionEdit"><i class="fas fa-file-alt"></i> DESCRIPCIÓN CATEGORÍA</label>
                    <textarea id="descripcionEdit" name="descripcionEdit" style="width: 100%; padding: 12px; border: 1px solid #e6e9ee; border-radius: 6px; text-transform: uppercase; resize: none; max-height: 150px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #2f4a5a #f0f0f0; font-size: 14px;" rows="5"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="imagenEdit"><i class="fas fa-image"></i> NUEVA IMAGEN</label>
                    <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div style="flex:1; min-width:240px;">
                            <input type="file" id="imagenEdit" name="imagenEdit" accept="image/png,image/jpeg,image/jpg,image/webp" style="width: 100%; padding: 10px; border: 1px solid #e6e9ee; border-radius: 6px; font-size: 14px;">
                            <small style="display: block; margin-top: 6px; color: #999;">La imagen anterior se eliminará automáticamente al subir una nueva</small>
                            <small style="display:block; margin-top:6px; color:#667085;">Cualquier imagen PNG, JPG, JPEG o WEBP. El sistema la recorta a un cuadrado de 1000 × 1000 px y la comprime a menos de 500 KB automáticamente.</small>
                            <div id="imagenEditFeedback" class="image-feedback" style="display:none;"></div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:center; min-width:180px; min-height:140px; border:1px dashed #d0d7de; border-radius:8px; padding:8px; background:#fafafa;">
                            <img id="imagenEditPreview" src="" alt="Vista previa de la imagen seleccionada" style="display:none; max-width:160px; max-height:160px; object-fit:contain; border-radius:6px; border:1px solid #e6e9ee;">
                            <span id="imagenEditPreviewPlaceholder" style="color:#667085; font-size:12px;">PREVIEW</span>
                        </div>
                    </div>
                    <div id="imagenEditPreviewWrap" style="display:none; margin-top:10px; padding:10px; border:1px dashed #d0d7de; border-radius:8px; background:#f8fafc; text-align:center;">
                        <img id="imagenEditPreviewSecondary" src="" alt="Vista previa de la imagen seleccionada" style="max-width:100%; max-height:180px; width:auto; height:auto; object-fit:contain; border-radius:6px; display:none;">
                    </div>
                </div>
                
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> ACTUALIZAR CATEGORÍA</button>
                    <button type="button" id="btnEditarRecorteEditar" onclick="if (window.categoriaCropperEditar) window.categoriaCropperEditar.reopen();" style="display:none; padding:12px 16px; border:1px solid #1d4ed8; background:#ffffff; color:#1d4ed8; border-radius:6px; font-weight:600; cursor:pointer;"><i class="fas fa-crop-alt"></i> EDITAR RECORTE</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Modal de Visualización de Categoría -->
    <div id="viewModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarViewModal()">&times;</span>
            <h2 style="text-align: center;">DETALLES DE LA CATEGORÍA</h2>
            <div id="viewCategoriaContent" style="text-align: left;">
                <!-- Contenido dinámico de la CATEGORÍA -->
            </div>
        </div>
    </div>

    <div class="container main-scroll-panel">
        <div class="estadistica-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-tag"></i> NOMBRE</th>
                            <th><i class="fas fa-file-alt"></i> DESCRIPCIÓN</th>
                            <th><i class="fas fa-image"></i> IMAGEN</th>
                            <th><i class="fas fa-toggle-on"></i> ESTADO</th>
                            <th><i class="fas fa-calendar"></i> FECHA CREACIÓN</th>
                            <th><i class="fas fa-sync-alt"></i> FECHA ACTUALIZACIÓN</th>
                            <th><i class="fas fa-tools"></i> ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="categorias-tbody">
                        <!-- Las categorías se cargarán aquí mediante JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="<?= base_url() ?>/Assets/js/image-cropper.js"></script>
    <script>
        // Permiso del usuario (variables globales del HTML generado por PHP arriba)
        const tienePermisoEditar = <?= json_encode($tienePermisoEditar) ?>;
        const tienePermisoEliminar = <?= json_encode($tienePermisoEliminar) ?>;
        const esSuperAdmin = <?= json_encode($esSuperAdmin) ?>;
        const base_url = <?= json_encode(base_url()) ?>;
        const resolveAppUrl = (path) => {
            const value = String(path || '').trim();
            if (!value) return base_url;
            if (/^https?:\/\//i.test(value) || value.startsWith('data:')) return value;
            if (value.startsWith('/')) return base_url + value;
            if (value.startsWith('../')) return `${base_url}${value.replace(/^\.\.\//, '/')}`;
            return `${base_url}/${value.replace(/^\/+/, '')}`;
        };
        const CATEGORIA_CONTROLLER_URL = `${base_url}/Controllers/CategoriaController.php`;
        const CATEGORY_IMAGE_RULES = {
            maxBytes: 500 * 1024,
            mime: ['image/png', 'image/jpeg', 'image/jpg'],
            width: 2400,
            height: 1400
        };

        const SCROLL_SAVE_KEY = 'categorias-scroll-position';
        let restoredScrollY = null;
        const savedScrollPosition = sessionStorage.getItem(SCROLL_SAVE_KEY);
        if (savedScrollPosition !== null) {
            restoredScrollY = Number(savedScrollPosition) || 0;
            sessionStorage.removeItem(SCROLL_SAVE_KEY);
        }

        window.addEventListener('beforeunload', () => {
            sessionStorage.setItem(SCROLL_SAVE_KEY, String(window.scrollY || 0));
        });

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
            const requiredText = `${CATEGORY_IMAGE_RULES.width} × ${CATEGORY_IMAGE_RULES.height} px`;
            const requiredColor = dimensionsOk ? '#0f8a4b' : '#c0392b';
            const messageColor = ok ? '#0f8a4b' : '#c0392b';
            const weightRequiredText = `${formatBytesToKb(CATEGORY_IMAGE_RULES.maxBytes)} KB`;
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

        function renderSelectedImagePreview(inputId, previewId, wrapId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const wrap = document.getElementById(wrapId);
            if (!input || !preview || !wrap) return;

            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                preview.src = '';
                preview.style.display = 'none';
                wrap.style.display = 'none';
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            preview.onload = function () {
                URL.revokeObjectURL(objectUrl);
            };
            preview.onerror = function () {
                URL.revokeObjectURL(objectUrl);
            };
            preview.src = objectUrl;
            preview.style.display = 'block';
            wrap.style.display = 'block';
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
                if (file.size >= rules.maxBytes) {
                    resolve({ valid: false, sizeValid: false, dimensionsValid: false, width: 0, height: 0, sizeLabel, message: 'El peso supera el límite permitido.' });
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                const image = new Image();
                let settled = false;
                const finish = (result) => {
                    if (settled) return;
                    settled = true;
                    clearTimeout(timeoutId);
                    URL.revokeObjectURL(objectUrl);
                    resolve(result);
                };
                const timeoutId = setTimeout(() => {
                    finish({ valid: false, sizeValid: false, dimensionsValid: false, width: 0, height: 0, sizeLabel, message: 'La imagen tardó demasiado en cargarse.' });
                }, 1000);
                image.onload = () => {
                    const width = Number(image.naturalWidth || 0);
                    const height = Number(image.naturalHeight || 0);

                    const sizeValid = true;
                    const requiredWidth = typeof rules.width === 'number' ? rules.width : null;
                    const requiredHeight = typeof rules.height === 'number' ? rules.height : null;
                    const dimensionsValid = (requiredWidth === null || width === requiredWidth) && (requiredHeight === null || height === requiredHeight);

                    if (!sizeValid && !dimensionsValid) {
                        finish({ valid: false, sizeValid: false, dimensionsValid: false, width, height, sizeLabel, message: 'El peso y las medidas no cumplen con lo requerido.' });
                        return;
                    }

                    if (!sizeValid) {
                        finish({ valid: false, sizeValid: false, dimensionsValid: true, width, height, sizeLabel, message: 'El peso supera el límite permitido.' });
                        return;
                    }

                    if (!dimensionsValid) {
                        finish({ valid: false, sizeValid: true, dimensionsValid: false, width, height, sizeLabel, message: 'Las medidas no coinciden con las requeridas.' });
                        return;
                    }

                    finish({ valid: true, sizeValid: true, dimensionsValid: true, width, height, sizeLabel, message: 'Imagen válida para guardar.' });
                };
                image.onerror = () => {
                    finish({ valid: false, sizeValid: false, dimensionsValid: false, width: 0, height: 0, sizeLabel, message: 'No se pudo leer la imagen seleccionada.' });
                };
                image.src = objectUrl;
            });
        }

        async function validateImageInput(inputId, feedbackId, rules, previewId = null, wrapId = null) {
            const input = document.getElementById(inputId);
            if (!input) return true;
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                renderImageFeedback(feedbackId, null);
                if (previewId && wrapId) {
                    renderSelectedImagePreview(inputId, previewId, wrapId);
                }
                return true;
            }
            const result = await inspectImageFile(file, rules);
            renderImageFeedback(feedbackId, result);
            if (previewId && wrapId) {
                if (result && result.valid) {
                    renderSelectedImagePreview(inputId, previewId, wrapId);
                } else {
                    const preview = document.getElementById(previewId);
                    const wrap = document.getElementById(wrapId);
                    if (preview) preview.src = '';
                    if (preview) preview.style.display = 'none';
                    if (wrap) wrap.style.display = 'none';
                }
            }
            return !!(result && result.valid);
        }

        function bindImageValidator(inputId, feedbackId, rules, onValidFile = null, onInvalidFile = null, previewId = null, wrapId = null) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('change', async function () {
                const isValid = await validateImageInput(inputId, feedbackId, rules, previewId, wrapId);
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

        function resetCategoriaModalState() {
            const createInput = document.getElementById('imagen');
            const editInput = document.getElementById('imagenEdit');
            const createPreview = document.getElementById('imagenCreatePreview');
            const createPreviewSecondary = document.getElementById('imagenCreatePreviewSecondary');
            const editPreview = document.getElementById('imagenEditPreview');
            const editPreviewSecondary = document.getElementById('imagenEditPreviewSecondary');
            const createWrap = document.getElementById('imagenCreatePreviewWrap');
            const editWrap = document.getElementById('imagenEditPreviewWrap');
            const createPlaceholder = document.getElementById('imagenCreatePreviewPlaceholder');
            const editPlaceholder = document.getElementById('imagenEditPreviewPlaceholder');
            if (createInput) createInput.value = '';
            if (editInput) editInput.value = '';
            [createPreview, createPreviewSecondary].forEach((preview) => {
                if (preview) {
                    preview.src = '';
                    preview.style.display = 'none';
                }
            });
            [editPreview, editPreviewSecondary].forEach((preview) => {
                if (preview) {
                    preview.src = '';
                    preview.style.display = 'none';
                }
            });
            if (createPlaceholder) createPlaceholder.style.display = 'inline';
            if (editPlaceholder) editPlaceholder.style.display = 'inline';
            if (createWrap) createWrap.style.display = 'none';
            if (editWrap) editWrap.style.display = 'none';
            renderImageFeedback('imagenFeedback', null);
            renderImageFeedback('imagenEditFeedback', null);
            if (window.categoriaCropperCrear) window.categoriaCropperCrear.reset(true);
            if (window.categoriaCropperEditar) window.categoriaCropperEditar.reset(true);
            toggleBotonRecorte('btnEditarRecorteCrear', false);
            toggleBotonRecorte('btnEditarRecorteEditar', false);
            toggleBotonRecorte('btnRecortarActual', false);

        }

        function toggleBotonRecorte(id, visible) {
            const btn = document.getElementById(id);
            if (btn) btn.style.display = visible ? 'inline-flex' : 'none';
        }

        function recortarImagenActualCategoria() {
            const img = document.getElementById('imagenPreview');
            if (!img || !img.src || img.style.display === 'none') return;
            if (window.categoriaCropperEditar) {
                window.categoriaCropperEditar.loadFromUrl(img.src);
            }
        }



        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const safeUpper = (value) => escapeHtml(String(value ?? '').toUpperCase());
        const DEFAULT_CATEGORY_ICON = `${base_url}/favicon.ico`;

        function resolverImagenCategoria(imagen) {
            const raw = String(imagen ?? '').trim();
            if (!raw || raw.toLowerCase() === 'favicon.ico') {
                return DEFAULT_CATEGORY_ICON;
            }
            const imagenFile = normalizarNombreImagen(raw);
            if (!imagenFile) {
                return DEFAULT_CATEGORY_ICON;
            }
            return resolveAppUrl('/Assets/images/categorias/' + imagenFile);
        }

        const phpSessionId = (new URLSearchParams(window.location.search)).get('PHPSESSID') || '';

        function preservarSesionEnUrl(url) {
            if (!phpSessionId) return url;
            try {
                const destino = new URL(url, window.location.href);
                if (destino.origin === window.location.origin && destino.pathname.includes('/Controllers/')) {
                    destino.searchParams.set('PHPSESSID', phpSessionId);
                    return destino.toString();
                }
            } catch (error) {
                console.warn('No se pudo conservar la sesión en la petición:', error);
            }
            return url;
        }

        const fetchOriginal = window.fetch.bind(window);
        window.fetch = function(resource, options) {
            return fetchOriginal(preservarSesionEnUrl(resource), options);
        };

        async function obtenerJson(url, options = {}) {
            const response = await fetch(preservarSesionEnUrl(url), {
                credentials: 'same-origin',
                ...options
            });
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (error) {
                console.error('Respuesta JSON inválida de', url, text);
                throw new Error(`Respuesta JSON inválida de ${url}: ${text}`);
            }
        }

        const normalizarNombreImagen = (value) => {
            const raw = String(value ?? '').trim();
            if (!raw) return '';
            const limpio = raw.replace(/\\/g, '/').split('/').pop() || '';
            return encodeURIComponent(limpio);
        };

        // Cargar todas las categorías desde el Controller
        function cargarCategorias(callback = null) {
            obtenerJson(`${CATEGORIA_CONTROLLER_URL}?action=getAll`)
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        const tbody = document.getElementById('categorias-tbody');
                        tbody.innerHTML = '';
                        
                        data.data.forEach(categoria => {
                            const fila = generarFilaCategoria(categoria);
                            tbody.appendChild(fila);
                        });
                        if (typeof callback === 'function') {
                            callback();
                        }
                        if (restoredScrollY !== null) {
                            window.scrollTo({ top: restoredScrollY, behavior: 'auto' });
                            restoredScrollY = null;
                        }
                    } else {
                        console.error('Error al cargar CATEGORÍAs:', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function refrescarCategoriasManteniendoScroll() {
            const scrollY = window.scrollY || 0;
            cargarCategorias(() => {
                window.scrollTo({ top: scrollY, behavior: 'auto' });
            });
        }

        // Generar fila de categoría
        function generarFilaCategoria(cat) {
            const tr = document.createElement('tr');
            const nombreSeguro = safeUpper(cat.nombre);
            const descripcionSegura = safeUpper(cat.descripcion || '');
            
            // Imagen
            const imagenSrc = resolverImagenCategoria(cat.imagen);
            const imagenHTML = `<img src="${imagenSrc}" alt="${nombreSeguro}" style="display:block; margin:0 auto; width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid #dfe5ec; background: #f7f9fb;" onerror="this.onerror=null;this.src='${DEFAULT_CATEGORY_ICON}';">`;
            
            // Estado
            let estadoHTML = '';
            if (tienePermisoEditar) {
                const activo = Number(cat.estado) === 1;
                estadoHTML = `
                    <div class="toggle-switch">
                        <input type="checkbox" id="estado_${cat.id}" ${activo ? 'checked' : ''} onchange="cambiarEstado(${cat.id}, this.checked)">
                        <label for="estado_${cat.id}" class="slider"></label>
                    </div>
                `;
            } else {
                const activo = Number(cat.estado) === 1;
                const claseEstado = activo ? 'estado-activo' : 'estado-inactivo';
                const textoEstado = activo ? 'ACTIVO' : 'INACTIVO';
                estadoHTML = `<span class="${claseEstado}">${textoEstado}</span>`;
            }
            
            // Acciones
            let botonesHTML = `<button class="btn-editar" onclick="verCategoria(${cat.id})" title="Ver"><i class="fas fa-eye"></i></button>`;
            if (tienePermisoEditar) {
                botonesHTML += `<button class="btn-editar" onclick="editarCategoria(${cat.id})" title="Editar"><i class="fas fa-edit"></i></button>`;
            }
            if (tienePermisoEliminar) {
                botonesHTML += `<button class="btn-eliminar" onclick="eliminarCategoria(${cat.id})" title="Eliminar"><i class="fas fa-trash"></i></button>`;
            }
            
            tr.innerHTML = `
                <td>${cat.id}</td>
                <td>${nombreSeguro}</td>
                <td class="descripcion-cell">
                    <div class="descripcion-content">${descripcionSegura}</div>
                </td>
                <td>${imagenHTML}</td>
                <td>${estadoHTML}</td>
                <td>${cat.fecha_creacion || 'Sin especificar'}</td>
                <td>${cat.fecha_actualizacion || cat.fecha_actulizacion || 'Sin especificar'}</td>
                <td>
                    <div class="button-actions">
                        ${botonesHTML}
                    </div>
                </td>
            `;
            
            return tr;
        }

        // Manejar ocultación de headers sticky cuando se abren modales
        (function() {
        })();
        
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
                resetCategoriaModalState();
            }
        }
        
        function limpiarFormulario() {
            document.getElementById('registroForm').reset();
            const errorMessage = document.getElementById('error-message');
            errorMessage.style.display = 'none';
            errorMessage.innerHTML = '';
            document.getElementById('registroForm').style.display = 'block';
            resetCategoriaModalState();
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
                    backdrop: 'rgba(15, 26, 46, 0.45)',
                    customClass: {
                        popup: 'swal-wide swal-elegant'
                    },
                    buttonsStyling: false,
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
                    backdrop: 'rgba(15, 26, 46, 0.45)',
                    customClass: {
                        popup: 'swal-wide swal-elegant'
                    },
                    buttonsStyling: false
                });
            }
        }

        async function enviarFormulario(event) {
            event.preventDefault();



            
            const formData = new FormData(document.getElementById('registroForm'));
            formData.append('action', 'crear');

            try {
                const response = await fetch(CATEGORIA_CONTROLLER_URL, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    mostrarAlerta('success', '¡LA CATEGORÍA SE HA CREADO CORRECTAMENTE!');
                    toggleModal();
                    setTimeout(() => {
                        refrescarCategoriasManteniendoScroll();
                    }, 1500);
                } else {
                    mostrarAlerta('error', data.message || 'Error al crear la categoría');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarAlerta('error', 'Error al procesar la solicitud');
            }

            return false;
        }

        // Cerrar el modal cuando se hace clic fuera de él
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            if (event.target == viewModal) {
                viewModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        }

        function confirmarReinicioCategorias() {
            Swal.fire({
                title: '¿REINICIAR CATEGORÍAS?',
                text: 'Esta acción limpiará los registros de categorías y dejará el listado vacío. Puede deshacer este cambio con el botón correspondiente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, reiniciar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'reiniciar');

                fetch(CATEGORIA_CONTROLLER_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo reiniciar las categorías');
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Categorías reiniciadas',
                        text: data.message || 'Se han eliminado todas las categorías.'
                    }).then(() => location.reload());
                })
                .catch(error => {
                    console.error('Error reiniciando categorías:', error);
                    mostrarAlerta('error', error.message || 'Error al reiniciar las categorías');
                });
            });
        }

        function confirmarDeshacerReinicioCategorias() {
            Swal.fire({
                title: '¿DESHACER REINICIO?',
                text: 'Se restaurará el último estado de las categorías sin afectar otros módulos.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#64748b'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('action', 'deshacer');

                fetch(CATEGORIA_CONTROLLER_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.message || 'No se pudo restaurar las categorías');
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Restaurado',
                        text: data.message || 'Las categorías han sido restauradas.'
                    }).then(() => location.reload());
                })
                .catch(error => {
                    console.error('Error deshaciendo reinicio de categorías:', error);
                    mostrarAlerta('error', error.message || 'Error al restaurar las categorías');
                });
            });
        }

        function eliminarCategoria(id) {
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
                    popup: 'swal-wide swal-delete'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'eliminar');
                    formData.append('id', id);

                    fetch(CATEGORIA_CONTROLLER_URL, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarAlerta('success', '¡LA CATEGORÍA HA SIDO ELIMINADA!');
                            setTimeout(() => {
                                refrescarCategoriasManteniendoScroll();
                            }, 1500);
                        } else {
                            mostrarAlerta('error', data.message || 'Error al eliminar');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        mostrarAlerta('error', 'Error al procesar la solicitud');
                    });
                }
            });
        }

        function verCategoria(id) {
            fetch(`${CATEGORIA_CONTROLLER_URL}?action=getOne&id=${id}`, {
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

                const categoria = response.data;
                const nombreSeguro = safeUpper(categoria.nombre || '');
                const descripcionSegura = safeUpper(categoria.descripcion || 'Sin especificar');

                const imagenSrc = resolverImagenCategoria(categoria.imagen);
                const imagenHtml = `<img src="${imagenSrc}" alt="${nombreSeguro}" style="display:block; margin:0 auto; max-width: 100%; max-height: 180px; width: auto; height: auto; object-fit: contain; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" onerror="this.onerror=null;this.src='${DEFAULT_CATEGORY_ICON}';">`;

                const estadoText = Number(categoria.estado) === 1
                    ? '<span class="estado-activo" style="padding: 6px 12px;"><i class="fas fa-check-circle"></i> ACTIVO</span>'
                    : '<span class="estado-inactivo" style="padding: 6px 12px;"><i class="fas fa-times-circle"></i> INACTIVO</span>';

                const html = `
                    <div style="margin-bottom: 15px; text-align: center; padding: 12px; background: linear-gradient(135deg, rgba(47, 74, 90, 0.05) 0%, rgba(53, 145, 202, 0.05) 100%); border-radius: 8px;">
                        <label style="display: block; margin-bottom: 10px; color: #2f4a5a; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-image"></i> IMAGEN DE LA CATEGORÍA</label>
                        ${imagenHtml}
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 15px; padding: 0;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #2f4a5a; border-radius: 4px;">
                            <label style="font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-hashtag"></i> ID</label>
                            <p style="color: #2f4a5a; margin: 0; font-size: 14px; font-weight: 600;">${categoria.id}</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #3591CA; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-tag"></i> NOMBRE</label>
                            <p style="color: #2f4a5a; margin: 0; font-size: 13px;">${nombreSeguro}</p>
                        </div>
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #3591CA; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-toggle-on"></i> ESTADO</label>
                            <p style="margin: 0;">${estadoText}</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #0B6623; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-calendar-alt"></i> CREACIÓN</label>
                            <p style="color: #0B6623; margin: 0; font-size: 13px; font-weight: 700;">${categoria.fecha_creacion || 'Sin especificar'}</p>
                        </div>
                        <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #0B6623; border-radius: 4px;">
                            <label style="font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-sync-alt"></i> ACTUALIZACIÓN</label>
                            <p style="color: #0B6623; margin: 0; font-size: 13px; font-weight: 700;">${categoria.fecha_actualizacion || categoria.fecha_actulizacion || 'Sin especificar'}</p>
                        </div>
                    </div>

                    <div style="padding: 10px; background: #f8f9fa; border-left: 4px solid #2f4a5a; border-radius: 4px;">
                        <label style="font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; display: block; margin-bottom: 4px;"><i class="fas fa-file-alt"></i> DESCRIPCIÓN</label>
                        <p style="color: #333; margin: 0; font-size: 13px; line-height: 1.5; text-align: justify;">${descripcionSegura}</p>
                    </div>
                `;

                document.getElementById('viewCategoriaContent').innerHTML = html;
                document.getElementById('viewModal').style.display = 'flex';
                document.body.classList.add('modal-open');
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('error', error.message || 'Error al obtener datos');
            });
        }

        function cerrarViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        function cerrarEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.classList.remove('modal-open');
            resetCategoriaModalState();
        }

        function editarCategoria(id) {
            const esSuperAdmin = <?php echo json_encode($esSuperAdmin); ?>;
            
            fetch(`${CATEGORIA_CONTROLLER_URL}?action=getOne&id=${id}`, {
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
                
                const categoria = response.data;
                
                // Establecer valores en el formulario
                document.getElementById('categoriaId').value = categoria.id;
                document.getElementById('idOriginal').value = categoria.id;
                document.getElementById('idDisplay').value = categoria.id;
                document.getElementById('nombreEdit').value = categoria.nombre;
                document.getElementById('descripcionEdit').value = categoria.descripcion;
                
                // Actualizar imagen
                document.getElementById('imagenPreview').src = resolverImagenCategoria(categoria.imagen);
                document.getElementById('imagenPreview').style.display = 'block';
                document.getElementById('sinImagenText').style.display = 'none';
                toggleBotonRecorte('btnRecortarActual', !!categoria.imagen);

                
                // Controlar el campo ID: solo Super Admin puede editar
                const inputId = document.getElementById('idDisplay');
                if (esSuperAdmin) {
                    inputId.removeAttribute('readonly');
                    inputId.style.backgroundColor = '';
                } else {
                    inputId.setAttribute('readonly', 'readonly');
                    inputId.style.backgroundColor = '#f8f9fa';
                    inputId.style.cursor = 'not-allowed';
                }
                
                // Abrir modal
                document.getElementById('editModal').style.display = 'flex';
                document.body.classList.add('modal-open');
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('error', error.message || 'Error al obtener datos');
            });
        }

        function cambiarEstado(id, estado) {
            Swal.fire({
                title: '¿CAMBIAR ESTADO?',
                text: `¿DESEAS ${estado ? 'ACTIVAR' : 'DESACTIVAR'} ESTA CATEGORÍA?`,
                icon: 'question',
                showCancelButton: false,
                showConfirmButton: true,
                confirmButtonColor: '#2f4a5a',
                confirmButtonText: 'SÍ, CAMBIAR',
                allowOutsideClick: false,
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'cambiarEstado');
                    formData.append('id', id);
                    formData.append('estado', estado ? 1 : 0);

                    fetch(CATEGORIA_CONTROLLER_URL, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarAlerta('success', '¡ESTADO ACTUALIZADO!');
                            setTimeout(() => {
                                refrescarCategoriasManteniendoScroll();
                            }, 1500);
                        } else {
                            throw new Error(data.message || 'Error al actualizar el estado');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        mostrarAlerta('error', 'Error al procesar la solicitud');
                        // Revertir el switch si hubo error
                        document.getElementById(`estado_${id}`).checked = !estado;
                    });
                } else {
                    // Si el usuario cancela, revertir el switch
                    document.getElementById(`estado_${id}`).checked = !estado;
                }
            });
        }

        // Manejar submit del formulario de edición
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar CATEGORÍAs al iniciar
            cargarCategorias();
            
            const formularioEdicion = document.getElementById('categoriaForm');
            if (formularioEdicion) {
                formularioEdicion.addEventListener('submit', function(e) {
                    e.preventDefault();

                    Promise.resolve(true).then(() => {


                        const formData = new FormData();
                        formData.append('action', 'editar');
                        formData.append('id_original', document.getElementById('idOriginal').value);
                        formData.append('id', document.getElementById('idDisplay').value.trim());
                        formData.append('nombre', document.getElementById('nombreEdit').value.trim());
                        formData.append('descripcion', document.getElementById('descripcionEdit').value.trim());
                        
                        const imagenFile = document.getElementById('imagenEdit').files[0];
                        if (imagenFile) {
                            formData.append('imagen', imagenFile);
                        }

                        fetch(CATEGORIA_CONTROLLER_URL, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                cerrarEditModal();
                                mostrarAlerta('success', '¡LA CATEGORÍA HA SIDO ACTUALIZADA!');
                                setTimeout(() => {
                                    refrescarCategoriasManteniendoScroll();
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

            if (window.SquareCropper) {
                window.categoriaCropperCrear = window.SquareCropper.attach({
                    inputId: 'imagen',
                    size: 1000,
                    maxBytes: 500 * 1024,
                    feedbackId: 'imagenFeedback',
                    previewIds: ['imagenCreatePreview', 'imagenCreatePreviewSecondary'],
                    placeholderIds: ['imagenCreatePreviewPlaceholder'],
                    wrapIds: ['imagenCreatePreviewWrap'],
                    onReady: function () {
                        toggleBotonRecorte('btnEditarRecorteCrear', true);
                    },
                    onClear: function () {
                        toggleBotonRecorte('btnEditarRecorteCrear', false);
                    }
                });

                window.categoriaCropperEditar = window.SquareCropper.attach({
                    inputId: 'imagenEdit',
                    size: 1000,
                    maxBytes: 500 * 1024,
                    feedbackId: 'imagenEditFeedback',
                    previewIds: ['imagenEditPreview', 'imagenEditPreviewSecondary'],
                    placeholderIds: ['imagenEditPreviewPlaceholder'],
                    wrapIds: ['imagenEditPreviewWrap'],
                    onReady: function () {
                        const sinImagenText = document.getElementById('sinImagenText');
                        if (sinImagenText) sinImagenText.style.display = 'none';
                        toggleBotonRecorte('btnEditarRecorteEditar', true);
                    },
                    onClear: function () {
                        toggleBotonRecorte('btnEditarRecorteEditar', false);
                    }
                });
            }



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





