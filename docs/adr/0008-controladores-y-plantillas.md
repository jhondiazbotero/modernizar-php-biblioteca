# 0008. Controladores y plantillas

Estado: aceptada. Reemplaza lo que el ADR 0004 decía del enrutador heredado, de `legacy/` y del `chdir`.

## Contexto

Después de la tarea 4 cada pantalla seguía siendo un archivo de `legacy/` que mezclaba en el mismo lugar el SQL, las reglas, las redirecciones y el HTML, con funciones globales (`hoy()`, `redirigir()`, `verificar_sesion()`), variables compartidas entre archivos (`$db`) y un `chdir` para que los `include` relativos siguieran funcionando. Esa mezcla era la causa de la falla F4: cada pantalla decidía por su cuenta si escapaba o no lo que imprimía, y `socios.php` no escapaba el nombre.

La tarea 5 ya había movido las reglas y las escrituras a casos de uso (ADR 0007). Faltaban las pantallas.

## Decisión

### Una petición, un controlador, una plantilla

- `Library\Http\Request` y `Response` son objetos inmutables (método, ruta, parámetros y cabeceras; estado, cabeceras y cuerpo). `Request::fromGlobals()` y `Response::send()` son los únicos lugares que tocan `$_GET`, `$_POST`, `$_SERVER`, `header()` y `http_response_code()`. `Session` envuelve `$_SESSION` (`start`, `get`, `set`, `regenerate`, `destroy`) y tiene una versión en memoria para las pruebas.
- **Los controladores** (`src/Http/Controller/`) reciben sus dependencias por el constructor, leen el `Request`, llaman a un caso de uso o a una consulta y devuelven un `Response`. No tienen SQL, no imprimen y no leen superglobales. Los códigos de error de los casos de uso están en inglés y el texto en español vive en el controlador, igual que antes en cada pantalla; las redirecciones con mensaje siguen usando `?msg=…&tipo=ok|error` en la misma URL.
- **Las plantillas** (`templates/`, fuera de `public/`) son PHP plano. `View::render()` las ejecuta con un buffer de salida y solo acepta nombres de plantilla de una lista cerrada de caracteres (sin `..`, sin rutas absolutas). `PageRenderer` pone el cuerpo de cada pantalla dentro de `layout.php` (cabecera, menú, mensaje y pie).
- **Escape por defecto.** Todo lo que una plantilla imprime pasa por `e()` (`htmlspecialchars` con `ENT_QUOTES | ENT_SUBSTITUTE` y UTF-8). La única excepción es `$content` en `layout.php`, que ya es HTML armado por `View` con valores escapados. Una prueba unitaria (`TemplatesTest`) recorre las plantillas con el tokenizador de PHP y falla si un `<?=` o un `echo` imprime una variable sin ser exactamente una llamada a `e(…)`; la lista de excepciones es explícita y está comentada en la prueba. Esa prueba es la que cierra F4: una pantalla nueva no puede olvidar el escape sin que falle.
- `Format` (`money`, `date`, `dateTime`, `memberStatus`) reemplaza a `formato_moneda`, `formato_fecha` y `estado_socio` con el mismo resultado byte a byte.

### Un enrutador con tabla fija

`Library\Http\Router` tiene una tabla constante `ruta → [clase de controlador, acción, exige sesión]`. La ruta de la URL solo se usa como **clave de la tabla**: nada de lo que manda el usuario forma un nombre de clase, de método o de archivo, así que `/../`, `/legacy/socios.php` o `/socios.php/extra` no encuentran nada y responden 404 con la página propia. Las rutas protegidas sin sesión responden 302 a `login.php`, como `verificar_sesion()`. Los controladores se construyen solo cuando la ruta los necesita (el enrutador recibe una función por clase), de modo que un 404 o una redirección al ingreso no abren la conexión a la base.

Cada ruta nueva exige agregar una línea a la tabla y a su prueba; es incómodo, y justamente por eso no se abre una URL sin querer.

### Consultas y casos de uso que faltaban

