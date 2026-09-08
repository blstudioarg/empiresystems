---

description: "Task list for 046-importacion-conversacional-asistente"
---

# Tasks: Importación conversacional con el asistente

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/endpoints.md](./contracts/endpoints.md)

**Tests**: SÍ, obligatorios. El Principio IV es NON-NEGOTIABLE para aislamiento multi-tenant, y se
aplica el mismo rigor a la aplicación de correcciones sobre el borrador: corregir la fila equivocada
es un error silencioso que nadie detecta hasta que los datos ya están mal.

**Organization**: por historia de usuario. US1 y US2 son ambas P1 y se entregan juntas: por separado,
US1 aporta poco más que la pantalla de importación que ya existe.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [X] T001 Crear `config/importacion.php` con `material.tipos` y `material.max_paginas` (default 20), según `data-model.md`
- [X] T002 [P] Crear `app/Support/MaterialImportable.php`: tipos admitidos, detección de origen (`hoja` o `documento`) y mensajes de rechazo comprensibles (FR-003)
- [X] T003 [P] Crear `tests/Unit/MaterialImportableTest.php`: cada tipo cae en el origen correcto y los no admitidos dan un mensaje explicativo, nunca una excepción cruda

---

## Phase 2: Foundational (bloqueante)

**⚠️ CRÍTICO**: sin la costura por filas nada de lo demás encaja. Esta fase bloquea el resto.

### Tests primero (Principio IV)

- [X] T004 Crear `tests/Feature/Asistente/MaterialAislamientoTest.php` con ≥2 tenants y 2 personas del mismo tenant: un token ajeno responde 404 y no 403, ni analizar, ni corregir, ni importar, ni borrar (debe FALLAR antes de la implementación)
- [X] T005 [P] Crear `tests/Unit/BorradorImportacionTest.php`: aplicar una corrección cambia **solo** esa fila y ese campo; descartar quita la fila del recuento; corregir un índice inexistente **falla explícitamente**, no se ignora (contrato §4)

### Costura por filas en el importador existente

- [X] T006 Extraer de `app/Services/ImportadorExcel.php` el análisis de filas ya normalizadas a un método propio, y hacer que la ruta de fichero actual se apoye en él — refactor sin cambio de comportamiento (research D2)
- [X] T007 Añadir a `ImportadorExcel` la entrada que analiza filas y la que las importa, revalidando contra base de datos en el momento de importar, y forzando `tenant_id` vía `crear()` (FR-006, FR-019, FR-020)
- [X] T008 Conservar en la ruta por filas la detección de duplicados **dentro del propio material** (claves de `unicos()` de la definición) además de contra la base de datos, y distinguir ambos casos en el motivo (FR-007) — el importador lo hace hoy sobre el fichero y el refactor no puede perderlo
- [X] T009 Verificar que los tests de la feature 031 siguen en verde **sin tocarlos**; si alguno exige cambios, el refactor cambió comportamiento y hay que rehacerlo

### Borrador

- [X] T010 [P] Crear `app/Excel/BorradorImportacion.php` con la estructura de `data-model.md` (filas, descartadas, correcciones, `analisis_origen`) y las operaciones de corregir y descartar
- [X] T011 Extender `app/Services/AlmacenImportaciones.php` para guardar y recuperar el borrador junto al material, acotado a empresa **y** persona, con la misma caducidad que ya aplica (FR-004)
- [X] T012 [P] Cubrir con test que el borrador de una persona no es accesible por otra ni desde otro tenant (completa T004)
- [X] T013 Verificar que `importaciones:purgar` se lleva también el borrador, no solo el fichero original, sin dejar restos en `storage/app/private/importaciones/` (FR-023, FR-024, SC-006)

---

## Phase 3: US1 + US2 — Importar y corregir conversando (P1) 🎯 MVP

**Independent Test**: `quickstart.md`, escenarios 1 y 2.

### Subida de material

- [X] T014 [P] [US1] Crear `tests/Feature/Asistente/ImportacionConversacionalTest.php` con los códigos del contrato: 200, 403 sin permiso, 404 módulo no importable, 422 por tipo/tamaño
- [X] T015 [US1] Crear `app/Http/Controllers/AsistenteMaterialController.php` con la subida y el descarte, según `contracts/endpoints.md` §1 y §2 (FR-001, FR-002, FR-003)
- [X] T016 [US1] Registrar las rutas en `routes/web.php`, con el mismo `can:ver-{modulo}` que la importación existente (FR-021)
- [X] T017 [US1] Rechazar módulos no importables apoyándose en el contrato `DefinicionImportable`, sin lista negra: que sea cierto por construcción, como en la 031 (FR-010)

