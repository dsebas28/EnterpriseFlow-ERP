-- titulo: Ventas por mes
-- descripcion: Ventas confirmadas de Demo Company agrupadas por mes, con el total facturado y el ticket promedio. Los importes se guardan en centavos (enteros) y se convierten a dólares solo al mostrarlos.
SELECT to_char(date_trunc('month', s.sale_date), 'YYYY-MM') AS mes,
       count(*)                                        AS ventas,
       round(sum(s.total) / 100.0, 2)                  AS total_usd,
       round(avg(s.total) / 100.0, 2)                  AS ticket_promedio
FROM sales s
JOIN companies c ON c.id = s.company_id
WHERE c.name = 'Demo Company'
  AND s.status IN ('confirmed', 'partially_paid', 'paid')
GROUP BY 1
ORDER BY 1;
