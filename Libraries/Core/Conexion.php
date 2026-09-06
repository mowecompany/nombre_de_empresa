<?php
    class Conexion{
        private $conect;
        private $lastError = '';

        public function __construct(){
            $configPath = __DIR__ . '/../../Config/Config.php';
            if ((!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_CONNECTION')) && file_exists($configPath)) {
                require_once $configPath;
            }

            $connectionType = defined('DB_CONNECTION') ? strtolower(trim(DB_CONNECTION)) : 'sqlite';
            if ($connectionType === 'sqlite') {
                if (!defined('SQLITE_PATH')) {
                    throw new Exception('SQLITE_PATH no está definido para la conexión SQLite.');
                }

                $this->conect = new PDO('sqlite:' . SQLITE_PATH);
                $this->conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conect->exec('PRAGMA foreign_keys = ON');
                $this->conect->exec('PRAGMA journal_mode = WAL');
                return;
            }

            if (!defined('DB_HOST') || !defined('DB_NAME')) {
                throw new Exception('No se encontraron las constantes de configuración de base de datos.');
            }

            $dbUser = defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : null);
            $dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
            $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;

            if ($dbUser === null) {
                throw new Exception('No se encontró DB_USER ni DB_USERNAME en la configuración.');
            }

            $hostsToTry = [];
            $mainHost = trim((string)DB_HOST);
            $httpHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $isProdHost = in_array($httpHost, ['nombreempresa.ct.ws', 'www.nombreempresa.ct.ws'], true);
            if ($mainHost !== '') {
                $hostsToTry[] = $mainHost;
            }

            // En muchos hostings Linux, "localhost" intenta socket Unix y puede fallar
            // con "No such file or directory". Como respaldo, probamos por TCP.
            if (strcasecmp($mainHost, 'localhost') === 0) {
                $hostsToTry[] = '127.0.0.1';
            } elseif ($mainHost === '127.0.0.1') {
                // Si el hosting no expone MySQL por TCP local, intentar socket local.
                $hostsToTry[] = 'localhost';
            }

            // Fallbacks opcionales desde entorno, separados por coma.
            $fallbackRaw = getenv('DB_HOST_FALLBACKS');
            if ($fallbackRaw !== false && trim($fallbackRaw) !== '') {
                foreach (explode(',', (string)$fallbackRaw) as $fallbackHost) {
                    $fallbackHost = trim($fallbackHost);
                    if ($fallbackHost !== '') {
                        $hostsToTry[] = $fallbackHost;
                    }
                }
            }

            // En producción, priorizar TCP loopback por encima de localhost si ambos existen.
            if ($isProdHost) {
                usort($hostsToTry, function ($a, $b) {
                    $rank = function ($host) {
                        $h = strtolower(trim((string)$host));
                        if ($h === '127.0.0.1') return 1;
                        if ($h === 'localhost') return 2;
                        return 0;
                    };
                    return $rank($a) <=> $rank($b);
                });
            }

            $hostsToTry = array_values(array_unique($hostsToTry));
            $lastExceptionMessage = '';

            foreach ($hostsToTry as $host) {
                $connectionString = "mysql:host=" . $host . ";port=" . $dbPort . ";dbname=" . DB_NAME . ";charset=" . $dbCharset;
                try {
                    $this->conect = new PDO($connectionString, $dbUser, $dbPass, [
                        PDO::ATTR_TIMEOUT => 8,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]);
                    $this->conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->conect->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
                    $this->lastError = '';
                    return;
                } catch (PDOException $e) {
                    $this->conect = null;
                    $lastExceptionMessage = $e->getMessage();
                    $this->lastError = $lastExceptionMessage;
                    error_log('ERROR DB Conexion (' . $host . '): ' . $lastExceptionMessage);
                }
            }

            if ($this->conect === null && $lastExceptionMessage !== '') {
                error_log('ERROR DB Conexion final: ' . $lastExceptionMessage);
            }
        }

        public function conect(){
            return $this->conect;
        }

        public function getLastError(): string
        {
            return $this->lastError;
        }
    }
?> 