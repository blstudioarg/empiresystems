# Tasks: Adjuntar material al asistente pegando o arrastrando

**Feature**: 047-adjuntar-pegar-arrastrar | **Branch**: `047-adjuntar-pegar-arrastrar`
**Input**: [spec.md](spec.md), [plan.md](plan.md), [research.md](research.md),
[contracts/gestos.md](contracts/gestos.md), [quickstart.md](quickstart.md)

**Tests**: no se generan tareas de test automático. La feature es solo front y no añade lógica de
servidor (ver "Estrategia de test" en [plan.md](plan.md)); el Principio IV de la constitución acota
el test-first a aislamiento multi-tenant, impuestos, series y Verifactu. La verificación es el
recorrido de [quickstart.md](quickstart.md) más la regresión de la suite existente, ambas como
tareas explícitas.

**Ficheros que se tocan** (solo estos cuatro):

- `public/js/asistente-chat.js` — gestos y retención
- `resources/views/partials/asistente-chat.blade.php` — CSS de la zona de arrastre
- `resources/views/ayuda/importar-exportar.blade.php` — guía in-app (capa 3)
- `resources/ia/conocimiento/asistente.md` — base de conocimiento (capa 4)

⚠️ **Casi todas las tareas tocan el mismo fichero JS**, así que hay muy pocas paralelizables. El
marcador `[P]` es escaso a propósito: marcar en paralelo tareas sobre `asistente-chat.js` produciría
conflictos.

---

## Phase 1: Setup

- [X] T001 Verificar la línea base ejecutando `php artisan test --filter="Asistente|Material|Importacion"` y anotar el resultado; sirve de referencia para la regresión final de [quickstart.md](quickstart.md)
- [X] T002 Releer la sección "Widget del asistente IA (feature 030)" de `docs/04-front-guidelines.md` para confirmar dónde va el CSS (`@push('styles')` dentro del partial) y la excepción del botón de enviar, que **no** hay que tocar ([research D7](research.md): estilos scoped con `var(--primary)` y avisos siempre por `window.showToast`)

---

## Phase 2: Foundational — retención de material sin módulo

**BLOQUEANTE**: sin esto, pegar una captura recién abierto el chat no haría nada en silencio
([research D2](research.md), [contrato G3](contracts/gestos.md)). Es el desajuste central de la
feature y habilita la historia P1.

- [X] T003 En `public/js/asistente-chat.js` añadir el estado de material retenido (junto a `materialToken` y `moduloImportacion`, línea ~51) y una función `retenerMaterial(fichero)` que lo guarde cuando `moduloImportacion` es `null`
- [X] T004 En `public/js/asistente-chat.js` extender `subirMaterial(fichero)` (línea ~274) para que, en vez de salir en silencio por la guarda `if (!urlMaterial || !moduloImportacion) return;` (línea 275), delegue en `retenerMaterial()` y pida el módulo en una frase que nombre clientes, artículos y proveedores
- [X] T005 En `public/js/asistente-chat.js` enganchar la liberación del material retenido dentro de `detectarContextoImportacion()` (línea ~228): en cuanto se asigna `moduloImportacion`, subir el fichero retenido automáticamente
- [X] T006 En `public/js/asistente-chat.js` limpiar el material retenido en el reset de conversación (junto a `olvidarMaterial()` y `moduloImportacion = null`, línea ~385) para que no sobreviva a un cambio de hilo, como exige [contrato G3](contracts/gestos.md)

**Checkpoint**: adjuntar con el clip sigue funcionando igual que antes, y el estado de retención
existe aunque todavía ningún gesto lo alimente.

---

## Phase 3: User Story 1 — Pegar una captura de pantalla (P1) 🎯 MVP

**Goal**: que pegar con Ctrl+V en el campo del chat adjunte el fichero del portapapeles, sin
romper el pegado de texto.

**Independent Test**: copiar una imagen, pegarla en el campo del chat, comprobar que queda
adjuntada y que el asistente la analiza igual que un fichero elegido con el clip.

