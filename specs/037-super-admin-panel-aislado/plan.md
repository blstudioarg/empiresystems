# Implementation Plan: Panel de Super Admin aislado + home con estadísticas de tenants

**Branch**: `037-super-admin-panel-aislado` | **Date**: 2026-07-27 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/037-super-admin-panel-aislado/spec.md`

## Summary

Dos entregas sobre el área `super_admin` (dominio central), sin tocar el negocio del tenant:

1. **Aislamiento (P1)** — middleware nuevo `BloquearSuperAdminAreaTenant` colgado del **grupo**
   `['tenant.context', 'auth']` de `routes/web.php`: cualquier ruta del área de tenant queda vedada
   al Super Admin (redirect + toast si es navegación, `403` JSON si es AJAX), con allowlist mínima
   (`logout`, `profile.*`, `localidades.index`). Al ir en el grupo y no sección por sección, toda
   ruta futura nace bloqueada (SC-002). La causa raíz —`Gate::before` concediéndole todos los `can:`
   (`AppServiceProvider.php:35`)— **no se toca**: es deseada y está cubierta por
   `SuperAdminBypassTest`; lo que faltaba era una regla de *contexto*, no de permiso (research D1).
2. **Home del panel (P2/P3)** — ruta `GET /super_admin` (`super_admin.home`) servida por
   `SuperAdmin\PanelController@index` sobre un servicio nuevo `App\Services\EstadisticasTenants`
   (espejo de `DashboardEstadisticas`): tarjetas de métricas, gráfico Chart.js de altas por mes
   (12 meses), últimos tenants creados, ranking por tamaño y bloque "requieren atención". Se
   convierte en el aterrizaje por defecto sin tocar `LoginController` (la raíz `/` cae en el
   middleware del punto 1, que redirige aquí) y se elimina la rama de super admin —ya inalcanzable—
   de `DashboardController@index`.

Sin migraciones, sin tablas, sin columnas, sin dependencias nuevas, sin permisos nuevos.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database), `spatie/laravel-permission`, Blade + jQuery, Chart.js (ya vendorizado en `public/vendor/chartjs/`), lordicon (`<x-lordicon>`), toastr

**Storage**: MySQL/MariaDB — solo lectura agregada de tablas existentes (`tenants`, `domains`, `users`, `facturas`, `logs_actividad`). **Ninguna migración.**

**Testing**: PHPUnit (`tests/Feature/SuperAdmin/…`), incluyendo la convención de test de aislamiento multi-tenant (≥2 tenants) del Principio I

**Target Platform**: aplicación web servida por Laravel (navegadores modernos de escritorio)

**Project Type**: monolito Laravel + Blade (sin separación front/back)

**Performance Goals**: la home resuelve sus indicadores en un número **fijo** de consultas agregadas (~6), independiente del número de tenants; objetivo SC-006 (<2 s con 100 tenants / 1.000 usuarios)

**Constraints**: hosting compartido (Principio V) — sin caché externa, sin precálculo, sin librerías nuevas; el bloqueo debe funcionar igual para navegación y para AJAX (el front del proyecto usa ambos)

**Scale/Scope**: 1 middleware nuevo, 1 controller nuevo, 1 servicio nuevo, 1 vista nueva + 1 init JS + 1 archivo de ayuda, 1 entrada de menú en la rama de Super Admin, 2 ediciones puntuales (`routes/web.php`, `DashboardController`), ~6 archivos de test

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Estado | Justificación |
|-----------|--------|---------------|
| **I. Aislamiento Multi-Tenant** (NON-NEGOTIABLE) | ✅ PASA — y lo refuerza | La feature existe justamente para cerrar una vía por la que un usuario **sin tenant** alcanzaba pantallas de negocio sin contexto de tenant. El panel opera en la excepción explícita "contexto de Super Admin" que el propio principio contempla, y **solo consulta agregados** (recuentos), nunca detalle de negocio de un tenant (FR-019). Toda consulta de `facturas`/`logs_actividad`/`users` filtra `tenant_id` explícitamente y se agrupa. Se exigen tests con ≥2 tenants (US1 escenarios 4–6, ranking y atención). |
| **II. Cumplimiento Normativo España-First** | ✅ APLICA PARCIALMENTE (registro de accesos) | No toca facturación, impuestos, series ni Verifactu. Sí aplica la parte de RGPD/LOPDGDD del principio sobre **registro de accesos denegados con IP y user-agent**: FR-008, resuelto en el diario técnico de la aplicación en vez de en `logs_actividad` (tabla de tenant, `tenant_id` NOT NULL) — ver research D4. No se crea ninguna tabla de datos personales, así que no hay plazo de retención nuevo que definir. |
| **III. Integridad Financiera Server-Side** | ✅ NO APLICA (espíritu respetado) | No hay cálculo de importes de factura. Los agregados que muestra la home (recuento de documentos emitidos) se calculan **en el servidor** y el cliente solo pinta; el gráfico no recalcula nada. |
| **IV. Test-First en Lógica Crítica** (NON-NEGOTIABLE) | ✅ PASA | El área crítica tocada es el aislamiento: los tests de bloqueo (US1) y de no-fuga de datos entre tenants se escriben **antes** de la implementación y deben fallar primero — marcado explícitamente en `tasks.md`. La UI de la home sigue el flujo flexible que el propio principio permite. |
| **V. Simplicidad y Hosting Compartido** | ✅ PASA | Cero dependencias nuevas (Chart.js ya está vendorizado y en uso), cero migraciones, cero tablas, cero caché/precálculo, cero cambios de infraestructura de dominios. Se descartó explícitamente hacer `tenant_id` nullable en `logs_actividad` y crear un subdominio propio para el panel (research D1, D4). |

**Resultado del gate (pre-Phase 0)**: PASA sin violaciones → "Complexity Tracking" vacío.

**Re-evaluación post-Phase 1**: PASA. El diseño de Phase 1 no introdujo tablas, dependencias ni
capas nuevas respecto a lo evaluado arriba. Dos decisiones con impacto en convenciones —no en la
constitución— quedan registradas y se documentan como parte de la feature:
(a) los widgets de resumen de la home usan `<table>` + `@foreach` como el dashboard del tenant, no
DataTable (research D6.1, excepción a anotar en `docs/04-front-guidelines.md`);
(b) FR-008 registra en el diario técnico de la aplicación, no en `logs_actividad` (research D4, ya
reflejado en `spec.md`).

## Cumplimiento de `docs/04-front-guidelines.md` (REGLA DE ORO de CLAUDE.md)

Documentación leída **antes** de escribir spec y plan; convenciones citadas con la sección exacta y
su exigencia, para que quede trazable (detalle completo en research D6):

| Sección de la guía | Qué exige | Cómo lo cumple esta feature |
|---|---|---|
| "Listados: SIEMPRE DataTable, nunca una `<table>` plana" | Todo listado de registros va en DataTable | El **listado de gestión de tenants** sigue siendo DataTable y no se toca. Los widgets de resumen de la home (top-5) son dashboard, no listado de módulo: mismo patrón que "Facturas recientes" en `partials/dashboard-contenido.blade.php`. Excepción a **documentar en la guía** (tarea de la feature). |
| "Icono flotante en cards informativas (métricas)" | `<x-lordicon>` en un `<div>` sin clases, nunca `icon-box bg-primary-light` | Las 5 tarjetas de métricas de la home lo siguen literalmente (`size="50"`, `trigger="hover"`, `target=".card"`). |
| "Colores de los lordicon" | Nunca pasar `colors` a mano | No se pasa; lo resuelve el componente con la paleta efectiva. |
| "Gap entre `.row` y `margin-bottom` de `.card`" | No añadir `mb-3`/`g-3` a columnas | La grid de la home usa `.row`/`.col-*` limpias. |
| "Tamaño de formularios: todo sm por defecto" | No añadir `.btn-sm`/`.form-control-sm` | Los enlaces/botones de la home van sin clases de tamaño. |
| "Notificaciones" (CLAUDE.md) + `partials/flash-toastr` | Siempre toastr, nunca `.alert` ad-hoc | El aviso del bloqueo es `->with('warning', …)`; en AJAX, `showToast('danger', …)` desde el `.fail()`. |
| "Modales: siempre centrados" | `modal-dialog-centered` sin excepción | Solo relevante si la home añade un modal; queda como requisito en tasks. |
| "Ayuda contextual" | Vista con guía ⇒ `@section('ayuda-titulo')` + `@section('ayuda')` + archivo en `resources/views/ayuda/` | Se crea `resources/views/ayuda/super-admin-panel.blade.php`. |
| "Nueva entrada de menú ⇒ nuevo permiso" | Toda entrada nueva del menú del tenant necesita permiso propio en `CatalogoPermisos` + `CatalogoMenu` | **No aplica**: la rama del Super Admin del sidebar está fuera de `CatalogoMenu` por diseño (feature 036) y se rige por `EnsureSuperAdmin`. La entrada "Inicio" se añade a esa rama a mano; **no** se toca `CatalogoPermisos` ni `CatalogoMenu`. |
| "DataTable: botones Anterior/Siguiente verticales" | Cada vista con DataTable propia repite el override | La home no añade DataTable; el de tenants ya lo trae. |

## Project Structure

### Documentation (this feature)

```text
specs/037-super-admin-panel-aislado/
├── plan.md              # Este archivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   ├── http.md          # Rutas, middleware y contrato de bloqueo
│   └── panel-home.md    # Forma de los datos de la home
├── checklists/
│   └── requirements.md
├── spec.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Middleware/
│   │   ├── BloquearSuperAdminAreaTenant.php      # NUEVO — corta el área de tenant al super admin
│   │   └── EnsureSuperAdmin.php                  # sin cambios
│   └── Controllers/
│       ├── SuperAdmin/
│       │   ├── PanelController.php               # NUEVO — home del panel
│       │   └── TenantController.php              # sin cambios funcionales
│       └── DashboardController.php               # EDITADO — se quita la rama de super admin
└── Services/
    └── EstadisticasTenants.php                   # NUEVO — agregados del panel

