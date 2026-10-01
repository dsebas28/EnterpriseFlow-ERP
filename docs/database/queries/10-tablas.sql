-- titulo: Tablas con más datos
-- descripcion: Estadísticas de PostgreSQL sobre la base de la demo: el libro de movimientos y la auditoría son las tablas que más crecen, por eso tienen sus propios índices.
SELECT relname                                         AS tabla,
       n_live_tup                                      AS filas,
       pg_size_pretty(pg_total_relation_size(relid))   AS tamano_total
FROM pg_stat_user_tables
ORDER BY n_live_tup DESC
LIMIT 12;
