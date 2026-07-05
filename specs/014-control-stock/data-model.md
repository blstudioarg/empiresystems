# Data Model: Control de stock con proveedores, compras y kardex

Materializa las tablas ya descritas en `docs/03-modelo-datos.md` (secciones `proveedores`,
`compras`, `compra_lineas`, `movimientos_stock`). Convenciones del proyecto: `id` bigint,
`timestamps`, `softDeletes` donde se indica; importes `DECIMAL(12,2)`, cantidades de stock
`DECIMAL(12,4)`, porcentajes `DECIMAL(5,2)`; toda tabla lleva `tenant_id` indexado + `BelongsToTenant`.

## Enums nuevos

| Enum | Valores | Uso |
|------|---------|-----|
| `TipoMovimientoStock` | `entrada`, `salida`, `ajuste` | sentido del movimiento |
| `OrigenMovimientoStock` | `factura`, `compra`, `ajuste_manual`, `inventario`, `devolucion` | procedencia |
| `EstadoCompra` | `borrador`, `confirmada`, `anulada` | ciclo de la compra |

## `proveedores`

Espejo de `clientes` (sin `tipo` ni recargo). `SoftDeletes`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice |
| nombre | varchar | nullable si hay razón social |
| razon_social | varchar | |
| nif | varchar(15) | |
| direccion, cp, ciudad, provincia, pais | varchar | pais default `ES` |
| email, telefono | varchar | |
| notas | text | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, nif)`, `(tenant_id, nombre)`.
**Reglas**: baja lógica (FR-008); no se puede borrar físicamente si hay compras que lo referencian
(FK `RESTRICT` a nivel de esquema + soft delete a nivel de app).

## `compras`

Documento de compra / factura de proveedor. `SoftDeletes`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| proveedor_id | fk → proveedores | |
| numero_documento | varchar | nº de factura externo del proveedor |
| fecha | date | |
| estado | enum `EstadoCompra` | default `borrador` |
| base_total | decimal(12,2) | calculado |
| cuota_impuesto_total | decimal(12,2) | impuesto soportado (IVA/IGIC/IPSI) |
| total | decimal(12,2) | |
| notas | text | nullable |
| confirmada_at | datetime | nullable; sello al confirmar |
| anulada_at | datetime | nullable; sello al anular |
| softDeletes, timestamps | | |

Índices: `(tenant_id, proveedor_id)`, `(tenant_id, fecha)`, `(tenant_id, estado)`.

**Transiciones de estado**:
```
borrador ──confirmar──▶ confirmada ──anular──▶ anulada
   │
   └──(editable solo aquí)
```
- `borrador`: editable (líneas, cabecera). No hay movimientos de stock.
- `confirmar()`: genera entradas de stock por línea con artículo+gestión; inmutable a partir de aquí (FR-014).
- `anular()`: solo desde `confirmada`; genera movimientos inversos (FR-013).

## `compra_lineas`

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| compra_id | fk → compras | índice; cascade on delete |
| articulo_id | fk → articulos | nullable |
| concepto | varchar | |
| unidad | varchar(20) | nullable |
| cantidad | decimal(12,4) | > 0 |
| precio_unitario | decimal(12,4) | coste de compra |
| base | decimal(12,2) | calculado |
| tipo_impositivo | decimal(5,2) | % impuesto soportado |
| cuota_impuesto | decimal(12,2) | calculado |
| orden | smallint | |
| timestamps | | |

**Regla de stock**: solo la línea con `articulo_id` de un producto con `gestion_stock=true` genera
movimiento al confirmar (FR-012); las demás computan en totales pero no mueven inventario.

## `movimientos_stock` (kardex, append-only)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| articulo_id | fk → articulos | índice |
| tipo | enum `TipoMovimientoStock` | entrada/salida/ajuste |
| cantidad | decimal(12,4) | positiva; el `tipo` marca el sentido |
| stock_resultante | decimal(12,4) | snapshot tras el movimiento (puede ser negativo) |
| origen | enum `OrigenMovimientoStock` | factura/compra/ajuste_manual/inventario/devolucion |
| factura_id | fk → facturas | nullable |
| compra_id | fk → compras | nullable |
| motivo | varchar | nullable (obligatorio en ajuste manual) |
| ocurrido_at | datetime | |
| timestamps | | |

Índice: `(tenant_id, articulo_id, ocurrido_at)`.
**Contrato append-only**: sin UPDATE ni DELETE (FR-002/SC-004). El modelo no expone rutas de
edición/borrado. Correcciones = movimiento inverso.

## `articulos` (existente — sin cambios de esquema)

`stock_actual`, `stock_minimo`, `gestion_stock` ya existen (migración
`2026_07_03_120001_create_articulos_table`). Esta feature les da comportamiento:
- `stock_actual`: caché de lectura, sincronizada SOLO por `RegistroMovimientoStock`.
- `stock_minimo`: umbral para la alerta (FR-017): artículo listado cuando `stock_actual <= stock_minimo` y `stock_minimo` no nulo.
- `gestion_stock=false` o tipo servicio: `RegistroMovimientoStock` rechaza el movimiento (FR-004).

## Invariantes (verificadas por tests)

- **INV-1**: tras cualquier operación, `articulo.stock_actual == ` último `movimientos_stock.stock_resultante` de ese artículo (SC-002).
- **INV-2**: `SUM(±cantidad según tipo)` del histórico de un artículo `== stock_actual` (SC-006).
- **INV-3**: ningún movimiento se modifica ni borra (SC-004).
- **INV-4**: ninguna operación cruza tenants (SC-003).

## Relaciones (Eloquent)

- `Proveedor hasMany Compra`; `Compra belongsTo Proveedor`.
- `Compra hasMany CompraLinea`; `CompraLinea belongsTo Compra, belongsTo Articulo` (nullable).
- `Compra hasMany MovimientoStock`; `Factura hasMany MovimientoStock`.
- `Articulo hasMany MovimientoStock`; `MovimientoStock belongsTo Articulo, Compra?, Factura?`.
