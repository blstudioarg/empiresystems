# Implementation Plan: Plano de sala arrastrable (POS)

**Branch**: `039-pos-plano-mesas` | **Date**: 2026-08-11 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/039-pos-plano-mesas/spec.md`

## Summary

Sustituir el grid automático de la pantalla de Sala (`resources/views/pos/sala.blade.php`) por un
plano por zona en el que cada mesa se posiciona libremente sobre una rejilla de 8×6 celdas,
arrastrable en un "modo edición" activado desde la propia Sala. `pos_mesas` gana columnas de
posición (`fila`, `columna`), forma (`forma`) y tamaño (`tamano`). El arrastre usa jQuery UI
`draggable`/`droppable` (mismo paquete ya vendorizado que `sortable`, patrón de
`docs/04-front-guidelines.md`); el reacomodo por colisión se resuelve en el cliente por distancia
euclídea a celdas libres, y el guardado es explícito por botón, con reconstrucción/validación
server-side y bloqueo optimista por zona (mismo patrón `version` que `PosCuenta`, feature 038).

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12), JavaScript ES6 (jQuery 3.x, sin build step — assets
servidos directo desde `public/js`)

**Primary Dependencies**: Laravel 12, `stancl/tenancy` (single-database), jQuery UI (vendorizado
en `public/vendor/jqueryui/`, ya incluye `draggable`/`droppable`/`sortable` en el mismo bundle)

**Storage**: MySQL/MariaDB — ampliación de `pos_mesas` (feature 038) + nueva columna `version` en
`pos_zonas` para bloqueo optimista del plano

**Testing**: PHPUnit (Feature tests, patrón existente en `tests/Feature/Pos/*`)

**Target Platform**: Navegador en tablet (táctil, landscape) — mismo target que el resto del POS

**Project Type**: Web application (Laravel monolito + Blade, sin frontend separado)

**Performance Goals**: guardar el plano completo de una zona (hasta 48 mesas) en una sola petición
en <1s en condiciones normales de hosting compartido; el arrastre en pantalla no debe sentirse con
lag perceptible en tablet gama media (60fps deseable, sin garantía dura)

**Constraints**: sin librerías nuevas (Principio V: hosting compartido, YAGNI); rejilla fija de
8×6 celdas por zona (Clarifications 2026-08-11); reacomodo por distancia euclídea; guardado por
botón, nunca por evento de arrastre (`docs/04-front-guidelines.md`)

**Scale/Scope**: hasta 48 mesas por zona, número de zonas por tenant ya acotado por el módulo
existente (feature 038, sin límite duro documentado, típicamente <10 zonas)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio I (Aislamiento Multi-Tenant)** — PASA. `pos_mesas`/`pos_zonas` ya usan
  `BelongsToTenant` + `TenantScope` (feature 038); las columnas nuevas no cambian ese scope. El
  endpoint de guardado de plano opera solo sobre mesas de la zona resuelta bajo el tenant activo
  (mismo patrón de resolución manual visto en `PosMesaController::validar()` para evitar el pitfall
  de binding implícito documentado en memoria del proyecto).
- **Principio II (Cumplimiento Normativo / RGPD)** — PASA, no aplica. Posición/forma/tamaño de una
  mesa no son datos personales; no se añade ninguna tabla ni columna con datos de personas físicas.
- **Principio III (Integridad Financiera Server-Side)** — PASA, no aplica. Esta feature no toca
  importes, impuestos ni numeración.
- **Principio IV (Test-First en Lógica Crítica)** — la lógica de reacomodo/colisión y el guardado
  concurrente con bloqueo optimista **no** están en la lista taxativa del principio (tenant,
  impuestos, numeración, Verifactu), así que no es estrictamente NON-NEGOTIABLE aquí, pero por
  tocar aislamiento de tenant en el endpoint de guardado, sus tests de aislamiento sí siguen
  Test-First. La lógica de reacomodo (cliente) se cubre con verificación manual (quickstart) más
  tests de Feature sobre el resultado persistido server-side.
- **Principio V (Simplicidad / Hosting Compartido)** — PASA. Sin librerías nuevas, sin
  infraestructura adicional; jQuery UI ya vendorizado cubre `draggable`/`droppable`.

Sin violaciones. No se requiere sección de Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/039-pos-plano-mesas/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
│   └── plano-sala.md
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
database/migrations/
└── 2026_08_11_xxxxxx_add_plano_a_pos_mesas_y_pos_zonas.php   # fila/columna/forma/tamano + version en pos_zonas

app/Models/
├── PosMesa.php           # + fillable/casts: fila, columna, forma, tamano
└── PosZona.php            # + fillable/casts: version

app/Http/Controllers/Pos/
├── SalaController.php     # + payload de fila/columna/forma/tamano en estado()
└── PlanoSalaController.php  # NUEVO: guardar plano de una zona (batch, con bloqueo optimista)

app/Support/
└── PosPlanoReacomodo.php  # NUEVO: cálculo server-side de validación/colisión (fuente de verdad, no confía en el cliente)

resources/views/pos/
└── sala.blade.php         # plano por zona en vez de grid automático; botón "Editar plano"/"Guardar plano"

public/js/plugins-init/
└── pos-sala-plano.init.js  # NUEVO: jQuery UI draggable/droppable, reacomodo en cliente (preview), guardado por botón

public/css/
└── pos-sala.css (o bloque @push('styles') en sala.blade.php)  # formas de mesa, lienzo punteado en modo edición, animación de arrastre

routes/web.php
└── + Route::match(['put','patch'], '/pos/sala/zonas/{zona}/plano', [PlanoSalaController::class, 'update'])->name('pos.sala.plano.update')
    dentro del grupo can:ver-configuracion (mismo permiso que configuracion.pos.*, según Assumptions del spec)

tests/Feature/Pos/
└── PlanoSalaTest.php      # NUEVO: aislamiento de tenant, guardado batch, conflicto de versión, reacomodo sin solapamiento
```

**Structure Decision**: Laravel monolito existente (sin frontend separado). Se extiende el módulo
`pos_` de feature 038 con una migración adicional y un controller nuevo dedicado al guardado del
plano (separado de `PosMesaController`/`SalaController` porque su contrato es un batch por zona
con bloqueo optimista, distinto al CRUD de una mesa a la vez); la vista de Sala existente se
amplía en vez de crear una pantalla nueva, cumpliendo FR-001 (modo edición vive en la Sala
operativa).

## Complexity Tracking

*No violations — sección no aplica.*
