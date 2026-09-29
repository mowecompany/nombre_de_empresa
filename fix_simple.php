<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

try {
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Paso 1: Verificando columna...<br>";
    flush();
    
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    $existe = $stmt->fetch();
    
    if (!$existe) {
        echo "Paso 2: Creando columna precio_compra...<br>";
        flush();
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Columna creada<br>";
        flush();
    } else {
        echo "✅ Columna ya existe<br>";
        flush();
    }
    
    echo "Paso 3: Migrando datos (limitado a 100 productos)...<br>";
    flush();
    
    $stmt = $db->query("SELECT id FROM productos WHERE precio_compra = 0 OR precio_compra IS NULL LIMIT 100");
    $productos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Encontrados " . count($productos) . " productos sin precio_compra<br>";
    flush();
    
    $actualizado = 0;
    foreach ($productos as $pid) {
        $stmt2 = $db->prepare("SELECT AVG(precio_compra) as promedio FROM entradas_inventario WHERE producto_id = ? AND estado = 1");
        $stmt2->execute([$pid]);
        $result = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['promedio'] > 0) {
            $stmt3 = $db->prepare("UPDATE productos SET precio_compra = ? WHERE id = ?");
            $stmt3->execute([$result['promedio'], $pid]);
            $actualizado++;
        }
    }
    
    echo "<br>✅ {$actualizado} productos actualizados<br>";
    echo "<br><strong style='color:green;'>¡LISTO! Ahora puedes reiniciar entradas sin perder precios de compra.</strong><br>";
    
} catch (Exception $e) {
    echo "<br>❌ ERROR: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