- [X] T007 [US1] En `public/js/asistente-chat.js` añadir el listener de `paste` **sobre el campo de escribir** (`input`, línea ~19), nunca sobre `document`, según [research D3](research.md) — cumple FR-001 (adjuntar pegando) y FR-004 (acotado al panel)
- [X] T008 [US1] En `public/js/asistente-chat.js` recorrer `clipboardData.items` quedándose con los de `kind === 'file'`; si no hay ninguno, retornar sin tocar el evento para que el texto se inserte normalmente (FR-003, SC-004)
- [X] T009 [US1] En `public/js/asistente-chat.js` implementar `nombrarCaptura(fichero)` que devuelva un `File` nuevo con nombre `captura-YYYYMMDD-HHMMSS.<ext>`, derivando la extensión del tipo del fichero ([research D4](research.md), FR-005) — el servidor valida por extensión, así que sin ella el fichero se rechazaría
- [X] T010 [US1] En `public/js/asistente-chat.js` conectar el gesto de pegar con `subirMaterial()` (que ya cubre retención, validación y toasts), llamando a `preventDefault()` **solo** cuando se adjuntó un fichero y no hay texto acompañante, para preservar el caso de contenido mixto

**Checkpoint**: E1, E2, E3 y E4 de [quickstart.md](quickstart.md) pasan. La feature ya entrega su
valor principal.

---

## Phase 4: User Story 2 — Arrastrar y soltar (P2)

**Goal**: que soltar un fichero sobre el panel lo adjunte, con marca visual y sin que el navegador
se lleve la página por delante.

**Independent Test**: arrastrar un fichero admitido desde el explorador hasta el panel, soltarlo y
comprobar que queda adjuntado.

- [X] T011 [US2] En `public/js/asistente-chat.js` añadir los listeners `dragover`, `dragenter`, `dragleave` y `drop` sobre el panel (`panel`, línea ~14), con `preventDefault()` en `dragover` y en `drop` — cumple FR-002 (adjuntar arrastrando); sin el `preventDefault` el navegador abre el fichero y descarta la aplicación (FR-008, SC-005), el fallo más caro de la feature
- [X] T012 [US2] En `public/js/asistente-chat.js` implementar el contador de entradas/salidas de arrastre para que la marca visual no parpadee al pasar sobre elementos hijos del panel ([research D5](research.md), FR-007)
- [X] T013 [US2] En `public/js/asistente-chat.js` tomar el fichero de `dataTransfer.files` al soltar y pasarlo a `subirMaterial()`; si el arrastre no trae ficheros (texto o enlace), apagar la marca y no adjuntar nada
- [X] T014 [US2] En `resources/views/partials/asistente-chat.blade.php` añadir el CSS de la zona de destino en el `@push('styles')` existente, scoped bajo `.asistente-chat__*` y usando `var(--primary)` para respetar el color de marca del tenant, según `docs/04-front-guidelines.md` — cumple FR-006 (marca visual de zona de destino)

**Checkpoint**: E5, E6, E7 y E8 de [quickstart.md](quickstart.md) pasan.

---

## Phase 5: User Story 3 — Rechazos y múltiples ficheros (P3)

**Goal**: que los caminos nuevos no degraden la calidad de los mensajes de error existentes, y que
aportar varios ficheros avise en vez de descartar en silencio.

**Independent Test**: arrastrar un fichero de tipo no admitido y comprobar que aparece el mismo
mensaje explicativo que al elegirlo con el clip.

- [X] T015 [US3] En `public/js/asistente-chat.js` detectar cuando un gesto aporta más de un fichero, adjuntar el primero y avisar con `window.showToast` de que el resto no se tomó (FR-011, [research D6](research.md)); aplica tanto a pegar como a soltar
- [ ] T016 [US3] Verificar en el navegador que los rechazos por tipo, tamaño y permiso llegan por `window.showToast` con el texto del servidor, sin introducir ningún bloque `.alert-*` ad-hoc — cumple FR-009 (mismas reglas), FR-010 (mismos mensajes) y SC-003; los tres gestos comparten `subirMaterial()`, así que esto confirma el invariante 1 de [contrato G5](contracts/gestos.md)
- [ ] T016b [US3] Verificar que los tres gestos convergen en el mismo estado posterior —mismo indicador de adjunto, mismo botón de quitar, misma acumulación por token— recorriendo E11 y E12 de [quickstart.md](quickstart.md) (FR-012, SC-006)

