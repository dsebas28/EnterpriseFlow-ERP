-- titulo: Plan de ejecución con índice compuesto
-- descripcion: El listado de ventas de una empresa por estado y fecha usa el índice (company_id, status, sale_date): PostgreSQL lo recorre sin leer la tabla completa.
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT number, sale_date, total
FROM sales
WHERE company_id = (SELECT id FROM companies WHERE name = 'Demo Company')
  AND status = 'paid'
ORDER BY sale_date DESC
LIMIT 20;
