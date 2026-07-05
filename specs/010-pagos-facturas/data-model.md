# Data Model: Pagos y cobros de facturas

## Tabla nueva: `pagos`

Cobros aplicados a facturas emitidas. Tabla de negocio → `tenant_id` + `BelongsToTenant`.
Materializa la definición de `docs/03-modelo-datos.md` (Fase 2), ampliada con `anulado_at` para la
anulación soft.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, índice | scope de tenant (Principio I) |
| factura_id | foreignId → facturas | el cobro pertenece a una única factura |
| fecha | date | fecha del cobro |
| importe | decimal(12,2) | > 0 (FR-002) |
| metodo | string | cast a `App\Enums\FormaPago` |
| referencia | varchar(100) nullable | ej. nº de operación bancaria |
| anulado_at | dateTime nullable | `NULL` = pago vigente; con valor = anulado (FR-007) |
| timestamps | | |

Índices: `(tenant_id, factura_id)`.

**Notas de esquema**:
- No usa `softDeletes` (`deleted_at`): un pago anulado debe seguir listándose (FR-006). La anulación
  vive en `anulado_at`, no en el default scope.
- FK a `facturas` sin `onDelete cascade` explícito (las facturas emitidas no se borran; los borradores
  no tienen pagos).

## Modelo: `App\Models\Pago`

- Traits: `BelongsToTenant`, `HasFactory`.
- `$fillable`: `tenant_id, factura_id, fecha, importe, metodo, referencia, anulado_at`.
- Casts: `fecha => date`, `importe => decimal:2`, `metodo => FormaPago::class`,
  `anulado_at => datetime`.
- Relaciones: `factura(): BelongsTo`, `tenant(): BelongsTo`.
- Scopes / helpers:
  - `scopeVigentes($q)` → `whereNull('anulado_at')`.
  - `estaAnulado(): bool` → `anulado_at !== null`.

## Cambios en `App\Models\Factura` (solo lectura, aditivos)

- `pagos(): HasMany` → todos los pagos (vigentes y anulados), para el historial (FR-006).
- `pagosVigentes(): HasMany` → `pagos()->whereNull('anulado_at')`.
- `montoCobrado(): float` → suma de `pagosVigentes` (en céntimos, redondeada a 2).
- `saldoPendiente(): float` → `round(total - montoCobrado(), 2)` (FR-004).
- `estadoCobro(): EstadoCobro` → derivado (FR-005):
  - sin pagos vigentes → `Pendiente`
  - `0 < cobrado < total` → `Parcial`
  - `cobrado == total` (comparación en céntimos) → `Cobrada`

> Ninguno de estos escribe en BD ni cambia `Factura::$estado`. La columna `estado` sigue siendo el
> estado fiscal (Principio II), independiente del estado de cobro.

## Enum nuevo: `App\Enums\EstadoCobro`

| Case | value |
|------|-------|
| Pendiente | `pendiente` |
| Parcial | `parcial` |
| Cobrada | `cobrada` |

(Enum de presentación/derivado; no se persiste como columna.)

## Reglas de validación (server-side)

| Regla | Origen | Dónde |
|-------|--------|-------|
| Factura debe estar `emitida` | FR-001, FR-012 | `RegistroPagos::registrar` |
| `importe > 0` | FR-002 | `StorePagoRequest` + servicio |
| `sum(vigentes) + importe <= total` (en céntimos) | FR-003 | `RegistroPagos::registrar` |
| `metodo` ∈ `FormaPago` | D5 | `StorePagoRequest` |
| No anular un pago ya anulado | FR-008 | `RegistroPagos::anular` |
| Aislamiento por tenant | FR-010 | `BelongsToTenant` + resolución manual |

Violaciones de negocio → `PagoInvalidoException` (mapea a HTTP 422). Recursos de otro tenant → 404
vía scope de tenant.

## Transiciones de estado de cobro (derivadas)

```text
                 registrar pago parcial
   Pendiente ─────────────────────────────▶ Parcial
       │                                       │
       │ registrar pago total                  │ registrar pago que completa el total
       ▼                                       ▼
    Cobrada ◀──────────────────────────────────┘

   (cualquier estado) ── anular pago ──▶ se recalcula: puede volver a Parcial o Pendiente
```

El estado nunca se guarda: se recalcula en cada lectura a partir de `pagosVigentes`.
