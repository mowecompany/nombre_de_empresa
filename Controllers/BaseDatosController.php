<?php
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../Helpers/Helpers.php';
}
require_once ROOT_PATH . '/Config/Config.php';
require_once ROOT_PATH . '/Config/database.php';
require_once ROOT_PATH . '/Helpers/Helpers.php';

if (!PermisosHelper::esSuperAdminSesion()) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

$databasePath = defined('SQLITE_PATH') ? SQLITE_PATH : __DIR__ . '/../database/database.db';
$databasePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $databasePath);
$imagesPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'images';
$tarPath = getenv('WINDIR') ? getenv('WINDIR') . DIRECTORY_SEPARATOR . 'System32' . DIRECTORY_SEPARATOR . 'tar.exe' : 'tar.exe';
$quoteWindowsPath = static function (string $path): string {
    return '"' . str_replace('"', '\\"', $path) . '"';
};

$addDirectoryToZip = static function (ZipArchive $zip, string $directory, string $prefix) use (&$addDirectoryToZip): void {
    if (!is_dir($directory)) {
        return;
    }
    $items = scandir($directory);
    foreach ($items ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $item;
        $zipPath = $prefix . '/' . $item;
        if (is_dir($absolutePath)) {
            $zip->addEmptyDir($zipPath);
            $addDirectoryToZip($zip, $absolutePath, $zipPath);
        } elseif (is_file($absolutePath)) {
            $zip->addFile($absolutePath, $zipPath);
        }
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'exportar') {
    if (!file_exists($databasePath)) {
        http_response_code(404);
        exit('No se encontró la base de datos.');
    }

    $exportPath = tempnam(sys_get_temp_dir(), 'mecanica_db_');
    $zipPath = tempnam(sys_get_temp_dir(), 'mecanica_backup_');
    try {
        if ($exportPath !== false && file_exists($exportPath)) {
            unlink($exportPath);
        }
        if ($zipPath !== false && file_exists($zipPath)) {
            unlink($zipPath);
        }
        $db = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec("VACUUM INTO " . $db->quote($exportPath));
        $db = null;

        if (!file_exists($exportPath) || filesize($exportPath) < 16) {
            throw new RuntimeException('SQLite no generó una copia válida.');
        }

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el paquete de respaldo.');
            }
            if (!$zip->addFile($exportPath, 'database.db')) {
                $zip->close();
                throw new RuntimeException('No se pudo agregar la base al respaldo.');
            }
            $addDirectoryToZip($zip, $imagesPath, 'Assets/images');
            if (!$zip->close()) {
                throw new RuntimeException('No se pudo cerrar el paquete de respaldo.');
            }
        } else {
            $stagingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mecanica_backup_' . bin2hex(random_bytes(8));
            if (!mkdir($stagingPath . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'images', 0755, true)) {
                throw new RuntimeException('No se pudo preparar el respaldo temporal.');
            }
            if (!copy($exportPath, $stagingPath . DIRECTORY_SEPARATOR . 'database.db')) {
                throw new RuntimeException('No se pudo copiar la base al respaldo.');
            }
            $copyDirectory = static function (string $source, string $destination) use (&$copyDirectory): void {
                if (!is_dir($source)) return;
                foreach (scandir($source) ?: [] as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $sourceItem = $source . DIRECTORY_SEPARATOR . $item;
                    $destinationItem = $destination . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($sourceItem)) {
                        if (!is_dir($destinationItem)) mkdir($destinationItem, 0755, true);
                        $copyDirectory($sourceItem, $destinationItem);
                    } elseif (is_file($sourceItem)) {
                        copy($sourceItem, $destinationItem);
                    }
                }
            };
            $copyDirectory($imagesPath, $stagingPath . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'images');
            $command = $quoteWindowsPath($tarPath) . ' -a -c -f ' . $quoteWindowsPath($zipPath) . ' -C ' . $quoteWindowsPath($stagingPath) . ' database.db Assets';
            exec($command, $output, $exitCode);
            if ($exitCode !== 0 || !file_exists($zipPath) || filesize($zipPath) < 100) {
                throw new RuntimeException('No se pudo crear el paquete ZIP con tar.exe: ' . implode(' ', $output));
            }
            $removeStaging = static function (string $directory) use (&$removeStaging): void {
                foreach (scandir($directory) ?: [] as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $directory . DIRECTORY_SEPARATOR . $item;
                    is_dir($path) ? $removeStaging($path) : @unlink($path);
                }
                @rmdir($directory);
            };
            $removeStaging($stagingPath);
        }

        $filename = 'base_datos_AUTOSERVICIO MI ESTRELLA
