# Data Model: Importación conversacional con el asistente

**Feature**: 046-importacion-conversacional-asistente | **Fecha**: 2026-09-07

## Sin tablas nuevas

Una importación en curso es **efímera por diseño**: vive mientras dura la conversación y desaparece
al confirmarse, descartarse o caducar. Persistirla en base de datos obligaría a un plazo de retención
y una purga propios para un dato que no aporta nada una vez terminado el proceso —justo lo contrario
del principio de minimización.

Se reutiliza el almacén de ficheros de importación de la feature 031, y con él la purga
`importaciones:purgar` que ya corre a diario.

---

## Borrador de importación (fichero, no tabla)

Guardado junto al material en `storage/app/private/importaciones/`, identificado por el mismo token.

| Campo | Tipo | Notas |
|---|---|---|
| `token` | uuid | identifica la importación en curso; es lo único que viaja al cliente |
| `tenant_id` | int | empresa propietaria (Principio I) |
| `user_id` | int | persona que aportó el material: el borrador es suyo, no de la empresa |
| `conversacion_id` | int\|null | hilo en el que se está trabajando (FR: no arrastrar a otro hilo) |
| `modulo` | string | `clientes`, `articulos` o `proveedores` |
| `origen` | string | `hoja` (Excel/CSV) o `documento` (PDF, imagen, texto) |
| `filas` | array | filas de trabajo, ya normalizadas a las claves de `ColumnaExcel` |
| `descartadas` | int[] | índices que la persona decidió dejar fuera (FR-013) |
| `correcciones` | array | traza de lo aplicado, para poder explicar qué se cambió |
| `analisis_origen` | array\|null | qué se leyó y qué no del documento interpretado |
| `creado_en` | timestamp | base de la caducidad |

**Por qué se guardan las filas y no solo el original**: la corrección conversacional muta filas. Sobre
un `.xlsx` no se puede aplicar "el NIF de Acme es B12345678" sin reescribir el fichero; y el material
interpretado nunca fue un fichero tabular. Guardar el borrador además evita reinterpretar el documento
—y volver a pagarlo— en cada ronda de corrección (research D4).

**Retención**: la misma que los ficheros de importación existentes. Una importación abandonada
desaparece con la purga diaria sin intervención.

---

## Estructura de una fila de trabajo

```
{
  "indice": 3,
  "datos": { "tipo": "empresa", "nombre": "Acme", "nif": null, ... },
  "leido": { "nif": false },
  "estado": "rechazada",
  "motivo": "El NIF es obligatorio para una empresa."
}
```

- `datos` usa **las claves internas de `ColumnaExcel`**, las mismas que esperan `validador()` y
  `crear()`. Así el borrador entra en el pipeline existente sin traducción.
- `leido` solo aparece en material interpretado: distingue "el documento no lo dice" de "el documento
  dice que está vacío". Es lo que permite cumplir FR-009 sin inventar nada.
- `estado` y `motivo` **no se guardan como verdad**: se recalculan en cada análisis. Guardarlos sería
  arriesgarse a mostrar un veredicto viejo.

---

## Qué NO se guarda

- **El documento original, más allá de lo necesario**: una vez interpretado y con las filas en el
  borrador, deja de hacer falta. Conservarlo sería guardar datos personales de terceros sin motivo.
- **El resultado crudo de la interpretación**: se traduce a filas y se descarta.
- **Nada en la conversación del asistente**: los mensajes guardan el diálogo, no el material.

---

## Configuración

| Clave | Fichero | Default | Descripción |
|---|---|---|---|
| `importacion.material.max_paginas` | `config/importacion.php` | 20 | tope de páginas/imágenes por importación interpretada (research D9) |
| `importacion.material.tipos` | `config/importacion.php` | xlsx, xls, csv, txt, pdf, jpg, png, webp | tipos admitidos |

El tope de **2.000 filas** y los **5 MB** de la feature 031 siguen vigentes y no se duplican aquí: los
aplica el importador.

---

## Cambios en documentación existente (FR-028)

- `docs/03-modelo-datos.md`: añadir que el asistente puede aportar material de importación, que vive
  en el almacén de importaciones y se purga con él. Sin tablas nuevas, pero el dato personal existe.
- `docs/01-arquitectura.md`: nueva decisión sobre reutilizar el pipeline de la 031 en vez de abrir un
  segundo camino de escritura.
- `docs/04-front-guidelines.md`: el clip de material y los chips de sugerencias del estado vacío.
- `resources/views/ayuda/importar-exportar.blade.php`: la pantalla de importación ya no es la única
  vía; contar que el asistente también puede hacerlo.
- `resources/ia/conocimiento/importacion-exportacion.md` y `asistente.md`: el asistente debe saber
  explicar esta capacidad y sus límites (qué módulos, qué formatos, qué no inventa).
