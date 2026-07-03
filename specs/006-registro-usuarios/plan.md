# Implementation Plan: Registro y aprobación de usuarios

**Branch**: `006-registro-usuarios` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/006-registro-usuarios/spec.md`

## Summary

Añadir un flujo de auto-registro público que crea usuarios en estado `pendiente` (sin acceso) y
una vista de gestión de **Usuarios** (lista + cards informativas) donde cualquier usuario
aprobado del tenant puede aprobar/rechazar solicitantes. La aprobación habilita el login. Se
apoya en la autenticación existente (`activo` como compuerta de login) añadiendo una columna de
`estado` para la semántica pendiente/aprobado/rechazado. Aislamiento multi-tenant por filtrado
manual de `tenant_id` en el controlador (User no lleva scope global de tenant para no romper el
login). Ver [research.md](./research.md) para las decisiones.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: Laravel Auth (sesión), `stancl/tenancy` (single-database), Blade +
template NexaDash (layout `fullwidth` para registro, layout con sidebar para usuarios), toastr.

**Storage**: MySQL/MariaDB. Tabla `users` extendida (columna `estado`, `aprobado_por`,
`aprobado_en`).

**Testing**: PHPUnit/Pest (`php artisan test`). Test-first en aislamiento y en el gating de login
(Principio IV).

**Target Platform**: Web app sobre hosting compartido (cPanel/Hostinger), Principio V.

**Project Type**: Web application (monolito Laravel, Blade server-rendered).

**Performance Goals**: N/A (CRUD de bajo volumen; sin objetivos especiales).

**Constraints**: No romper el login existente; sin dependencias nuevas; compatible con hosting
compartido.

**Scale/Scope**: 1 tenant activo en esta fase, decenas de usuarios. 2 vistas nuevas (registro,
usuarios), 1 controlador de registro, 1 controlador de usuarios, 1 migración, 1 enum.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ La lista/cards/acciones de usuarios se
  filtran por `tenant_id` del usuario autenticado; se prohíbe operar sobre usuarios de otro
  tenant (404). Tests de aislamiento con ≥2 tenants obligatorios. Justificación de NO usar el
  scope global sobre `User` documentada en research (rompe login) — el aislamiento se garantiza
  igual en el controlador. No hay fuga.
- **II. Cumplimiento Normativo España-First**: N/A (no toca facturación/impuestos/Verifactu).
- **III. Integridad Financiera Server-Side**: N/A (no maneja importes).
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ Aislamiento entre tenants es área
  crítica → tests primero (rojo) antes de implementar. El gating de login (pendiente no accede)
  también se cubre con tests.
- **V. Simplicidad / Hosting Compartido**: ✅ Sin dependencias nuevas; se reutiliza `activo` y el
  login existente; un solo tenant (sin subdominios/alta de tenant). YAGNI respetado.

**Resultado**: PASA. Sin violaciones que registrar en Complexity Tracking.

**Re-check post-diseño (Phase 1)**: PASA. El diseño (columna `estado` + filtrado manual + tests
de aislamiento) mantiene todos los gates; no se introdujo complejidad no justificada.

## Project Structure

### Documentation (this feature)

```text
specs/006-registro-usuarios/
├── plan.md              # Este archivo
├── research.md          # Decisiones de diseño
├── data-model.md        # Cambios en la tabla users + enum
├── quickstart.md        # Guía de validación end-to-end
├── contracts/
│   └── http.md          # Rutas y comportamiento
└── checklists/
    └── requirements.md  # Checklist de calidad de la spec
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── EstadoUsuario.php                 # nuevo: pendiente|aprobado|rechazado
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── RegisterController.php     # nuevo: create/store (registro público)
│   │   │   └── LoginController.php         # modificado: mensaje para cuentas no aprobadas
│   │   └── UsuarioController.php           # nuevo: index, aprobar, rechazar
│   └── Requests/
│       └── RegisterRequest.php             # nuevo: validación de registro
├── Models/
│   └── User.php                            # modificado: fillable/casts estado, relaciones, helpers

database/
└── migrations/
    └── 2026_07_03_xxxxxx_add_estado_to_users_table.php   # nuevo

resources/views/
├── auth/
│   └── register.blade.php                  # nuevo: basado en page-register (layout fullwidth)
└── usuarios/
    └── index.blade.php                     # nuevo: lista + cards + acciones aprobar/rechazar

routes/web.php                              # modificado: rutas guest de registro + rutas usuarios

tests/
└── Feature/
    ├── RegistroTest.php                     # registro crea pendiente, login bloqueado
    └── UsuariosTest.php                     # aprobar/rechazar, no-self, aislamiento tenant
```

**Structure Decision**: Monolito Laravel existente. Se siguen los patrones ya presentes:
FormRequest para validación (como `StoreClienteRequest`), resolución manual de modelos en el
controlador para respetar el orden de middleware/tenant (patrón de `ClienteController`),
notificaciones por flash + toastr, vistas Blade sobre los layouts del template NexaDash.

## Complexity Tracking

No aplica: el Constitution Check pasa sin violaciones.
