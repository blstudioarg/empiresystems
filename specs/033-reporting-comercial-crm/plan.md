# Implementation Plan: Reporting Comercial CRM

**Branch**: `033-reporting-comercial-crm` | **Date**: 2026-07-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/033-reporting-comercial-crm/spec.md`

## Summary

Cerrar el requisito 6 del modelo de evidencias del Kit Digital (categoría Gestión de Clientes)
añadiendo una sección de **informes comerciales** que mide el embudo lead → oportunidad →
presupuesto que la feature 028 construyó pero que hoy nadie agrega.

Enfoque técnico: un servicio `InformeComercial` que resuelve todo con agregación en base de datos
(`COUNT`/`SUM` + `GROUP BY`) sobre las tablas existentes, más tres cambios de esquema mínimos —un
catálogo de canales de captación por tenant, la fecha de conversión del lead y cuatro índices— que
son lo que hace posible la segmentación por canal y el ratio de ciclo comercial. El dashboard
financiero **no se toca**; la única refactorización sobre código existente es extraer la lógica de
buckets temporales de `DashboardEstadisticas` a `App\Support\BucketsRango` para que ambos servicios
la compartan.

El alcance de datos por perfil (lo que el requisito llama "diferentes niveles de agregación en
función del perfil del usuario") se resuelve con dos permisos nuevos sobre el sistema de roles ya
existente, aplicados **en servidor**.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (multi-tenant single-database), `spatie/laravel-permission`
(roles/permisos, feature 027), `maatwebsite/excel` (exportación, feature 031), Chart.js + Morris +
daterangepicker (ya vendorizados en `public/vendor/`)

**Storage**: MySQL/MariaDB. Una tabla nueva (`canales_captacion`), dos columnas nuevas en `leads`,
cuatro índices nuevos. Sin tablas de agregados.

**Testing**: PHPUnit (`php artisan test`). Test-first en aislamiento multi-tenant y cálculo de
ratios (Principio IV).

**Target Platform**: Aplicación web sobre hosting compartido tipo cPanel/Hostinger (Principio V).

**Project Type**: Aplicación web monolítica Laravel (Blade + JS vainilla/jQuery, sin SPA).

**Performance Goals**: Informe completo en <3 s con 5.000 leads, 1.000 oportunidades y 1.000
presupuestos en el periodo (SC-007).

**Constraints**: Todos los agregados calculados en servidor (Principio III); `TenantScope` aplicado
sin excepción y sin `withoutGlobalScopes()` (Principio I); sin dependencias que exijan VPS
(Principio V).

**Scale/Scope**: 1 sección nueva, ~5 rutas, 1 servicio de cálculo, 1 exportador, 1 CRUD de catálogo,
2 permisos nuevos, 1 tabla + 2 columnas + 4 índices.

## Constitution Check

*GATE: revisado antes de Phase 0 y de nuevo tras el diseño de Phase 1.*

| Principio | Evaluación | Resultado |
|---|---|---|
| **I. Aislamiento Multi-Tenant** | `canales_captacion` lleva `tenant_id` indexado y usa `BelongsToTenant` como el resto. Todas las consultas del informe pasan por el `TenantScope`; el plan prohíbe explícitamente `withoutGlobalScopes()` (data-model §4). Tests de aislamiento con ≥2 tenants sobre informe y exportación (SC-005). | ✅ PASA |
| **II. Cumplimiento Normativo España-First** | No toca facturación, impuestos, numeración ni Verifactu: la feature solo lee. Sobre RGPD/LOPDGDD: no introduce datos personales nuevos —`canal_captacion_id` es un dato comercial y `convertido_at` una marca temporal de un hecho de negocio—; ambos viven en `leads` y se purgan con la retención ya definida (`ConfigCrm::retencionDias`), sin necesitar mecanismo propio (data-model §6). | ✅ PASA |
| **III. Integridad Financiera Server-Side** | Todos los recuentos, importes y ratios se calculan en servidor y llegan al cliente ya redondeados; el cliente solo pinta (FR-010, contrato). El alcance por perfil también se resuelve en servidor, ignorando los parámetros de la petición (FR-024). No se recalcula ni se reemite ningún importe fiscal. | ✅ PASA |
| **IV. Test-First en Lógica Crítica** | Aislamiento multi-tenant y cálculo de ratios entran en el ámbito no negociable: sus tests se escriben antes y deben fallar primero. Incluye casos límite de denominador cero y de forzado de `comercial_id` ajeno. La UI y el catálogo de canales siguen un flujo de test más flexible. | ✅ PASA |
| **V. Simplicidad y Hosting Compartido** | Sin dependencias nuevas: se reutilizan las librerías de gráficos y de Excel ya vendorizadas. Cálculo bajo demanda, sin tablas de agregados ni jobs programados ni caché (research D6). Sin nada que exija VPS. Se descartó explícitamente materializar agregados por YAGNI. | ✅ PASA |

**Resultado del gate**: pasa sin violaciones que bloqueen. Una desviación menor documentada en
Complexity Tracking.

**Re-evaluación post-diseño (Phase 1)**: los artefactos de diseño no introdujeron ninguna violación
nueva. La extracción de `BucketsRango` es un movimiento mecánico de métodos privados cubierto por
los tests de dashboard existentes, y no altera el comportamiento del dashboard financiero.

## Project Structure

### Documentation (this feature)

```text
specs/033-reporting-comercial-crm/
├── plan.md                              # Este fichero
├── spec.md
├── research.md                          # Phase 0
├── data-model.md                        # Phase 1
├── quickstart.md                        # Phase 1
├── contracts/
│   └── informes-comerciales.md          # Phase 1
├── checklists/
│   └── requirements.md
└── tasks.md                             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── InformeComercialController.php        # nuevo — vista + JSON + exportación
│   │   └── CanalCaptacionController.php          # nuevo — CRUD del catálogo
│   └── Requests/
│       ├── InformeComercialFiltroRequest.php     # nuevo — no lanza 422 (patrón dashboard)
│       └── StoreCanalCaptacionRequest.php        # nuevo
├── Models/
│   └── CanalCaptacion.php                        # nuevo
├── Services/
│   ├── InformeComercial.php                      # nuevo — núcleo de cálculo
│   ├── ExportadorInformeComercial.php            # nuevo — xlsx multi-hoja
│   └── ConversorLeadCliente.php                  # modificado — puebla convertido_at
├── Support/
│   ├── BucketsRango.php                          # nuevo — extraído de DashboardEstadisticas
│   ├── RangoFechas.php                           # modificado — mismoPeriodoEjercicioAnterior()
│   ├── AlcanceInformeComercial.php               # nuevo — resuelve alcance por permisos
│   └── CatalogoPermisos.php                      # modificado — 2 permisos nuevos
├── Excel/Definiciones/
│   └── DefinicionLeads.php                       # modificado — columna canal
└── Services/DashboardEstadisticas.php            # modificado — usa BucketsRango

