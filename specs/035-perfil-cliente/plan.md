# Implementation Plan: Perfil del cliente

**Branch**: `035-perfil-cliente` | **Date**: 2026-07-26 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/035-perfil-cliente/spec.md`

## Summary

Añadir una página de perfil de cliente (`GET /clientes/{cliente}`, accesible desde un nuevo
ítem "Ver perfil" en el dropdown de acciones del listado) que reúne, en pestañas (mismo patrón
Bootstrap `nav-tabs`/`tab-pane` que `resources/views/profile/show.blade.php`): datos generales
del cliente, un resumen financiero calculado sobre sus facturas, listados paginados de facturas/
presupuestos/albaranes/oportunidades del cliente, una línea de tiempo combinada de esos cuatro
tipos de evento, y accesos rápidos de alta preseleccionando el cliente. Requiere declarar las
relaciones inversas `hasMany` que hoy faltan en `Cliente` hacia esas cuatro entidades, y extender
la acción `create` de facturas para aceptar `cliente_id` por query string (como ya hacen
presupuestos y albaranes).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12)

**Primary Dependencies**: Laravel 12, `stancl/tenancy` (single-database, `BelongsToTenant` +
`TenantScope`), Spatie-style permisos vía `can:` middleware/`@can`, Bootstrap 5 (nav-tabs),
jQuery (interacciones puntuales, sin DataTable en las pestañas de este perfil).

**Storage**: MySQL/MariaDB, sin migraciones nuevas (no se agregan columnas ni tablas; solo
relaciones Eloquent nuevas sobre columnas `cliente_id` ya existentes en `facturas`,
`presupuestos`, `albaranes`, `oportunidades`).

**Testing**: PHPUnit (Feature tests), siguiendo el patrón existente en `tests/Feature/*`
(p. ej. `tests/Feature/SuperAdmin/TenantCrudTest.php`, `tests/Feature/Profile/*`) — tests de
aislamiento multi-tenant con ≥2 tenants (Principio I) y tests de permisos por pestaña.

**Target Platform**: Aplicación web server-rendered (Blade), hosting compartido tipo cPanel
(Principio V) — sin JS build step nuevo, sin llamadas AJAX por pestaña.

**Project Type**: Web application monolítica (Laravel + Blade), un solo proyecto.

**Performance Goals**: Carga de la página de perfil completa (todas las pestañas con permiso)
en un tiempo comparable al resto de páginas de listado del CRM; sin N+1 queries por fila (usar
`with()`/`withCount()` donde aplique).

**Constraints**: Sin dependencias nuevas; sin cambios de esquema de base de datos; debe respetar
permisos por módulo ya existentes (no se crean permisos nuevos); debe funcionar sin JS adicional
más allá de la inicialización de tabs ya usada en `profile/show.blade.php`.

**Scale/Scope**: 1 ruta nueva (`GET /clientes/{cliente}`), 1 método de controller, 4 relaciones
`hasMany` nuevas en `Cliente`, 1 vista Blade nueva con 6 pestañas, 1 pequeño ajuste en
`FacturaController::create` para aceptar `cliente_id` por query string, 1 ítem de menú nuevo en
el dropdown de acciones del listado de clientes.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)** — PASA. `Cliente`, `Factura`, `Presupuesto`,
  `Albaran` y `Oportunidad` ya usan `BelongsToTenant`; las nuevas relaciones `hasMany` heredan el
  `TenantScope` de cada modelo relacionado, no hace falta lógica de filtrado manual. El
  `findOrFail` del nuevo método `show` sigue el patrón ya usado en `update`/`destroy` de
  `ClienteController` (resolución manual dentro del controller, no binding implícito de ruta —
  ver `[[project_tenant_route_binding]]` en memoria del proyecto). Se añaden tests con ≥2 tenants
  que verifiquen que el perfil de un cliente de otro tenant responde 404.
- **II. Cumplimiento Normativo España-First** — PASA / no aplica cambio de alcance. No se tocan
  reglas de facturación, Verifactu ni régimen impositivo; el resumen financiero es de solo
  lectura sobre importes ya calculados y persistidos por el módulo de facturación (no
  recalcula impuestos). No introduce ni cambia el tratamiento de datos personales (los mismos
  campos de `Cliente` que ya se muestran hoy en el modal de edición): no aplica un plazo de
  retención nuevo.
- **III. Integridad Financiera Server-Side** — PASA. El resumen financiero (total facturado,
  pendiente de cobro, facturas vencidas, ticket medio) se calcula en el backend a partir de
  `Factura::totalCobrable()`/`montoCobrado()`/`estadoCobro()` ya existentes; la vista solo
  renderiza los valores ya calculados por el controller, sin cálculos de importes en el cliente.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)** — Aplica al aislamiento multi-tenant del
  nuevo `show` (test primero, ver Fase 1). El cálculo del resumen financiero reutiliza métodos ya
  cubiertos por tests existentes de `Factura`; no introduce cálculo de impuestos nuevo, por lo
  que no dispara la obligación de test-first de cálculo de impuestos (no se toca esa lógica).
- **V. Simplicidad y Compatibilidad con Hosting Compartido** — PASA. Sin dependencias nuevas, sin
  infraestructura adicional. Se descarta explícitamente una tabla de caché de "resumen
  financiero por cliente" (YAGNI): se calcula al vuelo en cada carga del perfil, igual que otros
  agregados del CRM (p. ej. dashboard de estadísticas).

**Resultado**: Ninguna violación. No se requiere la tabla "Complexity Tracking".

## Project Structure

### Documentation (this feature)

```text
specs/035-perfil-cliente/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md         # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command)
```

No se genera `contracts/`: esta feature no expone una API pública/contrato externo nuevo (una
sola ruta web `GET /clientes/{cliente}` que devuelve una vista Blade, protegida por el mismo
middleware `can:ver-clientes` que ya protege el resto del recurso `clientes`; no hay variante
JSON como la que tiene `index` porque esta vista no alimenta ningún DataTable).

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   ├── ClienteController.php        # + método show()
│   └── FacturaController.php        # create(): acepta ?cliente_id= (preselección)
└── Models/
    ├── Cliente.php                  # + facturas(), presupuestos(), albaranes(), oportunidades()
    ├── Factura.php                  # sin cambios de esquema; ya tiene cliente_id
    ├── Presupuesto.php              # sin cambios de esquema; ya tiene cliente_id
    ├── Albaran.php                  # sin cambios de esquema; ya tiene cliente_id
    └── Oportunidad.php              # sin cambios de esquema; ya tiene cliente_id

resources/views/
├── clientes/
│   └── show.blade.php               # nueva vista de perfil (pestañas)
└── ayuda/
    └── clientes.blade.php           # actualizar guía in-app si ya documenta el listado de clientes

public/js/plugins-init/
└── clientes-datatable.init.js       # + ítem "Ver perfil" en renderAcciones()

routes/web.php                       # + Route::get('/clientes/{cliente}', ...)->name('clientes.show')

tests/Feature/
└── Cliente/
    └── PerfilClienteTest.php        # nuevo: aislamiento tenant, permisos por pestaña, cifras
```

**Structure Decision**: Aplicación Laravel monolítica ya existente (`app/`, `resources/views/`,
`routes/web.php`, `tests/Feature/`); esta feature no introduce una estructura nueva, solo añade
archivos dentro de las carpetas ya usadas por el resto del CRM (Opción "Single project" adaptada
a las convenciones de este repo — no hay separación backend/frontend independiente).

## Complexity Tracking

*No aplica: el Constitution Check no registró violaciones.*
