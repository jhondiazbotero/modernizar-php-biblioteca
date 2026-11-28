# 0009. Corrección de F1: multa por días reales de retraso

Estado: aceptada

## Contexto

`FinePolicy` calculaba los días de retraso restando el **día del mes** de la devolución y el del vencimiento (`(int) $returned->format('j') - (int) $due->format('j')`). Mientras los dos caían en el mismo mes daba lo correcto, pero al cruzar de mes fallaba:

- Vencía el 28 de marzo y se devolvía el 3 de abril (6 días tarde): `3 − 28` es negativo y la multa salía 0.
- Vencía el 10 de octubre y se devolvía el 27 de noviembre (48 días tarde): `27 − 10 = 17` y se cobraban 17 días.
- Vencía el 31 de diciembre y se devolvía el 2 de enero: 0.

La falla venía del legado y se conservó tal cual en el dominio (ADR 0007) mientras la tarea 5 no podía cambiar comportamiento. Las pruebas la fijaban con los nombres `test_f1_…` y el grupo `known-defect`.

## Decisión

- **Regla.** Si el préstamo se devuelve después del vencimiento, la multa es el número de **días calendario** entre la fecha de vencimiento y la de devolución por `LendingRules::FINE_PER_DAY` (500). Si se devuelve el día del vencimiento o antes, es 0.
- **Cálculo.** `FinePolicy::fineFor()` pone las dos fechas a medianoche (`setTime(0, 0, 0)`) y usa `diff()->days`. La hora del día no cuenta y un cambio de horario de verano no suma ni resta un día (hay pruebas con `America/New_York`).
- **Cómo se probó.** Las pruebas se escribieron antes que el arreglo y se vieron fallar con el código anterior: las `test_f1_*` de `FinePolicyTest` se cambiaron por las de la regla correcta (28-mar → 3-abr = 6 días = $ 3.000; 31-dic → 2-ene = 2 días; 31-ene → 1-mar sin año bisiesto = 29 días; 28-feb-2028 → 1-mar-2028 = 2 días; 16-nov → 12-dic = 26 días), y lo mismo en `ReturnLoanTest` y en las dos pruebas de caracterización por HTTP, que ya no pertenecen al grupo `known-defect`. Los resultados de cada rojo están en `PROGRESO.md`.
- **Datos ya guardados.** Las multas cobradas con el cálculo anterior **no se recalculan solas**. `db/consultas/multas-cruce-de-mes.sql` es una consulta de solo lectura que lista los préstamos devueltos con retraso cuya multa guardada no coincide con la regla correcta (préstamo, socio, vencimiento, devolución, multa guardada y multa correcta). Una prueba de integración la corre contra la semilla y espera exactamente el préstamo 12 (vence el 28 de marzo, devuelto el 3 de abril, multa 0, correcta $ 3.000), y comprueba que no cambia ninguna tabla.

## Consecuencias

- Un préstamo devuelto tarde paga lo mismo sin importar en qué mes caiga la devolución.
- Las multas que ya se cobraron de menos (o de más) siguen como estaban. **No hay un `UPDATE` automático** porque una multa es dinero que ya se cobró, se pagó o se perdonó: corregirla en silencio cambiaría deudas de socios, y las que ya están pagadas (`multa_pagada = 1`) no se pueden «ajustar» sin devolver o cobrar la diferencia. La biblioteca revisa la lista y decide caso por caso (cobrar la diferencia, perdonarla o dejarla). La consulta es repetible: cuando se corrige una fila, deja de aparecer.
- La consulta repite el valor de la multa diaria (500) en su primera línea; si `LendingRules::FINE_PER_DAY` cambia, hay que cambiarlo también allí.
- La pantalla de multas y el préstamo del socio muestran el valor nuevo sin cambios en las plantillas; los snapshots de caracterización no cambian porque la semilla no tiene devoluciones nuevas.
