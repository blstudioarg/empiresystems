# Tasks: Pagos y cobros de facturas

**Feature**: 010-pagos-facturas | **Branch**: `010-pagos-facturas`
**Input**: [plan.md](./plan.md) · [spec.md](./spec.md) · [data-model.md](./data-model.md) ·
[contracts/http.md](./contracts/http.md) · [research.md](./research.md) · [quickstart.md](./quickstart.md)

**Nota de flujo (Constitución, Principio IV — NON-NEGOTIABLE)**: aislamiento multi-tenant y la regla
financiera (suma de pagos ≤ total, saldo exacto sin redondeo) son lógica crítica → sus tests se
escriben ANTES de la implementación, deben fallar primero (Red), y luego se implementa hasta verde.
El resto (controlador/UI) sigue un flujo más flexible.

---

## Phase 1: Setup

- [X] T001 Crear migración `database/migrations/2026_07_03_200000_create_pagos_table.php` con las
  columnas de [data-model.md](./data-model.md): `id`, `tenant_id` (unsignedBigInteger, índice),
  `factura_id` (foreignId→facturas), `fecha` (date), `importe` (decimal 12,2), `metodo` (string),
  `referencia` (string 100 nullable), `anulado_at` (dateTime nullable), `timestamps`; índice
  compuesto `(tenant_id, factura_id)`. NO usar `softDeletes`.
- [X] T002 [P] Crear enum `app/Enums/EstadoCobro.php` (string backed): `Pendiente = 'pendiente'`,
  `Parcial = 'parcial'`, `Cobrada = 'cobrada'`.
- [X] T003 [P] Crear excepción `app/Exceptions/PagoInvalidoException.php` (extiende
  `\RuntimeException`), análoga a `FacturaNoEmitibleException`.

**Checkpoint**: migración corre (`php artisan migrate`) y el enum/excepción existen.

---

## Phase 2: Foundational (BLOQUEA las historias)

- [X] T004 Crear modelo `app/Models/Pago.php`: traits `BelongsToTenant`, `HasFactory`;
  `$fillable = [tenant_id, factura_id, fecha, importe, metodo, referencia, anulado_at]`; casts
  `fecha=>date`, `importe=>decimal:2`, `metodo=>FormaPago::class`, `anulado_at=>datetime`; relaciones
  `factura()`, `tenant()`; `scopeVigentes()` (`whereNull('anulado_at')`) y helper `estaAnulado()`.
- [X] T005 [P] Crear `database/factories/PagoFactory.php` con estado por defecto (fecha, importe,
  metodo aleatorio de `FormaPago`, `anulado_at=null`) y un state `anulado()` (`anulado_at=now()`).
- [X] T006 Añadir a `app/Models/Factura.php` (solo lectura, aditivo): relaciones `pagos(): HasMany`
  y `pagosVigentes(): HasMany` (`pagos()->whereNull('anulado_at')`); métodos `montoCobrado(): float`,
  `saldoPendiente(): float` (`round(total - montoCobrado(),2)`) y `estadoCobro(): EstadoCobro`
  (derivado por comparación en céntimos; ver [data-model.md](./data-model.md)). No tocar `estado`.

**Checkpoint**: `Pago` y los accessors de `Factura` existen; base lista para las historias.

---

## Phase 3: User Story 1 — Registrar un cobro contra una factura emitida (P1)

**Meta**: registrar cobros (parciales/totales) contra facturas emitidas, con las reglas de negocio.

**Test independiente**: emitir factura de total conocido, registrar pago parcial, verificar que el
saldo pendiente baja en esa cantidad; verificar los rechazos (borrador, exceso, importe ≤ 0).

### Tests (primero, deben fallar) — Principio IV

- [X] T007 [P] [US1] Test `tests/Feature/PagoRegistroTest.php`: registrar pago total contra factura
  emitida → 201/redirect, pago persistido, `saldoPendiente()==0`, `estadoCobro()==Cobrada`; y pago
  parcial → saldo = total − importe, `estadoCobro()==Parcial`.
- [X] T008 [P] [US1] En el mismo archivo, tests de rechazo: pago contra factura en `borrador` → 422 y
  sin fila en `pagos`; pago que excede el saldo pendiente → 422 y sin fila; importe 0 o negativo →
  422 (validación).

### Implementación

