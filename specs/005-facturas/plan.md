# Implementation Plan: Facturas — emisión de facturas ordinarias (núcleo mínimo)

**Branch**: `005-facturas` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005-facturas/spec.md`

## Summary

Primera feature de facturación. Introduce las tablas núcleo (`series`, `facturas`,
`factura_lineas`, `factura_impuestos`) con `tenant_id` + `BelongsToTenant`, un **servicio de
cálculo server-side** que produce bases, cuotas por tipo impositivo (IVA/IGIC/IPSI según el
`regimen_impositivo` del tenant), recargo de equivalencia (solo IVA) e IRPF (manual), y el
desglose por tipo. El front es la pieza más importante: un **listado DataTable** de facturas
(mismo patrón que clientes/artículos) y, como novedad, una **vista full-page dedicada de creación**
(no modal) con secciones (emisor, cliente, fechas/pago, líneas editables, IRPF/recargo, totales)
y **preview en vivo** del documento. Las facturas se crean/editan/borran en estado **borrador**
(sin número fiscal ni Verifactu). Desde el listado se **visualiza el PDF** (dompdf) de la factura.
La asignación de número correlativo se **diseña** (serie + contador con bloqueo) pero se **ejecuta
al emitir**, transición fuera del alcance de esta feature.

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 12)

**Primary Dependencies**: Laravel 12, `stancl/tenancy` (single-database, `BelongsToTenant` +
`TenantScope`), DataTables (jQuery, ya vendorizado), toastr (ya vendorizado), **nueva dependencia:
`barryvdh/laravel-dompdf`** para el PDF (pure-PHP, compatible con hosting compartido — ver
`research.md`)

**Storage**: MySQL/MariaDB — nuevas tablas `series`, `facturas`, `factura_lineas`,
`factura_impuestos`

**Testing**: PHPUnit (Feature tests), mismo patrón que `ClienteCrudTest` /
`ClienteTenantIsolationTest`; **tests unitarios** nuevos para el servicio de cálculo y la
numeración (Principio IV, test-first)

**Target Platform**: Hosting compartido tipo cPanel (Principio V) — Linux + Apache/Nginx, sin VPS

**Project Type**: Web application monolítica (Laravel + Blade), sin frontend separado

**Performance Goals**: Sin requisitos especiales; DataTable client-side sobre el dataset del
tenant (mismo enfoque que clientes/artículos). El PDF se genera on-demand por factura.

**Constraints**: Todos los importes se calculan en backend (Principio III); dompdf debe funcionar
sin binarios externos (hosting compartido); las facturas emitidas serían inmutables, pero en esta
feature solo existe borrador (editable).

**Scale/Scope**: Alcance a `series`/`facturas`/`factura_lineas`/`factura_impuestos` + servicio de
cálculo + numeración (diseñada) + vista de creación + PDF. NO incluye: emisión real, Verifactu
(hash/QR/XML/AEAT), `pagos`, `movimientos_stock`, `factura_eventos`, simplificada, rectificativa,
CRUD de series, factura electrónica B2B/Facturae.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio I (Aislamiento Multi-Tenant, NON-NEGOTIABLE)**: las 4 tablas nuevas llevan
  `tenant_id` indexado y sus modelos usan `BelongsToTenant`. Toda operación (listar, crear,
  editar, borrar, PDF) corre bajo el `TenantScope`. La resolución de modelos en el controller se
  hace **manualmente con `findOrFail`** (no binding implícito) por el pitfall ya documentado
  (`ClienteController`, ver memoria `project_tenant_route_binding`). Tests de aislamiento con ≥2
  tenants para facturas y para el PDF/detalle. **PASS**.
- **Principio II (Cumplimiento Normativo España-First)**: numeración correlativa sin huecos por
  serie (diseñada, se ejecuta al emitir), desglose de impuestos **por tipo** (`factura_impuestos`),
  cálculo **agnóstico al régimen** (IVA/IGIC/IPSI) vía `regimen_impositivo` congelado en la
  factura al crearse, recargo solo bajo IVA. Los campos Verifactu/ciclo B2B existen en el modelo
  desde el día uno aunque no se calculen. Borrador es editable; la inmutabilidad de "emitida" se
  respeta por diseño (esta feature no emite). **PASS**.
- **Principio III (Integridad Financiera Server-Side)**: un `CalculadoraFactura` (servicio
  dedicado) es la **única** fuente de verdad de los importes; el front solo previsualiza. La
  asignación de número se diseña dentro de transacción con bloqueo (`lockForUpdate` sobre la
  serie). **PASS**.
- **Principio IV (Test-First en Lógica Crítica, NON-NEGOTIABLE)**: cálculo de impuestos,
  numeración y aislamiento multi-tenant son lógica crítica → tests escritos antes de la
  implementación y en verde antes de cerrar tareas. El PDF y la UI siguen el flujo estándar.
  **PASS**.
- **Principio V (Simplicidad / Hosting Compartido)**: se añade una sola dependencia
  (`barryvdh/laravel-dompdf`, pure-PHP, sin binarios). No se implementan tablas ni capacidades
  fuera del núcleo (pagos/stock/eventos/Verifactu quedan como columnas/relaciones futuras, no
  como código). DataTable client-side reutilizado. **PASS**.

No hay violaciones que requieran "Complexity Tracking".

## Project Structure

### Documentation (this feature)

```text
specs/005-facturas/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (rutas HTTP + contrato del servicio de cálculo)
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

