# Contratos de interfaz (rutas web)

Endpoints Blade/servidor (patrón resource de Laravel, como el resto del panel). Todas bajo el
middleware de tenant activo; nunca exponen datos de otro tenant. No hay API REST pública.

## Proveedores — `ProveedorController` (resource completo)

| Método | Ruta | Nombre | Acción |
|--------|------|--------|--------|
| GET | `/proveedores` | `proveedores.index` | listado |
| GET | `/proveedores/create` | `proveedores.create` | form alta |
| POST | `/proveedores` | `proveedores.store` | crear (StoreProveedorRequest) |
| GET | `/proveedores/{proveedor}/edit` | `proveedores.edit` | form edición |
| PUT | `/proveedores/{proveedor}` | `proveedores.update` | actualizar (UpdateProveedorRequest) |
| DELETE | `/proveedores/{proveedor}` | `proveedores.destroy` | baja lógica |

> Binding resuelto en el cuerpo del controlador acotado al tenant (ver memoria
> `project_tenant_route_binding`: el implicit binding se salta el TenantScope).

## Compras — `CompraController`

| Método | Ruta | Nombre | Acción |
|--------|------|--------|--------|
| GET | `/compras` | `compras.index` | listado |
| GET | `/compras/create` | `compras.create` | form alta |
| POST | `/compras` | `compras.store` | crear borrador (StoreCompraRequest) |
| GET | `/compras/{compra}` | `compras.show` | detalle |
| GET | `/compras/{compra}/edit` | `compras.edit` | editar (solo borrador) |
| PUT | `/compras/{compra}` | `compras.update` | actualizar (solo borrador, UpdateCompraRequest) |
| POST | `/compras/{compra}/confirmar` | `compras.confirmar` | confirmar → entradas de stock |
| POST | `/compras/{compra}/anular` | `compras.anular` | anular → reverso de stock |
| DELETE | `/compras/{compra}` | `compras.destroy` | baja lógica (solo borrador) |

**Contrato de estado**: `edit`/`update`/`destroy` devuelven 422/redirect con error si la compra no
está en `borrador`. `confirmar` solo desde `borrador`; `anular` solo desde `confirmada`.

## Stock / kardex — `MovimientoStockController`

| Método | Ruta | Nombre | Acción |
|--------|------|--------|--------|
| GET | `/stock` | `stock.index` | kardex + alertas de stock mínimo |
| GET | `/stock/{articulo}` | `stock.show` | histórico de un artículo |
| POST | `/stock/ajuste` | `stock.ajuste` | ajuste manual (StoreAjusteStockRequest) |

**StoreAjusteStockRequest**: `articulo_id` (producto+gestion_stock del tenant), `tipo`
(entrada|salida), `cantidad` (>0), `motivo` (requerido). Rechaza artículos servicio/sin gestión.

> No existen rutas de edición/borrado de `movimientos_stock` (append-only, contrato del ledger).

## Integración con emisión (sin ruta nueva)

El descuento de stock al emitir cuelga de `EmisorFacturas::emitir()` — invocado por las rutas
existentes de emisión de factura (`FacturaController`) y de ticket POS (`RegistroTicket`). No añade
endpoints; es un efecto de dominio dentro de la transacción de emisión.
