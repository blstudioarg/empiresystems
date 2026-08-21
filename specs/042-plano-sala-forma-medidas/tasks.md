---

description: "Task list for feature 042 — Forma y medidas configurables de la zona en el plano de sala"
---

# Tasks: Forma y medidas configurables de la zona en el plano de sala

**Input**: Design documents from `/specs/042-plano-sala-forma-medidas/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/plano-zona.md](./contracts/plano-zona.md),
[quickstart.md](./quickstart.md)

**Tests**: SÍ. La constitución (Principio IV) exige test-first en aislamiento multi-tenant, y el plan
extiende esa exigencia a la validación de invariantes de geometría (G1-G5), que es donde un bug
destruye el plano de un cliente. Los tests de esas áreas se escriben **antes** que su implementación
y **deben fallar primero**. La UI se valida por [quickstart.md](./quickstart.md).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivo distinto, sin dependencias pendientes)
- **[Story]**: a qué historia de usuario pertenece (US1-US4)

## Path Conventions

Aplicación web Laravel monolítica: `app/`, `database/migrations/`, `resources/views/`,
`public/js/plugins-init/`, `tests/Feature/Pos/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dar a `pos_zonas` su lienzo, sin cambiar el comportamiento de nada todavía.

- [X] T001 Crear la migración `database/migrations/2026_08_21_HHMMSS_lienzo_por_zona_en_pos_zonas.php` que añade a `pos_zonas`: `columnas` (`unsignedTinyInteger`, default 8), `filas` (`unsignedTinyInteger`, default 6) y `celdas_inactivas` (`json`, default `[]`), con `down()` que elimina las tres. Docblock explicando que **no hay backfill** porque los defaults reproducen el lienzo fijo actual (D10) y que el `down()` pierde el diseño de sala pero ninguna mesa.
- [X] T002 Añadir `columnas`, `filas` y `celdas_inactivas` a `$fillable` y a `casts()` (`integer`, `integer`, `array`) en `app/Models/PosZona.php`, con nota de que la máscara se lee y escribe entera (D1).
- [X] T003 Ejecutar `php artisan migrate` en local y verificar en una zona existente que quedó 8×6 con `celdas_inactivas = []` y todas sus mesas intactas (FR-015).

**Checkpoint**: el dato existe y ninguna pantalla cambió.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: quitar las constantes globales de geometría —en servidor y en cliente— y hacer que la
geometría de la zona activa circule por el payload. **Ninguna historia puede empezar antes.**

**⚠️ CRÍTICO**: T004 y T005 se escriben antes que T006-T008 y deben fallar primero (Principio IV).

