# Implementation Plan: Catálogo de Productos/Servicios

**Branch**: `004-productos-servicios` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-productos-servicios/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

CRUD de catálogo de artículos (`articulos`: producto/servicio) por tenant, siguiendo exactamente
el patrón ya construido en `002-clientes-crm` (modelo con `BelongsToTenant`, FormRequests con
reglas condicionales, controller JSON+Blade para DataTables, vista con cards + tabla responsive,
tests de aislamiento multi-tenant). La única pieza nueva de dominio es que el tipo impositivo del
artículo debe validarse contra el `regimen_impositivo` del tenant activo (IVA/IGIC/IPSI) en vez de
asumir IVA. Como prerrequisito técnico, se añade la columna `regimen_impositivo` a `tenants`
(documentada en `03-modelo-datos.md` pero no implementada por la feature 001), con `iva` como
default para tenants existentes.

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 12)

**Primary Dependencies**: Laravel 12, `stancl/tenancy` (single-database, `BelongsToTenant` +
`TenantScope`), DataTables (jQuery, ya vendorizado en `public/vendor/datatables`), toastr
(`public/vendor/toastr` + `public/js/toastr-config.js`)

**Storage**: MySQL/MariaDB — nueva tabla `articulos` + columna `regimen_impositivo` en `tenants`

**Testing**: PHPUnit (Feature tests), mismo patrón que `tests/Feature/ClienteCrudTest.php` y
`tests/Feature/ClienteTenantIsolationTest.php`

**Target Platform**: Hosting compartido tipo cPanel (Principio V) — Linux + Apache/Nginx, sin VPS

**Project Type**: Web application monolítica (Laravel + Blade), sin frontend separado

**Performance Goals**: Sin requisitos especiales más allá de los ya cubiertos por DataTables
server-agnostic (carga vía JSON, paginación/orden/búsqueda en cliente sobre el dataset del
tenant); no se espera un volumen de artículos que requiera server-side processing en esta fase

**Constraints**: Debe convivir con hosting compartido (Principio V); ningún cálculo de importes
de factura se hace en esta feature (solo se persiste el dato base para uso futuro)

**Scale/Scope**: Alcance acotado a la entidad `articulos` (CRUD + catálogo de tipos impositivos
por régimen); no incluye `movimientos_stock`, `factura_lineas` ni ninguna tabla de facturación

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio I (Aislamiento Multi-Tenant, NON-NEGOTIABLE)**: `articulos` lleva `tenant_id`
  indexado y el modelo usa `BelongsToTenant` (mismo mecanismo que `Cliente`), cubierto por el
  global scope de tenancy. Se replican tests de aislamiento (≥2 tenants) igual que
  `ClienteTenantIsolationTest`. **PASS**.
- **Principio II (Cumplimiento Normativo España-First)**: esta feature es la primera en tratar el
  impuesto indirecto como agnóstico al régimen (IVA/IGIC/IPSI) fuera del campo suelto ya existente
  en `clientes.tipo_impositivo_defecto` (que no se validaba contra ningún régimen). Aquí se
  introduce la validación real: el backend resuelve los tipos válidos según
  `tenant.regimen_impositivo` y rechaza cualquier alta/edición fuera de ese conjunto. **PASS**
  (cumple, no contradice — de hecho materializa el principio por primera vez en código).
- **Principio III (Integridad Financiera Server-Side)**: no aplica cálculo de totales en esta
  feature (no hay líneas de factura todavía); el precio y tipo impositivo son datos maestros, no
  importes calculados. **N/A / PASS**.
- **Principio IV (Test-First en Lógica Crítica, NON-NEGOTIABLE)**: el aislamiento multi-tenant y
  la validación de tipo impositivo por régimen son lógica crítica de cumplimiento → tests
  escritos antes de la implementación (aislamiento + validación de régimen). El resto del CRUD
  (UI, formularios) sigue el flujo estándar del proyecto. **PASS**.
- **Principio V (Simplicidad / Hosting Compartido)**: no se añade infraestructura nueva; se
  reutiliza DataTables client-side y el stack ya vendorizado. Los tipos impositivos por régimen se
  codifican como catálogo fijo en PHP (enum/config), no una tabla nueva, evitando complejidad
  innecesaria para el alcance actual. **PASS**.

No hay violaciones que requieran "Complexity Tracking".

## Project Structure

### Documentation (this feature)

```text
specs/004-productos-servicios/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

Aplicación Laravel monolítica única (mismo patrón que `002-clientes-crm`, sin frontend separado):

```text
app/
├── Enums/
│   ├── TipoArticulo.php              # producto | servicio (nuevo, paralelo a TipoCliente)
│   └── RegimenImpositivo.php         # iva | igic | ipsi (nuevo, resuelto en tenant)
├── Models/
│   ├── Articulo.php                  # nuevo, BelongsToTenant + SoftDeletes (paralelo a Cliente)
│   └── Tenant.php                    # se añade regimen_impositivo a $fillable/casts
├── Http/
│   ├── Controllers/
│   │   └── ArticuloController.php    # nuevo, paralelo a ClienteController (index/store/update/destroy)
│   └── Requests/
│       ├── StoreArticuloRequest.php  # nuevo
│       └── UpdateArticuloRequest.php # nuevo
└── Support/
    └── TiposImpositivos.php          # catálogo fijo por régimen (iva/igic/ipsi → tipos válidos)

database/
├── migrations/
│   ├── ..._add_regimen_impositivo_to_tenants_table.php   # nuevo (prerrequisito)
│   └── ..._create_articulos_table.php                    # nuevo
├── factories/
│   └── ArticuloFactory.php           # nuevo
└── seeders/
    └── ArticuloSeeder.php            # nuevo (opcional, paralelo a ClienteSeeder)

resources/views/
├── articulos/
│   └── index.blade.php               # nuevo, paralelo a resources/views/clientes/index.blade.php
└── partials/sidebar.blade.php        # se añade enlace "Productos/Servicios"

routes/web.php                        # se añaden rutas resource articulos (mismo patrón clientes)

tests/Feature/
├── ArticuloCrudTest.php              # nuevo, paralelo a ClienteCrudTest
└── ArticuloTenantIsolationTest.php   # nuevo, paralelo a ClienteTenantIsolationTest
```

**Structure Decision**: se replica al 100% la estructura de `002-clientes-crm` (Model + FormRequests
+ Controller único con respuesta dual Blade/JSON + vista con cards y DataTable + tests Feature de
CRUD y de aislamiento). La única pieza estructural nueva es `app/Support/TiposImpositivos.php`, un
catálogo fijo (no una tabla) que resuelve los tipos impositivos válidos por régimen — ver
`research.md` para la justificación de por qué no es una tabla de base de datos.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
