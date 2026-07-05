# Implementation Plan: Pagos y cobros de facturas

**Branch**: `010-pagos-facturas` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/010-pagos-facturas/spec.md`

## Summary

Registro manual de cobros (parciales o totales) contra facturas **emitidas**, con saldo
pendiente y estado de cobro **derivados** de los pagos vigentes (no como columna editable), y
anulación soft de pagos mal registrados. Se materializa la tabla `pagos` ya diseñada en
`docs/03-modelo-datos.md` (Fase 2), un modelo `Pago` con scope de tenant, un servicio
`RegistroPagos` que centraliza validación y persistencia dentro de una transacción, y un
`PagoController` con rutas anidadas bajo facturas. El estado de cobro se expone como accessor
calculado sobre `Factura`, sin tocar la columna `estado` (fiscalmente inmutable en `emitida`).

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), Eloquent

**Storage**: MySQL/MariaDB (tabla nueva `pagos`)

**Testing**: PHPUnit (Feature + Unit), `RefreshDatabase`, factories existentes

**Target Platform**: Hosting compartido tipo cPanel/Hostinger (Principio V)

**Project Type**: Web (Laravel monolito, backend + Blade). Sin frontend nuevo obligatorio: la UI
mínima se limita a exponer estado de cobro en el listado de facturas ya existente.

**Performance Goals**: Operación CRUD estándar; saldo/estado de cobro visibles en < 5 s (SC-001/004).

**Constraints**: Importes en `DECIMAL(12,2)`; sin diferencias de redondeo al saldar (edge case);
aislamiento por tenant sin fugas (Principio I).

**Scale/Scope**: 1 tabla, 1 modelo, 1 enum (`EstadoCobro`), 1 servicio, 1 excepción, 1 controlador,
2-3 rutas, accessors en `Factura`. Sin pasarelas ni conciliación (fuera de alcance).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: `pagos` es tabla de negocio → lleva `tenant_id`
  indexado y `BelongsToTenant` (mismo patrón que `Factura`/`FacturaEvento`). Resolución manual en
  el controlador (`findOrFail`, no implicit binding) para no saltar el scope de tenant — ver memoria
  `project_tenant_route_binding`. Tests de aislamiento con ≥2 tenants obligatorios. ✅
- **II. Cumplimiento Normativo España-First**: no altera cálculo de impuestos ni numeración. Respeta
  inmutabilidad: una factura `emitida` **no cambia de `estado`** al cobrarse — el estado de cobro es
  un derivado aparte, no muta la factura fiscal. Solo `emitida` admite pagos (no `borrador`). ✅
- **III. Integridad Financiera Server-Side**: importe y saldo se validan/calculan en backend; el
  cliente nunca fija el saldo ni el estado de cobro. Registro y anulación dentro de transacción. ✅
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: aislamiento de tenant y la regla financiera
  (suma de pagos ≤ total, saldo exacto sin redondeo) son lógica crítica → tests primero, en rojo,
  luego implementación. ✅
- **V. Simplicidad / Hosting Compartido**: una tabla + servicio, sin dependencias nuevas ni
  infraestructura. Anulación soft con `anulado_at` (no tabla de auditoría aparte). YAGNI: sin
  conciliación bancaria, sin pago multi-factura, sin recordatorios. ✅

**Resultado**: PASS. Sin violaciones que justificar (Complexity Tracking vacío).

## Project Structure

### Documentation (this feature)

```text
specs/010-pagos-facturas/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1 (http.md)
├── checklists/
│   └── requirements.md  # (de /speckit-specify)
└── tasks.md             # Fase 2 (/speckit-tasks — no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── EstadoCobro.php                 # NUEVO: Pendiente | Parcial | Cobrada (derivado)
├── Exceptions/
│   └── PagoInvalidoException.php        # NUEVO
├── Models/
│   ├── Pago.php                         # NUEVO: BelongsToTenant, factura(), scope vigentes()
│   └── Factura.php                      # MOD: pagos(), pagosVigentes(), montoCobrado(),
│                                        #      saldoPendiente(), estadoCobro()
├── Services/
│   └── RegistroPagos.php                # NUEVO: registrar(Factura,$datos) / anular(Pago)
├── Http/
│   ├── Controllers/
│   │   └── PagoController.php           # NUEVO: store, anular (destroy)
│   └── Requests/
│       └── StorePagoRequest.php         # NUEVO: fecha, importe>0, metodo, referencia?
database/
├── migrations/
│   └── 2026_07_03_200000_create_pagos_table.php   # NUEVO
└── factories/
    └── PagoFactory.php                  # NUEVO (+ state anulado())
routes/
└── web.php                             # MOD: rutas pagos anidadas bajo facturas

resources/views/facturas/index.blade.php # MOD (US2): columna/badge estado de cobro
public/js/plugins-init/facturas-datatable.init.js # MOD (US2): render del estado de cobro

tests/Feature/
├── PagoRegistroTest.php                 # US1
├── PagoSaldoEstadoTest.php              # US2 (saldo/estado derivado, sin redondeo)
├── PagoAnulacionTest.php                # US3
└── PagoTenantIsolationTest.php          # Principio I
tests/Unit/
└── EstadoCobroTest.php                  # cálculo puro del estado (si se extrae lógica)
```

**Structure Decision**: Monolito Laravel existente. Se reutilizan los patrones ya establecidos por
las features 008/009 (servicio dedicado + excepción de dominio + FormRequest + controlador con
resolución manual del modelo). La feature es aditiva: no modifica el flujo de emisión ni la
numeración; solo añade la tabla `pagos` y accessors de solo lectura en `Factura`.

## Complexity Tracking

> Sin violaciones de la constitución. Sección vacía a propósito.
