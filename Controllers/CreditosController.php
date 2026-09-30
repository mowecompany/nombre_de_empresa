<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();

require_once __DIR__ . '/../Helpers/Helpers.php';
require_once __DIR__ . '/../Config/Config.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Models/Inventario.php';
require_once __DIR__ . '/../Models/Usuario.php';

/**
 * Devuelve la fecha/hora actual en la zona horaria configurada (America/Bogota)
 * formateada para insertar en la base de datos.
 */
function fechaAhora(): string {
    return (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
}

try {
    $db = Database::connect();
    $inventario = new Inventario($db);
    $usuarioModel = new Usuario($db);
    $driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
    $esSqlite = $driver === 'sqlite';

    $usuarioId = (int)($_SESSION['usuario_id'] ?? $_SESSION['userData']['id'] ?? 0);
    $empresaId = (int)($_SESSION['empresa_id'] ?? $_SESSION['userData']['empresa_id'] ?? 0);
    if ($empresaId <= 0 && $usuarioId > 0) {
        $stmtEmpresa = $db->prepare('SELECT empresa_id FROM usuarios WHERE id = :id LIMIT 1');
        $stmtEmpresa->execute([':id' => $usuarioId]);
        $empresaId = (int)($stmtEmpresa->fetchColumn() ?: 0);
    }

    $crearTablas = function () use ($db, $esSqlite): void {
        if ($esSqlite) {
            $db->exec("CREATE TABLE IF NOT EXISTS creditos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                empresa_id INTEGER,
                cliente_id INTEGER NOT NULL,
                referencia VARCHAR(80) NOT NULL,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                abono_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
                saldo DECIMAL(12,2) NOT NULL DEFAULT 0,
                estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
                ultimo_recargo_mes VARCHAR(7),
                fecha_pago DATETIME,
                notas TEXT,
                usuario_id INTEGER,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS detalle_creditos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                credito_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                cantidad DECIMAL(12,3) NOT NULL,
                precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                presentacion_id INTEGER NULL,
                cantidad_presentacion DECIMAL(12,3) NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS abonos_creditos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                credito_id INTEGER NOT NULL,
                monto DECIMAL(12,2) NOT NULL,
                metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
                usuario_id INTEGER,
                fecha_abono DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS creditos_backup (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                empresa_id INTEGER,
                cliente_id INTEGER NOT NULL,
                referencia VARCHAR(80) NOT NULL,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                abono_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
                saldo DECIMAL(12,2) NOT NULL DEFAULT 0,
                estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
                ultimo_recargo_mes VARCHAR(7),
                fecha_pago DATETIME,
                notas TEXT,
                usuario_id INTEGER,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS detalle_creditos_backup (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                credito_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                cantidad DECIMAL(12,3) NOT NULL,
                precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                presentacion_id INTEGER NULL,
                cantidad_presentacion DECIMAL(12,3) NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS abonos_creditos_backup (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                credito_id INTEGER NOT NULL,
                monto DECIMAL(12,2) NOT NULL,
                metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
                usuario_id INTEGER,
                fecha_abono DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        $db->exec("CREATE TABLE IF NOT EXISTS creditos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NULL,
            cliente_id INT NOT NULL,
            referencia VARCHAR(80) NOT NULL,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            abono_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
            saldo DECIMAL(12,2) NOT NULL DEFAULT 0,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            ultimo_recargo_mes VARCHAR(7) NULL,
            fecha_pago DATETIME NULL,
            notas TEXT NULL,
            usuario_id INT NULL,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS detalle_creditos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credito_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad DECIMAL(12,3) NOT NULL,
            precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            presentacion_id INT NULL,
            cantidad_presentacion DECIMAL(12,3) NULL
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS abonos_creditos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credito_id INT NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
            usuario_id INT NULL,
            fecha_abono DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS creditos_backup (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NULL,
            cliente_id INT NOT NULL,
            referencia VARCHAR(80) NOT NULL,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            abono_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
            saldo DECIMAL(12,2) NOT NULL DEFAULT 0,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            ultimo_recargo_mes VARCHAR(7) NULL,
            fecha_pago DATETIME NULL,
            notas TEXT NULL,
            usuario_id INT NULL,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS detalle_creditos_backup (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credito_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad DECIMAL(12,3) NOT NULL,
            precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            presentacion_id INT NULL,
            cantidad_presentacion DECIMAL(12,3) NULL
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS abonos_creditos_backup (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credito_id INT NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
            usuario_id INT NULL,
            fecha_abono DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    };

    $obtenerSiguienteReferenciaInventario = static function (array $referencias): string {
        $mayorNumero = 0;
        $prefijo = 'AU-';
        foreach ($referencias as $referencia) {
            $valor = trim((string)$referencia);
            if ($valor === '' || !preg_match('/^(.*?)(\d+)\s*$/u', $valor, $coincidencia)) {
                continue;
            }
            $numero = (int)$coincidencia[2];
            if ($numero >= $mayorNumero) {
                $mayorNumero = $numero;
                $prefijo = trim((string)$coincidencia[1]) ?: 'AU-';
            }
        }
        $separador = str_ends_with($prefijo, '-') ? '' : ' ';
        return $prefijo . $separador . str_pad((string)($mayorNumero + 1), 2, '0', STR_PAD_LEFT);
    };

    $crearTablas();
    $inventario->presentaciones()->asegurarEsquema();

    foreach ([
        'ultimo_recargo_mes' => 'VARCHAR(7)',
        'fecha_pago' => 'DATETIME'
    ] as $columna => $tipo) {
        try {
            if ($esSqlite) {
                $db->exec("ALTER TABLE creditos ADD COLUMN {$columna} {$tipo}");
            } else {
                $db->exec("ALTER TABLE creditos ADD COLUMN {$columna} {$tipo} NULL");
            }
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'already exists') === false) {
                error_log('No se pudo asegurar columna de créditos: ' . $e->getMessage());
            }
        }
    }

    foreach ([
        'presentacion_id' => $esSqlite ? 'INTEGER NULL' : 'INT NULL',
        'cantidad_presentacion' => $esSqlite ? 'DECIMAL(12,3) NULL' : 'DECIMAL(12,3) NULL'
    ] as $columna => $tipo) {
        try {
            $db->exec("ALTER TABLE detalle_creditos ADD COLUMN {$columna} {$tipo}");
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'already exists') === false) {
                error_log('No se pudo asegurar columna de detalle de créditos: ' . $e->getMessage());
            }
        }
    }

    // Asegurar columna referencia en abonos_creditos (necesaria para códigos AB-XX)
    try {
        $db->exec($esSqlite
            ? "ALTER TABLE abonos_creditos ADD COLUMN referencia VARCHAR(80)"
            : "ALTER TABLE abonos_creditos ADD COLUMN referencia VARCHAR(80) NULL");
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'already exists') === false) {
            error_log('No se pudo asegurar columna referencia en abonos_creditos: ' . $e->getMessage());
        }
    }

    $aplicarRecargos = function () use ($db, $empresaId): void {
        $stmt = $db->prepare("SELECT id, saldo, fecha_creacion, ultimo_recargo_mes FROM creditos
            WHERE empresa_id = :empresa_id AND estado = 'pendiente' AND saldo > 0");
        $stmt->execute([':empresa_id' => $empresaId]);
        $actual = new DateTimeImmutable('now');
        $mesActual = $actual->format('Y-m');
        $actualizar = $db->prepare('UPDATE creditos SET saldo = :saldo, ultimo_recargo_mes = :mes WHERE id = :id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $credito) {
            try {
                $fechaCreacion = new DateTimeImmutable((string)$credito['fecha_creacion']);
                if ($actual < $fechaCreacion->modify('+3 months') || (string)($credito['ultimo_recargo_mes'] ?? '') === $mesActual) {
                    continue;
                }
                $nuevoSaldo = round((float)$credito['saldo'] * 1.01, 2);
                $actualizar->execute([':saldo' => $nuevoSaldo, ':mes' => $mesActual, ':id' => (int)$credito['id']]);
            } catch (Throwable $e) {
                error_log('No se pudo aplicar recargo de crédito: ' . $e->getMessage());
            }
        }
    };
    $aplicarRecargos();

    $accion = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($accion === 'reiniciar') {
        $db->beginTransaction();
        try {
            $db->exec('DELETE FROM creditos_backup');
            $db->exec('DELETE FROM detalle_creditos_backup');
            $db->exec('DELETE FROM abonos_creditos_backup');

            $stmt = $db->prepare('INSERT INTO creditos_backup (empresa_id, cliente_id, referencia, total, abono_inicial, saldo, estado, ultimo_recargo_mes, fecha_pago, notas, usuario_id, fecha_creacion) SELECT empresa_id, cliente_id, referencia, total, abono_inicial, saldo, estado, ultimo_recargo_mes, fecha_pago, notas, usuario_id, fecha_creacion FROM creditos');
            $stmt->execute();
            $stmt = $db->prepare('INSERT INTO detalle_creditos_backup (credito_id, producto_id, cantidad, precio_unitario, total, presentacion_id, cantidad_presentacion) SELECT credito_id, producto_id, cantidad, precio_unitario, total, presentacion_id, cantidad_presentacion FROM detalle_creditos');
            $stmt->execute();
            $stmt = $db->prepare('INSERT INTO abonos_creditos_backup (credito_id, monto, metodo_pago, usuario_id, fecha_abono) SELECT credito_id, monto, metodo_pago, usuario_id, fecha_abono FROM abonos_creditos');
            $stmt->execute();

            $db->exec('DELETE FROM abonos_creditos');
            $db->exec('DELETE FROM detalle_creditos');
            $db->exec('DELETE FROM creditos');

            if ($esSqlite) {
                $db->exec('DELETE FROM sqlite_sequence WHERE name = "creditos"');
                $db->exec('DELETE FROM sqlite_sequence WHERE name = "detalle_creditos"');
                $db->exec('DELETE FROM sqlite_sequence WHERE name = "abonos_creditos"');
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Créditos reiniciados correctamente']);
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    if ($accion === 'deshacer') {
        $db->beginTransaction();
        try {
            $totalBackupCreditos = (int)$db->query('SELECT COUNT(*) FROM creditos_backup')->fetchColumn();
            if ($totalBackupCreditos <= 0) {
                throw new Exception('No hay un reinicio previo para deshacer en créditos');
            }

            $db->exec('DELETE FROM abonos_creditos');
            $db->exec('DELETE FROM detalle_creditos');
            $db->exec('DELETE FROM creditos');

            $stmt = $db->prepare('INSERT INTO creditos (empresa_id, cliente_id, referencia, total, abono_inicial, saldo, estado, ultimo_recargo_mes, fecha_pago, notas, usuario_id, fecha_creacion) SELECT empresa_id, cliente_id, referencia, total, abono_inicial, saldo, estado, ultimo_recargo_mes, fecha_pago, notas, usuario_id, fecha_creacion FROM creditos_backup');
            $stmt->execute();
            $stmt = $db->prepare('INSERT INTO detalle_creditos (credito_id, producto_id, cantidad, precio_unitario, total, presentacion_id, cantidad_presentacion) SELECT credito_id, producto_id, cantidad, precio_unitario, total, presentacion_id, cantidad_presentacion FROM detalle_creditos_backup');
            $stmt->execute();
            $stmt = $db->prepare('INSERT INTO abonos_creditos (credito_id, monto, metodo_pago, usuario_id, fecha_abono) SELECT credito_id, monto, metodo_pago, usuario_id, fecha_abono FROM abonos_creditos_backup');
            $stmt->execute();

            $db->exec('DELETE FROM creditos_backup');
            $db->exec('DELETE FROM detalle_creditos_backup');
            $db->exec('DELETE FROM abonos_creditos_backup');

            if ($esSqlite) {
                $db->exec('DELETE FROM sqlite_sequence WHERE name = "creditos"');
                $db->exec('DELETE FROM sqlite_sequence WHERE name = "detalle_creditos"');
                $db->exec('DELETE FROM sqlite_sequence WHERE name = "abonos_creditos"');
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Se restauró el último estado de créditos']);
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    if ($accion === 'editarDetalle') {
        $detalleId = (int)($_POST['detalle_id'] ?? 0);
        $cantidad = (float)($_POST['cantidad'] ?? 0);
        $precioVenta = function_exists('redondearPrecioVenta')
            ? redondearPrecioVenta((float)($_POST['precio_venta'] ?? 0))
            : (float)($_POST['precio_venta'] ?? 0);
        $presentacionId = isset($_POST['presentacion_id']) ? (int)$_POST['presentacion_id'] : null;

        if ($detalleId <= 0) {
            throw new Exception('No se pudo identificar la línea del crédito');
        }
        if ($cantidad <= 0 || $precioVenta < 0) {
            throw new Exception('Cantidad y precio no válidos');
        }

        $stmtDetalle = $db->prepare('SELECT d.*, c.empresa_id, c.estado AS credito_estado
            FROM detalle_creditos d INNER JOIN creditos c ON c.id = d.credito_id
            WHERE d.id = :id AND (:empresa_id IS NULL OR :empresa_id = 0 OR c.empresa_id = :empresa_id) LIMIT 1');
        $stmtDetalle->execute([':id' => $detalleId, ':empresa_id' => $empresaId > 0 ? $empresaId : 0]);
        $detalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC);
        if (!$detalle) {
            throw new Exception('No se encontró la línea del crédito');
        }
        if (strtolower((string)($detalle['credito_estado'] ?? '')) !== 'pendiente') {
            throw new Exception('Solo se pueden editar créditos pendientes');
        }

        $productoId = (int)($detalle['producto_id'] ?? 0);
        $presentacionAnteriorId = (int)($detalle['presentacion_id'] ?? 0);
        $presentacionNuevaId = max(0, $presentacionId ?? 0);
        if ($presentacionNuevaId > 0 && !$inventario->presentaciones()->manejaPresentaciones($productoId)) {
            $presentacionNuevaId = 0;
        }
        $stmtProducto = $db->prepare('SELECT COALESCE(venta_por_kilo, 0) AS venta_por_kilo FROM productos WHERE id = :id LIMIT 1');
        $stmtProducto->execute([':id' => $productoId]);
        $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);
        if (!$producto) {
            throw new Exception('No se encontró el producto del crédito');
        }
        $esPorKilo = (int)($producto['venta_por_kilo'] ?? 0) === 1;
        $cantidad = $esPorKilo ? round($cantidad, 3) : floor($cantidad);
        $presentacionAnterior = $presentacionAnteriorId > 0
            ? $inventario->presentaciones()->obtener($presentacionAnteriorId)
            : null;
        $presentacion = $presentacionNuevaId > 0
            ? $inventario->presentaciones()->obtener($presentacionNuevaId)
            : null;
        if ($presentacionAnteriorId > 0 && !$presentacionAnterior) {
            throw new Exception('La presentación anterior del crédito no existe');
        }
        if ($presentacion && (int)($presentacion['producto_id'] ?? 0) !== $productoId) {
            throw new Exception('La presentación seleccionada no corresponde al producto');
        }
        if ($presentacionNuevaId > 0 && !$presentacion) {
            throw new Exception('La presentación seleccionada no existe');
        }
        if ($presentacionAnterior && (int)($presentacionAnterior['producto_id'] ?? 0) !== $productoId) {
            throw new Exception('La presentación anterior no corresponde al producto');
        }
        $cantidadAnteriorBase = max(0, (float)($detalle['cantidad'] ?? 0));
        $cantidadAnteriorPresentacion = $presentacionAnteriorId > 0
            ? max(0, (float)($detalle['cantidad_presentacion'] ?? 0))
            : 0.0;
        $factor = $presentacion ? max(1, (float)($presentacion['factor_base'] ?? 1)) : 1.0;
        $cantidadBase = $cantidad * $factor;
        $cantidadCobro = $presentacion ? $cantidad : $cantidadBase;
        $totalDetalle = $cantidadCobro * $precioVenta;

        $enTransaccion = false;
        try {
            if ($driver === 'sqlite') {
                $db->exec('BEGIN IMMEDIATE');
            } else {
                $db->beginTransaction();
            }
            $enTransaccion = true;

            $ajustarStock = static function (int $idProducto, int $idPresentacion, float $cantidadAjuste, bool $esEntrada) use ($db, $inventario, $usuarioId, $empresaId): void {
                if ($cantidadAjuste <= 0.000001) {
                    return;
                }
                if ($idPresentacion > 0) {
                    if ($esEntrada) {
                        $inventario->presentaciones()->ingresar($idProducto, $idPresentacion, $cantidadAjuste);
                    } else {
                        $inventario->presentaciones()->descontar($idProducto, $idPresentacion, $cantidadAjuste, [
                            'usuario_id' => $usuarioId,
                            'empresa_id' => $empresaId
                        ]);
                    }
                    return;
                }

                if ($esEntrada) {
                    $stmtStock = $db->prepare('UPDATE productos SET stock = stock + :cantidad WHERE id = :id');
                    $stmtStock->execute([':cantidad' => $cantidadAjuste, ':id' => $idProducto]);
                    return;
                }

                $stmtStock = $db->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id AND stock >= :cantidad');
                $stmtStock->execute([':cantidad' => $cantidadAjuste, ':id' => $idProducto]);
                if ($stmtStock->rowCount() < 1) {
                    throw new Exception('Stock insuficiente para aumentar la cantidad del crédito');
                }
            };

            $cantidadAnteriorOperativa = $presentacionAnteriorId > 0
                ? $cantidadAnteriorPresentacion
                : $cantidadAnteriorBase;
            $cantidadNuevaOperativa = $presentacionNuevaId > 0 ? $cantidad : $cantidadBase;
            if ($presentacionAnteriorId === $presentacionNuevaId) {
                $diferencia = $cantidadNuevaOperativa - $cantidadAnteriorOperativa;
                if ($diferencia > 0.000001) {
                    $ajustarStock($productoId, $presentacionNuevaId, $diferencia, false);
                } elseif ($diferencia < -0.000001) {
                    $ajustarStock($productoId, $presentacionNuevaId, abs($diferencia), true);
                }
            } else {
                $ajustarStock($productoId, $presentacionAnteriorId, $cantidadAnteriorOperativa, true);
                $ajustarStock($productoId, $presentacionNuevaId, $cantidadNuevaOperativa, false);
            }

            $stmtUpdate = $db->prepare('UPDATE detalle_creditos SET cantidad = :cantidad, precio_unitario = :precio, total = :total, presentacion_id = :presentacion_id, cantidad_presentacion = :cantidad_presentacion WHERE id = :id');
            $stmtUpdate->execute([
                ':cantidad' => $cantidadBase,
                ':precio' => $precioVenta,
                ':total' => $totalDetalle,
                ':presentacion_id' => $presentacionNuevaId > 0 ? $presentacionNuevaId : null,
                ':cantidad_presentacion' => $presentacionNuevaId > 0 ? $cantidad : null,
                ':id' => $detalleId
            ]);

            $stmtTotal = $db->prepare('SELECT COALESCE(SUM(total), 0) AS total FROM detalle_creditos WHERE credito_id = :credito_id');
            $stmtTotal->execute([':credito_id' => (int)($detalle['credito_id'] ?? 0)]);
            $totalCredito = (float)$stmtTotal->fetchColumn();
            $db->prepare('UPDATE creditos SET total = :total, saldo = :saldo, estado = :estado WHERE id = :id')->execute([
                ':total' => $totalCredito,
                ':saldo' => $totalCredito,
                ':estado' => 'pendiente',
                ':id' => (int)($detalle['credito_id'] ?? 0)
            ]);

            if ($driver === 'sqlite') {
                $db->exec('COMMIT');
            } else {
                $db->commit();
            }
            $enTransaccion = false;
        } catch (Throwable $e) {
            if ($enTransaccion) {
                try {
                    if ($driver === 'sqlite') {
                        $db->exec('ROLLBACK');
                    } else {
                        $db->rollBack();
                    }
                } catch (Throwable $rollbackError) {
                    error_log('No se pudo revertir la edición del crédito: ' . $rollbackError->getMessage());
                }
            }
            throw $e;
        }

        echo json_encode(['success' => true, 'message' => 'Producto actualizado en el crédito', 'data' => ['total' => $totalCredito, 'saldo' => $totalCredito]]);
        exit;
    }

    if ($accion === 'eliminarDetalle') {
        $detalleId = (int)($_POST['detalle_id'] ?? 0);
        if ($detalleId <= 0) {
            throw new Exception('No se pudo identificar la línea del crédito');
        }

        $stmtDetalle = $db->prepare('SELECT d.*, c.empresa_id, c.estado AS credito_estado
            FROM detalle_creditos d INNER JOIN creditos c ON c.id = d.credito_id
            WHERE d.id = :id AND (:empresa_id IS NULL OR :empresa_id = 0 OR c.empresa_id = :empresa_id) LIMIT 1');
        $stmtDetalle->execute([':id' => $detalleId, ':empresa_id' => $empresaId > 0 ? $empresaId : 0]);
        $detalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC);
        if (!$detalle) {
            throw new Exception('No se encontró la línea del crédito');
        }
        if (strtolower((string)($detalle['credito_estado'] ?? '')) !== 'pendiente') {
            throw new Exception('Solo se pueden eliminar productos de créditos pendientes');
        }

        $productoId = (int)($detalle['producto_id'] ?? 0);
        $presentacionId = (int)($detalle['presentacion_id'] ?? 0);
        $cantidadBase = max(0, (float)($detalle['cantidad'] ?? 0));
        $cantidadPresentacion = max(0, (float)($detalle['cantidad_presentacion'] ?? 0));
        if ($presentacionId > 0) {
            $presentacion = $inventario->presentaciones()->obtener($presentacionId);
            if (!$presentacion || (int)($presentacion['producto_id'] ?? 0) !== $productoId) {
                throw new Exception('La presentación del crédito no existe o no corresponde al producto');
            }
            if ($cantidadPresentacion <= 0) {
                $factor = max(1, (float)($presentacion['factor_base'] ?? 1));
                $cantidadPresentacion = $cantidadBase / $factor;
            }
        }

        $enTransaccion = false;
        try {
            if ($driver === 'sqlite') {
                $db->exec('BEGIN IMMEDIATE');
            } else {
                $db->beginTransaction();
            }
            $enTransaccion = true;

            if ($presentacionId > 0) {
                $inventario->presentaciones()->ingresar($productoId, $presentacionId, $cantidadPresentacion);
            } elseif ($cantidadBase > 0) {
                $stmtStock = $db->prepare('UPDATE productos SET stock = stock + :cantidad WHERE id = :id');
                $stmtStock->execute([':cantidad' => $cantidadBase, ':id' => $productoId]);
            }

            $stmtEliminar = $db->prepare('DELETE FROM detalle_creditos WHERE id = :id');
            $stmtEliminar->execute([':id' => $detalleId]);

            $stmtTotal = $db->prepare('SELECT COALESCE(SUM(total), 0) AS total FROM detalle_creditos WHERE credito_id = :credito_id');
            $stmtTotal->execute([':credito_id' => (int)($detalle['credito_id'] ?? 0)]);
            $totalCredito = (float)$stmtTotal->fetchColumn();
            $db->prepare('UPDATE creditos SET total = :total, saldo = :saldo, estado = :estado WHERE id = :id')->execute([
                ':total' => $totalCredito,
                ':saldo' => $totalCredito,
                ':estado' => 'pendiente',
                ':id' => (int)($detalle['credito_id'] ?? 0)
            ]);

            if ($driver === 'sqlite') {
                $db->exec('COMMIT');
            } else {
                $db->commit();
            }
            $enTransaccion = false;
        } catch (Throwable $e) {
            if ($enTransaccion) {
                try {
                    if ($driver === 'sqlite') {
                        $db->exec('ROLLBACK');
                    } else {
                        $db->rollBack();
                    }
                } catch (Throwable $rollbackError) {
                    error_log('No se pudo revertir la eliminación del crédito: ' . $rollbackError->getMessage());
                }
            }
            throw $e;
        }

        echo json_encode(['success' => true, 'message' => 'Producto eliminado del crédito y cantidad restaurada en inventario', 'data' => ['total' => $totalCredito, 'saldo' => $totalCredito]]);
        exit;
    }

    if ($accion === 'eliminarCredito') {
        $creditoId = (int)($_POST['credito_id'] ?? 0);
        $idsSolicitados = $_POST['credito_ids'] ?? '';
        if (is_string($idsSolicitados)) {
            $idsSolicitados = json_decode($idsSolicitados, true);
        }
        $ids = is_array($idsSolicitados)
            ? array_values(array_filter(array_unique(array_map('intval', $idsSolicitados))))
            : [];
        if ($creditoId > 0) $ids[] = $creditoId;
        $ids = array_values(array_filter(array_unique($ids)));
        if (!$ids) {
            throw new Exception('No se pudo identificar el crédito');
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $sqlCredito = "SELECT id, estado FROM creditos WHERE id IN ({$marcadores})
            AND (:empresa_id = 0 OR empresa_id = :empresa_id)";
        $stmtCredito = $db->prepare($sqlCredito);
        $stmtCredito->execute(array_merge($ids, [':empresa_id' => $empresaId > 0 ? $empresaId : 0]));
        $creditosEliminar = $stmtCredito->fetchAll(PDO::FETCH_ASSOC);
        if (count($creditosEliminar) !== count($ids)) {
            throw new Exception('No se encontraron todos los créditos de la tarjeta');
        }
        if (array_filter($creditosEliminar, static fn(array $credito): bool => strtolower(trim((string)($credito['estado'] ?? ''))) !== 'pagado')) {
            throw new Exception('Solo se pueden eliminar créditos pagados');
        }

        $enTransaccion = false;
        try {
            if ($driver === 'sqlite') {
                $db->exec('BEGIN IMMEDIATE');
            } else {
                $db->beginTransaction();
            }
            $enTransaccion = true;
            
            $db->prepare("DELETE FROM abonos_creditos WHERE credito_id IN ({$marcadores})")->execute($ids);
            $db->prepare("DELETE FROM detalle_creditos WHERE credito_id IN ({$marcadores})")->execute($ids);
            $db->prepare("DELETE FROM creditos WHERE id IN ({$marcadores})")->execute($ids);
            
            if ($driver === 'sqlite') {
                $db->exec('COMMIT');
                Database::clearConnections();
            } else {
                $db->commit();
            }
            $enTransaccion = false;
            
        } catch (Throwable $e) {
            if ($enTransaccion) {
                try { $driver === 'sqlite' ? $db->exec('ROLLBACK') : $db->rollBack(); } catch (Throwable $rollbackError) {}
            }
            throw $e;
        }

        echo json_encode(['success' => true, 'message' => 'Crédito pagado eliminado correctamente']);
        exit;
    }

    if ($accion === 'abonar') {
        $creditoIdAbono = (int)($_POST['credito_id'] ?? 0);
        $montoAbono = round((float)($_POST['monto'] ?? 0), 2);
        $metodoPagoAbono = strtolower(trim((string)($_POST['metodo_pago'] ?? 'efectivo')));
        if (!in_array($metodoPagoAbono, ['efectivo', 'transferencia'], true)) {
            $metodoPagoAbono = 'efectivo';
        }
        if ($creditoIdAbono <= 0) {
            throw new Exception('Crédito no especificado');
        }
        if ($montoAbono <= 0) {
            throw new Exception('El monto del abono debe ser mayor a cero');
        }

        // Obtener crédito pendiente
        $stmtCred = $db->prepare("SELECT * FROM creditos WHERE id = :id AND (:empresa_id = 0 OR empresa_id = :empresa_id) AND estado = 'pendiente' LIMIT 1");
        $stmtCred->execute([':id' => $creditoIdAbono, ':empresa_id' => $empresaId > 0 ? $empresaId : 0]);
        $credAbono = $stmtCred->fetch(PDO::FETCH_ASSOC);
        if (!$credAbono) {
            throw new Exception('Crédito pendiente no encontrado');
        }

        $saldoActual = (float)$credAbono['saldo'];
        if ($montoAbono > $saldoActual + 0.005) {
            throw new Exception("El abono ($montoAbono) supera el saldo pendiente (" . number_format($saldoActual, 2) . ')');
        }

        // Generar código de abono secuencial AB-01, AB-02...
        $stmtRefAbono = $db->prepare("SELECT referencia FROM salidas_inventario WHERE empresa_id = :empresa_id AND referencia IS NOT NULL AND TRIM(referencia) <> ''");
        $stmtRefAbono->execute([':empresa_id' => $empresaId]);
        $todasReferencias = $stmtRefAbono->fetchAll(PDO::FETCH_COLUMN);
        $stmtRefAbonoExistentes = $db->prepare("SELECT referencia FROM abonos_creditos WHERE referencia IS NOT NULL AND TRIM(referencia) <> '' ORDER BY id DESC");
        try { $stmtRefAbonoExistentes->execute(); $refAbonoExist = $stmtRefAbonoExistentes->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { $refAbonoExist = []; }
        $mayorNumeroAbono = 0;
        foreach (array_merge($todasReferencias, $refAbonoExist) as $refVal) {
            $rv = trim((string)$refVal);
            if (preg_match('/^AB[-\s]*(\d+)$/i', $rv, $mAbono)) {
                $nAbono = (int)$mAbono[1];
                if ($nAbono > $mayorNumeroAbono) $mayorNumeroAbono = $nAbono;
            }
        }
        $codigoAbono = 'AB-' . str_pad((string)($mayorNumeroAbono + 1), 2, '0', STR_PAD_LEFT);

        $nuevoSaldo = round($saldoActual - $montoAbono, 2);
        $estadoNuevo = $nuevoSaldo <= 0.005 ? 'pagado' : 'pendiente';

        $db->beginTransaction();
        try {
            $stmtAbono = $db->prepare("INSERT INTO abonos_creditos (credito_id, monto, metodo_pago, usuario_id, fecha_abono, referencia) VALUES (:credito_id, :monto, :metodo_pago, :usuario_id, :fecha_abono, :referencia)");
            $stmtAbono->execute([
                ':credito_id' => $creditoIdAbono,
                ':monto' => $montoAbono,
                ':metodo_pago' => $metodoPagoAbono,
                ':usuario_id' => $usuarioId ?: null,
                ':fecha_abono' => fechaAhora(),
                ':referencia' => $codigoAbono
            ]);

            // Actualizar saldo del crédito
            $stmtUpdCred = $db->prepare("UPDATE creditos SET saldo = :saldo, estado = :estado" . ($estadoNuevo === 'pagado' ? ", fecha_pago = :fecha_pago" : "") . " WHERE id = :id AND (:empresa_id = 0 OR empresa_id = :empresa_id)");
            $params = [':saldo' => $nuevoSaldo, ':estado' => $estadoNuevo, ':id' => $creditoIdAbono, ':empresa_id' => $empresaId > 0 ? $empresaId : 0];
            if ($estadoNuevo === 'pagado') $params[':fecha_pago'] = fechaAhora();
            $stmtUpdCred->execute($params);

            // Si queda saldo 0, registrar la salida en inventario (igual que pago total)
            if ($estadoNuevo === 'pagado') {
                // El abono ya quedó registrado en abonos_creditos (arriba).
                // obtenerAbonosDiaPorMetodo lo suma a ganancia sin filtrar por estado.
                // No se registra salida en inventario para evitar doble conteo.
                // Solo actualizamos la referencia del crédito para el historial.
                $stmtUltRefAb = $db->prepare("SELECT referencia FROM salidas_inventario WHERE empresa_id = :empresa_id AND referencia IS NOT NULL AND TRIM(referencia) <> ''");
                $stmtUltRefAb->execute([':empresa_id' => $empresaId]);
                $refExistentesAb = $stmtUltRefAb->fetchAll(PDO::FETCH_COLUMN);
                $refFinalPago = $obtenerSiguienteReferenciaInventario($refExistentesAb);
                $db->prepare("UPDATE creditos SET referencia = :ref WHERE id = :id")->execute([':ref' => $refFinalPago, ':id' => $creditoIdAbono]);
            }

            // Registrar entrada en salidas_inventario con referencia AB para que aparezca en tabla de salidas
            // Solo registrar la "transacción de abono" como nota, sin mover producto
            // (El abono NO es una salida de inventario, pero sí se muestra en la tabla de historial como tipo AB)
            // Se guarda en abonos_creditos con la referencia; el frontend consultará esos datos.

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Calcular abono total acumulado
        $stmtAbonoTotal = $db->prepare("SELECT COALESCE(SUM(monto), 0) FROM abonos_creditos WHERE credito_id = :id");
        $stmtAbonoTotal->execute([':id' => $creditoIdAbono]);
        $abonoTotal = (float)$stmtAbonoTotal->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => 'Abono registrado correctamente',
            'data' => [
                'referencia' => $codigoAbono,
                'monto' => $montoAbono,
                'nuevo_saldo' => $nuevoSaldo,
                'abono_total' => $abonoTotal,
                'estado' => $estadoNuevo
            ]
        ]);
        exit;
    }

    if ($accion === 'listarAbonos') {
        $sql = "SELECT ab.*, c.empresa_id, c.cliente_id, c.total AS credito_total, c.saldo AS credito_saldo,
                    u.nombre AS usuario_nombre, u.apellidos AS usuario_apellidos, u.rol AS usuario_rol,
                    uc.nombre AS cliente_nombre, uc.apellidos AS cliente_apellidos, uc.documento AS cliente_documento
                FROM abonos_creditos ab
                INNER JOIN creditos c ON c.id = ab.credito_id
                LEFT JOIN usuarios u ON u.id = ab.usuario_id
                LEFT JOIN usuarios uc ON uc.id = c.cliente_id
                WHERE (:empresa_id = 0 OR c.empresa_id = :empresa_id)
                ORDER BY ab.fecha_abono DESC, ab.id DESC";
        $stmtAb = $db->prepare($sql);
        $stmtAb->execute([':empresa_id' => $empresaId > 0 ? $empresaId : 0]);
        $abonos = array_map(static function (array $ab): array {
            return [
                'id'                => (int)$ab['id'],
                'credito_id'        => (int)$ab['credito_id'],
                'referencia'        => $ab['referencia'] ?? '',
                'monto'             => (float)$ab['monto'],
                'metodo_pago'       => $ab['metodo_pago'] ?? 'efectivo',
                'fecha_abono'       => $ab['fecha_abono'] ?? '',
                'usuario_nombre'    => $ab['usuario_nombre'] ?? '',
                'usuario_apellidos' => $ab['usuario_apellidos'] ?? '',
                'usuario_rol'       => $ab['usuario_rol'] ?? '',
                'cliente_nombre'    => $ab['cliente_nombre'] ?? '',
                'cliente_apellidos' => $ab['cliente_apellidos'] ?? '',
                'cliente_documento' => $ab['cliente_documento'] ?? '',
                'credito_total'     => (float)$ab['credito_total'],
                'credito_saldo'     => (float)$ab['credito_saldo'],
            ];
        }, $stmtAb->fetchAll(PDO::FETCH_ASSOC));
        echo json_encode(['success' => true, 'data' => $abonos]);
        exit;
    }

    if ($accion === 'obtenerClientes') {
        $usuarios = $usuarioModel->obtenerUsuarios();
        $clientes = array_values(array_filter($usuarios, static function (array $usuario): bool {
            $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
            return $rol === 'cliente';
        }));
        echo json_encode(['success' => true, 'data' => $clientes]);
        exit;
    }

    if ($accion === 'listar') {
        $sql = "SELECT c.*, u.nombre, u.apellidos, u.codigo
                FROM creditos c LEFT JOIN usuarios u ON u.id = c.cliente_id
            WHERE (:empresa_id IS NULL OR :empresa_id = 0 OR c.empresa_id = :empresa_id)
                ORDER BY CASE WHEN LOWER(COALESCE(c.estado, '')) IN ('pendiente', 'PENDIENTE') THEN 0 ELSE 1 END, c.fecha_creacion DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId > 0 ? $empresaId : 0]);
        $creditosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $clientes = [];
        foreach ($usuarioModel->obtenerUsuarios() as $usuario) {
            $clientes[(int)$usuario['id']] = $usuario;
        }
        $creditos = array_map(static function (array $credito) use ($clientes): array {
            $cliente = $clientes[(int)$credito['cliente_id']] ?? [];
            $credito['documento'] = $cliente['documento'] ?? '';
            $credito['credit_ids'] = [(int)($credito['id'] ?? 0)];
            $credito['id'] = (int)($credito['id'] ?? 0);
            $credito['cliente_id'] = (int)($credito['cliente_id'] ?? 0);
            $credito['saldo'] = (float)($credito['saldo'] ?? 0);
            $credito['estado'] = $credito['saldo'] > 0 ? 'pendiente' : 'pagado';
            return $credito;
        }, $creditosRaw);

        $creditosPorCliente = [];
        foreach ($creditos as $credito) {
            $clienteId = (int)($credito['cliente_id'] ?? 0);
            if ($clienteId <= 0) {
                $creditosPorCliente[] = $credito;
                continue;
            }
            if (!isset($creditosPorCliente[$clienteId])) {
                $creditosPorCliente[$clienteId] = $credito;
                continue;
            }

            $actual = $creditosPorCliente[$clienteId];
            $estadoActual = strtolower(trim((string)($actual['estado'] ?? 'pendiente')));
            $estadoNuevo = strtolower(trim((string)($credito['estado'] ?? 'pendiente')));
            $fechaActual = (string)($actual['fecha_creacion'] ?? '1970-01-01 00:00:00');
            $fechaNueva = (string)($credito['fecha_creacion'] ?? '1970-01-01 00:00:00');

            // Solo acumular IDs de créditos del mismo estado para evitar que los productos
            // de créditos ya pagados aparezcan mezclados con un nuevo crédito pendiente.
            if ($estadoNuevo === $estadoActual) {
                $idsActuales = array_values(array_unique(array_map('intval', $actual['credit_ids'] ?? [])));
                $idsActuales[] = (int)($credito['id'] ?? 0);
                $actual['credit_ids'] = array_values(array_filter(array_unique($idsActuales)));
            }

            $debeReemplazar = false;
            if ($estadoNuevo === 'pendiente' && $estadoActual !== 'pendiente') {
                $debeReemplazar = true;
            } elseif ($estadoNuevo === $estadoActual && strcmp($fechaNueva, $fechaActual) > 0) {
                $debeReemplazar = true;
            }

            if ($debeReemplazar) {
                // Al reemplazar, el nuevo crédito pendiente empieza con sus propios IDs,
                // sin heredar los IDs del crédito pagado anterior.
                if ($estadoNuevo === 'pendiente' && $estadoActual !== 'pendiente') {
                    // Crédito nuevo pendiente reemplaza a uno pagado: IDs limpios
                    $creditosPorCliente[$clienteId] = $credito;
                } else {
                    // Mismo estado, más reciente: hereda los IDs acumulados del mismo estado
                    $credito['credit_ids'] = $actual['credit_ids'];
                    $creditosPorCliente[$clienteId] = $credito;
                }
            } else {
                $creditosPorCliente[$clienteId] = $actual;
            }
        }

        $creditos = array_values($creditosPorCliente);

        usort($creditos, static function (array $a, array $b): int {
            $estadoA = strtolower(trim((string)($a['estado'] ?? ''))) === 'pagado' ? 1 : 0;
            $estadoB = strtolower(trim((string)($b['estado'] ?? ''))) === 'pagado' ? 1 : 0;
            if ($estadoA !== $estadoB) {
                return $estadoA <=> $estadoB;
            }
            $fechaA = (string)($a['fecha_creacion'] ?? '');
            $fechaB = (string)($b['fecha_creacion'] ?? '');
            return strcmp($fechaB, $fechaA);
        });

        echo json_encode(['success' => true, 'data' => $creditos]);
        exit;
    }

    if ($accion === 'detalle') {
        $idsSolicitados = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? $_POST['ids'] ?? $_GET['id'] ?? $_POST['id'] ?? '')))));
        if (empty($idsSolicitados)) {
            throw new Exception('Crédito no especificado');
        }
        $marcadores = implode(',', array_fill(0, count($idsSolicitados), '?'));
        $stmt = $db->prepare("SELECT c.*, u.nombre, u.apellidos, u.codigo
            FROM creditos c LEFT JOIN usuarios u ON u.id = c.cliente_id
            WHERE c.id IN ({$marcadores}) AND (:empresa_id IS NULL OR :empresa_id = 0 OR c.empresa_id = :empresa_id) ORDER BY c.id");
        $stmt->execute(array_merge($idsSolicitados, [$empresaId > 0 ? $empresaId : 0]));
        $creditosDetalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $credito = $creditosDetalle[0] ?? null;
        if (!$credito) {
            throw new Exception('Crédito no encontrado');
        }
        $credito['credit_ids'] = $idsSolicitados;
        $credito['total'] = array_sum(array_column($creditosDetalle, 'total'));
        $credito['saldo'] = array_sum(array_column($creditosDetalle, 'saldo'));
        $credito['estado'] = (float)$credito['saldo'] > 0 ? 'pendiente' : 'pagado';
        $cliente = array_values(array_filter($usuarioModel->obtenerUsuarios(), static function (array $usuario) use ($credito): bool {
            return (int)$usuario['id'] === (int)$credito['cliente_id'];
        }))[0] ?? [];
        $credito['documento'] = $cliente['documento'] ?? '';
        // Determinar qué IDs dentro de los solicitados están pendientes.
        // Esto evita que, si por alguna razón llegan IDs mezclados (pagado + pendiente),
        // se muestren productos de créditos ya pagados en el perfil del crédito activo.
        $idsPendientesDetalle = array_values(array_filter(
            array_map('intval', array_column($creditosDetalle, 'id')),
            static function (int $cid) use ($creditosDetalle): bool {
                foreach ($creditosDetalle as $cd) {
                    if ((int)$cd['id'] === $cid) {
                        return (float)($cd['saldo'] ?? 0) > 0;
                    }
                }
                return false;
            }
        ));
        // Si hay al menos un crédito pendiente entre los solicitados, mostrar solo sus detalles.
        // Si todos están pagados (vista de historial), mostrar todos.
        $idsParaDetalle = !empty($idsPendientesDetalle) ? $idsPendientesDetalle : $idsSolicitados;
        $marcadoresDetalle = implode(',', array_fill(0, count($idsParaDetalle), '?'));
        $stmt = $db->prepare("SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, p.codigo_barras AS producto_codigo_barras, p.imagen AS producto_imagen, p.precio AS precio_actual, COALESCE(p.venta_por_kilo, 0) AS venta_por_kilo,
                pp.nombre AS presentacion_nombre, pp.factor_base AS presentacion_factor
            FROM detalle_creditos d LEFT JOIN productos p ON p.id = d.producto_id
            LEFT JOIN producto_presentaciones pp ON pp.id = d.presentacion_id
            WHERE d.credito_id IN ({$marcadoresDetalle}) ORDER BY d.id");
        $stmt->execute($idsParaDetalle);
        $credito['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $db->prepare("SELECT * FROM abonos_creditos WHERE credito_id IN ({$marcadores}) ORDER BY fecha_abono");
        $stmt->execute($idsSolicitados);
        $credito['abonos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $credito]);
        exit;
    }

    if ($accion === 'pagar') {
        $idsPago = $_POST['ids'] ?? $_GET['ids'] ?? $_POST['id'] ?? $_GET['id'] ?? '';
        if (is_string($idsPago) && $idsPago !== '') {
            $idsPagoDecodificado = json_decode($idsPago, true);
            if (is_array($idsPagoDecodificado)) {
                $idsPago = $idsPagoDecodificado;
            } else {
                $idsPago = array_map('trim', explode(',', $idsPago));
            }
        }
        if (!is_array($idsPago)) {
            $idsPago = [(int)$idsPago];
        }
        $idsPago = array_values(array_unique(array_filter(array_map('intval', $idsPago))));
        if (empty($idsPago)) {
            throw new Exception('Crédito no especificado');
        }
        $metodoPago = strtolower(trim((string)($_POST['metodo_pago'] ?? '')));
        if (!in_array($metodoPago, ['efectivo', 'transferencia'], true)) {
            throw new Exception('Selecciona efectivo o transferencia');
        }
        $marcadoresPago = implode(',', array_fill(0, count($idsPago), '?'));
        $stmt = $db->prepare("SELECT * FROM creditos WHERE id IN ({$marcadoresPago}) AND (:empresa_id IS NULL OR :empresa_id = 0 OR empresa_id = :empresa_id) AND estado IN ('pendiente', 'PENDIENTE')");
        $stmt->execute(array_merge($idsPago, [$empresaId > 0 ? $empresaId : 0]));
        $creditosPago = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($creditosPago)) {
            echo json_encode(['success' => false, 'message' => 'No hay créditos pendientes para pagar']);
            exit;
        }
        $idsPagoPendientes = array_values(array_unique(array_map('intval', array_column($creditosPago, 'id'))));
        if (empty($idsPagoPendientes)) {
            throw new Exception('No hay créditos pendientes para pagar');
        }
        foreach ($creditosPago as $creditoPago) {
            $creditoIdActual = (int)$creditoPago['id'];
            $totalOriginal = max(0.01, (float)($creditoPago['total'] ?? 0));
            $saldoActual   = max(0, (float)($creditoPago['saldo'] ?? 0));

            // Calcular el total abonado para mostrarlo en notas (informativo)
            $stmtAbonados = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM abonos_creditos WHERE credito_id = :id");
            $stmtAbonados->execute([':id' => $creditoIdActual]);
            $totalAbonado = (float)$stmtAbonados->fetchColumn();

            $stmtDetalles = $db->prepare("SELECT producto_id, cantidad, precio_unitario, presentacion_id, cantidad_presentacion FROM detalle_creditos WHERE credito_id = :credito_id");
            $stmtDetalles->execute([':credito_id' => $creditoIdActual]);

            $stmtUltimaReferencia = $db->prepare("SELECT referencia FROM salidas_inventario WHERE empresa_id = :empresa_id AND referencia IS NOT NULL AND TRIM(referencia) <> ''");
            $stmtUltimaReferencia->execute([':empresa_id' => $empresaId]);
            $referenciasExistentes = $stmtUltimaReferencia->fetchAll(PDO::FETCH_COLUMN);
            $referenciaCredito = $obtenerSiguienteReferenciaInventario($referenciasExistentes);

            $detallesPago = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

            // Distribuir el saldo exacto entre los productos del crédito.
            // El primer producto recibe precio = saldo/cantidad para que la suma exacta
            // sea el saldo real. Los demás llevan precio_venta = 0 (ya sumado en el primero).
            $saldoRestante = $saldoActual;
            foreach ($detallesPago as $i => $detalle) {
                $cantidadDet = (float)($detalle['presentacion_id'] ? ($detalle['cantidad_presentacion'] ?: 0) : $detalle['cantidad']);
                $precioAjustado = $i === 0
                    ? round($saldoRestante / max(1, $cantidadDet), 2)
                    : 0.0;

                $salida = $inventario->registrarSalida([
                    'producto_id'     => (int)$detalle['producto_id'],
                    'cantidad'        => $cantidadDet,
                    'presentacion_id' => (int)($detalle['presentacion_id'] ?? 0),
                    'tipo_salida'     => 'venta',
                    'metodo_pago'     => $metodoPago,
                    'referencia'      => $referenciaCredito,
                    'usuario_id'      => $usuarioId ?: null,
                    'notas'           => $totalAbonado > 0
                        ? "Crédito pagado | Deuda total: {$totalOriginal} | Saldo: {$saldoActual} | Abono previo: {$totalAbonado}"
                        : 'Crédito pagado',
                    'es_credito'      => 1,
                    'precio_venta'    => $precioAjustado,
                    'omitir_stock'    => true
                ]);
                if (empty($salida['success'])) {
                    throw new Exception($salida['message'] ?? 'No se pudo registrar la ganancia del crédito');
                }
            }
            $stmt = $db->prepare("UPDATE creditos SET saldo = 0, estado = 'pagado', fecha_pago = :fecha_pago, referencia = :referencia
                WHERE id = :id AND empresa_id = :empresa_id AND estado IN ('pendiente', 'PENDIENTE')");
            $stmt->execute([':fecha_pago' => fechaAhora(), ':referencia' => $referenciaCredito, ':id' => $creditoIdActual, ':empresa_id' => $empresaId]);
        }
        echo json_encode(['success' => true, 'message' => 'Crédito pagado y registrado en ganancias']);
        exit;
    }

    if ($accion !== 'registrarCreditoSalida') {
        throw new Exception('Acción no válida');
    }

    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $items = json_decode((string)($_POST['items'] ?? '[]'), true);
    if ($clienteId <= 0 || !is_array($items) || empty($items)) {
        throw new Exception('Cliente y productos son requeridos');
    }
    $stmt = $db->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $clienteId]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('Cliente no encontrado');
    }

    $normalizarIdentidad = static function (string $valor): string {
        $valor = trim(mb_strtolower($valor, 'UTF-8'));
        $valor = strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        return preg_replace('/[^a-z0-9]/', '', $valor) ?: '';
    };
    $stmtCliente = $db->prepare('SELECT id, nombre, apellidos, documento, codigo, rol, empresa_id FROM usuarios WHERE id = :id LIMIT 1');
    $stmtCliente->execute([':id' => $clienteId]);
    $clienteSeleccionado = $stmtCliente->fetch(PDO::FETCH_ASSOC);
    if (!$clienteSeleccionado) {
        throw new Exception('No se pudo identificar el cliente');
    }

    $usuarios = $usuarioModel->obtenerUsuarios();
    $documentoCliente = $normalizarIdentidad((string)($clienteSeleccionado['documento'] ?? ''));
    $nombreCliente = $normalizarIdentidad((string)($clienteSeleccionado['nombre'] ?? '') . (string)($clienteSeleccionado['apellidos'] ?? ''));
    foreach ($usuarios as $usuario) {
        $mismoDocumento = $documentoCliente !== '' && $documentoCliente === $normalizarIdentidad((string)($usuario['documento'] ?? ''));
        $mismoNombre = $nombreCliente !== '' && $nombreCliente === $normalizarIdentidad((string)($usuario['nombre'] ?? '') . (string)($usuario['apellidos'] ?? ''));
        if ($mismoDocumento || $mismoNombre) {
            $clienteId = (int)$usuario['id'];
            break;
        }
    }

    // Los créditos deben llevar su propia referencia secuencial y no reutilizar la referencia de la venta del inventario.
    // La referencia de la salida del inventario se usa solo para la operación de venta, no para el crédito.
    // Al crear el crédito no se asocia ninguna referencia; la referencia se asigna solo al pagar el crédito.
    $referencia = '';
    $tipoSalida = trim((string)($_POST['tipo_salida'] ?? 'venta'));
    $notas = trim((string)($_POST['notas'] ?? ''));
    $estado = 'pendiente';

    $itemsAgrupados = [];
    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        if ($productoId <= 0) {
            continue;
        }

        $cantidadVenta = max(0, (float)($item['cantidad'] ?? 0));
        $precio = max(0, (float)($item['precio_venta'] ?? 0));
        if (function_exists('redondearPrecioVenta')) {
            $precio = redondearPrecioVenta($precio);
        }
        $presentacionId = (int)($item['presentacion_id'] ?? 0);

        $stmtTipoProducto = $db->prepare('SELECT COALESCE(venta_por_kilo, 0) FROM productos WHERE id = :id LIMIT 1');
        $stmtTipoProducto->execute([':id' => $productoId]);
        $esPorKilo = (int)$stmtTipoProducto->fetchColumn() === 1;

        // Debe respetar el mismo criterio que el inventario para kilos y unidades, sin mezclar
        // cantidades de presentación con cantidades base.
        $cantidadVenta = $esPorKilo ? round($cantidadVenta, 3) : floor($cantidadVenta);
        if ($cantidadVenta <= 0) {
            continue;
        }

        $presentacion = $presentacionId > 0 ? $inventario->presentaciones()->obtener($presentacionId) : null;
        if ($presentacion && (int)$presentacion['producto_id'] !== $productoId) {
            throw new Exception('La presentación seleccionada no corresponde al producto');
        }

        $factor = $presentacion ? max(1, (float)$presentacion['factor_base']) : 1.0;
        $cantidadBase = $cantidadVenta * $factor;
        if ($presentacion && $precio <= 0) {
            $precio = max(0, (float)$presentacion['precio_venta']);
        }
        if ($presentacion && $precio <= 0 && isset($presentacion['precio_compra']) && (float)$presentacion['precio_compra'] > 0) {
            $precio = max(0, (float)$presentacion['precio_compra']);
        }
        if (function_exists('redondearPrecioVenta')) {
            $precio = redondearPrecioVenta($precio);
        }

        $clave = $productoId . ':' . $presentacionId;
        if (!isset($itemsAgrupados[$clave])) {
            $itemsAgrupados[$clave] = [
                'producto_id' => $productoId,
                'cantidad' => $cantidadBase,
                'cantidad_presentacion' => $presentacion ? $cantidadVenta : null,
                'presentacion_id' => $presentacion ? $presentacionId : 0,
                'precio_venta' => $precio
            ];
            continue;
        }

        $itemsAgrupados[$clave]['cantidad'] += $cantidadBase;
        if ($presentacion) {
            $itemsAgrupados[$clave]['cantidad_presentacion'] = (float)($itemsAgrupados[$clave]['cantidad_presentacion'] ?? 0) + $cantidadVenta;
        }
        $itemsAgrupados[$clave]['precio_venta'] = max((float)$itemsAgrupados[$clave]['precio_venta'], $precio);
    }
    $items = array_values($itemsAgrupados);

    $total = 0;
    foreach ($items as $item) {
        $cantidadCobro = $item['presentacion_id']
            ? max(0, (float)($item['cantidad_presentacion'] ?? 0))
            : max(0, (float)($item['cantidad'] ?? 0));
        $total += $cantidadCobro * max(0, (float)($item['precio_venta'] ?? 0));
    }
    if ($total <= 0) {
        throw new Exception('El crédito debe tener un total válido');
    }

    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        $cantidad = max(0, (float)($item['cantidad'] ?? 0));
        $presentacionId = (int)($item['presentacion_id'] ?? 0);
        $cantidadPresentacion = $presentacionId > 0 ? max(0, (float)($item['cantidad_presentacion'] ?? 0)) : 0.0;

        $stmtProducto = $db->prepare('SELECT stock, COALESCE(venta_por_kilo, 0) AS venta_por_kilo FROM productos WHERE id = :id LIMIT 1');
        $stmtProducto->execute([':id' => $productoId]);
        $productoCredito = $stmtProducto->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$productoCredito) {
            throw new Exception('Producto no encontrado para reservar el crédito');
        }

        $esPorKilo = (int)($productoCredito['venta_por_kilo'] ?? 0) === 1;
        $stockDisponible = max(0, (float)($productoCredito['stock'] ?? 0));
        if ($stockDisponible + 0.0001 < $cantidad) {
            throw new Exception('Stock insuficiente para reservar el crédito');
        }

        if ($presentacionId > 0) {
            $descuento = $inventario->presentaciones()->descontar(
                $productoId,
                $presentacionId,
                $cantidadPresentacion > 0 ? $cantidadPresentacion : $cantidad,
                ['usuario_id' => $usuarioId, 'empresa_id' => $empresaId]
            );
            if ($descuento + 0.0001 < $cantidad) {
                throw new Exception('No se pudo reservar la equivalencia de la presentación');
            }
        } else {
            $stmtReserva = $db->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id');
            $stmtReserva->execute([':cantidad' => $cantidad, ':id' => $productoId]);
        }
    }

    $stmtCreditoActivo = $db->prepare("SELECT * FROM creditos WHERE (:empresa_id = 0 OR empresa_id = :empresa_id OR empresa_id = 0) AND cliente_id = :cliente_id AND estado IN ('pendiente', 'PENDIENTE') ORDER BY id DESC LIMIT 1");
    $stmtCreditoActivo->execute([':empresa_id' => $empresaId > 0 ? $empresaId : 0, ':cliente_id' => $clienteId]);
    $creditoActivo = $stmtCreditoActivo->fetch(PDO::FETCH_ASSOC);

    if ($creditoActivo) {
        $creditoId = (int)($creditoActivo['id'] ?? 0);
        $referencia = trim((string)($creditoActivo['referencia'] ?? '')) !== '' ? (string)$creditoActivo['referencia'] : '';
        $stmt = $db->prepare("UPDATE creditos SET estado = :estado, notas = :notas, fecha_creacion = :fecha_creacion, referencia = :referencia WHERE id = :id AND (empresa_id = :empresa_id OR empresa_id = 0 OR :empresa_id = 0)");
        $stmt->execute([
            ':estado' => $estado,
            ':notas' => $notas ?: ($creditoActivo['notas'] ?? null),
            ':referencia' => $referencia,
            ':fecha_creacion' => fechaAhora(),
            ':id' => $creditoId,
            ':empresa_id' => $empresaId
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO creditos
            (empresa_id, cliente_id, referencia, total, saldo, estado, notas, usuario_id, fecha_creacion)
            VALUES (:empresa_id, :cliente_id, :referencia, :total, :saldo, :estado, :notas, :usuario_id, :fecha_creacion)");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':referencia' => '',
            ':total' => $total,
            ':saldo' => $total,
            ':estado' => $estado,
            ':notas' => $notas ?: null,
            ':usuario_id' => $usuarioId ?: null,
            ':fecha_creacion' => fechaAhora()
        ]);
        $creditoId = (int)$db->lastInsertId();
    }

    if ($creditoActivo) {
        $stmtDetallesExistentes = $db->prepare('SELECT id, producto_id, cantidad, precio_unitario, total, presentacion_id, cantidad_presentacion FROM detalle_creditos WHERE credito_id = :credito_id');
        $stmtDetallesExistentes->execute([':credito_id' => $creditoId]);
        $detallesActuales = [];
        foreach ($stmtDetallesExistentes->fetchAll(PDO::FETCH_ASSOC) as $detalleExistente) {
            $claveDetalle = (int)($detalleExistente['producto_id'] ?? 0) . ':' . (int)($detalleExistente['presentacion_id'] ?? 0);
            $detallesActuales[$claveDetalle] = $detalleExistente;
        }

        $stmtActualizarDetalle = $db->prepare('UPDATE detalle_creditos SET cantidad = :cantidad, cantidad_presentacion = :cantidad_presentacion, presentacion_id = :presentacion_id, precio_unitario = :precio, total = :total WHERE id = :id AND credito_id = :credito_id');
        $stmtInsertDetalle = $db->prepare('INSERT INTO detalle_creditos (credito_id, producto_id, cantidad, cantidad_presentacion, presentacion_id, precio_unitario, total) VALUES (:credito_id, :producto_id, :cantidad, :cantidad_presentacion, :presentacion_id, :precio, :total)');

        foreach ($items as $item) {
            $productoId = (int)($item['producto_id'] ?? 0);
            $cantidad = max(0, (float)($item['cantidad'] ?? 0));
            $cantidadPresentacion = isset($item['presentacion_id']) && (int)$item['presentacion_id'] > 0 ? max(0, (float)($item['cantidad_presentacion'] ?? 0)) : null;
            $presentacionId = (int)($item['presentacion_id'] ?? 0);
            $precio = max(0, (float)($item['precio_venta'] ?? 0));
            if (function_exists('redondearPrecioVenta')) {
                $precio = redondearPrecioVenta($precio);
            }
            if ($productoId <= 0) {
                continue;
            }

            $claveDetalle = $productoId . ':' . $presentacionId;
            if (isset($detallesActuales[$claveDetalle])) {
                $detalleExistente = $detallesActuales[$claveDetalle];
                $cantidadBase = max(0, (float)($detalleExistente['cantidad'] ?? 0));
                $cantidadVentaBase = isset($detalleExistente['presentacion_id']) && (int)$detalleExistente['presentacion_id'] > 0
                    ? max(0, (float)($detalleExistente['cantidad_presentacion'] ?? 0))
                    : null;
                $precioBase = max(0, (float)($detalleExistente['precio_unitario'] ?? 0));
                $cantidadFinal = $cantidadBase + $cantidad;
                $cantidadPresentacionFinal = $cantidadPresentacion === null || $cantidadVentaBase === null
                    ? null
                    : $cantidadVentaBase + $cantidadPresentacion;
                $precioFinal = max($precioBase, $precio);
                $totalDetalle = ($cantidadPresentacionFinal === null ? $cantidadFinal : $cantidadPresentacionFinal) * $precioFinal;
                $stmtActualizarDetalle->execute([
                    ':cantidad' => $cantidadFinal,
                    ':cantidad_presentacion' => $cantidadPresentacionFinal,
                    ':presentacion_id' => $presentacionId ?: null,
                    ':precio' => $precioFinal,
                    ':total' => $totalDetalle,
                    ':id' => (int)($detalleExistente['id'] ?? 0),
                    ':credito_id' => $creditoId
                ]);
                continue;
            }

            $stmtInsertDetalle->execute([
                ':credito_id' => $creditoId,
                ':producto_id' => $productoId,
                ':cantidad' => $cantidad,
                ':cantidad_presentacion' => $cantidadPresentacion,
                ':presentacion_id' => $presentacionId ?: null,
                ':precio' => $precio,
                ':total' => ($cantidadPresentacion === null ? $cantidad : $cantidadPresentacion) * $precio
            ]);
        }
    } else {
        $stmtDetalle = $db->prepare('INSERT INTO detalle_creditos (credito_id, producto_id, cantidad, cantidad_presentacion, presentacion_id, precio_unitario, total) VALUES (:credito_id, :producto_id, :cantidad, :cantidad_presentacion, :presentacion_id, :precio, :total)');
        foreach ($items as $item) {
            $cantidad = (float)($item['cantidad'] ?? 0);
            $cantidadPresentacion = $item['presentacion_id'] ? max(0, (float)($item['cantidad_presentacion'] ?? 0)) : null;
            $precio = (float)($item['precio_venta'] ?? 0);
            $stmtDetalle->execute([
                ':credito_id' => $creditoId,
                ':producto_id' => (int)($item['producto_id'] ?? 0),
                ':cantidad' => $cantidad,
                ':cantidad_presentacion' => $cantidadPresentacion,
                ':presentacion_id' => (int)($item['presentacion_id'] ?? 0) ?: null,
                ':precio' => $precio,
                ':total' => ($cantidadPresentacion === null ? $cantidad : $cantidadPresentacion) * $precio
            ]);
        }
    }

    $stmtTotalCredito = $db->prepare('SELECT COALESCE(SUM(total), 0) AS total, COALESCE(SUM(total), 0) AS saldo FROM detalle_creditos WHERE credito_id = :credito_id');
    $stmtTotalCredito->execute([':credito_id' => $creditoId]);
    $saldoCreditoActual = $stmtTotalCredito->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'saldo' => 0];
    $totalCreditoFinal = (float)($saldoCreditoActual['total'] ?? 0);
    $saldoCreditoFinal = (float)($saldoCreditoActual['saldo'] ?? 0);
    $stmtDetalleCredito = $db->prepare('UPDATE creditos SET total = :total, saldo = :saldo, estado = :estado WHERE id = :id AND (empresa_id = :empresa_id OR empresa_id = 0 OR :empresa_id = 0)');
    $stmtDetalleCredito->execute([
        ':total' => $totalCreditoFinal,
        ':saldo' => $saldoCreditoFinal,
        ':estado' => $estado,
        ':id' => $creditoId,
        ':empresa_id' => $empresaId
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => $creditoId, 'referencia' => $referencia, 'total' => $totalCreditoFinal, 'saldo' => $saldoCreditoFinal]]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
