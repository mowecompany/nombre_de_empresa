<?php
if (!class_exists('Database', false)) {
    class Database {
        private static array $connections = [];

        public static function connect() {
            try {
                self::loadEnvFile();

                $connectionType = strtoupper(trim(self::env('DB_CONNECTION', defined('DB_CONNECTION') ? DB_CONNECTION : 'mysql')));
                if ($connectionType === 'SQLITE') {
                    $sqlitePath = self::env('SQLITE_PATH', defined('SQLITE_PATH') ? SQLITE_PATH : dirname(__DIR__) . '/database/database.db');
                    $sqlitePath = self::normalizePath($sqlitePath);
                    $connectionKey = 'sqlite:' . $sqlitePath;
                    if (isset(self::$connections[$connectionKey])) {
                        return self::$connections[$connectionKey];
                    }
                    $sqliteDir = dirname($sqlitePath);

                    if (!is_dir($sqliteDir) && !mkdir($sqliteDir, 0755, true) && !is_dir($sqliteDir)) {
                        throw new RuntimeException('No se pudo crear el directorio SQLite: ' . $sqliteDir);
                    }

                    if (!file_exists($sqlitePath)) {
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

                    $conexion = new PDO($dsn, null, null, $options);
                    $conexion->exec('PRAGMA foreign_keys = ON');
                    $conexion->exec('PRAGMA journal_mode = WAL');
                    $conexion->exec('PRAGMA synchronous = NORMAL');
                    $conexion->exec('PRAGMA busy_timeout = 5000');
                    // Caché en memoria por conexión: 64 MB (antes 20 MB).
                    $conexion->exec('PRAGMA cache_size = -65536');
                    $conexion->exec('PRAGMA temp_store = MEMORY');
                    // Mapea hasta 256 MB del archivo SQLite en memoria: las lecturas
                    // pasan a ser prácticamente sin syscalls.
                    $conexion->exec('PRAGMA mmap_size = 268435456');
                    // Autocheckpoint del WAL cada 1000 páginas (evita que crezca).
                    $conexion->exec('PRAGMA wal_autocheckpoint = 1000');
                    $conexion->exec("PRAGMA encoding = 'UTF-8'");
                    // Reconstruye estadísticas del planificador cuando toca. Es
                    // barato y mejora consultas grandes de inventario/productos.
                    try { $conexion->exec('PRAGMA optimize'); } catch (Throwable $e) { /* opcional */ }

                    self::$connections[$connectionKey] = $conexion;
                    self::aplicarIndices($conexion, true, 'sqlite:' . $sqlitePath);
                    return self::$connections[$connectionKey];
                }


                $host = self::env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
                $user = self::env('DB_USERNAME', defined('DB_USERNAME') ? DB_USERNAME : 'root');
                $pass = self::env('DB_PASSWORD', defined('DB_PASSWORD') ? DB_PASSWORD : '');
                $db   = self::env('DB_NAME', defined('DB_NAME') ? DB_NAME : 'db_partner');
                $charset = self::env('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
                $connectionKey = "mysql:{$host}:{$db}:{$user}:{$charset}";
                if (isset(self::$connections[$connectionKey])) {
                    return self::$connections[$connectionKey];
                }

                $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ];

                $conexion = new PDO($dsn, $user, $pass, $options);
                // SQLite no soporta SET NAMES, se configura en el DSN
                if ($connectionType !== 'SQLITE') {
                    $conexion->exec("SET NAMES '{$charset}' COLLATE '{$charset}_unicode_ci'");
                }
                self::$connections[$connectionKey] = $conexion;
                self::aplicarIndices($conexion, false, $connectionKey);
                return self::$connections[$connectionKey];
            } catch (PDOException $e) {
                error_log('Database connect error: ' . $e->getMessage());
                if (PHP_SAPI === 'cli') {
                    throw $e;
                }
                header('Content-Type: text/plain; charset=utf-8', true, 500);
                die('Error de base de datos. Revisa el log.');
            }
        }

        /**
         * Crea una sola vez los índices de rendimiento. En MySQL se deja una
         * marca en disco para no revisar information_schema en cada petición.
         */
        private static function aplicarIndices(PDO $conexion, bool $esSqlite, string $clave): void
        {
            try {
                $indicesPath = __DIR__ . '/Indices.php';
                if (!is_file($indicesPath)) {
                    return;
                }
                require_once $indicesPath;
                if (!class_exists('Indices')) {
                    return;
                }

                $marca = null;
                if (!$esSqlite) {
                    $marca = sys_get_temp_dir() . '/estrella_indices_' . self::VERSION_INDICES . '_' . md5($clave) . '.ok';
                    if (is_file($marca)) {
                        return;
                    }
                }

                Indices::aplicar($conexion, $esSqlite);

                if ($marca !== null) {
                    @file_put_contents($marca, (string)time());
                }
            } catch (Throwable $e) {
                error_log('Database: no se pudieron aplicar los índices: ' . $e->getMessage());
            }
        }

        private const VERSION_INDICES = 4;


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
            static $loaded = false;
            if ($loaded) {
                return;
            }
            $loaded = true;
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