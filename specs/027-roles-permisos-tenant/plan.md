# Implementation Plan: Sistema de roles y permisos por tenant

**Branch**: `027-roles-permisos-tenant` | **Date**: 2026-07-06 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/027-roles-permisos-tenant/spec.md`

## Summary

Sistema de roles dinámicos por tenant sobre `spatie/laravel-permission` v6 con la feature
**teams** usando `tenant_id` como team key. El catálogo de permisos es global (una vista del
sidebar = un permiso, agrupado por módulo) y lo siembra un seeder idempotente; los roles son
por tenant y se gestionan desde una vista nueva (cards + datatable + modal). Enforcement
doble: sidebar con `@can` (oculta entradas) y rutas con middleware `can:` (403 en servidor).
El alta de tenant aprovisiona el rol "Administrador" con todos los permisos dentro de la
transacción existente; una migración de datos cubre los tenants ya creados. El super admin
central queda fuera con `Gate::before`. Ver decisiones en [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.2, Laravel 12

**Primary Dependencies**: `spatie/laravel-permission` ^6 (NUEVA), `stancl/tenancy` ^3.10
(existente), NexaDash (datatables, modales, toastr — assets ya vendorizados)

**Storage**: MySQL/MariaDB single-database; tablas de spatie (`permissions`, `roles` con
`tenant_id`, pivotes) — ver [data-model.md](data-model.md)

**Testing**: PHPUnit 11 (`php artisan test`), Feature tests con ≥2 tenants (patrón existente
en `tests/Feature/`)

**Target Platform**: hosting compartido cPanel (Principio V); sin dependencias nuevas de
infraestructura (cache de spatie sobre el store `database` existente)

**Project Type**: web app Laravel monolítica (Blade + endpoints JSON del mismo controller)

**Performance Goals**: sin objetivos nuevos; el cache de permisos de spatie evita queries por
check en cada request

**Constraints**: aislamiento estricto por tenant (Principio I); equivalencia de accesos tras
la migración (SC-005); anti-lockout server-side (FR-006)

**Scale/Scope**: ~17 permisos de catálogo, roles por tenant (unidades), 1 vista nueva,
1 middleware modificado, refactor de sidebar + rutas, 1 migración de datos

## Constitution Check

*GATE: evaluado contra constitution v1.2.0 — PASS (pre-research y post-diseño).*

| Principio | Evaluación |
|---|---|
| I. Aislamiento multi-tenant (NON-NEGOTIABLE) | ✅ Roles particionados por `tenant_id` vía spatie teams; contexto seteado en `SetTenantContext` (punto único, D2); resolución manual de modelos en controllers (pitfall de implicit binding + TenantScope); filtro explícito por tenant como defensa en profundidad; **tests con ≥2 tenants obligatorios** (quickstart). `permissions` es catálogo global de la app, no dato de tenant — no requiere `tenant_id` (análogo a `provincias`). |
| II. Cumplimiento normativo / RGPD | ✅ No introduce datos personales nuevos ni facturación. Accesos denegados (403) ya registrados por `logs_actividad` (feature 021). Sin retención nueva requerida (data-model.md). |
| III. Integridad server-side | ✅ Autorización 100% en backend: middleware `can:` en rutas + validaciones anti-lockout en servidor; ocultar en sidebar es solo UX (FR-004). |
| IV. Test-first en lógica crítica (NON-NEGOTIABLE) | ✅ Aislamiento de roles entre tenants + enforcement 403 + provisión atómica + anti-lockout son lógica crítica: tests primero (Red-Green-Refactor). UI (datatable/modal) con flujo flexible. |
| V. Simplicidad / hosting compartido | ✅ 1 dependencia madura en vez de sistema casero; sin Redis/colas nuevas; granularidad por vista (no por acción) — YAGNI; se reutiliza middleware, patrón de controllers `wantsJson()` y banco NexaDash existentes. |

**Workflow**: spec → plan → tasks → implement respetado; desviaciones: ninguna
(Complexity Tracking vacío).

## Project Structure

### Documentation (this feature)

```text
specs/027-roles-permisos-tenant/
├── plan.md              # Este archivo
├── research.md          # Fase 0 — decisiones D1–D9
├── data-model.md        # Fase 1 — tablas spatie + reglas RN-01..RN-05
├── quickstart.md        # Fase 1 — guía de validación
├── contracts/
│   └── http.md          # Fase 1 — endpoints de roles/asignación + enforcement
└── tasks.md             # Fase 2 (/speckit-tasks — pendiente)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── RolController.php                    # NUEVO — CRUD roles (HTML+JSON)
│   │   ├── UsuarioController.php                # MOD — asignación de rol (PATCH rol)
│   │   └── SuperAdmin/TenantController.php      # MOD — provisión rol Administrador en store()
│   ├── Middleware/SetTenantContext.php          # MOD — setPermissionsTeamId (D2)
│   └── Requests/
│       ├── StoreRolRequest.php                  # NUEVO
│       └── UpdateRolRequest.php                 # NUEVO
├── Models/User.php                              # MOD — trait HasRoles
├── Providers/AppServiceProvider.php             # MOD — Gate::before super admin; quitar gate gestiona-fichajes (D4, D5)
└── Support/
    ├── CatalogoPermisos.php                     # NUEVO — fuente de verdad del catálogo (D3)
    └── ProvisionadorRoles.php                   # NUEVO — crea/sincroniza rol Administrador (D6)

config/permission.php                            # NUEVO — publicado, teams=true, team_foreign_key=tenant_id
database/
├── migrations/
│   ├── xxxx_create_permission_tables.php        # NUEVA — publicada de spatie (adaptada a tenant_id)
│   └── xxxx_provisionar_roles_tenants_existentes.php  # NUEVA — migración de datos (FR-008)
└── seeders/PermisosSeeder.php                   # NUEVO — idempotente (RN-04)

resources/views/
├── roles/
│   ├── index.blade.php                          # NUEVA — cards + datatable + modales
│   └── _modales.blade.php                       # NUEVA
├── partials/sidebar.blade.php                   # MOD — @can por entrada (FR-003)
└── usuarios/index.blade.php                     # MOD — columna/select de rol

routes/web.php                                   # MOD — grupo /roles + middleware can: por sección (FR-004)
public/js/plugins-init/roles.init.js             # NUEVO — datatable + modal + AJAX

tests/
├── Feature/
│   ├── RolesTest.php                            # NUEVO — CRUD + validaciones + anti-lockout
│   ├── RolesAislamientoTest.php                 # NUEVO — 2 tenants (Principio I)
│   ├── SidebarPermisosTest.php                  # NUEVO — visibilidad menú + 403 por ruta
│   ├── AsignacionRolUsuarioTest.php             # NUEVO
│   └── SuperAdmin/TenantCrudTest.php            # MOD — alta aprovisiona rol (FR-007)
└── Unit/CatalogoPermisosTest.php                # NUEVO — catálogo cubre todas las rutas protegidas

docs/
├── 00-vision.md / 03-modelo-datos.md            # MOD al cierre — nuevo módulo y tablas
└── 04-front-guidelines.md                       # MOD al cierre — procedimiento "menú nuevo ⇒ permiso nuevo" (FR-013)
```

**Structure Decision**: monolito Laravel existente; se siguen los patrones ya establecidos
(controller único HTML+JSON con `wantsJson()`, vistas Blade + `_modales`, init JS por vista,
Support/ para servicios de dominio ligeros).

## Complexity Tracking

Sin violaciones que justificar.
