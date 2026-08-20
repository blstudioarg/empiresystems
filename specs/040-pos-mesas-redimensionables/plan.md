# Implementation Plan: Mesas redimensionables por celdas en el plano del POS

**Branch**: `040-pos-mesas-redimensionables` | **Date**: 2026-08-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/040-pos-mesas-redimensionables/spec.md`

## Summary

Una mesa del plano deja de ocupar una celda y pasa a ocupar un rectángulo de N×M celdas, ajustable
arrastrando sus bordes con encaje a la rejilla. El atributo `tamano` (pequeña/mediana/grande), que
solo escalaba píxeles dentro de una celda, se retira y se sustituye por `ancho_celdas`/`alto_celdas`
en `pos_mesas`. La colisión —hoy comparación de celdas puntuales, tanto en cliente como en
`PosPlanoReacomodo`— pasa a solapamiento de rectángulos, lo que además elimina el defecto actual de
que las formas `rectangular` y `barra` se dibujen sobre celdas vecinas sin reservarlas.

Enfoque técnico (detalle en [research.md](./research.md)): widget `resizable` de jQuery UI ya
vendorizado, con `grid`/`containment` para el encaje y clamp en el evento `resize` para el bloqueo
por colisión (D1, D2); validación AABB simétrica en cliente y servidor (D3); migración única que
añade las columnas, convierte los datos derivando de `forma`, corrige los solapamientos que la
conversión destaparía y elimina `tamano` (D8).

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12; JavaScript ES5 en los `plugins-init` (sin build step)

**Primary Dependencies**: jQuery 3 + jQuery UI 1.11.1 (`public/vendor/jqueryui/`, ya vendorizado e
incluyendo `resizable.js`); toastr. **Ninguna dependencia nueva.**

**Storage**: MySQL/MariaDB — tabla `pos_mesas` (bajo `tenant_id` + `TenantScope`)

**Testing**: PHPUnit (`tests/Feature/Pos/`, junto a `PlanoSalaTest.php`); validación manual guiada en
[quickstart.md](./quickstart.md)

**Target Platform**: navegador de escritorio y **tablet** (la Sala del POS es pantalla táctil de uso
diario); hosting compartido cPanel

**Project Type**: aplicación web Laravel monolítica con Blade + JS vendorizado

**Performance Goals**: el redimensionado debe seguir al dedo/puntero sin lag perceptible; la
validación de colisión corre en cada evento `resize` sobre un máximo de 48 celdas por zona, coste
despreciable

**Constraints**: sin build step (Principio V); el color del borde de la mesa está reservado para el
estado libre/ocupada/olvidada y no puede reutilizarse como feedback de bloqueo
(`docs/04-front-guidelines.md`); rejilla fija de 8×6 por zona

**Scale/Scope**: 1 tabla modificada, 1 migración, 2 clases de soporte, 1 controlador, 1 vista Blade,
1 archivo JS, 1 guía in-app, 1 archivo de base de conocimiento IA

## Constitution Check

*GATE: revisado antes de Phase 0 y de nuevo tras Phase 1. Sin violaciones.*

| Principio | Aplicación en esta feature | Estado |
|-----------|---------------------------|--------|
| **I. Aislamiento multi-tenant** | `pos_mesas` y `pos_zonas` ya llevan `tenant_id` y `TenantScope`; la migración solo añade columnas geométricas. La zona se resuelve manualmente en el controlador (nunca binding implícito — pitfall documentado del proyecto). Se extiende el test de aislamiento existente para cubrir que un tenant no puede redimensionar mesas de otro. | ✅ |
| **II. Cumplimiento normativo** | No aplica: geometría de una pantalla, sin datos personales, fiscales ni de facturación. Ningún dato nuevo es personal, así que no hay retención ni purga que definir. | ✅ N/A |
| **III. Integridad server-side** | El cliente es previsualización; el servidor **revalida toda la geometría** (rejilla, solapamiento, mínimo 1×1) y rechaza la petición entera sin cambios parciales (FR-013, transacción existente). El bloqueo optimista por `version` se conserva. | ✅ |
| **IV. Test-first en lógica crítica** | El aislamiento multi-tenant es área crítica ⇒ test primero. La lógica de solapamiento no es un área listada en la constitución (no es impuestos/numeración/Verifactu), pero es el corazón de la feature y un fallo es silencioso, así que se le aplica el mismo rigor por decisión de plan: tests de `PosPlanoReacomodo` antes de tocar su implementación. | ✅ |
| **V. Simplicidad / hosting compartido** | Cero dependencias nuevas, cero build step, cero infraestructura. Se retira un atributo (`tamano`) en vez de sumar uno. El reenviador táctil son ~20 líneas propias en lugar de vendorizar una librería sin mantenimiento (D5). | ✅ |

**Complexity Tracking**: sin violaciones que justificar; la sección se omite.

## Convenciones de front aplicables (regla de oro)

Citadas explícitamente para que quede trazable que condicionaron el plan, no solo la implementación:

- **"Tarjeta de mesa y sus tres estados (Sala del POS, feature 038)"** — el color del borde comunica
  el estado. ⇒ El feedback de bloqueo usa `box-shadow`, no color de borde (D7, FR-009). Esta
  restricción **nació de leer la doc**, no de la petición del usuario.
- **"Estado de carga en botones (AJAX/fetch)"** — el guardado del plano ya usa `withButtonLoading`;
  esta feature no añade peticiones, así que no introduce estados de carga nuevos.
- **"Acción frecuente y consecuente pero no destructiva: toast con Deshacer"** — redimensionar es
  frecuente, reversible y vive dentro de un modo edición con guardado explícito ⇒ **ni modal de
  confirmación ni toast de deshacer**. El "deshacer" es no guardar.
- **"Notificaciones: siempre toastr"** — el aviso de "no hay hueco" al mover sigue usando
  `window.showToast`; el bloqueo al redimensionar **no** usa toast (se dispararía en bucle, D7).
- **"Partición de un archivo JS grande en módulos"** — `pos-sala-plano.init.js` está en 457 líneas y
  esta feature le suma. Se vigila el umbral: si supera ~600 líneas, se parte siguiendo el patrón
  orquestador + módulos de la feature 038 (tarea explícita en `tasks.md`).
- **"Ayuda contextual"** — la guía de la Sala menciona el selector de tamaños que desaparece ⇒ se
  actualiza en el mismo cambio (FR-016).

## Project Structure

### Documentation (this feature)

```text
specs/040-pos-mesas-redimensionables/
├── plan.md              # Este archivo
├── spec.md              # Especificación
├── research.md          # Decisiones D1-D10
├── data-model.md        # Cambios en pos_mesas + invariantes
├── quickstart.md        # Guion de validación manual y automática
├── contracts/
│   └── plano-guardar.md # Contrato del PUT de guardado del plano
├── checklists/
│   └── requirements.md  # Checklist de calidad del spec
└── tasks.md             # Salida de /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/Pos/
│   └── PlanoSalaController.php      # MOD: reglas de validación (fuera `tamano`, entran ancho/alto)
├── Models/
│   └── PosMesa.php                  # MOD: fillable + casts
└── Support/
    ├── PosPlanoCeldas.php           # MOD: primeraCeldaLibre pasa a considerar rectángulos
    └── PosPlanoReacomodo.php        # MOD: validación puntual → validación por rectángulos