_' . date('Y-m-d_H-i-s') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
    } catch (Throwable $e) {
        error_log('Error exportando respaldo: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo exportar la base de datos: ' . $e->getMessage());
    } finally {
        if (isset($exportPath) && file_exists($exportPath)) {
            @unlink($exportPath);
        }
        if (isset($zipPath) && file_exists($zipPath)) {
            @unlink($zipPath);
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'importar' || ($_GET['action'] ?? '') === 'importar')) {
    header('Content-Type: application/json; charset=UTF-8');
    $uploaded = $_FILES['base_datos'] ?? null;
    if (!$uploaded || $uploaded['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Selecciona un archivo de base de datos válido.']);
        exit;
    }

    $handle = @fopen($uploaded['tmp_name'], 'rb');
    $signature = $handle ? fread($handle, 16) : '';
    if ($handle) fclose($handle);
    $isSqlite = $signature === "SQLite format 3\0";
    $extension = strtolower(pathinfo((string)($uploaded['name'] ?? ''), PATHINFO_EXTENSION));
    $isZip = substr($signature, 0, 4) === "PK\x03\x04" || $extension === 'zip';
    if (!$isSqlite && !$isZip) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Selecciona un respaldo .zip válido o una base SQLite .db.']);
        exit;
    }

    $directory = dirname($databasePath);
    $backupPath = $databasePath . '.before-import-' . date('YmdHis');
    $temporaryPath = $databasePath . '.importing';
    $temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mecanica_import_' . bin2hex(random_bytes(8));
    try {
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException('No se pudo preparar la carpeta de la base de datos.');
        }
        if (file_exists($databasePath) && !copy($databasePath, $backupPath)) {
            throw new RuntimeException('No se pudo crear la copia de seguridad.');
        }
        $sourceDatabase = $uploaded['tmp_name'];
        $sourceImages = '';
        if ($isZip) {
            if (!mkdir($temporaryDirectory, 0755, true) && !is_dir($temporaryDirectory)) {
                throw new RuntimeException('No se pudo preparar el respaldo temporal.');
            }
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($uploaded['tmp_name']) !== true || !$zip->extractTo($temporaryDirectory)) {
                    throw new RuntimeException('No se pudo extraer el respaldo ZIP.');
                }
                $zip->close();
            } else {
                $command = $quoteWindowsPath($tarPath) . ' -xf ' . $quoteWindowsPath($uploaded['tmp_name']) . ' -C ' . $quoteWindowsPath($temporaryDirectory);
                exec($command, $output, $exitCode);
                if ($exitCode !== 0) {
                    throw new RuntimeException('No se pudo extraer el respaldo ZIP con tar.exe: ' . implode(' ', $output));
                }
            }
            $sourceDatabase = $temporaryDirectory . DIRECTORY_SEPARATOR . 'database.db';
            $sourceImages = $temporaryDirectory . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'images';
            if (!file_exists($sourceDatabase)) {
                throw new RuntimeException('El respaldo ZIP no contiene database.db.');
            }
        }
        if (!copy($sourceDatabase, $temporaryPath)) {
            throw new RuntimeException('No se pudo preparar la importación.');
        }
        $check = new PDO('sqlite:' . $temporaryPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $check->query('PRAGMA schema_version')->fetchColumn();
        $tables = $check->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tablasProhibidas = ['ordenes_taller', 'detalles_orden', 'ordenes_taller_backups_reset'];
        foreach ($tables as $row) {
            if (in_array($row['name'], $tablasProhibidas, true)) {
                throw new RuntimeException('El archivo importado pertenece al módulo de órdenes de taller y no es compatible con la versión de AUTOSERVICIO MI ESTRELLA
.');
            }
        }
        $check = null;
        unset($check);
        clearstatcache(true, $databasePath);
        if (!copy($temporaryPath, $databasePath)) {
            throw new RuntimeException('No se pudo finalizar la importación. Verifica que database.db no esté bloqueada por otra instancia.');
        }
        @unlink($temporaryPath);
        if ($sourceImages !== '' && is_dir($sourceImages)) {
            $removeImagesDirectory = static function (string $directory) use (&$removeImagesDirectory): void {
                foreach (scandir($directory) ?: [] as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $directory . DIRECTORY_SEPARATOR . $item;
                    is_dir($path) ? $removeImagesDirectory($path) : @unlink($path);
                }
                @rmdir($directory);
            };
            if (is_dir($imagesPath)) {
                $removeImagesDirectory($imagesPath);
            }
            if (!rename($sourceImages, $imagesPath)) {
                throw new RuntimeException('No se pudo reemplazar la carpeta de imágenes.');
            }
        }
        @unlink($databasePath . '-wal');
        @unlink($databasePath . '-shm');
        echo json_encode(['success' => true, 'message' => 'Base de datos e imágenes importadas correctamente.']);
    } catch (Throwable $e) {
        @unlink($temporaryPath);
        if (file_exists($backupPath)) {
            @copy($backupPath, $databasePath);
        }
        http_response_code(500);
        error_log('Error importando respaldo: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'No se pudo importar la base de datos: ' . $e->getMessage()]);
    } finally {
        if (is_dir($temporaryDirectory)) {
            $removeDirectory = static function (string $directory) use (&$removeDirectory): void {
                foreach (scandir($directory) ?: [] as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $directory . DIRECTORY_SEPARATOR . $item;
                    is_dir($path) ? $removeDirectory($path) : @unlink($path);
                }
                @rmdir($directory);
            };
            $removeDirectory($temporaryDirectory);
        }
    }
    exit;
}

http_response_code(400);
echo 'Acción no válida.';
