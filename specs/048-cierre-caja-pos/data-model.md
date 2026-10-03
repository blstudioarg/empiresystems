# Data model — 048 Cierre de caja del POS

Convenciones del proyecto (docs/03): `id` BIGINT, `timestamps`, importes `DECIMAL(12,2)`,
`tenant_id` indexado + global scope de tenancy (trait `Stancl\Tenancy\Database\Concerns\BelongsToTenant`, igual que `TicketPago`). Prefijo `caja_` por la misma razón
que `pos_` en la feature 038: agrupa un módulo de un vistazo y evita colisiones semánticas.

```
tenants ──< caja_sesiones ──< caja_movimientos
users   ──< caja_sesiones (abierta_por, cerrada_por)
users   ──< caja_movimientos (usuario_id)
caja_sesiones ──< ticket_pagos (caja_sesion_id, nullable) >── facturas (tipo = simplificada)
```

## `caja_sesiones` (nueva)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | bigint, índice | `TenantScope` |
| estado | enum `abierta`/`cerrada` | `abierta` → `cerrada`, sin vuelta atrás |
| abierta_marca | tinyint unsigned, nullable | `1` si abierta, `NULL` si cerrada. `UNIQUE (tenant_id, abierta_marca)` (research D2) |
| fondo_inicial | decimal(12,2) | ≥ 0 |
| conteo_apertura | json, nullable | `{"5000": 2, "200": 10}` (céntimos → cantidad) si se contó por denominaciones |
| abierta_por | fk → users | `restrictOnDelete` |
| abierta_at | datetime (UTC) | |
| cerrada_por | fk → users, nullable | |
| cerrada_at | datetime (UTC), nullable | |
| **Cifras congeladas al cerrar** (nullable mientras está abierta) | | |
| num_tickets | int unsigned | tickets **no anulados** de la sesión |
| total_facturado | decimal(12,2) | suma de `facturas.total` no anuladas |
| efectivo_ventas | decimal(12,2) | suma de `ticket_pagos.importe` efectivo de tickets no anulados |
| entradas | decimal(12,2) | suma de movimientos `entrada` |
| salidas | decimal(12,2) | suma de movimientos `salida` |
| efectivo_esperado | decimal(12,2) | `fondo_inicial + efectivo_ventas + entradas − salidas` (puede ser negativo) |
| efectivo_contado | decimal(12,2) | recalculado en servidor desde `conteo_cierre` si viene por denominaciones |
| conteo_cierre | json, nullable | mismo formato que `conteo_apertura` |
| descuadre | decimal(12,2) | `efectivo_contado − efectivo_esperado` (>0 sobra, <0 falta) |
| observacion | text, nullable | obligatoria si `|descuadre| > pos.caja_umbral_descuadre` |
| resumen | json, nullable | desgloses congelados, ver abajo |
| timestamps | | sin `softDeletes`: una sesión nunca se borra |

Índices: `(tenant_id, abierta_marca)` único; `(tenant_id, abierta_at)` (listado histórico).

**Estructura de `resumen`** (lo escribe `CierreCaja` desde `ResumenCaja`, research D4/D5):

```json
{
  "por_metodo": [{"metodo": "efectivo", "importe": "50.00", "tickets": 2}, …],
  "por_impuesto": [{"tipo_impuesto": "iva", "porcentaje": "21.00", "base": "78.51", "cuota": "16.49"}, …],
  "primer_ticket": "S-2026-0141",
  "ultimo_ticket": "S-2026-0143",
  "anulados": [{"numero": "S-2026-0142", "total": "12.00"}],
  "movimientos": [{"tipo": "salida", "importe": "30.00", "motivo": "Pago proveedor pan", "usuario": "Ana", "at": "2026-10-03T10:12:00Z"}]
}
```

Los cuatro métodos aparecen siempre en `por_metodo` (con 0 si no hubo), para que el informe tenga
siempre la misma forma.

**Invariantes** (verificados en tests):

- **C1** — como mucho una sesión `abierta` por tenant (índice único + `lockForUpdate`).
- **C2** — una sesión `cerrada` no admite `update` ni `delete` (el modelo lanza excepción).
- **C3** — `efectivo_esperado = fondo_inicial + efectivo_ventas + entradas − salidas` y
  `descuadre = efectivo_contado − efectivo_esperado`, al céntimo.
- **C4** — `total_facturado` = suma por método de `resumen.por_metodo` = suma de `facturas.total`
  no anuladas de la sesión (los repartos de pago dividido cuadran por la regla de `RegistroTicket`).

## `caja_movimientos` (nueva) — ledger de solo alta

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | bigint, índice | |
| caja_sesion_id | fk → caja_sesiones | `restrictOnDelete` |
| tipo | enum `entrada`/`salida` | |
| importe | decimal(12,2) | > 0 |
| motivo | varchar(160) | obligatorio |
| usuario_id | fk → users | |
| timestamps | | `created_at` es el momento del movimiento |

Solo alta, sin `update`/`delete` (mismo criterio append-only que `movimientos_stock` y `fichajes`).
Solo se puede crear contra la sesión **abierta** del tenant (bloqueada con `lockForUpdate`). Una
corrección es un movimiento del tipo contrario con el motivo "Corrección: …".

## `ticket_pagos` (existente) — columna nueva

| Campo | Tipo | Notas |
|-------|------|-------|
| caja_sesion_id | fk → caja_sesiones, nullable, índice | la escribe `RegistroTicket` al emitir. `NULL` en los tickets anteriores a esta feature |

Índice `(tenant_id, caja_sesion_id)`. Migración solo **añade** una columna nullable: no hay
backfill ni riesgo para los datos existentes (ningún `migrate:fresh`).

## `configuraciones` (existente) — clave nueva

| clave | grupo | default | Notas |
|-------|-------|---------|-------|
| `pos.caja_umbral_descuadre` | pos | `5.00` | `ConfigPos::CLAVE_CAJA_UMBRAL_DESCUADRE` / `DEFAULT_CAJA_UMBRAL_DESCUADRE`; editable en Configuración → POS |

## Permiso nuevo

`ver-pos-caja` (etiqueta "Caja", módulo POS) en `CatalogoPermisos`. Entrada de menú `pos-caja`
("Caja", ruta `pos.caja`) en el grupo POS de `CatalogoMenu`, **sin** `modulo` de hostelería (la caja
aplica a todo POS). No excluido del rol base: decisión por defecto del catálogo, el admin lo acota
desde `/roles`.

## Transiciones de estado

```
(sin sesión) ──abrir──▶ abierta ──cerrar──▶ cerrada (terminal, inmutable)
                           │
                           └── registrar movimiento (n veces)
                           └── emitir ticket (n veces, vía RegistroTicket)
```
