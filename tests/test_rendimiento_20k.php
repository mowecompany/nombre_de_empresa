<?php
/**
 * Prueba de velocidad con 20.000 productos: mide las pantallas antes
 * (sin índices y sin paginación) y después (con índices y paginación).
 *
 * Ejecutar:  php tests/test_rendimiento_20k.php [cantidad_productos]
 */

require_once __DIR__ . '/seed_rendimiento.php';
require_once __DIR__ . '/../Config/Indices.php';
require_once __DIR__ . '/../Models/Producto.php';
require_once __DIR__ . '/../Models/Inventario.php';
require_once __DIR__ . '/../Models/Vencimiento.php';

$cantidad = isset($argv[1]) ? max(100, (int)$argv[1]) : 20000;
$ruta = sys_get_temp_dir() . '/estrella_rendimiento_test.db';

echo "Generando base de prueba con {$cantidad} productos...\n";
seed_rendimiento($ruta, $cantidad);

function abrir(string $ruta, bool $conAjustes): PDO
{
    $db = new PDO('sqlite:' . $ruta);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if ($conAjustes) {
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA synchronous = NORMAL');
        $db->exec('PRAGMA cache_size = -65536');
        $db->exec('PRAGMA temp_store = MEMORY');
        $db->exec('PRAGMA busy_timeout = 8000');
    }
    return $db;
}

function medir(string $titulo, callable $fn): float
{
    $inicio = microtime(true);
    $filas = $fn();
    $ms = (microtime(true) - $inicio) * 1000;
    $n = is_array($filas) ? (isset($filas['data']) && is_array($filas['data']) ? count($filas['data']) : count($filas)) : 1;
    printf("  %-46s %9.1f ms  (%d filas)\n", $titulo, $ms, $n);
    return $ms;
}

$_SESSION = ['id' => 1, 'usuario_id' => 1, 'empresa_id' => 1, 'rol' => 'Administrador', 'nombre' => 'CAJERO'];

// ---------------- ANTES: sin índices, cargando todo ----------------
echo "\nANTES (sin índices, cargando todo de una vez)\n";
$db = abrir($ruta, false);
$producto = new Producto($db);
$inventario = new Inventario($db);
$vencimiento = new Vencimiento($db);
$vencimiento->asegurarEsquema();

$antes = [];
$antes['Productos: listado completo'] = medir('Productos: listado completo (getAll)', fn() => $producto->getAll());
$antes['Movimientos: listado completo'] = medir('Movimientos: listado completo', fn() => $inventario->obtenerMovimientos());
$antes['Entradas: listado completo'] = medir('Entradas: listado completo', fn() => $inventario->obtenerEntradas());
$antes['Salidas: listado completo'] = medir('Salidas: listado completo', fn() => $inventario->obtenerSalidas());
$antes['Vencimientos: todos los lotes'] = medir('Vencimientos: todos los lotes', fn() => $vencimiento->lotes());
$codigoBuscado = '77' . str_pad((string)intdiv($cantidad, 2), 11, '0', STR_PAD_LEFT);
$antes['Código de barras: buscar en memoria'] = medir('Código de barras: buscando en la lista completa', function () use ($producto, $codigoBuscado) {
    foreach ($producto->getAll() as $fila) {
        $codigo = is_array($fila) ? ($fila['codigo_barras'] ?? '') : ($fila->codigo_barras ?? '');
        if ((string)$codigo === $codigoBuscado) {
            return [$fila];
        }
    }
    return [];
});
$db = null;

// ---------------- DESPUÉS: con índices y paginación ----------------
echo "\nDESPUÉS (con índices automáticos, ajustes de SQLite y paginación de 50)\n";
$db = abrir($ruta, true);
$inicioIndices = microtime(true);
Indices::aplicar($db, true);
printf("  Índices creados en %.0f ms (una sola vez)\n", (microtime(true) - $inicioIndices) * 1000);

$producto = new Producto($db);
$inventario = new Inventario($db);
$vencimiento = new Vencimiento($db);

$despues = [];
$despues['Productos: listado completo'] = medir('Productos: primera página (50)', fn() => $producto->getPaginado(['limit' => 50, 'offset' => 0]));
medir('Productos: buscar "LECHE" en la base', fn() => $producto->getPaginado(['limit' => 50, 'search' => 'LECHE']));
$despues['Movimientos: listado completo'] = medir('Movimientos: primera página (50)', fn() => $inventario->obtenerMovimientosPaginado(['limit' => 50]));
$despues['Entradas: listado completo'] = medir('Entradas: primera página (50)', fn() => $inventario->obtenerEntradasPaginado(['limit' => 50]));
$despues['Salidas: listado completo'] = medir('Salidas: primera página (50)', fn() => $inventario->obtenerSalidasPaginado(['limit' => 50]));
$despues['Vencimientos: todos los lotes'] = medir('Vencimientos: primera página (50)', fn() => $vencimiento->lotesPaginado(['limit' => 50]));
$despues['Código de barras: buscar en memoria'] = medir('Código de barras: consulta directa', fn() => [$producto->buscarPorCodigoBarras($codigoBuscado)]);
medir('Selector de productos: búsqueda ligera', fn() => $producto->buscarLigero('ARROZ', 50));

echo "\nComparación\n";
printf("  %-40s %10s %10s %8s\n", 'Pantalla', 'antes', 'después', 'mejora');
foreach ($antes as $clave => $ms) {
    $ahora = $despues[$clave] ?? 0;
    $factor = $ahora > 0 ? $ms / $ahora : 0;
    printf("  %-40s %8.0f ms %8.0f ms %7.1fx\n", $clave, $ms, $ahora, $factor);
}
echo "\nBase de prueba: {$ruta}\n";
