# Data Model: Gestión de Albaranes de Entrega

**Feature**: 029 | **Fecha**: 2026-07-08 | **Fase**: 1

Convenciones del proyecto (`docs/03-modelo-datos.md`): `id` BIGINT autoincrement; `tenant_id`
indexado + global scope (`BelongsToTenant`); `timestamps`; `softDeletes` donde aplique; importes
`DECIMAL(12,2)`, cantidades/precios `DECIMAL(12,4)`, porcentajes `DECIMAL(5,2)`.

## Diagrama de relaciones (resumen)

```
tenants ──< albaranes
clientes ──< albaranes
presupuestos ──(opcional)──< albaranes
presupuesto_lineas ──(opcional, +cantidad_entregada)── albaran_lineas
albaranes ──< albaran_lineas
articulos ──(opcional)── albaran_lineas
albaranes ──(N:1, convertido_a_factura_id)── facturas
movimientos_stock ──(+albaran_id nullable)── albaranes
```

---

## `albaranes` — documento de entrega (NO fiscal)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| numero | varchar(20) | numeración propia de albarán (independiente de series de factura), p. ej. `A-2026-0001` |
| presupuesto_id | fk → presupuestos, nullable | origen si nace de un presupuesto aceptado (`nullOnDelete`) |
| cliente_id | fk → clientes | receptor; obligatorio (a diferencia de presupuesto, el albarán siempre implica un cliente ya existente, no un lead) |
| estado | varchar(12) | enum `EstadoAlbaran`: `borrador`, `entregado`, `facturado`, `anulado` |
| **Snapshot receptor** (precargado, mismo patrón que `presupuestos.receptor_*`) | | |
| receptor_nombre, receptor_nif, receptor_direccion, receptor_cp, receptor_ciudad, receptor_provincia, receptor_pais | varchar | congelados en el documento |
| fecha_entrega | date, nullable | se fija al pasar a `entregado` |
| regimen_impositivo | varchar(5) | `iva`/`igic`/`ipsi`, heredado del presupuesto o del cliente/tenant |
| **Totales (calculados en backend con `CalculadoraFactura`, D1):** | | |
| base_total | decimal(12,2) | |
| cuota_impuesto_total | decimal(12,2) | |
| cuota_recargo_total | decimal(12,2) | |
| total | decimal(12,2) | |
| convertido_a_factura_id | fk → facturas, nullable | varios albaranes pueden compartir el mismo valor (relación N:1, `nullOnDelete`) |
| notas | text, nullable | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, estado)`, `(tenant_id, cliente_id)`, `(tenant_id, presupuesto_id)`,
`(tenant_id, convertido_a_factura_id)`, `unique(tenant_id, numero)`.

**Regla de no-fiscalidad**: mismo criterio que el presupuesto (feature 028) — `numero` es una
numeración propia, no participa de series de factura ni de Verifactu.

**Transiciones de estado** (`EstadoAlbaran`):

```
borrador ──► entregado ──► facturado   (terminal; solo lectura)
                │
                └──► anulado            (terminal; revierte stock y cantidad_entregada)
```

`facturado` y `anulado` son terminales. Solo se puede anular desde `entregado` y antes de
facturar (FR-006/FR-007).

---

## `albaran_lineas` — detalle de la entrega

Espejo de `presupuesto_lineas`/`factura_lineas` en las columnas de importe, con el vínculo adicional
que habilita el seguimiento de entrega parcial.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| albaran_id | fk → albaranes | `cascadeOnDelete`; índice |
| presupuesto_linea_id | fk → presupuesto_lineas, nullable | origen si el albarán nace de un presupuesto (`nullOnDelete`); null en albarán directo a cliente |
| articulo_id | fk → articulos, nullable | línea de catálogo o concepto libre (`nullOnDelete`) |
| concepto | varchar | descripción (siempre presente) |
| cantidad | decimal(12,4) | cantidad efectivamente entregada en **este** albarán |
| precio_unitario | decimal(12,4) | |
| descuento_porcentaje | decimal(5,2), nullable | |
| base | decimal(12,2) | calculada |
| tipo_impositivo | decimal(5,2) | |
| cuota_impuesto | decimal(12,2) | calculada |
| tipo_recargo | decimal(5,2), nullable | |
| cuota_recargo | decimal(12,2) | calculada |
| orden | smallint | |
| timestamps | | |

Índice: `(tenant_id, albaran_id)`, `(tenant_id, presupuesto_linea_id)`.

Al **convertir a factura** (D4), cada `albaran_linea` de todos los albaranes seleccionados se copia
a una `factura_linea` con sus importes **congelados** (no releídos del catálogo), y esa
`factura_linea` conserva de qué albarán proviene (columna nueva `factura_lineas.albaran_id`
nullable, solo para trazabilidad — no participa en el cálculo de la factura).

---

## Columnas nuevas en tablas existentes

### `presupuesto_lineas` (feature 028)

| Campo nuevo | Tipo | Notas |
|-------------|------|-------|
| cantidad_entregada | decimal(12,4), default 0 | acumulado de lo ya entregado en albaranes confirmados sobre esta línea (D2) |

Regla: `cantidad_entregada` nunca puede superar `cantidad`. `RegistroAlbaran` valida
`cantidad_solicitada_en_albaran <= cantidad - cantidad_entregada` antes de crear/editar un albarán
en borrador que referencie esta línea.

### `movimientos_stock` (feature 014)

| Campo nuevo | Tipo | Notas |
|-------------|------|-------|
| albaran_id | fk → albaranes, nullable | traza el movimiento hasta el albarán que lo generó (`nullOnDelete`), igual que ya existen `factura_id`/`compra_id` |

### `factura_lineas`

| Campo nuevo | Tipo | Notas |
|-------------|------|-------|
| albaran_id | fk → albaranes, nullable | solo trazabilidad: de qué albarán proviene esta línea cuando la factura nace de una consolidación de albaranes (`nullOnDelete`) |

---

## Enums nuevos

| Enum | Valores | Uso |
|------|---------|-----|
| `EstadoAlbaran` | `borrador`, `entregado`, `facturado`, `anulado` | `albaranes.estado` |

## Enums existentes que se amplían

| Enum | Caso nuevo | Uso |
|------|-----------|-----|
| `OrigenMovimientoStock` (feature 014) | `Albaran` | movimiento de salida al confirmar un albarán como entregado |

El movimiento de entrada al **anular** un albarán reutiliza el caso ya existente `Devolucion` (no
se añade un caso nuevo para esto — ver research.md D3).

## Impacto en documentación (cierre de feature)

- Añadir `albaranes`/`albaran_lineas` y las columnas nuevas (`presupuesto_lineas.cantidad_entregada`,
  `movimientos_stock.albaran_id`, `factura_lineas.albaran_id`) a `docs/03-modelo-datos.md`.
- Revisar si `docs/06-kit-digital.md` menciona gestión documental de entregas; si no, no hace falta
  tocarlo (el albarán no es un requisito de homologación distinto de los ya cubiertos por 028).
