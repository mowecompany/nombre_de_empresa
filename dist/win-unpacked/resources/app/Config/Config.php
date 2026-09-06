<?php
function config_env(string $key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    return $default;
}

// Entorno y rutas
if (!defined('APP_ENV')) {
    define('APP_ENV', config_env('APP_ENV', 'local'));
}

// Conexión de base de datos: mysql o sqlite
if (!defined('DB_CONNECTION')) {
    define('DB_CONNECTION', config_env('DB_CONNECTION', 'mysql'));
}

// MySQL por compatibilidad actual, SQLite solo si se selecciona.
if (!defined('DB_HOST')) {
    define('DB_HOST', config_env('DB_HOST', 'localhost'));
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', config_env('DB_USERNAME', 'root'));
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', config_env('DB_PASSWORD', ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', config_env('DB_NAME', 'db_partner'));
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', config_env('DB_CHARSET', 'utf8mb4'));
}

// SQLite: carpeta database/database.db
if (!defined('SQLITE_PATH')) {
    $sqlitePath = config_env('SQLITE_PATH', 'database/database.db');
    if (!preg_match('/^(?:[a-zA-Z]:\\\\|\\\\|\/)/', $sqlitePath)) {
        $sqlitePath = dirname(__DIR__) . '/' . ltrim($sqlitePath, '\\/.');
    }
    $sqlitePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $sqlitePath);
    define('SQLITE_PATH', $sqlitePath);
}

// URL base para resources, útil para Electron / servidor local.
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', config_env('APP_BASE_URL', 'http://127.0.0.1:8000'));
}

// Ruta de log para errores de PHP
if (!defined('LOG_PATH')) {
    $logPath = config_env('LOG_PATH', dirname(__DIR__) . '/storage/logs/php.log');
    if (!preg_match('/^(?:[a-zA-Z]:\\\\|\\\\|\/)/', $logPath)) {
        $logPath = dirname(__DIR__) . '/' . ltrim($logPath, '\\/.');
    }
    $logPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $logPath);
    define('LOG_PATH', $logPath);
}

$logDir = dirname(LOG_PATH);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
if (!file_exists(LOG_PATH)) {
    @touch(LOG_PATH);
}

ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH);

date_default_timezone_set('America/Bogota');

// Otras configuraciones si las hay...
?>
