# Implementation Plan: Adjuntar material al asistente pegando o arrastrando

**Branch**: `047-adjuntar-pegar-arrastrar` | **Date**: 2026-09-08 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/047-adjuntar-pegar-arrastrar/spec.md`

## Summary

Añadir dos gestos de entrada de material al widget del asistente —pegar desde el portapapeles y
arrastrar y soltar— sobre la capacidad de recepción que ya construyó la feature 046. La feature es
**solo front**: no se toca el controlador, ni la validación, ni el almacenamiento.

El trabajo real no es capturar los eventos (eso es mecánico), sino resolver el desajuste de orden
que documenta [research.md D2](research.md): hoy el material solo puede subir *después* de que la
persona haya escrito de qué módulo va la importación, y pegar una captura es un gesto que se hace
*antes* de explicar nada. Se resuelve reteniendo el material y preguntando el módulo, en vez de
descartarlo en silencio o adivinarlo.

## Technical Context

**Language/Version**: JavaScript ES5-compatible (el fichero existente no usa transpilación) + Blade
(Laravel 12, PHP 8.2)

**Primary Dependencies**: ninguna nueva. Se usan APIs nativas del navegador (`ClipboardEvent`,
`DataTransfer`, `File`) y `window.showToast` (toastr, ya global).

**Storage**: N/A — no se persiste nada nuevo. El material sigue el camino de la 046.

**Testing**: Pest/PHPUnit para el backend (sin cambios que testear ahí) + validación manual guiada
por [quickstart.md](quickstart.md). Ver "Estrategia de test" abajo.

**Target Platform**: navegadores de escritorio modernos (Chrome, Firefox, Edge, Safari). En táctil
sin teclado los gestos no aplican y el clip sigue siendo el camino ([research.md D8](research.md)).

**Project Type**: aplicación web multi-tenant (Laravel + Blade + JS vanilla vendorizado)

**Performance Goals**: el gesto debe sentirse inmediato — el indicador de adjunto aparece sin
espera perceptible tras soltar o pegar. La subida en sí ya tiene su indicador de estado.

**Constraints**: tope de 5 MB por fichero y tipos admitidos, ambos heredados de la 046 sin cambios.

**Scale/Scope**: 2 ficheros tocados (`public/js/asistente-chat.js`,
`resources/views/partials/asistente-chat.blade.php`) + 2 de documentación
(`resources/views/ayuda/`, `resources/ia/conocimiento/`).

## Constitution Check

| Principio | Estado | Justificación |
| --- | --- | --- |
| **I. Aislamiento multi-tenant** (NON-NEGOTIABLE) | ✅ Cumple | No se añade ninguna consulta ni ruta. El material entra por `POST /asistente/material`, que ya resuelve tenant y conversación del usuario autenticado. FR-013 lo fija como requisito explícito para que el cumplimiento sea verificable y no accidental. |
| **II. Cumplimiento normativo España-first** | ✅ No aplica | La feature no toca facturación, series, impuestos ni Verifactu. |
| **III. Integridad financiera server-side** | ✅ No aplica | No hay cálculo de importes. El front no decide nada sobre el material: solo lo entrega. |
| **IV. Test-First en lógica crítica** (NON-NEGOTIABLE) | ✅ Cumple | El principio acota el test-first a aislamiento multi-tenant, impuestos, series y Verifactu, y admite "flujo de test más flexible" para UI. Esta feature es exclusivamente UI y **no añade lógica de negocio en el servidor**: no hay unidad testeable nueva en PHP. La verificación es la del quickstart, que cubre cada FR. |
| **V. Simplicidad y hosting compartido** | ✅ Cumple | Cero dependencias nuevas, cero build extra, APIs nativas del navegador. No cambia nada de infraestructura. Es la solución más simple que cumple el objetivo (YAGNI): se reutiliza `subirMaterial()` en vez de crear un segundo camino de subida. |

**Convenciones de front aplicables** (`docs/04-front-guidelines.md`, citadas por exigencia de la
regla de oro del CLAUDE.md):

- **"Widget del asistente IA (feature 030)"** — el CSS del widget va scoped bajo
  `.asistente-chat__*` en un `@push('styles')` **dentro del propio partial**, usando
  `var(--primary)` para respetar el color de marca del tenant. La marca de zona de arrastre se
  añade ahí, no en `style.css`.
- **"Notificaciones"** (CLAUDE.md) — todo aviso por `window.showToast(tipo, mensaje)`. Prohibido
  introducir divs `.alert-*` ad-hoc.
- **Excepción documentada del botón de enviar** — la misma sección advierte que el botón del chat
  no usa `withButtonLoading` por ser un icono de 42 px. Esta feature **no toca ese botón**, pero se
  anota para no "corregirlo" al pasar por el fichero.

**Veredicto**: sin violaciones. No se requiere entrada en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```
specs/047-adjuntar-pegar-arrastrar/
├── spec.md
├── plan.md              # este archivo
├── research.md          # D1-D8: decisiones y riesgos
├── quickstart.md        # guía de validación manual por FR
├── contracts/
│   └── gestos.md        # contrato de comportamiento de los gestos
├── checklists/
│   └── requirements.md
└── tasks.md             # lo genera /speckit-tasks
```

No hay `data-model.md`: la feature no introduce entidades ni tablas. El material que maneja ya
está modelado por la 046 (ver "Key Entities" del spec).

### Source Code (repository root)

```
public/js/
└── asistente-chat.js                        # MODIFICADO: gestos + retención de material

