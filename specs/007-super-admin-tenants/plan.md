# Implementation Plan: Panel Super Admin — Gestión de Tenants por Dominio

**Branch**: `007-super-admin-tenants` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/007-super-admin-tenants/spec.md`

## Summary

Dos cambios encadenados:

1. **Resolución de tenant por dominio.** Hoy el tenant activo se resuelve por el `tenant_id` del
   usuario autenticado (`SetTenantContext`). Esta feature lo cambia a resolución **por el host de
   la petición**: cada tenant tiene un registro en la tabla `domains` de `stancl/tenancy` (el
   `Domain::class` ya está cableado en `config/tenancy.php`). El host determina el tenant activo;
   en un dominio central (`central_domains`) no hay tenant. El login se refuerza: un usuario solo
   puede autenticarse desde el dominio de SU tenant (su `tenant_id` debe coincidir con el tenant
   del dominio).

2. **Panel super_admin.** Área nueva bajo el prefijo de ruta `super_admin`, accesible solo desde
   el dominio central y solo para usuarios con rol `super_admin` (`tenant_id` null). CRUD de
   tenants (listar, crear, editar, eliminar) con su dominio asociado (1:1) y sus datos fiscales
   básicos. La eliminación se bloquea si el tenant tiene facturas emitidas (Principio II), ofreciendo
   desactivar. Ver [research.md](./research.md) para las decisiones.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database; `Domain::class` + tabla `domains`),
Laravel Auth (sesión), Blade + template NexaDash (layout con sidebar para el panel), DataTables,
toastr, modal genérico de confirmación de borrado.

**Storage**: MySQL/MariaDB. Nueva tabla `domains` (migración estándar de stancl, en el grupo
central). Tabla `tenants` sin columnas nuevas (el dominio vive en `domains`).

**Testing**: PHPUnit/Pest (`php artisan test`). Test-first (Principio IV) en: resolución de tenant
por dominio, aislamiento entre tenants por dominio, gate login↔dominio, y regla de bloqueo de
borrado por facturas emitidas.

**Target Platform**: Web app sobre hosting compartido (cPanel/Hostinger), Principio V. El apuntado
DNS del dominio a la app lo hace el usuario en el hosting; la app solo gestiona la asociación
dominio→tenant.

**Project Type**: Web application (monolito Laravel, Blade server-rendered).

**Performance Goals**: N/A (CRUD de bajo volumen; decenas de tenants). Lookup de dominio por host
en cada request → indexado y cacheable.

**Constraints**: No romper el login ni el contexto de tenant existentes al migrar de resolución
por-usuario a por-dominio; sin dependencias nuevas (stancl ya está); compatible con hosting
compartido. El área super_admin es el único contexto que opera fuera del scope de un tenant
(excepción explícita del Principio I).

**Scale/Scope**: 50–80 tenants (docs/01), decenas de usuarios por tenant. 1 tabla nueva
(`domains`), 1 controlador super_admin, 1 FormRequest, 1 middleware nuevo o modificación de
`SetTenantContext`, 1 middleware de guard super_admin, vistas del panel (index + form), rutas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ **Se refuerza.** La resolución por dominio
  hace el aislamiento más estricto que el modelo anterior: el host fija el tenant y el login se
  valida contra él. El área super_admin es la **excepción explícita** que la constitución ya
  contempla ("salvo en contexto explícito de Super Admin"): opera en contexto central, sin scope de
  tenant, y solo para `super_admin`. Tests de aislamiento por dominio con ≥2 tenants obligatorios
  (acceso por dominio A nunca expone datos de B; usuario de B no entra por dominio A).
- **II. Cumplimiento Normativo España-First**: ✅ La eliminación de un tenant con facturas
  `emitida` se **impide** (FR-017); las facturas emitidas son inmutables y no se pierden por una
  operación de gestión de tenant. Se ofrece desactivar como alternativa.
- **III. Integridad Financiera Server-Side**: N/A (no maneja importes). La comprobación de
  "tiene facturas emitidas" se hace en backend.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ Resolución de tenant por dominio,
  aislamiento y gate login↔dominio son áreas críticas (fuga entre clientes) → tests primero (rojo)
  antes de implementar. La regla de bloqueo de borrado por facturas también se cubre con test.
- **V. Simplicidad / Hosting Compartido**: ✅ Sin dependencias nuevas (se usa `stancl/tenancy` ya
  presente y su tabla `domains` estándar). La app NO configura DNS (fuera de alcance); solo asocia
  dominio→tenant. Un solo dominio por tenant (YAGNI: no se modelan alias). Sin VPS ni permisos
  especiales.

**Resultado**: PASA. Sin violaciones que registrar en Complexity Tracking.

**Re-check post-diseño (Phase 1)**: PASA. El diseño (tabla `domains` de stancl + middleware de
resolución por host + guard super_admin + regla de borrado en backend + tests de aislamiento por
dominio) mantiene todos los gates. La única operación fuera de scope de tenant (super_admin) es la
excepción ya prevista por el Principio I.

## Project Structure

### Documentation (this feature)

```text
specs/007-super-admin-tenants/
├── plan.md              # Este archivo
├── research.md          # Decisiones de diseño (resolución por dominio, storage del dominio, guard)
├── data-model.md        # Tabla domains + relación Tenant↔Domain + reglas
├── quickstart.md        # Guía de validación end-to-end
├── contracts/
│   └── http.md          # Rutas del panel super_admin + comportamiento de resolución por dominio
└── checklists/
    └── requirements.md  # Checklist de calidad de la spec (ya creado)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── SuperAdmin/
