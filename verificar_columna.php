try {
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si columna existe
    $stmt = $db->query('SHOW COLUMNS FROM productos LIKE "precio_compra"');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Columna precio_compra EXISTE\n\n";
        
        // Ver algunos productos
        $stmt2 = $db->query('SELECT id, nombre, precio, precio_compra, stock FROM productos LIMIT 5');
        $productos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Productos:\n";
        foreach ($productos as $p) {
            echo "ID: {$p['id']}, Nombre: {$p['nombre']}, Precio Venta: {$p['precio']}, Precio Compra: {$p['precio_compra']}, Stock: {$p['stock']}\n";
        }
    } else {
        echo "❌ Columna precio_compra NO EXISTE\n";
        echo "Intentando crear la columna...\n\n";
        
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Columna creada\n\n";
        
        echo "Migrando datos desde entradas...\n";
        $db->exec("UPDATE productos p 
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
                   )");
        echo "✅ Datos migrados\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
