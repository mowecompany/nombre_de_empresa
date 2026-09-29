<?php
echo "<h1>Log de Setup Precio Compra</h1>";
echo "<hr>";

$logFile = __DIR__ . '/logs/precio_compra_setup.log';

if (file_exists($logFile)) {
    echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;'>";
    echo htmlspecialchars(file_get_contents($logFile));
    echo "</pre>";
} else {
    echo "<p style='color:red;'>❌ El archivo de log no existe todavía. Recarga cualquier página del sistema primero.</p>";
}

echo "<hr>";
echo "<h2>Estado de la Base de Datos</h2>";

try {
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    
    // Ver si columna existe
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    if ($stmt->fetch()) {
        echo "<p style='color:green;'>✅ Columna precio_compra EXISTE</p>";
        
        // Ver cuántos productos tienen precio
        $stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE precio_compra > 0");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>📊 Productos con precio_compra: <strong>{$result['total']}</strong></p>";
        
        // Ver algunos ejemplos
        $stmt = $db->query("SELECT id, nombre, precio, precio_compra, stock FROM productos WHERE precio_compra > 0 LIMIT 10");
        echo "<h3>Ejemplos de productos:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Precio Venta</th><th>Precio Compra</th><th>Stock</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['nombre']}</td>";
            echo "<td>\${$row['precio']}</td>";
            echo "<td style='color:green;font-weight:bold;'>\${$row['precio_compra']}</td>";
            echo "<td>{$row['stock']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color:red;'>❌ Columna precio_compra NO EXISTE</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