│   │       └── TenantController.php        # nuevo: index, create, store, edit, update, destroy
│   ├── Middleware/
│   │   ├── SetTenantContext.php            # modificado: resolver tenant por host (Domain), no por user
│   │   └── EnsureSuperAdmin.php            # nuevo: exige rol super_admin + dominio central
│   └── Requests/
│       └── SuperAdmin/
│           ├── StoreTenantRequest.php      # nuevo: validación alta (dominio único + datos fiscales)
│           └── UpdateTenantRequest.php     # nuevo: validación edición
├── Models/
│   └── Tenant.php                          # modificado: relación domains(), helper para dominio único
├── Support/
│   └── DominioTenant.php                   # nuevo (opcional): normalización de host/dominio
└── Http/Controllers/Auth/
    └── LoginController.php                 # modificado: validar user.tenant_id == tenant del dominio

database/
└── migrations/
    └── 2026_07_xx_xxxxxx_create_domains_table.php   # nuevo: tabla domains de stancl (central)

resources/views/
└── super_admin/
    └── tenants/
        ├── index.blade.php                 # lista (DataTable + dropdown acciones + cards)
        └── form.blade.php                  # alta/edición (o create.blade.php + edit.blade.php)

routes/web.php                              # modificado: grupo super_admin (dominio central + guard)

tests/
└── Feature/
    ├── SuperAdmin/
    │   └── TenantCrudTest.php              # CRUD, guard de rol, borrado bloqueado por facturas
    └── TenantDomainResolutionTest.php      # resolución por host, aislamiento, login↔dominio
```

**Structure Decision**: Monolito Laravel existente. Se siguen los patrones ya presentes:
FormRequest para validación (como `StoreClienteRequest`), controlador RESTful, resolución de
tenant vía `tenancy()->initialize()` (que activa el global scope de `BelongsToTenant` en los
modelos de negocio), vistas Blade sobre el layout con sidebar del template NexaDash, DataTable +
dropdown de acciones + modal genérico de borrado + toastr (docs/04-front-guidelines.md). El área
super_admin se agrupa bajo su propio prefijo de ruta, middleware `EnsureSuperAdmin` y restricción
al dominio central.

## Complexity Tracking

No aplica: el Constitution Check pasa sin violaciones. El cambio de mecanismo de resolución de
tenant (de por-usuario a por-dominio) no añade complejidad injustificada: sustituye la lógica
existente por la idiomática de `stancl/tenancy` (tabla `domains` + resolución por host), que el
paquete ya soporta y la config ya cablea.
