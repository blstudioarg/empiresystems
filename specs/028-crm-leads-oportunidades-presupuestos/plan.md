# Implementation Plan: Gestión de Clientes CRM — Leads, Oportunidades y Presupuestos

**Branch**: `028-crm-leads-oportunidades-presupuestos` | **Date**: 2026-07-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/028-crm-leads-oportunidades-presupuestos/spec.md`

## Summary

Construir el embudo comercial previo a la factura (**Lead → Oportunidad → Presupuesto →
conversión a factura**) para cerrar el gap de homologación de la categoría *"Gestión de Clientes"*
del Kit Digital (`docs/06-kit-digital.md`). Tres módulos nuevos de negocio, todos multi-tenant:

- **Leads**: alta manual + importación CSV/Excel con reporte de filas rechazadas, dedup por
  email/teléfono (rechazo, sin fusión automática), reglas de asignación (manual + round-robin),
  estados, notas y conversión a cliente.
- **Oportunidades**: pipeline (nueva → en negociación → ganada/perdida) vinculado a lead o cliente.
- **Presupuestos**: documento no fiscal que **reutiliza `CalculadoraFactura`** para líneas e
  importes (sin duplicar el motor), con ciclo de vida propio y conversión a **factura borrador**
  (la numeración correlativa y Verifactu se consumen al emitir con `EmisorFacturas`, no al convertir).

**Enfoque técnico**: seguir el patrón de tablas de negocio ya establecido (`BelongsToTenant` +
global scope, servicios dedicados como único punto de escritura de lógica crítica, tests de
no-fuga). El presupuesto **no** duplica el cálculo: invoca `App\Services\CalculadoraFactura` igual
que `FacturaController`. La conversión a factura crea un `Factura` en `borrador` copiando líneas e
importes congelados y reutiliza el flujo de emisión existente. Todo síncrono, viable en hosting
compartido (Principio V).

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`),
`spatie/laravel-permission` (feature 027, teams por `tenant_id`), `maatwebsite/excel` **o**
`league/csv` para la importación de leads (ver research D2), motor de facturación existente
(`CalculadoraFactura`, `EmisorFacturas`, `NumeradorFacturas`).

**Storage**: MySQL/MariaDB. Tablas nuevas: `leads`, `lead_notas`, `oportunidades`, `presupuestos`,
`presupuesto_lineas`. Reutiliza `clientes`, `articulos`, `facturas`, `series`, `configuraciones`.

**Testing**: PHPUnit (Pest si el repo ya lo usa). Test-first obligatorio en aislamiento multi-tenant
y en el cálculo de importes del presupuesto (Principio IV).

**Target Platform**: Aplicación web Laravel sobre hosting compartido cPanel/Hostinger.

**Project Type**: Web (monolito Laravel con Blade + assets del template NexaDash).

**Performance Goals**: Importar 500 leads en una operación síncrona sin timeout (SC-001);
recálculo de totales de presupuesto instantáneo para el usuario.

**Constraints**: Sin colas persistentes ni workers dedicados (Principio V). Importación síncrona
por lotes dentro del request. Cálculo de importes 100% backend (Principio III).

**Scale/Scope**: Volumen pyme (cientos/miles de leads por tenant). 3 módulos, ~5 tablas nuevas,
~4 controladores, reutilización del motor de facturación.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cumplimiento en este plan |
|-----------|---------------------------|
| **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)** | Todas las tablas nuevas llevan `tenant_id` indexado + `BelongsToTenant` (global scope). Tests de no-fuga con ≥2 tenants para leads, oportunidades y presupuestos (SC-006, FR-024). |
| **II. Cumplimiento Normativo España-First** | El presupuesto **no** es documento fiscal: no consume numeración de serie de factura ni entra en el encadenamiento/huella Verifactu (FR-017). La factura resultante sí, pero al **emitir**, conservando inmutabilidad y numeración correlativa del motor existente. Los leads contienen datos personales → retención/purga configurable reutilizando el patrón `RetencionLogsTenant`/`logs:purgar` (feature 021), reflejado en `docs/03-modelo-datos.md` (FR-025). |
| **III. Integridad Financiera Server-Side** | Los importes del presupuesto se calculan con `CalculadoraFactura` en backend; el cliente nunca es fuente de verdad (FR-016). La numeración correlativa se asigna dentro de transacción con bloqueo al emitir la factura (motor existente, `NumeradorFacturas`). |
| **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)** | Tests escritos primero para: (a) aislamiento tenant de los 3 módulos, (b) igualdad al céntimo entre totales de presupuesto y factura para IVA/IGIC/IPSI (SC-003), (c) conversión que no permite doble facturación (SC-005). |
| **V. Simplicidad y Compatibilidad Hosting Compartido** | Importación síncrona por lotes (sin colas). Reglas de asignación acotadas a manual + round-robin (sin motor de reglas). Sin portal público de aceptación. Reutiliza el motor de facturación en vez de duplicarlo (YAGNI). |

