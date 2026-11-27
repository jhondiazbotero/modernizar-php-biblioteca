# Modernizar PHP: biblioteca comunitaria

Una aplicación PHP heredada que se moderniza por fases sin cambiar lo que ve el usuario: primero se fija su comportamiento con pruebas y después cada mejora llega en su propio PR.

## Qué es

Es la aplicación de una biblioteca comunitaria: socios, libros y ejemplares, préstamos con multa por retraso, reservas en cola y búsqueda. Está hecha en PHP 7.4 con MySQL, como se escribían muchas aplicaciones hace años, y este repositorio la moderniza por fases.

> Esta versión es el punto de partida: tiene fallas conocidas que se documentan y corrigen en las siguientes versiones. No la uses en producción.

## Cómo correrla

Hace falta Docker con Compose.

```bash
cp .env.example .env
# edita .env y cambia los valores "cambia-esto"
docker compose up -d --build
```

Abre http://localhost:8090 e ingresa con el usuario `admin` y la clave que pusiste en `DEMO_PASSWORD`.

Para apagarla y borrar los datos: `docker compose down -v`.

## Estructura

- `src/`: la aplicación (una pantalla por archivo `.php`, en la raíz web).
- `src/includes/`: conexión a la base, funciones comunes, cabecera y pie.
- `db/`: esquema, datos de ejemplo y creación del bibliotecario `admin`.
- `docker/` y `docker-compose.yml`: imagen de PHP 7.4 con Apache y base MySQL 8.4.
- `tests/characterization/`: pruebas de caracterización (PHP 8.4) con sus snapshots; `docker-compose.test.yml` y `bin/test` las ejecutan.
- `docs/adr/`: decisiones de arquitectura.
- `.github/workflows/ci.yml`: revisión de sintaxis, prueba de arranque y pruebas de caracterización.

## Pruebas

Las pruebas de caracterización entran por HTTP contra la aplicación levantada y revisan el HTML de cada pantalla y el estado de la base. No dependen del código de `src/`, así que siguen sirviendo cuando cambie por dentro. Corren en un contenedor aparte con PHP 8.4, PHPUnit 12 y Guzzle (ver `docs/adr/`).

```bash
cp .env.example .env   # y cambia los valores "cambia-esto"
bin/test --build       # la primera vez; después basta bin/test
docker compose -f docker-compose.yml -f docker-compose.test.yml down -v
```

`bin/test` equivale a `docker compose -f docker-compose.yml -f docker-compose.test.yml run --rm tests` y devuelve el código de salida de PHPUnit.

Qué cubren (138 pruebas, 442 aserciones, unos 20 segundos sin contar la construcción de la imagen):

- Un snapshot por pantalla, con la sesión iniciada, y la pantalla de ingreso.
- Sesión: ingreso, clave mala, pantallas protegidas sin sesión y salida.
- Socios, libros y ejemplares: altas, ediciones, validaciones y bajas.
- Préstamos y devoluciones: vencimiento a 14 días, rechazos, multa por retraso y cola de reservas.
- Reservas, pago de multas y búsqueda.

Para que los resultados no dependan del día, la aplicación acepta la cabecera `X-Fecha-Fija` **solo** cuando corre con `APP_ENV=test`; el CI comprueba que en modo normal se ignora (ADR 0002).

Si una pantalla cambia de forma intencional, se reescriben los snapshots con `UPDATE_SNAPSHOTS=1 bin/test`, se revisa la diferencia con `git diff` y se confirma.

## Fallas conocidas

La aplicación tiene cuatro fallas conocidas. Las pruebas (grupo `known-defect`) las fijan tal como se comportan hoy y se corrigen en próximas versiones.

- **F1, multa mal calculada:** si el préstamo vencido se devuelve en otro mes, los días se calculan restando el día del mes y la multa sale 0 o de menos.
- **F2, cola de reservas:** la siguiente reserva se elige por el nombre del socio y no por orden de llegada, tanto al devolver como al cancelar.
- **F3, inyección SQL en la búsqueda:** `buscar.php` concatena el texto en la consulta; `' OR 1=1 -- ` devuelve todo el catálogo y una comilla muestra el error de MySQL.
- **F4, XSS almacenado:** `socios.php` muestra el nombre del socio sin escapar.
