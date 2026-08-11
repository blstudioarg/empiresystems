---

description: "Task list for feature implementation"
---

# Tasks: Plano de sala arrastrable (POS)

**Input**: Design documents from `/specs/039-pos-plano-mesas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/plano-sala.md, quickstart.md

**Tests**: Incluidos para el endpoint de guardado del plano (aislamiento de tenant, bloqueo
optimista, validación de colisión/rango) porque toca directamente el Principio I (Aislamiento
Multi-Tenant, NON-NEGOTIABLE) del proyecto. El reacomodo visual en cliente se valida manualmente
vía `quickstart.md` (no es lógica crítica según Principio IV).

**Organization**: Tareas agrupadas por historia de usuario (spec.md) para permitir implementación
y prueba independiente de cada una.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: A qué historia de usuario pertenece (US1, US2, US3, US4)

## Path Conventions

Proyecto único (Laravel monolito), rutas relativas a la raíz del repo, según `plan.md`.

---

## Phase 1: Setup

**Purpose**: Preparar la migración y el modelo de datos ampliado, base para todas las historias.

- [X] T001 Crear migración `database/migrations/2026_08_11_120000_add_plano_a_pos_mesas_y_pos_zonas.php`
      que añade a `pos_mesas`: `fila` (unsignedTinyInteger, nullable), `columna`
      (unsignedTinyInteger, nullable), `forma` (enum: redonda/cuadrada/rectangular/barra, default
      `cuadrada`), `tamano` (enum: pequena/mediana/grande, default `mediana`); y a `pos_zonas`:
      `version` (unsignedInteger, default 1). Incluir el índice único
      `(tenant_id, zona_id, fila, columna)` sobre `pos_mesas` (ver data-model.md, nota sobre
      `softDeletes`).
- [X] T002 En la misma migración (`up()`), añadir el paso de backfill (D7, research.md): recorrer
      las mesas existentes agrupadas por `zona_id` (orden `orden`, `nombre`) y asignarles la
      primera celda libre disponible de la rejilla 8×6 (columnas 0-7, filas 0-5) de su zona.

**Checkpoint**: `php artisan migrate` corre limpio; toda mesa existente en el tenant de demo queda
con `fila`/`columna`/`forma`/`tamano` válidos.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelos, ruta base y validación server-side compartida por todas las historias.

**⚠️ CRITICAL**: Ninguna historia de usuario puede completarse sin esta fase.

- [X] T003 [P] Ampliar `app/Models/PosMesa.php`: añadir `fila`, `columna`, `forma`, `tamano` a
      `$fillable` y a `casts()` (`fila`/`columna` como `integer`, `forma`/`tamano` como `string`).
- [X] T004 [P] Ampliar `app/Models/PosZona.php`: añadir `version` a `$fillable` y `casts()` como
      `integer`.
- [X] T005 Crear `app/Support/PosPlanoReacomodo.php`: clase de soporte con la validación
      server-side del payload de guardado (data-model.md, "Validación de payload de guardado"):
      verifica que el conjunto de `mesa_id` pertenezca a la zona/tenant, que `fila`/`columna`
      estén en rango (0-5 / 0-7) y sin duplicados dentro del payload; lanza excepción de
      validación descriptiva si falla. Reutilizable por el controller (T009) y cubierta por los
      tests de las fases siguientes (T007, T008, T014, T015).
- [X] T006 Registrar la ruta `Route::match(['put','patch'], '/pos/sala/zonas/{zona}/plano',
      [PlanoSalaController::class, 'update'])->name('pos.sala.plano.update')` en `routes/web.php`,
      dentro del grupo `can:ver-configuracion` + `modulo.hosteleria` (mismo criterio de acceso que
      `configuracion.pos.*`, D6 de research.md).
- [X] T006b Crear `app/Support/PosPlanoCeldas.php`: helper compartido que calcula la primera
      celda libre (`fila`/`columna`) de la rejilla 8×6 de una zona, dado el conjunto de mesas ya
      posicionadas (mismo criterio de recorrido usado por el backfill de T002). Lo usa T006c.
- [X] T006c Ampliar `app/Http/Controllers/Configuracion/PosMesaController.php` (método `store`):
      al crear una mesa, asignarle automáticamente `fila`/`columna` con la primera celda libre de
      su zona vía `PosPlanoCeldas` (T006b), y `forma`/`tamano` con los defaults (`cuadrada`/
      `mediana`) si no se envían — cubre FR-014 del spec ("una mesa nueva sin posición previa DEBE
      aparecer en una posición libre por defecto, nunca superpuesta a una mesa existente").

**Checkpoint**: Modelos y ruta listos; ninguna historia de usuario depende de trabajo adicional
compartido más allá de este punto.

---

## Phase 3: User Story 1 - Distribuir las mesas de una zona (Priority: P1) 🎯 MVP

**Goal**: Poder arrastrar mesas a nuevas posiciones dentro del lienzo de una zona y persistir esa
disposición con un botón "Guardar plano".

**Independent Test**: Entrar a Sala, activar "Editar plano", arrastrar una mesa, pulsar "Guardar
plano", recargar y comprobar que la nueva posición persiste (quickstart.md, Escenario 1).

### Tests for User Story 1

- [X] T007 [P] [US1] Feature test `tests/Feature/Pos/PlanoSalaTest.php`: caso "guarda posiciones
      válidas de una zona y aumenta `version`" — crea zona con 3 mesas, hace `PUT
      /pos/sala/zonas/{zona}/plano` con nuevas `fila`/`columna` para cada una y el `version`
      vigente, asevera 200, nuevas posiciones persistidas y `version` incrementado en 1.
- [X] T008 [P] [US1] Feature test en el mismo archivo: caso de aislamiento de tenant (Principio I)
      — crea 2 tenants con zonas propias, intenta guardar el plano de la zona del tenant B usando
      la sesión del tenant A (o un `zona_id` ajeno), asevera 404 (zona no resuelta bajo el tenant
      activo), sin fuga de datos entre tenants.
- [X] T008b [P] [US1] Feature test (`tests/Feature/Configuracion/PosMesaControllerTest.php` o el
      archivo de test existente del CRUD de mesas): al eliminar una mesa y crear una mesa nueva en
      la misma zona, la nueva mesa puede ocupar la celda `fila`/`columna` que ocupaba la eliminada
      — cubre FR-015 (la celda de una mesa borrada queda libre para futuros reacomodos).

### Implementation for User Story 1

- [X] T009 [US1] Crear `app/Http/Controllers/Pos/PlanoSalaController.php` con el método `update`:
      resuelve la zona manualmente bajo el tenant activo (`PosZona::query()->findOrFail($zona)`,
      sin binding implícito), valida el request (`version` entero, `mesas` array de
      `{id, fila, columna, forma, tamano}`), usa `PosPlanoReacomodo` (T005) para la validación de
      consistencia, compara `version` (409 si difiere, contrato en `contracts/plano-sala.md`), y
      persiste en una transacción incrementando `zona->version`. Responde `{ message, version }`.
- [X] T010 [US1] Ampliar `SalaController::estado()` en `app/Http/Controllers/Pos/SalaController.php`
      para incluir `fila`, `columna`, `forma`, `tamano` en cada mesa del payload y `version` en
      cada zona (contrato ampliado en `contracts/plano-sala.md`).
- [X] T011 [US1] Ampliar `resources/views/pos/sala.blade.php`: añadir botón "Editar plano" /
      "Guardar plano" en el header de cada zona, y el marcado del lienzo (contenedor por zona con
      celdas posicionadas por `fila`/`columna` en vez del grid automático actual
      `.pos-mesas-grid`). Mantener el modo solo-lectura actual (colores de estado libre/ocupada/
      olvidada) como vista por defecto.
- [X] T012 [US1] Crear `public/js/plugins-init/pos-sala-plano.init.js`: inicializa jQuery UI
      `draggable`/`droppable` sobre las mesas del lienzo cuando el modo edición está activo (asa
      de arrastre obligatoria, sin arrastre por toda la superficie — patrón de
      `docs/04-front-guidelines.md`); mantiene el estado de posiciones pendientes en memoria (no
      persiste al soltar); al pulsar "Guardar plano", envía `PUT /pos/sala/zonas/{zona}/plano`
      con el `version` vigente y actualiza el estado local con la respuesta.
- [X] T013 [US1] Cargar `pos-sala-plano.init.js` y el CSS del lienzo/celdas desde
      `resources/views/pos/sala.blade.php` vía `@push('scripts')`/`@push('styles')` (jQuery UI ya
      vendorizado, sin nueva dependencia).

**Checkpoint**: User Story 1 funcional y probable de forma independiente — arrastrar, guardar,
recargar conserva la disposición.

---

## Phase 4: User Story 3 - No perder mesas por solapamiento accidental (Priority: P1)

**Goal**: Al soltar una mesa sobre una celda ocupada, la mesa que ya estaba ahí se reubica
automáticamente en la celda libre más cercana (distancia euclídea); si no hay celda libre, se
cancela el movimiento con aviso.

**Independent Test**: En modo edición, soltar una mesa exactamente sobre otra y comprobar que
ambas quedan visibles sin solaparse (quickstart.md, Escenario 3).

### Tests for User Story 3

- [X] T014 [P] [US3] Feature test en `tests/Feature/Pos/PlanoSalaTest.php`: caso "rechaza payload
      con dos mesas en la misma celda" — `PUT /pos/sala/zonas/{zona}/plano` con dos mesas
      apuntando a la misma `fila`/`columna`, asevera 422 y que ninguna mesa quedó modificada
      (atomicidad, data-model.md).
- [X] T015 [P] [US3] Feature test: caso "rechaza `fila`/`columna` fuera de rango (0-5 / 0-7)",
      asevera 422.

### Implementation for User Story 3

- [X] T016 [US3] En `public/js/plugins-init/pos-sala-plano.init.js`, implementar el cálculo de
      reacomodo en cliente (D3, research.md): al soltar una mesa sobre una celda ocupada, calcular
      la celda libre de la misma zona con menor distancia euclídea respecto a la celda original de
      la mesa desplazada, y moverla ahí en el estado pendiente; si no existe ninguna celda libre,
      cancelar el drop (la mesa arrastrada vuelve a su posición anterior) y mostrar un toast de
      aviso (`window.showToast('warning', ...)`, patrón ya establecido del proyecto).
- [X] T017 [US3] Completar en `app/Support/PosPlanoReacomodo.php` la validación de colisión/rango
      del lado servidor (defensa en profundidad, contrato en `contracts/plano-sala.md`): usada
      por T009 y cubierta por T014/T015.

**Checkpoint**: Users Stories 1 y 3 combinadas garantizan que el plano nunca queda en un estado
con mesas solapadas, ni en cliente ni en servidor.

---

## Phase 5: User Story 2 - Forma y tamaño configurables (Priority: P2)

**Goal**: Poder elegir la forma (redonda/cuadrada/rectangular/barra) y el tamaño
(pequeña/mediana/grande) de cada mesa desde el propio modo edición del plano.

**Independent Test**: En modo edición, cambiar forma/tamaño de una mesa, guardar, recargar y
comprobar que persiste (quickstart.md, Escenario 2).

### Implementation for User Story 2

- [X] T018 [P] [US2] En `resources/views/pos/sala.blade.php` (o un partial dedicado, p. ej.
      `resources/views/pos/_plano_mesa_editor.blade.php`), añadir un selector de forma y tamaño
      accesible desde cada mesa en modo edición (p. ej. al tocarla se abre un pequeño popover/menú
      con las 4 formas y los 3 tamaños).
- [X] T019 [US2] En `public/js/plugins-init/pos-sala-plano.init.js`, manejar el cambio de
      forma/tamaño como parte del mismo estado pendiente que las posiciones (T012): se refleja de
      inmediato en el lienzo y se incluye en el payload de "Guardar plano".
- [X] T020 [P] [US2] CSS de las 4 formas de mesa con marcas de sillas alrededor del borde
      (decisión de diseño acordada) en el bloque `@push('styles')` de `sala.blade.php`, reutilizando
      los colores de estado (libre/ocupada/olvidada) ya definidos en la hoja de estilos actual de
      `pos.sala`.

**Checkpoint**: Las 3 historias P1/P2 centrales completas — plano posicionable, sin
solapamiento, con forma/tamaño fieles al mobiliario real.

---

## Phase 6: User Story 4 - Cada zona con su propio lienzo (Priority: P2)

**Goal**: Confirmar y blindar que el modo edición y el guardado de una zona son independientes de
las demás zonas (ya implícito en el modelo de datos por `zona_id`, pero se verifica
explícitamente).

**Independent Test**: Editar y guardar el plano de una zona, cambiar de pestaña sin guardar, y
comprobar que la otra zona no se vio afectada (quickstart.md, Escenario 4).

### Tests for User Story 4

- [X] T021 [P] [US4] Feature test en `tests/Feature/Pos/PlanoSalaTest.php`: guardar el plano de la
      zona A y verificar que las mesas de la zona B (mismo tenant) no cambiaron ni su `version` se
      alteró.

### Implementation for User Story 4

- [X] T022 [US4] En `public/js/plugins-init/pos-sala-plano.init.js`, asegurar que el estado
      pendiente de edición (posiciones/forma/tamaño sin guardar) se resetea al cambiar de pestaña
      de zona (FR-009: cambios no guardados se descartan al salir del modo edición de esa zona).

**Checkpoint**: Todas las historias de usuario completas e independientemente verificables.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Conflicto de concurrencia, documentación y limpieza final.

- [X] T023 [P] Feature test en `tests/Feature/Pos/PlanoSalaTest.php`: caso "409 en guardado con
      `version` desactualizado" (quickstart.md, Escenario 5) — dos guardados consecutivos con el
      mismo `version` inicial, el segundo debe fallar con 409 y no modificar lo persistido por el
      primero.
- [X] T024 [P] En `public/js/plugins-init/pos-sala-plano.init.js`, manejar la respuesta 409 del
      guardado mostrando un aviso claro ("El plano se modificó desde otro dispositivo...") en vez
      de un error genérico, y ofrecer recargar el plano vigente.
- [X] T025 Actualizar `docs/03-modelo-datos.md`, sección "POS con mesas y opciones — módulo de
      hostelería (feature 038)", para documentar las columnas nuevas de `pos_mesas`
      (`fila`/`columna`/`forma`/`tamano`) y `pos_zonas.version` (regla del proyecto: "Documentación
      al día en TODO cambio").
- [X] T026 Revisar si `resources/views/ayuda/` tiene una guía in-app para la pantalla de Sala; si
      existe, actualizarla para explicar el nuevo modo "Editar plano"; si no existe, evaluar si
      amerita crearla (regla del proyecto, capa 3 de documentación).
- [X] T027 Añadir/actualizar el archivo correspondiente en `resources/ia/conocimiento/*.md` sobre
      el módulo POS/hostelería para que el asistente IA conozca el nuevo modo de plano arrastrable
      (regla del proyecto, capa 4 de documentación, FR-013 de feature 030).
- [X] T028 Ejecutar manualmente los 5 escenarios de `quickstart.md` de punta a punta en un
      navegador/tablet antes de dar la feature por cerrada.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sin dependencias — puede iniciar de inmediato.
- **Foundational (Phase 2)**: Depende de Setup. Bloquea todas las historias de usuario.
- **User Story 1 (Phase 3)**: Depende de Foundational. Es el MVP.
- **User Story 3 (Phase 4)**: Depende de Foundational; en la práctica se apoya en el mismo
  endpoint/init.js de US1 (T009, T012), así que conviene implementarla justo después de US1 aunque
  ambas sean P1.
- **User Story 2 (Phase 5)**: Depende de Foundational; reutiliza el mismo init.js (T012) pero es
  aditiva — puede implementarse en paralelo a US3 si hay más de un desarrollador.
- **User Story 4 (Phase 6)**: Depende de Foundational; mayormente verificación de un
  comportamiento ya implícito en T009/T012.
- **Polish (Phase 7)**: Depende de que las historias que se quieran entregar estén completas.

### Parallel Opportunities

- T003 y T004 (modelos) en paralelo.
- T007 y T008 (tests US1) en paralelo entre sí.
- T014 y T015 (tests US3) en paralelo entre sí.
- Una vez completado Foundational + US1, un segundo desarrollador puede tomar US2 (Phase 5)
  mientras el primero completa US3 (Phase 4); ambas dependen del mismo `pos-sala-plano.init.js`
  (T012), así que requieren coordinación de merge si se trabajan en paralelo sobre el mismo
  archivo.

---

## Implementation Strategy

### MVP First (User Story 1 + User Story 3)

Dado que ambas son P1 y US3 protege la integridad de la interacción central de US1 (sin
solapamiento), el MVP real de esta feature es **Setup + Foundational + US1 + US3** (Phases 1-4):
reordenar el plano de forma segura, sin solapamientos, con guardado explícito. US2 (forma/tamaño)
y US4 (verificación de independencia entre zonas) son mejoras incrementales sobre ese MVP.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 + US3 → MVP funcional y seguro (arrastrar, reacomodar sin solapar, guardar).
3. US2 → mesas con forma/tamaño reales.
4. US4 → verificación explícita de independencia entre zonas.
5. Polish → concurrencia (409), documentación de las 4 capas exigidas por el proyecto.

## Notes

- [P] = archivos distintos, sin dependencias entre sí.
- Los tests de esta feature se limitan a lo que toca aislamiento de tenant y a la validación
  crítica del endpoint de guardado (colisión, rango, concurrencia); el reacomodo visual en cliente
  se valida manualmente vía `quickstart.md`, coherente con que no es lógica de la lista taxativa
  del Principio IV.
- No olvidar el cierre de spec/feature: las 4 capas de documentación (T025-T027 + `docs/00-
  vision.md`/`01-arquitectura.md`/`02-facturacion-espana.md` si aplicara, que en este caso no
  cambian alcance/normativa) antes de considerar la feature cerrada.
