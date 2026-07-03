# Tasks: Facturas rectificativas (corregir una factura emitida)

**Input**: Design documents from `/specs/009-facturas-rectificativas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/crear-rectificativa.md,
contracts/emitir-rectificativa.md

**Tests**: Incluidos y OBLIGATORIOS (Principio IV: numeración de la serie rectificativa, aislamiento
multi-tenant, inmutabilidad/unicidad de rectificación y cálculo del delta se cubren con tests
escritos primero, deben fallar antes de implementar).

**Organization**: Tareas agrupadas por user story (US1 crear rectificativa, US2 emitir con serie
separada + marcar original, US3 visibilidad/inmutabilidad) para implementar y validar cada una de
forma independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: US1 / US2 / US3
- Rutas de archivo exactas en cada descripción

## Path Conventions

Monolito Laravel existente: `app/`, `database/`, `resources/views/`, `routes/`, `tests/` en la raíz
del repo (ver plan.md § Project Structure). Se reutilizan `NumeradorFacturas`, `CalculadoraFactura`,
`EmisorFacturas` y `factura_eventos` de las features 005/008.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Añadir el esquema y las piezas de dominio (columnas, enum, serie) que necesitan todas
las historias.

- [X] T001 Crear migración aditiva
      `database/migrations/2026_07_03_190000_add_rectificativa_columns_to_facturas_table.php` que
      añade a `facturas`: `es_rectificativa` boolean default false; `factura_rectificada_id`
      unsignedBigInteger nullable con fk → `facturas.id`; `motivo_rectificacion` text nullable;
      `tipo_rectificacion` string nullable; e índice `(tenant_id, factura_rectificada_id)`
      (data-model.md § 1). No recrear la tabla (datos existentes intactos).
- [X] T002 [P] Crear enum `app/Enums/TipoRectificacion.php` (string backed): `Sustitucion =
      'sustitucion'`, `Diferencias = 'diferencias'` (data-model.md § 2).
- [X] T003 [P] Añadir a `app/Models/Factura.php`: `es_rectificativa`, `factura_rectificada_id`,
      `motivo_rectificacion`, `tipo_rectificacion` a `$fillable`; casts (`es_rectificativa` =>
      boolean, `tipo_rectificacion` => TipoRectificacion::class); relaciones
      `facturaRectificada(): BelongsTo` (self, `factura_rectificada_id`) y `rectificativa(): HasOne`
      (self, inversa) (data-model.md § 4).
- [X] T004 [P] Añadir a `database/seeders/SerieSeeder.php` una serie rectificativa por defecto del
      tenant demo: `codigo 'R'`, `tipo 'rectificativa'`, `formato '{serie}-{anio}-{numero:0000}'`,
      `proximo_numero 1`, `activa true` (data-model.md § 3), vía `firstOrCreate` como la ordinaria.
- [X] T005 [P] Añadir a `database/factories/SerieFactory.php` un state `rectificativa()` (`codigo
      'R'`, `tipo TipoFactura::Rectificativa`) para los tests.

**Checkpoint**: `php artisan migrate` añade las columnas; enum, modelo, seeder y factory listos.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Ajustar la selección de serie por tipo y extender el emisor, prerrequisitos de crear y
emitir rectificativas. Bloquea todas las historias.

⚠️ **CRÍTICO**: sin esto, `store()` tomaría la serie equivocada y `emitir()` rechazaría deltas ≤ 0.

- [X] T006 Añadir a `app/Models/Serie.php` un scope/helper `porTipo(TipoFactura $tipo)` (o método
      estático `activaPorTipo`) que devuelva la serie activa del tenant para ese tipo; usarlo en
      `FacturaController::store()` reemplazando `Serie::where('activa', true)->firstOrFail()` por la
      serie **ordinaria** explícita (research.md § 2, gotcha de selección de serie).
- [X] T007 Extender `app/Services/EmisorFacturas.php::validar()`: si `$factura->es_rectificativa`,
      **omitir** la comprobación `base_total > 0` (el delta por diferencias puede ser 0/negativo);
      mantener las validaciones de estado borrador y datos fiscales mínimos del receptor
      (research.md § 3; contracts/emitir-rectificativa.md § Precondiciones).
- [X] T008 Extender `app/Services/EmisorFacturas.php::emitir()`: dentro de la MISMA
      `DB::transaction`, si `$factura->es_rectificativa`, marcar la original
      (`$factura->facturaRectificada`) como `EstadoFactura::Rectificada` y `save()`, y registrar un
      `FacturaEvento` `tipo_evento = 'rectificada'` sobre la original (append-only). La numeración
      sigue usando `$factura->serie` (ya apunta a la serie rectificativa) sin cambios en
      `NumeradorFacturas` (research.md § 2, § 3; contracts/emitir-rectificativa.md § Comportamiento).

**Checkpoint**: emisión y selección de serie preparadas para el tipo rectificativa; `NumeradorFacturas`
intacto.

---

## Phase 3: User Story 1 - Crear una rectificativa desde una emitida (Priority: P1)

**Goal**: A partir de una factura ordinaria emitida, generar una rectificativa en borrador vinculada,
con snapshot del receptor, régimen, motivo y modalidad.

**Independent Test**: rectificar una emitida crea un borrador `tipo = rectificativa` con
`factura_rectificada_id`, motivo, modalidad y snapshot copiados; rechazos sobre borrador y sobre ya
rectificada.

- [X] T009 [P] [US1] Test `tests/Feature/RectificativaCreacionTest.php`: rectificar una factura
      emitida crea un borrador con `tipo = rectificativa`, `es_rectificativa = true`,
      `factura_rectificada_id` correcto, `motivo`/`tipo_rectificacion` guardados, snapshot del
      receptor + `regimen_impositivo` + `aplica_recargo` copiados, `serie_id` = serie rectificativa,
      líneas de la original copiadas, `estado = borrador`, sin número (spec US1 AC1/AC4).
- [X] T010 [P] [US1] En el mismo archivo, tests de rechazo: rectificar una factura en `borrador`
      falla (422/redirect, "solo emitidas"); rectificar una ya `rectificada` falla ("ya fue
      rectificada"); no se crea documento en ninguno (spec US1 AC2/AC3; contracts/crear-rectificativa.md).
- [X] T011 [US1] Crear excepción `app/Exceptions/FacturaNoRectificableException.php` (extiende
      `\DomainException`) para señalar rechazos de rectificabilidad sin `abort()` directo.
- [X] T012 [US1] Crear servicio `app/Services/GeneradorRectificativa.php` con
      `generar(Factura $original, TipoRectificacion $modalidad, string $motivo): Factura` que valida
      (original `emitida`, no `rectificada`; lanza `FacturaNoRectificableException`), y dentro de
      `DB::transaction` crea la rectificativa borrador copiando snapshot del receptor, `regimen_impositivo`,
      `aplica_recargo`, `serie_id` (serie rectificativa vía `Serie::porTipo`), líneas de la original,
      y setea `es_rectificativa/factura_rectificada_id/motivo_rectificacion/tipo_rectificacion`
      (research.md § 4; data-model.md § 5/§ 6).
- [X] T013 [US1] Crear `app/Http/Requests/StoreRectificativaRequest.php`: reglas
      `tipo_rectificacion` `required|in:sustitucion,diferencias`, `motivo_rectificacion`
      `required|string|max:1000` (contracts/crear-rectificativa.md § Request).
- [X] T014 [US1] Añadir `rectificar(StoreRectificativaRequest $request, string $factura)` a
      `app/Http/Controllers/FacturaController.php`: resolver la original manualmente bajo el tenant
      activo (no binding implícito — memoria `project_tenant_route_binding`), delegar en
      `GeneradorRectificativa`, capturar `FacturaNoRectificableException` (422 JSON / redirect back +
      flash `error`), y en éxito redirigir a `facturas.edit` de la rectificativa con flash `success`
      (201 JSON con `id`) (contracts/crear-rectificativa.md § Respuestas/Errores).
- [X] T015 [US1] Registrar ruta `POST /facturas/{factura}/rectificar` →
      `FacturaController@rectificar` con nombre `facturas.rectificar` en `routes/web.php` (dentro del
      grupo autenticado de `facturas.*`).

**Checkpoint**: se puede crear una rectificativa borrador desde una emitida; tests US1 en verde.

---

## Phase 4: User Story 2 - Emitir la rectificativa con numeración en serie separada (Priority: P1)

**Goal**: emitir la rectificativa asigna correlativo de la serie "R" (reinicio anual, sin huecos),
congela, marca la original como `rectificada`, todo atómico; calcula el delta por diferencias.

**Independent Test**: emitir una rectificativa borrador → número de serie "R", `emitida`, original
`rectificada`; contadores ordinaria/rectificativa independientes; delta por diferencias correcto.

- [X] T016 [P] [US2] Test `tests/Feature/RectificativaEmisionTest.php`: emitir una rectificativa
      borrador le asigna número de la **serie rectificativa** (`R-<año>-0001`), `estado = emitida`,
      evento `emitida`; la original pasa a `rectificada`; dos rectificativas seguidas → correlativos
      sin huecos; reinicio anual (spec US2 AC1/AC3/AC4/AC5/AC6).
- [X] T017 [P] [US2] En `RectificativaEmisionTest.php`, test de independencia de series: emitir
      ordinarias y rectificativas no cruza contadores (emitir "R" no altera `proximo_numero` de la
      ordinaria ni viceversa) (spec US2 AC2; SC-001).
- [X] T018 [P] [US2] Test `tests/Feature/RectificativaDeltaTest.php`: modalidad **sustitución** →
      totales = importes corregidos; modalidad **diferencias** → totales de cabecera = delta
      (corregido − original) con negativos admitidos y **delta cero** (solo cambia dato del receptor)
      emitible (spec US1 AC4, US3 AC4; SC-004; research.md § 1).
- [X] T019 [US2] Implementar el cálculo del delta por diferencias en el flujo de guardado de la
      rectificativa (en `FacturaController::guardar()` o un colaborador dedicado): cuando
      `es_rectificativa` y `tipo_rectificacion == diferencias`, tras calcular los totales corregidos
      con `CalculadoraFactura`, persistir en cabecera `base_total`, `cuota_impuesto_total`,
      `cuota_recargo_total`, `irpf_cuota`, `total` como `corregido − original` (y el desglose
      `factura_impuestos` como delta por tipo); en `sustitucion` persistir los corregidos tal cual
      (research.md § 1; data-model.md § 7). Las líneas guardan el detalle corregido.
- [X] T020 [US2] Verificar/ajustar que `FacturaController::update()` (editor de borrador reutilizado)
      preserva `tipo`, `es_rectificativa`, `factura_rectificada_id`, `motivo_rectificacion` y
      `tipo_rectificacion` de la rectificativa al guardar líneas (no los pisa con valores de
      ordinaria); si `guardar()` fuerza `tipo = Ordinaria`, condicionarlo al tipo real de la factura.

**Checkpoint**: emitir una rectificativa numera en serie separada, marca la original y calcula el
delta; tests US2 en verde.

---

## Phase 5: User Story 3 - Visible, trazable e inmutable (Priority: P2)

**Goal**: la rectificativa emitida muestra número/condición/motivo/referencia en listado, detalle y
PDF; es inmutable; la original enlaza a su rectificativa; una `rectificada` no se re-rectifica.

**Independent Test**: emitir una rectificativa y ver su número/condición/motivo/referencia en
listado/detalle/PDF; editar/borrar/re-emitir rechazado; original rectificada bloqueada.

- [X] T021 [P] [US3] Test `tests/Feature/RectificativaInmutabilidadTest.php`: una rectificativa
      `emitida` no se puede editar/actualizar/eliminar/re-emitir (reutiliza guardas de 008); una
      original `rectificada` no se puede editar/borrar ni volver a rectificar (spec US3 AC2; SC-002/SC-005).
- [X] T022 [P] [US3] Test `tests/Feature/RectificativaTenantIsolationTest.php`: crear ≥2 tenants;
      rectificar en A no cambia la numeración de B ni expone/permite referenciar originales de B
      (spec Edge Cases; SC-006; Principio I).
- [X] T023 [US3] En `resources/views/facturas/index.blade.php` (+ su init JS
      `public/js/plugins-init/facturas-datatable.init.js`): mostrar acción "Rectificar" solo para
      facturas `emitida` (no ordinarias en borrador, no ya rectificadas); no ofrecer editar/borrar en
      emitidas/rectificadas/rectificativas emitidas (spec US3; FR-017). Notificaciones vía flash /
      `window.showToast` (CLAUDE.md), sin alertas ad-hoc.
- [X] T024 [US3] En la vista de detalle y en `resources/views/facturas/pdf.blade.php`: para una
      rectificativa mostrar su condición de "rectificativa", el `motivo_rectificacion` y la referencia
      (`numero_completo`) a la factura original; para una original `rectificada`, enlazar/mencionar su
      rectificativa (spec US3 AC1/AC3; FR-017). En diferencias, reflejar los importes como delta.

**Checkpoint**: rectificativas visibles, trazables e inmutables; tests US3 en verde.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: validación final end-to-end y housekeeping.

- [X] T025 [P] Ejecutar `php artisan test --filter=Rectificativa` completo y confirmar los 5 archivos
      de test en verde, sin regresiones en `php artisan test --filter=Factura` (008).
- [X] T026 Recorrer los 10 escenarios de `quickstart.md` y tachar los que falten.
- [X] T027 [P] Revisar que ninguna notificación nueva (crear/emitir/errores de rectificación) use
      markup de alerta ad-hoc — debe pasar por flash + `partials/flash-toastr` o `window.showToast`
      (CLAUDE.md).
- [X] T028 [P] Actualizar `database/factories/FacturaFactory.php` con states de apoyo
      (`emitida()`, `rectificativa()`) si los tests los requieren, para no duplicar setup.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — arranca de inmediato.
- **Foundational (Phase 2)**: depende de Setup (T001-T003, T006 antes de emitir/crear) — BLOQUEA las historias.
- **User Story 1 (Phase 3)**: depende de Foundational (necesita serie por tipo + modelo/columnas).
- **User Story 2 (Phase 4)**: depende de US1 (necesita rectificativas borrador que emitir) y de
  Foundational (T007/T008 emisión extendida).
- **User Story 3 (Phase 5)**: depende de US2 (necesita rectificativas emitidas y original marcada).
- **Polish (Phase 6)**: depende de las historias deseadas completas.

### User Story Dependencies

- **US1 (P1)**: la base — sin crear la rectificativa no hay nada que emitir.
- **US2 (P1)**: depende de US1; junto con US1 forma el MVP fiscal (documento + emisión numerada).
- **US3 (P2)**: depende de US2 (visibilidad/inmutabilidad de lo ya emitido).

### Parallel Opportunities

- T002, T003, T004, T005 (Phase 1) en paralelo tras T001.
- T009, T010 (tests US1) en paralelo entre sí.
- T016, T017, T018 (tests US2, archivos/métodos distintos) en paralelo.
- T021, T022 (tests US3, archivos distintos) en paralelo.

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

1. Phase 1: Setup (columnas, enum, serie "R").
2. Phase 2: Foundational (selección de serie por tipo + emisor extendido).
3. Phase 3 (US1): crear rectificativa borrador desde emitida, con sus tests.
4. Phase 4 (US2): emitir con serie separada + marcar original + delta, con sus tests.
5. **STOP y VALIDAR**: serie separada correcta, original rectificada, delta por diferencias, atomicidad.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → crear rectificativa → validar.
3. US2 → emitir numerada en serie "R" + marcar original → validar (MVP fiscal completo).
4. US3 → visibilidad/trazabilidad/inmutabilidad → validar.
5. Polish → quickstart.md completo, sin regresiones en 008.
