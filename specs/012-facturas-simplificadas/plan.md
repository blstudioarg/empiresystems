# Implementation Plan: POS — Facturas simplificadas (tickets)

**Branch**: `012-facturas-simplificadas` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/012-facturas-simplificadas/spec.md`

## Summary

Añadir un módulo **POS** (dropdown en el sidebar) con dos vistas —listado DataTable de facturas
`tipo = simplificada` y una vista "Crear ticket" táctil orientada a tablet— **sin tocar** la lógica de
facturas ordinarias. Los tickets se emiten reutilizando el motor fiscal existente (numeración con
bloqueo por serie/año, cálculo server-side, eventos append-only, inmutabilidad de la 008), añadiendo
tres reglas propias de la simplificada: (1) serie propia con prefijo **"S"**; (2) **bloqueo duro** de
tope de importe (400 € por defecto / 3.000 € si el tenant está marcado como sector con tope ampliado,
vía configuración); (3) **receptor opcional** (simplificada simple vs. cualificada). El PDF del ticket
se puede generar en formato **80 mm** o **A4**, elegible al ver/descargar. No se añaden columnas a
`facturas`: `cliente_id`/snapshot `cliente_*` ya son nullable y `tipo = simplificada` ya existe en el
enum.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant` + `TenantScope`),
`barryvdh/laravel-dompdf` (PDF), DataTables (front del template NexaDash)

**Storage**: MySQL/MariaDB. Sin cambios de esquema en `facturas`. Se añade **una clave de
configuración** (`configuraciones`, grupo `facturacion`) y **una serie sembrada** (código "S").

**Testing**: PHPUnit (Feature + Unit). Test-first (Principio IV) para: numeración de la serie "S" con
aislamiento por tenant, validación de tope, e inmutabilidad. Reutiliza helpers de tests de 008/009.

**Target Platform**: App web multi-tenant; la vista "Crear ticket" se optimiza para **tablet/TPV**
(pantalla táctil), sirviéndose desde el mismo layout `app.blade.php`.

**Project Type**: Web (backend Laravel + vistas Blade + JS del template).

**Performance Goals**: Emisión de ticket con respuesta percibida < 1 s; cálculo de total en la vista
POS actualizado sin recarga (JS) pero con la verdad de importes siempre recalculada en backend.

**Constraints**: Hosting compartido (Principio V) — sin dependencias nuevas de infraestructura.
Importes y tope siempre server-side (Principio III). Sin regresiones en el flujo ordinario.

**Scale/Scope**: 2 vistas nuevas (index + create POS), 1 controller, 1 servicio de registro/emisión de
tickets, 1 form request, 1 support de tope, 1 support de validación por tipo (o refactor de
`EmisorFacturas`), 2 plantillas PDF (80 mm y A4 simplificada), 1 clave de config + tab, 1 serie
sembrada, entrada de sidebar. Sin columnas nuevas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ `Factura`, `Serie`, `Configuracion` ya usan
  `BelongsToTenant`. La serie "S", su numeración y el listado POS quedan filtrados por tenant.
  Se añaden tests de aislamiento (≥2 tenants) para numeración de tickets y para el listado POS.
- **II. Cumplimiento Normativo España-First**: ✅ Serie separada "S" con correlativo sin huecos y
  reinicio anual (reutiliza `NumeradorFacturas`). Desglose por tipo impositivo agnóstico al régimen
  (reutiliza `CalculadoraFactura`, congela `regimen_impositivo`). Tope de simplificada aplicado como
  bloqueo duro. Ticket emitido inmutable. Contenido mínimo en el PDF. Sin nuevos umbrales inventados:
  400 €/3.000 € están documentados en `docs/02-facturacion-espana.md §3.1`.
- **III. Integridad Financiera Server-Side**: ✅ Importes calculados por `CalculadoraFactura`; tope
  validado en backend sobre el total del servidor; numeración con `lockForUpdate` (reutiliza
  `NumeradorFacturas`). El cliente nunca fija importe ni número.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ Tests Red-Green primero para numeración de
  serie "S", validación de tope y aislamiento entre tenants antes de implementar el servicio.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: ✅ Sin dependencias nuevas, sin columnas
  nuevas, reutiliza servicios existentes. La vista POS es Blade + JS del template ya vendorizado.

**Resultado**: PASS. Sin violaciones → sin entradas en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/012-facturas-simplificadas/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1 (contratos HTTP del módulo POS)
│   └── pos.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── PosController.php                 # NUEVO: index (datatable), create, store (crea+emite ticket), pdf
│   └── Requests/
│       └── StoreTicketRequest.php            # NUEVO: validación de líneas + receptor opcional
├── Services/
│   └── RegistroTicket.php                    # NUEVO: construye simplificada, valida tope, calcula y emite (reutiliza Calculadora + Numerador)
├── Support/
│   └── TopeSimplificada.php                  # NUEVO: resuelve tope (400 / 3000) desde config del tenant; constantes de clave/default
├── Exceptions/
│   └── TicketFueraDeTopeException.php        # NUEVO: bloqueo duro de tope
└── Services/EmisorFacturas.php               # MODIF: validación de receptor condicionada al tipo (no exigir receptor en simplificada)

resources/views/
├── pos/
│   ├── index.blade.php                       # NUEVO: DataTable de simplificadas
│   └── create.blade.php                      # NUEVO: vista "Crear ticket" tablet-first (skills de diseño)
├── facturas/
│   ├── pdf.blade.php                          # (existente A4) — reutilizado/param. para simplificada A4
│   └── ticket-80mm.blade.php                  # NUEVO: plantilla PDF de rollo 80 mm
├── partials/sidebar.blade.php                 # MODIF: dropdown "POS"
└── configuracion/_tab_facturacion.blade.php   # MODIF: switch "sector con tope ampliado (3.000 €)"

public/js/
├── plugins-init/pos-datatable.init.js         # NUEVO: init DataTable POS
└── pos-form.js                                # NUEVO: lógica táctil de captura + total en vivo

database/seeders/SerieSeeder.php               # MODIF: sembrar serie "S" (simplificada)
database/seeders/ConfiguracionSeeder.php       # MODIF: clave factura.simplificada_tope_ampliado (default false)

app/Http/Controllers/FacturaController.php      # MODIF: index() excluye tipo=simplificada
routes/web.php                                  # MODIF: grupo de rutas pos.*

tests/Feature/
├── PosTicketEmisionTest.php                    # numeración serie S, cualificada/simple, evento, inmutabilidad
├── PosTopeSimplificadaTest.php                 # bloqueo duro 400/3000 (test-first)
├── PosListadoSeparadoTest.php                  # index POS solo simplificadas; facturas excluye simplificadas
└── PosTenantIsolationTest.php                  # aislamiento de serie/numeración/listado entre tenants
```

**Structure Decision**: Laravel monolito existente. Se añade un módulo POS **paralelo** al de
Facturas (controller + vistas + rutas propios) que reutiliza los servicios de dominio ya probados
(`CalculadoraFactura`, `NumeradorFacturas`, `EmisorFacturas`/eventos). El único cambio quirúrgico en
código existente es: (a) excluir simplificadas del `FacturaController::index`, y (b) condicionar al
`tipo` la validación de receptor en `EmisorFacturas` para no bloquear el ticket simple. Todo lo demás
es aditivo, respetando "no tocar la lógica de facturas ordinarias".

## Complexity Tracking

> Sin violaciones de la constitución. Nada que justificar.
