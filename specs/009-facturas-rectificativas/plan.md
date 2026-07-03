# Implementation Plan: Facturas rectificativas (corregir una factura emitida)

**Branch**: `009-facturas-rectificativas` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/009-facturas-rectificativas/spec.md`

## Summary

Permitir corregir una factura ordinaria **ya emitida** mediante una **factura rectificativa**
vinculada a la original. El flujo tiene dos actos: (1) **crear** la rectificativa a partir de una
factura emitida — copia el snapshot del receptor y el `regimen_impositivo`, registra motivo,
modalidad (sustitución/diferencias) y referencia a la original, y queda en `borrador` editable; y
(2) **emitir** la rectificativa — le asigna el correlativo de una **serie rectificativa separada**
(prefijo "R", reinicio anual), congela fecha/datos, la deja inmutable y **marca la original como
`rectificada`**, todo atómico. Una factura solo puede rectificarse una vez. Verifactu real,
simplificadas, ciclo B2B, CRUD de series, pagos y stock quedan fuera de alcance.

**Enfoque técnico**: reutilizar al máximo la maquinaria de la feature 008. `NumeradorFacturas` ya es
genérico sobre la serie (`MAX(numero)+1` por (serie, año) bajo `lockForUpdate`), así que numera la
serie rectificativa sin cambios siempre que la rectificativa apunte a esa serie. `EmisorFacturas`
ya orquesta validación→numeración→congelado→evento en transacción; se extiende para (a) no exigir
`base_total > 0` cuando el tipo es rectificativa (el delta puede ser 0/negativo) y (b) marcar la
original como `rectificada` en la misma transacción cuando `es_rectificativa`. Se añade un servicio
`GeneradorRectificativa` que crea la rectificativa borrador desde la original (validando estado y
unicidad). La modalidad **por diferencias** persiste como totales de cabecera el **delta**
(corregido − original) calculado en backend a nivel de totales/impuestos; las líneas guardan el
detalle corregido de referencia. Migración **aditiva** que añade las columnas de rectificativa a
`facturas` (documentadas en `docs/03-modelo-datos.md` pero aún inexistentes en la tabla) y sembrado
de una serie rectificativa por defecto.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), `barryvdh/laravel-dompdf` (PDF existente), Eloquent

**Storage**: MySQL/MariaDB (hosting compartido; sin features que requieran VPS)

**Testing**: PHPUnit (Feature + Unit), `RefreshDatabase`

**Target Platform**: Aplicación web Laravel sobre hosting compartido tipo cPanel/Hostinger

**Project Type**: Web app (monolito Laravel; backend + Blade)

**Performance Goals**: N/A — operación puntual por usuario; el foco es correctitud transaccional y de numeración, no throughput

**Constraints**: Numeración de la serie rectificativa sin huecos/duplicados bajo concurrencia (bloqueo de fila, reutilizado de 008); inmutabilidad de rectificativa emitida y de original rectificada; atomicidad de "emitir rectificativa + marcar original"; aislamiento por `tenant_id`; delta calculado en backend admitiendo negativos

**Scale/Scope**: 1 migración aditiva (columnas de rectificativa en `facturas`), 1 enum nuevo (`TipoRectificacion`), 1 servicio nuevo (`GeneradorRectificativa`), extensión de `EmisorFacturas`, ajuste de selección de serie por tipo, 2 rutas/acciones (crear rectificativa, ya existe emitir), sembrado de serie rectificativa, ajustes de UI (botón "Rectificar" en emitidas, mostrar condición/motivo/referencia en detalle/PDF)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: la rectificativa, la serie rectificativa y los
  eventos llevan `tenant_id` + global scope. La numeración usa el `MAX(numero)` bajo el scope del
  tenant activo. La creación valida que la original pertenece al mismo tenant (resolución manual en
  el controller, no binding implícito — ver memoria `project_tenant_route_binding`). Tests: rectificar
  en tenant A no afecta numeración ni expone facturas de B; no se puede referenciar una original de
  otro tenant. ✅ PASA.
- **II. Cumplimiento Normativo España-First**: rectificativa en **serie separada** obligatoria con
  correlativo sin huecos y reinicio anual; indica su condición, motivo y referencia a la original
  (art. facturas rectificativas); modalidades sustitución/diferencias; `regimen_impositivo`
  congelado; inmutabilidad de emitida y de la original rectificada. Columnas Verifactu siguen
  reservadas. ✅ PASA.
- **III. Integridad Financiera Server-Side**: totales y **delta** por diferencias se calculan en
  backend desde las líneas (`CalculadoraFactura`) y los totales persistidos de la original; el cliente
  nunca fija un importe. Numeración en transacción con `lockForUpdate`. Marcado de la original y
  emisión en la misma transacción (o todo o nada). ✅ PASA.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: numeración de la serie rectificativa,
  aislamiento multi-tenant, inmutabilidad/unicidad de rectificación y cálculo del delta se cubren con
  tests escritos primero (rojo→verde). ✅ PASA (reflejado en el orden de tareas de `/speckit-tasks`).
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: sin dependencias nuevas; se reutiliza
  `NumeradorFacturas`/`EmisorFacturas`/`CalculadoraFactura` y el `lockForUpdate` estándar de InnoDB;
  migración aditiva; una sola serie rectificativa por defecto (sin CRUD). ✅ PASA.

**Resultado**: sin violaciones. Complexity Tracking vacío.

## Project Structure

### Documentation (this feature)

```text
specs/009-facturas-rectificativas/
├── plan.md              # Este archivo
├── research.md          # Fase 0: modalidades/delta, selección de serie, reutilización de 008
├── data-model.md        # Fase 1: columnas nuevas + transiciones de estado + serie rectificativa
├── quickstart.md        # Fase 1: cómo validar la feature end-to-end
├── contracts/
│   ├── crear-rectificativa.md   # Fase 1: contrato POST /facturas/{factura}/rectificar
│   └── emitir-rectificativa.md  # Fase 1: contrato de emisión (reutiliza POST emitir)
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── EstadoFactura.php              # ya tiene Rectificada; sin cambios
│   ├── TipoFactura.php                # ya tiene Rectificativa; sin cambios
│   └── TipoRectificacion.php          # NUEVO: Sustitucion | Diferencias
├── Exceptions/
│   └── FacturaNoRectificableException.php   # NUEVO (o reutilizar FacturaNoEmitibleException)
├── Models/
│   ├── Factura.php                    # + relaciones facturaRectificada()/rectificativa(); casts nuevos; helpers
│   └── Serie.php                      # sin cambios estructurales (ya tiene tipo)
├── Services/
│   ├── NumeradorFacturas.php          # SIN CAMBIOS (ya genérico por serie/año)
│   ├── GeneradorRectificativa.php     # NUEVO: crea rectificativa borrador desde una emitida
│   └── EmisorFacturas.php             # EXTENDIDO: validación por tipo + marcar original al emitir
database/
├── migrations/
│   └── 2026_07_03_1900xx_add_rectificativa_columns_to_facturas_table.php  # NUEVO (aditiva)
├── seeders/
│   └── SerieSeeder.php                # + serie rectificativa por defecto (codigo 'R')
└── factories/
    ├── SerieFactory.php               # + state rectificativa()
    └── FacturaFactory.php             # + states para rectificativa/emitida (para tests)
