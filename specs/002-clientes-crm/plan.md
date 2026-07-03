# Implementation Plan: Gestión de Clientes (CRM)

**Branch**: `002-clientes-crm` | **Date**: 2026-07-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-clientes-crm/spec.md`

## Summary

Primer módulo de negocio del CRM: CRUD de `clientes` por tenant. Se añade la tabla `clientes`
(según `docs/03-modelo-datos.md`) con `tenant_id` y aislamiento automático vía el trait
`BelongsToTenant` de stancl/tenancy (el mismo mecanismo ya decidido en la feature 001). Se expone
una pantalla "Clientes" en el sidebar con 3 cartas de métricas del tenant (total, empresas,
particulares), una tabla DataTables en modo responsive, y un **modal Bootstrap único** reutilizado
para alta y edición (sin páginas `create`/`edit` separadas — los datos de edición viajan embebidos
en la fila vía atributos `data-*`, sin AJAX). Borrado lógico con confirmación previa. Validación
server-side incluyendo formato de NIF/CIF/NIE español y unicidad de NIF por tenant.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` ^3.10 (trait `BelongsToTenant` + `TenantScope`),
frontend NexaDash (Bootstrap 5, jQuery bundled en `vendor/global/global.min.js`), DataTables 1.x +
plugin Responsive (assets a trasplantar desde `template/`).

**Storage**: MySQL/MariaDB (prod); SQLite `:memory:` para tests. Base compartida single-database.

**Testing**: PHPUnit (feature tests con SQLite in-memory, ya configurado en phpunit.xml).

**Target Platform**: App web servida desde hosting compartido cPanel/Hostinger.

**Project Type**: Web application monolítica Laravel (backend + Blade views).

**Performance Goals**: pantalla utilizable con miles de clientes por tenant; tabla con
búsqueda/orden/paginación client-side sobre el render inicial (ver research D4).

**Constraints**: sin dependencias que requieran VPS (Principio V); assets servidos como estáticos
en `public/` (sin build step: el template es CSS/JS plano).

**Scale/Scope**: 50–80 tenants, miles de clientes cada uno. Alcance: solo entidad `clientes`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ `clientes` lleva `tenant_id` indexado y usa
  el trait `BelongsToTenant` (aplica `TenantScope` global + autofill de `tenant_id`). Tests de
  aislamiento con ≥2 tenants son obligatorios (ver Phase 1 / quickstart). El `super_admin` no opera
  esta pantalla (fuera de scope), así que no hay bypass del scope.
- **II. Cumplimiento Normativo España-First**: ✅ Los campos fiscales por defecto del cliente
  (`irpf_defecto`, `tipo_impositivo_defecto`, `aplica_recargo_equivalencia`) se modelan pero **no
  se hace cálculo de impuestos ni emisión** en esta feature (eso vive en facturas). Se valida el
  formato de NIF/CIF/NIE español. No se toca numeración, Verifactu ni inmutabilidad (no aplica a
  clientes). Sin conflicto.
- **III. Integridad Financiera Server-Side**: ✅ No hay cálculo de importes en esta feature. Toda
  validación (incluida unicidad de NIF y formato) es server-side; el cliente nunca es fuente de
  verdad. Las 3 métricas de las cartas se calculan en backend.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ El aislamiento multi-tenant es área
  crítica → tests de aislamiento (crear/listar/editar/borrar cruzado entre 2 tenants) se escriben
  antes de la implementación (Red-Green-Refactor). El resto del CRUD/UI sigue flujo de test más
  flexible.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: ✅ CRUD Laravel estándar (resource
  controller + Form Requests + Blade), sin paquetes nuevos. DataTables es JS estático servido
  desde `public/`. Sin build step ni servicios externos.

**Resultado**: PASS. Sin violaciones → sección Complexity Tracking vacía.

## Project Structure

### Documentation (this feature)

```text
specs/002-clientes-crm/
├── plan.md              # Este archivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   └── clientes-routes.md   # Contrato de rutas/HTTP de la UI
└── tasks.md             # Phase 2 (/speckit-tasks - NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Models/
│   └── Cliente.php                      # nuevo; usa BelongsToTenant + SoftDeletes
├── Enums/
│   └── TipoCliente.php                  # nuevo; empresa | particular
├── Http/
│   ├── Controllers/
│   │   └── ClienteController.php        # nuevo; resource controller
│   └── Requests/
│       ├── StoreClienteRequest.php      # nuevo; validación alta
│       └── UpdateClienteRequest.php     # nuevo; validación edición
└── Rules/
    └── NifEspanol.php                   # nuevo; regla de formato NIF/CIF/NIE

database/
├── migrations/
│   └── 2026_07_02_xxxxxx_create_clientes_table.php   # nuevo
├── factories/
│   └── ClienteFactory.php               # nuevo (para tests/seed)
└── seeders/
    └── ClienteSeeder.php                # opcional; clientes demo del tenant demo

resources/views/
├── clientes/
│   ├── index.blade.php                  # cartas + tabla DataTables responsive + modal único
│   └── _form.blade.php                  # parcial de campos, dentro del modal (alta y edición)
└── partials/
    └── sidebar.blade.php                # editado: enlace "Clientes"

public/
├── vendor/datatables/**                 # trasplantado desde template/
└── js/plugins-init/
    ├── clientes-datatable.init.js       # nuevo init propio (no el demo del template)
    └── clientes-modal.init.js           # nuevo; precarga el modal desde data-* o old()/errors

routes/web.php                           # editado: Route::resource('clientes', ...)->only([...])

tests/Feature/
├── ClienteTenantIsolationTest.php       # crítico (Principio IV) - test-first
└── ClienteCrudTest.php                  # CRUD + validación (NIF formato/único)
```

**Structure Decision**: Monolito Laravel con vistas Blade, siguiendo la estructura ya establecida
por la feature 001 (`app/Models`, `app/Enums`, `app/Http/Controllers`, `resources/views/<recurso>`,
`tests/Feature`). Se añade `app/Http/Requests` y `app/Rules` (aún no existían) para validación
server-side. Los assets de DataTables se trasplantan a `public/vendor/datatables` y se cargan por
página vía `@push('styles')`/`@push('scripts')` (el layout ya expone ambos stacks), sin resucitar
el sistema `config/dz.php` (prohibido por CLAUDE.md). Alta y edición usan un modal Bootstrap único
sobre `index.blade.php` (ver research D10), no páginas propias: `ClienteController` no expone
`create`/`edit` como rutas GET, solo `index`/`store`/`update`/`destroy`.

## Complexity Tracking

> Sin violaciones de la constitución. Sección vacía intencionalmente.
