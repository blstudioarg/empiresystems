# Tasks: Formulario de factura adaptado al régimen impositivo del tenant

**Feature**: 013-facturas-regimen-impositivo | **Branch**: `013-facturas-regimen-impositivo`

**Input**: Design documents from `specs/013-facturas-regimen-impositivo/`
**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/regimen-view-contract.md](contracts/regimen-view-contract.md), [quickstart.md](quickstart.md)

**Tests**: Incluidos y **obligatorios** (Principio IV test-first + SC-005). Los tests de cálculo de
impuestos/aislamiento se escriben antes que la implementación, deben fallar primero y luego pasar.

## Convenciones

- Cálculo definitivo siempre en backend (Principio III); el JS es solo previsualización.
- El régimen es siempre el de `tenant()` activo (Principio I).
- Sin cambios de esquema ni migraciones.

---

## Phase 1: Setup

- [X] T001 Verificar el punto de partida corriendo la suite existente en verde antes de tocar nada: `php artisan test --filter=Factura` y `php artisan test --filter=Pos`, para tener una línea base sin regresiones.

---

## Phase 2: Foundational (bloquea todas las user stories)

**Objetivo**: crear la fuente única del "payload de régimen" que consumirán vistas y JS, según
[contracts/regimen-view-contract.md](contracts/regimen-view-contract.md) §1.

- [X] T002 Añadir a `app/Enums/RegimenImpositivo.php` un método `label(): string` (devuelve `IVA`/`IGIC`/`IPSI`) y `tipoPorDefecto(): float` (21 IVA, 7 IGIC, 0 IPSI), sin romper los casos existentes.
- [X] T003 Añadir en `app/Support/TiposImpositivos.php` un método estático `payloadVista(RegimenImpositivo $regimen): array` que devuelva `['value','label','tiposValidos','tipoPorDefecto','aplicaRecargo']` reutilizando `validosPara()` y `RegimenImpositivo::label()/tipoPorDefecto()`; `aplicaRecargo = $regimen === RegimenImpositivo::Iva`.
- [X] T004 [P] Añadir test unitario `tests/Unit/TiposImpositivosPayloadTest.php` que afirme el payload correcto para IVA (tiposValidos `[0,4,10,21]`, aplicaRecargo true), IGIC (`[0,3,7,9.5,15,20]`, aplicaRecargo false) e IPSI (`tiposValidos null`, aplicaRecargo false).

**Checkpoint**: existe una única función que produce el payload de régimen y está cubierta por test.

---

## Phase 3: User Story 1 — Emisión de factura en tenant IGIC (Priority: P1) 🎯 MVP

**Goal**: que un tenant de Canarias emita una factura viendo tipos y etiqueta IGIC, sin "IVA".

**Independent Test**: tenant IGIC → nueva factura → selector ofrece tipos IGIC, cabecera "IGIC %",
línea al 7 % → emite → factura con `regimen_impositivo = igic` y desglose "IGIC 7%".

### Tests (escribir primero, deben fallar)

- [X] T005 [P] [US1] Crear `tests/Feature/FacturaEmisionIgicTest.php`: con tenant IGIC, emitir factura con una línea al 7 %; afirmar base/cuota/total correctos y que existe un `FacturaImpuesto` con `tipo_impuesto = igic` y `porcentaje = 7`.
- [X] T006 [P] [US1] En el mismo test (o `FacturaRegimenTenantIsolationTest.php`), afirmar aislamiento: el `regimen_impositivo` congelado en la factura es el del tenant activo y no el de un segundo tenant creado con régimen distinto (Principio I).

### Implementación

