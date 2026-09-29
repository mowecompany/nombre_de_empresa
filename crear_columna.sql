-- Agregar columna precio_compra a productos
ALTER TABLE productos ADD COLUMN IF NOT EXISTS precio_compra DECIMAL(10,2) DEFAULT 0.00;

-- Migrar datos desde entradas (primeros 50 productos)
UPDATE productos p 
SET precio_compra = (
    SELECT AVG(e.precio_compra) 
    FROM entradas_inventario e 
    WHERE e.producto_id = p.id 
    AND e.estado = 1
)
WHERE id <= 50
AND EXISTS (
    SELECT 1 FROM entradas_inventario e2 
    WHERE e2.producto_id = p.id 
    AND e2.estado = 1
);

SELECT 'COLUMNA CREADA Y DATOS MIGRADOS' as resultado;