- [X] T009 [US1] Crear `app/Http/Requests/StorePagoRequest.php`: `authorize()=>true`; reglas `fecha`
  `required|date`, `importe` `required|numeric|gt:0`, `metodo` `required|in:transferencia,tarjeta,
  efectivo,domiciliacion`, `referencia` `nullable|string|max:100`.
- [X] T010 [US1] Crear servicio `app/Services/RegistroPagos.php` con `registrar(Factura $factura,
  array $datos): Pago`: dentro de `DB::transaction`, validar `estado===Emitida` (si no,
  `PagoInvalidoException`), validar `sum(pagosVigentes)+importe <= total` en **céntimos** (si no,
  `PagoInvalidoException`), crear el `Pago` con `tenant_id` de la factura y devolverlo.
- [X] T011 [US1] Crear `app/Http/Controllers/PagoController.php` con `store(StorePagoRequest $request,
  string $factura)`: resolver `Factura::findOrFail($factura)` (NO implicit binding — memoria
  `project_tenant_route_binding`); llamar a `RegistroPagos::registrar`; capturar
  `PagoInvalidoException` → 422 (JSON) o flash `error` (HTML); éxito → 201 con `id`,
  `saldo_pendiente`, `estado_cobro` (JSON) o redirect back con flash `success`.
- [X] T012 [US1] Registrar ruta `POST /facturas/{factura}/pagos` → `PagoController@store`
  (name `facturas.pagos.store`) en `routes/web.php`, dentro del grupo `['tenant.context','auth']`.

**Checkpoint (STOP y VALIDAR)**: T007/T008 en verde. Se puede registrar un cobro y el saldo baja;
los rechazos funcionan. US1 entregable de forma independiente.

---

## Phase 4: User Story 2 — Ver estado de cobro e historial (P1)

**Meta**: exponer estado de cobro (pendiente/parcial/cobrada), saldo pendiente e historial de pagos.

**Test independiente**: con 0/1/varios pagos, verificar que el estado y el saldo mostrados coinciden
con lo registrado; verificar el saldo exacto a 0,00 € con cuotas decimales.

### Tests (primero) — la parte de saldo/estado sin redondeo es Principio IV

- [X] T013 [P] [US2] Test `tests/Feature/PagoSaldoEstadoTest.php`: factura sin pagos →
  `estadoCobro()==Pendiente`, saldo = total; con dos pagos parciales → saldo = total − suma, historial
  (`pagos()`) devuelve ambos; cuotas con decimales que suman el total → `saldoPendiente()===0.00`
  exacto y `estadoCobro()==Cobrada`.
- [X] T014 [P] [US2] En `PagoSaldoEstadoTest.php`, test del endpoint del listado: la respuesta JSON de
  `facturas.index` incluye `estado_cobro`, `saldo_pendiente` y `monto_cobrado` por fila con valores
  correctos.

### Implementación

- [X] T015 [US2] En `app/Http/Controllers/FacturaController.php@index` (rama `wantsJson`), añadir a cada
  fila `estado_cobro` (`$factura->estadoCobro()->value`), `saldo_pendiente` y `monto_cobrado`
  (formateados con 2 decimales, como `total`).
- [X] T016 [US2] En `resources/views/facturas/index.blade.php` + `public/js/plugins-init/
  facturas-datatable.init.js`: renderizar un badge de estado de cobro (pendiente/parcial/cobrada) y
  mostrar el saldo pendiente en la fila. Sin alerts ad-hoc; notificaciones con toastr si aplica.
- [X] T017 [US2] Mostrar el historial de pagos (fecha, importe, método, referencia, vigente/anulado)
  en la vista de detalle/PDF de la factura donde ya se muestren sus datos (solo lectura).

**Checkpoint (STOP y VALIDAR)**: T013/T014 en verde; el listado distingue estados de cobro. MVP
(US1+US2) completo.

---

## Phase 5: User Story 3 — Anular un pago registrado por error (P2)

**Meta**: anular (soft) un pago vigente y recalcular saldo/estado; impedir doble anulación.

**Test independiente**: registrar pago, anularlo, verificar que el saldo vuelve al valor previo y que
el pago anulado se distingue de uno vigente; reintentar anular → rechazado.

### Tests (primero)

- [X] T018 [P] [US3] Test `tests/Feature/PagoAnulacionTest.php`: anular un pago vigente → `anulado_at`
  seteado (fila sigue existiendo), `saldoPendiente()` vuelve al valor previo, `estadoCobro()`
  recalculado; anular un pago ya anulado → 422 y sin cambios.

### Implementación

