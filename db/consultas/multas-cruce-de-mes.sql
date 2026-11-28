-- Préstamos devueltos con retraso cuya multa guardada no coincide con la regla correcta:
-- días calendario entre el vencimiento y la devolución por la multa de cada día.
-- Solo lee (no modifica nada): la biblioteca decide caso por caso qué hacer con cada fila.
-- Las multas guardadas antes de v0.3.0 con el cálculo anterior (que restaba el día del mes
-- y fallaba al cruzar de mes) no se corrigen solas. Ver docs/adr/0009-correccion-de-f1-multa.md.
--
-- Uso:
--   docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < db/consultas/multas-cruce-de-mes.sql

WITH regla AS (
    SELECT 500 AS multa_por_dia -- el mismo valor que LendingRules::FINE_PER_DAY
)
SELECT
    p.id AS prestamo_id,
    s.nombre AS socio,
    p.fecha_vence AS vencimiento,
    p.fecha_devolucion AS devolucion,
    p.multa AS multa_guardada,
    DATEDIFF(p.fecha_devolucion, p.fecha_vence) * regla.multa_por_dia AS multa_correcta
FROM prestamos p
JOIN socios s ON s.id = p.socio_id
CROSS JOIN regla
WHERE p.fecha_devolucion IS NOT NULL
  AND p.fecha_devolucion > p.fecha_vence
  AND p.multa <> DATEDIFF(p.fecha_devolucion, p.fecha_vence) * regla.multa_por_dia
ORDER BY p.id;