- [X] T004 [P] Escribir `tests/Feature/Pos/AislamientoPlanoLienzoTest.php`: dos tenants con una zona cada uno; el tenant A no puede leer el lienzo de la zona de B en el payload de la Sala ni guardarlo vía `PUT /pos/sala/zonas/{zona}/plano` (404/403, nunca 200). Debe fallar primero.
- [X] T005 [P] Escribir `tests/Feature/Pos/PlanoLienzoZonaTest.php` con los casos de la tabla de [quickstart.md](./quickstart.md) que dependen de esta fase: medidas fuera de 4-24 → 422; recortar la zona entera (G5) → 422; `version` desactualizada → 409 sin cambios; guardado válido con medidas nuevas → 200 y zona actualizada. Debe fallar primero.
- [X] T006 Renombrar en `app/Support/PosPlanoCeldas.php` `COLUMNAS`/`FILAS` a `COLUMNAS_DEFECTO`/`FILAS_DEFECTO`, añadir `MIN = 4` y `MAX = 24`, y cambiar `primeraCeldaLibre()` para recibir la `PosZona` (o su geometría), recorrer **sus** medidas y **saltarse las celdas inactivas** (D3, FR-020). Actualizar todos los usos que el renombrado deje al descubierto.
- [X] T007 Ampliar `app/Support/PosPlanoReacomodo.php::validar()` para recibir la **geometría propuesta** (`columnas`, `filas`, `celdas_inactivas` normalizadas) en vez de leer constantes: G2 pasa a validarse contra las medidas propuestas y se añade **G5** (medidas dentro de rango, al menos una celda de suelo). Añadir también la normalización de `celdas_inactivas` descrita en [data-model.md](./data-model.md) (descartar mal formadas y fuera de rejilla, deduplicar, ordenar). G4 llega en T017.
- [X] T008 Actualizar `app/Http/Controllers/Pos/PlanoSalaController.php` según [contracts/plano-zona.md](./contracts/plano-zona.md): validar `columnas`, `filas`, `celdas_inactivas` y `celdas_inactivas.*`; acotar `mesas.*.ancho_celdas`/`alto_celdas` por las medidas **del payload**; mantener el orden 409 antes de invariantes; persistir zona (medidas + máscara normalizada) y mesas en la **misma transacción** con el mismo bump de `version`.
- [X] T009 Añadir `columnas`, `filas` y `celdas_inactivas` a cada entrada de `zonas` en el payload de `app/Http/Controllers/Pos/SalaController.php`, **sin** condicionarlo al permiso de configuración (FR-012) y sin introducir consultas nuevas (la zona ya se carga).
- [X] T010 Ampliar `tests/Feature/Pos/SalaPayloadPlanoTest.php` para fijar los tres campos nuevos del contrato de lectura (corolario de `docs/04-front-guidelines.md`, feature 041).
- [X] T011 Quitar de `public/js/plugins-init/pos-plano-dibujo.js` las constantes `COLS`/`ROWS` y la escritura de `--plano-cols`/`--plano-rows` en `documentElement`; exponer en su lugar funciones que reciben `{ columnas, filas, inactivas }` de la zona activa y una utilidad que escribe esas variables **en el elemento del lienzo** (D4). Mantener el módulo fuera del guard de permiso del editor.
- [X] T012 Adaptar `public/js/plugins-init/pos-sala-plano.init.js` y `public/js/plugins-init/pos-sala-plano-servicio.init.js` a la geometría por zona: leerla del payload al dibujar cada zona, y sustituir todo uso de `D.COLS`/`D.ROWS` (límites de arrastre, búsqueda de hueco, tamaño del lienzo) por la de la zona activa.

**Checkpoint**: `php artisan test --filter="AislamientoPlanoLienzo|PlanoLienzoZona|SalaPayloadPlano|PlanoRectangulos|PlanoSala"` en verde y el plano funcionando **exactamente** como antes con 8×6 en todas las zonas.

---

## Phase 3: User Story 1 - Ajustar las medidas de la zona (Priority: P1) 🎯 MVP

**Goal**: cada zona tiene sus medidas y el encargado puede cambiarlas desde el editor.

**Independent Test**: cambiar las medidas de una zona, guardar, recargar y comprobar que se
conservan y que otra zona conserva las suyas (escenario 1 de [quickstart.md](./quickstart.md)).

- [X] T013 [US1] Añadir a la barra del editor en `resources/views/pos/sala.blade.php` los controles de medidas (columnas y filas), en formato `sm` como el resto de formularios de la app (`docs/04-front-guidelines.md`, "Tamaño de formularios"), visibles solo con permiso de configuración (FR-018).
- [X] T014 [US1] En `public/js/plugins-init/pos-sala-plano.init.js`, aplicar el cambio de medidas al estado en memoria del plano y redibujar el lienzo en el acto, **sin** petición al servidor: viaja en el guardado explícito por botón que ya existe (FR-013). Cancelar la edición restaura las medidas guardadas.
- [X] T015 [US1] Incluir `columnas` y `filas` en el cuerpo del guardado del plano y refrescar el estado local con la `version` que devuelve el servidor.

**Checkpoint**: US1 entregable por sí sola — una terraza pequeña deja de dibujarse como un salón.

---

## Phase 4: User Story 2 - Recortar la planta (Priority: P1)

**Goal**: la sala deja de ser obligatoriamente un rectángulo.

**Independent Test**: recortar un bloque de esquina en un solo gesto, guardar, recargar y ver la
zona en L; comprobar que una mesa no puede ocupar una celda recortada (escenario 2 de
[quickstart.md](./quickstart.md)).