bootstrap/app.php                                 # EDITADO — alias 'sin_super_admin'
routes/web.php                                    # EDITADO — middleware en el grupo + ruta home

resources/views/
├── super_admin/
│   ├── panel/index.blade.php                     # NUEVO — home
│   └── tenants/index.blade.php                   # sin cambios
├── ayuda/super-admin-panel.blade.php             # NUEVO — ayuda in-app
└── partials/sidebar.blade.php                    # EDITADO — entrada "Inicio" del panel

public/js/plugins-init/
└── super-admin-panel.init.js                     # NUEVO — gráfico Chart.js de altas

tests/Feature/SuperAdmin/
├── SuperAdminAreaTenantBloqueadaTest.php         # NUEVO — US1 (test-first)
├── SuperAdminPanelHomeTest.php                   # NUEVO — US2
└── SuperAdminPanelAtencionTest.php               # NUEVO — US3

docs/04-front-guidelines.md                       # EDITADO — excepción de widgets de dashboard
docs/01-arquitectura.md                           # EDITADO — Decisión: aislamiento del panel central
```

**Structure Decision**: monolito Laravel existente; la feature se acopla a la estructura ya presente
(`app/Http/Middleware`, `app/Http/Controllers/SuperAdmin`, `app/Services`, `resources/views/super_admin`)
sin introducir carpetas ni capas nuevas.

## Complexity Tracking

> Sin violaciones de la constitución que justificar. Sección vacía a propósito.
