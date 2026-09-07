<?php
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/Helpers/Helpers.php';
}

$esAppDesktop = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'Electron') !== false;

if (isset($_GET['ruta'])) {
    header('Location: ' . BASE_URL . '/Views/login.php');
    exit;
}

if ($esAppDesktop) {
    header('Location: ' . BASE_URL . '/Views/login.php');
    exit;
}

// Si ya está autenticado, ir a usuarios
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/Views/usuarios.php');
    exit;
}

// Si no está autenticado, ir al login
header('Location: ' . BASE_URL . '/Views/login.php');
exit;
?>