- [X] T016 [P] [US2] Añadir a `tests/Feature/Pos/PlanoLienzoZonaTest.php` los casos de **G4** y de normalización: mesa sobre celda inactiva → 422 sin cambios; `celdas_inactivas` con claves fuera de la rejilla propuesta → 200 con esas claves descartadas; duplicadas y desordenadas → 200 persistido normalizado. Deben fallar primero.
- [X] T017 [US2] Implementar **G4** en `app/Support/PosPlanoReacomodo.php`: ninguna celda de una mesa puede pertenecer a `celdas_inactivas`, con el mensaje de [data-model.md](./data-model.md). Es también la barrera de servidor de FR-008: un recorte que dejara una mesa encima nunca llega a persistirse.
- [X] T018 [US2] Añadir a `resources/views/pos/sala.blade.php` el CSS de la capa de celdas: `.plano-celda` (blanco de toque, alineado al `STEP` del lienzo) y `.plano-celda-inactiva` (vacío, inequívocamente distinto de suelo libre, FR-005), con `touch-action: none` en la capa (D5/D6).
- [X] T019 [US2] En `pos-plano-dibujo.js`, generar el HTML de la capa de celdas a partir de la geometría de la zona: todas las celdas cuando se pide para edición, solo las inactivas cuando se pide para servicio (D5). Función pura, sin tocar el DOM, como el resto del módulo.
- [X] T020 [US2] En `pos-sala-plano.init.js`, añadir el **modo de recorte** (toggle en la barra del editor): al activarlo monta la capa de celdas completa y desactiva `draggable`/`resizable` de las mesas; al desactivarlo la desmonta y los reactiva (D6, FR-019).
- [X] T021 [US2] Implementar el pintado con Pointer Events (`pointerdown` + `pointerenter`, `setPointerCapture`) para marcar y desmarcar celdas en un mismo gesto de arrastre (FR-004), mutando la máscara en el estado compartido del plano (no reasignándola, `docs/04-front-guidelines.md` feature 038).
- [X] T022 [US2] Impedir en cliente que una mesa se mueva o crezca sobre una celda inactiva, reutilizando la ruta de colisión que ya existe para celdas ocupadas (FR-006).
- [X] T023 [US2] Incluir `celdas_inactivas` en el cuerpo del guardado del plano y restaurar la máscara guardada al cancelar la edición.

**Checkpoint**: US2 entregable — salas en L, patios y pilares representables y persistidos.

---

## Phase 5: User Story 3 - Nunca perder una mesa por cambiar el lienzo (Priority: P1)

**Goal**: ningún ajuste del lienzo mueve, encoge o borra una mesa en silencio.

**Independent Test**: intentar reducir columnas con una mesa en la última, e intentar recortar una
celda ocupada (escenario 3 de [quickstart.md](./quickstart.md)).

- [X] T024 [P] [US3] Añadir a `tests/Feature/Pos/PlanoLienzoZonaTest.php` los casos de reducción: reducir dejando una mesa fuera → 422 nombrando la mesa y sin cambios; reducir **y** mover esa mesa en la misma petición → 200 (se valida la geometría propuesta, no la guardada, D8). Deben fallar primero.
- [X] T025 [US3] En `pos-sala-plano.init.js`, bloquear en cliente la reducción de medidas cuando alguna mesa quedaría fuera, mostrando el **conteo** de mesas que lo impiden (FR-009, D9), sin mover ninguna.
- [X] T026 [US3] Bloquear el recorte de una celda ocupada: la celda no cambia y la mesa que lo impide recibe `.plano-mesa-bloqueada` (sombra + micro-desplazamiento ya existente, respetando `prefers-reduced-motion`), **nunca** un cambio de color de borde (FR-010, D7).
- [X] T027 [US3] Emitir **un solo** toast al soltar el gesto de recorte, con el conteo agregado de celdas rechazadas, vía `window.showToast` (nunca markup de alerta ad-hoc, `CLAUDE.md`); no uno por celda recorrida (FR-010).
- [X] T028 [US3] Impedir que el recorte deje la zona sin ninguna celda de suelo también en cliente (FR-011), con el mismo canal de feedback.

**Checkpoint**: las tres P1 completas — la feature es usable de punta a punta en el editor.

---

## Phase 6: User Story 4 - El camarero ve la misma sala (Priority: P2)

**Goal**: la vista de servicio dibuja medidas y forma reales, sin permiso de configuración.

**Independent Test**: abrir la Sala en vista de plano con un usuario sin permiso de configuración y
comparar el contorno con el del editor (escenario 6 de [quickstart.md](./quickstart.md)).

