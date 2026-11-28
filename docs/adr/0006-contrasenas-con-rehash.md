# 0006. Contraseñas con rehash transparente

Estado: aceptada

## Contexto

Las claves de los bibliotecarios se guardaban con MD5 sin sal: rápido de calcular, igual para claves iguales y con tablas de resultados públicas. Pedirle a todos que cambien su clave de golpe no es razonable, y no hay forma de convertir un MD5 en otro hash sin conocer la clave.

## Decisión

- **Rehash en el ingreso.** `Library\Auth\PasswordVerifier` verifica la clave contra lo guardado: si son exactamente 32 caracteres hexadecimales es un MD5 viejo y se compara con `hash_equals(md5($clave), strtolower($guardado))`; si no, con `password_verify`. Cuando el ingreso es correcto y `needsRehash()` dice que lo guardado es un MD5 o un hash con otro algoritmo o costo, `login.php` guarda el hash nuevo (`password_hash` con `PASSWORD_DEFAULT`) con un `UPDATE … WHERE id = ?`. Nadie tiene que hacer nada y cada cuenta se pone al día sola la primera vez que entra.
- **No se fuerza ningún cambio de clave.** Las claves siguen siendo las mismas; solo cambia cómo se guardan.
- **Tiempo constante.** La comparación de MD5 usa `hash_equals`. Si el usuario no existe, `login.php` igual corre un `password_verify` contra un hash fijo (`PasswordVerifier::DUMMY_HASH`, de un valor al azar que nadie conoce) para que la respuesta no tarde menos cuando el usuario no existe. Una prueba unitaria exige que el hash ficticio tenga el mismo costo que `PASSWORD_DEFAULT`: si al pasar a PHP 8.4 (costo 12 en vez de 10) cambia el valor por defecto, esa prueba falla y hay que regenerar el hash.
- **Sesión nueva al entrar.** Tras un ingreso correcto se llama a `session_regenerate_id(true)`, así un identificador de sesión que alguien haya fijado antes no sirve después de autenticarse.
- **Base de datos.** La columna `bibliotecarios.clave` pasa de `CHAR(32)` a `VARCHAR(255)` (`db/migraciones/2026-11-27-01-clave-para-password-hash.sql` para bases existentes y el mismo cambio en `db/01-esquema.sql` para instalaciones nuevas). El administrador de la plantilla y de `db/03-admin.sh` **sigue naciendo con MD5** para que se ejercite el camino de las cuentas viejas.
- Mensajes y redirecciones del ingreso no cambian.

## Consecuencias

- **La migración hay que aplicarla antes de desplegar este código** en una base que ya existe: un hash de 60 caracteres no cabe en `CHAR(32)` y, con el modo estricto de MySQL 8, el primer ingreso de una cuenta vieja falla con un 500.
- **Las cuentas MD5 que nunca vuelvan a entrar siguen en MD5.** Es el riesgo que se acepta: si alguien se lleva la base, esas claves son las más fáciles de romper. Cómo cerrarlo más adelante: avisar o desactivar las cuentas que no entren en un plazo, o pedir cambio de clave solo a esas cuentas, y al final borrar la rama de MD5 de `PasswordVerifier`.
- El tiempo de respuesta todavía distingue una cuenta MD5 (rápida) de una con hash moderno (lenta). Deja de importar cuando no queden cuentas MD5.
- `PASSWORD_DEFAULT` es bcrypt hoy. Si PHP cambia el algoritmo o el costo por defecto, `needsRehash()` lo detecta y las cuentas se actualizan en su próximo ingreso.