**Resultado**: PASS. Sin violaciones que requieran Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/028-crm-leads-oportunidades-presupuestos/
├── plan.md              # Este archivo
├── spec.md              # Especificación (con Clarifications)
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1 (rutas HTTP de los 3 módulos)
│   ├── leads.md
│   ├── oportunidades.md
│   └── presupuestos.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Lead.php                    # BelongsToTenant
│   ├── LeadNota.php                # BelongsToTenant
│   ├── Oportunidad.php             # BelongsToTenant
│   ├── Presupuesto.php             # BelongsToTenant
│   └── PresupuestoLinea.php        # BelongsToTenant
├── Enums/
│   ├── EstadoLead.php              # nuevo, contactado, cualificado, descartado, convertido
│   ├── EstrategiaAsignacion.php    # manual, round_robin
│   ├── EtapaOportunidad.php        # nueva, en_negociacion, ganada, perdida
│   └── EstadoPresupuesto.php       # borrador, enviado, aceptado, rechazado, caducado, facturado
├── Services/
│   ├── ImportadorLeads.php         # parseo CSV/Excel → filas válidas/rechazadas (único punto)
│   ├── AsignadorLeads.php          # aplica la EstrategiaAsignacion vigente
│   ├── ConversorLeadCliente.php    # lead → cliente conservando trazabilidad
│   ├── RegistroPresupuesto.php     # crea/edita presupuesto usando CalculadoraFactura (único punto)
│   └── ConversorPresupuestoFactura.php  # presupuesto aceptado → Factura borrador
├── Http/
│   ├── Controllers/
│   │   ├── LeadController.php
│   │   ├── LeadImportacionController.php
│   │   ├── OportunidadController.php
│   │   └── PresupuestoController.php
│   └── Requests/
│       ├── StoreLeadRequest.php / UpdateLeadRequest.php
│       ├── ImportarLeadsRequest.php
│       ├── StoreOportunidadRequest.php / UpdateOportunidadRequest.php
│       └── StorePresupuestoRequest.php / UpdatePresupuestoRequest.php
└── Support/
    └── RetencionLeadsTenant.php    # plazo de purga de leads descartados (patrón feature 021)

database/migrations/
├── ...create_leads_table.php
├── ...create_lead_notas_table.php
├── ...create_oportunidades_table.php
├── ...create_presupuestos_table.php
└── ...create_presupuesto_lineas_table.php

resources/views/
├── leads/        (index, _form, importar, show)
├── oportunidades/(index — vista pipeline, _form, show)
└── presupuestos/ (index, _form, show, pdf)

routes/web.php    # grupos resource protegidos por permisos (ver-leads, ver-oportunidades, ver-presupuestos)

tests/
├── Feature/Leads/ (aislamiento, importación, dedup, asignación, conversión)
├── Feature/Oportunidades/ (pipeline, transición, ganar→cliente)
└── Feature/Presupuestos/ (cálculo == factura, ciclo de vida, conversión, no doble factura)
```

**Structure Decision**: Monolito Laravel existente. Se añaden modelos/servicios/controladores
siguiendo el patrón ya establecido por facturas/compras (servicio dedicado como único punto de
escritura para la lógica crítica; controladores finos). Se integran en el sidebar y en el sistema
de permisos de la feature 027 con 3 permisos nuevos (`ver-leads`, `ver-oportunidades`,
`ver-presupuestos`) añadidos a `App\Support\CatalogoPermisos`.

## Complexity Tracking

> No aplica — el Constitution Check pasa sin violaciones. No se introduce complejidad fuera del
> alcance del MVP: la importación es síncrona, las reglas de asignación son dos, no hay portal
> público, y el motor de facturación se reutiliza en lugar de duplicarse.
