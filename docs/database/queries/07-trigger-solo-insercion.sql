-- titulo: El libro de inventario no se puede modificar
-- descripcion: Un trigger de PostgreSQL rechaza cualquier UPDATE o DELETE sobre stock_movements, incluso con SQL directo. Las correcciones se registran como un movimiento compensatorio nuevo.
UPDATE stock_movements
SET quantity = 999
WHERE id = (SELECT min(id) FROM stock_movements);
