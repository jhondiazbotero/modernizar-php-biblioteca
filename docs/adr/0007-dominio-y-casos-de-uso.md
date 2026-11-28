# 0007. Dominio y casos de uso en inglés, con transacciones

Estado: aceptada

## Contexto

Las reglas de la biblioteca (quién puede llevarse un libro, cuánto vale una multa, a quién le toca el ejemplar devuelto) vivían dentro de `prestar.php`, `devolver.php`, `reservas.php` y `multas.php`, mezcladas con las consultas SQL y el HTML. Las operaciones que escriben en varias tablas (devolver toca el préstamo, la multa, el ejemplar y la reserva) corrían sin transacción: un error a mitad de camino dejaba la base a medias.

Esta tarea mueve esas reglas y esas escrituras a código nuevo, sin cambiar lo que ve el usuario. Los controladores y las plantillas de todas las pantallas llegan en el siguiente cambio de esta tarea.

## Decisión

### Capas

| Capa | Dónde | Qué hay | De qué depende |
|---|---|---|---|
| Dominio | `src/Domain/` | `LendingRules`, `FinePolicy`, `ReservationQueue`, `LoanEligibility`, objetos de datos (`MemberSnapshot`, `CopySnapshot`, `LoanSnapshot`, `ReservationSnapshot`, `PendingReservation`), la interfaz `Clock` | de nada |
| Aplicación | `src/Application/` | casos de uso (`LendCopy`, `ReturnLoan`, `PlaceReservation`, `CancelReservation`, `PayFine`), sus resultados y los puertos (`src/Application/Port/`: repositorios y `TransactionManager`) | del dominio |
| Infraestructura | `src/Infrastructure/` | repositorios PDO, `PdoTransactionManager` y `RequestClock` | de la aplicación, del dominio y de PDO |
| Pantallas heredadas | `legacy/` | formularios, listados y HTML; sus POST llaman a un caso de uso | de todo lo anterior |

Una prueba unitaria de arquitectura (`tests/unit/Architecture/LayersTest.php`) lee los archivos y falla si algo de `src/Domain` usa `PDO`, `Library\Infrastructure`, `Library\Application`, `Library\Http`, una superglobal o las funciones `hoy()`, `ahora()` y `redirigir()` del legado, o si algo de `src/Application` usa `PDO` o `Library\Infrastructure`.

### El dominio conserva F1 y F2

La regla de esta tarea es **cero cambios de comportamiento**, así que el dominio nuevo reproduce las dos fallas conocidas tal cual y lo dice en su documentación:

- **F1 (`FinePolicy`)**: si el préstamo está vencido, la multa es `(día del mes de devolución − día del mes de vencimiento) × 500`, y 0 si eso es negativo. Un préstamo que vencía el 28 de marzo y se devuelve el 3 de abril no paga nada.
- **F2 (`ReservationQueue`)**: la siguiente reserva es la del primer socio por nombre, no la primera en llegar.

Quedaron fijadas en pruebas unitarias con su nombre (`test_f1_…`, `test_f2_…`) y siguen fijadas por las de caracterización por HTTP. Se arreglan en la tarea 6, cada una con su ADR y su prueba en rojo; ese cambio solo tocará `FinePolicy` y `ReservationQueue`.

### Orden de nombres como el de MySQL

La cola antes dejaba el orden a MySQL (`ORDER BY s.nombre`, con la intercalación `utf8mb4_unicode_ci`: no distingue mayúsculas ni tildes). El dominio no puede preguntarle a la base, así que `NameCollation` compara igual: minúsculas, sin tildes (la `ñ` cuenta como `n`, como en esa intercalación y a diferencia de una colación española) y sin espacios al final; el desempate es el id de la reserva. La extensión `intl` no está en las imágenes de PHP 7.4, y `Collator('es')` tampoco ordenaría la `ñ` como MySQL; por eso la comparación es de PHP puro. Una prueba de integración compara su orden con el de MySQL sobre una lista de nombres con tildes, mayúsculas, `ñ`, `ü`, espacios y números.

