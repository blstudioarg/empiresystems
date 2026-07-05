# Tasks: Control de stock con proveedores, compras y kardex

**Feature**: `014-control-stock` | **Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

**Input**: plan.md, spec.md, data-model.md, contracts/rutas.md, research.md, quickstart.md

**Tests**: SÍ para la lógica crítica de stock (Principio IV — test-first, Red-Green-Refactor). El
CRUD de UI sigue el flujo de test más flexible del proyecto (tests de aislamiento incluidos).

**Convenciones de rutas**: proyecto Laravel monolítico; rutas reales bajo `app/`, `database/`,
`resources/views/`, `routes/web.php`, `tests/Feature/` (ver Structure Decision en plan.md).

---

## Phase 1: Setup (enums y migraciones compartidas)

- [X] T001 [P] Crear enum `App\Enums\TipoMovimientoStock` (entrada|salida|ajuste) en `app/Enums/TipoMovimientoStock.php`
- [X] T002 [P] Crear enum `App\Enums\OrigenMovimientoStock` (factura|compra|ajuste_manual|inventario|devolucion) en `app/Enums/OrigenMovimientoStock.php`
- [X] T003 [P] Crear enum `App\Enums\EstadoCompra` (borrador|confirmada|anulada) en `app/Enums/EstadoCompra.php`
- [X] T004 [P] Migración `create_proveedores_table` en `database/migrations/2026_07_04_130000_create_proveedores_table.php` (campos y índices según data-model.md; softDeletes)
- [X] T005 [P] Migración `create_compras_table` en `database/migrations/2026_07_04_130001_create_compras_table.php` (fk proveedor RESTRICT, estado, totales, confirmada_at/anulada_at, softDeletes)
- [X] T006 [P] Migración `create_compra_lineas_table` en `database/migrations/2026_07_04_130002_create_compra_lineas_table.php` (fk compra cascade, articulo_id nullable)
- [X] T007 [P] Migración `create_movimientos_stock_table` en `database/migrations/2026_07_04_130003_create_movimientos_stock_table.php` (tipo, cantidad 12,4, stock_resultante 12,4, origen, factura_id/compra_id nullable, motivo, ocurrido_at; índice (tenant_id, articulo_id, ocurrido_at))

**Checkpoint**: `php artisan migrate` aplica las 4 tablas sin errores.

---

## Phase 2: Foundational (bloquea las historias de stock)

**Propósito**: relaciones de `Articulo` con movimientos, necesarias por US1/US2/US3.

- [X] T008 Añadir relación `movimientos(): HasMany` a `App\Models\Articulo` en `app/Models/Articulo.php` (sin cambios de esquema; stock_actual/minimo/gestion_stock ya existen)

**Checkpoint**: fundamentos listos; comienzan las historias en orden de prioridad.

---

## Phase 3: User Story 1 — Kardex de movimientos (Priority: P1) 🎯 MVP

**Goal**: ledger append-only con `stock_resultante` encadenado, `stock_actual` sincronizado
atómicamente, ajustes manuales, y rechazo para servicios/sin-gestión.

**Independent Test**: registrar ajuste entrada+salida sobre un producto con gestión y verificar
resultante encadenado, `stock_actual` sincronizado, inmutabilidad y aislamiento entre tenants.

### Tests (escribir primero — deben fallar)

- [X] T009 [P] [US1] Test `MovimientoStockTest` en `tests/Feature/MovimientoStockTest.php`: resultante encadenado (INV-1), reconstrucción por histórico (INV-2), append-only (INV-3), rechazo de servicio/sin-gestión (FR-004), aislamiento ≥2 tenants (INV-4)
- [X] T010 [P] [US1] Test de concurrencia en `tests/Feature/MovimientoStockConcurrenciaTest.php`: dos movimientos sobre el mismo artículo no descuadran `stock_actual` (lockForUpdate)

### Implementación

