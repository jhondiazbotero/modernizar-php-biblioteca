# 0005. PDO y consultas preparadas

Estado: aceptada

## Contexto

La aplicación hablaba con la base con `mysqli_query` y SQL armado con texto del usuario. Escapar con `mysqli_real_escape_string` dependía de que cada consulta se acordara de hacerlo, y la búsqueda no se acordaba (falla F3): `' OR 1=1 -- ` devolvía todo el catálogo y una comilla mostraba el error de MySQL en pantalla. Además, el `--` comentaba el `ORDER BY`, y un `%` buscado por el usuario era un comodín.

## Decisión

- **PDO en vez de mysqli.** `Library\Database\ConnectionFactory::create()` abre la conexión con `ERRMODE_EXCEPTION`, `FETCH_ASSOC` por defecto y `EMULATE_PREPARES = false`. La conexión queda en `$db` (en `legacy/includes/conexion.php`); `$con` deja de existir y la extensión `mysqli` sale de la imagen y de `composer.json` (ahora `ext-pdo` y `ext-pdo_mysql`).
- **Sin emulación.** Con `EMULATE_PREPARES = false` el servidor recibe la consulta y los datos por separado: el dato nunca se interpreta como SQL. Es el modo que se usa y el que corren las pruebas.
- **Todas las consultas con datos van preparadas** (`prepare` + `execute` con marcadores `?`). Las que no llevan datos variables usan `query()`. Ninguna cadena SQL lleva una variable PHP concatenada o interpolada, y **no se usó la excepción de listas blancas de columnas**: `prestamos.php`, que antes armaba el `WHERE` y el `ORDER BY` con variables, ahora tiene una consulta completa por filtro y la URL solo elige cuál se corre.
- **Contar filas.** `mysqli_num_rows` pasa a `fetch()` (¿hay una fila?), `fetchAll()` + `count()` o `COUNT(*)` con `fetchColumn()`. No se usa `rowCount()` en `SELECT`: con MySQL no es fiable. `mysqli_insert_id` pasa a `lastInsertId()`.
- **Los errores de la base son excepciones y no llegan a la pantalla.** El front controller atrapa `\PDOException` (igual que `MissingConfigurationException`), escribe el mensaje en el registro y responde la página 500 genérica. Antes de incluir la pantalla hace `unset` de las variables propias (`$frontRoot`, `$frontPath`); `$frontFile` queda porque la pantalla se incluye desde ahí.
- **Tipos.** Sin emulación los enteros vuelven como `int` y los `DECIMAL` como cadena. Ninguna pantalla cambia por eso: todas las imprimen con `echo`, `number_format` o comparaciones numéricas. Los snapshots de las pantallas no se tocaron.

## F3 se arregla aquí

La búsqueda usa `LIKE ? ESCAPE '\\'` con el texto del usuario rodeado de `%` y con `%`, `_` y `\` escapados con `addcslashes`, así que se buscan como texto. Es la única pantalla cuyo comportamiento cambia por esta tarea (la otra es el ingreso, ADR 0006).

Las dos pruebas `known-defect` de F3 describían el defecto y dejan de ser ciertas; se reescriben en el mismo cambio y ya no están en el grupo `known-defect`:

| Antes | Ahora |
|---|---|
| `' OR 1=1 -- ` devuelve los 15 libros, sin orden por título | Se busca como texto: 0 resultados («No se encontraron libros para …») |
| Una comilla muestra el error de MySQL en la pantalla | La pantalla normal de «sin resultados», sin mensaje de error |
| (sin prueba) `%` | 0 resultados: ya no devuelve todo |
| (sin prueba) `_` y `\` | Se buscan como texto; un libro que de verdad los contiene sí aparece |
| El orden por título se perdía con el `--` | Siempre se ordena por título |

## Consecuencias

- Una consulta nueva no puede «olvidarse» de escapar: no hay nada que escapar. La revisión se resume en que no haya variables dentro de la cadena SQL (se comprueba con `grep` y a mano).
- Cualquier fallo de la base se ve como un 500 genérico y una línea en el registro, en vez de un error de PHP o de MySQL en pantalla. Un fallo a mitad de una pantalla que ya escribió datos sigue sin transacción: esta tarea cambia cómo se habla con la base, no las reglas de las pantallas.
- `ESCAPE '\\'` supone que MySQL no tiene el modo `NO_BACKSLASH_ESCAPES`, que no es el valor por defecto de MySQL 8.4.
- `ext-pdo_mysql` se pide en `composer.json`; la imagen `composer:2` no la trae, por eso `composer install` lleva `--ignore-platform-req=ext-pdo_mysql` (en el Dockerfile y en `bin/unit`).
