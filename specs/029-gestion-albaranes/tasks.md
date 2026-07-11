# Tasks: Gestión de Albaranes de Entrega

**Feature**: 029 | **Branch**: `029-gestion-albaranes`
**Input**: [plan.md](./plan.md), [spec.md](./spec.md), [data-model.md](./data-model.md),
[research.md](./research.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

> **Test-first (Principio IV, NON-NEGOTIABLE)**: los tests de aislamiento multi-tenant y de
> movimiento de stock (entrega/anulación/consolidación) se escriben antes de la implementación,
> deben fallar primero y luego pasar. El resto del código (UI, endpoints no críticos) sigue un
> flujo más flexible.
>
> **Convención de rutas**: raíz del repo. Modelos en `app/Models/`, servicios en `app/Services/`,
> controladores en `app/Http/Controllers/`, requests en `app/Http/Requests/`, enums en `app/Enums/`,
> migraciones en `database/migrations/`, vistas en `resources/views/`, tests en `tests/Feature/`.
>
> **Servicios existentes que se tocan (aditivo, no se reescriben)**: `RegistroMovimientoStock`
> (nuevo parámetro opcional `albaran`), `EmisorFacturas::moverStock()` (nuevo guard), `Factura`
> (nueva relación `albaranes()`). Ver research.md D3/D4.

---

## Phase 1: Setup

- [x] T001 [P] Crear enum `app/Enums/EstadoAlbaran.php` (string-backed: `borrador`, `entregado`, `facturado`, `anulado`, con `label(): string` en español y `esTerminal(): bool`), según [data-model.md](./data-model.md).
- [x] T002 [P] Añadir el caso `Albaran` a `app/Enums/OrigenMovimientoStock.php` (existente; no tocar los casos ya usados por facturas/compras).
- [x] T003 Añadir el permiso `ver-albaranes` (módulo "CRM") a `app/Support/CatalogoPermisos.php` y re-ejecutar el seeder de permisos para asignarlo al rol Administrador.

---

## Phase 2: Foundational (prerequisitos bloqueantes)

**Bloquea todas las user stories: migraciones, modelos base, y la extensión de
`RegistroMovimientoStock` que usan tanto la entrega (US1/US2) como la anulación (US4).**

- [x] T004 [P] Crear migración `database/migrations/*_create_albaranes_table.php` con columnas e índices de [data-model.md](./data-model.md) (`tenant_id`, `numero` único por tenant, `presupuesto_id` nullable, `cliente_id`, snapshot receptor, `estado`, `fecha_entrega`, régimen, totales, `convertido_a_factura_id`, softDeletes).
- [x] T005 [P] Crear migración `database/migrations/*_create_albaran_lineas_table.php` (espejo de `presupuesto_lineas` en columnas de importe; `albaran_id` cascadeOnDelete; `presupuesto_linea_id` nullable).
- [x] T006 [P] Crear migración `database/migrations/*_add_cantidad_entregada_to_presupuesto_lineas_table.php` (`decimal(12,4) default 0`).
- [x] T007 [P] Crear migración `database/migrations/*_add_albaran_id_to_movimientos_stock_table.php` (fk nullable, `nullOnDelete`).
- [x] T008 [P] Crear migración `database/migrations/*_add_albaran_id_to_factura_lineas_table.php` (fk nullable, `nullOnDelete`, solo trazabilidad).
- [x] T009 [P] Crear modelo `app/Models/Albaran.php` con `BelongsToTenant`, cast de `EstadoAlbaran`, relaciones (`lineas`, `presupuesto`, `cliente`, `facturaConvertida`).
- [x] T010 [P] Crear modelo `app/Models/AlbaranLinea.php` con `BelongsToTenant` y relaciones a `articulo`/`presupuestoLinea`.
- [x] T011 Añadir a `app/Models/PresupuestoLinea.php` el método `cantidadPendiente(): float` (`cantidad - cantidad_entregada`) usado por `RegistroAlbaran` (US1) para validar el tope.
- [x] T012 Extender `app/Services/RegistroMovimientoStock.php::registrar()` con el parámetro opcional `?Albaran $albaran = null` al final de la firma (sin romper las llamadas existentes desde `EmisorFacturas`/`RegistroCompra`) y persistirlo en `MovimientoStock::create()` (research D3).
- [x] T013 Añadir la entrada "Albaranes" al grupo CRM del sidebar en `resources/views/partials/sidebar.blade.php`, protegida por `ver-albaranes`.

**Checkpoint**: migraciones aplican limpio (`php artisan migrate:fresh`), modelos resuelven bajo el
tenant activo, `RegistroMovimientoStock::registrar()` sigue funcionando para compras/facturas sin
el parámetro nuevo.

---

## Phase 3: User Story 1 — Entrega parcial desde un presupuesto aceptado (P1) 🎯 MVP

**Goal**: generar un albarán desde un presupuesto aceptado con cantidades acotadas a lo pendiente
de entrega por línea; al confirmarlo como entregado, mover stock de salida y actualizar el saldo
pendiente del presupuesto.
**Independent test**: presupuesto aceptado con línea de 100 unidades; generar albarán de 40,
confirmarlo, verificar stock −40 y línea con 60 pendientes; generar segundo albarán de 60,
verificar línea en 0 pendiente; intentar un tercero → rechazado.

### Tests (escribir primero — Principio IV)

- [x] T014 [P] [US1] Test de aislamiento multi-tenant en `tests/Feature/Albaranes/AlbaranTenantScopeTest.php` (≥2 tenants, un tenant no ve albaranes del otro) — debe fallar antes de implementar.
- [x] T015 [P] [US1] Test de entrega parcial en `tests/Feature/Albaranes/AlbaranEntregaParcialTest.php`: dos albaranes consecutivos sobre la misma línea de presupuesto respetan el tope pendiente; un tercero que exceda el saldo se rechaza (FR-003, SC-001).
- [x] T016 [P] [US1] Test de movimiento de stock en `tests/Feature/Albaranes/AlbaranMovimientoStockTest.php`: confirmar como `entregado` genera movimiento de salida exacto por artículo con `gestion_stock`, trazado al albarán (FR-005, SC-002).

### Implementación

- [x] T017 [US1] Crear `app/Services/RegistroAlbaran.php` (único punto de escritura): crear/editar albarán en borrador invocando `CalculadoraFactura`; si tiene `presupuesto_id`, valida cada línea contra `presupuestoLinea->cantidadPendiente()` (T011); numeración propia `A-{anio}-{n}` no fiscal.
- [x] T018 [US1] Crear `app/Services/EntregadorAlbaran.php`: transición `borrador → entregado` — fija `fecha_entrega`, invoca `RegistroMovimientoStock::registrar(tipo: Salida, origen: Albaran, albaran: ...)` (T012) por cada línea con artículo `producto`+`gestion_stock`, e incrementa `cantidad_entregada` en cada `presupuesto_linea` referenciada, todo en una transacción.
- [x] T019 [P] [US1] Crear `app/Http/Requests/StoreAlbaranRequest.php` y `UpdateAlbaranRequest.php` (cliente_id o presupuesto_id; ≥1 línea; update solo si `borrador`).
- [x] T020 [US1] Crear `app/Http/Controllers/AlbaranController.php` con `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` y `estado` (soportando por ahora la transición `entregado`) por [contracts/albaranes.md](./contracts/albaranes.md).
- [x] T021 [US1] Registrar las rutas de albaranes en `routes/web.php` protegidas por `can:ver-albaranes`.
- [x] T022 [P] [US1] Crear vistas `resources/views/albaranes/` (index con cards informativas + datatable, create/_form con selección de líneas de presupuesto y cantidad editable acotada al pendiente, show con trazabilidad a su presupuesto de origen), siguiendo `docs/04-front-guidelines.md`.
- [x] T023 [US1] Añadir la acción "Generar albarán" en `resources/views/presupuestos/show.blade.php` (visible solo si el presupuesto está `aceptado` y tiene líneas con saldo pendiente).

**Checkpoint**: US1 entregable de forma independiente — un presupuesto aceptado se puede entregar
en uno o varios albaranes, moviendo stock en el momento correcto.

---

## Phase 4: User Story 3 — Consolidar varios albaranes en una única factura (P1) 🎯 MVP

**Goal**: seleccionar N albaranes entregados del mismo cliente y convertirlos en una única factura
borrador, sin duplicar el movimiento de stock que ya ocurrió al entregarlos.
**Independent test**: 2-3 albaranes entregados del mismo cliente → convertir → factura con todas
las líneas identificadas por su albarán de origen, mismo stock antes/después de la conversión;
mezclar un albarán de otro cliente → rechazado.

> Nota de prioridad: US3 es P1 junto con US1 (forman el MVP demostrable: sin poder facturar los
> albaranes, la feature no cierra el embudo). Depende de que existan albaranes `entregado` (US1).

### Tests (escribir primero — Principio IV)

- [x] T024 [P] [US3] Test crítico en `tests/Feature/Albaranes/ConversorAlbaranesFacturaTest.php`: convertir N albaranes del mismo cliente crea una factura con la suma exacta de líneas/importes y el `stock_actual` no cambia respecto a antes de la conversión (FR-008/FR-010, SC-003).
- [x] T025 [P] [US3] Test de guarda en el mismo archivo (o `AlbaranConversionGuardTest.php`): rechaza la conversión si los albaranes son de distinto cliente, o si alguno no está `entregado`/ya está facturado (FR-009, SC-004).

### Implementación

- [x] T026 [US3] Añadir a `app/Models/Factura.php` la relación `albaranes(): HasMany` (`Albaran::class`, `convertido_a_factura_id`) — solo lectura, no cambia columnas de `facturas`.
- [x] T027 [US3] Añadir el guard en `app/Services/EmisorFacturas.php::moverStock()`: si `$factura->albaranes()->exists()`, retornar sin generar movimientos (research D4) — no tocar la rama de rectificativas ni la de facturas ordinarias sin albarán.
- [x] T028 [US3] Crear `app/Services/ConversorAlbaranesFactura.php::convertir(Collection $albaranes): Factura` — valida mismo cliente + todos `entregado`/no facturados (con bloqueo de fila), crea `Factura` borrador con líneas de todos los albaranes (`factura_lineas.albaran_id` de origen), enlaza `albaranes.convertido_a_factura_id`, marca cada albarán `facturado`.
- [x] T029 [US3] Añadir la acción `convertir` a `AlbaranController` (recibe `albaran_ids[]`, delega en `ConversorAlbaranesFactura`, redirige a `facturas.edit`).
- [x] T030 [US3] Registrar la ruta `POST /albaranes/convertir` en `routes/web.php` protegida por `can:ver-albaranes`.
- [x] T031 [P] [US3] Añadir selección múltiple de filas (checkboxes) al datatable de `resources/views/albaranes/index.blade.php` con botón "Convertir a factura" habilitado solo cuando la selección es válida (mismo cliente, todos entregados).

**Checkpoint**: US3 entregable — varios albaranes entregados de un cliente se consolidan en una
factura borrador sin duplicar movimiento de stock. Junto con US1, MVP completo.

---

## Phase 5: User Story 2 — Albarán directo a cliente, sin presupuesto (P2)

**Goal**: crear un albarán contra un cliente sin presupuesto de origen, para ventas/entregas
ad-hoc, con el mismo comportamiento de stock que un albarán derivado de presupuesto.
**Independent test**: desde la ficha de un cliente, crear albarán con líneas propias, confirmarlo
como entregado, verificar mismo efecto de stock que US1 sin vínculo a ningún presupuesto.

### Tests (escribir primero — Principio IV)

- [x] T032 [P] [US2] Test en `tests/Feature/Albaranes/AlbaranDirectoClienteTest.php`: crear y entregar un albarán sin `presupuesto_id` mueve stock igual que uno derivado de presupuesto, y su ficha no muestra presupuesto de origen (FR-002).

### Implementación

- [x] T033 [US2] Ajustar `RegistroAlbaran` (T017) para aceptar líneas libres (`articulo_id?, concepto, cantidad, precio_unitario, descuento_porcentaje?, tipo_impositivo`) cuando no hay `presupuesto_id`, tomando régimen impositivo del cliente/tenant.
- [x] T034 [US2] Añadir la acción "+ Nuevo albarán" en `resources/views/clientes/index.blade.php` (mismo lugar donde ya está "+ Nueva oportunidad"), enlazando a `albaranes.create?cliente_id=...`.
- [x] T035 [P] [US2] Ajustar `resources/views/albaranes/create.blade.php` para alternar entre modo "desde presupuesto" (líneas precargadas, cantidad acotada) y modo "directo a cliente" (líneas libres, igual que `presupuestos/create`).

**Checkpoint**: US2 entregable — venta directa sin presupuesto previo cubierta.

---

## Phase 6: User Story 4 — Anular un albarán entregado antes de facturarlo (P3)

**Goal**: revertir un albarán `entregado` no facturado, generando el movimiento de stock inverso y
reponiendo el saldo pendiente de su presupuesto de origen si lo tiene.
**Independent test**: confirmar un albarán (stock baja), anularlo (stock vuelve al valor previo,
línea de presupuesto recupera el pendiente); intentar anular uno ya facturado → rechazado.

### Tests (escribir primero — Principio IV)

- [x] T036 [P] [US4] Test en `tests/Feature/Albaranes/AlbaranAnulacionTest.php`: anular un albarán `entregado` revierte exactamente el stock movido y repone `cantidad_entregada` en las líneas de presupuesto de origen (FR-006, SC-002).
- [x] T037 [P] [US4] Test en el mismo archivo: anular un albarán `facturado` se rechaza (FR-007).

### Implementación

- [x] T038 [US4] Crear `app/Services/AnuladorAlbaran.php`: transición `entregado → anulado` — invoca `RegistroMovimientoStock::registrar(tipo: Entrada, origen: Devolucion, albaran: ...)` por cada línea con stock, y decrementa `cantidad_entregada` de vuelta en las líneas de presupuesto correspondientes, en una transacción; rechaza si ya está `facturado`.
- [x] T039 [US4] Extender `AlbaranController::estado` (T020) para soportar la transición `anulado`.
- [x] T040 [P] [US4] Añadir la acción "Anular" en `resources/views/albaranes/show.blade.php` e `index.blade.php`, visible solo cuando `estado = entregado`.

**Checkpoint**: todas las user stories funcionan de forma independiente.

---

## Phase 7: Polish & Cross-Cutting

- [x] T041 [P] Registrar acciones de negocio en `logs_actividad` vía `RegistradorActividad` (alta, entrega, anulación y conversión de albaranes), siguiendo el patrón existente.
- [x] T042 [P] Documentar las tablas/columnas nuevas (`albaranes`, `albaran_lineas`,
  `presupuesto_lineas.cantidad_entregada`, `movimientos_stock.albaran_id`,
  `factura_lineas.albaran_id`) en `docs/03-modelo-datos.md`.
- [x] T043 [P] Crear `AlbaranFactory`/`AlbaranLineaFactory` (forzando `tenant_id` del tenant activo — ver pitfall de factories con `tenant_id`) para tests y seeding de demo.
- [x] T044 [P] Crear la guía in-app `resources/views/ayuda/albaranes.blade.php` y enlazarla en la vista de albaranes (sección "Ayuda de esta pantalla" del sidebar).
- [x] T045 Ejecutar el quickstart ([quickstart.md](./quickstart.md)) de punta a punta (4 escenarios) y la suite completa (`php artisan test`); confirmar SC-001..SC-005 en verde.

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** bloquean todo, en particular la extensión de
  `RegistroMovimientoStock` (T012), que usan US1, US2 y US4.
- **US1 (P1)** y **US3 (P1)** forman el MVP; US3 depende de que existan albaranes `entregado`
  generados por US1, así que va después en el orden de construcción aunque ambas sean P1.
- **US2 (P2)** reutiliza `RegistroAlbaran`/`AlbaranController` de US1 (extiende, no duplica); puede
  construirse en paralelo con US3 una vez completado US1.
- **US4 (P3)** depende de US1 (necesita albaranes `entregado` para anular) pero es independiente de
  US2/US3.
- **Polish (Phase 7)** al final.

## Parallel Opportunities

- Phase 2: T004–T010 (migraciones + modelos) son `[P]` entre sí (archivos distintos).
- Dentro de cada US: los tests `[P]` se escriben juntos; Requests y vistas `[P]` son paralelizables.
- US2 y US3 pueden desarrollarse por dos personas en paralelo una vez completado el checkpoint de US1.

## MVP Scope

**MVP mínimo demostrable**: Phase 1 + Phase 2 + **US1 (entrega parcial)** + **US3 (consolidación a
factura)**. Cubre el motivo original de la feature: entregar en partes y facturar varias entregas
juntas. US2 (venta directa) y US4 (anulación) son mejoras que se entregan en la siguiente
iteración sin romper el MVP.

## Format validation

Todas las tareas siguen `- [ ] TID [P?] [Story?] descripción con ruta`. Las de Setup/Foundational/
Polish no llevan etiqueta de historia; las de fase de historia llevan `[US1]`/`[US2]`/`[US3]`/`[US4]`.
