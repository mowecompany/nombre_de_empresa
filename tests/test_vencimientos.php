<?php
/**
 * Prueba del control de fechas de vencimiento (Models/Vencimiento.php).
 * Usa una base SQLite en memoria, sin tocar la base real.
 *
 * Ejecutar:  php tests/test_vencimientos.php
 */

require_once __DIR__ . '/../Models/Vencimiento.php';

$fallos = 0;
function check(string $titulo, bool $ok, string $detalle = '')
{
    global $fallos;
    if ($ok) {
        echo "OK   - {$titulo}\n";
    } else {
        $fallos++;
        echo "FALLA- {$titulo}" . ($detalle !== '' ? " ({$detalle})" : '') . "\n";
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE categorias (id INTEGER PRIMARY KEY, nombre TEXT)");
$db->exec("CREATE TABLE productos (id INTEGER PRIMARY KEY, nombre TEXT, codigo TEXT, imagen TEXT, stock REAL, categoria_id INTEGER)");
$db->exec("CREATE TABLE entradas_inventario (id INTEGER PRIMARY KEY AUTOINCREMENT, producto_id INTEGER, proveedor_id INTEGER, cantidad REAL, fecha_entrada TEXT)");
$db->exec("CREATE TABLE proveedores (id INTEGER PRIMARY KEY, nombre TEXT)");

$db->exec("INSERT INTO categorias (id, nombre) VALUES (1, 'LACTEOS'), (2, 'ASEO')");
$db->exec("INSERT INTO productos (id, nombre, codigo, imagen, stock, categoria_id) VALUES
    (1, 'LECHE', 'L001', '', 20, 1),
    (2, 'JABON', 'J001', '', 15, 2)");
$db->exec("INSERT INTO proveedores (id, nombre) VALUES (1, 'DISTRIBUIDORA')");

$v = new Vencimiento($db);
$v->asegurarEsquema();

// 1. Esquema
$cols = array_column($db->query("PRAGMA table_info(categorias)")->fetchAll(PDO::FETCH_ASSOC), 'name');
check('categorias.requiere_vencimiento creada', in_array('requiere_vencimiento', $cols, true));
$cols = array_column($db->query("PRAGMA table_info(entradas_inventario)")->fetchAll(PDO::FETCH_ASSOC), 'name');
check('entradas_inventario.fecha_vencimiento creada', in_array('fecha_vencimiento', $cols, true));
check('entradas_inventario.cantidad_vencida creada', in_array('cantidad_vencida', $cols, true));
$tabla = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lotes_vencidos'")->fetchColumn();
check('tabla lotes_vencidos creada', $tabla === 'lotes_vencidos');

// 2. Marcar LACTEOS como perecedera
$db->exec("UPDATE categorias SET requiere_vencimiento = 1 WHERE id = 1");
check('categoria perecedera detectada', $v->categoriaRequiere(1) === true);
check('categoria no perecedera detectada', $v->categoriaRequiere(2) === false);
check('producto perecedero exige fecha', $v->productoRequiere(1)['requiere'] === true);
check('producto de aseo no exige fecha', $v->productoRequiere(2)['requiere'] === false);

// 3. Validación de fecha en la entrada
try {
    $v->validarFechaEntrada(1, '');
    check('entrada perecedera sin fecha es rechazada', false);
} catch (Exception $e) {
    check('entrada perecedera sin fecha es rechazada', true);
}
try {
    $v->validarFechaEntrada(1, date('Y-m-d', strtotime('-2 days')));
    check('fecha ya vencida es rechazada', false);
} catch (Exception $e) {
    check('fecha ya vencida es rechazada', true);
}
$futura = date('Y-m-d', strtotime('+30 days'));
check('fecha futura aceptada', $v->validarFechaEntrada(1, $futura) === $futura);
check('producto no perecedero sin fecha es válido', $v->validarFechaEntrada(2, '') === null);
check('formato dd/mm/aaaa aceptado', $v->validarFechaEntrada(1, date('d/m/Y', strtotime('+10 days'))) === date('Y-m-d', strtotime('+10 days')));

// 4. Lotes y semáforo
$ins = $db->prepare("INSERT INTO entradas_inventario (producto_id, proveedor_id, cantidad, fecha_entrada, fecha_vencimiento) VALUES (?,?,?,?,?)");
$ins->execute([1, 1, 5, date('Y-m-d H:i:s'), date('Y-m-d', strtotime('-3 days'))]);   // vencido
$ins->execute([1, 1, 5, date('Y-m-d H:i:s'), date('Y-m-d', strtotime('+3 days'))]);   // critico
$ins->execute([1, 1, 5, date('Y-m-d H:i:s'), date('Y-m-d', strtotime('+7 days'))]);   // proximo
$ins->execute([1, 1, 5, date('Y-m-d H:i:s'), date('Y-m-d', strtotime('+60 days'))]);  // vigente

$lotes = $v->lotes();
check('se listan los 4 lotes con existencias', count($lotes) === 4, 'obtenidos: ' . count($lotes));
$estados = array_column($lotes, 'estado');
check('semáforo correcto', $estados === ['vencido', 'critico', 'proximo', 'vigente'], implode(',', $estados));
check('orden por fecha más próxima primero', $lotes[0]['fecha_vencimiento'] <= $lotes[1]['fecha_vencimiento']);

$resumen = $v->resumen();
check('resumen: 1 vencido', (int)$resumen['vencidos'] === 1);
check('resumen: 2 por vencer', (int)$resumen['por_vencer'] === 2, json_encode($resumen));

// 5. Archivar el lote vencido (inventario simulado)
class InventarioFalso
{
    public $db;
    public $salidas = [];
    public function __construct($db) { $this->db = $db; }
    public function registrarSalida(array $datos): array
    {
        $this->salidas[] = $datos;
        $stmt = $this->db->prepare('UPDATE productos SET stock = stock - :c WHERE id = :id');
        $stmt->execute([':c' => $datos['cantidad'], ':id' => $datos['producto_id']]);
        return ['success' => true];
    }
}

$inv = new InventarioFalso($db);
$vencido = $lotes[0]['entrada_id'];
$res = $v->archivar($vencido, $inv, ['usuario_id' => 1, 'empresa_id' => 0]);
check('lote vencido archivado', !empty($res['success']), $res['message'] ?? '');
check('stock descontado (20 -> 15)', (float)$db->query('SELECT stock FROM productos WHERE id = 1')->fetchColumn() === 15.0);
check('registro guardado en productos vencidos', count($v->archivados()) === 1);
check('el lote archivado ya no aparece en la lista', count($v->lotes()) === 3);

// 6. No se puede archivar un lote que aún no vence
$res = $v->archivar($lotes[3]['entrada_id'], $inv, []);
check('lote vigente no se puede archivar', empty($res['success']));

echo "\n" . ($fallos === 0 ? "TODAS LAS PRUEBAS PASARON\n" : "{$fallos} PRUEBA(S) FALLARON\n");
exit($fallos === 0 ? 0 : 1);