- [X] T011 [P] [US1] Modelo `App\Models\MovimientoStock` en `app/Models/MovimientoStock.php` (BelongsToTenant, casts de enums/decimales, belongsTo articulo/compra?/factura?; documentar contrato append-only — sin update/delete públicos)
- [X] T012 [P] [US1] Factory `MovimientoStockFactory` en `database/factories/MovimientoStockFactory.php`
- [X] T013 [US1] Servicio `App\Services\RegistroMovimientoStock` en `app/Services/RegistroMovimientoStock.php`: método `registrar(Articulo, tipo, cantidad, origen, motivo?, factura?/compra?)` dentro de `DB::transaction` con `lockForUpdate()` sobre el artículo, calcula `stock_resultante`, crea el movimiento y guarda `stock_actual`; rechaza servicio/sin-gestión (FR-004..006, D1/D2)
- [X] T014 [US1] Request `StoreAjusteStockRequest` en `app/Http/Requests/StoreAjusteStockRequest.php` (articulo_id producto+gestion_stock del tenant, tipo entrada|salida, cantidad>0, motivo requerido)
- [X] T015 [US1] Controlador `MovimientoStockController` en `app/Http/Controllers/MovimientoStockController.php`: `index` (kardex), `show` (histórico por artículo), `ajuste` (POST vía servicio) — binding de artículo acotado al tenant en el cuerpo (memoria project_tenant_route_binding)
- [X] T016 [US1] Rutas `stock.index/show/ajuste` en `routes/web.php` (sin rutas de edición/borrado de movimientos)
- [X] T017 [P] [US1] Vista `resources/views/stock/index.blade.php` (kardex) + modal de ajuste `resources/views/stock/_ajuste.blade.php` (toastr para feedback, patrón front-guidelines)
- [X] T018 [P] [US1] Enlace en `resources/views/partials/sidebar.blade.php` a `/stock`

**Checkpoint**: US1 entregable y testeable de forma aislada (MVP: registrar y auditar stock).

---

## Phase 4: User Story 4 — Gestión de proveedores (Priority: P1)

**Goal**: CRUD de proveedores con baja lógica, análogo a clientes.

**Independent Test**: alta con NIF/domicilio, edición, baja lógica sin romper compras, aislamiento.

### Tests

- [X] T019 [P] [US4] Test `ProveedorCrudTest` en `tests/Feature/ProveedorCrudTest.php`: alta/edición/listado/baja lógica (FR-007/008) + aislamiento ≥2 tenants

### Implementación

- [X] T020 [P] [US4] Modelo `App\Models\Proveedor` en `app/Models/Proveedor.php` (BelongsToTenant, SoftDeletes, hasMany compras; patrón Cliente sin tipo/recargo)
- [X] T021 [P] [US4] Factory `ProveedorFactory` en `database/factories/ProveedorFactory.php`
- [X] T022 [P] [US4] Requests `StoreProveedorRequest`/`UpdateProveedorRequest` en `app/Http/Requests/` (nombre/razón social, NIF, domicilio con país default ES)
- [X] T023 [US4] Controlador resource `ProveedorController` en `app/Http/Controllers/ProveedorController.php` (binding acotado al tenant; destroy = baja lógica)
- [X] T024 [US4] Rutas resource `proveedores.*` en `routes/web.php`
- [X] T025 [P] [US4] Vistas `resources/views/proveedores/{index,create,edit}.blade.php` (selects provincia/ciudad encadenados, toastr)
- [X] T026 [P] [US4] Enlace en `resources/views/partials/sidebar.blade.php` a `/proveedores`

**Checkpoint**: US4 entregable; desbloquea compras (US2).

---

## Phase 5: User Story 2 — Compras que reponen stock (Priority: P2)

**Goal**: compras borrador/confirmada/anulada; confirmar genera entradas, anular revierte.

**Independent Test**: crear borrador, confirmar (stock sube vía movimiento entrada origen compra),
anular (reverso), líneas libres no mueven stock, confirmada inmutable, aislamiento.

