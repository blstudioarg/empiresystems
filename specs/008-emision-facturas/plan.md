# Implementation Plan: Emisión de facturas (borrador → emitida)

**Branch**: `008-emision-facturas` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/008-emision-facturas/spec.md`

## Summary

Implementar la transición de una factura ordinaria de `borrador` a `emitida`: asignar el número
correlativo fiscal dentro de su serie **reiniciando por año natural**, de forma atómica y segura
ante concurrencia; congelar la fecha de expedición (= hoy) junto con el resto de datos ya
congelados en la creación; registrar el acto en un log append-only (`factura_eventos`); y reforzar
la inmutabilidad de la factura emitida (no editar / no borrar / no re-emitir) en backend y UI.
Verifactu real (huella/QR/XML/AEAT), simplificadas, rectificativas, ciclo B2B, CRUD de series,
pagos y stock quedan fuera de alcance.

**Enfoque técnico**: reutilizar el servicio existente `NumeradorFacturas` (hoy un stub con contador
único por serie) y reescribir su asignación para derivar el siguiente número desde las facturas ya
emitidas de la serie en el año en curso (`MAX(numero)+1`) bajo bloqueo de fila de la serie
(`lockForUpdate`), garantizando reinicio anual sin huecos ni duplicados. Añadir un servicio
`EmisorFacturas` que orquesta validación previa → numeración → congelado → cambio de estado →
evento, todo en una transacción. Exponer una acción `POST /facturas/{factura}/emitir`. Crear la
tabla/modelo `factura_eventos` (append-only). El congelado de datos de cliente ya ocurre en la
creación (feature 005); aquí solo se fija la fecha y se sella el estado.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), `barryvdh/laravel-dompdf` (PDF ya existente), Eloquent

**Storage**: MySQL/MariaDB (hosting compartido; sin features que requieran VPS)

**Testing**: PHPUnit (Feature + Unit), `RefreshDatabase`

**Target Platform**: Aplicación web Laravel sobre hosting compartido tipo cPanel/Hostinger

**Project Type**: Web app (monolito Laravel; backend + Blade)

**Performance Goals**: N/A — operación puntual por usuario; el foco es correctitud transaccional, no throughput

**Constraints**: Numeración sin huecos ni duplicados bajo concurrencia (bloqueo de fila en transacción); inmutabilidad de facturas emitidas; aislamiento por `tenant_id`; importes nunca recalculados en la emisión

**Scale/Scope**: 1 nueva tabla (`factura_eventos`), 1 nuevo servicio (`EmisorFacturas`), reescritura de `NumeradorFacturas`, 1 nueva ruta/acción de controller, ajustes de UI (botón Emitir + ocultar editar/borrar en emitidas)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: `factura_eventos` lleva `tenant_id` + `BelongsToTenant`. La numeración es por serie del tenant activo; el `MAX(numero)` se calcula bajo el global scope de tenant. Tests de aislamiento: emitir en tenant A no afecta la numeración de B ni expone sus facturas. ✅ PASA.
- **II. Cumplimiento Normativo España-First**: numeración correlativa sin huecos por serie con reinicio anual (decisión de Clarifications, práctica legal en España); factura `emitida` INMUTABLE (no edición/borrado/re-emisión); `regimen_impositivo` ya congelado al crear y sellado al emitir; columnas Verifactu siguen reservadas (envío AEAT diferido). ✅ PASA.
- **III. Integridad Financiera Server-Side**: la emisión NO recalcula importes (se conservan los del borrador). Asignación de `numero` en transacción con `lockForUpdate` sobre la serie → sin huecos/duplicados en concurrencia. Todo en backend; el cliente solo dispara la acción. ✅ PASA.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: numeración de series, aislamiento multi-tenant e inmutabilidad se cubren con tests escritos primero (deben fallar antes de implementar). ✅ PASA (reflejado en el orden de tareas).
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: sin dependencias nuevas; el bloqueo usa `lockForUpdate` estándar de MySQL/InnoDB; no se introduce infraestructura dedicada. Se reutiliza el máximo del código de 005. ✅ PASA.

**Resultado**: sin violaciones. Complexity Tracking vacío.

## Project Structure

### Documentation (this feature)

```text
specs/008-emision-facturas/
├── plan.md              # Este archivo
├── research.md          # Fase 0: decisiones (numeración anual, bloqueo, eventos)
├── data-model.md        # Fase 1: factura_eventos + transiciones de estado
├── quickstart.md        # Fase 1: cómo validar la feature end-to-end
├── contracts/
│   └── emision.md       # Fase 1: contrato de la acción POST emitir
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── EstadoFactura.php              # ya tiene Emitida; sin cambios
├── Models/
│   ├── Factura.php                    # + relación eventos(); + scopes/helpers estado
│   ├── Serie.php                      # sin cambios estructurales
│   └── FacturaEvento.php              # NUEVO (append-only, BelongsToTenant)
├── Services/
│   ├── NumeradorFacturas.php          # REESCRITO: número por (serie, año) bajo lock
│   └── EmisorFacturas.php             # NUEVO: orquesta validación→numeración→congelado→evento
├── Http/
│   ├── Controllers/
│   │   └── FacturaController.php      # + emitir(); index/edit/destroy refuerzan inmutabilidad
│   └── Requests/
│       └── (validación previa a emitir vive en EmisorFacturas / policy)
database/
├── migrations/
│   └── 2026_07_03_1800xx_create_factura_eventos_table.php   # NUEVO
└── factories/
    └── FacturaEventoFactory.php       # NUEVO (para tests)
resources/views/facturas/
├── index.blade.php + su init JS       # botón Emitir; ocultar editar/borrar si emitida
└── show/pdf                           # ya muestran numero_completo ?? 'Borrador'
routes/web.php                         # + POST /facturas/{factura}/emitir

tests/Feature/
├── FacturaEmisionTest.php             # transición, congelado, validaciones previas
├── FacturaNumeracionTest.php          # correlativo, sin huecos, reinicio anual, concurrencia
├── FacturaInmutabilidadTest.php       # no editar/borrar/re-emitir una emitida
└── FacturaEmisionTenantIsolationTest.php  # numeración/visibilidad aisladas por tenant
```

**Structure Decision**: Monolito Laravel existente (Option 2 colapsado a backend + Blade). Se
extiende el módulo de facturas ya presente de la feature 005; no se crean proyectos ni capas nuevas.

## Complexity Tracking

> Sin violaciones de la constitución. Sección vacía a propósito.
