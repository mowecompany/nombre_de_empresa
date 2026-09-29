-- Crear columna precio_compra si no existe
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio;

-- Ver cuántos productos hay
SELECT COUNT(*) as total_productos FROM productos;

-- Ver cuántos tienen entradas
SELECT COUNT(DISTINCT producto_id) as productos_con_entradas FROM entradas_inventario WHERE estado = 1;

-- Migrar precios (hacer por lotes para evitar timeout)
-- Lote 1: primeros 100
UPDATE productos p 
SET precio_compra = (
    SELECT AVG(e.precio_compra) 
    FROM entradas_inventario e 
    WHERE e.producto_id = p.id 
    AND e.estado = 1
)
WHERE p.id <= 100
AND EXISTS (
    SELECT 1 FROM entradas_inventario e2 
    WHERE e2.producto_id = p.id 
    AND e2.estado = 1
);

-- Lote 2: 101-200
UPDATE productos p 
SET precio_compra = (
    SELECT AVG(e.precio_compra) 
    FROM entradas_inventario e 
    WHERE e.producto_id = p.id 
    AND e.estado = 1
)
WHERE p.id BETWEEN 101 AND 200
AND EXISTS (
    SELECT 1 FROM entradas_inventario e2 
    WHERE e2.producto_id = p.id 
    AND e2.estado = 1
);

-- Lote 3: 201-300
UPDATE productos p 
SET precio_compra = (
    SELECT AVG(e.precio_compra) 
    FROM entradas_inventario e 
    WHERE e.producto_id = p.id 
    AND e.estado = 1
)
WHERE p.id BETWEEN 201 AND 300
AND EXISTS (
    SELECT 1 FROM entradas_inventario e2 
    WHERE e2.producto_id = p.id 
    AND e2.estado = 1
);

-- Lote 4: 301-500
UPDATE productos p 
SET precio_compra = (
    SELECT AVG(e.precio_compra) 
    FROM entradas_inventario e 
    WHERE e.producto_id = p.id 
    AND e.estado = 1
)
WHERE p.id BETWEEN 301 AND 500
AND EXISTS (
    SELECT 1 FROM entradas_inventario e2 
    WHERE e2.producto_id = p.id 
    AND e2.estado = 1
);

-- Lote 5: 501+
UPDATE productos p 
SET precio_compra = (
    SELECT AVG(e.precio_compra) 
    FROM entradas_inventario e 
    WHERE e.producto_id = p.id 
    AND e.estado = 1
)
WHERE p.id > 500
AND EXISTS (
    SELECT 1 FROM entradas_inventario e2 
    WHERE e2.producto_id = p.id 
    AND e2.estado = 1
);

-- Ver resultado
SELECT COUNT(*) as productos_con_precio FROM productos WHERE precio_compra > 0;

-- Ver ejemplos
SELECT id, codigo, nombre, precio as precio_venta, precio_compra, stock 
FROM productos 
ORDER BY id DESC 
LIMIT 20;
