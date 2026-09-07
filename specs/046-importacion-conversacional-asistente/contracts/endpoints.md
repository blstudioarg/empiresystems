# Contratos: Importación conversacional con el asistente

**Feature**: 046-importacion-conversacional-asistente

Regla transversal: todo token se resuelve acotado a la **empresa activa y a la persona autenticada**.
Un token ajeno responde `404`, nunca `403`: un 403 confirmaría que existe (mismo criterio que el
historial de la 045 y los documentos de compra de la 044).

---

## 1. `POST /asistente/material`

Sube material para una importación en curso. Va aparte del endpoint de mensaje porque ese es SSE por
streaming y meterle un `multipart` con un PDF de 5 MB complica el flujo sin ganar nada (research D5).

**Petición** — `multipart/form-data`

| Campo | Reglas |
|---|---|
| `fichero` | `required`, `file`, tipos de `config/importacion.php`, `max:5120` (5 MB) |
| `modulo` | `required`, uno de `clientes`, `articulos`, `proveedores` |
| `token` | opcional: si se pasa, el material se **acumula** en esa importación (US3, escenario 4) |

**Respuesta 200**

```json
{
  "token": "9f2c1e40-…",
  "modulo": "clientes",
  "origen": "documento",
  "nombre": "listado-clientes.pdf"
}
```

Subir **no analiza**: solo deja el material disponible. El análisis lo dispara el asistente, para que
el usuario vea el progreso en la conversación.

**Errores**

| Código | Caso |
|---|---|
| `403` | Sin permiso sobre el módulo (el mismo `can:ver-{modulo}` que la importación existente) |
| `404` | Token ajeno o inexistente; módulo no importable |
| `422` | Tipo no admitido, mayor de 5 MB, fichero vacío o ilegible — **siempre con explicación, nunca un 500** |

---

## 2. `DELETE /asistente/material/{token}`

Descarta una importación en curso y borra su material.

**Respuesta 200**: `{ "ok": true }` · **404** si el token no es de quien lo pide.

---

## 3. Tool `analizar_material_importable` *(lectura)*

Analiza el material y devuelve al modelo el estado real. **Los recuentos salen del análisis, nunca
del modelo** (research D6).

**Entrada**: `{ "token": "9f2c1e40-…" }`

**Salida**

```json
{
  "modulo": "clientes",
  "origen": "documento",
  "leidas": 10,
  "validas": 7,
  "descartadas": 0,
  "rechazadas": [
    { "indice": 3, "identificador": "Acme", "motivo": "El NIF es obligatorio para una empresa.", "campo": "nif" },
    { "indice": 5, "identificador": "Beta", "motivo": "El NIF es obligatorio para una empresa.", "campo": "nif" }
  ],
  "no_interpretado": ["La página 3 no se pudo leer."]
}
```

`identificador` y `campo` son lo que permite al asistente decir *"a Acme y Beta les falta el NIF,
¿me los das?"* en vez de listar números de fila. `no_interpretado` solo aparece con `origen:
documento`.

---

## 4. Tool `corregir_filas_importables` *(lectura: muta el borrador, no la base de datos)*

Aplica lo que la persona dictó. No escribe nada en la base de datos, por eso no necesita
confirmación: el efecto es sobre un borrador efímero.

**Entrada**

```json
{
  "token": "9f2c1e40-…",
  "correcciones": [ { "indice": 3, "campo": "nif", "valor": "B12345678" } ],
  "descartar": [5, 7]
}
```

**Salida**: el mismo análisis actualizado que la tool anterior (FR-014).

**Invariante**: una corrección sobre un índice inexistente **falla explícitamente**, no se ignora en
silencio. Aplicar la corrección a la fila equivocada, o darla por aplicada sin estarlo, es el error
más difícil de detectar de esta feature.

---

## 5. Tool `importar_material` *(escritura — requiere confirmación)*

Propone la importación. Al ser escritura pasa por la tarjeta de confirmación del asistente.

**Entrada**: `{ "token": "9f2c1e40-…" }`

**Propuesta** (lo que ve la persona): resumen del tipo *"Importar 8 clientes"*, con el **ojo al
detalle** que abre la tabla de todos los campos (feature 045), y en la caja de estado del modal el
resultado del análisis: origen, leídas, válidas, descartadas y lo no interpretado — que es
exactamente para lo que esa caja se dejó preparada.

**Al confirmar**:

1. Se **revalidan** las filas contra la base de datos en ese momento (FR-019): entre el análisis y la
   confirmación, otra persona pudo crear un registro que ahora colisiona.
2. Se comprueba de nuevo el permiso sobre el módulo (FR-021).
3. Se importan las válidas y se **reportan las rechazadas con su motivo**, sin abortar (FR-018).
4. Se borra el material y el borrador.
5. Se registra en el historial de actividad (FR-022).

**Respuesta**: la misma forma que cualquier confirmación del asistente, con `hechas` y `rechazadas`.

---

## 6. `GET /asistente/sugerencias`

Sugerencias del estado vacío, agrupadas por categoría y **filtradas por los permisos** de la persona.

**Respuesta 200**

```json
{
  "categorias": [
    { "clave": "importar", "etiqueta": "Importar",
      "sugerencias": ["Necesito importar clientes desde un Excel", "Tengo un PDF con proveedores"] },
    { "clave": "consultar", "etiqueta": "Consultar",
      "sugerencias": ["¿Cuántas facturas emití este mes?", "¿Qué clientes me deben dinero?"] }
  ]
}
```

No se ofrece nada que la persona no pudiera ejecutar: filtrar en servidor es lo mismo que ya hace
`CatalogoTools::paraUsuario`, y evita una sugerencia que al pulsarla responda "no tenés permiso".