- [X] T019 [US3] Añadir `anular(Pago $pago): Pago` a `app/Services/RegistroPagos.php`: si
  `estaAnulado()` → `PagoInvalidoException`; si no, setear `anulado_at=now()` y guardar (en
  transacción).
- [X] T020 [US3] Añadir `anular(Request $request, string $pago)` a `app/Http/Controllers/
  PagoController.php`: resolver `Pago::findOrFail($pago)` (NO implicit binding); llamar al servicio;
  `PagoInvalidoException` → 422/flash `error`; éxito → 200 con `saldo_pendiente`/`estado_cobro` o
  redirect back con flash `success`.
- [X] T021 [US3] Registrar ruta `POST /pagos/{pago}/anular` → `PagoController@anular`
  (name `pagos.anular`) en `routes/web.php`, dentro del grupo `['tenant.context','auth']`.

**Checkpoint (STOP y VALIDAR)**: T018 en verde; anulación reversible y auditable.

---

## Phase 6: Aislamiento multi-tenant (Principio I — transversal, NON-NEGOTIABLE)

- [X] T022 [P] Test `tests/Feature/PagoTenantIsolationTest.php`: crear ≥2 tenants; usuario del tenant
  A no puede registrar pago contra factura del tenant B (→ 404) ni anular un pago del tenant B (→ 404);
  registrar/anular en A no altera saldos ni pagos de B.

**Checkpoint**: T022 en verde — sin fuga entre tenants.

---

## Phase 7: Polish & validación final

- [X] T023 [P] Ejecutar `php artisan test --filter=Pago` y confirmar que los 4 archivos
  (`PagoRegistroTest`, `PagoSaldoEstadoTest`, `PagoAnulacionTest`, `PagoTenantIsolationTest`) pasan.
- [X] T024 Ejecutar la suite completa `php artisan test` y confirmar 0 regresiones en 008/009.
- [X] T025 Recorrer los escenarios de [quickstart.md](./quickstart.md) y tachar los verificados.
- [X] T026 [P] Revisar que ninguna notificación nueva (registrar/anular/errores) use alerts Bootstrap
  ad-hoc; usar flashes de sesión → toastr / `window.showToast` (CLAUDE.md).
- [X] T027 [P] Actualizar `docs/03-modelo-datos.md`: marcar `pagos` como implementada (feature 010) y
  documentar el estado de cobro derivado + `anulado_at`. Revisar si procede nota en
  `docs/01-arquitectura.md`.

---

## Dependencies & Execution Order

- **Setup (Phase 1)**: sin dependencias — arranca de inmediato.
- **Foundational (Phase 2)**: depende de Setup (T001-T003) — BLOQUEA las historias.
- **US1 (Phase 3)**: depende de Foundational (necesita `Pago` + accessors de `Factura`).
- **US2 (Phase 4)**: depende de Foundational; puede ir tras US1 (reutiliza pagos registrados en tests).
- **US3 (Phase 5)**: depende de US1 (necesita pagos vigentes que anular).
- **Aislamiento (Phase 6)**: depende de US1 (registro) y US3 (anulación) existiendo los endpoints.
- **Polish (Phase 7)**: al final.

### User Story Dependencies

- **US1 (P1)**: la base — sin registrar un cobro no hay nada que ver ni anular.
- **US2 (P1)**: depende de Foundational; junto con US1 forma el MVP.
- **US3 (P2)**: depende de US1.

### Parallel Opportunities

- T002, T003 (Phase 1) en paralelo tras T001.
- T005 en paralelo con T006 (archivos distintos) tras T004.
- T007, T008 (tests US1) en paralelo entre sí.
- T013, T014 (tests US2) en paralelo entre sí.
- Los tests marcados [P] de distintas historias son archivos separados y pueden escribirse en paralelo.

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

1. Phase 1: Setup (tabla `pagos`, enum, excepción).
2. Phase 2: Foundational (modelo `Pago` + accessors de `Factura`).
3. Phase 3 (US1): registrar cobro con reglas, con sus tests primero.
4. Phase 4 (US2): estado de cobro + saldo + historial, con sus tests primero.
5. **STOP y VALIDAR**: saldo correcto, sin redondeo residual, estados derivados coherentes.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → registrar cobro → validar.
3. US2 → ver estado/saldo/historial → validar (MVP de cobros completo).
4. US3 → anular pago → validar.
5. Phase 6 (aislamiento) + Phase 7 (polish) → cierre sin regresiones.
