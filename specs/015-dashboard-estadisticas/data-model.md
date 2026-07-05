# Data Model: Dashboard de estadísticas

No se crean tablas ni columnas nuevas. Este documento describe las **estructuras de datos en
memoria** (DTOs/arrays) que `App\Services\DashboardEstadisticas` produce a partir de las tablas
existentes (`facturas`, `pagos`, `clientes`, `articulos`), para que el controller las pase a la
vista sin cálculos adicionales.

## Entidades derivadas (no persistidas)

### `ResumenMensual`
Agregado de un mes calendario para un tenant.

| Campo | Tipo | Origen |
|-------|------|--------|
| `anio` | int | — |
| `mes` | int (1-12) | — |
| `total_facturado` | float | `SUM(facturas.total)` donde `estado` IN (emitida, pagada, vencida, rectificada) y `fecha_expedicion` en el mes |
| `total_cobrado` | float | `SUM(pagos.importe)` donde `anulado_at IS NULL` y `pagos.fecha` en el mes |
| `num_facturas` | int | `COUNT(facturas.id)` mismo filtro que `total_facturado` |

### `VariacionKpi`
Resultado del helper `VariacionPorcentual` para un par (mes actual, mes anterior).

| Campo | Tipo | Notas |
|-------|------|-------|
| `valor_actual` | float\|int | valor de `ResumenMensual` del mes en curso |
| `valor_anterior` | float\|int | valor de `ResumenMensual` del mes anterior |
| `porcentaje` | float\|null | `null` cuando `valor_anterior == 0` → la vista muestra "sin datos previos" |

### `PuntoSerieMensual`
Un punto de las series de 12 y 6 meses (gráficos de evolución y comparativo).

| Campo | Tipo | Notas |
|-------|------|-------|
| `etiqueta` | string | mes/año legible, ej. `"jul 2026"` |
| `facturado` | float | igual criterio que `ResumenMensual.total_facturado` |
| `cobrado` | float | solo presente en la serie de 6 meses (comparativo) |

### `DistribucionEstado`
Conteo de facturas por estado (histórico completo del tenant, excluye `tipo = simplificada`
igual que `FacturaController::index`).

| Campo | Tipo | Notas |
|-------|------|-------|
| `estado` | string (`EstadoFactura`) | uno de los 6 valores del enum |
| `cantidad` | int | `COUNT(*)` agrupado por `estado` |

### `RankingCliente`
Fila del top 5 de clientes por facturación acumulada.

| Campo | Tipo | Notas |
|-------|------|-------|
| `cliente_id` | int\|null | `null` si la factura no tiene cliente asociado (simplificadas quedan excluidas igualmente) |
| `nombre` | string | `cliente_razon_social` o `cliente_nombre` (snapshot de la factura, no join a `clientes` — consistente con el resto del sistema, que trata el snapshot como la fuente para historicos) |
| `total_facturado` | float | `SUM(facturas.total)` agrupado por `cliente_id`, mismo filtro de estados que `ResumenMensual` |

### `AlertaStock`
Artículo en condición de alerta (reutiliza `Articulo::scopeBajoMinimo` + condición de negativo).

| Campo | Tipo | Origen |
|-------|------|--------|
| `articulo_id` | int | `articulos.id` |
| `nombre` | string | `articulos.nombre` |
| `stock_actual` | float | `articulos.stock_actual` |
| `stock_minimo` | float\|null | `articulos.stock_minimo` |

### `FacturaReciente`
Fila de la lista de últimas facturas emitidas.

| Campo | Tipo | Origen |
|-------|------|--------|
| `id` | int | `facturas.id` |
| `numero_completo` | string | `facturas.numero_completo` |
| `estado` | string | `facturas.estado` |
| `cliente_nombre` | string | snapshot `cliente_razon_social`/`cliente_nombre` |
| `total` | float | `facturas.total` |
| `fecha_expedicion` | date | `facturas.fecha_expedicion` |

## DTO contenedor

`DashboardEstadisticas::resumen(): array` devuelve una única estructura con todas las claves
anteriores (`kpis`, `serie_12_meses`, `comparativo_6_meses`, `distribucion_estados`,
`top_clientes`, `alertas_stock`, `facturas_recientes`) — ver `contracts/dashboard-service.md`
para la forma exacta y las reglas de estado vacío.

## Reglas de validación / bordes (heredadas de la spec)

- Si `valor_anterior == 0` en cualquier KPI → `porcentaje = null`, nunca división por cero.
- Si un mes de la serie de 12/6 meses no tiene actividad, se incluye igual con `facturado = 0` /
  `cobrado = 0` (no se omite el mes).
- `alertas_stock` es un array vacío tanto si no hay artículos en alerta como si el tenant no
  gestiona stock; el DTO agrega un flag `gestiona_stock: bool` (¿existe al menos un artículo con
  `gestion_stock = true`?) para que la vista distinga "sin alertas" de "no aplica" (FR-012).
- Todas las sumas excluyen `tipo = simplificada` (mismo criterio que `FacturaController::index`,
  que ya separa el módulo POS del de facturas ordinarias) — el dashboard de esta iteración cubre
  facturación ordinaria/rectificativa, no tickets POS.
