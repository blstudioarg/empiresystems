# Data Model: Forma y medidas configurables de la zona en el plano de sala

**Feature**: 042-plano-sala-forma-medidas | **Fecha**: 2026-08-21

## Alcance del cambio

Una sola tabla cambia: **`pos_zonas`**, que gana su lienzo. `pos_mesas` **no cambia**: sigue siendo un
rectángulo de celdas anclado en `fila`/`columna`; lo que cambia es contra qué se valida.

## `pos_zonas` — columnas nuevas

| Columna | Tipo | Default | Descripción |
|---------|------|---------|-------------|
| `columnas` | `TINYINT UNSIGNED` | `8` | Ancho de la rejilla de la zona, en celdas. Entre `PosPlanoCeldas::MIN` (4) y `::MAX` (24). |
| `filas` | `TINYINT UNSIGNED` | `6` | Alto de la rejilla de la zona, en celdas. Mismo rango. |
| `celdas_inactivas` | `JSON` | `[]` | Celdas que **no son suelo**, como array de claves `"fila-columna"`. Normalizado (sin duplicados, ordenado) al guardar. |

Los defaults reproducen exactamente la rejilla fija actual (D2/D10 de [research.md](./research.md)):
ninguna zona existente cambia y la migración no necesita backfill.

**Índices**: ninguno nuevo. `celdas_inactivas` nunca se filtra desde SQL; se lee entera con la zona.

**Modelo `PosZona`**: los tres campos entran en `$fillable`; casts `columnas` → `integer`, `filas` →
`integer`, `celdas_inactivas` → `array`.

## Invariantes de geometría (verificados en servidor, siempre)

Ampliación de los tres invariantes de la feature 040. Se comprueban sobre la geometría **propuesta**
en el payload, no sobre la persistida (D8).

| # | Invariante | Mensaje de rechazo |
|---|-----------|--------------------|
| G1 | `ancho_celdas ≥ 1` y `alto_celdas ≥ 1` | "La mesa {n} debe ocupar al menos una celda." |
| G2 | `columna + ancho ≤ columnas` y `fila + alto ≤ filas`, con `fila ≥ 0`, `columna ≥ 0` | "La mesa {n} se sale de la rejilla de la zona." |
| G3 | Ninguna celda pertenece a dos mesas | "Las mesas {a} y {b} se solapan en el plano." |
| **G4** | Ninguna celda de una mesa está en `celdas_inactivas` | "La mesa {n} está sobre una parte del plano que no es sala." |
| **G5** | `MIN ≤ columnas ≤ MAX`, `MIN ≤ filas ≤ MAX`, y el número de celdas de suelo (`columnas × filas − |celdas_inactivas ∩ rejilla|`) es ≥ 1 | "La zona debe conservar al menos una celda de sala." |

Cualquier fallo rechaza la petición **entera**, sin cambios parciales (FR-014).

## Normalización de `celdas_inactivas` al guardar

1. Se descartan las claves mal formadas (no `"entero-entero"`).
2. Se descartan las celdas **fuera de la rejilla propuesta** — es la regla de "el recorte de las
   celdas que dejan de existir se descarta" del edge case de encoger: si más tarde la zona se
   agranda, esas celdas vuelven como suelo, no como recorte recordado.
3. Se eliminan duplicados y se ordena (fila, columna) para que el JSON persistido sea estable y dos
   guardados equivalentes produzcan el mismo valor.

## Estado derivado (sin cambios)

El estado de una mesa (`libre` / `ocupada` / `olvidada`) sigue derivándose de `pos_cuentas` en el
servidor; esta feature no lo toca. La forma de la sala es geometría, no estado de servicio: una mesa
con cuenta abierta no impide cambiar el lienzo mientras su área siga siendo suelo válido.

## Ciclo de vida

- **Zona nueva**: nace con 8×6 y `[]`. No hay ningún paso de "diseñar la sala" obligatorio.
- **Zona existente**: conserva su lienzo por el default de la migración.
- **Zona borrada** (soft delete): sin cambios; el lienzo se va con ella.
- **Cambio de lienzo**: solo dentro del guardado del plano, con `version` incrementada en la misma
  transacción que las mesas (bloqueo optimista compartido, D8).

## Constantes de referencia

`App\Support\PosPlanoCeldas`:

| Constante | Valor | Notas |
|-----------|-------|-------|
| `COLUMNAS_DEFECTO` | 8 | Antes `COLUMNAS`. Renombrada a propósito (D3): el nombre viejo afirmaba que era *la* rejilla. |
| `FILAS_DEFECTO` | 6 | Antes `FILAS`. |
| `MIN` | 4 | Mínimo por eje. |
| `MAX` | 24 | Máximo por eje; acota el lienzo a 576 celdas. |