- Las pantallas de solo lectura piden sus datos a interfaces de consulta (`src/Application/Query/`: tablero, socios, libros, ejemplares, préstamos, reservas, multas y búsqueda). Sus implementaciones PDO (`src/Infrastructure/Persistence/Query/`) conservan el SQL y el orden de antes y devuelven arreglos con claves en inglés.
- Las altas, ediciones y bajas que aún vivían en las pantallas pasaron a casos de uso con transacción, probados con repositorios en memoria: `RegisterMember`, `EditMember`, `RegisterBook`, `EditBook`, `AddCopy`, `WithdrawCopy` y `Authenticate` (el ingreso: `PasswordVerifier`, hash ficticio si el usuario no existe y rehash, ADR 0006). Las reglas son las de antes: documento y nombre obligatorios en socios (y documento no repetido), título y autor obligatorios en libros, código del ejemplar obligatorio y no repetido, y no dar de baja un ejemplar prestado o reservado. Editar un socio o un libro que no existe sigue respondiendo «actualizado», como antes.
- `Library\Bootstrap\Application` es la raíz de composición: arma a mano la conexión (perezosa), el reloj de la petición, los repositorios, los casos de uso y los controladores. `public/index.php` quedó en tres líneas. El manejo de `MissingConfigurationException` y `PDOException` es el mismo: el mensaje al `error_log` y una página 500 genérica.

### Migración pantalla por pantalla

El cambio no se hizo de golpe. Durante la transición `public/index.php` preguntaba primero al `Router` y, si la ruta no estaba, caía en la lista blanca heredada (`LegacyRouter`). Cada commit migró una pantalla (o un grupo pequeño: tablero; ingreso y salida; listado y ficha de socios; edición de socios; libros; ejemplares; préstamos; reservas; multas; búsqueda), borró su archivo de `legacy/` y su entrada de la lista, y dejó las tres suites en verde con los snapshots sin cambios. Así un fallo apuntaba a una sola pantalla y el HTML que llega al navegador nunca dejó de coincidir con los snapshots.

Al migrar la última pantalla se borró `legacy/` completo (`includes/` con la conexión, las funciones globales, la cabecera, el pie, `servicios.php` y `mensajes.php`), `LegacyRouter` con su prueba y el híbrido de `public/index.php`.

### Adiós al `chdir`

El `chdir` existía para que cada pantalla viera el mismo directorio de trabajo que cuando Apache la ejecutaba directamente (ADR 0004). Con controladores y plantillas nadie depende del directorio de trabajo: las plantillas se buscan con una ruta absoluta desde la raíz del proyecto, y no queda ningún `include` relativo. Desaparece con `legacy/`.

### F4 corregida

El listado de socios imprime el nombre con `e()`. La prueba de caracterización `StoredXssTest` dejó de ser un `known-defect` y ahora fija el comportamiento nuevo: el nombre `<b>Ana</b>` sale como `&lt;b&gt;Ana&lt;/b&gt;`. Los 25 snapshots no cambian (ningún dato de la semilla lleva HTML ni comillas simples, así que pasar de `htmlspecialchars` a `ENT_QUOTES` no se nota en ellos).

## Consecuencias

- Una pantalla es un controlador pequeño (sin SQL) y una plantilla (sin lógica de datos); las reglas están en los casos de uso y las consultas en un solo lugar.
- Escapar es lo que pasa si uno no hace nada; no escapar es lo que hay que justificar en una lista.
- Con `ENT_QUOTES` las comillas simples de un dato salen como `&#039;` (antes salían tal cual). En el navegador se ven igual; solo cambian los bytes del HTML cuando un dato las lleva.
- Con `ENT_SUBSTITUTE` un dato con UTF-8 inválido se muestra con el carácter de reemplazo; antes `htmlspecialchars` devolvía una cadena vacía.
- La capa HTTP depende de los puertos de la aplicación y de nada de infraestructura (`LayersTest`); las plantillas no conocen PDO ni superglobales (`TemplatesTest`).
- Quedan para la tarea 6 las fallas F1 y F2; este cambio no tocó ninguna regla de negocio.
