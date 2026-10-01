-- titulo: Productos con más ingresos y su margen
-- descripcion: Cada línea de venta guarda el costo del producto en el momento de confirmar la venta (unit_cost), así el margen histórico no cambia aunque el costo actual sí lo haga.
SELECT p.sku,
       p.name                                                               AS producto,
       sum(si.quantity)                                                     AS unidades,
       round(sum(si.line_subtotal) / 100.0, 2)                              AS ingresos,
       round(sum(si.line_subtotal - si.unit_cost * si.quantity) / 100.0, 2) AS margen,
       round(100.0 * sum(si.line_subtotal - si.unit_cost * si.quantity)
             / nullif(sum(si.line_subtotal), 0), 1)                         AS margen_pct
FROM sale_items si
JOIN sales s     ON s.company_id = si.company_id AND s.id = si.sale_id
JOIN products p  ON p.company_id = si.company_id AND p.id = si.product_id
JOIN companies c ON c.id = si.company_id
WHERE c.name = 'Demo Company'
  AND s.status IN ('confirmed', 'partially_paid', 'paid')
GROUP BY p.sku, p.name
ORDER BY ingresos DESC
LIMIT 10;
