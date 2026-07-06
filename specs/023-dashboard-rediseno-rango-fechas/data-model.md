# Data Model: Rediseño del Dashboard con filtro por rango de fechas

> Feature de solo lectura: **no hay tablas ni migraciones nuevas**. Se documentan (a) el value object
> nuevo, (b) las estructuras de salida del servicio y (c) las fuentes de datos existentes que se
> consultan.

## Value Object nuevo

### `App\Support\RangoFechas`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `desde` | `Carbon` (date) | Inicio del periodo (inclusive). |
| `hasta` | `Carbon` (date) | Fin del periodo (inclusive). Para presets "en curso" = hoy. |
| `preset` | `PresetRango` (enum) | `mes`, `trimestre`, `anio`, `personalizado`. |

**Enum `App\Enums\PresetRango`**: `Mes = 'mes'`, `Trimestre = 'trimestre'`, `Anio = 'anio'`,
`Personalizado = 'personalizado'`.

**Constructores estáticos**:
- `mesEnCurso(?Carbon $hoy = null): self` — `startOfMonth()` → `hoy`.
- `trimestreEnCurso(?Carbon $hoy = null): self` — `firstOfQuarter()` → `hoy`.
- `anioEnCurso(?Carbon $hoy = null): self` — `startOfYear()` → `hoy`.
- `personalizado(Carbon $desde, Carbon $hasta): self`.
- `desdePeticion(array $filtros, ?Carbon $hoy = null): self` — mapea el request validado; fallback a
  `mesEnCurso()` si falta o es inválido.

**Métodos**:
- `anterior(): self` — periodo inmediatamente anterior de igual nº de días, terminando el día antes de
  `desde`. Preset del anterior = `personalizado` (solo se usa para comparar magnitudes).
- `dias(): int` — nº de días inclusive del rango (para elegir granularidad de la serie).
- `granularidad(): 'dia'|'mes'` — `'dia'` si `dias() <= 62`, si no `'mes'`.
- `contiene(Carbon $fecha): bool` — helper para tests.

**Reglas de validación** (aplicadas en `DashboardFiltroRequest`, no en el VO):
- `preset` ∈ enum.
- Si `preset = personalizado`: `desde` y `hasta` fechas válidas; `hasta >= desde`.
- Rango inválido → el controller usa `mesEnCurso()` + aviso `warning`.

## Estructura de salida de `DashboardEstadisticas::resumen(RangoFechas $rango): array`

Claves (las **nuevas / modificadas** marcadas):

| Clave | Filtrada por rango | Notas |
|-------|--------------------|-------|
| `rango` | — | Eco del rango aplicado: `{ preset, desde, hasta, etiqueta }` para pintar el selector. **NUEVO** |
| `kpis.facturado` | ✅ | **MODIF**: facturado **neto** del rango (antes `facturado_mes`). Con `valor` y `variacion_pct` vs. periodo anterior. |
| `kpis.cobrado` | ✅ | Cobrado del rango (por `pagos.fecha`). |
| `kpis.pendiente_cobro` | ❌ (a hoy) | Saldo vivo total. Marcado "a día de hoy". |
| `kpis.num_facturas` | ✅ | Nº de facturas facturables emitidas en el rango. |
| `kpis.gastos` | ✅ | **NUEVO**: total de compras confirmadas del rango. |
| `kpis.resultado` | ✅ | **NUEVO**: facturado neto − gastos. |
| `kpis.ventas_pos` | ✅ | **NUEVO**: total simplificadas del rango (métrica separada). |
| `impuestos` | ✅ | **NUEVO**: `{ repercutido, soportado, etiqueta }` (etiqueta = IVA/IGIC/IPSI según régimen). |
| `serie_facturacion` | ✅ | **MODIF**: serie de facturado neto con granularidad adaptativa (día/mes). Antes `serie_facturacion_12_meses` fijo. |
| `comparativo` | ✅ | **MODIF**: facturado neto vs. cobrado por sub-periodo del rango. Antes `comparativo_6_meses`. |
| `distribucion_estados` | ✅ (por rango) | Conteo por estado de facturas del rango. |
| `top_clientes` | ✅ | **MODIF**: ranking por facturado **neto** del rango (no `SUM(total)`). |
| `alertas_stock` | ❌ (a hoy) | Stock actual bajo mínimo. Sin cambios. |
| `facturas_recientes` | ✅ | Últimas facturas del rango (máx. 8). |

> El renombrado de claves (`facturado_mes` → `kpis.facturado`, etc.) obliga a actualizar la vista y los
> tests. Se asume que ninguna otra parte del sistema consume esta estructura (solo `dashboard.blade.php`).

## Fuentes de datos (tablas existentes consultadas)

### `facturas` (facturación, IVA repercutido, top clientes, distribución, recientes)
- Filtro base: `tipo != 'simplificada'`, `tenant` (global scope).
- **Facturable** (para facturado/IVA/top): `estado ∈ {emitida, pagada, vencida, rectificada}` **y**
  `es_rectificativa = false`. Importe = `totalCobrable()`; IVA repercutido neto = desglose neteado.
- Campos usados: `estado`, `tipo`, `es_rectificativa`, `tipo_rectificacion`, `total`,
  `cuota_impuesto_total`, `fecha_expedicion`, `cliente_id`, `cliente_razon_social`, `cliente_nombre`.
- Relación `rectificativa` (eager load) para `totalCobrable()`.

### `pagos` (cobrado)
- Filtro: `anulado_at IS NULL`, `fecha` en rango. Suma `importe`.

### `compras` (gastos, IVA soportado)
- Filtro: estado que representa gasto real (**confirmada**; excluye borrador/anulada), `fecha` en rango.
- Campos: `total` (gastos), `cuota_impuesto_total` (IVA soportado), `fecha`, `estado`.
- El valor exacto del enum de estado "confirmada" se confirma contra `App\Enums\EstadoCompra` en
  implementación (T-research menor: leer el enum).
  **Confirmado (T001)**: `App\Enums\EstadoCompra` = `Borrador = 'borrador'`, `Confirmada = 'confirmada'`,
  `Anulada = 'anulada'`. El estado que representa gasto real es `EstadoCompra::Confirmada`.

### `articulos` (alertas de stock — a fecha de hoy, sin rango)
- Filtro: `gestion_stock = true` y (`stock_actual < stock_minimo` o `stock_actual < 0`).

## Estados y transiciones

No aplica: la feature no cambia estados de ninguna entidad. Solo lee.
