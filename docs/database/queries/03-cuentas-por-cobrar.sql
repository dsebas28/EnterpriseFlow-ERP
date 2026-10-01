-- titulo: Antigüedad de cuentas por cobrar
-- descripcion: Saldo pendiente de cada cliente repartido por días de vencimiento con la cláusula FILTER de PostgreSQL. Es la misma lógica del reporte de antigüedad de saldos de la aplicación.
SELECT cu.name AS cliente,
       round(coalesce(sum(i.total - i.amount_paid)
             FILTER (WHERE i.due_date >= current_date), 0) / 100.0, 2)                   AS por_vencer,
       round(coalesce(sum(i.total - i.amount_paid)
             FILTER (WHERE current_date - i.due_date BETWEEN 1 AND 30), 0) / 100.0, 2)   AS dias_1_30,
       round(coalesce(sum(i.total - i.amount_paid)
             FILTER (WHERE current_date - i.due_date BETWEEN 31 AND 60), 0) / 100.0, 2)  AS dias_31_60,
       round(coalesce(sum(i.total - i.amount_paid)
             FILTER (WHERE current_date - i.due_date > 60), 0) / 100.0, 2)                AS mas_de_60,
       round(sum(i.total - i.amount_paid) / 100.0, 2)                                     AS total
FROM invoices i
JOIN customers cu ON cu.company_id = i.company_id AND cu.id = i.customer_id
WHERE i.status IN ('issued', 'partially_paid', 'overdue')
GROUP BY cu.name
ORDER BY total DESC
LIMIT 10;