### Casos de uso y transacciones

Cada caso de uso es una clase con `handle(...)` que devuelve un resultado: éxito con lo que la pantalla necesita para armar su mensaje, o un **código de error en inglés** (`member_inactive`, `max_active_loans`, `unpaid_fines`, `copy_on_loan`, `copy_withdrawn`, `copy_reserved_for_other`, `loan_not_found`, `already_returned`, `copies_available`, `already_pending`, `not_cancellable`, `no_fine`, `already_paid`…).

**Toda escritura va dentro de `TransactionManager::transactional()`**: prestar (préstamo, ejemplar y reserva atendida), devolver (préstamo, multa, ejemplar y reserva asignada), reservar, cancelar (reserva, ejemplar y siguiente de la cola) y pagar. Si algo lanza una excepción se deshace todo y la excepción sigue su camino (el front controller la convierte en la página 500). Dentro de la transacción se leen con `SELECT … FOR UPDATE` el socio, el ejemplar, el préstamo, la reserva y las reservas pendientes del libro, y el libro al reservar, para que dos peticiones a la vez no se pisen (por ejemplo, dos devoluciones del mismo libro que elegirían la misma reserva). Los errores de regla (socio inactivo, préstamo ya devuelto…) se devuelven antes de escribir nada.

### Los mensajes siguen en español, en la pantalla

El dominio y la aplicación no tienen texto para el usuario. Cada pantalla traduce el código con `mensaje_error()` (`legacy/includes/mensajes.php`) a los mismos textos de antes, y arma sus mensajes de éxito con los datos del resultado. `servicios()` (`legacy/includes/servicios.php`) arma los casos de uso con la conexión `$db` y `RequestClock`; no hay contenedor de dependencias.

### El reloj sale del legado

`Library\Domain\Clock` (`today()`, `now()`) tiene una implementación, `RequestClock`, con la lógica de antes: la fecha solo se fija con `APP_ENV=test` y una cabecera `X-Fecha-Fija` válida (ADR 0002); si no, hora real de `America/Bogota`. `hoy()` y `ahora()` del legado delegan en él y `fecha_fija()` desaparece. Los casos de uso reciben el reloj por el constructor, de modo que las pruebas unitarias usan una hora fija sin tocar globales.

### Pruebas

- Unitarias del dominio y de los casos de uso (con repositorios en memoria y un `TransactionManager` falso que descarta lo escrito cuando algo falla y rechaza escrituras fuera de una transacción).
- **Integración con MySQL real** (`bin/integration`, job `integration` del CI): los repositorios PDO ejecutan sus consultas, y un repositorio que falla después del primer `UPDATE` de `ReturnLoan` comprueba por SQL que el préstamo no quedó devuelto, que no hay multa y que el ejemplar no cambió.
- Las 152 pruebas de caracterización no cambian.

## Consecuencias

- Las reglas tienen un solo lugar y se prueban sin base de datos ni HTTP; arreglar F1 y F2 en la tarea 6 es cambiar dos clases.
- Una operación que falla a mitad ya no deja datos a medias.
- Cada operación hace más consultas que antes (el dominio decide con listas completas, por ejemplo todas las reservas pendientes del libro) a cambio de no depender de `ORDER BY … LIMIT 1`. Con el tamaño de una biblioteca comunitaria no importa.
- El orden de los nombres duplica en PHP lo que hace MySQL para letras latinas; los alfabetos que no sean latinos se comparan por bytes. Si algún día cambia la intercalación de la columna, la prueba de integración lo detecta.
- Las pantallas de listados, altas y ediciones (socios, libros, ejemplares) y el ingreso siguen con SQL propio: no escriben reglas de préstamo y se mueven en el siguiente cambio de la tarea o más adelante.
- `ahora()` queda definida en el legado aunque ninguna pantalla la use hoy.
