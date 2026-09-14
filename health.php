<?php
/**
 * Punto de comprobación ligero para la conexión entre equipos.
 * No abre base de datos ni sesión: solo confirma que el servidor responde.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo json_encode([
    'ok' => true,
    'app' => 'AUTOSERVICIO MI ESTRELLA',
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
