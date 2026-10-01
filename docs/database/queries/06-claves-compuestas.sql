-- titulo: Claves foráneas compuestas por empresa
-- descripcion: Las relaciones incluyen company_id en la clave foránea: la base de datos impide que una línea de venta apunte a una venta o a un producto de otra empresa, incluso si la aplicación tuviera un error.
SELECT conrelid::regclass         AS tabla,
       conname                    AS restriccion,
       pg_get_constraintdef(oid)  AS definicion
FROM pg_constraint
WHERE contype = 'f'
  AND conrelid IN ('sale_items'::regclass, 'payments'::regclass)
ORDER BY tabla, restriccion;
