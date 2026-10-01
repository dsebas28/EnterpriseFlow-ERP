-- titulo: Libro de inventario de un producto
-- descripcion: El stock no se edita: cada entrada o salida es un movimiento que guarda el saldo resultante. La suma acumulada (función de ventana) reconstruye el saldo y siempre coincide con balance_after.
SELECT m.occurred_at::date                                    AS fecha,
       m.type                                                 AS tipo,
       m.quantity                                             AS cantidad,
       m.balance_after                                        AS saldo,
       sum(m.quantity) OVER (PARTITION BY m.product_id, m.warehouse_id
                             ORDER BY m.occurred_at, m.id)    AS saldo_recalculado
FROM stock_movements m
JOIN products p   ON p.company_id = m.company_id AND p.id = m.product_id
JOIN warehouses w ON w.company_id = m.company_id AND w.id = m.warehouse_id
JOIN companies c  ON c.id = m.company_id
WHERE c.name = 'Demo Company' AND p.sku = 'ELE-003' AND w.code = 'MAIN'
ORDER BY m.occurred_at, m.id
LIMIT 14;
