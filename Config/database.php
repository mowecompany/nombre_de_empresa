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

// Crear conexión global si no existe
if (!isset($db)) {
    $db = Database::connect();
}

// ========== MIGRACIÓN AUTOMÁTICA DE PRECIO_COMPRA (SE EJECUTA EN TODAS LAS PÁGINAS) ==========
try {
    $lockFile = __DIR__ . '/../.precio_compra_ok';
    
    // Solo migrar si no está bloqueado
    if (!file_exists($lockFile)) {
        error_log("PRECIO_COMPRA: No hay lock - Verificando productos...");
        
        // Verificar cuántos productos tienen precio_compra en 0 Y tienen entradas
        $stmt = $db->query("
            SELECT COUNT(*) as pendientes 
            FROM productos 
            WHERE (precio_compra IS NULL OR precio_compra = 0) 
            AND id IN (
                SELECT DISTINCT producto_id 
                FROM entradas_inventario 
                WHERE estado = 1
            )
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $pendientes = $result['pendientes'];
        
        error_log("PRECIO_COMPRA: Productos con precio 0 que tienen entradas: " . $pendientes);
        
        if ($pendientes > 0) {
            error_log("PRECIO_COMPRA: Migrando precios desde entradas...");
            
            // Migrar SOLO los que tienen precio en 0 Y tienen entradas
            $affected = $db->exec("
                UPDATE productos 
                SET precio_compra = (
                    SELECT AVG(precio_compra) 
                    FROM entradas_inventario 
                    WHERE producto_id = productos.id 
                    AND estado = 1
                )
                WHERE (precio_compra IS NULL OR precio_compra = 0)
                AND EXISTS (
                    SELECT 1 FROM entradas_inventario 
                    WHERE producto_id = productos.id 
                    AND estado = 1
                )
            ");
            
            error_log("PRECIO_COMPRA: ✅ Migrados: " . $affected . " productos");
        } else {
            error_log("PRECIO_COMPRA: ✅ No hay productos pendientes de migrar");
        }
        
        // Crear archivo de bloqueo SIEMPRE (aunque no haya migrado nada)
        // Esto evita que siga intentando migrar cuando no hay entradas
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        error_log("PRECIO_COMPRA: Lock creado");
    } else {
        error_log("PRECIO_COMPRA: Lock existe - No se requiere migración");
    }
    
} catch (Exception $e) {
    error_log("PRECIO_COMPRA ERROR: " . $e->getMessage());
}
// ===============================================================================================


// EJECUTAR AUTOMÁTICAMENTE: Crear columna precio_compra y migrar datos
require_once __DIR__ . '/setup_precio_compra.php';
?>