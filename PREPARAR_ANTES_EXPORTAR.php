<?php
/**
 * EJECUTA ESTE SCRIPT ANTES DE EXPORTAR LA BASE DE DATOS
 * Asegura que todos los productos tengan precio_compra guardado
 */

echo "<h1>🔧 PREPARAR BASE DE DATOS PARA EXPORTACIÓN</h1>";
echo "<hr>";

try {
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>PASO 1: Verificar columna precio_compra</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    if (!$stmt->fetch()) {
        echo "<p>Creando columna...</p>";
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio");
        echo "<p style='color:green;'>✅ Columna creada</p>";
    } else {
        echo "<p style='color:green;'>✅ Columna existe</p>";
    }
    
    echo "<h2>PASO 2: Copiar precios desde entradas a productos</h2>";
    $affected = $db->exec("
        UPDATE productos 
        SET precio_compra = (
            SELECT AVG(precio_compra) 
            FROM entradas_inventario 
            WHERE producto_id = productos.id 
            AND estado = 1
        )
        WHERE EXISTS (
            SELECT 1 FROM entradas_inventario 
            WHERE producto_id = productos.id 
            AND estado = 1
        )
    ");
    echo "<p style='color:green;font-size:18px;'><strong>✅ Actualizados: {$affected} productos</strong></p>";
    
    echo "<h2>PASO 3: Verificar resultado</h2>";
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE precio_compra > 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Productos con precio_compra guardado: {$result['total']}</strong></p>";
    
    echo "<h2>PASO 4: Ver ejemplos</h2>";
    $stmt = $db->query("SELECT id, codigo, nombre, precio, precio_compra FROM productos WHERE precio_compra > 0 LIMIT 10");
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Código</th><th>Nombre</th><th>Precio Venta</th><th>Precio Compra</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['codigo']}</td>";
        echo "<td>{$row['nombre']}</td>";
        echo "<td>\${$row['precio']}</td>";
        echo "<td style='background:#d4edda;font-weight:bold;'>\${$row['precio_compra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h2 style='color:green;'>✅ BASE DE DATOS LISTA PARA EXPORTAR</h2>";
    echo "<p><strong>AHORA:</strong></p>";
    echo "<ol>";
    echo "<li>Ve a phpMyAdmin</li>";
    echo "<li>Selecciona la base de datos 'estrella'</li>";
    echo "<li>Haz clic en 'Exportar'</li>";
    echo "<li>Exporta en formato SQL</li>";
    echo "</ol>";
    echo "<p><strong>Cuando importes esta base de datos, los productos YA tendrán precio_compra guardado y NO se borrarán al reiniciar entradas.</strong></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