**Checkpoint**: E9, E10 y E13 de [quickstart.md](quickstart.md) pasan.

---

## Phase 6: Documentación (capas 3 y 4 del CLAUDE.md)

**Obligatorio, no opcional.** Una guía desactualizada le miente al usuario, y un asistente que no
sabe que se le puede pegar una imagen explica mal su propia función.

- [X] T017 [P] En `resources/views/ayuda/importar-exportar.blade.php` documentar los dos gestos nuevos junto al clip que ya se menciona ahí (capa 3)
- [X] T018 [P] En `resources/ia/conocimiento/asistente.md` actualizar la sección "Importar ficheros conversando" (línea ~33): hoy afirma que el clip es la única vía de adjuntar, lo que quedaría **falso** tras esta feature (capa 4, regla FR-013 de la 030)
- [X] T019 Evaluar si `docs/04-front-guidelines.md` merece una convención nueva sobre zonas de arrastre reutilizables; añadirla en este mismo cambio si la respuesta es sí, y dejar constancia de la decisión si es no (capa 2)

---

## Phase 7: Validación y cierre

- [ ] T020 Recorrer los escenarios E1-E13 de [quickstart.md](quickstart.md) en el navegador, marcando cada uno; prestar atención especial a E6 (no perder la página ni lo escrito) y a E1 (no fallar en silencio)
- [ ] T020b Confirmar SC-001 contando las acciones manuales que hoy exige adjuntar una captura (guardar a disco, abrir el selector, localizar el fichero) frente a las que exige después (pegar), y comprobar SC-002 pidiendo a una persona que no vio la feature que adjunte una captura sin darle instrucciones
- [X] T021 Ejecutar la suite completa con `php artisan test` y confirmar que queda en verde **sin haber modificado ningún test existente** — si hubo que tocar alguno, la feature rompió el camino de la 046
- [X] T022 Confirmar que ningún fichero fuera de los cuatro previstos quedó modificado (`git status`), y que `docs/01-arquitectura.md` y `03-modelo-datos.md` no necesitan cambios (esta feature no toca tablas ni decisiones técnicas — dejar constancia explícita)

---

## Dependencies

```
Phase 1 (Setup)
   └─> Phase 2 (Foundational: retención)   ← BLOQUEANTE de todo lo demás
          ├─> Phase 3 (US1: pegar)          ← MVP
          │      └─> Phase 5 (US3)
          └─> Phase 4 (US2: arrastrar)
                 └─> Phase 5 (US3)
                        └─> Phase 6 (documentación)
                               └─> Phase 7 (validación)
```

- **Phase 2 bloquea a US1 y US2**: ambos gestos entregan ficheros que pueden llegar sin módulo
  resuelto. Sin la retención, los dos fallarían en silencio.
- **US1 y US2 son independientes entre sí** una vez existe la retención: se pueden implementar en
  cualquier orden, pero no en paralelo (mismo fichero).
- **US3 depende de que exista al menos un gesto** para tener qué verificar.

## Parallel opportunities

Muy limitadas por diseño: 15 de las 22 tareas tocan `public/js/asistente-chat.js`.

- **T017 y T018** son paralelizables entre sí (ficheros distintos, sin dependencia de código).
- **T014** (CSS en el partial) puede hacerse en paralelo a T011-T013 si lo hace otra persona, ya
  que es el único fichero Blade de la fase.

## Implementation Strategy

**MVP = Phase 1 + Phase 2 + Phase 3 (US1)**. Con eso, pegar una captura funciona de punta a punta,
que es el caso que motivó la feature y el que hoy no tiene ninguna salida. US2 (arrastrar) es
comodidad sobre un camino que ya existe (el clip), y US3 es aseguramiento.

Entrega incremental sugerida: parar tras Phase 3 y validar E1-E4 con una persona real antes de
seguir. Si la retención de Phase 2 resulta confusa en la práctica, es mejor descubrirlo con un solo
gesto implementado que con dos.
