# Implementation Plan: Control de stock con proveedores, compras y kardex

**Branch**: `014-control-stock` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/014-control-stock/spec.md`

## Summary

Materializa la "Fase 2" ya documentada en `docs/03-modelo-datos.md`: cuatro tablas nuevas
(`proveedores`, `compras`, `compra_lineas`, `movimientos_stock`) más el enganche de descuento de
stock en la emisión de facturas. El núcleo es un **ledger append-only** (`movimientos_stock`) que
es la fuente de verdad del inventario; `articulos.stock_actual` pasa a ser una caché de lectura
sincronizada por un único servicio de dominio (`RegistroMovimientoStock`) que calcula
`stock_resultante` y actualiza el stock de forma atómica por artículo. Compras y emisión de
facturas invocan ese servicio; nunca escriben stock por su cuenta. Todo tenant-scoped vía
`BelongsToTenant` y con tests-first en la lógica de stock (Principio IV).

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, trait `BelongsToTenant` para el global
scope de tenant), Eloquent, PHPUnit/Pest (suite `tests/Feature`).

**Storage**: MySQL/MariaDB. Tablas nuevas con `id` bigint, `timestamps`, `softDeletes` donde aplique;
importes `DECIMAL(12,2)`, cantidades de stock `DECIMAL(12,4)`, porcentajes `DECIMAL(5,2)`.

**Testing**: Feature tests estilo existente (`tests/Feature/*CrudTest.php`, `FacturaCrudTest.php`),
con ≥2 tenants para aislamiento y Red-Green-Refactor en la lógica de stock.

**Target Platform**: Hosting compartido cPanel/Hostinger (Principio V) — sin dependencias que exijan
VPS. Bloqueo de concurrencia con `lockForUpdate()` dentro de transacción (soportado por
MySQL/MariaDB InnoDB), no locks a nivel de aplicación externos.

**Project Type**: Web app monolítica Laravel (backend + Blade). Estructura single-project.

**Performance Goals**: Operativa CRUD normal; el coste crítico es la corrección del stock bajo
concurrencia, no throughput. `stock_actual` desnormalizado para listados rápidos.

**Constraints**: `movimientos_stock` es append-only (nunca UPDATE/DELETE); cálculo de stock siempre
server-side (Principio III); coherencia `stock_actual` == último `stock_resultante` en todo momento.

**Scale/Scope**: 4 tablas, 4 modelos, 1 servicio de dominio nuevo (`RegistroMovimientoStock`), 1
servicio de compras (`RegistroCompra`), enganche en `EmisorFacturas` + reverso en
`GeneradorRectificativa`/anulación, 3-4 controladores, vistas Blade CRUD, 1 enum nuevo
(`TipoMovimientoStock`) + 1 enum origen (`OrigenMovimientoStock`).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)** — ✅ Las 4 tablas llevan `tenant_id` indexado y
  usan `BelongsToTenant`. Se añaden tests de fuga con ≥2 tenants para proveedores, compras y
  movimientos. Sin queries que salten el scope.
- **II. Cumplimiento Normativo España-First** — ✅ N/A directo: las compras registran un documento
  externo del proveedor, **no** emiten numeración fiscal propia ni pasan por Verifactu. No se altera
  la inmutabilidad de facturas emitidas: el descuento de stock ocurre *en el acto de emitir* (mismo
  punto donde ya se congela la factura) y su reverso solo vía anulación/rectificativa existentes,
  nunca editando la factura. El impuesto soportado en compras respeta `regimen_impositivo` del tenant.
- **III. Integridad Financiera Server-Side** — ✅ Totales de compra calculados en backend desde
  líneas (se reutiliza el patrón de `CalculadoraFactura` para el impuesto soportado). `stock_resultante`
  y `stock_actual` se calculan server-side dentro de transacción con `lockForUpdate()` por artículo.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)** — ✅ Stock es lógica crítica: los tests de
  `RegistroMovimientoStock` (resultante encadenado, append-only, atomicidad, rechazo en
  servicio/no-gestión), de confirmación/anulación de compra y de descuento al emitir se escriben
  antes y deben fallar primero.
- **V. Simplicidad y Compatibilidad Hosting Compartido** — ✅ Sin multi-almacén, sin valoración
  FIFO/PMP, sin órdenes de compra previas, sin recepción parcial (declarado fuera de alcance en la
  spec). Concurrencia resuelta con `lockForUpdate` de InnoDB, sin infra extra. La feature entra al
  alcance del MVP por decisión explícita del usuario (ya reflejada en `docs/00-vision.md`), lo que
  satisface la condición del Principio V para implementar la "Fase 2".

**Resultado del gate**: PASS. Sin violaciones → sin entradas en Complexity Tracking.

> Nota de cierre (no bloquea el plan): al terminar la feature, enmendar
> `.specify/memory/constitution.md` (Additional Constraints, nota "Compras (Fase 2) … NO se
> implementan") vía `/speckit-constitution`, ya que la Fase 2 pasa a estar implementada.

## Project Structure

### Documentation (this feature)

```text
specs/014-control-stock/
├── plan.md              # Este archivo
├── spec.md              # Especificación (fase previa)
├── research.md          # Fase 0 (este comando)
├── data-model.md        # Fase 1 (este comando)
├── quickstart.md        # Fase 1 (este comando)
├── contracts/           # Fase 1 (este comando) — rutas/endpoints
│   └── rutas.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── TipoMovimientoStock.php        # entrada | salida | ajuste
│   ├── OrigenMovimientoStock.php      # factura | compra | ajuste_manual | inventario | devolucion
│   └── EstadoCompra.php               # borrador | confirmada | anulada
├── Models/
│   ├── Proveedor.php                  # BelongsToTenant + SoftDeletes (patrón Cliente)
│   ├── Compra.php                     # BelongsToTenant + SoftDeletes; hasMany lineas
│   ├── CompraLinea.php                # BelongsToTenant; belongsTo compra/articulo
│   └── MovimientoStock.php            # BelongsToTenant; append-only (sin update/delete públicos)
├── Services/
│   ├── RegistroMovimientoStock.php    # ÚNICO punto que escribe stock (resultante + stock_actual atómico)
│   └── RegistroCompra.php             # confirmar()/anular() → invoca RegistroMovimientoStock
├── Http/
│   ├── Controllers/
│   │   ├── ProveedorController.php     # resource CRUD
│   │   ├── CompraController.php        # CRUD + confirmar/anular
│   │   └── MovimientoStockController.php # ajuste manual + listado kardex + alertas stock mínimo
│   └── Requests/
│       ├── StoreProveedorRequest.php / UpdateProveedorRequest.php
│       ├── StoreCompraRequest.php / UpdateCompraRequest.php
│       └── StoreAjusteStockRequest.php
├── Services/EmisorFacturas.php        # MODIFICADO: descuenta stock al emitir (salida origen factura)
└── Services/GeneradorRectificativa.php # MODIFICADO/verificado: reverso de stock en anulación

database/
├── migrations/
│   ├── 2026_07_04_130000_create_proveedores_table.php
│   ├── 2026_07_04_130001_create_compras_table.php
│   ├── 2026_07_04_130002_create_compra_lineas_table.php
│   └── 2026_07_04_130003_create_movimientos_stock_table.php
└── factories/
    ├── ProveedorFactory.php / CompraFactory.php / CompraLineaFactory.php / MovimientoStockFactory.php

resources/views/
├── proveedores/       # index, create, edit (patrón clientes)
├── compras/           # index, create, edit, show
└── stock/             # index (kardex + alertas de stock mínimo), _ajuste (modal)

tests/Feature/
├── ProveedorCrudTest.php
├── CompraStockTest.php          # confirmar suma / anular revierte / aislamiento
├── MovimientoStockTest.php      # resultante encadenado / append-only / no-gestión / concurrencia
├── FacturaStockTest.php         # emitir descuenta / anular-rectificar revierte / stock negativo permitido
└── StockMinimoTest.php          # alertas
```

**Structure Decision**: Single-project Laravel monolítico (Opción 1). Se sigue al pie de la letra el
patrón ya establecido por features previas: modelos con `BelongsToTenant`, servicios de dominio con
`DB::transaction`, controladores resource + FormRequests, enums en `app/Enums`, feature tests en
`tests/Feature`. El punto de integración clave es `EmisorFacturas::emitir()` (ya invocado tanto por
`FacturaController` como por `RegistroTicket` del POS), de modo que enganchar ahí cubre facturas
ordinarias y simplificadas con un único cambio.

## Complexity Tracking

> Sin violaciones de la constitución. No aplica.
