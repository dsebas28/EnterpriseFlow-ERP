-- titulo: Aislamiento multiempresa
-- descripcion: Todas las tablas de negocio tienen company_id. La aplicación filtra siempre por la empresa activa (y falla si no hay ninguna), así cada empresa solo ve sus propios datos aunque compartan la misma base.
SELECT c.name     AS empresa,
       c.currency AS moneda,
       (SELECT count(*) FROM products p        WHERE p.company_id = c.id)  AS productos,
       (SELECT count(*) FROM customers cu      WHERE cu.company_id = c.id) AS clientes,
       (SELECT count(*) FROM sales s           WHERE s.company_id = c.id)  AS ventas,
       (SELECT count(*) FROM invoices i        WHERE i.company_id = c.id)  AS facturas,
       (SELECT count(*) FROM stock_movements m WHERE m.company_id = c.id)  AS movimientos
FROM companies c
ORDER BY c.name;