**Dependencies**: US1 (RegistroMovimientoStock) + US4 (Proveedor).

### Tests

- [X] T027 [P] [US2] Test `CompraStockTest` en `tests/Feature/CompraStockTest.php`: confirmar suma stock (FR-012), anular revierte (FR-013), línea libre/sin-gestión no mueve (FR-012), inmutable en confirmada (FR-014), totales server-side (FR-010), aislamiento ≥2 tenants

### Implementación

- [X] T028 [P] [US2] Modelo `App\Models\Compra` en `app/Models/Compra.php` (BelongsToTenant, SoftDeletes, estado EstadoCompra, hasMany lineas/movimientos, belongsTo proveedor)
- [X] T029 [P] [US2] Modelo `App\Models\CompraLinea` en `app/Models/CompraLinea.php` (BelongsToTenant, belongsTo compra/articulo)
- [X] T030 [P] [US2] Factories `CompraFactory`/`CompraLineaFactory` en `database/factories/`
- [X] T031 [US2] Servicio `App\Services\RegistroCompra` en `app/Services/RegistroCompra.php`: `confirmar(Compra)` (valida estado borrador, invoca RegistroMovimientoStock por línea con artículo+gestión, marca confirmada_at) y `anular(Compra)` (valida confirmada, reverso, marca anulada_at); totales calculados server-side (D7)
- [X] T032 [US2] Requests `StoreCompraRequest`/`UpdateCompraRequest` en `app/Http/Requests/` (proveedor del tenant, numero_documento, fecha, líneas con cantidad>0 y precio_unitario; update solo si borrador)
- [X] T033 [US2] Controlador `CompraController` en `app/Http/Controllers/CompraController.php`: index/create/store/show/edit/update/destroy + `confirmar`/`anular` (guardas de estado; binding acotado al tenant)
- [X] T034 [US2] Rutas `compras.*` + `compras.confirmar`/`compras.anular` en `routes/web.php`
- [X] T035 [P] [US2] Vistas `resources/views/compras/{index,create,edit,show}.blade.php` (líneas dinámicas, selector de proveedor y artículo, botones confirmar/anular con estado, toastr)

**Checkpoint**: US2 entregable; ciclo de entrada de stock completo.

---

## Phase 6: User Story 3 — Salida de stock al emitir facturas (Priority: P2)

**Goal**: emitir factura/ticket descuenta stock (salida origen factura); anular/rectificar revierte;
stock negativo permitido y marcado.

**Independent Test**: emitir con línea de artículo con stock descuenta; borrador no; anular/rectificar
revierte; POS descuenta igual; cantidad>stock permitida con resultante negativo.

**Dependencies**: US1 (RegistroMovimientoStock).

### Tests

- [X] T036 [P] [US3] Test `FacturaStockTest` en `tests/Feature/FacturaStockTest.php`: emitir descuenta (FR-015), borrador no descuenta, anular/rectificar revierte (FR-016), stock negativo permitido y marcado (D6), líneas libres no mueven, aislamiento ≥2 tenants
- [X] T037 [P] [US3] Test en `tests/Feature/TicketStockTest.php`: emisión de ticket POS simplificado descuenta stock igual que ordinaria (FR-015 vía EmisorFacturas)

### Implementación

- [X] T038 [US3] Modificar `App\Services\EmisorFacturas::emitir()` en `app/Services/EmisorFacturas.php`: dentro de la transacción existente, por cada línea con artículo producto+gestion_stock invocar `RegistroMovimientoStock` (salida, origen factura, factura_id) — cubre ordinaria y POS por el punto único (D4)
- [X] T039 [US3] Inyectar `RegistroMovimientoStock` en el constructor de `EmisorFacturas` (revisar el binding/servicio y sus usos en `FacturaController` y `RegistroTicket`)
- [X] T040 [US3] Añadir el reverso de stock en la anulación/rectificativa por sustitución en `app/Services/GeneradorRectificativa.php` (y flujo de anulación): movimiento entrada/devolución origen factura por línea con stock (FR-016, D5)
- [X] T041 [P] [US3] Marcar visualmente en `resources/views/stock/index.blade.php` los artículos con `stock_actual` negativo (descuadre a corregir, D6)