resources/views/partials/
└── asistente-chat.blade.php                 # MODIFICADO: CSS de zona de arrastre (@push styles)

resources/views/ayuda/
└── <guía del asistente>.blade.php           # MODIFICADO: los gestos nuevos (capa 3)

resources/ia/conocimiento/
└── asistente.md                             # MODIFICADO: qué sabe el asistente de sí mismo (capa 4)
```

**Structure Decision**: se modifican los dos ficheros que ya son dueños del widget, sin crear
módulos nuevos. El JS del asistente son 923 líneas en un IIFE con estado compartido
(`materialToken`, `moduloImportacion`); partirlo ahora sería una refactorización de alcance mayor
que la feature. La guía de front documenta el patrón de partición
("Partición de un archivo JS grande en módulos con estado compartido") para cuando haga falta —
no es este el momento, y el CLAUDE.md manda preferir lo simple.

## Estrategia de test

El Principio IV admite flujo flexible para UI, pero "flexible" no es "ninguno":

1. **Sin tests PHP nuevos**: no hay código de servidor nuevo. Los tests de la 046
   (`tests/Feature/Asistente/*`, `tests/Unit/MaterialImportableTest.php`) ya cubren validación de
   tipo, tamaño, permisos y acumulación por token, y deben seguir en verde sin tocarlos — eso es
   la prueba de que la feature no rompió el camino existente.
2. **Validación manual guiada**: [quickstart.md](quickstart.md) recorre cada FR con pasos
   reproducibles, incluidos los casos destructivos (perder la página al soltar, romper el pegado
   de texto) que ningún test automático de este repo cubriría hoy.
3. **Regresión obligatoria**: la suite completa antes de dar la feature por cerrada.

## Fases de implementación

**Fase 1 — Retención de material sin módulo** ([research D2](research.md))
El cambio con más riesgo, y el que habilita P1. Se hace primero porque los gestos sin esto
adjuntarían en silencio a la nada.

**Fase 2 — Pegar** (P1, [research D3](research.md) y [D4](research.md))
Listener acotado al campo de escribir; detección de ficheros; renombrado con sello temporal;
pegado de texto intacto.

**Fase 3 — Arrastrar y soltar** (P2, [research D5](research.md))
Listeners sobre el panel, `preventDefault` en `dragover` y `drop`, marca visual con contador de
entradas, CSS en el partial.

**Fase 4 — Varios ficheros y rechazos** (P3, [research D6](research.md))
Tomar el primero, avisar del resto. Verificar que los rechazos heredados salen por toast.

**Fase 5 — Documentación** (capas 3 y 4 del CLAUDE.md)
Guía in-app y base de conocimiento del asistente. Obligatorio, no opcional: una guía
desactualizada le miente al usuario, y un asistente que no sabe que se le puede pegar una imagen
explica mal su propia función.

## Complexity Tracking

Sin desviaciones de la constitución que justificar.
