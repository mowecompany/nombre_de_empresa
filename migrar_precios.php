<?php
/**
 * Script de migración de precios de compra
 * Ejecutar UNA SOLA VEZ para migrar datos existentes
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "<h2>Migración de Precios de Compra</h2>";
    echo "<p>Iniciando proceso...</p>";
    
    // Verificar si columna existe
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    if (!$stmt->fetch()) {
        echo "<p>Creando columna precio_compra...</p>";
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) DEFAULT 0.00");
        echo "<p style='color:green;'>✅ Columna creada</p>";
    } else {
        echo "<p style='color:green;'>✅ Columna ya existe</p>";
    }
    
    // Contar productos a migrar
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE (precio_compra IS NULL OR precio_compra = 0) AND id IN (SELECT DISTINCT producto_id FROM entradas_inventario WHERE estado = 1)");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<p>Productos a migrar: <strong>{$total}</strong></p>";
    
    if ($total > 0) {
        echo "<p>Procesando por lotes de 10...</p>";
        $procesados = 0;
        $lote = 0;
        
        while ($procesados < $total) {
            $lote++;
            $stmt = $db->query("
                SELECT p.id, AVG(e.precio_compra) as promedio
                FROM productos p
                INNER JOIN entradas_inventario e ON e.producto_id = p.id
                WHERE e.estado = 1 
                AND (p.precio_compra IS NULL OR p.precio_compra = 0)
                GROUP BY p.id
                LIMIT 10
            ");
            
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($productos)) break;
            
            foreach ($productos as $prod) {
                $db->prepare("UPDATE productos SET precio_compra = ? WHERE id = ?")
                   ->execute([$prod['promedio'], $prod['id']]);
                $procesados++;
            }
            
            echo "<p>Lote {$lote}: {$procesados}/{$total} productos migrados</p>";
            flush();
        }
        
        echo "<p style='color:green;font-weight:bold;'>✅ Migración completada: {$procesados} productos actualizados</p>";
    } else {
        echo "<p style='color:green;'>✅ No hay productos pendientes de migrar</p>";
    }
    
    echo "<br><p style='background:#d4edda;padding:15px;border-radius:5px;'><strong>¡LISTO!</strong> Ahora puedes reiniciar entradas sin perder los precios de compra.</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERROR: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
