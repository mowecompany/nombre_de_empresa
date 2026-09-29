<?php
// Script de emergencia para agregar precio_compra a productos
try {
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Verificando columna precio_compra...\n";
    $stmt = $db->query('SHOW COLUMNS FROM productos LIKE "precio_compra"');
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existe) {
        echo "2. Creando columna precio_compra...\n";
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Columna creada\n";
    } else {
        echo "✅ Columna ya existe\n";
    }
    
    echo "3. Copiando precios desde entradas (esto puede tardar)...\n";
    
    // Hacerlo producto por producto para evitar timeout
    $stmt = $db->query("SELECT DISTINCT p.id FROM productos p 
                        INNER JOIN entradas_inventario e ON e.producto_id = p.id 
                        WHERE e.estado = 1");
    $productos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $actualizado = 0;
    foreach ($productos as $pid) {
        $stmt2 = $db->prepare("UPDATE productos SET precio_compra = (
            SELECT AVG(precio_compra) FROM entradas_inventario 
            WHERE producto_id = ? AND estado = 1
        ) WHERE id = ?");
        $stmt2->execute([$pid, $pid]);
        $actualizado++;
        
        if ($actualizado % 10 == 0) {
            echo ".";
        }
    }
    
    echo "\n✅ {$actualizado} productos actualizados\n";
    echo "\n¡LISTO! Ahora el reinicio de entradas NO afectará los precios de productos.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
