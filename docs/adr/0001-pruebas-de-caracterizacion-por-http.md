# 0001. Pruebas de caracterización por HTTP

Estado: aceptada

## Contexto

La aplicación heredada no tiene pruebas y se va a modernizar por fases: Composer, PDO, capas, PHP 8.4. En cada fase cambia el código por dentro, pero lo que ve el usuario no debe cambiar. Unas pruebas unitarias escritas hoy contra `src/` se romperían con la primera reorganización y no dirían nada sobre las pantallas.

## Decisión

- Las pruebas **solo entran por HTTP** y miran **la base de datos**. No incluyen ni leen nada de `src/`.
- Corren en un **contenedor aparte con PHP 8.4** (PHPUnit 12 y Guzzle), contra la aplicación levantada con Compose. Así el mismo código de pruebas sirve con PHP 7.4 hoy y con PHP 8.4 al final, y no depende de la versión que se esté modernizando.
- Dos tipos de prueba, juntos:
  - **Snapshots** del HTML normalizado de cada pantalla (se quitan comentarios y se colapsan espacios, nada más). Detectan cualquier cambio visible.
  - **Pruebas de reglas** con mensaje en pantalla y estado de la base (préstamos, devoluciones, multas, reservas, búsqueda). Dicen qué regla se rompió.
- **Base limpia por prueba sin reiniciar MySQL:** al arrancar la suite se crea `biblioteca_plantilla` con `db/01-esquema.sql`, `db/02-semilla.sql` y el administrador; antes de cada prueba se copian sus tablas sobre `biblioteca` (`TRUNCATE` e `INSERT … SELECT`). Tarda milisegundos y las pruebas corren en serie.
- Las fallas conocidas (F1 a F4) también tienen prueba: fijan **cómo se comporta hoy**, con el grupo `known-defect`. Cuando una falla se corrija, su prueba cambia en el mismo PR que la corrige.

## Consecuencias

- Cualquier fase que cambie una pantalla o una regla sin querer rompe una prueba.
- Los snapshots se vuelven a escribir con `UPDATE_SNAPSHOTS=1` y hay que revisar el cambio antes de confirmarlo.
- Las pruebas necesitan Docker y una fecha controlable (ver ADR 0002).
- La suite es lenta comparada con una unitaria (unos 20 segundos), pero cubre la aplicación completa.
