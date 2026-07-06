# Implementation Plan: Rediseño del Dashboard con filtro por rango de fechas

**Branch**: `023-dashboard-rediseno-rango-fechas` | **Date**: 2026-07-05 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/023-dashboard-rediseno-rango-fechas/spec.md`

## Summary

Evolucionar el dashboard (feature 015) en tres frentes: (1) **corregir el importe facturado** para
que cada operación cuente una sola vez con su neto (fin del doble conteo de rectificativas por
sustitución), reutilizando `Factura::totalCobrable()`; (2) **añadir un filtro por rango de fechas**
(mes/trimestre/año en curso + personalizado) que reparametriza todo el servicio de estadísticas; y
(3) **rediseñar la vista** con widgets nuevos (gastos/compras y resultado, IVA repercutido vs.
soportado, ventas POS como métrica separada) trasplantando piezas del template NexaDash.

El enfoque técnico central: introducir un value object `RangoFechas` que encapsula el periodo y su
periodo anterior comparable, y refactorizar `DashboardEstadisticas` para recibirlo. La corrección
del facturado se hace calculando la facturación neta **en PHP** sobre facturas originales
facturables (usando `totalCobrable()`), en lugar de con `SUM(total)` en SQL que no puede excluir
rectificativas ni netear. La carga inicial del dashboard sigue server-rendered (Blade); el cambio de
rango recarga solo el contenido por AJAX (misma ruta con `Accept: application/json`, sin navegar de
página) y sincroniza la URL con `history.pushState`, de modo que el rango persiste al recargar/compartir.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: Blade, Eloquent, `stancl/tenancy` (single-DB). Front: chartjs/morris/raphael
(ya vendorizados), date range picker del banco del template (a vendorizar).

**Storage**: MySQL/MariaDB. Sin tablas nuevas: la feature es de solo lectura sobre `facturas`,
`pagos`, `compras`, `articulos`. El rango es un parámetro de consulta, no se persiste.

**Testing**: PHPUnit (Feature tests sobre `DashboardEstadisticas` y la ruta `/`).

**Target Platform**: App web multi-tenant (navegadores de escritorio/móvil soportados por el template).

**Project Type**: Web application (Laravel monolito, server-rendered).

**Performance Goals**: Render del dashboard sin degradación perceptible para volúmenes típicos de PYME
(miles de facturas/año por tenant). Evitar N+1 al netear rectificativas (eager load de `rectificativa`).

**Constraints**: Cálculos financieros en backend y en la precisión monetaria del proyecto; aislamiento
multi-tenant estricto; sin libs de front nuevas salvo el date range picker (justificado).

**Scale/Scope**: 1 servicio refactorizado, 1 value object nuevo, 1 controller y 1 vista actualizados,
~3-4 métodos de agregación nuevos (compras/gastos, IVA soportado/repercutido, ventas POS), y su JS de
charts. Sin migraciones.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ Todas las queries pasan por modelos con
  `BelongsToTenant`/global scope; se añade test de aislamiento para las métricas nuevas (dos tenants,
  afirmar que no se filtran datos). No hay acceso Super Admin aquí.
- **II. Cumplimiento Normativo España-First**: ✅ No se emite ni altera facturación; solo se agrega.
  El widget de impuestos usa `regimen_impositivo` del tenant (IVA/IGIC/IPSI), no asume IVA. La
  facturación neta respeta la inmutabilidad (no toca facturas emitidas).
- **III. Integridad Financiera / cálculos en backend**: ✅ El neteo y todas las cifras se calculan en
  el servicio backend; el front solo pinta. Se reutiliza la lógica ya probada de `totalCobrable()`.
- **IV. Test-First**: ✅ Se escriben primero los tests del servicio (facturado neto, rango, nuevos
  widgets) que hoy fallarían, y luego la implementación. Los tests existentes de `DashboardTest` se
  actualizan al nuevo contrato (rango por defecto = mes en curso, equivalente al actual).
- **V. Simplicidad**: ✅ Sin tablas nuevas, sin dependencias de negocio nuevas. Un value object y un
  refactor del servicio existente; se reutilizan componentes de front del template.

**Resultado: PASS. Sin violaciones que justificar (Complexity Tracking vacío).**

## Project Structure

### Documentation (this feature)

```text
specs/023-dashboard-rediseno-rango-fechas/
├── plan.md              # Este archivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1 (entidades derivadas + value object)
├── quickstart.md        # Phase 1 (guía de validación)
├── contracts/
│   └── dashboard.md     # Contrato del servicio + query params del controller
└── checklists/
    └── requirements.md  # (de /speckit-specify)
```

### Source Code (repository root)

```text
app/
├── Support/
│   └── RangoFechas.php               # NUEVO: value object (preset/desde-hasta + periodo anterior)
├── Services/
│   └── DashboardEstadisticas.php     # REFACTOR: recibe RangoFechas; facturado neto; widgets nuevos
├── Http/
│   ├── Controllers/
│   │   └── DashboardController.php    # ACTUALIZA: parsea query params → RangoFechas
│   └── Requests/
│       └── DashboardFiltroRequest.php # NUEVO: valida preset y rango personalizado
resources/views/
└── dashboard.blade.php               # REDISEÑO: selector de rango + widgets nuevos
public/js/plugins-init/
└── dashboard-charts.init.js          # ACTUALIZA: charts nuevos + submit del filtro
public/vendor/<daterangepicker>/      # NUEVO (si se vendoriza el picker del template)

tests/Feature/
└── DashboardTest.php                 # ACTUALIZA + AMPLÍA: rango, neteo, widgets nuevos, aislamiento
```

**Structure Decision**: Monolito Laravel server-rendered existente. Se reutiliza el servicio
`DashboardEstadisticas` (no se crea uno paralelo) y la vista `dashboard.blade.php`. El único artefacto
de dominio nuevo es el value object `RangoFechas` en `app/Support/` (coherente con otros helpers del
proyecto como `VencimientoFactura`, `TopeSimplificada`).

## Complexity Tracking

> Sin violaciones de constitución. Tabla no aplica.
