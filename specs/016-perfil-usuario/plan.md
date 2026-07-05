# Implementation Plan: Vista de perfil de usuario (Mi perfil)

**Branch**: `016-perfil-usuario` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/016-perfil-usuario/spec.md`

## Summary

Falta la vista `resources/views/profile/show.blade.php` que ya renderiza el
`ProfileController@show` existente (`GET /perfil`). El objetivo es entregar esa vista dentro del
layout con sidebar del CRM, trasplantando **solo la cabecera** del template NexaDash
(`overview-profile.blade.php`) — card con avatar, badge de estado, nombre y meta en línea — y
mostrando los datos reales del usuario autenticado (nombre, email, rol, empresa/tenant, estado
de aprobación, fecha de alta). Incluye subir/reemplazar la foto de perfil contra el endpoint ya
existente `profile.avatar.update` (`POST /perfil/avatar`), con notificaciones toastr. Todo el
contenido demo del template (feed, comentarios, galerías, embeds, métricas de venta, proyectos,
to-dos, charts) se descarta. Sin cambios de base de datos, sin cálculo fiscal, sin nuevas rutas.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12, Blade

**Primary Dependencies**: Layout `layouts/app.blade.php` + partials NexaDash ya vendorizados;
toastr (`window.showToast`); `stancl/tenancy` (contexto de tenant ya activo)

**Storage**: N/A (sin migraciones). Avatar en disco `public` vía el flujo existente del controller

**Testing**: PHPUnit/Pest Feature test sobre `GET /perfil` (auth, datos propios, valores de
reserva). El avatar upload ya está cubierto por el endpoint existente.

**Target Platform**: Web (hosting compartido cPanel/Hostinger)

**Project Type**: Web application (Laravel monolito con Blade)

**Performance Goals**: Render de una sola página sin consultas pesadas; carga percibida instantánea

**Constraints**: Español en toda la UI; respetar tema claro/oscuro persistido; notificaciones
solo con toastr (nunca alerts ad-hoc); leer `docs/04-front-guidelines.md` antes de tocar la vista

**Scale/Scope**: 1 vista Blade nueva + (posible) 1 helper de presentación de labels; 1 Feature test

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant**: ✅ La vista resuelve siempre `auth()->user()`; no hay
  identificador de usuario en la ruta ni queries de negocio nuevas. `tenant()` se lee vía la
  relación del propio usuario. No hay riesgo de fuga entre tenants. Se añade un test que verifica
  que un usuario solo ve sus propios datos.
- **II. Cumplimiento Normativo**: ✅ N/A — no toca facturación, impuestos ni Verifactu.
- **III. Integridad Financiera Server-Side**: ✅ N/A — no hay importes.
- **IV. Test-First en Lógica Crítica**: ✅ No hay lógica crítica (aislamiento por `auth()`
  estándar, sin cálculo). Se incluye un Feature test de la vista, pero no aplica el mandato
  estricto Red-Green de las áreas críticas.
- **V. Simplicidad / Hosting Compartido**: ✅ Solución mínima: una vista Blade + presentación de
  labels de enums. Sin dependencias nuevas, sin infra dedicada. YAGNI respetado (perfil de solo
  lectura salvo foto; no se añade edición de nombre/email/password).

**Resultado**: PASS, sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/016-perfil-usuario/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1 (entidades de presentación, sin migraciones)
├── quickstart.md        # Fase 1 (guía de validación)
├── contracts/
│   └── profile-ui.md    # Fase 1 (contrato de la vista y del endpoint reutilizado)
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea este comando)
```

### Source Code (repository root)

```text
resources/views/profile/
└── show.blade.php                 # NUEVA — vista de perfil (cabecera NexaDash trasplantada)

app/Http/Controllers/
└── ProfileController.php          # EXISTENTE — show() y updateAvatar() ya implementados

app/Enums/
├── UserRole.php                   # EXISTENTE — se usa su label() para mostrar el rol
└── EstadoUsuario.php              # EXISTENTE — se usa su label()/color para el estado

routes/web.php                     # EXISTENTE — profile.show y profile.avatar.update ya definidos

tests/Feature/
└── ProfileTest.php                # NUEVA — GET /perfil autenticado, datos propios, aislamiento
```

**Structure Decision**: Monolito Laravel + Blade existente. La feature es puramente de capa de
presentación: una vista nueva bajo `resources/views/profile/` y un Feature test. Se reutilizan
controller, rutas y enums ya presentes; no se crean modelos, migraciones ni servicios.

## Complexity Tracking

> Sin violaciones de la constitución. No aplica.
