<?php
/**
 * Genera una base SQLite de prueba con 20.000 productos y su historial,
 * con la misma estructura que usa la aplicación. No toca la base real.
 *
 * Ejecutar:  php tests/seed_rendimiento.php [ruta_destino] [cantidad_productos]
 */

function seed_rendimiento(string $ruta, int $productos = 20000): PDO
{
    if (file_exists($ruta)) {
        unlink($ruta);
    }
    foreach ([$ruta . '-wal', $ruta . '-shm'] as $extra) {
        if (file_exists($extra)) {
            unlink($extra);
        }
    }

    $db = new PDO('sqlite:' . $ruta);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = OFF');

    $db->exec("CREATE TABLE empresas (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, imagen TEXT)");
    $db->exec("CREATE TABLE usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellidos TEXT, correo TEXT, rol TEXT, empresa_id INTEGER, estado INTEGER DEFAULT 1)");
    $db->exec("CREATE TABLE categorias (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, imagen TEXT, empresa_id INTEGER, usuario_id INTEGER, requiere_vencimiento INTEGER DEFAULT 0, estado INTEGER DEFAULT 1)");
    $db->exec("CREATE TABLE proveedores (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, empresa_id INTEGER)");
    $db->exec("CREATE TABLE clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, empresa_id INTEGER)");
    $db->exec("CREATE TABLE productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT, codigo_barras TEXT, nombre TEXT, descripcion TEXT,
        precio REAL, precio_original REAL, imagen TEXT, categoria_id INTEGER,
        estado INTEGER DEFAULT 1, stock REAL DEFAULT 0,
        empresa_id INTEGER, usuario_id INTEGER,
        porcentaje_ganancia REAL DEFAULT 0, descuento_porcentaje REAL DEFAULT 0,
        venta_por_kilo INTEGER DEFAULT 0, color TEXT,
        fecha_creacion TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE entradas_inventario (
        id INTEGER PRIMARY KEY AUTOINCREMENT, producto_id INTEGER, proveedor_id INTEGER,
        cantidad REAL, precio_compra REAL, fecha_entrada TEXT, fecha_vencimiento TEXT,
        cantidad_vencida REAL DEFAULT 0, referencia TEXT, notas TEXT,
        empresa_id INTEGER, usuario_id INTEGER
    )");
    $db->exec("CREATE TABLE salidas_inventario (
        id INTEGER PRIMARY KEY AUTOINCREMENT, producto_id INTEGER, cantidad REAL,
        precio_venta_unitario REAL, total_venta REAL, tipo_salida TEXT,
        metodo_pago TEXT, referencia TEXT, notas TEXT, fecha_salida TEXT,
        empresa_id INTEGER, usuario_id INTEGER
    )");
    $db->exec("CREATE TABLE movimientos_inventario (
        id INTEGER PRIMARY KEY AUTOINCREMENT, producto_id INTEGER, tipo_movimiento TEXT,
        cantidad REAL, stock_anterior REAL, stock_nuevo REAL, precio_unitario REAL,
        total_movimiento REAL, referencia_id INTEGER, descripcion TEXT,
        fecha_movimiento TEXT, empresa_id INTEGER, usuario_id INTEGER
    )");
    $db->exec("CREATE TABLE creditos (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER, total REAL, saldo REAL, estado TEXT, fecha_credito TEXT, empresa_id INTEGER, usuario_id INTEGER)");
    $db->exec("CREATE TABLE detalle_creditos (id INTEGER PRIMARY KEY AUTOINCREMENT, credito_id INTEGER, producto_id INTEGER, cantidad REAL, precio REAL)");
    $db->exec("CREATE TABLE abonos_creditos (id INTEGER PRIMARY KEY AUTOINCREMENT, credito_id INTEGER, monto REAL, fecha_abono TEXT)");

    $db->exec("INSERT INTO empresas (id, nombre, imagen) VALUES (1, 'TIENDA DE PRUEBA', '')");
    $db->exec("INSERT INTO usuarios (id, nombre, apellidos, correo, rol, empresa_id) VALUES (1, 'CAJERO', 'PRUEBA', 'cajero@prueba.local', 'Administrador', 1)");
    $db->exec("INSERT INTO proveedores (id, nombre, empresa_id) VALUES (1, 'DISTRIBUIDORA CENTRAL', 1)");
    $db->exec("INSERT INTO clientes (id, nombre, empresa_id) VALUES (1, 'CLIENTE MOSTRADOR', 1)");

    $categorias = ['LACTEOS', 'ASEO', 'GRANOS', 'BEBIDAS', 'CARNICOS Y REFRIGERADOS', 'FRUTAS', 'VERDURAS', 'PANADERIA', 'DULCES', 'LICORES', 'MASCOTAS', 'HOGAR'];
    $stmtCat = $db->prepare("INSERT INTO categorias (nombre, imagen, empresa_id, usuario_id, requiere_vencimiento) VALUES (?, '', 1, 1, ?)");
    foreach ($categorias as $indice => $nombre) {
        $stmtCat->execute([$nombre, in_array($nombre, ['LACTEOS', 'CARNICOS Y REFRIGERADOS', 'FRUTAS', 'VERDURAS', 'PANADERIA'], true) ? 1 : 0]);
    }
    $totalCategorias = count($categorias);

    $palabras = ['ARROZ', 'LECHE', 'JABON', 'ACEITE', 'AZUCAR', 'CAFE', 'PAN', 'QUESO', 'ATUN', 'PASTA', 'GALLETA', 'CHOCOLATE', 'GASEOSA', 'AGUA', 'SAL', 'HARINA', 'PAPEL', 'DETERGENTE', 'CREMA', 'SHAMPOO'];
    $marcas = ['DIANA', 'ALPINA', 'COLGATE', 'PREMIER', 'NACIONAL', 'DORIA', 'ZENU', 'FAMILIA', 'QUALA', 'NESTLE'];

    $db->beginTransaction();
    $stmtProd = $db->prepare("INSERT INTO productos (codigo, codigo_barras, nombre, descripcion, precio, precio_original, imagen, categoria_id, estado, stock, empresa_id, usuario_id, porcentaje_ganancia, descuento_porcentaje, venta_por_kilo)
                              VALUES (?, ?, ?, ?, ?, ?, 'favicon.ico', ?, 1, ?, 1, 1, ?, 0, 0)");
    for ($i = 1; $i <= $productos; $i++) {
        $nombre = $palabras[$i % count($palabras)] . ' ' . $marcas[$i % count($marcas)] . ' ' . $i;
        $precio = 1000 + (($i * 37) % 45000);
        $stmtProd->execute([
            'PR' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
            '77' . str_pad((string)$i, 11, '0', STR_PAD_LEFT),
            $nombre,
            'PRODUCTO DE PRUEBA ' . $i,
            $precio,
            $precio,
            ($i % $totalCategorias) + 1,
            ($i % 120) + 1,
            round((($i * 13) % 60) / 2, 1),
        ]);
    }
    $db->commit();

    // Historial: entradas, salidas y movimientos sobre una parte de los productos.
    $db->beginTransaction();
    $stmtEnt = $db->prepare("INSERT INTO entradas_inventario (producto_id, proveedor_id, cantidad, precio_compra, fecha_entrada, fecha_vencimiento, cantidad_vencida, referencia, empresa_id, usuario_id)
                             VALUES (?, 1, ?, ?, ?, ?, 0, ?, 1, 1)");
    $stmtSal = $db->prepare("INSERT INTO salidas_inventario (producto_id, cantidad, precio_venta_unitario, total_venta, tipo_salida, metodo_pago, referencia, fecha_salida, empresa_id, usuario_id)
                             VALUES (?, ?, ?, ?, 'venta', 'efectivo', ?, ?, 1, 1)");
    $stmtMov = $db->prepare("INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, precio_unitario, total_movimiento, referencia_id, descripcion, fecha_movimiento, empresa_id, usuario_id)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");

    $salidaId = 0;
    for ($i = 1; $i <= $productos; $i++) {
        $fecha = date('Y-m-d H:i:s', strtotime('-' . ($i % 400) . ' days'));
        $precioCompra = 800 + (($i * 29) % 30000);
        $vence = ($i % 5 === 0) ? date('Y-m-d', strtotime('+' . (($i % 90) - 10) . ' days')) : null;
        $stmtEnt->execute([$i, ($i % 50) + 5, $precioCompra, $fecha, $vence, 'ENT-' . $i]);

        if ($i % 2 === 0) {
            $stmtEnt->execute([$i, ($i % 30) + 3, $precioCompra + 200, date('Y-m-d H:i:s', strtotime('-' . (($i % 200)) . ' days')), $vence, 'ENT-B' . $i]);
        }

        if ($i % 2 === 0) {
            $cantidad = ($i % 7) + 1;
            $precio = 1500 + (($i * 41) % 40000);
            $stmtSal->execute([$i, $cantidad, $precio, $cantidad * $precio, 'SAL-' . $i, $fecha]);
            $salidaId = (int)$db->lastInsertId();
            $stmtMov->execute([$i, 'salida', $cantidad, 50, 50 - $cantidad, $precio, $cantidad * $precio, $salidaId, 'Salida por venta', $fecha]);
            $stmtMov->execute([$i, 'entrada', $cantidad, 40, 50, $precioCompra, $cantidad * $precioCompra, 0, 'Entrada de inventario', $fecha]);
        }
    }
    $db->commit();

    return $db;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $ruta = $argv[1] ?? (sys_get_temp_dir() . '/estrella_rendimiento.db');
    $cantidad = isset($argv[2]) ? max(100, (int)$argv[2]) : 20000;
    $inicio = microtime(true);
    seed_rendimiento($ruta, $cantidad);
    printf("Base de prueba creada en %s con %d productos (%.1f s, %.1f MB)\n", $ruta, $cantidad, microtime(true) - $inicio, filesize($ruta) / 1048576);
}