- [X] T007 [US1] `app/Http/Controllers/FacturaController.php`: en `create()` y `edit()` pasar `'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo)` al array de la vista.
- [X] T008 [US1] `resources/views/facturas/create.blade.php`: cambiar la cabecera `<th>IVA %</th>` (línea ~510) por `{{ $regimen['label'] }} %`.
- [X] T009 [US1] `resources/views/facturas/create.blade.php`: en el `#linea-template` (línea ~573) renderizar condicionalmente un `<select class="form-control linea-tipo">` con `$regimen['tiposValidos']` cuando no sea `null`, o mantener el `<input type="number">` libre cuando sea `null` (IPSI). Preservar clase `linea-tipo` y compatibilidad con el nombrado dinámico del submit.
- [X] T010 [US1] `resources/views/facturas/create.blade.php`: inyectar `regimen: @json($regimen)` dentro de `window.facturaFormState` (línea ~585), según contrato §2.
- [X] T011 [US1] `public/js/facturas-form.js`: en `crearFilaLinea()` usar `state.regimen.tipoPorDefecto` en vez del literal `21` (línea 38).
- [X] T012 [US1] `public/js/facturas-form.js`: en `recalcularPreview()` cambiar la clave de desglose `'IVA/IGIC ' + tipo + '%'` por `state.regimen.label + ' ' + tipo + '%'` (línea 98).

**Checkpoint**: T005–T006 pasan en verde; formulario IGIC operativo de punta a punta.

---

## Phase 4: User Story 2 — El recargo de equivalencia no aparece fuera de IVA (Priority: P1)

**Goal**: recargo solo en IVA, tanto en preview como en emisión.

**Independent Test**: tenant IGIC + cliente en recargo → sin fila ni importe de recargo; tenant IVA +
cliente en recargo → recargo 5,2 % visible.

### Tests (escribir primero, deben fallar)

- [X] T013 [P] [US2] Crear `tests/Feature/FacturaRecargoRegimenTest.php`: con tenant IGIC y cliente marcado en recargo, emitir factura y afirmar que **no** existe ningún `FacturaImpuesto` con `tipo_impuesto = recargo`; con tenant IVA y cliente en recargo, afirmar que **sí** existe el recargo esperado.

### Implementación

- [X] T014 [US2] `public/js/facturas-form.js`: en `recalcularPreview()` condicionar el bloque de recargo (línea 101) a `state.regimen.aplicaRecargo === true` **además** del flag de cliente en recargo, de modo que en IGIC/IPSI no se calcule ni se muestre fila de recargo.
- [X] T015 [US2] Verificar (sin cambios de código esperados) que el backend ya excluye el recargo fuera de IVA en `app/Services/CalculadoraFactura.php` (`aplicaRecargoEfectivo`, línea 20); si algún camino de emisión no lo respeta, corregirlo. Dejar constancia en el commit.

**Checkpoint**: T013 en verde; recargo coherente entre preview y emisión en los tres regímenes.

---

## Phase 5: User Story 3 — POS/ticket respeta el régimen (Priority: P2)

**Goal**: el POS muestra etiqueta e impuestos del régimen del tenant.

**Independent Test**: tenant IGIC → POS → etiquetas "IGIC", desglose del tipo IGIC del artículo,
ticket emitido correctamente.

### Tests (escribir primero, deben fallar)

- [X] T016 [P] [US3] Crear/extender `tests/Feature/PosTicketRegimenTest.php`: con tenant IGIC emitir un ticket y afirmar que el desglose usa `tipo_impuesto = igic`; afirmar aislamiento del régimen respecto a un segundo tenant.

### Implementación

- [X] T017 [US3] `app/Http/Controllers/PosController.php`: en `create()` pasar `'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo)` a la vista.
- [X] T018 [US3] `resources/views/pos/create.blade.php`: reemplazar las etiquetas literales "IVA incl." (líneas ~175, ~184) por `{{ $regimen['label'] }} incl.` e inyectar `regimen: @json($regimen)` en `window.posState` (línea ~228).
- [X] T019 [US3] `public/js/pos-form.js`: usar `state.regimen.label` en las etiquetas de impuesto donde hoy se asume IVA; confirmar que no se calcula recargo (sin cambios de comportamiento en ese punto).

**Checkpoint**: T016 en verde; POS coherente con el formulario de factura para el mismo régimen.

