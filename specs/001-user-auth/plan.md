# Implementation Plan: Autenticación de usuarios (login multi-tenant)

**Branch**: `001-user-auth` | **Date**: 2026-07-02 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-user-auth/spec.md`

## Summary

Login con email/contraseña sobre sesiones nativas de Laravel (sin starter kit), con "Recordarme",
logout, throttling de intentos y protección de todas las rutas internas. Cada usuario (salvo el
`super_admin` global) pertenece a un tenant; al autenticarse, un middleware inicializa el contexto
de tenant con `stancl/tenancy` (modo single-database, inicialización manual basada en el usuario
autenticado), dejando listo el mecanismo de scoping que usarán todos los modelos de negocio
futuros. La vista de login trasplanta la plantilla `page-login` de NexaDash sobre un nuevo layout
`guest` (fullwidth, sin sidebar), adaptada a español y branding Empire Systems. Un seeder crea el
super_admin, un tenant demo y su admin para poder entrar desde el día uno.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 12

**Primary Dependencies**: `laravel/framework ^12` (auth de sesión nativo, sin Breeze/Jetstream),
`stancl/tenancy ^3.10` (ya instalado; modo single-database con inicialización manual)

**Storage**: MySQL/MariaDB (`empire_crm` local vía XAMPP); SQLite `:memory:` para tests (ya
configurado en `phpunit.xml`)

**Testing**: PHPUnit 11 (`php artisan test`), feature tests con `RefreshDatabase`

**Target Platform**: hosting compartido cPanel/Hostinger (producción futura); `php artisan serve`
en desarrollo

**Project Type**: aplicación web Laravel monolítica (Blade server-side, assets NexaDash ya en
`public/`)

**Performance Goals**: sin requisitos especiales; login estándar web (SC-001: < 30s percibido)

**Constraints**: sin dependencias que requieran VPS (Principio V); sesiones en driver `database`
(ya configurado en `.env`); sin registro público ni recuperación de contraseña

**Scale/Scope**: 50–80 tenants esperados; esta feature: 1 pantalla (login), 1 middleware de
contexto, 2 migraciones, 1 seeder, ~8–10 feature tests

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Aislamiento Multi-Tenant | `users` y `tenants` son tablas **centrales** (sin `tenant_id` scope, según docs/03-modelo-datos.md), así que no llevan global scope. Esta feature **crea** el mecanismo de contexto (middleware + stancl) que los modelos de negocio usarán. Tests verifican que el contexto queda inicializado con el tenant correcto y que el super_admin opera sin contexto. | ✅ PASS |
| II. Cumplimiento Normativo España-First | No toca facturación ni impuestos. | ✅ N/A |
| III. Integridad Financiera Server-Side | No hay importes. Autenticación y validación de credenciales 100% server-side. | ✅ PASS |
| IV. Test-First en Lógica Crítica | Autenticación y contexto de tenant son lógica crítica → los feature tests se escriben **antes** de implementar (Red-Green-Refactor). El desglose de tareas debe ordenar tests antes que implementación. | ✅ PASS (obliga orden en tasks) |
| V. Simplicidad y Hosting Compartido | Sesiones nativas + driver database, sin colas ni servicios extra. stancl/tenancy ya instalado y elegido en docs/01-arquitectura.md; se usa solo su núcleo de contexto (sin bootstrappers de multi-DB). Sin starter kits que arrastren scaffolding innecesario. | ✅ PASS |

**Post-diseño (re-check tras Phase 1)**: sin cambios — el diseño no introduce violaciones ni
complejidad no justificada. Tabla Complexity Tracking vacía.

## Project Structure

### Documentation (this feature)

```text
specs/001-user-auth/
├── spec.md              # Especificación (hecha)
├── plan.md              # Este archivo
├── research.md          # Phase 0: decisiones técnicas
├── data-model.md        # Phase 1: entidades y migraciones
├── quickstart.md        # Phase 1: guía de validación end-to-end
├── contracts/
│   └── routes.md        # Phase 1: contrato de rutas HTTP
├── checklists/
│   └── requirements.md  # Checklist de calidad de la spec (hecho)
└── tasks.md             # Phase 2 (/speckit-tasks — todavía no)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Auth/
│   │       └── LoginController.php        # showLoginForm, login, logout
│   └── Middleware/
│       └── SetTenantContext.php           # inicializa tenancy desde el user autenticado
├── Models/
│   ├── Tenant.php                         # extiende stancl Tenant, int id, columnas propias
│   └── User.php                           # + tenant_id, rol, activo; relación tenant()
├── Enums/
│   └── UserRole.php                       # super_admin | admin | usuario
└── Listeners/
    └── LogAuthenticationActivity.php      # log de Login/Logout/Failed/Lockout (FR-014)

bootstrap/
└── app.php                                # registro middleware alias + grupo web

config/
└── tenancy.php                            # publicado de stancl, ajustado a single-DB

database/
├── migrations/
│   ├── xxxx_create_tenants_table.php      # tabla central tenants
│   └── xxxx_add_tenancy_fields_to_users_table.php  # tenant_id, rol, activo
└── seeders/
    ├── DatabaseSeeder.php                 # llama a AuthSeeder
    └── AuthSeeder.php                     # super_admin + tenant demo + admin demo

resources/views/
├── layouts/
│   └── guest.blade.php                    # fullwidth (sin sidebar) para páginas públicas
└── auth/
    └── login.blade.php                    # page-login adaptada (ES, Empire, sin registro/social)

routes/
└── web.php                                # rutas guest (login) + grupo auth (app)

public/images/
└── login.png                              # ilustración del template (copiar)

tests/Feature/Auth/
├── LoginTest.php                          # credenciales, mensajes, activo, throttle, remember
├── RouteProtectionTest.php                # redirecciones guest/auth, logout
├── TenantContextTest.php                  # contexto inicializado por tenant, super_admin sin contexto
└── SeederTest.php                         # usuarios iniciales pueden autenticarse
```

**Structure Decision**: estructura estándar de app Laravel monolítica (la que ya existe en el
repo). La autenticación vive en `app/Http/Controllers/Auth/`; el contexto de tenant es un
middleware propio delgado sobre stancl. Vistas Blade sobre los assets NexaDash ya presentes en
`public/`.

## Complexity Tracking

> Sin violaciones de la constitución que justificar. (El uso de stancl/tenancy con inicialización
> manual está previsto por docs/01-arquitectura.md Decisión 2 y por Additional Constraints de la
> constitución; no se usa scaffolding de auth de terceros.)
