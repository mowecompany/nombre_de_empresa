<?php
/**
 * Script automático para crear y migrar precio_compra
 * Se ejecuta automáticamente al cargar el sistema
 */

if (!isset($db)) {
    return;
}

$logFile = __DIR__ . '/../logs/precio_compra_setup.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logSetup($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
    error_log($msg);
}

try {
    logSetup("=== INICIO SETUP PRECIO_COMPRA ===");
    
    // Verificar si ya se ejecutó este script
    $lockFile = __DIR__ . '/../.precio_compra_migrado';
    
    // Verificar si la columna existe
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'precio_compra'");
    $existe = $stmt->fetch();
    
    if (!$existe) {
        logSetup("❌ Columna precio_compra NO EXISTE - Creando...");
        
        // Crear la columna
        $db->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio");
        logSetup("✅ Columna precio_compra CREADA");
        
        // Migrar datos INMEDIATAMENTE
        logSetup("📦 Migrando precios desde entradas...");
        $result = $db->exec("
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
        logSetup("✅ Migrados $result productos con precios");
        
        // Crear archivo de bloqueo
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        logSetup("🔒 Archivo de bloqueo creado");
        
    } else {
        logSetup("✅ Columna precio_compra YA EXISTE");
        
        // Verificar si hay productos sin precio que tengan entradas
        if (!file_exists($lockFile)) {
            logSetup("⚠️ Sin archivo de bloqueo - Verificando pendientes...");
            
            $stmt = $db->query("
                SELECT COUNT(*) as pendientes 
                FROM productos p
                WHERE (p.precio_compra IS NULL OR p.precio_compra = 0)
                AND EXISTS (
                    SELECT 1 FROM entradas_inventario e 
                    WHERE e.producto_id = p.id AND e.estado = 1
                )
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            logSetup("📊 Productos pendientes: {$result['pendientes']}");
            
            if ($result['pendientes'] > 0) {
                // Migrar los que faltan
                $db->exec("
                    UPDATE productos p 
                    SET precio_compra = (
                        SELECT AVG(e.precio_compra) 
                        FROM entradas_inventario e 
                        WHERE e.producto_id = p.id 
                        AND e.estado = 1
                    )
                    WHERE (p.precio_compra IS NULL OR p.precio_compra = 0)
                    AND EXISTS (
                        SELECT 1 FROM entradas_inventario e2 
                        WHERE e2.producto_id = p.id 
                        AND e2.estado = 1
                    )
                ");
                logSetup("✅ Migrados {$result['pendientes']} productos pendientes");
                
                // Crear archivo de bloqueo
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
                logSetup("🔒 Archivo de bloqueo creado");
            } else {
                logSetup("✅ No hay productos pendientes");
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
            }
        } else {
            logSetup("✅ Migración ya completada anteriormente");
        }
    }
    
    logSetup("=== FIN SETUP PRECIO_COMPRA ===\n");
    
} catch (Exception $e) {
    logSetup("❌ ERROR: " . $e->getMessage());
    logSetup("Stack: " . $e->getTraceAsString());
}
