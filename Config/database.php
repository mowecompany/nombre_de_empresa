<?php
// Forzar SQLite explícitamente
require_once __DIR__ . '/database_config.php';

if (!class_exists('Database', false)) {
    class Database {
        public static function connect() {
            try {
                self::loadEnvFile();

                $connectionType = strtoupper(trim(self::env('DB_CONNECTION', defined('DB_CONNECTION') ? DB_CONNECTION : 'sqlite')));
                if ($connectionType === 'SQLITE') {
                    $sqlitePath = self::env('SQLITE_PATH', defined('SQLITE_PATH') ? SQLITE_PATH : dirname(__DIR__) . '/database/database.db');
                    $sqlitePath = self::normalizePath($sqlitePath);
                    $sqliteDir = dirname($sqlitePath);

                    error_log('SQLite requested path: ' . $sqlitePath);
                    error_log('SQLite directory exists: ' . (is_dir($sqliteDir) ? 'yes' : 'no'));
                    error_log('SQLite directory writable: ' . (is_writable($sqliteDir) ? 'yes' : 'no'));
                    error_log('SQLite file exists: ' . (file_exists($sqlitePath) ? 'yes' : 'no'));
                    error_log('SQLite file writable: ' . (file_exists($sqlitePath) && is_writable($sqlitePath) ? 'yes' : 'no'));

                    if (!is_dir($sqliteDir) && !mkdir($sqliteDir, 0755, true) && !is_dir($sqliteDir)) {
                        throw new RuntimeException('No se pudo crear el directorio SQLite: ' . $sqliteDir);
                    }

                    if (!file_exists($sqlitePath)) {
                        error_log('SQLite database no existe. Se intentará crear: ' . $sqlitePath);
                        try {
                            $handle = fopen($sqlitePath, 'c');
                            if ($handle !== false) {
                                fclose($handle);
                            }
                        } catch (Throwable $exception) {
                            error_log('No se pudo crear SQLite database: ' . $exception->getMessage());
                        }
                    }

                    $dsn = 'sqlite:' . $sqlitePath;
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::ATTR_TIMEOUT            => 5,
                    ];

                    error_log('Conectando SQLite con DSN: ' . $dsn);
                    $conexion = new PDO($dsn, null, null, $options);
                    $conexion->exec('PRAGMA foreign_keys = ON');
                    $conexion->exec('PRAGMA journal_mode = WAL');
                    $conexion->exec("PRAGMA encoding = 'UTF-8'");

                    return $conexion;
                }

                $host = self::env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
                $user = self::env('DB_USERNAME', defined('DB_USERNAME') ? DB_USERNAME : 'root');
                $pass = self::env('DB_PASSWORD', defined('DB_PASSWORD') ? DB_PASSWORD : '');
                $db   = self::env('DB_NAME', defined('DB_NAME') ? DB_NAME : 'db_partner');
                $charset = self::env('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

                $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ];

                $conexion = new PDO($dsn, $user, $pass, $options);
                $conexion->exec("SET NAMES '{$charset}' COLLATE '{$charset}_unicode_ci'");
                return $conexion;
            } catch (PDOException $e) {
                error_log('Database connect error: ' . $e->getMessage());
                if (PHP_SAPI === 'cli') {
                    throw $e;
                }
                header('Content-Type: text/plain; charset=utf-8', true, 500);
                die('Error de base de datos. Revisa el log.');
            }
        }

        private static function env(string $key, $default = null) {
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

        private static function normalizePath(string $path): string {
            $path = trim($path);
            if ($path === '') {
                return $path;
            }
            if (!preg_match('/^(?:[a-zA-Z]:\\\\|\\\\|\/)/', $path)) {
                $path = dirname(__DIR__) . '/' . ltrim($path, '\\/.');
            }
            return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        }

        private static function loadEnvFile(): void {
            $envPath = dirname(__DIR__) . '/Config/.env';
            if (!file_exists($envPath)) {
                return;
            }

            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                [$name, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
                if ($name === '') {
                    continue;
                }
                if (getenv($name) === false) {
                    putenv("{$name}={$value}");
                }
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
?>