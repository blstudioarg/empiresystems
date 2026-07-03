# Implementation Plan: Configuración del tenant — Apariencia / Marca

**Branch**: `003-config-apariencia` | **Date**: 2026-07-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-config-apariencia/spec.md`

## Summary

Añadir una pantalla de **Configuración** por tenant, accesible desde el dropdown del perfil en la
topbar (no desde el sidebar), organizada en **tabs**. En esta entrega solo la tab **"Apariencia /
Marca"** es funcional: permite fijar **color primario, color secundario, color de fondo de la
topbar** (con color pickers) y subir un **logo** con vista previa. Los valores se guardan por tenant
y se aplican a la interfaz del CRM inyectando en `<head>` un bloque `<style>` que sobrescribe las
variables CSS del template (`--primary`, `--primary-hover`, `--rgba-primary-1..9`, `--secondary` y
una variable nueva para el fondo de la topbar). Los colores se almacenan en la tabla `configuraciones`
(clave-valor por tenant, ya documentada) y el logo como fichero en disco público referenciado por
`tenants.logo_path`.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, trait `BelongsToTenant`); template NexaDash
(Bootstrap 5 + variables CSS); `jquery-asColorPicker` (plugin del banco del template, a vendorizar).

**Storage**: MySQL/MariaDB. Colores → tabla nueva `configuraciones` (clave-valor por tenant). Logo →
columna nueva `tenants.logo_path` + fichero en disco `public` (`storage/app/public/logos/...`).

**Testing**: PHPUnit (Feature tests) — guardado de colores, subida/validación de logo, aislamiento
entre tenants (test-first, Principio IV), acceso solo autenticado.

**Target Platform**: App web servida por Laravel sobre hosting compartido (cPanel/Hostinger).

**Project Type**: Web application (monolito Laravel con vistas Blade).

**Performance Goals**: Sin objetivos especiales; la resolución de apariencia por tenant debe ser
barata (una consulta cacheable por request) para no penalizar cada carga de página.

**Constraints**: Compatible con hosting compartido (Principio V): sin dependencias que exijan VPS.
La subida de logo requiere `php artisan storage:link` (symlink estándar de Laravel). El override de
color debe cubrir también las variantes `--rgba-primary-N`, que el template define con el color
hardcodeado.

**Scale/Scope**: 1 pantalla nueva (Configuración con tabs), 1 tab funcional, ~4 valores configurables,
1 tabla nueva (`configuraciones`), 1 columna nueva (`tenants.logo_path`).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: `configuraciones` es tabla de negocio → lleva
  `tenant_id` indexado y usa `BelongsToTenant` (mismo patrón que `Cliente`). El logo se resuelve del
  tenant activo (`tenants.logo_path`). **Tests de aislamiento obligatorios** (≥2 tenants) sobre lectura
  y escritura de configuración. ✅ Cubierto en el diseño.
- **II. Cumplimiento Normativo España-First**: No aplica — esta feature no toca facturación,
  impuestos ni Verifactu. ✅ Sin impacto.
- **III. Integridad Financiera Server-Side**: No aplica — no hay importes. La validación de
  colores/logo se hace en backend igualmente. ✅ Sin impacto.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: El aislamiento multi-tenant entra en el
  alcance test-first → los tests de aislamiento de `configuraciones` se escriben antes de implementar.
  El resto (UI, preview, pickers) sigue flujo de test flexible. ✅ Respetado.
- **V. Simplicidad y Hosting Compartido**: Sin dependencias nuevas de servidor; el color picker es un
  plugin jQuery ya presente en el banco del template. Almacenamiento con tabla clave-valor ya
  documentada (no se inventa modelo nuevo). Symlink de storage es estándar Laravel. ✅ Respetado.

**Resultado del gate**: PASS. No hay violaciones que registrar en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/003-config-apariencia/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   └── configuracion-http.md   # Fase 1 — contrato de endpoints
└── tasks.md             # Fase 2 (/speckit-tasks, NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ConfiguracionController.php        # show (vista tabs) + update (apariencia)
│   ├── Requests/
│   │   └── UpdateAparienciaRequest.php        # validación colores + logo
│   └── Middleware/
│       └── SetTenantContext.php               # (existente) ya inicializa tenancy
├── Models/
│   ├── Configuracion.php                      # NUEVO — clave-valor por tenant (BelongsToTenant)
│   └── Tenant.php                             # + logo_path (fillable + getCustomColumns)
├── Support/
│   └── AparienciaTenant.php                   # NUEVO — resuelve/serializa la apariencia vigente
└── View/
    └── Composers/ (o AppServiceProvider)      # comparte apariencia con el layout

database/
├── migrations/
│   ├── 2026_07_02_xxxxxx_create_configuraciones_table.php   # NUEVO
│   └── 2026_07_02_xxxxxx_add_logo_path_to_tenants_table.php # NUEVO
└── factories/
    └── ConfiguracionFactory.php               # NUEVO

resources/views/
├── configuracion/
│   ├── index.blade.php                        # pantalla con tabs
│   └── _tab_apariencia.blade.php              # formulario apariencia (pickers + logo+preview)
├── partials/
│   ├── header.blade.php                       # link "Configuración" en dropdown de perfil
│   ├── nav-header.blade.php                    # logo del tenant si existe
│   └── apariencia-tenant.blade.php            # <style> con overrides de variables CSS (en <head>)
└── layouts/app.blade.php                      # incluye el partial de apariencia en <head>

public/
├── vendor/jquery-asColorPicker/               # NUEVO — CSS/JS/images del picker (del banco)
└── js/plugins-init/
    ├── jquery-asColorPicker.init.js           # NUEVO — init del picker
    └── configuracion-apariencia.init.js       # NUEVO — preview del logo

tests/Feature/
├── ConfiguracionAparienciaTest.php            # guardado colores, logo, validación, auth
└── ConfiguracionTenantIsolationTest.php       # aislamiento entre tenants (test-first)
```

**Structure Decision**: Monolito Laravel existente (Option "web application" servida por Blade). Se
reutilizan los patrones ya establecidos por la feature de clientes: FormRequest para validación,
`BelongsToTenant` para el scope de tenant, y partials Blade del template. No se introduce frontend
SPA ni capa de servicios pesada.

## Complexity Tracking

> No aplica: el Constitution Check pasó sin violaciones. Sección vacía a propósito.
