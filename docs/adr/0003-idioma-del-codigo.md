# 0003. Idioma del código

Estado: aceptada

## Contexto

La aplicación heredada está escrita en español: archivos, funciones, variables, tablas y mensajes. Al modernizarla se agrega código nuevo (pruebas ahora, dominio y herramientas después) y hay que decidir en qué idioma va.

## Decisión

- **Lo heredado se queda en español**, tal como está. Renombrarlo cambiaría las URLs y las tablas, y esas son parte del contrato con el usuario.
- **Lo nuevo va en inglés:** código de pruebas, nombres de clases, métodos y variables, y comentarios. Los nombres de las pruebas siguen `test_<behavior>`.
- **Lo que ve el usuario sigue en español:** pantallas, mensajes y textos que las pruebas comparan.
- ADR y README van en español.

## Consecuencias

- Durante la modernización convivieron los dos idiomas y se notaba la frontera: lo que vivía en `legacy/` (borrado en el ADR 0008) era español y lo que se escribe nuevo (`src/`, `templates/`) es inglés. Los textos que ve el usuario y los nombres de tablas, columnas, campos de formulario y URLs siguen en español.
- Al pasar el dominio a clases nuevas (en inglés), los nombres de tablas y columnas en español se traducen en una sola capa y no se esparcen por el código.
