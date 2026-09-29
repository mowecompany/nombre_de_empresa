<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 ARREGLANDO PRECIO DE COMPRA</h1>";
echo "<hr>";

try {
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>PASO 1: Verificar columna</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    $existe = $stmt->fetch();
    
    if (!$existe) {
        echo "<p>❌ NO EXISTE - Creando...</p>";
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio");
        echo "<p style='color:green;font-size:18px;'><strong>✅ COLUMNA CREADA</strong></p>";
    } else {
        echo "<p style='color:green;'>✅ Ya existe</p>";
    }
    
    echo "<h2>PASO 2: Ver datos de entradas</h2>";
    $stmt = $db->query("SELECT COUNT(DISTINCT producto_id) as total FROM entradas_inventario WHERE estado = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>📦 Productos diferentes en entradas: <strong>{$result['total']}</strong></p>";
    
    echo "<h2>PASO 3: Migrar TODOS los precios</h2>";
    $affected = $db->exec("
        UPDATE productos p 
        SET precio_compra = (
            SELECT AVG(e.precio_compra) 
            FROM entradas_inventario e 
            WHERE e.producto_id = p.id 
            AND e.estado = 1
        )
        WHERE EXISTS (
            SELECT 1 FROM entradas_inventario e2 
            WHERE e2.producto_id = p.id 
            AND e2.estado = 1
        )
    ");
    echo "<p style='color:green;font-size:18px;'><strong>✅ ACTUALIZADOS {$affected} PRODUCTOS</strong></p>";
    
    echo "<h2>PASO 4: Verificar resultado</h2>";
    $stmt = $db->query("SELECT COUNT(*) as con_precio FROM productos WHERE precio_compra > 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>✅ Productos con precio_compra > 0: <strong>{$result['con_precio']}</strong></p>";
    
    echo "<h2>PASO 5: Ver ejemplos</h2>";
    $stmt = $db->query("SELECT id, codigo, nombre, precio, precio_compra, stock FROM productos ORDER BY id DESC LIMIT 20");
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Código</th><th>Nombre</th><th>Precio Venta</th><th style='background:#ffffcc;'>PRECIO COMPRA</th><th>Stock</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $colorCompra = $row['precio_compra'] > 0 ? 'green' : 'red';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['codigo']}</td>";
        echo "<td>{$row['nombre']}</td>";
        echo "<td>\${$row['precio']}</td>";
        echo "<td style='background:#ffffcc;color:{$colorCompra};font-weight:bold;font-size:16px;'>\${$row['precio_compra']}</td>";
        echo "<td>{$row['stock']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h2 style='color:green;'>✅ PROCESO COMPLETADO</h2>";
    echo "<p><strong>AHORA recarga la página de PRODUCTOS y los precios deberían aparecer.</strong></p>";
    echo "<p><strong>Cuando reinicies ENTRADAS, los precios NO se borrarán porque están guardados en productos.precio_compra</strong></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR</h2>";
    echo "<p style='color:red;font-size:16px;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
