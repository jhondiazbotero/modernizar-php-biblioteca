# 0002. Costura de fecha para las pruebas

Estado: aceptada

## Contexto

Multas, vencimientos y reservas dependen de la fecha y la hora de «hoy». Sin controlarlas, las pruebas por HTTP dan resultados distintos cada día y los snapshots no se pueden comparar. El legado toma la fecha en un solo lugar (`hoy()` en `includes/funciones.php`), salvo una hora que `reservas.php` pedía con `date('H:i:s')`.

## Decisión

- Se toca el legado en lo mínimo: `hoy()` y una función nueva `ahora()` devuelven la fecha de la cabecera `X-Fecha-Fija` (`Y-m-d` o `Y-m-d H:i:s`, validada al volver a formatearla; si llega solo la fecha, la hora es `12:00:00`). `reservas.php` usa `ahora()`.
- La cabecera **solo se lee si `APP_ENV=test`**. En cualquier otro caso se usa el reloj real, y un valor inválido se ignora.
- `docker-compose.yml` define `APP_ENV` con valor `production` por defecto. Solo `docker-compose.test.yml` lo pone en `test`.
- El CI lo vigila: con la aplicación en modo normal inicia sesión, crea un préstamo enviando `X-Fecha-Fija: 2000-01-01` y comprueba en la base que `fecha_prestamo` es la fecha de hoy en `America/Bogota`.

## Consecuencias

- Es el único cambio al código heredado en esta fase; no cambia ninguna regla de negocio.
- Las fases siguientes tienen que conservar la costura mientras existan las pruebas (el dominio recibirá «ahora» como parámetro).
- Si alguien pone `APP_ENV=test` en un despliegue real, cualquiera podría fijar la fecha. El valor por defecto y el paso del CI existen para que eso no pase sin que se note.
