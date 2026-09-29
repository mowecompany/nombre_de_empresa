<?php
/**
 * SCRIPT DE EMERGENCIA - CREAR COLUMNA PRECIO_COMPRA
 * Abre este archivo en el navegador: http://localhost/nombre_de_empresa/EJECUTAR_ESTO.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "<h1>🔧 Reparación de Precio de Compra</h1>";
echo "<hr>";

try {
    // Conectar a la base de datos
    $db = new PDO('mysql:host=localhost;dbname=estrella', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>✅ Conexión exitosa a la base de datos</h3>";
    
    // Paso 1: Verificar si la columna existe
    echo "<h3>Paso 1: Verificando columna precio_compra...</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    $existe = $stmt->fetch();
    
    if ($existe) {
        echo "<p style='color:orange;'>⚠️ La columna ya existe. Verificando datos...</p>";
    } else {
        echo "<p>❌ La columna NO existe. Creándola ahora...</p>";
        
        try {
            $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio");
            echo "<p style='color:green;font-size:18px;'><strong>✅ ¡COLUMNA CREADA EXITOSAMENTE!</strong></p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error al crear columna: " . $e->getMessage() . "</p>";
            
            // Intentar sintaxis alternativa
            echo "<p>Intentando sintaxis alternativa...</p>";
            $db->exec("ALTER TABLE productos ADD precio_compra DECIMAL(10,2) DEFAULT 0.00");
            echo "<p style='color:green;'><strong>✅ ¡COLUMNA CREADA CON SINTAXIS ALTERNATIVA!</strong></p>";
        }
    }
    
    // Paso 2: Migrar datos de las primeras 20 productos para probar
    echo "<h3>Paso 2: Migrando precios de compra desde entradas (primeros 20 productos)...</h3>";
    
    $stmt = $db->query("
        SELECT p.id, p.nombre, p.precio_compra as actual,
               (SELECT AVG(e.precio_compra) 
                FROM entradas_inventario e 
                WHERE e.producto_id = p.id AND e.estado = 1) as promedio
        FROM productos p
        WHERE EXISTS (
            SELECT 1 FROM entradas_inventario e2 
            WHERE e2.producto_id = p.id AND e2.estado = 1
        )
        LIMIT 20
    ");
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Producto</th><th>Precio Actual</th><th>Promedio Entradas</th><th>Acción</th></tr>";
    
    $actualizados = 0;
    foreach ($productos as $prod) {
        $actualizar = false;
        
        if ($prod['promedio'] > 0) {
            $stmtUpdate = $db->prepare("UPDATE productos SET precio_compra = ? WHERE id = ?");
            $stmtUpdate->execute([$prod['promedio'], $prod['id']]);
            $actualizados++;
            $actualizar = true;
        }
        
        echo "<tr>";
        echo "<td>{$prod['id']}</td>";
        echo "<td>{$prod['nombre']}</td>";
        echo "<td>\${$prod['actual']}</td>";
        echo "<td>\${$prod['promedio']}</td>";
        echo "<td style='color:" . ($actualizar ? 'green' : 'gray') . ";'>" . ($actualizar ? '✅ Actualizado' : '⚠️ Sin entradas') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<p style='color:green;font-size:16px;'><strong>✅ {$actualizados} productos actualizados</strong></p>";
    
    // Paso 3: Ver estado final
    echo "<h3>Paso 3: Verificación final</h3>";
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE precio_compra > 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p style='background:#d4edda;padding:15px;border-radius:8px;font-size:18px;'>";
    echo "<strong>✅ PRODUCTOS CON PRECIO DE COMPRA: {$result['total']}</strong>";
    echo "</p>";
    
    echo "<hr>";
    echo "<h2 style='color:green;'>🎉 ¡PROCESO COMPLETADO!</h2>";
    echo "<p><strong>Ahora recarga la página de productos y verás los precios de compra.</strong></p>";
    echo "<p><strong>Cuando reinicies entradas, los precios NO se borrarán.</strong></p>";
    
    // Paso 4: Mostrar SQL para copiar todos los productos
    echo "<hr>";
    echo "<h3>Paso 4: Para migrar TODOS los productos restantes</h3>";
    echo "<p>Ejecuta esta consulta SQL en phpMyAdmin o MySQL:</p>";
    echo "<textarea style='width:100%;height:150px;font-family:monospace;'>";
    echo "UPDATE productos p 
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
AND (precio_compra IS NULL OR precio_compra = 0);";
    echo "</textarea>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR</h2>";
    echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
