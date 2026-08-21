# Implementation Plan: Vista de plano en la Sala en modo servicio

**Branch**: `041-plano-sala-servicio` | **Date**: 2026-08-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/041-plano-sala-servicio/spec.md`

## Summary

Poner a trabajar durante el servicio el plano que hoy solo existe dentro del editor: la Sala gana un
selector de vista (tarjetas / plano) y, en vista de plano, dibuja el mismo lienzo con las mesas en su
posición, forma y ocupación reales, coloreadas por estado y con el importe pendiente, donde tocar una
mesa lleva al mismo sitio que tocar su tarjeta.

**Enfoque técnico**: es una feature **de interfaz, sin cambios en el servidor**. El estado de la Sala
ya entrega todo lo necesario (D8 de [research.md](./research.md)). La pieza que decide la calidad del
resultado es **no duplicar el dibujo**: las funciones de geometría, sillas y HTML de mesa se extraen
del editor a un módulo compartido (`PosPlanoDibujo`, D1) que consumen los dos modos. La preferencia de
vista vive en `localStorage` por usuario (D2), y las mesas sin posición en la rejilla se muestran bajo
el lienzo con el componente de tarjeta que ya existe (D5).

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12) en servidor; JavaScript ES5 en cliente (sin build step,
mismo estilo que el resto de `public/js/plugins-init/`).

**Primary Dependencies**: ninguna nueva. jQuery y el bundle vendorizado de jQuery UI ya cargan en la
vista, pero esta feature **no los necesita**: el modo servicio es solo lectura, sin `draggable` ni
`resizable`.

**Storage**: ninguna tabla nueva ni columna nueva. La única persistencia es `localStorage` para la
preferencia de vista (dato de interfaz, no de negocio).

**Testing**: PHPUnit/Pest sobre SQLite en memoria (`phpunit.xml`). Un test nuevo que fija el contrato
del payload de la Sala; los de aislamiento existentes cubren la parte crítica.

**Target Platform**: navegador de tablet (uso principal del POS) y escritorio.

**Project Type**: aplicación web Laravel con vistas Blade y JS por pantalla, sin SPA.

**Performance Goals**: repintado del plano imperceptible al refrescar la Sala; el volumen es de 48
mesas como máximo por zona (rejilla 8×6), así que no hay presión de rendimiento real.

**Constraints**: sin build step (Principio V); el ancho del lienzo debe caber en la pantalla sin
obligar a scroll en dos ejes (FR-021); el color del borde está reservado al estado de la mesa
(`docs/04-front-guidelines.md`).

**Scale/Scope**: una pantalla (`resources/views/pos/sala.blade.php`), tres archivos JS (uno nuevo,
uno nuevo extraído, uno modificado), cuatro capas de documentación.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Aplica | Evaluación |
|-----------|--------|------------|
| **I. Aislamiento Multi-Tenant** (NON-NEGOTIABLE) | Sí | La feature **no abre ninguna vía de datos nueva**: consume el mismo payload de `SalaController` que ya usa la vista de tarjetas, que va por el `TenantScope`. No hay endpoints, queries ni bindings nuevos. `tests/Feature/Pos/AislamientoMesasTest.php` ya cubre que la Sala no expone mesas de otro tenant. **PASA**. |
| **II. Cumplimiento Normativo España-First** | No | No toca facturación, impuestos, numeración ni Verifactu. Tampoco datos personales nuevos: la preferencia de vista es una elección de interfaz, sin dato identificable ni necesidad de retención/purga. **NO APLICA**. |
| **III. Integridad Financiera Server-Side** | Sí (por vigilancia) | El plano **muestra** el importe pendiente que ya calcula el servidor; no lo recalcula ni lo deriva. El estado `olvidada` lo sigue decidiendo el servidor (FR-008). Ninguna vista nueva hace aritmética de importes ni de fechas. **PASA**. |
| **IV. Test-First en Lógica Crítica** (NON-NEGOTIABLE) | Parcial | Las áreas test-first (aislamiento, impuestos, numeración, Verifactu) **no se tocan**. Lo único automatizable con valor aquí es fijar el contrato del payload de la Sala, y ese test se escribe **antes** del cambio de vista (T004). El resto es UI, donde la constitución permite un flujo más flexible; la validación va por `quickstart.md`. **PASA**. |
| **V. Simplicidad y Hosting Compartido** | Sí | Cero dependencias nuevas, cero migraciones, cero endpoints. Se reutilizan el lienzo, el dibujo de mesas, el componente de tarjeta y el evento de cambio de zona que ya existen. La única estructura nueva es un módulo JS **extraído** de código existente, que además reduce el tamaño de un archivo que ya rozaba el umbral de partición de `docs/04-front-guidelines.md`. **PASA**. |

**Resultado del gate (pre-Phase 0)**: PASA sin violaciones. No hay nada que registrar en Complexity
Tracking.

**Re-evaluación (post-Phase 1)**: los artefactos de diseño no introdujeron ninguna tabla, endpoint ni
dependencia. `data-model.md` confirma que **no hay cambios de modelo de datos**. El gate sigue en
PASA.

## Project Structure

### Documentation (this feature)

```text
specs/041-plano-sala-servicio/
├── plan.md              # Este archivo
├── research.md          # Fase 0: D1-D10
├── data-model.md        # Fase 1: sin cambios de esquema; contrato de lo que consume la vista
├── quickstart.md        # Fase 1: guion de validación manual
├── contracts/
│   └── vista-sala.md    # Fase 1: contrato de interfaz de la Sala (payload + comportamiento)
├── checklists/
│   └── requirements.md  # Calidad del spec (16/16)
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
public/js/plugins-init/
├── pos-plano-dibujo.js              # NUEVO — módulo compartido de dibujo (extraído del editor, D1)
├── pos-sala-plano-servicio.init.js  # NUEVO — vista de plano en modo servicio
├── pos-sala-plano.init.js           # MODIFICADO — pasa a consumir PosPlanoDibujo
├── pos-sala.init.js                 # MODIFICADO — selector de vista y coordinación con el plano
└── pos-sala-plano-gestion.init.js   # sin cambios

