<?php
session_start();
require 'Config/database.php';
require 'Models/Inventario.php';
$db = Database::connect();
$inventario = new Inventario($db);
$res = $inventario->obtenerSalidas([]);
echo json_encode([
    'success' => true,
    'count' => count($res),
    'first' => count($res) ? $res[0] : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