### Tools del asistente

- [X] T018 [US1] Crear `app/Ia/Tools/AnalizarMaterialImportable.php` (lectura) devolviendo el análisis del contrato §3, con `identificador` y `campo` para que el asistente pueda nombrar los registros (FR-005, FR-008, FR-011)
- [X] T019 [US2] Crear `app/Ia/Tools/CorregirFilasImportables.php` (lectura: muta el borrador, no la base de datos) según el contrato §4, devolviendo el análisis actualizado (FR-012, FR-013, FR-014)
- [X] T020 [US2] Garantizar que la importación usa **siempre el borrador vigente** y nunca un análisis anterior: el token identifica el borrador, y la confirmación lo relee en vez de llevarse consigo una copia de las filas (FR-015)
- [X] T021 [US1] Crear `app/Ia/Tools/ImportarMaterial.php` (escritura, con confirmación) que propone la importación y, al confirmar, revalida, importa las válidas y reporta las rechazadas (FR-016, FR-018, FR-019)
- [X] T022 [US1] Dar de alta las tres tools en `app/Ia/CatalogoTools.php` con el permiso del módulo correspondiente
- [X] T023 [US1] Ampliar `resources/ia/conocimiento/importacion-exportacion.md` para que el asistente sepa conducir el flujo: pedir el material, no inventar datos, nombrar los registros afectados y ofrecer descartarlos

### Conexión con la conversación

- [X] T024 [US1] Registrar la importación en el historial de actividad al confirmar, con módulo y número de registros importados, igual que hace la pantalla de importación existente (FR-022)
- [X] T025 [US1] Rellenar la caja de estado del análisis del modal de detalle (creada vacía en la 045) con origen, leídas, válidas, descartadas y lo no interpretado (FR-017)
- [X] T026 [US2] Descartar el material en curso al cambiar de conversación o iniciar una nueva, igual que se descarta una propuesta pendiente (edge case del spec)
- [X] T027 [P] [US1] Cubrir el invariante de SC-002: tras subir material, analizar y corregir cuantas veces haga falta, el recuento de registros del módulo en base de datos es **idéntico** al de antes. Nada se escribe hasta confirmar
- [X] T028 [P] [US2] Cubrir con test el flujo completo de corrección: fichero con 3 registros sin NIF → corregir uno y descartar dos → el análisis pasa a 8 válidos → confirmar importa 8 (SC-003, SC-004, SC-008)

### Frontend

- [X] T029 [US1] Añadir el clip a `resources/views/partials/asistente-chat.blade.php`, visible solo en el contexto de una importación (research D5), con CSS scoped `.asistente-chat__*`
- [X] T030 [US1] Implementar en `public/js/asistente-chat.js` la subida del material, el aviso de progreso reutilizando `.asistente-chat__estado` y el manejo de los errores del contrato con `window.showToast`

---

## Phase 4: US3 — Material que no es una hoja (P2)

**Independent Test**: `quickstart.md`, escenario 3.

- [X] T031 [P] [US3] Crear `tests/Unit/InterpretadorMaterialTest.php` sustituyendo el punto que habla con el proveedor: un campo ilegible llega **vacío y no relleno** (FR-009 — el riesgo más serio de la feature), y un documento sin registros se reporta como tal
- [X] T032 [US3] Crear `app/Services/InterpretadorMaterialImportable.php`: única pieza que toca al proveedor, salida con JSON Schema estricto, imagen como `image_url` y PDF como parte `file` (research D3, patrón de la 044)
- [X] T033 [US3] Redactar el system prompt con la prohibición explícita de inventar valores y el `null` permitido en todo lo no imprescindible
- [X] T034 [US3] Traducir la lectura cruda a filas con las claves internas de `ColumnaExcel`, marcando en `leido` qué campos venían del documento y cuáles no
- [X] T035 [US3] Aplicar el tope de páginas de `config/importacion.php` y explicarlo en la conversación cuando se supera (research D9)
- [X] T036 [US3] Acumular varios documentos en la misma importación (US3, escenario 4)
- [X] T037 [US3] Sin clave de IA configurada: los ficheros estructurados siguen analizándose y los documentos se rechazan con explicación (edge case del spec)

