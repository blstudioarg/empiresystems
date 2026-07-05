# Implementation Plan: Logs de actividad de usuarios

**Branch**: `021-logs-actividad-usuarios` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/021-logs-actividad-usuarios/spec.md`

## Summary

Nuevo ítem "Logs" en el dropdown de usuario que abre una vista con el historial de actividad del
tenant: login/logout y altas/bajas/modificaciones sobre clientes, artículos, facturas,
configuración y usuarios. Se materializa una tabla única `logs_actividad` (append-only, con
`BelongsToTenant` como el resto de tablas de negocio) alimentada por un servicio
`RegistradorActividad` invocado desde los controladores existentes y desde el listener de
autenticación ya presente (`LogAuthenticationActivity`). La vista usa, por primera vez en el
proyecto, una datatable con paginación **server-side real** (en vez del patrón client-side de
usuarios/facturas) porque este log crece sin cota temporal — ver decisión D1 en `research.md`.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), Eloquent. Sin
paquetes nuevos (sin Yajra DataTables: protocolo server-side implementado a mano, Principio V).

**Storage**: MySQL/MariaDB (tabla nueva `logs_actividad`)

**Testing**: PHPUnit (Feature + Unit), `RefreshDatabase`, factories existentes

**Target Platform**: Hosting compartido tipo cPanel/Hostinger (Principio V)

**Project Type**: Web (Laravel monolito, backend + Blade). Vista nueva (`resources/views/logs/index.blade.php`)
+ un ítem de menú en `partials/header.blade.php`.

**Performance Goals**: Listado de logs responde en < 2 s con cientos/miles de filas gracias a
paginación server-side (SC-004); resto de operaciones (registro de un evento) es una escritura
puntual sin impacto perceptible en la acción que la origina.

**Constraints**: Aislamiento por tenant sin fugas (Principio I) verificado también a nivel de
query del listado, no solo del global scope. Registro de eventos de solo lectura desde la UI
(FR-007) y append-only (FR-008): sin rutas de edición/borrado.

**Scale/Scope**: 1 tabla, 1 modelo, 2 enums (`AccionLogActividad`, `EntidadLogActividad`), 1
servicio (`RegistradorActividad`), 1 listener modificado (`LogAuthenticationActivity`), 1
controlador nuevo (`LogActividadController`), llamadas al servicio añadidas en 6 controladores
existentes (Cliente, Articulo, Configuracion, Usuario, Factura, Register), 1 vista, 1 JS de
datatable, 1 ítem de menú, rutas nuevas. Sin exportación, sin filtros avanzados, sin retención
configurable (fuera de alcance del MVP).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: `logs_actividad` es tabla de negocio →
  `tenant_id` indexado + `BelongsToTenant` (mismo patrón que `FacturaEvento`). El listado no usa
  route-model-binding (no hay un solo recurso, es una consulta agregada), pero el controlador
  filtra explícitamente por `auth()->user()->tenant_id` como capa defensiva adicional al global
  scope, igual que `UsuarioController@index` — ver memoria `project_tenant_route_binding`. Tests
  de aislamiento con ≥2 tenants obligatorios (US2), incluyendo variación de parámetros de
  búsqueda/orden/paginación de la datatable. ✅
- **II. Cumplimiento Normativo España-First**: no toca cálculo de impuestos, numeración ni
  Verifactu. Los eventos sobre facturas son un registro de auditoría general (quién hizo qué)
  complementario a `factura_eventos` (huella/encadenamiento Verifactu); no lo sustituye ni lo
  modifica. ✅
- **III. Integridad Financiera Server-Side**: no aplica (no hay importes ni impuestos en esta
  feature). El contenido de cada evento se decide y persiste siempre en el backend, nunca lo
  envía el cliente. ✅
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: el aislamiento multi-tenant del listado
  de logs es lógica crítica (fuga de datos entre tenants) → tests primero, en rojo, luego
  implementación. El resto (redacción de descripciones, render de la UI) sigue el flujo estándar
  del proyecto. ✅
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: sin dependencias nuevas; reutiliza
  los patrones ya establecidos (servicio dedicado + `BelongsToTenant` + controlador con query
  explícita de tenant). La única desviación de un patrón existente es la paginación server-side
  real del listado (en vez de carga completa client-side como en usuarios/facturas), justificada
  por el crecimiento no acotado del log en el tiempo — ver D1 en `research.md`. Sin exportación,
  sin retención configurable, sin vista cross-tenant para Super Admin (fuera de alcance del MVP
  según `docs/00-vision.md`). ✅

**Resultado**: PASS. Sin violaciones que justificar (Complexity Tracking vacío).

## Project Structure

### Documentation (this feature)

```text
specs/021-logs-actividad-usuarios/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md         # Fase 1
├── quickstart.md         # Fase 1
├── contracts/
│   └── http.md          # Fase 1
├── checklists/
│   └── requirements.md  # (de /speckit-specify)
└── tasks.md              # Fase 2 (/speckit-tasks — no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── AccionLogActividad.php           # NUEVO: Login | Logout | Alta | Baja | Modificacion
│   └── EntidadLogActividad.php          # NUEVO: Cliente | Articulo | Factura | Configuracion | Usuario
├── Models/
│   └── LogActividad.php                 # NUEVO: BelongsToTenant, sin SoftDeletes (append-only)
├── Services/
│   └── RegistradorActividad.php         # NUEVO: registrar(User, accion, entidad?, id?, descripcion)
├── Listeners/
│   └── LogAuthenticationActivity.php    # MOD: además de Log::info, persiste en logs_actividad
├── Http/
│   └── Controllers/
│       ├── LogActividadController.php   # NUEVO: index (vista + JSON server-side)
│       ├── ClienteController.php        # MOD: registrar alta/baja/modificación
│       ├── ArticuloController.php       # MOD: registrar alta/baja/modificación
│       ├── ConfiguracionController.php  # MOD: registrar modificación (4 tabs)
│       ├── UsuarioController.php        # MOD: registrar alta/baja (aprobar/rechazar)
│       ├── FacturaController.php        # MOD: registrar alta/baja/modificación/emisión/rectificación
│       └── Auth/RegisterController.php  # MOD: registrar alta de usuario (registro pendiente)
database/
├── migrations/
│   └── 2026_07_04_000000_create_logs_actividad_table.php   # NUEVO
└── factories/
    └── LogActividadFactory.php          # NUEVO
