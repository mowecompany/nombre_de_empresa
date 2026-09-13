<?php
require_once __DIR__ . '/../Config/database.php';

function resetDbCreditoPrueba(string $sqlitePath): void {
    if (file_exists($sqlitePath)) {
        unlink($sqlitePath);
    }
    putenv('DB_CONNECTION=sqlite');
    putenv('SQLITE_PATH=' . $sqlitePath);
    $_SERVER['DB_CONNECTION'] = 'sqlite';
    $_SERVER['SQLITE_PATH'] = $sqlitePath;
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['SQLITE_PATH'] = $sqlitePath;
}

function ejecutarCredito(string $sqlitePath, array $post): void {
    $_POST = $post;
    $_GET = [];
    $_SESSION = [
        'usuario_id' => 1,
        'empresa_id' => 1,
        'userData' => ['id' => 1, 'empresa_id' => 1],
        'rol' => 'Administrador'
    ];

    $conexion = Database::connect();
    $conexion->exec('CREATE TABLE IF NOT EXISTS empresas (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, tipo_empresa_id INTEGER, imagen TEXT);');
    $conexion->exec('CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellidos TEXT, documento TEXT, codigo TEXT, rol TEXT, empresa_id INTEGER);');
    $conexion->exec('CREATE TABLE IF NOT EXISTS productos (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, codigo TEXT, stock DECIMAL(12,3) DEFAULT 0, precio DECIMAL(12,2) DEFAULT 0, empresa_id INTEGER);');
    $conexion->exec('CREATE TABLE IF NOT EXISTS creditos (id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER, cliente_id INTEGER NOT NULL, referencia VARCHAR(80) NOT NULL, total DECIMAL(12,2) NOT NULL DEFAULT 0, abono_inicial DECIMAL(12,2) NOT NULL DEFAULT 0, saldo DECIMAL(12,2) NOT NULL DEFAULT 0, estado VARCHAR(20) NOT NULL DEFAULT "pendiente", ultimo_recargo_mes VARCHAR(7), fecha_pago DATETIME, notas TEXT, usuario_id INTEGER, fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);');
    $conexion->exec('CREATE TABLE IF NOT EXISTS detalle_creditos (id INTEGER PRIMARY KEY AUTOINCREMENT, credito_id INTEGER NOT NULL, producto_id INTEGER NOT NULL, cantidad DECIMAL(12,3) NOT NULL, precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0, total DECIMAL(12,2) NOT NULL DEFAULT 0);');
    $conexion->exec('CREATE TABLE IF NOT EXISTS abonos_creditos (id INTEGER PRIMARY KEY AUTOINCREMENT, credito_id INTEGER NOT NULL, monto DECIMAL(12,2) NOT NULL, metodo_pago VARCHAR(30) NOT NULL DEFAULT "efectivo", usuario_id INTEGER, fecha_abono DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);');
    $conexion->exec('DELETE FROM usuarios; DELETE FROM empresas; DELETE FROM productos; DELETE FROM creditos; DELETE FROM detalle_creditos; DELETE FROM abonos_creditos;');
    $conexion->prepare('INSERT INTO empresas (id, nombre, tipo_empresa_id, imagen) VALUES (?, ?, ?, ?)')->execute([1, 'Empresa test', 1, null]);
    $conexion->prepare('INSERT INTO usuarios (id, nombre, apellidos, documento, codigo, rol, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([1, 'Ana', 'García', '123', 'CLI-1', 'cliente', 1]);
    $conexion->prepare('INSERT INTO productos (id, nombre, codigo, stock, precio, empresa_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([10, 'Arroz', 'P-10', 10, 100, 1]);

    ob_start();
    include __DIR__ . '/../Controllers/CreditosController.php';
    $output = ob_get_clean();
    $payload = json_decode($output, true);
    if (!is_array($payload) || empty($payload['success'])) {
        throw new RuntimeException('Error en controller: ' . $output);
    }
}

$sqlitePath = __DIR__ . '/tmp_creditos_reglas.sqlite';
resetDbCreditoPrueba($sqlitePath);

$primeraCarga = [
    'action' => 'registrarCreditoSalida',
    'cliente_id' => 1,
    'items' => json_encode([
        ['producto_id' => 10, 'cantidad' => 2, 'precio_venta' => 100]
    ])
];
$segundaCarga = [
    'action' => 'registrarCreditoSalida',
    'cliente_id' => 1,
    'items' => json_encode([
        ['producto_id' => 10, 'cantidad' => 3, 'precio_venta' => 120]
    ])
];

ejecutarCredito($sqlitePath, $primeraCarga);
ejecutarCredito($sqlitePath, $segundaCarga);

$conexion = Database::connect();
$detalle = $conexion->query('SELECT producto_id, cantidad, precio_unitario FROM detalle_creditos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$credito = $conexion->query('SELECT total, saldo, estado FROM creditos ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

fwrite(STDOUT, "DETALLE=" . json_encode($detalle) . PHP_EOL);
fwrite(STDOUT, "CREDITO=" . json_encode($credito) . PHP_EOL);

if (count($detalle) !== 1 || (float)$detalle[0]['cantidad'] !== 5.0 || (float)$detalle[0]['precio_unitario'] !== 120.0) {
    fwrite(STDERR, "TEST FALLIDO: el producto existente no se fusionó ni se actualizó el precio esperado\n");
    exit(1);
}

fwrite(STDOUT, "TEST OK\n");
exit(0);
