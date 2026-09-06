<?php
// Configuración explícita para forzar SQLite
define('DB_CONNECTION', 'sqlite');

// Detectar si estamos en modo Electron empaquetado
$isElectronPackaged = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'Electron') !== false;

if ($isElectronPackaged) {
    // En modo Electron empaquetado, usar ruta relativa
    define('SQLITE_PATH', 'database/database.db');
} else {
    // En desarrollo, usar ruta absoluta
    define('SQLITE_PATH', dirname(__DIR__) . '/database/database.db');
}
?>