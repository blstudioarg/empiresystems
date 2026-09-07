# Quickstart: validar la importación conversacional

**Feature**: 046-importacion-conversacional-asistente

Detalles de esquema en [data-model.md](./data-model.md) y de la API en
[contracts/endpoints.md](./contracts/endpoints.md).

## Requisitos previos

- Entorno local levantado en el dominio de un tenant (ver `ACCESOS.local.md`).
- Clave de API del asistente configurada (necesaria para los escenarios con documentos).
- Un `.xlsx` de clientes: sirve la plantilla de `GET /importar/clientes/plantilla`.

> No hace falta `migrate`: esta feature **no añade tablas**. Y sigue prohibido `migrate:fresh`.

---

## Escenario 1 — Importar un Excel hablando (US1)

1. Abrir el asistente y escribir "necesito importar clientes".
2. Adjuntar con el clip un `.xlsx` con 10 clientes, **3 de ellos empresas sin NIF**.

**Esperado**: el asistente responde con los números reales (10 leídos, 7 válidos, 3 rechazados) y
nombra los tres afectados. No debe decir "algunos registros tienen errores": tiene que decir cuáles.

3. Abrir el ojo de la propuesta.

**Esperado**: tabla con todos los campos de los 7 a importar, y arriba el estado del análisis con
origen y recuentos.

4. Confirmar.

**Esperado**: 7 clientes creados, y el mensaje informa de los 3 que quedaron fuera con su motivo.

---

## Escenario 2 — Corregir conversando (US2)

Siguiendo desde el escenario anterior, antes de confirmar:

1. Responder en el chat: "el NIF de Acme es B12345678, los otros dos descártalos".

**Esperado**: análisis actualizado con 8 válidos y 2 descartados, **sin volver a subir el fichero**.

2. Confirmar.

**Esperado**: se importan 8. Comprobar que el que se corrigió tiene el NIF que se dictó y no otro:
aplicar la corrección a la fila equivocada es el fallo más difícil de detectar de esta feature.

---

## Escenario 3 — Material que no es una hoja (US3)

1. Adjuntar una **foto** de un listado de clientes escrito a mano o impreso.

**Esperado**: mismo tipo de análisis. Los campos que no se leen bien llegan **vacíos**, no
inventados: comprobar expresamente que ningún NIF ausente aparece relleno.

2. Adjuntar además un PDF en la misma conversación.

**Esperado**: se acumula en la misma importación, no arranca otra.

3. Adjuntar un documento sin registros (una factura, un folleto).

**Esperado**: lo dice claramente en vez de inventar filas.

---

## Escenario 4 — Aislamiento (SC-005, Principio I)

1. Anotar el token de un material propio.
2. Desde otra persona del mismo tenant, y luego desde otro tenant, pedir el análisis con ese token.

**Esperado**: `404` en ambos casos. Cubierto por tests automatizados (Principio IV: escritos antes de
implementar); el paso manual solo confirma el comportamiento real.

---

## Escenario 5 — Límites y errores (SC-007)

Probar, uno a uno: un fichero de más de 5 MB, uno con más de 2.000 filas, un `.docx`, un Excel
protegido con contraseña, y un intento de importar **facturas**.

**Esperado**: en los cinco casos, una explicación comprensible. Ningún error técnico, ningún 500.

---

## Escenario 6 — Retención (FR-023)

```powershell
# Abandonar una importación: subir material y no confirmar.
php artisan importaciones:purgar
```

**Esperado**: pasado el plazo, el material y el borrador desaparecen. Comprobar que
`storage/app/private/importaciones/` no conserva nada de esa importación.

---

## Escenario 7 — Sugerencias (US4)

1. Abrir el asistente sin conversación previa.

**Esperado**: saludo y sugerencias por categoría, una de ellas de importar.

2. Pulsar una.

**Esperado**: se envía como mensaje.

3. Repetir con un usuario **sin** permiso sobre clientes.

**Esperado**: no se le ofrecen las sugerencias que no podría ejecutar.

---

## Tests automatizados

```powershell
php artisan test tests/Feature/Asistente
php artisan test tests/Unit/BorradorImportacionTest.php tests/Unit/InterpretadorMaterialTest.php
php artisan test tests/Feature/ImportacionExcelTest.php
```

Los de la feature 031 deben seguir en verde **sin tocarlos**: si el refactor de la costura por filas
exige cambiarlos, es que cambió comportamiento y no solo estructura.