database/migrations/                              # canales_captacion, columnas leads, índices
database/seeders/                                 # siembra de canales por defecto

resources/views/
├── informes-comerciales/index.blade.php          # nuevo
├── partials/informe-comercial-contenido.blade.php # nuevo — bloque recargable por JSON
├── configuracion/                                # modificado — pestaña de canales
├── leads/                                        # modificado — campo canal
└── ayuda/informes-comerciales.blade.php          # nuevo

public/js/plugins-init/
└── informe-comercial.init.js                     # nuevo

resources/ia/conocimiento/
└── informes-comerciales.md                       # nuevo

tests/Feature/
├── InformeComercialIndicadoresTest.php
├── InformeComercialRatiosTest.php
├── InformeComercialSegmentacionTest.php
├── InformeComercialComparativaTest.php
├── InformeComercialAlcancePerfilTest.php
├── InformeComercialAislamientoTenantTest.php
├── InformeComercialExportacionTest.php
└── CanalCaptacionCrudTest.php
```

**Structure Decision**: se mantiene la estructura monolítica Laravel ya establecida en el proyecto
(controllers / services / support / views / tests-feature). El cálculo vive en un **servicio**
(`InformeComercial`), no en el controller, siguiendo el precedente de `DashboardEstadisticas`; la
resolución de alcance por permisos se aísla en `App\Support\AlcanceInformeComercial` para poder
testearla sin levantar HTTP y para que el controller no pueda saltársela por descuido.

## Complexity Tracking

| Violación | Por qué se necesita | Alternativa más simple descartada porque |
|---|---|---|
| Dos permisos (`ver-informes-comerciales` + `ver-informes-equipo`) para una sola vista, cuando el catálogo documenta que su granularidad es "una vista del sidebar" | El requisito 6 exige explícitamente "diferentes niveles de agregación de información en función del perfil del usuario". Un permiso booleano solo expresa "ve / no ve", no dos alcances distintos sobre la misma vista | Reutilizar un permiso existente (p. ej. `ver-usuarios`) para inferir "es responsable" acoplaría el alcance de un informe comercial a la administración de usuarios —responsabilidades distintas— y dejaría al tenant sin poder dar informes de equipo a un jefe de ventas que no administra usuarios |

### Ampliación de alcance registrada (no es violación, pero conviene que conste)

La feature no es puramente de lectura: añade el catálogo de canales de captación y toca el alta,
la importación y la exportación de leads (FR-011..FR-014). Está justificado en la spec —sin ello la
segmentación "por canales" que exige el requisito sería vacía, porque el campo `origen` actual solo
distingue alta manual de importación— y es la decisión de alcance más relevante de esta feature.
Si se decidiera recortarla, caen la Historia 2 completa y el criterio SC-003 queda a medias.