database/migrations/
└── 2026_08_19_HHMMSS_mesas_ocupan_rectangulo_de_celdas.php   # NUEVA (D8)

resources/
├── views/pos/sala.blade.php         # MOD: CSS de asas de resize + feedback de bloqueo; fuera el popover de tamaños
├── views/ayuda/pos-sala.blade.php   # MOD: guía in-app (FR-016)
└── ia/conocimiento/pos-sala.md      # MOD: base de conocimiento del asistente (FR-017)

public/js/plugins-init/
└── pos-sala-plano.init.js           # MOD: resizable, colisión AABB, sillas derivadas, shim táctil

tests/Feature/Pos/
├── PlanoSalaTest.php                # MOD: casos de geometría rectangular
├── PlanoRectangulosTest.php         # NUEVA: solapamiento, límites, mínimo 1×1, rechazo atómico
├── PlanoConversionTest.php          # NUEVA: migración no deja solapes ni mesas fuera de rejilla
└── AislamientoMesasTest.php         # MOD: un tenant no redimensiona mesas de otro
```

**Structure Decision**: monolito Laravel existente; la feature no crea capas ni directorios nuevos.
Toda la lógica de geometría server-side se concentra en `app/Support/PosPlanoReacomodo.php` (que ya es
el guardián de la validación del plano) y su equivalente cliente en `pos-sala-plano.init.js`, para
que la regla de colisión viva en **exactamente dos sitios** y sea trivial comprobar que dicen lo
mismo.

## Fases de implementación (resumen; el desglose real lo produce `/speckit-tasks`)

1. **Datos y servidor primero** — migración + modelo + `PosPlanoReacomodo` + validación del
   controlador, con sus tests en rojo antes de implementar. Al terminar esta fase el backend ya
   acepta y valida rectángulos aunque la interfaz siga enviando 1×1.
2. **Interfaz de redimensionado** — `resizable` con encaje y clamp, feedback de bloqueo, retirada del
   popover de tamaños, adaptación de `dimensiones()` y del reacomodo al mover.
3. **Sillas** — `sillasParaMesa` derivada del contorno, recalculada al soltar el borde. La Sala en
   modo servicio (rejilla de tarjetas) no dibuja el plano y queda fuera de alcance.
4. **Táctil** — shim de reenvío de eventos y ajuste de la zona de agarre; se valida en tablet real o
   emulación táctil.
5. **Documentación** — guía in-app, base de conocimiento IA y, si aplica, `docs/03-modelo-datos.md` y
   `docs/04-front-guidelines.md` (el patrón "feedback de bloqueo por sombra, nunca por color de
   borde" es una convención reutilizable que merece quedar escrita).

## Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|--------|---------|-----------|
| El arrastre existente puede no funcionar ya en tablet (jQuery UI 1.11.1 sin soporte táctil, D5) | SC-005 no se cumpliría y quedaría oculto tras "no es culpa de esta feature" | Verificarlo **antes** de implementar el shim (primer paso de la fase 4) y reportar el resultado explícitamente, sea cual sea |
| La conversión de datos destapa solapamientos preexistentes | Planos que no se pueden guardar hasta arreglarlos a mano | Paso (c) de D8: la migración reduce la ocupación de la mesa que colisiona, con orden determinista, y un test lo verifica |
| El asa de arrastre se solapa con la esquina `ne` de redimensionado | El usuario mueve la mesa cuando quería agrandarla | D6: renunciar a la esquina `ne` (siete asas) |
| `pos-sala-plano.init.js` crece más allá de lo manejable | Bugs de estado compartido como los que forzaron partir `pos-form.js` | Tarea explícita de revisar el umbral (~600 líneas) al cerrar la implementación |