---

## Phase 6: User Story 4 — IPSI con entrada de tipo libre (Priority: P3)

**Goal**: tenant IPSI introduce tipo libre, etiqueta "IPSI", sin recargo.

**Independent Test**: tenant IPSI → nueva factura → campo de tipo libre, cabecera "IPSI %", sin
recargo; emisión con un tipo libre (p. ej. 4 %) correcta.

### Tests (escribir primero, deben fallar)

- [X] T020 [P] [US4] Extender `tests/Feature/FacturaEmisionIgicTest.php` o crear `FacturaEmisionIpsiTest.php`: con tenant IPSI emitir factura con un tipo libre (4 %); afirmar `FacturaImpuesto` con `tipo_impuesto = ipsi`, `porcentaje = 4`, y ausencia de recargo.

### Implementación

- [X] T021 [US4] Verificar que el render condicional de T009 (input libre cuando `tiposValidos === null`) cubre IPSI correctamente y que el `@selected()`/repoblado `old()` funciona con el input libre; ajustar la Blade si el input libre no repobla bien tras error de validación.
- [X] T022 [US4] `public/js/facturas-form.js`: confirmar que con `state.regimen.tiposValidos === null` la fila usa entrada libre y `tipoPorDefecto` (0) como valor inicial, sin ofrecer un select vacío.

**Checkpoint**: T020 en verde; los tres regímenes cubiertos de punta a punta.

---

## Phase 7: Polish & Cross-Cutting

- [ ] T023 [P] Ejecutar el quickstart manual completo ([quickstart.md](quickstart.md)) escenarios 1–5 y anotar resultados; verificación visual de UI con Chrome DevTools **solo tras pedir confirmación al usuario** (regla del proyecto).
- [X] T024 [P] Revisar no-regresión en IVA (SC-003): tenant IVA con tipos 0/4/10/21, default 21 y recargo 5,2/1,4/0,5 idénticos al comportamiento previo.
- [X] T025 Ejecutar suite completa `php artisan test` y confirmar verde; correr `/code-review` sobre el diff antes de cerrar.
- [X] T026 Confirmar que **no** hizo falta tocar `docs/02-facturacion-espana.md` ni el modelo de datos (regímenes ya documentados); dejarlo explícito en el mensaje de commit.

---

## Dependencies & Execution Order

- **Setup (T001)** → **Foundational (T002–T004)** bloquean todas las user stories.
- **US1 (P1, T005–T012)** es el MVP y debe ir primero tras Foundational.
- **US2 (P1, T013–T015)** depende de US1 (comparte `facturas-form.js`/state).
- **US3 (P2, T016–T019)** es independiente de US1/US2 salvo por el payload Foundational; puede ir en paralelo a US2 (archivos distintos: POS vs facturas).
- **US4 (P3, T020–T022)** depende del render condicional introducido en US1 (T009).
- **Polish (T023–T026)** al final.

### Oportunidades de paralelismo

- T004 (test unit payload) en paralelo con T002/T003 una vez definida la firma.
- Tras Foundational: bloque US3 (POS: `PosController`, `pos/create.blade.php`, `pos-form.js`) puede
  avanzar en paralelo al bloque US1/US2 (facturas), al no compartir archivos.
- Tests marcados [P] (T005, T006, T013, T016, T020) se pueden escribir en paralelo por tocar archivos
  de test distintos.

## Implementation Strategy

- **MVP** = Phase 1 + Phase 2 + **User Story 1** (T001–T012): habilita facturar en Canarias (IGIC),
  que es el motivo de negocio inmediato. Entregable y demostrable por sí solo.
- Incrementos siguientes: US2 (recargo correcto) → US3 (POS) → US4 (IPSI) → Polish.

## Format validation

Todas las tareas siguen `- [ ] Txxx [P?] [US?] descripción + ruta de archivo`. Setup/Foundational/Polish
sin etiqueta de story; fases de user story con `[US1]`..`[US4]`.