---

## Phase 5: US4 — Sugerencias destacadas (P3)

**Independent Test**: `quickstart.md`, escenario 7.

- [X] T038 [P] [US4] Crear `tests/Feature/Asistente/SugerenciasPanelTest.php`: se agrupan por categoría y **no se ofrecen** las que la persona no podría ejecutar (FR-027)
- [X] T039 [US4] Crear `app/Ia/SugerenciasAsistente.php` con el catálogo por categoría, filtrado por permisos igual que `CatalogoTools::paraUsuario`
- [X] T040 [US4] Añadir `GET /asistente/sugerencias` según el contrato §6
- [X] T041 [US4] Pintar los chips de categoría y las sugerencias en el estado vacío del panel, con CSS scoped (FR-025)
- [X] T042 [US4] Al pulsar una sugerencia se envía como mensaje, y las sugerencias desaparecen al empezar a conversar (FR-026)

---

## Phase 6: Polish y documentación

**Cierra FR-028**: las cuatro capas de documentación que exige `CLAUDE.md`. No es opcional.

- [X] T043 [P] Actualizar `docs/01-arquitectura.md`: decisión de reutilizar el pipeline de la 031 en vez de abrir un segundo camino de escritura, y por qué
- [X] T044 [P] Actualizar `docs/03-modelo-datos.md`: el asistente puede aportar material de importación; vive en el almacén de importaciones y se purga con él
- [X] T045 [P] Anotar en `docs/04-front-guidelines.md` el clip de material y los chips de sugerencias del estado vacío
- [X] T046 [P] Actualizar `resources/views/ayuda/importar-exportar.blade.php`: la pantalla ya no es la única vía de importar
- [X] T047 [P] Actualizar `resources/ia/conocimiento/asistente.md` con la capacidad, sus módulos y sus límites
- [X] T048 Ejecutar `vendor/bin/pint` **solo sobre los ficheros tocados** y la suite completa en verde
- [ ] T049 Recorrer `quickstart.md` entero, incluidos aislamiento, límites y purga. Para SC-001, hacerlo **sin usar la pantalla de importación** en ningún momento: si hace falta recurrir a ella, la feature no cumple su objetivo

---

## Dependencies

```text
Phase 1 (T001–T003)  Setup
      ↓
Phase 2 (T004–T013)  BLOQUEANTE: sin la costura por filas nada encaja
      ↓
Phase 3 (T014–T029)  US1+US2 — MVP
      ↓
Phase 4 (T030–T036)  US3 — documentos
      ↓
Phase 5 (T037–T041)  US4 — sugerencias (independiente de US3)
      ↓
Phase 6 (T042–T048)  Documentación y cierre
```

- US3 y US4 son independientes entre sí una vez cerrada la fase 3.
- El refactor va en orden: extraer la costura, añadir las entradas por filas, conservar los duplicados, y solo entonces el checkpoint de los tests de la 031, que no se salta.

## Parallel execution

- **Phase 1**: las dos tareas de soporte y su test van juntas.
- **Phase 2**: los dos tests van juntos; el borrador y su test de aislamiento, en paralelo tras el refactor.
- **Phase 3**: los tests de endpoints y del flujo de corrección van juntos; el frontend avanza en paralelo al backend con el contrato fijado.
- **Phase 6**: las tareas de documentación son ficheros distintos y van en paralelo.

## Implementation strategy

**MVP = fases 1, 2 y 3**: importar un Excel hablando con el asistente y corregirlo conversando. Es lo
que resuelve el problema planteado y es desplegable solo.

US3 (documentos) y US4 (sugerencias) son incrementos posteriores. US4 es pequeña pero es cómo se
descubre todo lo demás: no dejarla indefinidamente para el final.

**Recordatorio**: la tarea de Pint dice **solo sobre los ficheros tocados** a propósito. En la feature anterior se
lanzó Pint sobre `app/` y `tests/` enteros y reformateó ~95 ficheros ajenos, que hubo que commitear
aparte para no contaminar el diff.