- [X] T029 [US4] En `public/js/plugins-init/pos-sala-plano-servicio.init.js`, dimensionar el lienzo con la geometría de la zona activa y montar la capa de celdas **solo con las inactivas** (D5), sin controles de edición.
- [X] T030 [US4] Verificar que ningún control de medidas ni de recorte se renderiza sin el permiso de configuración (FR-018), y que el módulo de dibujo se sigue cargando fuera del guard del editor (regresión de la feature 041).
- [ ] T031 [US4] Comprobar el desplazamiento del lienzo en una zona más ancha que la pantalla de tablet: todas las mesas alcanzables, sin scroll simultáneo en dos ejes.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: cerrar la feature. **Las cuatro capas de documentación son obligatorias**
(`CLAUDE.md`, "Documentación al día en TODO cambio").

- [X] T032 [P] Actualizar `docs/03-modelo-datos.md`: las tres columnas nuevas de `pos_zonas` y el paso de rejilla fija a lienzo por zona.
- [X] T033 [P] Añadir a `docs/04-front-guidelines.md` la convención nueva: **pintar sobre un lienzo que ya tiene arrastre exige un modo de gesto explícito** (D6), con el ejemplo vivo del modo de recorte, y la nota de que la geometría del lienzo se escribe en el propio lienzo y no en `documentElement` (D4).
- [X] T034 [P] Actualizar `resources/views/ayuda/pos-sala.blade.php`: cómo ajustar las medidas de una zona y cómo recortar su planta, y qué hacer cuando el sistema avisa de que hay mesas que lo impiden (FR-016).
- [X] T035 [P] Actualizar `resources/ia/conocimiento/pos-hosteleria.md` con el lienzo por zona: medidas propias, celdas fuera de la sala y la regla de que nada se mueve solo (FR-017).
- [ ] T036 Ejecutar la suite completa del módulo POS (`php artisan test --filter=Pos`) y recorrer los ocho escenarios manuales de [quickstart.md](./quickstart.md), incluido el de rendimiento a 24×24 y el táctil.
- [X] T037 Revisar que no queda ningún uso de la rejilla fija: `grep -rn "COLUMNAS_DEFECTO\|FILAS_DEFECTO\|plano-cols" app public resources` y confirmar que cada aparición es un default o el lienzo de una zona concreta, nunca una suposición de 8×6.

---

## Dependencies

```text
Phase 1 (T001-T003)
      ↓
Phase 2 (T004-T012)  ← bloquea todo
      ↓
   ┌──┴───────────────┬──────────────────┐
 US1 (T013-T015)   US2 (T016-T023)   US4 (T029-T031)*
                        ↓
                  US3 (T024-T028)
      ↓
Phase 7 (T032-T037)
```

- **US1** solo depende de la fase 2.
- **US2** solo depende de la fase 2.
- **US3** depende de US1 (reducción de medidas) y de US2 (recorte de celdas): es la red de seguridad
  de ambas, no puede probarse antes de que existan.
- **US4*** depende de la fase 2 para dimensionar el lienzo, y de US2 para dibujar celdas inactivas
  que alguien haya podido recortar; puede empezarse en paralelo y cerrarse después de US2.

## Parallel Opportunities

- **T004 y T005**: dos archivos de test distintos, sin dependencia entre ellos.
- **T016 y T024**: aunque tocan el mismo archivo de test, son casos independientes; si se paraleliza,
  hacerlo en ramas de trabajo distintas para evitar conflicto en el mismo archivo.
- **T032-T035**: las cuatro capas de documentación son archivos distintos y no dependen entre sí.
- **US1 y US2** pueden desarrollarse en paralelo tras el checkpoint de la fase 2, pero tocan ambas
  `pos-sala-plano.init.js` y `sala.blade.php`: coordinar o secuenciar si trabaja más de una persona.

## Implementation Strategy

**MVP**: fase 1 + fase 2 + **US1**. Con eso cada zona ya tiene el tamaño de su sala real, que es la
mitad del valor y no rompe nada de lo anterior.

**Incremento 2**: **US2** — la forma, que es la petición central.

**Incremento 3**: **US3** — obligatorio antes de dar la feature por terminada. US1 y US2 sin US3
permitirían perder mesas al ajustar el lienzo, que es exactamente lo que la spec prohíbe (SC-004).

**Incremento 4**: **US4** + documentación.
