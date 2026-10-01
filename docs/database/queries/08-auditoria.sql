-- titulo: Auditoría de cambios
-- descripcion: Cada cambio queda registrado con el usuario, el evento y solo los campos modificados (en JSON), en la misma transacción que el cambio. La tabla también está protegida contra modificaciones.
SELECT a.created_at::timestamp(0)                                         AS fecha,
       coalesce(u.name, 'Sistema')                                        AS usuario,
       a.event                                                            AS evento,
       a.auditable_type                                                   AS registro,
       (SELECT string_agg(k, ', ') FROM json_object_keys(a.new_values) k) AS campos_modificados
FROM audit_logs a
LEFT JOIN users u ON u.id = a.user_id
WHERE a.new_values IS NOT NULL
ORDER BY a.id DESC
LIMIT 10;
