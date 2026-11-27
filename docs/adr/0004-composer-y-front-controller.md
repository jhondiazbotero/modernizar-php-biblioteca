# 0004. Composer y front controller

Estado: aceptada

## Contexto

La aplicación heredada son archivos sueltos que Apache sirve directamente: cada pantalla es una URL y las carpetas internas (`includes/`) quedan expuestas en la raíz web. Para modernizarla hace falta un lugar donde vivan las clases nuevas, una forma de cargarlas y una sola puerta de entrada, sin cambiar las URLs ni lo que ve el usuario.

## Decisión

- **Composer sin framework.** Se usa solo para el autoload PSR-4 (`Library\` en `src/`) y para PHPUnit. Un framework obligaría a rehacer pantallas y rutas de golpe; aquí se cambia por capas y cada fase tiene que dejar las pruebas de caracterización en verde.
- **Estructura:** `public/` es la única carpeta que sirve Apache (`index.php` y `css/`); las pantallas heredadas pasan a `legacy/` y el código nuevo vive en `src/`. `legacy/` y `src/` quedan fuera de la raíz web.
- **Lista blanca en vez de enrutar por nombre de archivo.** `Library\Http\LegacyRouter` conoce las 15 pantallas y `/`; cualquier otra ruta es 404. Nunca se arma una ruta de archivo con lo que manda el usuario, así que `/../`, `/includes/conexion.php`, `/legacy/socios.php` o `/socios.php/extra` no llegan a ningún archivo.
- **`chdir` al directorio de la pantalla antes de incluirla.** Los `include 'includes/...'` del legado son relativos; con el directorio de trabajo igual al que tenían cuando Apache ejecutaba cada archivo, las pantallas no se tocan.
- **Apache:** `DocumentRoot` en `public/`; si el archivo pedido existe allí (los estilos) se sirve y todo lo demás va a `index.php`. La regla vive en la configuración del sitio y no en un `.htaccess`.
- **Configuración por entorno:** `DatabaseConfig::fromEnvironment()` exige `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`. Si falta alguna, el front controller escribe en el registro el nombre de la variable (nunca su valor) y el usuario ve una página 500 genérica.
- **Código nuevo compatible con PHP 7.4** hasta la tarea 7 (sin `enum`, `readonly`, `match`, promoción en constructores, tipos unión ni argumentos con nombre). El `composer.json` declara `^7.4 || ^8.4` y fija la plataforma en 7.4.33 para que el `composer.lock` resuelva paquetes que funcionen en ambas.

## Consecuencias

- Las URLs y las pantallas no cambian: las pruebas de caracterización pasan sin tocar un snapshot. Lo único nuevo que se observa es el 404 con página propia para lo que no es una pantalla.
- Agregar una pantalla heredada exige agregarla a la lista; es incómodo, y es lo buscado: así no se abre una ruta sin querer.
- El `chdir` es un estado global que se quita cuando las pantallas dejen de incluir archivos por ruta relativa.
- La imagen de Docker se construye en dos etapas (dependencias de Composer y aplicación) y en desarrollo se montan `public/`, `legacy/` y `src/` por separado para no tapar `vendor/`.
