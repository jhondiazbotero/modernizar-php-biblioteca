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

- `public/`: la única carpeta que sirve Apache: `index.php` (front controller) y `css/`. Las URLs son las de siempre (`/socios.php?...`).
- `legacy/`: las 15 pantallas heredadas (una por archivo `.php`) y `legacy/includes/` (conexión a la base, funciones comunes, cabecera y pie, `servicios.php` que arma los casos de uso y `mensajes.php` con los textos de error). No se sirven directamente.
- `src/`: código nuevo, en inglés, con autoload PSR-4 (`Library\`), por capas (ADR 0007):
  - `Domain/`: reglas de préstamo, multa, cola de reservas y elegibilidad, y la interfaz `Clock`. No depende de nada.
  - `Application/`: casos de uso (prestar, devolver, reservar, cancelar, pagar multa) con transacción, y los puertos (`Application/Port/`).
  - `Infrastructure/`: repositorios PDO, gestor de transacciones y el reloj de la petición.
  - `Http/`, `Config/`, `Database/`, `Auth/`: enrutador con lista blanca, configuración por variables de entorno y páginas de error (ADR 0004), conexión PDO (ADR 0005) y verificación de contraseñas (ADR 0006).
- `db/`: esquema, datos de ejemplo, creación del bibliotecario `admin` y `migraciones/` para bases que ya existen.
- `docker/` y `docker-compose.yml`: imagen de PHP 7.4 con Apache y `pdo_mysql` (multi-etapa, con `vendor/` de Composer) y base MySQL 8.4.
- `tests/unit/`, `tests/integration/` y `tests/characterization/`: pruebas unitarias del código nuevo (PHPUnit 9.6, PHP 7.4), pruebas de integración contra MySQL real (PHPUnit 9.6, PHP 7.4) y pruebas de caracterización (PHP 8.4) con sus snapshots.
- `docs/adr/`: decisiones de arquitectura.
- `.github/workflows/ci.yml`: pruebas unitarias, de integración y de caracterización, revisión de sintaxis y prueba de arranque.

## Fases

| Fase | Qué | Estado |
|---|---|---|
| T1 | Aplicación heredada en PHP 7.4 | hecha |
| T2 | Pruebas de caracterización (`v0.1.0`) | hecha |
| T3 | Composer, PSR-4, front controller y configuración por entorno | hecha |
| T4 | PDO con consultas preparadas y contraseñas con `password_hash` (`v0.2.0`) | hecha |
| **T5** | **Dominio y casos de uso con transacciones (primera parte: reglas y escrituras); controladores y plantillas, después** | **esta rama** |
| T6 | Arreglo de las fallas conocidas | pendiente |
| T7 | PHP 8.4 | pendiente |
| T8 | Calidad: análisis estático, estilo y mutación | pendiente |
| T9 | Documentación y métricas | pendiente |
| T10 | Revisión final y `v1.0.0` | pendiente |

## Pruebas

Las pruebas de caracterización entran por HTTP contra la aplicación levantada y revisan el HTML de cada pantalla y el estado de la base. No dependen del código de la aplicación, así que siguen sirviendo cuando cambie por dentro. Corren en un contenedor aparte con PHP 8.4, PHPUnit 12 y Guzzle (ver `docs/adr/`).

```bash
cp .env.example .env   # y cambia los valores "cambia-esto"
bin/test --build       # la primera vez; después basta bin/test
docker compose -f docker-compose.yml -f docker-compose.test.yml down -v
```

`bin/test` equivale a `docker compose -f docker-compose.yml -f docker-compose.test.yml run --rm tests` y devuelve el código de salida de PHPUnit.

Qué cubren (152 pruebas, 488 aserciones, unos 30 segundos sin contar la construcción de la imagen):

- Un snapshot por pantalla, con la sesión iniciada, y la pantalla de ingreso.
- Sesión: ingreso, clave mala, pantallas protegidas sin sesión y salida; la cuenta con MD5 que pasa a `password_hash` al entrar y el identificador de sesión que cambia.
- Socios, libros y ejemplares: altas, ediciones, validaciones y bajas.
- Préstamos y devoluciones: vencimiento a 14 días, rechazos, multa por retraso y cola de reservas.
- Reservas, pago de multas y búsqueda (el texto con comillas, `%`, `_` y `\` se busca como texto).
- Front controller: lo que no es una pantalla (`/no-existe.php`, `/legacy/socios.php`) responde 404, y una cabecera `X-Fecha-Fija` inválida se ignora.

Para que los resultados no dependan del día, la aplicación acepta la cabecera `X-Fecha-Fija` **solo** cuando corre con `APP_ENV=test`; el CI comprueba que en modo normal se ignora (ADR 0002).

Si una pantalla cambia de forma intencional, se reescriben los snapshots con `UPDATE_SNAPSHOTS=1 bin/test`, se revisa la diferencia con `git diff` y se confirma.

### Pruebas unitarias

Cubren el código nuevo de `src/` (dominio, casos de uso con repositorios en memoria, reloj, enrutador, configuración, conexión y contraseñas, y la prueba de arquitectura de las capas) y corren con PHP 7.4, la versión de la aplicación hasta la tarea 7. Son 173 pruebas, 391 aserciones:

```bash
bin/unit                      # instala vendor/ con Composer si falta y corre PHPUnit 9.6
bin/unit --filter FinePolicy
```

### Pruebas de integración

Corren los repositorios PDO y las transacciones contra un MySQL real (el del compose, en una base propia que crean y borran) con PHP 7.4. Entre otras cosas, fuerzan un fallo después del primer `UPDATE` de una devolución y comprueban por SQL que no quedó nada a medias. Son 15 pruebas, 90 aserciones:

```bash
cp .env.example .env   # y cambia los valores "cambia-esto"
bin/integration --build
docker compose -f docker-compose.yml -f docker-compose.test.yml down -v
```

## Fallas conocidas

La aplicación tuvo cuatro fallas conocidas. Las pruebas de las que siguen abiertas (grupo `known-defect`) las fijan tal como se comportan hoy y se corrigen en próximas versiones.

- **F1, multa mal calculada** (se conserva en `FinePolicy`): si el préstamo vencido se devuelve en otro mes, los días se calculan restando el día del mes y la multa sale 0 o de menos.
- **F2, cola de reservas** (se conserva en `ReservationQueue`): la siguiente reserva se elige por el nombre del socio y no por orden de llegada, tanto al devolver como al cancelar.
- **F3, inyección SQL en la búsqueda: corregida en v0.2.0.** `buscar.php` concatenaba el texto en la consulta; `' OR 1=1 -- ` devolvía todo el catálogo y una comilla mostraba el error de MySQL. La corrigió el paso a consultas preparadas con PDO (ADR 0005): el texto se busca tal cual, con `%`, `_` y `\` como texto.
- **F4, XSS almacenado:** `socios.php` muestra el nombre del socio sin escapar.

## Actualizar una base que ya existe

Las bases creadas antes de `v0.2.0` guardan la clave de los bibliotecarios en una columna de 32 caracteres. Antes de usar este código hay que ampliarla (solo cambia el tipo de la columna; las claves siguen valiendo y se convierten a `password_hash` la primera vez que cada persona entra):

```bash
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < db/migraciones/2026-11-27-01-clave-para-password-hash.sql
```

Las instalaciones nuevas ya traen la columna ampliada (`db/01-esquema.sql`). Las cuentas con MD5 que nunca vuelvan a entrar siguen en MD5 (ADR 0006).
