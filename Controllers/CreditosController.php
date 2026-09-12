<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
session_start();

require_once __DIR__ . '/../Helpers/Helpers.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Models/Inventario.php';
require_once __DIR__ . '/../Models/Usuario.php';

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
                total DECIMAL(12,2) NOT NULL DEFAULT 0
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS abonos_creditos (
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
            total DECIMAL(12,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS abonos_creditos (
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
                WHERE c.empresa_id = :empresa_id
                ORDER BY CASE WHEN LOWER(COALESCE(c.estado, '')) IN ('pendiente', 'PENDIENTE') THEN 0 ELSE 1 END, c.fecha_creacion DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);
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
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

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

            $debeReemplazar = false;
            if ($estadoNuevo === 'pendiente' && $estadoActual !== 'pendiente') {
                $debeReemplazar = true;
            } elseif ($estadoNuevo === $estadoActual && strcmp($fechaNueva, $fechaActual) > 0) {
                $debeReemplazar = true;
            }

            if ($debeReemplazar) {
                $creditosPorCliente[$clienteId] = $credito;
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
            WHERE c.id IN ({$marcadores}) AND c.empresa_id = ? ORDER BY c.id");
        $stmt->execute(array_merge($idsSolicitados, [$empresaId]));
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
        $stmt = $db->prepare("SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, p.imagen AS producto_imagen, p.precio AS precio_actual
            FROM detalle_creditos d LEFT JOIN productos p ON p.id = d.producto_id
            WHERE d.credito_id IN ({$marcadores}) ORDER BY d.id");
        $stmt->execute($idsSolicitados);
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
        $stmt = $db->prepare("SELECT * FROM creditos WHERE id IN ({$marcadoresPago}) AND empresa_id = ? AND estado IN ('pendiente', 'PENDIENTE')");
        $stmt->execute(array_merge($idsPago, [$empresaId]));
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
            $stmtDetalles = $db->prepare("SELECT producto_id, cantidad, precio_unitario FROM detalle_creditos WHERE credito_id = :credito_id");
            $stmtDetalles->execute([':credito_id' => $creditoIdActual]);

            $stmtUltimaReferencia = $db->prepare("SELECT referencia FROM salidas_inventario WHERE empresa_id = :empresa_id AND referencia IS NOT NULL AND TRIM(referencia) <> ''");
            $stmtUltimaReferencia->execute([':empresa_id' => $empresaId]);
            $referenciasExistentes = $stmtUltimaReferencia->fetchAll(PDO::FETCH_COLUMN);
            $referenciaCredito = $obtenerSiguienteReferenciaInventario($referenciasExistentes);

            $detallesPago = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
            foreach ($detallesPago as $detalle) {
                $salida = $inventario->registrarSalida([
                    'producto_id' => (int)$detalle['producto_id'],
                    'cantidad' => (float)$detalle['cantidad'],
                    'tipo_salida' => 'venta',
                    'metodo_pago' => $metodoPago,
                    'referencia' => $referenciaCredito,
                    'usuario_id' => $usuarioId ?: null,
                    'notas' => 'Crédito pagado',
                    'precio_venta' => (float)$detalle['precio_unitario'],
                    'omitir_stock' => true
                ]);
                if (empty($salida['success'])) {
                    throw new Exception($salida['message'] ?? 'No se pudo registrar la ganancia del crédito');
                }
            }
            $stmt = $db->prepare("UPDATE creditos SET saldo = 0, estado = 'pagado', fecha_pago = CURRENT_TIMESTAMP, referencia = :referencia
                WHERE id = :id AND empresa_id = :empresa_id AND estado IN ('pendiente', 'PENDIENTE')");
            $stmt->execute([':referencia' => $referenciaCredito, ':id' => $creditoIdActual, ':empresa_id' => $empresaId]);
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
    $usuarios = $usuarioModel->obtenerUsuarios();
    $clienteSeleccionado = null;
    foreach ($usuarios as $usuario) {
        if ((int)$usuario['id'] === $clienteId) {
            $clienteSeleccionado = $usuario;
            break;
        }
    }
    if (!$clienteSeleccionado) {
        throw new Exception('No se pudo identificar el cliente');
    }
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
        $cantidad = max(0, (float)($item['cantidad'] ?? 0));
        $precio = max(0, (float)($item['precio_venta'] ?? 0));
        $clave = (string)$productoId;
        if (!isset($itemsAgrupados[$clave])) {
            $itemsAgrupados[$clave] = [
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'precio_venta' => $precio
            ];
            continue;
        }
        $itemsAgrupados[$clave]['cantidad'] += $cantidad;
        $itemsAgrupados[$clave]['precio_venta'] = max((float)$itemsAgrupados[$clave]['precio_venta'], $precio);
    }
    $items = array_values($itemsAgrupados);

    $total = 0;
    foreach ($items as $item) {
        $total += max(0, (float)($item['cantidad'] ?? 0)) * max(0, (float)($item['precio_venta'] ?? 0));
    }
    if ($total <= 0) {
        throw new Exception('El crédito debe tener un total válido');
    }

    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        $cantidad = (float)($item['cantidad'] ?? 0);
        $stmtProducto = $db->prepare('SELECT stock FROM productos WHERE id = :id LIMIT 1');
        $stmtProducto->execute([':id' => $productoId]);
        $stock = $stmtProducto->fetchColumn();
        if ($stock === false || (float)$stock < $cantidad) {
            throw new Exception('Stock insuficiente para reservar el crédito');
        }
        $stmtReserva = $db->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id');
        $stmtReserva->execute([':cantidad' => $cantidad, ':id' => $productoId]);
    }

    $stmtCreditoActivo = $db->prepare("SELECT * FROM creditos WHERE empresa_id = :empresa_id AND cliente_id = :cliente_id AND estado IN ('pendiente', 'PENDIENTE') ORDER BY id DESC LIMIT 1");
    $stmtCreditoActivo->execute([':empresa_id' => $empresaId, ':cliente_id' => $clienteId]);
    $creditoActivo = $stmtCreditoActivo->fetch(PDO::FETCH_ASSOC);

    if ($creditoActivo) {
        $creditoId = (int)($creditoActivo['id'] ?? 0);
        $referencia = trim((string)($creditoActivo['referencia'] ?? '')) !== '' ? (string)$creditoActivo['referencia'] : '';
        $totalActual = (float)($creditoActivo['total'] ?? 0);
        $saldoActual = (float)($creditoActivo['saldo'] ?? 0);
        $totalCreditoFinal = $totalActual + $total;
        $saldoCreditoFinal = $saldoActual + $total;
        $stmt = $db->prepare("UPDATE creditos SET total = :total, saldo = :saldo, estado = :estado, notas = :notas, fecha_creacion = CURRENT_TIMESTAMP, referencia = :referencia WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute([
            ':total' => $totalCreditoFinal,
            ':saldo' => $saldoCreditoFinal,
            ':estado' => $estado,
            ':notas' => $notas ?: ($creditoActivo['notas'] ?? null),
            ':referencia' => $referencia,
            ':id' => $creditoId,
            ':empresa_id' => $empresaId
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO creditos
            (empresa_id, cliente_id, referencia, total, saldo, estado, notas, usuario_id)
            VALUES (:empresa_id, :cliente_id, :referencia, :total, :saldo, :estado, :notas, :usuario_id)");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':referencia' => '',
            ':total' => $total,
            ':saldo' => $total,
            ':estado' => $estado,
            ':notas' => $notas ?: null,
            ':usuario_id' => $usuarioId ?: null
        ]);
        $creditoId = (int)$db->lastInsertId();
    }

    if ($creditoActivo) {
        $stmtDetallesExistentes = $db->prepare('SELECT id, producto_id, cantidad, precio_unitario, total FROM detalle_creditos WHERE credito_id = :credito_id');
        $stmtDetallesExistentes->execute([':credito_id' => $creditoId]);
        $detallesActuales = [];
        foreach ($stmtDetallesExistentes->fetchAll(PDO::FETCH_ASSOC) as $detalleExistente) {
            $detallesActuales[(int)($detalleExistente['producto_id'] ?? 0)] = $detalleExistente;
        }

        $stmtActualizarDetalle = $db->prepare('UPDATE detalle_creditos SET cantidad = :cantidad, precio_unitario = :precio, total = :total WHERE id = :id AND credito_id = :credito_id');
        $stmtInsertDetalle = $db->prepare('INSERT INTO detalle_creditos (credito_id, producto_id, cantidad, precio_unitario, total) VALUES (:credito_id, :producto_id, :cantidad, :precio, :total)');

        foreach ($items as $item) {
            $productoId = (int)($item['producto_id'] ?? 0);
            $cantidad = max(0, (float)($item['cantidad'] ?? 0));
            $precio = max(0, (float)($item['precio_venta'] ?? 0));
            if ($productoId <= 0) {
                continue;
            }

            if (isset($detallesActuales[$productoId])) {
                $detalleExistente = $detallesActuales[$productoId];
                $cantidadBase = max(0, (float)($detalleExistente['cantidad'] ?? 0));
                $precioBase = max(0, (float)($detalleExistente['precio_unitario'] ?? 0));
                $cantidadFinal = $cantidadBase + $cantidad;
                $precioFinal = max($precioBase, $precio);
                $totalDetalle = $cantidadFinal * $precioFinal;
                $stmtActualizarDetalle->execute([
                    ':cantidad' => $cantidadFinal,
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
                ':precio' => $precio,
                ':total' => $cantidad * $precio
            ]);
        }
    } else {
        $stmtDetalle = $db->prepare('INSERT INTO detalle_creditos (credito_id, producto_id, cantidad, precio_unitario, total) VALUES (:credito_id, :producto_id, :cantidad, :precio, :total)');
        foreach ($items as $item) {
            $cantidad = (float)($item['cantidad'] ?? 0);
            $precio = (float)($item['precio_venta'] ?? 0);
            $stmtDetalle->execute([
                ':credito_id' => $creditoId,
                ':producto_id' => (int)($item['producto_id'] ?? 0),
                ':cantidad' => $cantidad,
                ':precio' => $precio,
                ':total' => $cantidad * $precio
            ]);
        }
    }

    echo json_encode(['success' => true, 'data' => ['id' => $creditoId, 'referencia' => $referencia, 'total' => $creditoActivo ? ($totalActual + $total) : $total, 'saldo' => $creditoActivo ? ($saldoActual + $total) : $total]]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