app/Http/
├── Controllers/
│   └── FacturaController.php          # + rectificar(); store() elige serie por tipo; index/UI
└── Requests/
    └── StoreRectificativaRequest.php  # NUEVO: valida modalidad + motivo al crear
resources/views/facturas/
├── index.blade.php + su init JS       # botón "Rectificar" solo en emitidas; sin editar/borrar
├── show/detalle                       # condición rectificativa, motivo, enlace a original/rectificativa
└── pdf.blade.php                      # menciones de rectificativa (condición, motivo, referencia)
routes/web.php                         # + POST /facturas/{factura}/rectificar

tests/Feature/
├── RectificativaCreacionTest.php          # crear desde emitida; rechazos (borrador, ya rectificada); snapshot
├── RectificativaEmisionTest.php           # serie separada, correlativo, reinicio anual, marcar original, evento
├── RectificativaDeltaTest.php             # sustitución vs diferencias (delta, negativos, delta cero)
├── RectificativaInmutabilidadTest.php     # no editar/borrar/re-emitir; original rectificada bloqueada
└── RectificativaTenantIsolationTest.php   # numeración y referencias aisladas por tenant
```

**Structure Decision**: Monolito Laravel existente (backend + Blade). Se extiende el módulo de
facturas de las features 005/008; no se crean proyectos ni capas nuevas. Se reutilizan
`NumeradorFacturas`, `CalculadoraFactura` y `factura_eventos` sin duplicar lógica.

## Complexity Tracking

> Sin violaciones de la constitución. Sección vacía a propósito.