resources/views/pos/
└── sala.blade.php                   # MODIFICADO — selector de vista, contenedor de servicio, CSS

resources/views/ayuda/
└── pos-sala.blade.php               # MODIFICADO — guía in-app (capa 3)

resources/ia/conocimiento/
└── pos-hosteleria.md                # MODIFICADO — base de conocimiento del asistente (capa 4)

docs/
└── 04-front-guidelines.md           # MODIFICADO — convención de "misma info, distinto envase"

tests/Feature/Pos/
└── SalaPayloadPlanoTest.php         # NUEVO — fija el contrato del payload (D8/D10)
```

**Structure Decision**: se mantiene la estructura de la aplicación Laravel existente; no hay proyecto
nuevo ni reorganización. El único movimiento estructural es la **extracción** del dibujo del plano a
`pos-plano-dibujo.js`, siguiendo la guía de partición de archivos JS de `docs/04-front-guidelines.md`
(feature 038): el editor deja de ser el dueño exclusivo del dibujo y pasa a ser uno de sus dos
consumidores.

## Convenciones citadas (trazabilidad de la regla de oro)

`CLAUDE.md` exige citar explícitamente las convenciones leídas que condicionan el diseño:

- **`docs/04-front-guidelines.md` → "Tarjeta de mesa y sus tres estados"** — el borde comunica el
  estado y la vista nunca hace aritmética de fechas. Condiciona FR-006 y FR-008: el plano usa el
  mismo código de color y el mismo `olvidada` que decide el servidor.
- **`docs/04-front-guidelines.md` → "Feedback de bloqueo cuando el borde ya comunica estado"**
  (feature 040) — confirma que el color del borde está reservado; cualquier señal extra de la vista
  de servicio deberá usar otro recurso visual.
- **`docs/04-front-guidelines.md` → filtros de zona con `.pos-filtro`** — condiciona D3: el selector
  de vista **no** reutiliza `.pos-filtro`, porque no filtra; va en la cabecera de acciones.
- **`docs/04-front-guidelines.md` → "Partición de un archivo JS grande…"** (feature 038) — condiciona
  D1 y la estructura de archivos de arriba.
- **`.specify/memory/constitution.md`** — Principios I, III y V, evaluados en el gate.

## Complexity Tracking

No aplica: el Constitution Check pasa sin violaciones.
