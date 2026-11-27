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
- `.github/workflows/ci.yml`: revisión de sintaxis y prueba de arranque.
