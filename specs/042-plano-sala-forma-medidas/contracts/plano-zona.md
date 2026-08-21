# Contrato: lienzo de la zona (payload de la Sala y guardado del plano)

**Feature**: 042-plano-sala-forma-medidas

Dos contratos existentes se amplían. **Ninguno es nuevo**: no hay endpoints, rutas ni permisos
nuevos.

---

## 1. Estado de la Sala (lectura) — `App\Http\Controllers\Pos\SalaController`

Cada entrada de `zonas` gana su geometría. Es la parte que consumen **las dos** vistas del plano
(editor y servicio), así que viaja para cualquier usuario con acceso a la Sala, tenga o no permiso de
configuración (FR-012).

```jsonc
{
  "zonas": [
    {
      "id": 12,
      "nombre": "Terraza",
      "suplemento": "10.00",
      "total_mesas": 6,
      "version": 4,

      // NUEVO (feature 042)
      "columnas": 12,
      "filas": 8,
      "celdas_inactivas": ["0-10", "0-11", "1-10", "1-11"]
    }
  ],
  "mesas": [ /* sin cambios */ ],
  "umbral_olvidada_min": 20
}
```

**Reglas del contrato**:

- `columnas` y `filas` son enteros entre 4 y 24. Siempre presentes.
- `celdas_inactivas` es un array de cadenas `"fila-columna"`, siempre presente (puede ser vacío) y
  siempre contenido dentro de la rejilla que declaran `columnas`/`filas`.
- El cliente **no** infiere la geometría de las mesas ni de ninguna constante propia: la lee de aquí.

**Test que fija el contrato**: se amplía `tests/Feature/Pos/SalaPayloadPlanoTest.php` (corolario de
`docs/04-front-guidelines.md`, feature 041: cuando una vista pasa a depender de campos del payload,
un test de backend los fija para que un refactor del controlador no la rompa en silencio).

---

## 2. Guardado del plano (escritura) — `PUT|PATCH /pos/sala/zonas/{zona}/plano`

Ruta, nombre (`pos.sala.plano.update`), permisos y bloqueo optimista **sin cambios**. El cuerpo gana
la geometría de la zona, que se persiste en la misma transacción que las mesas (D8).

### Petición

```jsonc
{
  "version": 4,

  // NUEVO (feature 042)
  "columnas": 12,
  "filas": 8,
  "celdas_inactivas": ["0-10", "0-11", "1-10", "1-11"],

  "mesas": [
    { "id": 31, "fila": 0, "columna": 0, "forma": "cuadrada", "ancho_celdas": 2, "alto_celdas": 1 }
  ]
}
```

**Validación de entrada** (`$request->validate`):

| Campo | Reglas |
|-------|--------|
| `version` | `required, integer` |
| `columnas` | `required, integer, min:PosPlanoCeldas::MIN, max:PosPlanoCeldas::MAX` |
| `filas` | `required, integer, min:PosPlanoCeldas::MIN, max:PosPlanoCeldas::MAX` |
| `celdas_inactivas` | `present, array` |
| `celdas_inactivas.*` | `string, regex:/^\d+-\d+$/` |
| `mesas.*.ancho_celdas` | `required, integer, min:1, max:` **`columnas` del payload** (antes: constante global) |
| `mesas.*.alto_celdas` | `required, integer, min:1, max:` **`filas` del payload** |

El resto de reglas de `mesas.*` queda igual.

### Respuestas

| Código | Cuándo | Cuerpo |
|--------|--------|--------|
| `200` | Guardado correcto | `{ "message": "Plano guardado.", "version": 5 }` |
| `409` | `version` no coincide (otro dispositivo guardó antes) | `{ "message": "El plano se modificó desde otro dispositivo. Recárgalo antes de guardar." }` |
| `422` | Falla la validación de entrada o cualquier invariante G1-G5 | `ValidationException` con el mensaje del invariante (ver [data-model.md](../data-model.md)) |
| `403` | Sin el permiso de configuración del plano | — |

**Atomicidad**: un `422` no aplica **ningún** cambio: ni las medidas, ni el recorte, ni una sola
mesa, ni el incremento de `version`.

**Orden de validación**: primero las reglas de entrada, después `version` (409 antes que los
invariantes, como hoy), después G1-G5 sobre la geometría propuesta, y solo entonces la transacción.

---

## 3. Contrato interno del módulo de dibujo (`PosPlanoDibujo`)

No es una API HTTP, pero sí un contrato entre los dos modos de vista, y su ruptura es precisamente lo
que la guía de front prohíbe (feature 041).

- `PosPlanoDibujo` **deja de exponer** `COLS` / `ROWS` como constantes y **deja de escribir**
  `--plano-cols` / `--plano-rows` en `documentElement` al cargar (D4).
- Pasa a exponer funciones que reciben la geometría de la zona activa: `{ columnas, filas, inactivas }`.
- Las variables CSS del lienzo se escriben **en el elemento del lienzo**, al dibujar cada zona.
- El módulo sigue **fuera** del guard `if (!state.puedeEditar) return;` del editor: la geometría es
  contorno compartido, no una capacidad del editor.