**Checkpoint**: US3 entregable; ciclo entrada/salida cerrado.

---

## Phase 7: User Story 5 — Alertas de stock mínimo (Priority: P3)

**Goal**: listar artículos con `stock_actual <= stock_minimo` (mínimo definido).

**Independent Test**: artículo en/bajo umbral aparece; por encima o sin mínimo no aparece.

### Tests

- [X] T042 [P] [US5] Test `StockMinimoTest` en `tests/Feature/StockMinimoTest.php`: en umbral aparece, bajo mínimo aparece, por encima/sin mínimo no (FR-017) + aislamiento

### Implementación

- [X] T043 [US5] Scope/consulta de artículos bajo mínimo en `app/Models/Articulo.php` (`scopeBajoMinimo`) y sección de alertas en `MovimientoStockController::index`
- [X] T044 [P] [US5] Bloque de alertas de reposición en `resources/views/stock/index.blade.php` (badge/lista de artículos a reponer)

**Checkpoint**: todas las historias entregadas.

---

## Phase 8: Polish & Cross-Cutting

- [X] T045 [P] Actualizar `docs/03-modelo-datos.md`: marcar `proveedores`/`compras`/`compra_lineas`/`movimientos_stock` como implementadas (quitar "Fase 2 no implementada" y la nota de EmisorFacturas que no descuenta stock)
- [X] T046 [P] Actualizar `docs/04-front-guidelines.md` si surge un patrón de UI reutilizable (líneas dinámicas de compra, badges de descuadre)
- [X] T047 Enmendar `.specify/memory/constitution.md` vía `/speckit-constitution`: la nota "Compras (Fase 2) … NO se implementan" queda obsoleta (bump PATCH/MINOR según corresponda)
- [X] T048 Ejecutar `php artisan test` completo y `quickstart.md` V1–V5 de punta a punta; confirmar INV-1..4 en verde

---

## Dependencies & Execution Order

```
Setup (T001-T007) ─▶ Foundational (T008)
   │
   ├─▶ US1 Kardex (T009-T018)  ◀── MVP, base de stock
   │        │
   │        ├─▶ US2 Compras (T027-T035)   [también depende de US4]
   │        └─▶ US3 Salida factura (T036-T041)
   │
   ├─▶ US4 Proveedores (T019-T026)  ── independiente, desbloquea US2
   │
   └─▶ US5 Alertas (T042-T044)  ── depende de que exista stock (US1)

Polish (T045-T048) ─▶ al final
```

- **US1 y US4** son P1 e independientes entre sí → pueden ir en paralelo tras Setup.
- **US2** requiere US1 + US4. **US3** requiere US1. **US5** requiere US1.
- **Setup (T001-T007)** casi todo `[P]` (archivos distintos).

## Parallel Execution Examples

- **Setup**: T001, T002, T003, T004, T005, T006, T007 en paralelo (archivos independientes).
- **US1**: T009+T010 (tests) en paralelo; luego T011+T012 en paralelo; T013→T014→T015→T016; T017+T018 en paralelo.
- **US4** completa puede correr en paralelo a **US1** (equipos separados).
- Dentro de **US2**: T028+T029+T030 en paralelo tras los tests.

## Implementation Strategy

- **MVP** = Phase 1 + 2 + **US1** (kardex + ajustes manuales): ya entrega control de stock auditable.
- **Incremento 2**: US4 + US2 (entrada de stock por compras con trazabilidad de proveedor).
- **Incremento 3**: US3 (salida al facturar — cierra el ciclo y corrige el gap actual).
- **Incremento 4**: US5 (alertas) + Polish (docs + constitución).
- Test-first obligatorio en US1/US2/US3 (lógica de stock, Principio IV): los tests se escriben y
  fallan antes de implementar.
