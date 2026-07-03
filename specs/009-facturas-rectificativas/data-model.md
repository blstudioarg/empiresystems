# Data Model: Facturas rectificativas

Fase 1 — cambios de datos. Todo se apoya en tablas existentes (`facturas`, `series`,
`factura_lineas`, `factura_impuestos`, `factura_eventos`); esta feature **añade columnas** a
`facturas` y **siembra** una serie rectificativa. Alineado con `docs/03-modelo-datos.md`.

## 1. Columnas nuevas en `facturas` (migración aditiva)

| Campo | Tipo | Notas |
|-------|------|-------|
| `es_rectificativa` | boolean, default `false` | marca la factura como rectificativa |
| `factura_rectificada_id` | fk nullable → `facturas.id` | la factura original que corrige (1:1) |
| `motivo_rectificacion` | text nullable | motivo obligatorio al crear una rectificativa |
| `tipo_rectificacion` | string nullable | enum `sustitucion` \| `diferencias` |

- Índice: `(tenant_id, factura_rectificada_id)` para localizar la rectificativa de una original.
- Las columnas quedan **nulas/false** en todas las facturas ordinarias existentes (migración segura).
- Reutilizan las columnas ya presentes de cabecera para totales (`base_total`, `cuota_impuesto_total`,
  `cuota_recargo_total`, `irpf_cuota`, `total`) que pueden ser **negativas** en modalidad diferencias.

## 2. Enum nuevo

- `App\Enums\TipoRectificacion`: `Sustitucion = 'sustitucion'`, `Diferencias = 'diferencias'`.
- `App\Enums\TipoFactura`: ya tiene `Rectificativa` (sin cambios).
- `App\Enums\EstadoFactura`: ya tiene `Rectificada` y `Emitida` (sin cambios).

## 3. Serie rectificativa (sembrada)

- Fila en `series` con `tipo = rectificativa`, `codigo = 'R'`, `formato = '{serie}-{anio}-{numero:0000}'`,
  `activa = true`, contador por año natural (mismo mecanismo que la ordinaria).
- Una serie rectificativa por defecto por tenant (CRUD de series fuera de alcance).
- Sembrada por `SerieSeeder` (demo) y por los factories/seeders de test junto a la ordinaria.

## 4. Relaciones (modelo `Factura`)

- `facturaRectificada(): BelongsTo` → la original (`factura_rectificada_id`).
- `rectificativa(): HasOne` → la rectificativa que corrige a esta factura (inversa).
- `serie(): BelongsTo` (existente) — apunta a la serie rectificativa cuando `es_rectificativa`.
- `eventos(): HasMany` (existente) — registra "emitida" (rectificativa) y "rectificada" (original).

## 5. Transiciones de estado

```text
Factura ordinaria:  emitida ──(se emite su rectificativa)──▶ rectificada  [terminal, inmutable]

Rectificativa:      (no existe) ──rectificar()──▶ borrador ──emitir()──▶ emitida  [inmutable]
```

Reglas:

- **Crear rectificativa**: la original DEBE estar en `emitida`. Si está en `borrador`, `anulada` o
  `rectificada`, se rechaza. Resultado: nueva factura `borrador`, `tipo = rectificativa`.
- **Emitir rectificativa**: la rectificativa DEBE estar en `borrador`. Al emitir: número de la serie
  rectificativa, `fecha_expedicion = hoy`, congelado, `estado = emitida`, evento "emitida", y **la
  original pasa a `rectificada`** — todo en una transacción.
- **Unicidad**: una original solo puede tener una rectificativa; `rectificada` es terminal (no vuelve
  a `emitida`, no se re-rectifica).

## 6. Reglas de validación

- Crear: `factura_rectificada_id` existe y pertenece al tenant activo; original en `emitida`;
  `tipo_rectificacion ∈ {sustitucion, diferencias}`; `motivo_rectificacion` no vacío.
- Emitir (rectificativa): estado `borrador`; datos fiscales mínimos del receptor presentes; **NO** se
  exige `base_total > 0` (delta puede ser 0 o negativo).
- Cálculo del delta (diferencias): `total_rectificativa = total_corregido − total_original` a nivel de
  totales y de desglose por tipo impositivo; calculado en backend (`CalculadoraFactura` + resta).

## 7. Importes por modalidad

| Modalidad | Líneas persistidas | Totales de cabecera persistidos |
|-----------|--------------------|---------------------------------|
| Sustitución | corregidas completas | totales corregidos (suma de líneas) |
| Diferencias | corregidas (referencia) | **delta** = corregido − original (puede ser negativo/cero) |