Aplicación Laravel monolítica única. Reutiliza el patrón CRUD de `002-clientes-crm` para el
listado, y añade estructura nueva para el servicio de cálculo, la vista full-page y el PDF:

```text
app/
├── Enums/
│   ├── EstadoFactura.php          # borrador | emitida | pagada | vencida | anulada | rectificada
│   ├── TipoFactura.php            # ordinaria | simplificada | rectificativa (solo ordinaria en uso)
│   ├── FormaPago.php              # transferencia | tarjeta | efectivo | domiciliacion...
│   └── TipoImpuesto.php           # iva | igic | ipsi | recargo | irpf (para factura_impuestos)
├── Models/
│   ├── Serie.php                  # BelongsToTenant
│   ├── Factura.php                # BelongsToTenant + SoftDeletes; hasMany lineas/impuestos
│   ├── FacturaLinea.php           # BelongsToTenant
│   └── FacturaImpuesto.php        # BelongsToTenant
├── Http/
│   ├── Controllers/
│   │   └── FacturaController.php  # index(list/json), create, store, edit, update, destroy, pdf
│   └── Requests/
│       ├── StoreFacturaRequest.php
│       └── UpdateFacturaRequest.php
├── Services/
│   ├── CalculadoraFactura.php     # ÚNICA fuente de verdad de importes (bases/cuotas/desglose/total)
│   └── NumeradorFacturas.php      # asignación correlativa con lockForUpdate (diseñado; usado al emitir)
└── Support/
    └── TiposImpositivos.php       # (existente) tipos válidos por régimen; recargo asociado a cada tipo IVA

database/
├── migrations/
│   ├── ..._create_series_table.php
│   ├── ..._create_facturas_table.php          # incluye columnas Verifactu + ciclo B2B (sin usar)
│   ├── ..._create_factura_lineas_table.php
│   └── ..._create_factura_impuestos_table.php
├── factories/
│   ├── SerieFactory.php
│   ├── FacturaFactory.php
│   ├── FacturaLineaFactory.php
│   └── FacturaImpuestoFactory.php
└── seeders/
    └── SerieSeeder.php             # serie ordinaria por defecto por tenant (+ hook en DatabaseSeeder)

resources/views/
├── facturas/
│   ├── index.blade.php            # DataTable (patrón clientes/articulos)
│   ├── create.blade.php           # VISTA FULL-PAGE de creación/edición con preview en vivo
│   ├── _form.blade.php            # (parcial de secciones del formulario, si conviene dividir)
│   └── pdf.blade.php              # plantilla del PDF (dompdf)
├── partials/sidebar.blade.php     # enlace "Facturas"
└── (js) public/js/facturas-form.js  # lógica de líneas dinámicas + preview + recálculo cliente

routes/web.php                     # rutas: resource facturas (index/create/store/edit/update/destroy) + facturas.pdf

tests/
├── Unit/
│   ├── CalculadoraFacturaTest.php        # bases, cuotas por tipo, recargo (solo IVA), IRPF, redondeo, desglose
│   └── NumeradorFacturasTest.php         # correlatividad sin huecos + concurrencia (lock)
└── Feature/
    ├── FacturaCrudTest.php               # crear/editar/borrar borrador, validación, totales persistidos
    ├── FacturaTenantIsolationTest.php    # ≥2 tenants: no fuga en listado/detalle/PDF/update/destroy
    └── FacturaPdfTest.php                # PDF responde y refleja datos; deniega cross-tenant
```

**Structure Decision**: se reutiliza el patrón de controller dual Blade/JSON + DataTable del
listado (clientes/artículos). Las novedades estructurales son: (1) `app/Services/` con
`CalculadoraFactura` y `NumeradorFacturas` (Principio III/IV); (2) una **vista full-page** de
creación (`facturas/create.blade.php`) en lugar del modal usado en los CRUD anteriores, con JS
dedicado para líneas dinámicas y preview; (3) plantilla y ruta de **PDF** con
`barryvdh/laravel-dompdf`. Ver `research.md` para las decisiones (dompdf, congelado de régimen,
numeración diferida) y `data-model.md` para el detalle de tablas y validaciones.

## Complexity Tracking

> No hay violaciones de la constitución que justificar. Sección vacía a propósito.
