# Data Model: Mesas redimensionables por celdas en el plano del POS

**Feature**: 040-pos-mesas-redimensionables | **Fecha**: 2026-08-19

Solo se toca una tabla. `pos_zonas` no cambia (conserva `version` para el bloqueo optimista).

---

## Tabla `pos_mesas` (modificada)

### Columnas añadidas

| Columna | Tipo | Nulo | Default | Descripción |
|---------|------|------|---------|-------------|
| `ancho_celdas` | `unsignedTinyInteger` | no | `1` | Celdas de la rejilla que la mesa ocupa horizontalmente, desde `columna` hacia la derecha. Mínimo 1, máximo `PosPlanoCeldas::COLUMNAS` (8). |
| `alto_celdas` | `unsignedTinyInteger` | no | `1` | Celdas que ocupa verticalmente, desde `fila` hacia abajo. Mínimo 1, máximo `PosPlanoCeldas::FILAS` (6). |

Se colocan tras `columna` (`->after('columna')`), junto al resto de atributos de plano.
`unsignedTinyInteger` es suficiente y explícito: el valor nunca podrá superar 8.

### Columna eliminada

| Columna | Motivo |
|---------|--------|
| `tamano` (`enum('pequena','mediana','grande')`) | Sustituida por `ancho_celdas`/`alto_celdas` (FR-010). Solo escalaba píxeles dentro de una celda; mantenerla dejaría dos nociones de tamaño solapadas. |

### Columnas sin cambios (contexto)

| Columna | Papel tras esta feature |
|---------|------------------------|
| `fila`, `columna` | Celda **de origen** del rectángulo (esquina superior izquierda). Su significado cambia sutilmente: antes era "la celda de la mesa", ahora es "desde dónde se extiende". |
| `forma` (`redonda`, `cuadrada`, `rectangular`, `barra`) | Pasa a determinar **solo el aspecto y el reparto de sillas**, ya no el espacio reservado. Una mesa `barra` puede ser 1×1 y una `cuadrada` puede ser 3×2. |
| `zona_id`, `tenant_id` | Sin cambios. El aislamiento sigue por `TenantScope` (Principio I). |

---

## Invariantes de geometría

Válidos para toda mesa activa de una zona, y **verificados en el servidor** en cada guardado
(FR-013), no solo en el cliente:

- **G1 — Ocupación mínima**: `ancho_celdas >= 1` y `alto_celdas >= 1`.
- **G2 — Dentro de la rejilla**: `columna + ancho_celdas <= PosPlanoCeldas::COLUMNAS` y
  `fila + alto_celdas <= PosPlanoCeldas::FILAS`.
- **G3 — Sin solapamiento**: para dos mesas cualesquiera A y B de la misma zona, **no** se cumple
  simultáneamente:
  ```
  A.columna < B.columna + B.ancho_celdas  &&  B.columna < A.columna + A.ancho_celdas  &&
  A.fila    < B.fila    + B.alto_celdas   &&  B.fila    < A.fila    + A.alto_celdas
  ```

G3 es la novedad conceptual: hoy el invariante equivalente es "dos mesas no comparten celda", una
comparación de puntos que las formas alargadas burlaban por diseño.

**Nota**: los invariantes se comprueban sobre el payload completo de la zona en cada guardado, no
como restricción de base de datos. Una restricción SQL no puede expresar G3 y una tabla de celdas
ocupadas sería complejidad desproporcionada (Principio V) para una rejilla de 48 posiciones.

---

## Conversión de datos existentes (migración, D8 de research.md)

Se ejecuta dentro de la propia migración, en tres pasos por zona:

1. **Derivar de `forma`** la ocupación que la mesa *ya aparentaba* tener en pantalla:

   | `forma` | → `ancho_celdas` × `alto_celdas` |
   |---------|----------------------------------|
   | `rectangular` | 2 × 1 |
   | `barra` | 3 × 1 |
   | `redonda`, `cuadrada` | 1 × 1 |

2. **Corregir** recorriendo las mesas de la zona en orden determinista (`fila`, luego `columna`,
   luego `id`): si el rectángulo de una mesa viola G2 o G3 contra las ya asentadas, se **reduce su
   `ancho_celdas`** de uno en uno hasta que cumpla, con suelo en 1. Como 1×1 siempre cumple G3
   (la celda de origen ya era exclusiva antes de la migración) y siempre cumple G2, el proceso
   termina siempre en un estado válido.

3. **Eliminar** la columna `tamano`.

`tamano` **no** interviene en el mapeo: era una escala de píxeles dentro de una misma celda, no una
ocupación, así que traducirla a celdas inventaría espacio que la mesa nunca reservó.

### Reversión (`down()`)

Reintroduce `tamano` con su default (`mediana`) y elimina `ancho_celdas`/`alto_celdas`. **Es una
reversión con pérdida**: el modelo antiguo no puede representar un 3×2. Queda declarado en el
docblock de la migración. Por la regla del proyecto sobre datos de demo, la migración se aplica con
`php artisan migrate`, nunca con `migrate:fresh`.

---

## Modelo `PosMesa`

- `$fillable`: añadir `ancho_celdas`, `alto_celdas`; quitar `tamano`.
- `$casts`: `ancho_celdas => 'integer'`, `alto_celdas => 'integer'`; quitar el cast de `tamano`.

## `PosPlanoCeldas`

`primeraCeldaLibre()` pasa a considerar rectángulos: el mapa de celdas ocupadas se construye
marcando **todas** las celdas de cada mesa (bucle sobre su ancho y alto), no solo su origen. Firma y
contrato de retorno se mantienen — una mesa nueva sigue creándose 1×1, así que basta con encontrar
una celda libre. Si la rejilla está llena sigue devolviendo `null`.

## `PosPlanoReacomodo`

`validar()` deja de indexar una clave `"fila-columna"` por mesa y pasa a marcar todas las celdas de
cada rectángulo, comprobando G1, G2 y G3 en el mismo recorrido. Sigue lanzando `ValidationException`
que aborta la petición entera, sin cambios parciales. Los mensajes de error se adaptan para nombrar
el rectángulo, no la celda.
