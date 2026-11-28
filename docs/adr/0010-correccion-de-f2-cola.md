# 0010. Corrección de F2: la cola de reservas respeta el orden de llegada

Estado: aceptada

## Contexto

Cuando un ejemplar quedaba libre (al devolver un préstamo o al cancelar una reserva que ya tenía ejemplar apartado), la siguiente reserva de la cola se elegía por el **nombre del socio**: `ORDER BY s.nombre LIMIT 1` en el código original y, desde la tarea 5, `ReservationQueue` con `NameCollation`, que imitaba en PHP el orden de MySQL (`utf8mb4_unicode_ci`). Así, quien reservaba primero pero se llamaba «Sofía» perdía el turno frente a «Andrés», que reservó dos días después. La caracterización y las pruebas unitarias fijaban ese comportamiento con los nombres `test_f2_…` y el grupo `known-defect`.

## Decisión

- **Regla.** La siguiente reserva pendiente es la de `reservedAt` más antiguo; si dos tienen el mismo instante, la de id menor. `ReservationQueue::next()` la aplica y la usan los dos casos de uso que dan el ejemplar a la cola: `ReturnLoan` (devolución) y `CancelReservation` (cancelación de una reserva asignada).
- **Se borra `NameCollation`** y su prueba unitaria y la de integración que la comparaba con MySQL: ya nada ordena por nombre. `PendingReservation` conserva el nombre del socio porque el mensaje «Ejemplar apartado para <socio>» lo necesita, pero ya no interviene en el orden.
- **Cómo se probó.** Antes del arreglo se reescribieron las pruebas y se vieron fallar con el código anterior: `ReservationQueueTest` (orden por llegada, mismo instante por id, hora del día, lista vacía y de un elemento), `ReturnLoanTest` y `CancelReservationTest` con el resultado nuevo, las dos de integración con MySQL y las dos de caracterización por HTTP (en la devolución de «Misericordia» el ejemplar es para Sofía, que reservó el 10 de noviembre, y no para Andrés, que reservó el 12; al cancelar la reserva de «Emma» es para Paula, del 14, y no para Mateo, del 16). Ya no pertenecen al grupo `known-defect`. Los resultados de cada rojo están en `PROGRESO.md`.
- **Datos ya guardados.** Las reservas que ya quedaron asignadas por el orden anterior **no se reasignan**: el ejemplar ya está apartado a nombre de ese socio y quitárselo sin que la biblioteca lo decida sería un cambio que nadie pidió. Solo las asignaciones nuevas siguen la regla. Si la biblioteca quiere corregir alguna, la cancela y la vuelve a crear desde la pantalla de reservas (la cancelación pasa el ejemplar al primero de la cola).

## Consecuencias

- La cola se comporta como la pantalla de reservas la muestra (ordenada por hora de reserva y numerada).
- El orden ya no depende de la intercalación de la columna ni de tablas de tildes en PHP: dos fechas y un id.
- La consulta del repositorio sigue trayendo las pendientes del libro con `FOR UPDATE` y el dominio decide cuál es la siguiente.
- Una reserva hecha en el mismo segundo que otra se resuelve por id; la columna `fecha_reserva` guarda segundos, no fracciones.
