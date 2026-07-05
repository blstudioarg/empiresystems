# Implementation Plan: Alta de usuario administrador al crear un tenant

**Branch**: `020-tenant-admin-inicial` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/020-tenant-admin-inicial/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

Al crear un tenant desde el panel de super admin, el formulario de alta pide además el email y
la contraseña de un administrador inicial. `TenantController::store` crea, en la misma
transacción que ya crea el `Tenant` y su `Domain`, un `User` con `rol=admin`,
`estado=aprobado`, `activo=true`, asociado al `tenant_id` recién generado. No se tocan
migraciones (el modelo `User` ya tiene todos los campos necesarios) ni el sembrado de series
(`Tenant::booted()` ya lo hace). No se crean filas de `configuracion`.

## Technical Context

**Language/Version**: PHP 8.2 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database multi-tenancy), Laravel `Hash` facade,
Bootstrap modal + jQuery (front del panel de super admin ya existente)

**Storage**: MySQL/MariaDB — sin migraciones nuevas; reutiliza tablas `tenants`, `domains`, `users`

**Testing**: PHPUnit (`tests/Feature/SuperAdmin/TenantCrudTest.php`), `RefreshDatabase`

**Target Platform**: Hosting compartido (cPanel/Hostinger), servidor web Laravel

**Project Type**: Web application (backend Laravel + Blade/jQuery, monolito single-database)

**Performance Goals**: N/A (operación administrativa de baja frecuencia, sin requisito de
rendimiento específico)

**Constraints**: La operación completa (tenant + dominio + usuario admin) DEBE ser atómica
(FR-005); el email del admin es único por tenant pero al ser un tenant nuevo no hay colisión
posible dentro del mismo alta

**Scale/Scope**: Un formulario existente (`super_admin/tenants/_form.blade.php`), un controller
existente (`TenantController::store`), un form request existente (`StoreTenantRequest`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio I (Aislamiento Multi-Tenant)**: el `User` creado se asocia explícitamente al
  `tenant_id` del tenant recién creado dentro de la misma transacción; no hay global scope en
  `User` (igual que el resto del código existente: `UsuarioController` filtra manualmente por
  `tenant_id`). Se añaden tests de aislamiento (2 tenants, verificar que el admin de uno no es
  visible/autenticable en el otro). ✅ Cumple.
- **Principio II (Normativa España-First)**: no aplica — no toca facturación ni régimen
  impositivo. ✅ N/A.
- **Principio III (Integridad Financiera Server-Side)**: no aplica — no toca importes ni
  Verifactu. ✅ N/A.
- **Principio IV (Test-First en Lógica Crítica)**: el aislamiento multi-tenant del usuario
  administrador es lógica crítica → tests de aislamiento escritos antes de tocar el controller,
  deben fallar primero. ✅ Cumple (ver tasks.md).
- **Principio V (Simplicidad)**: no se introduce ninguna dependencia ni tabla nueva; se reutiliza
  el modelo `User` y sus enums (`UserRole::Admin`, `EstadoUsuario::Aprobado`) tal cual existen.
  ✅ Cumple.

Sin violaciones. No hace falta "Complexity Tracking".

## Project Structure

### Documentation (this feature)

```text
specs/020-tenant-admin-inicial/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/SuperAdmin/TenantController.php   # store(): + creación de User admin
│   └── Requests/SuperAdmin/StoreTenantRequest.php     # + admin_email, admin_password
├── Models/
│   ├── Tenant.php     # sin cambios (series por defecto ya se siembran en booted())
│   └── User.php       # sin cambios (ya soporta rol/estado/activo)

resources/views/super_admin/tenants/
└── _form.blade.php     # + campos admin_email / admin_password (solo visibles en alta)

public/js/plugins-init/
└── super-admin-tenants-modal.init.js   # mostrar/ocultar los campos admin_* según alta/edición

tests/Feature/SuperAdmin/
└── TenantCrudTest.php   # + casos de alta con admin, validación, aislamiento
```

**Structure Decision**: Aplicación web monolítica Laravel existente (sin frontend/backend
separados). Los cambios son quirúrgicos sobre archivos ya existentes: no se crean controllers,
modelos ni tablas nuevas. Reutiliza el patrón ya establecido en `UsuarioController` para
filtrado manual por `tenant_id` (Principio I) y en `TenantController::store` para transacciones
atómicas (FR-005).

## Complexity Tracking

> No aplica — Constitution Check no reportó violaciones.
