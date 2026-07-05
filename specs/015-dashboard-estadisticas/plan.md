# Implementation Plan: Dashboard de estadísticas

**Branch**: `015-dashboard-estadisticas` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/015-dashboard-estadisticas/spec.md`

## Summary

Reemplazar el placeholder vacío de `dashboard.blade.php` (ruta `/`) por una vista de solo
lectura que agrega, en el backend y por tenant activo, los indicadores de facturación/cobro del
mes en curso con variación vs. mes anterior, series mensuales de 12 y 6 meses, distribución de
facturas por estado, ranking de clientes, alertas de stock bajo/negativo y últimas facturas
emitidas. Un único `DashboardController::index()` construye todos los datos vía un servicio de
agregación (`DashboardEstadisticas`) y los pasa ya calculados a la vista; el frontend solo
renderiza cards, listas y gráficos (Chart.js, vendorizado nuevo) sin realizar ningún cálculo de
importes.

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12)

**Primary Dependencies**: Laravel 12 (Eloquent, Blade), `stancl/tenancy` (TenantScope ya
existente), Chart.js (nuevo vendor estático en `public/vendor/chartjs/`, sin build step — mismo
patrón que el resto de `public/vendor/*`)

**Storage**: MySQL/MariaDB existente; no se agregan tablas — todo es agregación de lectura sobre
`facturas`, `pagos`, `clientes`, `articulos` ya existentes

**Testing**: PHPUnit (Feature tests sobre `DashboardController` y tests unitarios sobre
`DashboardEstadisticas`), siguiendo el patrón de `tests/Feature/*CrudTest.php` ya presente

**Target Platform**: Hosting compartido tipo cPanel (mismo que el resto del proyecto)

**Project Type**: Web app monolítica Laravel (backend + Blade), sin frontend separado

**Performance Goals**: Carga completa de `/` en menos de 2s con un tenant de hasta unos pocos
miles de facturas (SC-004) — implica agregar con consultas `SUM`/`COUNT`/`GROUP BY` en SQL, nunca
cargar todas las facturas a PHP para sumarlas en memoria

**Constraints**: Todo cálculo de importes/variaciones en backend (Principio III); ningún dato
cruza tenants (Principio I); sin nuevas dependencias que requieran build tools o VPS (Principio
V) — Chart.js se vendoriza como archivo estático `.min.js`, igual que `toastr`/`select2`

**Scale/Scope**: Una sola página (home), ~6 secciones de datos, sin paginación ni filtros de
usuario en esta versión (periodos fijos: mes actual/anterior, últimos 6 y 12 meses, histórico
completo para ranking/distribución)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: Todas las consultas de agregación parten de
  los modelos `Factura`, `Pago`, `Cliente`, `Articulo`, que ya usan `BelongsToTenant` +
  `TenantScope`; el controller no hace queries raw sin scope. Se añade un test de aislamiento
  (2 tenants con datos distintos, afirmar que el dashboard de uno no ve importes del otro). ✅
  Cumple.
- **II. Cumplimiento Normativo España-First**: No aplica cálculo de impuestos nuevo — el
  dashboard solo lee `total`/`base_total`/`cuota_impuesto_total` ya calculados y congelados por
  `CalculadoraFactura`/`EmisorFacturas`. No se introduce lógica fiscal nueva. ✅ Cumple (N/A).
- **III. Integridad Financiera Server-Side**: Todos los agregados (sumas, conteos, porcentajes de
  variación) se calculan en `DashboardEstadisticas` (backend); Blade y JS solo renderizan values
  ya resueltos y arrays de datos para Chart.js (no fórmulas). ✅ Cumple.
- **IV. Test-First en Lógica Crítica**: El cálculo de variación mensual y el aislamiento por
  tenant no son numeración/Verifactu/impuestos, pero sí tocan integridad de datos multi-tenant →
  se aplica test-first al menos para el aislamiento (Principio I) y para los bordes de cálculo de
  variación (mes anterior en cero, sin datos). El resto (formato de vista) sigue flujo estándar.
  ✅ Cumple.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: Sin tablas nuevas, sin colas, sin
  websockets/polling (spec asume recálculo al cargar, no tiempo real). Chart.js es un único
  archivo JS estático, sin paso de build. ✅ Cumple.

No hay violaciones que requieran la sección "Complexity Tracking".

## Project Structure

### Documentation (this feature)

```text
specs/015-dashboard-estadisticas/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (contrato interno del servicio de agregación)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── DashboardController.php        # nuevo — reemplaza la Closure de routes/web.php
├── Services/
│   └── DashboardEstadisticas.php      # nuevo — agregaciones SQL por tenant, sin cálculos en vista
└── Support/
    └── VariacionPorcentual.php        # nuevo — helper puro para % vs período anterior (incl. caso base=0)

resources/views/
└── dashboard.blade.php                # se completa (ya existe como esqueleto vacío)

public/
├── vendor/chartjs/chart.umd.min.js    # nuevo vendor estático (sin build)
└── js/plugins-init/
    └── dashboard-charts.init.js       # nuevo — solo instancia Chart.js con los datos ya calculados

routes/web.php                         # cambia la Closure de '/' por DashboardController::index

tests/
├── Feature/
│   └── DashboardTest.php              # nuevo — aislamiento multi-tenant + valores agregados end-to-end
└── Unit/
    └── VariacionPorcentualTest.php    # nuevo — casos borde (base 0, ambos 0, negativos)
```

**Structure Decision**: Se mantiene la estructura monolítica Laravel ya existente (sin
`src/`/`backend/`/`frontend/` separados). El único controller nuevo (`DashboardController`)
delega todo el cálculo a un servicio (`DashboardEstadisticas`), siguiendo el mismo patrón que
`EmisorFacturas`/`CalculadoraFactura` para features anteriores: controller delgado, servicio con
la lógica de negocio, vista Blade solo de presentación.

## Complexity Tracking

*(No violations — sección omitida por no aplicar.)*