routes/
└── web.php                              # MOD: GET /logs -> logs.index

resources/views/
├── logs/
│   └── index.blade.php                  # NUEVO
└── partials/
    └── header.blade.php                 # MOD: ítem "Logs" en el dropdown de usuario

public/js/plugins-init/
└── logs-datatable.init.js               # NUEVO: datatable server-side (draw/start/length/order)

tests/Feature/
├── LogActividadRegistroTest.php         # US1: eventos se registran (login/logout + CRUD por entidad)
├── LogActividadListadoTest.php          # US1/US3: listado, búsqueda y orden server-side
├── LogActividadTenantIsolationTest.php  # US2 — Principio I
└── LogActividadUsuarioEliminadoTest.php # Edge case: nombre de usuario se conserva
```

**Structure Decision**: Monolito Laravel existente. Se reutilizan los patrones ya establecidos
(servicio dedicado tipo `RegistroPagos`/`EmisorFacturas`, modelo con `BelongsToTenant` tipo
`FacturaEvento`, controlador con query explícita de tenant tipo `UsuarioController`). La feature es
aditiva sobre los controladores existentes (una llamada al servicio al final de cada acción
relevante), sin introducir Observers ni Model Events nuevos — consistente con que el proyecto no
usa ese patrón en ningún otro punto.

## Complexity Tracking

> Sin violaciones de la constitución. Sección vacía a propósito.
